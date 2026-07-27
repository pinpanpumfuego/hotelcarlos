<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConsentimientoModel;
use App\Models\HuespedModel;
use App\Models\HuespedPreferenciaModel;
use RuntimeException;

/**
 * El perfil único del huésped.
 *
 * «Único» es la palabra que cuesta. La misma persona entra en la base tres
 * veces: una por la web con el correo del trabajo, otra por Booking sin
 * documento, otra porque recepción la dio de alta a mano con el apellido mal
 * escrito. Mientras estén separadas, ni el historial ni el valor ni las
 * alergias sirven de nada.
 */
class Crm
{
    private HuespedModel $huespedes;
    private ConsentimientoModel $consentimientos;

    public function __construct()
    {
        $this->huespedes       = new HuespedModel();
        $this->consentimientos = new ConsentimientoModel();
    }

    // ── Consentimientos ─────────────────────────────────────────────────

    /**
     * Apunta que alguien autorizó algo.
     *
     * Nunca actualiza: escribe. La tabla es un libro, no una ficha.
     *
     * @param array{origen?: string, version?: ?string, ip?: ?string,
     *              dispositivo?: ?string, nota?: ?string, reserva_id?: ?int,
     *              usuario_id?: ?int} $prueba
     */
    public function otorgar(int $huespedId, string $finalidad, string $canal, array $prueba = []): int
    {
        return $this->apuntar($huespedId, $finalidad, $canal, true, $prueba);
    }

    /**
     * Apunta que alguien lo retiró.
     *
     * Retirar tiene que ser tan fácil como dar: lo dice la ley y, más allá de
     * la ley, alguien que no puede darse de baja marca el correo como spam y
     * entonces no llegan tampoco los que sí importan.
     */
    public function retirar(int $huespedId, string $finalidad, string $canal, array $prueba = []): int
    {
        return $this->apuntar($huespedId, $finalidad, $canal, false, $prueba);
    }

    private function apuntar(int $huespedId, string $finalidad, string $canal, bool $otorgado, array $prueba): int
    {
        if (! array_key_exists($finalidad, ConsentimientoModel::FINALIDADES)) {
            throw new RuntimeException('Esa finalidad no existe: ' . $finalidad);
        }

        if (! array_key_exists($canal, ConsentimientoModel::CANALES)) {
            throw new RuntimeException('Ese canal no existe: ' . $canal);
        }

        if ($this->huespedes->find($huespedId) === null) {
            throw new RuntimeException('Ese huésped no existe.');
        }

        $this->consentimientos->insert([
            'huesped_id'       => $huespedId,
            'finalidad'        => $finalidad,
            'canal'            => $canal,
            'otorgado'         => $otorgado ? 1 : 0,
            'origen'           => $prueba['origen'] ?? 'recepcion',
            'version_politica' => $prueba['version'] ?? null,
            'ip'               => $prueba['ip'] ?? null,
            'dispositivo'      => isset($prueba['dispositivo']) ? mb_substr((string) $prueba['dispositivo'], 0, 255) : null,
            'nota'             => isset($prueba['nota']) ? mb_substr((string) $prueba['nota'], 0, 300) : null,
            'reserva_id'       => $prueba['reserva_id'] ?? null,
            'usuario_id'       => $prueba['usuario_id'] ?? null,
        ]);

        return (int) $this->consentimientos->getInsertID();
    }

    /** ¿Se le puede escribir hoy para esto? Por defecto, no. */
    public function puedeContactar(int $huespedId, string $finalidad, string $canal = 'email'): bool
    {
        return $this->consentimientos->permite($huespedId, $finalidad, $canal);
    }

    // ── El valor de un huésped ──────────────────────────────────────────

