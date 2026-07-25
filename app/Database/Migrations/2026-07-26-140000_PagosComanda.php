<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pagos de la comanda: permite dividir la cuenta en varios pagos
 * (distintas personas o formas de pago) y registrar propina.
 */
class PagosComanda extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'comanda_id' => ['type' => 'INT', 'unsigned' => true],
            'forma_pago' => ['type' => 'ENUM', 'constraint' => ['efectivo', 'tarjeta', 'transferencia', 'wompi', 'habitacion']],
            'valor'      => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'recibido'   => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'cambio'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('comanda_id');
        $this->forge->addForeignKey('comanda_id', 'comandas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('comanda_pagos');

        $this->forge->addColumn('comandas', [
            'propina' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'motivo_descuento'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('comandas', 'propina');
        $this->forge->dropTable('comanda_pagos');
    }
}
