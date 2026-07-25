<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Mesas iniciales del restaurante del ecolodge. */
class MesasEjemplo extends Seeder
{
    public function run()
    {
        if ($this->db->table('mesas')->countAllResults() > 0) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');
        $mesas = [
            ['nombre' => 'Mesa 1', 'zona' => 'Comedor', 'capacidad' => 4, 'orden' => 1],
            ['nombre' => 'Mesa 2', 'zona' => 'Comedor', 'capacidad' => 4, 'orden' => 2],
            ['nombre' => 'Mesa 3', 'zona' => 'Comedor', 'capacidad' => 6, 'orden' => 3],
            ['nombre' => 'Mesa 4', 'zona' => 'Comedor', 'capacidad' => 2, 'orden' => 4],
            ['nombre' => 'Terraza 1', 'zona' => 'Terraza', 'capacidad' => 4, 'orden' => 1],
            ['nombre' => 'Terraza 2', 'zona' => 'Terraza', 'capacidad' => 4, 'orden' => 2],
            ['nombre' => 'Muelle', 'zona' => 'Terraza', 'capacidad' => 2, 'orden' => 3],
            ['nombre' => 'Fogata', 'zona' => 'Exterior', 'capacidad' => 8, 'orden' => 1],
        ];

        foreach ($mesas as &$m) {
            $m['activa']     = 1;
            $m['created_at'] = $ahora;
            $m['updated_at'] = $ahora;
        }

        $this->db->table('mesas')->insertBatch($mesas);

        // Color por categoría para el código de colores del TPV
        $colores = ['Desayunos' => '#c9822e', 'Platos fuertes' => '#1f4d36', 'Bebidas' => '#2e6f8e', 'Postres' => '#8a4b7a'];
        foreach ($colores as $nombre => $color) {
            $this->db->table('carta_categorias')->where('nombre', $nombre)->update(['color' => $color]);
        }
    }
}