    /**
     * Lo que ha dejado en casa y cuántas veces ha vuelto.
     *
     * Se calcula al vuelo y no se guarda en columnas. Con un contador
     * denormalizado, cualquier cambio en una reserva vieja lo deja mintiendo, y
     * un número de dinero que miente es peor que no tenerlo.
     *
     * @return array{estancias: int, noches: int, gasto: float, cancelaciones: int,
     *               primera: ?string, ultima: ?string, dias_desde: ?int}
     */
    public function valor(int $huespedId): array
    {
        $fila = db_connect()->query(
            "SELECT
                SUM(CASE WHEN r.estado IN ('checkout','checkin') THEN 1 ELSE 0 END) AS estancias,
                SUM(CASE WHEN r.estado IN ('checkout','checkin')
                         THEN DATEDIFF(r.fecha_salida, r.fecha_entrada) ELSE 0 END)  AS noches,
                SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END)             AS cancelaciones,
                MIN(CASE WHEN r.estado IN ('checkout','checkin') THEN r.fecha_entrada END) AS primera,
                MAX(CASE WHEN r.estado IN ('checkout','checkin') THEN r.fecha_salida END)  AS ultima
             FROM reservas r
             WHERE r.huesped_id = ?",
            [$huespedId]
        )->getRowArray();

        // El gasto sale del folio y no de `reservas.total`: ahí están el
        // restaurante, el minibar y las actividades, que es donde de verdad se
        // ve quién gasta.
        $gasto = db_connect()->query(
            "SELECT COALESCE(SUM(f.valor), 0) AS gasto
             FROM folio_movimientos f
             JOIN reservas r ON r.id = f.reserva_id
             WHERE r.huesped_id = ? AND f.tipo = 'cargo' AND r.estado != 'cancelada'",
            [$huespedId]
        )->getRowArray();

        $ultima = $fila['ultima'] ?? null;

