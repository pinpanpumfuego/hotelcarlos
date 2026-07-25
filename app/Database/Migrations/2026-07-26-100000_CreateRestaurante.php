<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Restaurante: carta (categorías y productos), comandas y sus líneas.
 * Una comanda puede cobrarse directamente o cargarse al folio de una reserva.
 */
class CreateRestaurante extends Migration
{
    public function up()
    {
        // Categorías de la carta
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 80],
            'orden'      => ['type' => 'INT', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('carta_categorias');

        // Productos
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'categoria_id' => ['type' => 'INT', 'unsigned' => true],
            'nombre'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'descripcion'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'precio'       => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'disponible'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('categoria_id', 'carta_categorias', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('carta_productos');

        // Comandas (cuentas del restaurante)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'numero'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'mesa'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'estado'     => ['type' => 'ENUM', 'constraint' => ['abierta', 'cobrada', 'anulada'], 'default' => 'abierta'],
            'total'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'forma_pago' => ['type' => 'ENUM', 'constraint' => ['efectivo', 'tarjeta', 'transferencia', 'wompi', 'habitacion'], 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true],
            'cerrada_en' => ['type' => 'DATETIME', 'null' => true],
            'notas'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('numero');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('comandas');

        // Líneas de la comanda (guardan nombre y precio del momento)
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'comanda_id'      => ['type' => 'INT', 'unsigned' => true],
            'producto_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'nombre_producto' => ['type' => 'VARCHAR', 'constraint' => 120],
            'precio_unitario' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'cantidad'        => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'entregado'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'notas'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('comanda_id');
        $this->forge->addForeignKey('comanda_id', 'comandas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('producto_id', 'carta_productos', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('comanda_lineas');
    }

    public function down()
    {
        $this->forge->dropTable('comanda_lineas');
        $this->forge->dropTable('comandas');
        $this->forge->dropTable('carta_productos');
        $this->forge->dropTable('carta_categorias');
    }
}
