<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Folio del huésped: la cuenta de cada reserva.
 * Cargos (alojamiento, consumos, daños...) y pagos (efectivo, tarjeta...).
 * El saldo = suma de cargos - suma de pagos; debe quedar en 0 para el check-out.
 */
class CreateFolio extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true],
            'tipo'       => ['type' => 'ENUM', 'constraint' => ['cargo', 'pago']],
            'concepto'   => ['type' => 'VARCHAR', 'constraint' => 150],
            'valor'      => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'metodo'     => ['type' => 'ENUM', 'constraint' => ['efectivo', 'tarjeta', 'transferencia', 'wompi', 'otro'], 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reserva_id');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('folio_movimientos');
    }

    public function down()
    {
        $this->forge->dropTable('folio_movimientos');
    }
}