        return [
            'estancias'     => (int) ($fila['estancias'] ?? 0),
            'noches'        => (int) ($fila['noches'] ?? 0),
            'gasto'         => round((float) ($gasto['gasto'] ?? 0), 2),
            'cancelaciones' => (int) ($fila['cancelaciones'] ?? 0),
            'primera'       => $fila['primera'] ?? null,
            'ultima'        => $ultima,
            'dias_desde'    => $ultima !== null
                ? (int) floor((time() - strtotime($ultima)) / 86400)
                : null,
        ];
    }

    /**
     * El historial completo: reservas, encuestas, quejas.
     *
     * @return array<string, mixed>
     */
    public function historial(int $huespedId): array
    {
        $db = db_connect();

        return [
            'reservas' => $db->query(
                "SELECT r.*, u.nombre AS unidad_nombre
                 FROM reservas r
                 LEFT JOIN unidades u ON u.id = r.unidad_id
                 WHERE r.huesped_id = ?
                 ORDER BY r.fecha_entrada DESC",
                [$huespedId]
            )->getResultArray(),

            'encuestas' => $db->query(
                "SELECT e.*, r.codigo
                 FROM encuestas e
                 JOIN reservas r ON r.id = e.reserva_id
                 WHERE r.huesped_id = ?
                 ORDER BY e.created_at DESC",
                [$huespedId]
            )->getResultArray(),

            'solicitudes' => $db->query(
                "SELECT s.*
                 FROM solicitudes s
                 JOIN reservas r ON r.id = s.reserva_id
                 WHERE r.huesped_id = ?
                 ORDER BY s.created_at DESC
                 LIMIT 30",
                [$huespedId]
            )->getResultArray(),
        ];
    }

    // ── Duplicados ──────────────────────────────────────────────────────

    /**
     * Perfiles que probablemente sean la misma persona.
     *
     * Se busca por documento, correo y teléfono, en ese orden de confianza. El
     * nombre no se usa: hay demasiados «Juan Pérez» y demasiadas formas de
     * escribir el mismo apellido.
     *
     * @return list<array<string, mixed>>
     */
    public function posiblesDuplicados(array $huesped): array
    {
        $db    = db_connect();
        $donde = [];
        $args  = [];

        if (trim((string) $huesped['num_documento']) !== '') {
            $donde[] = 'num_documento = ?';
            $args[]  = $huesped['num_documento'];
        }

        if (trim((string) $huesped['email']) !== '') {
            $donde[] = 'email = ?';
            $args[]  = strtolower(trim($huesped['email']));
        }

        // Solo los dígitos, y solo los últimos diez: el mismo móvil se escribe
        // de cinco maneras. Comparar el número entero fallaría en un sentido —
        // buscando «+57 300...» no encontraría el mismo guardado como
        // «300...»— y ese es justo el caso real: recepción lo teclea con
        // prefijo y Booking lo manda sin él.
        $telefono = preg_replace('/\D/', '', (string) $huesped['telefono']);
        $cola     = substr($telefono, -10);

        if (strlen($cola) >= 7) {
            $donde[] = "REPLACE(REPLACE(REPLACE(telefono,' ',''),'-',''),'+','') LIKE ?";
            $args[]  = '%' . $cola;
        }

        if ($donde === []) {
            return [];
        }

        $args[] = (int) $huesped['id'];

        return $db->query(
            'SELECT * FROM huespedes WHERE (' . implode(' OR ', $donde) . ")
             AND id != ? AND estado = 'activo' ORDER BY created_at",
            $args
        )->getResultArray();
    }

    /**
     * Funde dos perfiles en uno.
     *
     * El perdedor **no se borra**: se marca y se queda apuntando al ganador.
     * Borrarlo dejaría reservas huérfanas y, sobre todo, haría imposible
     * explicar por qué desapareció alguien de la base el día que lo pregunten.
     *
     * @return array{reservas: int, preferencias: int, consentimientos: int}
     */
    public function fusionar(int $ganadorId, int $perdedorId, ?int $usuarioId = null): array
    {
        if ($ganadorId === $perdedorId) {
            throw new RuntimeException('No se puede fusionar un perfil consigo mismo.');
        }

        $ganador  = $this->huespedes->find($ganadorId);
        $perdedor = $this->huespedes->find($perdedorId);

        if ($ganador === null || $perdedor === null) {
            throw new RuntimeException('Alguno de los dos perfiles no existe.');
        }

        if ($perdedor['estado'] !== 'activo') {
            throw new RuntimeException('Ese perfil ya se fusionó antes.');
        }

        $db = db_connect();
        $db->transStart();

        $reservas = $db->table('reservas')->where('huesped_id', $perdedorId)->countAllResults();
        $db->table('reservas')->where('huesped_id', $perdedorId)->update(['huesped_id' => $ganadorId]);

        $preferencias = (new HuespedPreferenciaModel())->trasladar($perdedorId, $ganadorId);

        // Los consentimientos se trasladan enteros, con sus fechas: son la
        // prueba de qué autorizó esa persona, y se prueba con la historia
        // completa, no con un resumen.
        $consentimientos = $db->table('consentimientos')->where('huesped_id', $perdedorId)->countAllResults();
        $db->table('consentimientos')->where('huesped_id', $perdedorId)->update(['huesped_id' => $ganadorId]);

        // Lo que el ganador tenga en blanco se rellena con lo del perdedor: si
        // uno traía el teléfono y el otro el correo, la fusión los junta.
        $completar = [];

        foreach (['telefono', 'email', 'nacionalidad', 'fecha_nacimiento', 'ciudad', 'pais', 'empresa', 'origen'] as $campo) {
            if (trim((string) ($ganador[$campo] ?? '')) === '' && trim((string) ($perdedor[$campo] ?? '')) !== '') {
                $completar[$campo] = $perdedor[$campo];
            }
        }

        // Las notas se concatenan: descartar las de uno sería tirar información
        // que alguien se molestó en escribir.
        $notas = trim(trim((string) $ganador['notas']) . "\n" . trim((string) $perdedor['notas']));

        if ($notas !== '' && $notas !== trim((string) $ganador['notas'])) {
            $completar['notas'] = $notas;
        }

        if ($completar !== []) {
            $this->huespedes->update($ganadorId, $completar);
        }

        $this->huespedes->update($perdedorId, [
            'estado'       => 'fusionado',
            'fusionado_en' => $ganadorId,
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('La fusión no se pudo completar. No se ha cambiado nada.');
        }

        log_message('notice', 'CRM: perfil {p} fusionado en {g} por el usuario {u}', [
            'p' => $perdedorId, 'g' => $ganadorId, 'u' => $usuarioId ?? 'desconocido',
        ]);

        return [
            'reservas'        => $reservas,
            'preferencias'    => $preferencias,
            'consentimientos' => $consentimientos,
        ];
    }
}
