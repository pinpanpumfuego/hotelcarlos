<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registro de rondas enviadas desde el comandero del móvil.
 *
 * El teléfono puede quedarse sin señal en la terraza o en el muelle, así que
 * guarda la ronda y la reintenta luego. Sin esta tabla, un reintento que en
 * realidad sí había llegado duplicaría los platos en cocina. El `uuid` lo
 * genera el teléfono y no cambia entre reintentos: si ya está aquí, la ronda
 * se da por buena y se devuelve la comanda que salió la primera vez.
 */
class ComanderoEnvios extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('comandero_envios')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uuid'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'comanda_id'  => ['type' => 'INT', 'unsigned' => true],
            'empleado_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'lineas'      => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'a_cocina'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // Cuánto tardó la ronda en llegar: delata zonas sin cobertura
            'demora_seg'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('comanda_id');
        $this->forge->addForeignKey('comanda_id', 'comandas', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', '', 'SET NULL');
        $this->forge->createTable('comandero_envios');
    }

    public function down()
    {
        $this->forge->dropTable('comandero_envios', true);
    }
}
