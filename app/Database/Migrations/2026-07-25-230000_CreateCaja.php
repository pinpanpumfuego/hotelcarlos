<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Caja por turnos: apertura con base, movimientos de efectivo
 * y cierre con arqueo (conteo y diferencia).
 */
class CreateCaja extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'usuario_id'       => ['type' => 'INT', 'unsigned' => true],
            'base_inicial'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'apertura'         => ['type' => 'DATETIME'],
            'cierre'           => ['type' => 'DATETIME', 'null' => true],
            'efectivo_contado' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'diferencia'       => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'notas'            => ['type' => 'TEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('caja_turnos');

        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'turno_id'            => ['type' => 'INT', 'unsigned' => true],
            'tipo'                => ['type' => 'ENUM', 'constraint' => ['ingreso', 'egreso']],
            'concepto'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'valor'               => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'usuario_id'          => ['type' => 'INT', 'unsigned' => true],
            'folio_movimiento_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('turno_id');
        $this->forge->addForeignKey('turno_id', 'caja_turnos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('caja_movimientos');
    }

    public function down()
    {
        $this->forge->dropTable('caja_movimientos');
        $this->forge->dropTable('caja_turnos');
    }
}
