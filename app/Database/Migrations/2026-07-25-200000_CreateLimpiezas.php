<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registros de limpieza: quién limpió cada cabaña, cuándo empezó,
 * cuándo terminó y qué novedades encontró (daños, objetos olvidados...).
 */
class CreateLimpiezas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id'  => ['type' => 'INT', 'unsigned' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true],
            'inicio'     => ['type' => 'DATETIME'],
            'fin'        => ['type' => 'DATETIME', 'null' => true],
            'notas'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('unidad_id');
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('limpiezas');
    }

    public function down()
    {
        $this->forge->dropTable('limpiezas');
    }
}
