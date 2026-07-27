<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConsentimientoModel;
use App\Models\EnvioModel;
use App\Models\HuespedModel;
use App\Models\PlantillaModel;
use RuntimeException;

/**
 * Monta los mensajes, los encola y los manda.
 *
 * **Aquí está la puerta del consentimiento, y es la única.** Todo lo que sale
 * del hotel pasa por `encolar()`, y `encolar()` pregunta antes. Si esa
 * comprobación estuviera repartida por los controladores, el día que alguien
 * añada un envío nuevo se le olvidaría — y ese envío sería justo el que llegue
 * a quien se dio de baja.
 *
 * De momento solo sale correo. El canal se lleva de punta a punta para que
 * WhatsApp entre después sin tocar nada de esto: lo que falta ahí no es código,
 * es una cuenta de empresa verificada en Meta.
 */
class Mensajero
{
    private EnvioModel $envios;
    private PlantillaModel $plantillas;
    private ConsentimientoModel $consentimientos;

    public function __construct()
    {
        $this->envios          = new EnvioModel();
        $this->plantillas      = new PlantillaModel();
        $this->consentimientos = new ConsentimientoModel();
    }

    // ── Encolar ─────────────────────────────────────────────────────────

    /**
     * Prepara un mensaje y lo pone en la cola.
     *
     * @param array{reserva_id?: ?int, cuando?: ?string, canal?: string,
     *              automatizacion_id?: ?int, unica?: bool} $opciones
     *
     * @return int|null El id del envío, o `null` si no se debía mandar.
     *                  `null` no es un error: es la respuesta correcta cuando
     *                  alguien no lo ha autorizado.
     */
    public function encolar(int $huespedId, string $clave, array $opciones = []): ?int
    {
        $huesped = (new HuespedModel())->find($huespedId);

        if ($huesped === null || $huesped['estado'] !== 'activo') {
            return null;
        }

        $canal      = $opciones['canal'] ?? 'email';
        $reservaId  = $opciones['reserva_id'] ?? null;
        $plantilla  = $this->plantillas->buscar($clave, (string) ($huesped['idioma'] ?: 'es'), $canal);

        if ($plantilla === null) {
            log_message('warning', 'Mensajero: no hay plantilla «{c}» para el canal {ca}', ['c' => $clave, 'ca' => $canal]);

            return null;
        }

        // Sin dirección no hay mensaje. Encolarlo para que falle tres veces
        // solo ensucia la pantalla de envíos.
        $destino = $canal === 'email' ? trim((string) $huesped['email']) : trim((string) $huesped['telefono']);

        if ($destino === '' || ($canal === 'email' && ! filter_var($destino, FILTER_VALIDATE_EMAIL))) {
            return null;
        }

        // ── La puerta ───────────────────────────────────────────────────
        if (! $this->consentimientos->permite($huespedId, $plantilla['finalidad'], $canal)) {
            return null;
        }

        // Lo que no se manda dos veces por la misma reserva
        if (($opciones['unica'] ?? true) && $reservaId !== null && $this->envios->yaExiste($reservaId, $clave)) {
            return null;
        }

        $token   = bin2hex(random_bytes(24));
        $datos   = $this->contexto($huesped, $reservaId, $token);
        $asunto  = $this->rellenar((string) $plantilla['asunto'], $datos);
        $cuerpo  = $this->rellenar((string) $plantilla['cuerpo'], $datos);

        $this->envios->insert([
            'huesped_id'      => $huespedId,
            'reserva_id'      => $reservaId,
            'plantilla_clave' => $clave,
            'canal'           => $canal,
            'finalidad'       => $plantilla['finalidad'],
            'destino'         => $destino,
            'asunto'          => mb_substr($asunto, 0, 200),
            'cuerpo'          => $cuerpo,
            'estado'          => 'pendiente',
            'programado_para' => $opciones['cuando'] ?? date('Y-m-d H:i:s'),
            'token'           => $token,
            'automatizacion_id' => $opciones['automatizacion_id'] ?? null,
        ]);

        return (int) $this->envios->getInsertID();
    }

