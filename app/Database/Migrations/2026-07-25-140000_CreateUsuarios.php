<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsuarios extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'clave_hash'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'rol'           => ['type' => 'ENUM', 'constraint' => ['gerencia', 'recepcion', 'limpieza'], 'default' => 'recepcion'],
            'activo'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'ultimo_acceso' => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('usuarios');
    }

    public function down()
    {
        $this->forge->dropTable('usuarios');
    }
}
