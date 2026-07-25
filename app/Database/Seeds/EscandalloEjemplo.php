<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Ejemplo de escandallo y modificadores para arrancar:
 * insumos con coste, una receta real y grupos de personalización.
 */
class EscandalloEjemplo extends Seeder
{
    public function run()
    {
        $ahora = date('Y-m-d H:i:s');

        // ── Insumos (coste por unidad de medida) ──
        if ($this->db->table('insumos')->countAllResults() === 0) {
            $insumos = [
                ['nombre' => 'Gallina', 'unidad' => 'g', 'costo_unitario' => 18, 'proveedor' => 'Granja local'],
                ['nombre' => 'Papa', 'unidad' => 'g', 'costo_unitario' => 2.5, 'proveedor' => 'Plaza de mercado'],
                ['nombre' => 'Yuca', 'unidad' => 'g', 'costo_unitario' => 2.2, 'proveedor' => 'Plaza de mercado'],
                ['nombre' => 'Mazorca', 'unidad' => 'ud', 'costo_unitario' => 1200, 'proveedor' => 'Plaza de mercado'],
                ['nombre' => 'Arroz', 'unidad' => 'g', 'costo_unitario' => 4, 'proveedor' => null],
                ['nombre' => 'Aguacate', 'unidad' => 'ud', 'costo_unitario' => 2500, 'proveedor' => null],
                ['nombre' => 'Cilantro y aliños', 'unidad' => 'g', 'costo_unitario' => 6, 'proveedor' => null],
                ['nombre' => 'Trucha', 'unidad' => 'g', 'costo_unitario' => 28, 'proveedor' => 'Piscícola del lago'],
                ['nombre' => 'Harina de trigo', 'unidad' => 'g', 'costo_unitario' => 3.5, 'proveedor' => null],
                ['nombre' => 'Queso mozzarella', 'unidad' => 'g', 'costo_unitario' => 22, 'proveedor' => null],
                ['nombre' => 'Salsa de tomate', 'unidad' => 'g', 'costo_unitario' => 8, 'proveedor' => null],
                ['nombre' => 'Café en grano', 'unidad' => 'g', 'costo_unitario' => 45, 'proveedor' => 'Finca vecina'],
            ];
            foreach ($insumos as &$i) {
                $i['activo']     = 1;
                $i['created_at'] = $ahora;
                $i['updated_at'] = $ahora;
            }
            unset($i);
            $this->db->table('insumos')->insertBatch($insumos);
        }

        // ── Grupos de modificadores ──
        if ($this->db->table('modificador_grupos')->countAllResults() === 0) {
            $grupos = [
                ['nombre' => 'Punto de la carne', 'tipo' => 'unico', 'obligatorio' => 1, 'orden' => 1],
                ['nombre' => 'Extras', 'tipo' => 'multiple', 'obligatorio' => 0, 'orden' => 2],
                ['nombre' => 'Quitar ingredientes', 'tipo' => 'multiple', 'obligatorio' => 0, 'orden' => 3],
                ['nombre' => 'Preparación de la bebida', 'tipo' => 'unico', 'obligatorio' => 0, 'orden' => 4],
            ];
            foreach ($grupos as &$g) {
                $g['created_at'] = $ahora;
                $g['updated_at'] = $ahora;
            }
            unset($g);
            $this->db->table('modificador_grupos')->insertBatch($grupos);

            $ids = [];
            foreach ($this->db->table('modificador_grupos')->get()->getResultArray() as $g) {
                $ids[$g['nombre']] = $g['id'];
            }

            $opciones = [
                ['g' => 'Punto de la carne', 'nombre' => 'Poco hecha', 'precio' => 0, 'orden' => 1],
                ['g' => 'Punto de la carne', 'nombre' => 'Al punto', 'precio' => 0, 'orden' => 2],
                ['g' => 'Punto de la carne', 'nombre' => 'Bien hecha', 'precio' => 0, 'orden' => 3],
                ['g' => 'Extras', 'nombre' => 'Queso extra', 'precio' => 4000, 'orden' => 1],
                ['g' => 'Extras', 'nombre' => 'Aguacate', 'precio' => 3000, 'orden' => 2],
                ['g' => 'Extras', 'nombre' => 'Porción de arroz', 'precio' => 5000, 'orden' => 3],
                ['g' => 'Extras', 'nombre' => 'Patacón adicional', 'precio' => 4000, 'orden' => 4],
                ['g' => 'Quitar ingredientes', 'nombre' => 'Sin cebolla', 'precio' => 0, 'orden' => 1],
                ['g' => 'Quitar ingredientes', 'nombre' => 'Sin cilantro', 'precio' => 0, 'orden' => 2],
                ['g' => 'Quitar ingredientes', 'nombre' => 'Sin picante', 'precio' => 0, 'orden' => 3],
                ['g' => 'Quitar ingredientes', 'nombre' => 'Sin sal', 'precio' => 0, 'orden' => 4],
                ['g' => 'Preparación de la bebida', 'nombre' => 'En agua', 'precio' => 0, 'orden' => 1],
                ['g' => 'Preparación de la bebida', 'nombre' => 'En leche', 'precio' => 1500, 'orden' => 2],
                ['g' => 'Preparación de la bebida', 'nombre' => 'Sin azúcar', 'precio' => 0, 'orden' => 3],
            ];

            $filas = [];
            foreach ($opciones as $o) {
                $filas[] = [
                    'grupo_id'     => $ids[$o['g']],
                    'nombre'       => $o['nombre'],
                    'precio_extra' => $o['precio'],
                    'orden'        => $o['orden'],
                    'created_at'   => $ahora,
                    'updated_at'   => $ahora,
                ];
            }
            $this->db->table('modificadores')->insertBatch($filas);
        }

        // ── Receta de ejemplo: sancocho de gallina ──
        $sancocho = $this->db->table('carta_productos')->like('nombre', 'Sancocho', 'after')->get()->getRowArray();
        if ($sancocho !== null && $this->db->table('receta_lineas')->where('producto_id', $sancocho['id'])->countAllResults() === 0) {
            $costes = [];
            foreach ($this->db->table('insumos')->get()->getResultArray() as $i) {
                $costes[$i['nombre']] = $i['id'];
            }

            $receta = [
                ['Gallina', 320],
                ['Papa', 200],
                ['Yuca', 150],
                ['Mazorca', 1],
                ['Arroz', 120],
                ['Aguacate', 0.5],
                ['Cilantro y aliños', 25],
            ];

            $filas = [];
            foreach ($receta as [$nombre, $cantidad]) {
                if (isset($costes[$nombre])) {
                    $filas[] = [
                        'producto_id' => $sancocho['id'],
                        'insumo_id'   => $costes[$nombre],
                        'cantidad'    => $cantidad,
                        'created_at'  => $ahora,
                        'updated_at'  => $ahora,
                    ];
                }
            }
            if ($filas !== []) {
                $this->db->table('receta_lineas')->insertBatch($filas);
            }

            // Alérgenos y modificadores del sancocho
            $this->db->table('carta_productos')->where('id', $sancocho['id'])->update(['alergenos' => 'apio']);

            $grupoExtras = $this->db->table('modificador_grupos')->where('nombre', 'Extras')->get()->getRowArray();
            $grupoQuitar = $this->db->table('modificador_grupos')->where('nombre', 'Quitar ingredientes')->get()->getRowArray();
            foreach ([$grupoExtras, $grupoQuitar] as $g) {
                if ($g !== null) {
                    $this->db->table('producto_modificador_grupos')->ignore(true)
                        ->insert(['producto_id' => $sancocho['id'], 'grupo_id' => $g['id']]);
                }
            }
        }
    }
}
