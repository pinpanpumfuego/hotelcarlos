<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Destino de cada producto al enviar la comanda:
 *  - cocina: se prepara en cocina
 *  - barra: lo prepara el bar (jugos, cócteles)
 *  - directo: se entrega tal cual (cerveza, agua embotellada), no pasa por preparación
 */
class DestinoProducto extends Migration
{
    public function up()
    {
        $this->forge->addColumn('carta_productos', [
            'destino' => [
                'type'       => 'ENUM',
                'constraint' => ['cocina', 'barra', 'directo'],
                'default'    => 'cocina',
                'after'      => 'precio',
            ],
        ]);

        $this->forge->addColumn('comanda_lineas', [
            'destino' => [
                'type'       => 'ENUM',
                'constraint' => ['cocina', 'barra', 'directo'],
                'default'    => 'cocina',
                'after'      => 'nombre_producto',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('comanda_lineas', 'destino');
        $this->forge->dropColumn('carta_productos', 'destino');
    }
}
