<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registro de correos enviados: permite comprobar qué salió,
 * a quién y por qué falló, sin depender del proveedor.
 */
class CorreosLog extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tipo'         => ['type' => 'VARCHAR', 'constraint' => 50],
            'destinatario' => ['type' => 'VARCHAR', 'constraint' => 200],
            'asunto'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'estado'       => ['type' => 'ENUM', 'constraint' => ['enviado', 'fallido', 'sin_configurar'], 'default' => 'enviado'],
            'error'        => ['type' => 'TEXT', 'null' => true],
            'reserva_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'usuario_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reserva_id');
        $this->forge->createTable('correos_log');
    }

    public function down()
    {
        $this->forge->dropTable('correos_log');
    }
}
