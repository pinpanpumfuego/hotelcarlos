<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Inventario real del ecolodge: 7 cabañas iguales.
 * Sustituye los datos de ejemplo (borra reservas y unidades previas).
 */
class InventarioReal extends Seeder
{
    public function run()
    {
        $ahora = date('Y-m-d H:i:s');

        // Orden de borrado respetando las claves foráneas
        $this->db->table('reservas')->where('id >', 0)->delete();
        $this->db->table('unidades')->where('id >', 0)->delete();
        $this->db->table('tipos_unidad')->where('id >', 0)->delete();

        $this->db->table('tipos_unidad')->insert([
            'nombre'      => 'Cabaña',
            'descripcion' => 'Cabaña triangular con techo de paja a la orilla del lago, con terraza de madera y vista a las montañas.',
            'capacidad'   => 4,          // PROVISIONAL: confirmar capacidad real
            'tarifa_base' => 350000,     // PROVISIONAL: confirmar tarifa real
            'created_at'  => $ahora,
            'updated_at'  => $ahora,
        ]);

        $tipoId = $this->db->insertID();

        $unidades = [];
        for ($i = 1; $i <= 7; $i++) {
            $unidades[] = [
                'tipo_id'    => $tipoId,
                'nombre'     => 'Cabaña ' . $i,
                'estado'     => 'disponible',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }
        $this->db->table('unidades')->insertBatch($unidades);
    }
}