    // ── Mandar ──────────────────────────────────────────────────────────

    /**
     * Vacía la cola.
     *
     * @return array{enviados: int, fallidos: int, saltados: int}
     */
    public function procesar(int $tope = 40): array
    {
        $enviados = 0;
        $fallidos = 0;
        $saltados = 0;

        foreach ($this->envios->pendientes($tope) as $envio) {
            // Se vuelve a preguntar justo antes de mandar. Entre que se encoló
            // un pre-arrival y le toca salir pueden pasar tres días, y en tres
            // días alguien puede darse de baja.
            if ($envio['huesped_id'] !== null
                && ! $this->consentimientos->permite((int) $envio['huesped_id'], $envio['finalidad'], $envio['canal'])) {
                $this->envios->update($envio['id'], [
                    'estado' => 'cancelado',
                    'error'  => 'Se dio de baja antes de que le tocara salir.',
                ]);
                $saltados++;

                continue;
            }

            if ($envio['canal'] !== 'email') {
                // WhatsApp todavía no. Se deja en la cola en vez de darlo por
                // enviado: dar por enviado lo que no salió es peor que un fallo.
                $saltados++;

                continue;
            }

            $this->enviarUno($envio) ? $enviados++ : $fallidos++;
        }

        return ['enviados' => $enviados, 'fallidos' => $fallidos, 'saltados' => $saltados];
    }

    private function enviarUno(array $envio): bool
    {
        $correo = new Correo();

        if (! $correo->configurado()) {
            $this->envios->anotarFallo(
                (int) $envio['id'],
                'No hay servidor de correo configurado en Administración.',
                (int) $envio['intentos']
            );

            return false;
        }

        $ok = $correo->enviar([
            'tipo'       => 'crm:' . $envio['plantilla_clave'],
            'para'       => $envio['destino'],
            'asunto'     => $envio['asunto'],
            'vista'      => 'mensaje',
            'reserva_id' => $envio['reserva_id'],
            'datos'      => [
                'cuerpo'    => $envio['cuerpo'],
                // El enlace de baja solo en lo que no es operativo: poner
                // «date de baja» en la confirmación de su propia reserva
                // confunde y no significa nada.
                'baja'      => $envio['finalidad'] === 'operativo'
                    ? null
                    : site_url('baja/' . $envio['token']),
                'finalidad' => $envio['finalidad'],
            ],
        ]);

        if ($ok) {
            $this->envios->anotarEnviado((int) $envio['id']);

            return true;
        }

        $this->envios->anotarFallo((int) $envio['id'], 'El servidor de correo no lo aceptó.', (int) $envio['intentos']);

        return false;
    }

    // ── Variables ───────────────────────────────────────────────────────

