<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tercer estado de la línea: cocina la marca lista (entregado) y el
 * mesero confirma que la llevó a la mesa (servido).
 */
class LineaServida extends Migration
{
    public function up()
    {
        $this->forge->addColumn('comanda_lineas', [
            'servido'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'entregado'],
            'listo_en'  => ['type' => 'DATETIME', 'null' => true, 'after' => 'servido'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('comanda_lineas', ['servido', 'listo_en']);
    }
}
