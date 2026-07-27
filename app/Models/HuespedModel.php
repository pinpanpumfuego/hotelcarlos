<?php

namespace App\Models;

use CodeIgniter\Model;

class HuespedModel extends Model
{
    protected $table         = 'huespedes';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'nombre', 'apellidos', 'tipo_documento', 'num_documento', 'nacionalidad',
        'fecha_nacimiento', 'idioma', 'ciudad', 'pais', 'empresa', 'empresa_nit',
        'origen', 'vip', 'telefono', 'email', 'notas', 'notas_internas',
        'estado', 'fusionado_en', 'anonimizado_en',
    ];
    protected $useTimestamps = true;

    /** Cómo nos conoció. Es lo único que dice si un canal sirve de algo. */
    public const ORIGENES = [
        'web'          => 'La web del hotel',
        'booking'      => 'Booking',
        'airbnb'       => 'Airbnb',
        'recomendado'  => 'Se lo recomendaron',
        'redes'        => 'Redes sociales',
        'agencia'      => 'Agencia de viajes',
        'empresa'      => 'Convenio con empresa',
        'repetidor'    => 'Ya había venido',
        'otro'         => 'Otro',
    ];

    protected $validationRules = [
        'nombre'         => 'required|max_length[100]',
        'apellidos'      => 'required|max_length[150]',
        'tipo_documento' => 'required|in_list[CC,CE,PASAPORTE,TI,OTRO]',
        'num_documento'  => 'required|max_length[50]',
        'email'          => 'permit_empty|valid_email',
    ];

    /**
     * Los que se pueden usar.
     *
     * Los fusionados siguen en la tabla —borrarlos dejaría reservas huérfanas—
     * pero no tienen por qué salir en ningún listado ni en ningún buscador.
     */
    public function activos()
    {
        return $this->where('estado', 'activo');
    }
}
