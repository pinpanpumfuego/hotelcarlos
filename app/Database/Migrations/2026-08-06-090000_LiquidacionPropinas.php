<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Liquidación de propinas.
 *
 * La Ley 1935 de 2018 exige que el 100 % de la propina llegue a los
 * trabajadores y que se les informe cómo se repartió. Sin un documento que
 * lo demuestre, cumplirlo de palabra no sirve de nada ante una inspección.
 */
class LiquidacionPropinas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'desde'  => ['type' => 'DATE'],
            'hasta'  => ['type' => 'DATE'],

            'criterio' => ['type' => 'ENUM', 'constraint' => ['ventas', 'partes_iguales', 'manual'], 'default' => 'ventas'],
            'recaudado' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'repartido' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            'estado' => ['type' => 'ENUM', 'constraint' => ['borrador', 'cerrada'], 'default' => 'borrador'],
            'notas'  => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],

            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cerrada_en' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['desde', 'hasta']);
        $this->forge->createTable('propina_liquidaciones');

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'liquidacion_id' => ['type' => 'INT', 'unsigned' => true],
            'empleado_id'    => ['type' => 'INT', 'unsigned' => true],

            // Lo que generó atendiendo: la referencia del reparto por ventas
            'generado'   => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'comandas'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'importe'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            'entregado'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'entregado_en' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['liquidacion_id', 'empleado_id']);
        $this->forge->addForeignKey('liquidacion_id', 'propina_liquidaciones', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('propina_liquidacion_lineas');

        // Para no liquidar dos veces la misma comanda
        $this->forge->addColumn('comandas', [
            'liquidacion_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'propina'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('comandas', 'liquidacion_id');
        $this->forge->dropTable('propina_liquidacion_lineas');
        $this->forge->dropTable('propina_liquidaciones');
    }
}
