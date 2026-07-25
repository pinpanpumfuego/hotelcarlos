<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Configuración del sistema en formato clave-valor.
 * Los valores sensibles (contraseñas, llaves privadas) se guardan cifrados.
 */
class CreateConfiguracion extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'clave'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'valor'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('configuracion');
    }

    public function down()
    {
        $this->forge->dropTable('configuracion');
    }
}
