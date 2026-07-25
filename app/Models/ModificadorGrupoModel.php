<?php

namespace App\Models;

use CodeIgniter\Model;

/** Grupos de modificadores: "Punto de la carne", "Extras", "Quitar ingredientes"... */
class ModificadorGrupoModel extends Model
{
    protected $table         = 'modificador_grupos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'tipo', 'obligatorio', 'orden'];
    protected $useTimestamps = true;

    public const TIPOS = [
        'unico'    => 'Elegir uno',
        'multiple' => 'Elegir varios',
    ];

    protected $validationRules = [
        'nombre' => 'required|min_length[2]|max_length[100]',
        'tipo'   => 'required|in_list[unico,multiple]',
    ];

    /** Grupos con sus opciones, ordenados. */
    public function conOpciones(): array
    {
        $grupos = $this->orderBy('orden')->orderBy('nombre')->findAll();
        if ($grupos === []) {
            return [];
        }

        $opciones = (new ModificadorModel())
            ->whereIn('grupo_id', array_column($grupos, 'id'))
            ->orderBy('orden')->orderBy('nombre')
            ->findAll();

        foreach ($grupos as &$g) {
            $g['opciones'] = array_values(array_filter(
                $opciones,
                static fn ($o) => (int) $o['grupo_id'] === (int) $g['id']
            ));
        }

        return $grupos;
    }

    /** Grupos aplicables a un producto, con sus opciones. */
    public function deProducto(int $productoId): array
    {
        $ids = array_column(
            db_connect()->table('producto_modificador_grupos')
                ->select('grupo_id')->where('producto_id', $productoId)->get()->getResultArray(),
            'grupo_id'
        );

        if ($ids === []) {
            return [];
        }

        return array_values(array_filter(
            $this->conOpciones(),
            static fn ($g) => in_array((string) $g['id'], array_map('strval', $ids), true)
        ));
    }
}
