<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConfiguracionModel;

/**
 * El estado de cumplimiento, en una sola pantalla.
 *
 * **Lo que esto es y lo que no.** No tramita el RNT, no decide si alguien es
 * menor, no clasifica impuestos y no sustituye a un abogado. Lo que hace es
 * mirar los datos que ya están en el sistema y decir qué falta, qué vence y
 * qué lleva demasiado tiempo sin hacerse.
 *
 * Su valor real es que nadie pueda decir «no me acordaba». Un RNT sin renovar,
 * una queja fuera de plazo o una copia de seguridad de hace tres meses no
 * avisan solos: simplemente están ahí.
 */
class Cumplimiento
{
    /** Cada punto revisado, con su estado y qué hacer. */
    public const BIEN     = 'bien';
    public const ATENCION = 'atencion';
    public const MAL      = 'mal';
    public const SINDATO  = 'sin_dato';

    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
    }

    /**
     * Todo lo que se revisa, agrupado por tema.
     *
     * @return array<string, array{titulo: string, puntos: list<array<string, mixed>>}>
     */
    public function revisar(): array
    {
        return [
            'rnt' => [
                'titulo' => 'Registro Nacional de Turismo',
                'puntos' => $this->revisarRnt(),
            ],
            'datos' => [
                'titulo' => 'Protección de datos personales',
                'puntos' => $this->revisarDatos(),
            ],
            'huespedes' => [
                'titulo' => 'Registro de huéspedes y menores',
                'puntos' => $this->revisarHuespedes(),
            ],
            'consumidor' => [
                'titulo' => 'Consumidor y facturación',
                'puntos' => $this->revisarConsumidor(),
            ],
            'seguridad' => [
                'titulo' => 'Seguridad de la información',
                'puntos' => $this->revisarSeguridad(),
            ],
        ];
    }

    /** Cuántos puntos hay en cada estado, para el resumen de arriba. */
    public function resumen(?array $revision = null): array
    {
        $revision = $revision ?? $this->revisar();
        $conteo   = [self::BIEN => 0, self::ATENCION => 0, self::MAL => 0, self::SINDATO => 0];

        foreach ($revision as $grupo) {
            foreach ($grupo['puntos'] as $p) {
                $conteo[$p['estado']]++;
            }
        }

        return $conteo;
    }

    // ── RNT ─────────────────────────────────────────────────────────────

    private function revisarRnt(): array
    {
        $puntos = [];

        $rnt = trim((string) $this->config->obtener('legal_rnt', ''));
        $nit = trim((string) $this->config->obtener('legal_nit', ''));

        $puntos[] = $this->punto(
            'Número de RNT',
            $rnt !== '' ? self::BIEN : self::SINDATO,
            $rnt !== '' ? 'RNT ' . $rnt : 'Sin apuntar.',
            'Operar sin RNT vigente expone a sanción y al cierre del establecimiento. '
            . 'El trámite y su renovación los hace el prestador; aquí solo se guarda el dato.'
        );

        $puntos[] = $this->punto(
            'NIT y razón social',
            $nit !== '' && trim((string) $this->config->obtener('legal_razon_social', '')) !== ''
                ? self::BIEN : self::SINDATO,
            $nit !== '' ? $nit : 'Sin apuntar.',
            'Hace falta para facturar y para cualquier trámite.'
        );

        // La renovación es anual y cae dentro del primer trimestre. La fecha
        // exacta está en configuración porque la fija la norma y la norma
        // cambia: conviene confirmarla cada año.
        $renovar = (string) $this->config->obtener('legal_rnt_renovar', '03-31');

        if ($rnt !== '' && preg_match('/^\d{2}-\d{2}$/', $renovar) === 1) {
            $limite = date('Y') . '-' . $renovar;

            // Si la fecha de este año ya pasó, la próxima es la del que viene
            if ($limite < date('Y-m-d')) {
                $limite = (string) ((int) date('Y') + 1) . '-' . $renovar;
            }

            $dias = (int) floor((strtotime($limite) - strtotime(date('Y-m-d'))) / 86400);

            $puntos[] = $this->punto(
                'Renovación del RNT',
                $dias <= 15 ? self::MAL : ($dias <= 45 ? self::ATENCION : self::BIEN),
                'Antes del ' . date('d/m/Y', strtotime($limite)) . ' — quedan ' . $dias . ' días.',
                'La renovación es anual. Confirma la fecha en la norma vigente: el sistema solo '
                . 'te recuerda la que tengas configurada.'
            );
        }

        return $puntos;
    }

    // ── Protección de datos ─────────────────────────────────────────────

    private function revisarDatos(): array
    {
        $db     = db_connect();
        $puntos = [];

        $politica = $db->table('politicas')
            ->where('tipo', 'datos')->where('publicada', 1)
            ->orderBy('vigente_desde', 'DESC')->get()->getRowArray();

        $puntos[] = $this->punto(
            'Política de tratamiento de datos',
            $politica !== null ? self::BIEN : self::MAL,
            $politica !== null
                ? 'Versión ' . $politica['version'] . ', vigente desde el '
                    . date('d/m/Y', strtotime($politica['vigente_desde']))
                : 'No hay ninguna versión publicada.',
            'Sin política publicada, las autorizaciones que se recogen no se sostienen: '
            . 'el titular tiene derecho a saber para qué autoriza y quién responde.'
        );

        $responsable = trim((string) $this->config->obtener('legal_correo_datos', ''));

        $puntos[] = $this->punto(
            'Canal para ejercer derechos',
            $responsable !== '' ? self::BIEN : self::MAL,
            $responsable !== '' ? $responsable : 'Sin correo de contacto.',
            'Tiene que haber una dirección donde cualquiera pueda pedir sus datos, '
            . 'corregirlos o revocar su autorización.'
        );

        // Solicitudes de derechos que se están quedando sin plazo
        $vencidas = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM solicitudes_datos
             WHERE estado IN ('recibida','en_tramite') AND vence_en < CURDATE()"
        )->getRowArray()['n'] ?? 0);

        $abiertas = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM solicitudes_datos WHERE estado IN ('recibida','en_tramite')"
        )->getRowArray()['n'] ?? 0);

        $puntos[] = $this->punto(
            'Derechos del titular',
            $vencidas > 0 ? self::MAL : ($abiertas > 0 ? self::ATENCION : self::BIEN),
            $vencidas > 0
                ? $vencidas . ' fuera de plazo, ' . $abiertas . ' abiertas.'
                : ($abiertas > 0 ? $abiertas . ' abiertas, todas en plazo.' : 'Ninguna pendiente.'),
            'Los plazos son días hábiles y no admiten «se nos pasó».'
        );

        // Datos que ya no deberían estar. Guardar de más también es incumplir:
        // la norma pide conservar mientras dure la finalidad, no para siempre.
        $meses = max(1, (int) $this->config->obtener('datos_retencion_meses', '60'));

        // La subconsulta es necesaria: el `HAVING` mira la última salida de
        // cada uno, y eso solo se puede contar después de agrupar.
        $caducados = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM (
                SELECT h.id
                FROM huespedes h
                LEFT JOIN reservas r ON r.huesped_id = h.id
                WHERE h.estado = 'activo'
                GROUP BY h.id, h.created_at
                HAVING COALESCE(MAX(r.fecha_salida), DATE(h.created_at))
                       < DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             ) AS caducados",
            [$meses]
        )->getRowArray()['n'] ?? 0);

        $puntos[] = $this->punto(
            'Retención de datos',
            $caducados > 0 ? self::ATENCION : self::BIEN,
            $caducados > 0
                ? $caducados . ' perfiles llevan más de ' . $meses . ' meses sin actividad.'
                : 'Nada por encima del plazo configurado (' . $meses . ' meses).',
            'Guardar más de lo necesario también incumple. Revísalo con tu asesor antes de '
            . 'borrar nada: hay datos que otras normas obligan a conservar.'
        );

        return $puntos;
    }

    // ── Huéspedes y menores ─────────────────────────────────────────────

    private function revisarHuespedes(): array
    {
        $db     = db_connect();
        $puntos = [];

        // Registros aprobados que aún no se han reportado
        $sinReportar = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM registros
             WHERE estado = 'aprobado' AND hay_extranjeros = 1 AND reportado_sire = 0"
        )->getRowArray()['n'] ?? 0);

        $puntos[] = $this->punto(
            'Reporte de extranjeros',
            $sinReportar > 0 ? self::ATENCION : self::BIEN,
            $sinReportar > 0 ? $sinReportar . ' registros con extranjeros sin marcar como reportados.' : 'Al día.',
            'El reporte a Migración Colombia lo hace el prestador. Aquí solo se lleva la cuenta '
            . 'de cuáles quedan.'
        );

        $protocolo = trim((string) $this->config->obtener('legal_protocolo_menores', ''));

        $puntos[] = $this->punto(
            'Protocolo de menores',
            $protocolo !== '' ? self::BIEN : self::MAL,
            $protocolo !== '' ? 'Escrito y disponible en recepción.' : 'Sin escribir.',
            'Qué se pide y qué se hace cuando llega un menor con un adulto que no es su padre '
            . 'o su madre. La decisión y la verificación son siempre del hotel; el sistema solo '
            . 'guarda el protocolo para que esté a mano cuando llegue el caso.'
        );

        $escnna = trim((string) $this->config->obtener('legal_escnna_texto', ''));

        $puntos[] = $this->punto(
            'Advertencia ESCNNA',
            $escnna !== '' ? self::BIEN : self::ATENCION,
            $escnna !== '' ? 'Configurada y visible en el registro.' : 'Sin texto propio.',
            'La Ley 1336/2009 obliga a los prestadores turísticos a advertir sobre la explotación '
            . 'sexual de niños, niñas y adolescentes.'
        );

        // Menores registrados en los últimos meses, para que se vea que existe
        $conMenores = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM registros
             WHERE hay_menores = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)"
        )->getRowArray()['n'] ?? 0);

        if ($conMenores > 0) {
            $puntos[] = $this->punto(
                'Registros con menores',
                self::BIEN,
                $conMenores . ' en los últimos seis meses.',
                'Todos quedan marcados en su registro, con la firma de quien lo hizo.'
            );
        }

        return $puntos;
    }

    // ── Consumidor y facturación ────────────────────────────────────────

    private function revisarConsumidor(): array
    {
        $db     = db_connect();
        $puntos = [];

        $siigo = (new Siigo())->configurado();

        $puntos[] = $this->punto(
            'Facturación electrónica',
            $siigo ? self::BIEN : self::MAL,
            $siigo ? 'Proveedor conectado.' : 'Sin conectar.',
            'La emisión va por un proveedor habilitado ante la DIAN. La parametrización de '
            . 'impuestos y responsabilidades la valida tu contador, no este sistema.'
        );

        $conError = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM facturas WHERE estado = 'error'"
        )->getRowArray()['n'] ?? 0);

        $puntos[] = $this->punto(
            'Facturas con error',
            $conError > 0 ? self::MAL : self::BIEN,
            $conError > 0 ? $conError . ' no se emitieron.' : 'Ninguna pendiente de resolver.',
            'Una factura que falló y nadie miró es una venta sin documento.'
        );

        $cancelacion = trim((string) $this->config->obtener('portal_politica_cancelacion', ''));

        $puntos[] = $this->punto(
            'Política de cancelación publicada',
            $cancelacion !== '' ? self::BIEN : self::MAL,
            $cancelacion !== '' ? 'Publicada.' : 'Sin escribir.',
            'El Estatuto del Consumidor exige que las condiciones se conozcan antes de pagar, '
            . 'no después.'
        );

        // PQR fuera de plazo
        $pqrTarde = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM pqr
             WHERE estado IN ('recibida','en_gestion','reabierta') AND vence_en < CURDATE()"
        )->getRowArray()['n'] ?? 0);

        $puntos[] = $this->punto(
            'PQR en plazo',
            $pqrTarde > 0 ? self::MAL : self::BIEN,
            $pqrTarde > 0 ? $pqrTarde . ' fuera del plazo legal.' : 'Todas dentro de plazo.',
            'Quince días hábiles para contestar una queja o un reclamo (Ley 1755/2015).'
        );

        return $puntos;
    }

    // ── Seguridad ───────────────────────────────────────────────────────

    private function revisarSeguridad(): array
    {
        $db     = db_connect();
        $puntos = [];

        // Copias de seguridad. El sistema no puede hacerlas solo —dependen del
        // hosting— pero sí puede avisar de que hace mucho que nadie apunta una.
        $ultima = trim((string) $this->config->obtener('seguridad_ultima_copia', ''));
        $aviso  = max(1, (int) $this->config->obtener('seguridad_aviso_dias', '7'));

        if ($ultima === '') {
            $puntos[] = $this->punto(
                'Copia de seguridad',
                self::MAL,
                'Nunca se ha apuntado ninguna.',
                'Sin copia, un disco que falle se lleva las reservas, los folios y los registros '
                . 'de huéspedes. Configúrala en tu hosting y apunta aquí cada vez que la compruebes.'
            );
        } else {
            $dias = (int) floor((time() - strtotime($ultima)) / 86400);

            $puntos[] = $this->punto(
                'Copia de seguridad',
                $dias > $aviso * 2 ? self::MAL : ($dias > $aviso ? self::ATENCION : self::BIEN),
                'La última, hace ' . $dias . ' día(s).',
                'Apúntala cada vez que la compruebes. Una copia que nadie ha restaurado nunca '
                . 'no es una copia: es un archivo.'
            );
        }

        // Usuarios activos sin entrar en mucho tiempo
        $dormidos = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM usuarios
             WHERE activo = 1 AND (ultimo_acceso IS NULL OR ultimo_acceso < DATE_SUB(NOW(), INTERVAL 90 DAY))"
        )->getRowArray()['n'] ?? 0);

        $puntos[] = $this->punto(
            'Cuentas sin uso',
            $dormidos > 0 ? self::ATENCION : self::BIEN,
            $dormidos > 0 ? $dormidos . ' cuenta(s) activas sin entrar en tres meses.' : 'Ninguna.',
            'Una cuenta de alguien que ya no trabaja aquí sigue pudiendo entrar. Es la puerta '
            . 'más fácil y la que menos se mira.'
        );

        // La bitácora. Que exista y tenga movimiento reciente.
        $auditoria = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM auditoria WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->getRowArray()['n'] ?? 0);

        $puntos[] = $this->punto(
            'Bitácora de acciones sensibles',
            $auditoria > 0 ? self::BIEN : self::ATENCION,
            $auditoria > 0 ? $auditoria . ' apuntes en los últimos 30 días.' : 'Sin movimiento.',
            'Queda constancia de quién hizo qué con los datos delicados: documentos de identidad, '
            . 'descuentos, anulaciones.'
        );

        $denegados = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM auditoria
             WHERE resultado = 'denegado' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->getRowArray()['n'] ?? 0);

        if ($denegados > 0) {
            $puntos[] = $this->punto(
                'Intentos rechazados',
                $denegados > 20 ? self::ATENCION : self::BIEN,
                $denegados . ' en la última semana.',
                'Alguien intentando entrar donde no le corresponde. Unos pocos son despistes; '
                . 'muchos y seguidos, no.'
            );
        }

        return $puntos;
    }

    /** @return array<string, mixed> */
    private function punto(string $titulo, string $estado, string $detalle, string $porque): array
    {
        return [
            'titulo'  => $titulo,
            'estado'  => $estado,
            'detalle' => $detalle,
            'porque'  => $porque,
        ];
    }
}
