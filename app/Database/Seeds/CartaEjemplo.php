<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Carta inicial de ejemplo: categorías y platos típicos de la región. */
class CartaEjemplo extends Seeder
{
    public function run()
    {
        if ($this->db->table('carta_categorias')->countAllResults() > 0) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');

        $categorias = [
            ['nombre' => 'Desayunos', 'orden' => 1],
            ['nombre' => 'Platos fuertes', 'orden' => 2],
            ['nombre' => 'Bebidas', 'orden' => 3],
            ['nombre' => 'Postres', 'orden' => 4],
        ];
        foreach ($categorias as &$c) {
            $c['created_at'] = $ahora;
            $c['updated_at'] = $ahora;
        }
        unset($c);
        $this->db->table('carta_categorias')->insertBatch($categorias);

        $ids = [];
        foreach ($this->db->table('carta_categorias')->get()->getResultArray() as $c) {
            $ids[$c['nombre']] = $c['id'];
        }

        $productos = [
            ['cat' => 'Desayunos', 'nombre' => 'Calentado paisa', 'desc' => 'Arroz, frijol, huevo, arepa y chorizo', 'precio' => 18000],
            ['cat' => 'Desayunos', 'nombre' => 'Huevos al gusto', 'desc' => 'Con arepa y café', 'precio' => 14000],
            ['cat' => 'Desayunos', 'nombre' => 'Fruta y yogur', 'desc' => 'Frutas de la región con granola', 'precio' => 12000],
            ['cat' => 'Platos fuertes', 'nombre' => 'Trucha al ajillo', 'desc' => 'Con patacón y ensalada', 'precio' => 38000],
            ['cat' => 'Platos fuertes', 'nombre' => 'Pechuga a la plancha', 'desc' => 'Con arroz y verduras', 'precio' => 32000],
            ['cat' => 'Platos fuertes', 'nombre' => 'Sancocho de gallina', 'desc' => 'Servido con arroz y aguacate', 'precio' => 30000],
            ['cat' => 'Bebidas', 'nombre' => 'Café de la región', 'desc' => null, 'precio' => 5000],
            ['cat' => 'Bebidas', 'nombre' => 'Jugo natural', 'desc' => 'En agua o leche', 'precio' => 8000],
            ['cat' => 'Bebidas', 'nombre' => 'Cerveza', 'desc' => null, 'precio' => 9000],
            ['cat' => 'Bebidas', 'nombre' => 'Agua', 'desc' => null, 'precio' => 4000],
            ['cat' => 'Postres', 'nombre' => 'Postre de la casa', 'desc' => null, 'precio' => 12000],
        ];

        $filas = [];
        foreach ($productos as $p) {
            $filas[] = [
                'categoria_id' => $ids[$p['cat']],
                'nombre'       => $p['nombre'],
                'descripcion'  => $p['desc'],
                'precio'       => $p['precio'],
                'disponible'   => 1,
                'created_at'   => $ahora,
                'updated_at'   => $ahora,
            ];
        }
        $this->db->table('carta_productos')->insertBatch($filas);
    }
}
