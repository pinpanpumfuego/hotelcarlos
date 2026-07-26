<?php

namespace App\Models;

use CodeIgniter\Model;

/** Catálogo de enseres que debe haber en las cabañas. */
class InventarioItemModel extends Model
{
    protected $table         = 'inventario_items';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'grupo', 'valor_reposicion', 'cantidad_estandar', 'orden', 'activo'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre'            => 'required|min_length[2]|max_length[100]',
        'grupo'             => 'required|max_length[40]',
        'valor_reposicion'  => 'permit_empty|numeric',
        'cantidad_estandar' => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'nombre' => ['required' => 'El artículo necesita un nombre.'],
        'grupo'  => ['required' => 'Indica a qué zona de la cabaña pertenece.'],
    ];

    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('grupo')->orderBy('orden')->orderBy('nombre')->findAll();
    }

    /** Todos, agrupados por zona, para las pantallas de gestión. */
    public function porGrupo(bool $soloActivos = false): array
    {
        $consulta = $this->orderBy('grupo')->orderBy('orden')->orderBy('nombre');
        if ($soloActivos) {
            $consulta->where('activo', 1);
        }

        $agrupados = [];
        foreach ($consulta->findAll() as $i) {
            $agrupados[$i['grupo']][] = $i;
        }

        return $agrupados;
    }

    /**
     * Inventario esperado de una cabaña: todos los artículos activos con la
     * cantidad que le toca (0 si en esa cabaña no debe haber ninguno).
     */
    public function deUnidad(int $unidadId): array
    {
        $cantidades = array_column(
            $this->db->table('unidad_inventario')->where('unidad_id', $unidadId)->get()->getResultArray(),
            'cantidad',
            'item_id'
        );

        $lista = [];
        foreach ($this->activos() as $item) {
            $item['cantidad'] = (int) ($cantidades[$item['id']] ?? 0);
            $lista[] = $item;
        }

        return $lista;
    }

    /** Guarda de golpe las cantidades de una cabaña. */
    public function fijarEnUnidad(int $unidadId, array $cantidades): void
    {
        $tabla = $this->db->table('unidad_inventario');
        $tabla->where('unidad_id', $unidadId)->delete();

        foreach ($cantidades as $itemId => $cantidad) {
            $cantidad = (int) $cantidad;
            if ($cantidad > 0) {
                $tabla->insert(['unidad_id' => $unidadId, 'item_id' => (int) $itemId, 'cantidad' => $cantidad]);
            }
        }
    }

    /** Copia el inventario de una cabaña a todas las demás del mismo tipo. */
    public function copiarATodas(int $unidadOrigenId): int
    {
        $origen = $this->db->table('unidades')->where('id', $unidadOrigenId)->get()->getRowArray();
        if ($origen === null) {
            return 0;
        }

        $cantidades = array_column(
            $this->db->table('unidad_inventario')->where('unidad_id', $unidadOrigenId)->get()->getResultArray(),
            'cantidad',
            'item_id'
        );

        $destinos = $this->db->table('unidades')
            ->where('tipo_id', $origen['tipo_id'])
            ->where('id !=', $unidadOrigenId)
            ->get()
            ->getResultArray();

        foreach ($destinos as $d) {
            $this->fijarEnUnidad((int) $d['id'], $cantidades);
        }

        return count($destinos);
    }
}
