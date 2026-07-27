<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Órdenes de compra con recepción parcial.
 *
 * La recepción parcial no es un lujo: **es lo normal**. El proveedor trae ocho
 * kilos de los diez pedidos, o trae los diez pero dos vienen mal. Un sistema
 * que solo sepa «recibido» o «no recibido» obliga a mentir en uno de los dos
 * sentidos, y a partir de ahí el stock deja de cuadrar.
 *
 * Por eso cada línea lleva **lo pedido y lo recibido por separado**, y la
 * diferencia se puede mirar después: es lo que dice qué proveedor cumple.
 */
class Compras extends Migration
{
    public function up()
    {
        $this->ordenes();
        $this->lineas();
    }

    private function ordenes(): void
    {
        if ($this->db->tableExists('ordenes_compra')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'numero'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'proveedor_id' => ['type' => 'INT', 'unsigned' => true],
            'bodega_id'    => ['type' => 'INT', 'unsigned' => true],

            // `borrador` se puede editar; a partir de `enviada`, no: si se
            // pudiera cambiar lo pedido después de pedirlo, comparar lo pedido
            // con lo recibido dejaría de significar nada.
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['borrador', 'enviada', 'parcial', 'recibida', 'anulada'],
                'default'    => 'borrador',
            ],

            'fecha'          => ['type' => 'DATE'],
            'fecha_esperada' => ['type' => 'DATE', 'null' => true],
            'total'          => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],

            // El papel que respalda la compra: número de factura y, si se sube,
            // el archivo. Sin esto, una entrada de mercancía no se puede
            // justificar ante nadie.
            'documento'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'archivo'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            'notas'       => ['type' => 'TEXT', 'null' => true],
            'usuario_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'enviada_en'  => ['type' => 'DATETIME', 'null' => true],
            'recibida_en' => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('numero');
        $this->forge->addKey(['estado', 'fecha']);
        $this->forge->addForeignKey('proveedor_id', 'proveedores', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('bodega_id', 'bodegas', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('ordenes_compra');
    }

    private function lineas(): void
    {
        if ($this->db->tableExists('orden_compra_lineas')) {
            return;
        }

        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'orden_id'  => ['type' => 'INT', 'unsigned' => true],
            'insumo_id' => ['type' => 'INT', 'unsigned' => true],

            'cantidad_pedida'   => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'cantidad_recibida' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'costo_unitario'    => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],

            // El lote llega con la mercancía, no con el pedido: se rellena al
            // recibir.
            'lote'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'caduca_el' => ['type' => 'DATE', 'null' => true],

            // Por qué no vino todo lo pedido. Es lo que después explica al
            // proveedor por qué se le paga menos.
            'motivo_diferencia' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('orden_id');
        $this->forge->addForeignKey('orden_id', 'ordenes_compra', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('insumo_id', 'insumos', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('orden_compra_lineas');
    }

    public function down()
    {
        foreach (['orden_compra_lineas', 'ordenes_compra'] as $tabla) {
            if ($this->db->tableExists($tabla)) {
                $this->forge->dropTable($tabla);
            }
        }
    }
}
