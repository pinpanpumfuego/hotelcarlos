<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\FestivosColombia;
use CodeIgniter\Model;

/**
 * Derechos del titular sobre sus datos (Ley 1581/2012).
 *
 * Van aparte de las PQR porque el plazo, el fundamento y lo que hay que
 * entregar son distintos. Una queja por la ducha y una petición de «dígame qué
 * datos míos tiene» se parecen en el formulario y en nada más.
 *
 * **Antes de entregar nada hay que estar seguro de quién pide.** Darle los
 * datos de alguien a quien no es esa persona es peor que no dárselos: es la
 * misma infracción que se intenta evitar, hecha por escrito y con acuse.
 */
class SolicitudDatosModel extends Model
{
    protected $table         = 'solicitudes_datos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'codigo', 'tipo', 'huesped_id', 'nombre', 'documento', 'email', 'telefono',
        'identificado', 'como_identifico', 'detalle', 'estado', 'vence_en', 'prorrogada',
        'respuesta', 'atendida_en', 'atendio_id', 'motivo_rechazo', 'origen', 'ip',
    ];

    public const TIPOS = [
        'acceso'        => 'Saber qué datos suyos tenemos',
        'rectificacion' => 'Corregir un dato equivocado',
        'actualizacion' => 'Actualizar un dato',
        'supresion'     => 'Que borremos sus datos',
        'revocatoria'   => 'Retirar su autorización',
        'prueba'        => 'Ver la prueba de su autorización',
    ];

    public const ESTADOS = [
        'recibida'   => 'Recibida',
        'en_tramite' => 'En trámite',
        'atendida'   => 'Atendida',
        'rechazada'  => 'Rechazada',
    ];

    public const ABIERTAS = ['recibida', 'en_tramite'];

    /**
     * Las que la ley llama «consultas» tienen un plazo; las demás, otro.
     *
     * Preguntar qué datos hay es una consulta. Pedir que se corrijan, se borren
     * o se revoque la autorización es un reclamo, y da más margen porque exige
     * comprobar y actuar, no solo mirar.
     */
    public const SON_CONSULTA = ['acceso', 'prueba'];

    protected $validationRules = [
        'nombre'    => 'required|max_length[150]',
        'documento' => 'required|max_length[50]',
    ];

    protected $validationMessages = [
        'nombre'    => ['required' => 'Hace falta el nombre de quien lo pide.'],
        'documento' => ['required' => 'Sin documento no se puede comprobar que es esa persona.'],
    ];

    /**
     * El plazo, en días hábiles.
     *
     * Los días van en configuración porque los fija la norma, y una norma que
     * cambie no puede exigir un despliegue.
     */
    public function vencimiento(string $tipo, ?string $desde = null): string
    {
        $config = new ConfiguracionModel();

        $dias = in_array($tipo, self::SON_CONSULTA, true)
            ? (int) $config->obtener('datos_plazo_consulta', '10')
            : (int) $config->obtener('datos_plazo_reclamo', '15');

        $dias  = max(1, $dias);
        $fecha = $desde ?? date('Y-m-d');
        $vistos = 0;

        // Tope de seguridad: sin él, un calendario mal cargado daría un bucle
        // infinito en algo que puede correr sin nadie delante.
        for ($i = 0; $i < 400 && $vistos < $dias; $i++) {
            $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'));

            if (! FestivosColombia::esNoLaborable($fecha)) {
                $vistos++;
            }
        }

        return $fecha;
    }

    public function siguienteCodigo(): string
    {
        $anio   = date('Y');
        $ultimo = $this->like('codigo', 'HD-' . $anio . '-', 'after')->orderBy('id', 'DESC')->first();

        $numero = 1;

        if ($ultimo !== null && preg_match('/-(\d+)$/', $ultimo['codigo'], $m) === 1) {
            $numero = (int) $m[1] + 1;
        }

        do {
            $codigo = 'HD-' . $anio . '-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
            $numero++;
        } while ($this->where('codigo', $codigo)->countAllResults() > 0);

        return $codigo;
    }

    public function listar(string $estado = 'abiertas'): array
    {
        $q = $this->select('solicitudes_datos.*, huespedes.nombre AS huesped_nombre,
                            huespedes.apellidos AS huesped_apellidos, usuarios.nombre AS atendio_nombre')
            ->join('huespedes', 'huespedes.id = solicitudes_datos.huesped_id', 'left')
            ->join('usuarios', 'usuarios.id = solicitudes_datos.atendio_id', 'left');

        if ($estado === 'abiertas') {
            $q->whereIn('solicitudes_datos.estado', self::ABIERTAS);
        } elseif ($estado !== '' && array_key_exists($estado, self::ESTADOS)) {
            $q->where('solicitudes_datos.estado', $estado);
        }

        return $q->orderBy('solicitudes_datos.vence_en')->findAll(200);
    }

    public function detalle(int $id): ?array
    {
        return $this->select('solicitudes_datos.*, huespedes.nombre AS huesped_nombre,
                              huespedes.apellidos AS huesped_apellidos, usuarios.nombre AS atendio_nombre')
            ->join('huespedes', 'huespedes.id = solicitudes_datos.huesped_id', 'left')
            ->join('usuarios', 'usuarios.id = solicitudes_datos.atendio_id', 'left')
            ->where('solicitudes_datos.id', $id)
            ->first();
    }

    /**
     * Busca a quién corresponde una solicitud, por documento.
     *
     * Por documento y no por nombre: hay demasiados nombres iguales, y aquí
     * confundirse significa entregarle a alguien los datos de otro.
     */
    public function huespedProbable(string $documento): ?array
    {
        $documento = trim($documento);

        if ($documento === '') {
            return null;
        }

        return (new HuespedModel())->where('num_documento', $documento)->where('estado', 'activo')->first();
    }
}
