<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cliente de la comanda cuando no es un huésped alojado:
 * cliente ocasional identificado por nombre (y datos opcionales).
 */
class ClienteComanda extends Migration
{
    public function up()
    {
        $this->forge->addColumn('comandas', [
            'cliente_nombre'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'reserva_id'],
            'cliente_documento' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'cliente_nombre'],
            'cliente_telefono'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'cliente_documento'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('comandas', ['cliente_nombre', 'cliente_documento', 'cliente_telefono']);
    }
}
