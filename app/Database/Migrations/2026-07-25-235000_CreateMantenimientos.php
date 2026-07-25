<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Incidencias de mantenimiento: reporte, prioridad, estado y resolución.
 * Pueden asociarse a una cabaña (y bloquearla) o a zonas comunes.
 */
class CreateMantenimientos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'ubicacion'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'titulo'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'descripcion'  => ['type' => 'TEXT', 'null' => true],
            'prioridad'    => ['type' => 'ENUM', 'constraint' => ['baja', 'media', 'alta', 'urgente'], 'default' => 'media'],
            'estado'       => ['type' => 'ENUM', 'constraint' => ['abierta', 'en_proceso', 'resuelta'], 'default' => 'abierta'],
            'reporto_id'   => ['type' => 'INT', 'unsigned' => true],
            'resolvio_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'resuelta_en'  => ['type' => 'DATETIME', 'null' => true],
            'solucion'     => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('unidad_id');
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('mantenimientos');
    }

    public function down()
    {
        $this->forge->dropTable('mantenimientos');
    }
}
