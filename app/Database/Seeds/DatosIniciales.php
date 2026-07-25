<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DatosIniciales extends Seeder
{
    public function run()
    {
        $ahora = date('Y-m-d H:i:s');

        $this->db->table('tipos_unidad')->insertBatch([
            ['nombre' => 'Habitación estándar', 'descripcion' => 'Habitación doble con baño privado y vista al jardín.', 'capacidad' => 2, 'tarifa_base' => 180000, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Cabaña familiar', 'descripcion' => 'Cabaña independiente con dos habitaciones, cocina y terraza.', 'capacidad' => 5, 'tarifa_base' => 350000, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Glamping', 'descripcion' => 'Tienda glamping con cama queen y vista a la montaña.', 'capacidad' => 2, 'tarifa_base' => 250000, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        $this->db->table('unidades')->insertBatch([
            ['tipo_id' => 1, 'nombre' => 'Habitación 1', 'estado' => 'disponible', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo_id' => 1, 'nombre' => 'Habitación 2', 'estado' => 'disponible', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo_id' => 1, 'nombre' => 'Habitación 3', 'estado' => 'limpieza', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo_id' => 2, 'nombre' => 'Cabaña El Roble', 'estado' => 'disponible', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo_id' => 2, 'nombre' => 'Cabaña La Ceiba', 'estado' => 'ocupada', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo_id' => 3, 'nombre' => 'Glamping Mirador', 'estado' => 'disponible', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }
}