    /**
     * Sustituye `{{variable}}` por su valor.
     *
     * Lo que no exista se queda en blanco. Un correo que dice «Hola {{nombre}}»
     * es peor que uno que dice «Hola»: el primero delata que nadie lo miró.
     */
    public function rellenar(string $texto, array $datos): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            static fn (array $m): string => (string) ($datos[strtolower($m[1])] ?? ''),
            $texto
        );
    }

    /**
     * Los valores de las variables para un huésped y una reserva.
     *
     * @return array<string, string>
     */
    public function contexto(array $huesped, ?int $reservaId, ?string $token = null): array
    {
        $hotel = config('Hotel');

        $datos = [
            'nombre'    => (string) $huesped['nombre'],
            'apellidos' => (string) $huesped['apellidos'],
            'hotel'     => (string) $hotel->nombre,
            'telefono'  => (string) ($hotel->telefono ?? ''),
            'whatsapp'  => (string) ($hotel->whatsapp ?? ''),
            'codigo'    => '', 'entrada' => '', 'salida' => '', 'noches' => '',
            'cabana'    => '', 'adultos' => '', 'total' => '', 'saldo' => '',
            'portal'    => '', 'registro' => '', 'encuesta' => '', 'pago' => '',
        ];

        if ($reservaId === null) {
            return $datos;
        }

        $reserva = db_connect()->query(
            'SELECT r.*, u.nombre AS unidad_nombre
             FROM reservas r LEFT JOIN unidades u ON u.id = r.unidad_id
             WHERE r.id = ?',
            [$reservaId]
        )->getRowArray();

        if ($reserva === null) {
            return $datos;
        }

        $noches = (int) ((strtotime($reserva['fecha_salida']) - strtotime($reserva['fecha_entrada'])) / 86400);
        $saldo  = (new \App\Models\FolioModel())->saldo($reservaId);

        $datos['codigo']  = (string) $reserva['codigo'];
        $datos['entrada'] = date('d/m/Y', strtotime($reserva['fecha_entrada']));
        $datos['salida']  = date('d/m/Y', strtotime($reserva['fecha_salida']));
        $datos['noches']  = (string) $noches;
        $datos['cabana']  = (string) ($reserva['unidad_nombre'] ?? 'Por asignar');
        $datos['adultos'] = (string) $reserva['adultos'];
        $datos['total']   = '$' . number_format((float) $reserva['total'], 0, ',', '.');
        $datos['saldo']   = '$' . number_format(max(0, $saldo), 0, ',', '.');
        $datos['pago']    = site_url('pago/reserva/' . $reserva['codigo'] . '/total');

        $acceso = db_connect()->table('portal_accesos')
            ->where('reserva_id', $reservaId)->where('revocado', 0)->get()->getRowArray();

        if ($acceso !== null) {
            $datos['portal']   = site_url('estancia/' . $acceso['token']);
            $datos['encuesta'] = site_url('estancia/' . $acceso['token'] . '/encuesta');
        }

        $registro = db_connect()->table('registros')->where('reserva_id', $reservaId)->get()->getRowArray();

        if ($registro !== null) {
            $datos['registro'] = site_url('registro/' . $registro['token']);
        }

        unset($token);

        return $datos;
    }

    // ── Baja con un clic ────────────────────────────────────────────────

    /**
     * Retira el consentimiento desde el enlace del propio correo.
     *
     * Un clic, sin pedir contraseña ni hacer preguntas. Quien no puede darse de
     * baja fácil marca el correo como spam, y a partir de ahí dejan de llegar
     * también los que sí importan.
     *
     * Retira **solo la finalidad de ese correo**: darse de baja de las ofertas
     * no puede dejarte sin la confirmación de tu propia reserva.
     */
    public function darDeBaja(string $token, ?string $ip = null): ?array
    {
        $envio = $this->envios->porToken($token);

        if ($envio === null || $envio['huesped_id'] === null) {
            return null;
        }

        if (in_array($envio['finalidad'], ConsentimientoModel::SIN_PERMISO, true)) {
            return null;
        }

        (new Crm())->retirar((int) $envio['huesped_id'], $envio['finalidad'], $envio['canal'], [
            'origen' => 'baja_email',
            'ip'     => $ip,
            'nota'   => 'Se dio de baja desde el enlace del mensaje.',
        ]);

        return $envio;
    }

    /**
     * Apunta que lo abrió.
     *
     * Solo cuando autorizó el perfilado. Saber quién abre qué es analizar su
     * comportamiento, y eso tiene su propia casilla; ponerle un píxel a
     * quien no lo autorizó sería hacerlo a escondidas.
     */
    public function anotarApertura(string $token): void
    {
        $envio = $this->envios->porToken($token);

        if ($envio === null || $envio['abierto_en'] !== null || $envio['huesped_id'] === null) {
            return;
        }

        if (! $this->consentimientos->permite((int) $envio['huesped_id'], 'perfilado', $envio['canal'])) {
            return;
        }

        $this->envios->update($envio['id'], ['abierto_en' => date('Y-m-d H:i:s')]);
    }

    /** Manda uno a mano desde el panel, saltándose la cola. */
    public function mandarAhora(int $envioId): bool
    {
        $envio = $this->envios->find($envioId);

        if ($envio === null || $envio['estado'] === 'enviado') {
            throw new RuntimeException('Ese mensaje no se puede mandar.');
        }

        return $this->enviarUno($envio);
    }
}
