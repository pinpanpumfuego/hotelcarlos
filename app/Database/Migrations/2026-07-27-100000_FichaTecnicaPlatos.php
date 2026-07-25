<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ficha técnica del plato, modificadores, escandallo y productos divisibles.
 */
class FichaTecnicaPlatos extends Migration
{
    public function up()
    {
        // ── 1. Ficha técnica: dietas, picante y alérgenos ──
        $this->forge->addColumn('carta_productos', [
            'apto_vegano'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'destino'],
            'apto_vegetariano' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'apto_vegano'],
            'sin_gluten'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'apto_vegetariano'],
            'sin_lactosa'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'sin_gluten'],
            'picante'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'sin_lactosa'],
            'alergenos'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'picante'],
            'divisible'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'alergenos'],
        ]);

        // ── 2. Modificadores ──
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'tipo'        => ['type' => 'ENUM', 'constraint' => ['unico', 'multiple'], 'default' => 'multiple'],
            'obligatorio' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'orden'       => ['type' => 'INT', 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('modificador_grupos');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'grupo_id'     => ['type' => 'INT', 'unsigned' => true],
            'nombre'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'precio_extra' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'orden'        => ['type' => 'INT', 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('grupo_id', 'modificador_grupos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('modificadores');

        // Qué grupos de modificadores se aplican a qué producto
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'producto_id' => ['type' => 'INT', 'unsigned' => true],
            'grupo_id'    => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['producto_id', 'grupo_id']);
        $this->forge->addForeignKey('producto_id', 'carta_productos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('grupo_id', 'modificador_grupos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('producto_modificador_grupos');

        // Modificadores elegidos en una línea de comanda (guardan nombre y precio del momento)
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'linea_id'       => ['type' => 'INT', 'unsigned' => true],
            'modificador_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'nombre'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'precio_extra'   => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('linea_id');
        $this->forge->addForeignKey('linea_id', 'comanda_lineas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('modificador_id', 'modificadores', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('comanda_linea_modificadores');

        // ── 3. Escandallo: insumos y recetas ──
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'          => ['type' => 'VARCHAR', 'constraint' => 120],
            'unidad'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'g'],
            'costo_unitario'  => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],
            'proveedor'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'activo'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('insumos');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'producto_id' => ['type' => 'INT', 'unsigned' => true],
            'insumo_id'   => ['type' => 'INT', 'unsigned' => true],
            'cantidad'    => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('producto_id');
        $this->forge->addForeignKey('producto_id', 'carta_productos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('insumo_id', 'insumos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('receta_lineas');

        // ── 4. Composición de mitades en la línea de comanda ──
        $this->forge->addColumn('comanda_lineas', [
            'composicion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'nombre_producto'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('comanda_lineas', 'composicion');
        $this->forge->dropTable('receta_lineas');
        $this->forge->dropTable('insumos');
        $this->forge->dropTable('comanda_linea_modificadores');
        $this->forge->dropTable('producto_modificador_grupos');
        $this->forge->dropTable('modificadores');
        $this->forge->dropTable('modificador_grupos');
        $this->forge->dropColumn('carta_productos', [
            'apto_vegano', 'apto_vegetariano', 'sin_gluten', 'sin_lactosa',
            'picante', 'alergenos', 'divisible',
        ]);
    }
}
