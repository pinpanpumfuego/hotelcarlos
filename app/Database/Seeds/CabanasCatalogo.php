<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Catálogo de partida para las cabañas: servicios que se anuncian
 * e inventario de enseres que se revisa después de cada estancia.
 * Todo es editable desde el panel; esto solo evita empezar de cero.
 */
class CabanasCatalogo extends Seeder
{
    public function run()
    {
        $ahora = date('Y-m-d H:i:s');

        // ── Servicios y equipamiento ──
        $servicios = [
            // Comodidades
            ['Agua caliente', 'Comodidades', 'bi-droplet-half'],
            ['Wifi', 'Comodidades', 'bi-wifi'],
            ['Calefacción', 'Comodidades', 'bi-thermometer-sun'],
            ['Chimenea', 'Comodidades', 'bi-fire'],
            ['Ropa de cama y toallas', 'Comodidades', 'bi-layers'],
            ['Caja fuerte', 'Comodidades', 'bi-safe'],
            // Baño
            ['Baño privado', 'Baño', 'bi-house-door'],
            ['Ducha', 'Baño', 'bi-moisture'],
            ['Secador de pelo', 'Baño', 'bi-wind'],
            ['Amenidades de baño', 'Baño', 'bi-flower1'],
            // Exterior
            ['Terraza privada', 'Exterior', 'bi-sun'],
            ['Vista al lago', 'Exterior', 'bi-water'],
            ['Hamaca', 'Exterior', 'bi-tree'],
            ['Zona de fogata', 'Exterior', 'bi-fire'],
            ['Parqueadero', 'Exterior', 'bi-p-square'],
            // Cocina
            ['Nevera', 'Cocina', 'bi-box'],
            ['Cafetera', 'Cocina', 'bi-cup-hot'],
            ['Cocineta', 'Cocina', 'bi-fire'],
            ['Vajilla y menaje', 'Cocina', 'bi-egg-fried'],
            // Servicios del ecolodge
            ['Desayuno incluido', 'Servicios', 'bi-egg-fried'],
            ['Aseo diario', 'Servicios', 'bi-bucket'],
            ['Admite mascotas', 'Servicios', 'bi-heart'],
            ['Acceso sin escaleras', 'Servicios', 'bi-universal-access'],
        ];

        $filas = [];
        foreach ($servicios as $i => [$nombre, $grupo, $icono]) {
            $filas[] = [
                'nombre' => $nombre, 'grupo' => $grupo, 'icono' => $icono,
                'orden' => $i, 'activo' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
            ];
        }
        $this->db->table('servicios')->ignore(true)->insertBatch($filas);

        // ── Inventario de enseres ──
        // El valor de reposición sirve para cobrar en el folio lo que se llevan.
        $items = [
            ['Almohada', 'Dormitorio', 35000, 2],
            ['Cobija', 'Dormitorio', 90000, 2],
            ['Juego de sábanas', 'Dormitorio', 120000, 1],
            ['Cubrelecho', 'Dormitorio', 150000, 1],
            ['Toalla de cuerpo', 'Baño', 40000, 2],
            ['Toalla de mano', 'Baño', 18000, 2],
            ['Secador de pelo', 'Baño', 90000, 1],
            ['Tapete de baño', 'Baño', 25000, 1],
            ['Control del televisor', 'Sala', 60000, 1],
            ['Lámpara de noche', 'Sala', 80000, 2],
            ['Linterna', 'Sala', 30000, 1],
            ['Vaso de vidrio', 'Cocina', 8000, 4],
            ['Taza', 'Cocina', 12000, 2],
            ['Plato', 'Cocina', 15000, 4],
            ['Cubiertos (juego)', 'Cocina', 20000, 4],
            ['Hamaca', 'Exterior', 180000, 1],
            ['Silla de terraza', 'Exterior', 140000, 2],
            ['Sombrilla', 'Exterior', 60000, 1],
        ];

        $filas = [];
        foreach ($items as $i => [$nombre, $grupo, $valor, $cantidad]) {
            $filas[] = [
                'nombre' => $nombre, 'grupo' => $grupo,
                'valor_reposicion' => $valor, 'cantidad_estandar' => $cantidad,
                'orden' => $i, 'activo' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
            ];
        }
        $this->db->table('inventario_items')->ignore(true)->insertBatch($filas);

        // ── Se asignan a lo que ya existe ──
        $tipos = $this->db->table('tipos_unidad')->get()->getResultArray();
        $todosServicios = $this->db->table('servicios')->get()->getResultArray();

        // Por defecto se marcan los servicios básicos de un ecolodge rural
        $basicos = ['Agua caliente', 'Wifi', 'Ropa de cama y toallas', 'Baño privado', 'Ducha',
            'Terraza privada', 'Vista al lago', 'Hamaca', 'Zona de fogata', 'Parqueadero',
            'Desayuno incluido', 'Aseo diario'];

        foreach ($tipos as $tipo) {
            foreach ($todosServicios as $s) {
                if (in_array($s['nombre'], $basicos, true)) {
                    $this->db->table('tipo_servicios')->ignore(true)->insert([
                        'tipo_unidad_id' => $tipo['id'],
                        'servicio_id'    => $s['id'],
                    ]);
                }
            }
        }

        // Inventario estándar en cada cabaña
        $unidades   = $this->db->table('unidades')->get()->getResultArray();
        $todosItems = $this->db->table('inventario_items')->get()->getResultArray();

        foreach ($unidades as $u) {
            foreach ($todosItems as $item) {
                $this->db->table('unidad_inventario')->ignore(true)->insert([
                    'unidad_id' => $u['id'],
                    'item_id'   => $item['id'],
                    'cantidad'  => $item['cantidad_estandar'],
                ]);
            }
        }
    }
}
