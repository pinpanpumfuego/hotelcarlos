<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Cupones de descuento y registro de sus usos. */
class Cupones extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'codigo'      => ['type' => 'VARCHAR', 'constraint' => 40],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'tipo'   => ['type' => 'ENUM', 'constraint' => ['porcentaje', 'valor'], 'default' => 'porcentaje'],
            'valor'  => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'ambito' => ['type' => 'ENUM', 'constraint' => ['alojamiento', 'restaurante', 'todo'], 'default' => 'alojamiento'],

            // Vigencia y condiciones
            'desde'          => ['type' => 'DATE', 'null' => true],
            'hasta'          => ['type' => 'DATE', 'null' => true],
            'importe_minimo' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'descuento_maximo' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'limite_usos'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'limite_por_huesped' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'usos'           => ['type' => 'INT', 'unsigned' => true, 'default' => 0],

            // Dónde se puede canjear
            'en_web'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'en_recepcion' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'en_tpv'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            'activo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->createTable('cupones');

        // Cada canje queda registrado: sin esto no se pueden controlar los límites
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'cupon_id'   => ['type' => 'INT', 'unsigned' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'comanda_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'huesped_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'base'       => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'descuento'  => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'canal'      => ['type' => 'ENUM', 'constraint' => ['web', 'recepcion', 'tpv'], 'default' => 'recepcion'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('cupon_id', 'cupones', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('comanda_id', 'comandas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cupon_usos');

        // El folio necesita un tipo propio para los descuentos: no son ni cargo ni pago
        $this->db->query("ALTER TABLE folio_movimientos MODIFY tipo ENUM('cargo','pago','descuento') NOT NULL");

        // La comanda recuerda con qué cupón se descontó
        $this->forge->addColumn('comandas', [
            'cupon_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'descuento'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('comandas', 'cupon_id');
        $this->db->query("ALTER TABLE folio_movimientos MODIFY tipo ENUM('cargo','pago') NOT NULL");
        $this->forge->dropTable('cupon_usos');
        $this->forge->dropTable('cupones');
    }
}
