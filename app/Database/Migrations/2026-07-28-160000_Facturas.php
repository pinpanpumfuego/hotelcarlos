<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Facturación electrónica a través de Siigo (proveedor tecnológico
 * autorizado por la DIAN).
 *
 * Se guarda una copia local de cada factura emitida: número, CUFE y enlace
 * público. Así el hotel conserva la trazabilidad aunque cambie de proveedor.
 */
class Facturas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],

            // De dónde sale la factura
            'origen'        => ['type' => 'ENUM', 'constraint' => ['reserva', 'comanda', 'manual'], 'default' => 'reserva'],
            'reserva_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'comanda_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            // Datos del cliente en el momento de facturar
            'cliente_nombre'    => ['type' => 'VARCHAR', 'constraint' => 200],
            'cliente_documento' => ['type' => 'VARCHAR', 'constraint' => 50],
            'cliente_email'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],

            // Importes
            'subtotal'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'impuestos'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'total'         => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            // Respuesta de Siigo / DIAN
            'estado'        => ['type' => 'ENUM', 'constraint' => ['pendiente', 'emitida', 'error', 'anulada'], 'default' => 'pendiente'],
            'siigo_id'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'numero'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'cufe'          => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'url_publica'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'respuesta'     => ['type' => 'TEXT', 'null' => true],
            'error'         => ['type' => 'TEXT', 'null' => true],

            'emitida_en'    => ['type' => 'DATETIME', 'null' => true],
            'usuario_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reserva_id');
        $this->forge->addKey('comanda_id');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('comanda_id', 'comandas', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('facturas');

        // Líneas de la factura, tal como se enviaron
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'factura_id'  => ['type' => 'INT', 'unsigned' => true],
            'codigo'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255],
            'cantidad'    => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 1],
            'precio'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('factura_id');
        $this->forge->addForeignKey('factura_id', 'facturas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('factura_lineas');
    }

    public function down()
    {
        $this->forge->dropTable('factura_lineas');
        $this->forge->dropTable('facturas');
    }
}
