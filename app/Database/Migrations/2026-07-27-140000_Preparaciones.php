<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Subrecetas: preparaciones intermedias (hogao, masa de pizza, salsa de ajillo)
 * que se elaboran en tandas y se usan por cantidad en varios platos.
 *
 * Una preparación es un insumo con receta propia y rendimiento: su coste por
 * unidad se calcula (coste de la tanda ÷ rendimiento) en vez de teclearse.
 */
class Preparaciones extends Migration
{
    public function up()
    {
        $this->forge->addColumn('insumos', [
            'es_preparacion' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'unidad'],
            'rendimiento'    => ['type' => 'DECIMAL', 'constraint' => '12,3', 'null' => true, 'after' => 'es_preparacion'],
            'notas'          => ['type' => 'TEXT', 'null' => true, 'after' => 'proveedor'],
        ]);

        // Componentes de una preparación (pueden ser insumos u otras preparaciones)
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'preparacion_id' => ['type' => 'INT', 'unsigned' => true],
            'insumo_id'      => ['type' => 'INT', 'unsigned' => true],
            'cantidad'       => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('preparacion_id');
        $this->forge->addForeignKey('preparacion_id', 'insumos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('insumo_id', 'insumos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('preparacion_lineas');
    }

    public function down()
    {
        $this->forge->dropTable('preparacion_lineas');
        $this->forge->dropColumn('insumos', ['es_preparacion', 'rendimiento', 'notas']);
    }
}
