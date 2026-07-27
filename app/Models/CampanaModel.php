<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Campañas: mandarle lo mismo a un grupo.
 *
 * Guardan los filtros, no la lista. La lista se vuelve a calcular en el momento
 * de enviar y vuelve a pasar por la puerta del consentimiento — si no, una
 * campaña preparada el lunes y enviada el viernes le llegaría a quien se dio de
 * baja el miércoles.
 */
class CampanaModel extends Model
{
    protected $table         = 'campanas';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'nombre', 'plantilla_clave', 'canal', 'filtros', 'estado',
        'encolados', 'saltados', 'enviada_en', 'usuario_id',
    ];

    protected $validationRules = [
        'nombre'          => 'required|max_length[120]',
        'plantilla_clave' => 'required|max_length[40]',
    ];

    protected $validationMessages = [
        'nombre' => ['required' => 'Ponle un nombre para reconocerla después.'],
    ];

    public function listar(): array
    {
        return $this->select('campanas.*, plantillas.nombre AS plantilla_nombre, usuarios.nombre AS usuario_nombre')
            ->join(
                'plantillas',
                "plantillas.clave = campanas.plantilla_clave AND plantillas.idioma = 'es'
                 AND plantillas.canal = campanas.canal",
                'left'
            )
            ->join('usuarios', 'usuarios.id = campanas.usuario_id', 'left')
            ->orderBy('campanas.created_at', 'DESC')
            ->findAll(60);
    }

    /** @return array<string, mixed> */
    public function filtrosDe(array $campana): array
    {
        $filtros = json_decode((string) ($campana['filtros'] ?? ''), true);

        return is_array($filtros) ? $filtros : [];
    }
}
