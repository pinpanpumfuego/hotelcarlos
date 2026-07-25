<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ampliación para el TPV táctil: mesas configurables, descuentos,
 * envío a cocina y datos del cobro (recibido y cambio).
 */
class AmpliarTpv extends Migration
{
    public function up()
    {
        // Mesas del restaurante (mapa de salón)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 60],
            'zona'       => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'Salón'],
            'capacidad'  => ['type' => 'INT', 'unsigned' => true, 'default' => 4],
            'orden'      => ['type' => 'INT', 'default' => 0],
            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('mesas');

        $this->forge->addColumn('comandas', [
            'mesa_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'mesa'],
            'descuento'       => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'total'],
            'motivo_descuento' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'descuento'],
            'recibido'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'forma_pago'],
            'cambio'          => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'recibido'],
            'comensales'      => ['type' => 'INT', 'unsigned' => true, 'default' => 1, 'after' => 'mesa_id'],
        ]);

        $this->forge->addColumn('comanda_lineas', [
            'enviado_cocina' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'cantidad'],
        ]);

        // Color por categoría, para el código de colores del TPV
        $this->forge->addColumn('carta_categorias', [
            'color' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '#4f8a68', 'after' => 'orden'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('carta_categorias', 'color');
        $this->forge->dropColumn('comanda_lineas', 'enviado_cocina');
        $this->forge->dropColumn('comandas', ['mesa_id', 'descuento', 'motivo_descuento', 'recibido', 'cambio', 'comensales']);
        $this->forge->dropTable('mesas');
    }
}
