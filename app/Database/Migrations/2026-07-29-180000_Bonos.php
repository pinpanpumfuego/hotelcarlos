<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bonos regalo: saldo prepagado.
 * Ojo contable: un bono vendido es dinero cobrado por adelantado (un pasivo),
 * no un descuento. Por eso al canjearlo se registra como forma de pago.
 */
class Bonos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'codigo'          => ['type' => 'VARCHAR', 'constraint' => 30],
            'importe_inicial' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'saldo'           => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            // Quién lo compra y para quién es
            'comprador_nombre'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'comprador_email'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'comprador_telefono' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'beneficiario'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'mensaje'            => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],

            'caduca'     => ['type' => 'DATE', 'null' => true],
            'forma_pago' => ['type' => 'ENUM', 'constraint' => ['efectivo', 'tarjeta', 'transferencia', 'wompi', 'otro'], 'default' => 'efectivo'],
            'estado'     => ['type' => 'ENUM', 'constraint' => ['activo', 'anulado'], 'default' => 'activo'],
            'notas'      => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->createTable('bonos');

        // Historial de cada movimiento del saldo
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'bono_id'    => ['type' => 'INT', 'unsigned' => true],
            'tipo'       => ['type' => 'ENUM', 'constraint' => ['emision', 'consumo', 'devolucion', 'anulacion'], 'default' => 'consumo'],
            'valor'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'saldo_despues' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'concepto'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'comanda_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('bono_id', 'bonos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('comanda_id', 'comandas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('bono_movimientos');

        // El bono es una forma de pago más, tanto en el folio como en el TPV
        $this->db->query("ALTER TABLE folio_movimientos MODIFY metodo ENUM('efectivo','tarjeta','transferencia','wompi','bono','otro') NULL");
        $this->db->query("ALTER TABLE comanda_pagos MODIFY forma_pago ENUM('efectivo','tarjeta','transferencia','wompi','habitacion','bono') NOT NULL");
        $this->db->query("ALTER TABLE comandas MODIFY forma_pago ENUM('efectivo','tarjeta','transferencia','wompi','habitacion','bono') NULL");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE comandas MODIFY forma_pago ENUM('efectivo','tarjeta','transferencia','wompi','habitacion') NULL");
        $this->db->query("ALTER TABLE comanda_pagos MODIFY forma_pago ENUM('efectivo','tarjeta','transferencia','wompi','habitacion') NOT NULL");
        $this->db->query("ALTER TABLE folio_movimientos MODIFY metodo ENUM('efectivo','tarjeta','transferencia','wompi','otro') NULL");
        $this->forge->dropTable('bono_movimientos');
        $this->forge->dropTable('bonos');
    }
}
