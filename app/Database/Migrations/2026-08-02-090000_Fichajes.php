<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Control de jornada: fichajes de entrada, salida y pausas.
 *
 * La foto y la ubicación son datos personales del trabajador (Ley 1581/2012),
 * así que las fotos se guardan fuera de public/ y solo las ve gerencia.
 */
class Fichajes extends Migration
{
    public function up()
    {
        // ── El PIN vive en la ficha del empleado, siempre cifrado ──
        $this->forge->addColumn('empleados', [
            'pin_hash'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'activo'],
            'pin_actualizado' => ['type' => 'DATETIME', 'null' => true, 'after' => 'pin_hash'],
            'ficha_movil'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'pin_actualizado'],
            'foto'            => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'ficha_movil'],
        ]);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'empleado_id' => ['type' => 'INT', 'unsigned' => true],
            'tipo'        => ['type' => 'ENUM', 'constraint' => ['entrada', 'salida', 'pausa_inicio', 'pausa_fin'], 'default' => 'entrada'],
            'marcado_en'  => ['type' => 'DATETIME'],

            'origen'      => ['type' => 'ENUM', 'constraint' => ['terminal', 'movil', 'manual'], 'default' => 'terminal'],
            'foto'        => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            // Solo tiene sentido desde el móvil; el terminal no se mueve
            'latitud'     => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'longitud'    => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'precision_m' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'distancia_m' => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            'ip'          => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'observacion' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            // Rastro de las correcciones: un fichaje corregido nunca se borra
            'editado_por' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'editado_en'  => ['type' => 'DATETIME', 'null' => true],
            'anulado'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'motivo'      => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['empleado_id', 'marcado_en']);
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('fichajes');
    }

    public function down()
    {
        $this->forge->dropTable('fichajes');
        $this->forge->dropColumn('empleados', ['pin_hash', 'pin_actualizado', 'ficha_movil', 'foto']);
    }
}
