<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Las reglas de «cuándo se manda qué».
 *
 * Se siembran casi todas apagadas a propósito. Encender solas unas campañas el
 * día del despliegue, sin que nadie haya leído los textos, es la peor forma
 * posible de estrenar esto.
 */
class AutomatizacionModel extends Model
{
    protected $table         = 'automatizaciones';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['nombre', 'evento', 'plantilla_clave', 'canal', 'dias', 'hora', 'activa'];

    public const EVENTOS = [
        'reserva_confirmada' => 'Al confirmarse la reserva',
        'pago_pendiente'     => 'Queda un pago pendiente',
        'antes_llegada'      => 'Antes de la llegada',
        'dia_llegada'        => 'El día de la llegada',
        'durante'            => 'Durante la estancia',
        'dia_salida'         => 'El día de la salida',
        'tras_salida'        => 'Después de la salida',
        'recuperacion'       => 'Hace tiempo que no viene',
    ];

    /** Qué significa el número de días en cada evento. */
    public const SENTIDO_DIAS = [
        'reserva_confirmada' => 'Días desde que se confirmó',
        'pago_pendiente'     => 'Días antes de la llegada',
        'antes_llegada'      => 'Días antes de llegar (en negativo)',
        'dia_llegada'        => 'Días respecto a la llegada',
        'durante'            => 'No se usa: sale cada día de la estancia',
        'dia_salida'         => 'Días respecto a la salida',
        'tras_salida'        => 'Días después de irse',
        'recuperacion'       => 'Días desde su última salida',
    ];

    protected $validationRules = [
        'nombre'          => 'required|max_length[120]',
        'plantilla_clave' => 'required|max_length[40]',
    ];

    public function activas(): array
    {
        return $this->where('activa', 1)->orderBy('evento')->findAll();
    }

    public function listar(): array
    {
        return $this->select('automatizaciones.*, plantillas.nombre AS plantilla_nombre,
                              plantillas.finalidad')
            ->join(
                'plantillas',
                "plantillas.clave = automatizaciones.plantilla_clave
                 AND plantillas.canal = automatizaciones.canal
                 AND plantillas.idioma = 'es'",
                'left'
            )
            ->orderBy('automatizaciones.activa', 'DESC')
            ->orderBy('automatizaciones.evento')
            ->findAll();
    }
}
