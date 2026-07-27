<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\FestivosColombia;
use CodeIgniter\Model;

/**
 * Peticiones, quejas, reclamos, sugerencias y felicitaciones.
 *
 * **Van aparte de `solicitudes` porque no son lo mismo.** Una petición de
 * toallas y una queja formal las escribe el mismo huésped, pero a la segunda la
 * ampara la Ley 1755/2015: tiene plazo, hay que contestarla por escrito y hay
 * que poder demostrar que se contestó. En la misma tabla, la queja se pierde
 * entre las toallas.
 */
class PqrModel extends Model
{
    protected $table         = 'pqr';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'codigo', 'tipo', 'huesped_id', 'reserva_id', 'canal_entrada',
        'nombre_contacto', 'email_contacto', 'telefono_contacto',
        'asunto', 'detalle', 'estado', 'prioridad', 'responsable_id',
        'vence_en', 'respuesta', 'respondida_en', 'respondio_id',
        'cerrada_en', 'satisfaccion', 'causa',
    ];

    public const TIPOS = [
        'peticion'     => 'Petición',
        'queja'        => 'Queja',
        'reclamo'      => 'Reclamo',
        'sugerencia'   => 'Sugerencia',
        'felicitacion' => 'Felicitación',
    ];

    public const ESTADOS = [
        'recibida'   => 'Recibida',
        'en_gestion' => 'En gestión',
        'respondida' => 'Respondida',
        'cerrada'    => 'Cerrada',
        'reabierta'  => 'Reabierta',
    ];

    public const CANALES = [
        'presencial' => 'En persona',
        'telefono'   => 'Por teléfono',
        'email'      => 'Por correo',
        'portal'     => 'Desde su portal',
        'web'        => 'Desde la web',
        'ota'        => 'Booking o Airbnb',
        'redes'      => 'Redes sociales',
        'otro'       => 'Otro',
    ];

    /**
     * Plazo legal por tipo, en días **hábiles** (Ley 1755/2015).
     *
     * Las sugerencias y las felicitaciones no llevan plazo legal, pero se les
     * pone uno de cortesía: una felicitación sin contestar es una oportunidad
     * tirada, y una sugerencia que nadie mira es alguien que no volverá a dar
     * ninguna.
     */
    public const PLAZOS = [
        'peticion'     => 15,
        'queja'        => 15,
        'reclamo'      => 15,
        'sugerencia'   => 10,
        'felicitacion' => 5,
    ];

    /** Las que siguen vivas. */
    public const ABIERTAS = ['recibida', 'en_gestion', 'reabierta'];

    protected $validationRules = [
        'asunto'  => 'required|max_length[200]',
        'detalle' => 'required',
    ];

    protected $validationMessages = [
        'asunto'  => ['required' => 'Resume en una línea de qué va.'],
        'detalle' => ['required' => 'Escribe qué pasó, con las palabras del huésped si puede ser.'],
    ];

    /**
     * La fecha límite, contando solo días hábiles.
     *
     * En días naturales daría una fecha que no es la que exige la ley: quince
     * días hábiles desde el 20 de diciembre no son el 4 de enero. Se apoya en
     * el calendario de festivos colombianos que ya usa el módulo de turnos.
     */
    public function vencimiento(string $tipo, ?string $desde = null): string
    {
        $dias  = self::PLAZOS[$tipo] ?? 15;
        $fecha = $desde ?? date('Y-m-d');
        $vistos = 0;

        // Tope de seguridad: sin él, un calendario mal cargado daría un bucle
        // infinito en una tarea que corre sin nadie delante.
        for ($i = 0; $i < 400 && $vistos < $dias; $i++) {
            $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'));

            if (! FestivosColombia::esNoLaborable($fecha)) {
                $vistos++;
            }
        }

        return $fecha;
    }

    /** Un código que el huésped pueda citar por teléfono. */
    public function siguienteCodigo(): string
    {
        $anio  = date('Y');
        $ultimo = $this->like('codigo', 'PQR-' . $anio . '-', 'after')
            ->orderBy('id', 'DESC')
            ->first();

        $numero = 1;

        if ($ultimo !== null && preg_match('/-(\d+)$/', $ultimo['codigo'], $m) === 1) {
            $numero = (int) $m[1] + 1;
        }

        do {
            $codigo = 'PQR-' . $anio . '-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
            $numero++;
        } while ($this->where('codigo', $codigo)->countAllResults() > 0);

        return $codigo;
    }

    /**
     * El tablero.
     *
     * @param array{estado?: string, tipo?: string, responsable_id?: int} $filtros
     */
    public function listar(array $filtros = []): array
    {
        $q = $this->consulta();

        if (($filtros['estado'] ?? '') !== '') {
            $filtros['estado'] === 'abiertas'
                ? $q->whereIn('pqr.estado', self::ABIERTAS)
                : $q->where('pqr.estado', $filtros['estado']);
        }

        if (($filtros['tipo'] ?? '') !== '') {
            $q->where('pqr.tipo', $filtros['tipo']);
        }

        if (($filtros['responsable_id'] ?? 0) > 0) {
            $q->where('pqr.responsable_id', $filtros['responsable_id']);
        }

        return $q->orderBy("FIELD(pqr.estado, 'reabierta', 'recibida', 'en_gestion', 'respondida', 'cerrada')")
            ->orderBy('pqr.vence_en')
            ->findAll(200);
    }

    public function detalle(int $id): ?array
    {
        return $this->consulta()->where('pqr.id', $id)->first();
    }

    /**
     * Las que se van a pasar de plazo o ya se pasaron.
     *
     * Sale en el panel. Una queja fuera de plazo no avisa sola: simplemente
     * lleva ahí veinte días y nadie se ha dado cuenta.
     */
    public function enRiesgo(int $margenDias = 3): array
    {
        return $this->consulta()
            ->whereIn('pqr.estado', self::ABIERTAS)
            ->where('pqr.vence_en <=', date('Y-m-d', strtotime('+' . $margenDias . ' days')))
            ->orderBy('pqr.vence_en')
            ->findAll();
    }

    public function conteo(): array
    {
        $filas  = $this->select('estado, COUNT(*) AS cuantas')->groupBy('estado')->findAll();
        $conteo = array_fill_keys(array_keys(self::ESTADOS), 0);

        foreach ($filas as $f) {
            $conteo[$f['estado']] = (int) $f['cuantas'];
        }

        return $conteo;
    }

    /**
     * De qué se queja la gente, y cuánto se tarda en contestar.
     *
     * @return array{por_tipo: array, por_causa: array, dias_medio: ?float,
     *               fuera_plazo: int, satisfaccion: ?float}
     */
    public function informe(string $desde, string $hasta): array
    {
        $db = db_connect();

        $porTipo = [];

        foreach ($db->query(
            'SELECT tipo, COUNT(*) AS cuantas FROM pqr WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY tipo',
            [$desde, $hasta]
        )->getResultArray() as $f) {
            $porTipo[$f['tipo']] = (int) $f['cuantas'];
        }

        $porCausa = $db->query(
            "SELECT causa, COUNT(*) AS cuantas
             FROM pqr
             WHERE causa IS NOT NULL AND causa != '' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY causa ORDER BY cuantas DESC LIMIT 10",
            [$desde, $hasta]
        )->getResultArray();

        $tiempos = $db->query(
            'SELECT AVG(DATEDIFF(respondida_en, created_at)) AS dias,
                    AVG(satisfaccion) AS satisfaccion,
                    SUM(CASE WHEN DATE(respondida_en) > vence_en THEN 1 ELSE 0 END) AS tarde
             FROM pqr
             WHERE respondida_en IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?',
            [$desde, $hasta]
        )->getRowArray();

        // Las que siguen abiertas y ya se pasaron cuentan como fuera de plazo:
        // si no, una queja de hace un mes sin contestar no aparecería en ningún
        // sitio, que es justo la que más importa.
        $abiertasTarde = $db->query(
            "SELECT COUNT(*) AS cuantas FROM pqr
             WHERE estado IN ('recibida','en_gestion','reabierta')
               AND vence_en < CURDATE() AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        return [
            'por_tipo'     => $porTipo,
            'por_causa'    => $porCausa,
            'dias_medio'   => $tiempos['dias'] !== null ? round((float) $tiempos['dias'], 1) : null,
            'fuera_plazo'  => (int) ($tiempos['tarde'] ?? 0) + (int) ($abiertasTarde['cuantas'] ?? 0),
            'satisfaccion' => $tiempos['satisfaccion'] !== null ? round((float) $tiempos['satisfaccion'], 1) : null,
        ];
    }

    private function consulta()
    {
        return $this->select('pqr.*, huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos,
                              reservas.codigo AS reserva_codigo, responsable.nombre AS responsable_nombre')
            ->join('huespedes', 'huespedes.id = pqr.huesped_id', 'left')
            ->join('reservas', 'reservas.id = pqr.reserva_id', 'left')
            ->join('usuarios AS responsable', 'responsable.id = pqr.responsable_id', 'left');
    }
}
