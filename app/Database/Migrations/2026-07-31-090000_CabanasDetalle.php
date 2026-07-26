<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ficha completa de las cabañas: galería, servicios e inventario de enseres.
 *
 * Reparto: lo que se anuncia va en el tipo de alojamiento (es lo que se vende);
 * lo que se limpia, se avería y se cuenta va en la unidad física.
 */
class CabanasDetalle extends Migration
{
    public function up()
    {
        // ── Galería: fotos y vídeos ──
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tipo_unidad_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'unidad_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'tipo'           => ['type' => 'ENUM', 'constraint' => ['foto', 'video'], 'default' => 'foto'],
            'archivo'        => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'miniatura'      => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'url'            => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'titulo'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'alt'            => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'orden'          => ['type' => 'INT', 'default' => 0],
            'portada'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'usuario_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tipo_unidad_id', 'orden']);
        $this->forge->addKey(['unidad_id', 'orden']);
        $this->forge->addForeignKey('tipo_unidad_id', 'tipos_unidad', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('medios');

        // ── Catálogo de servicios y equipamiento ──
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 80],
            'grupo'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Comodidades'],
            'icono'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'bi-check-circle'],
            'orden'  => ['type' => 'INT', 'default' => 0],
            'activo' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('servicios');

        // Servicios que tiene un tipo de alojamiento (lo que se anuncia)
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tipo_unidad_id' => ['type' => 'INT', 'unsigned' => true],
            'servicio_id'    => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tipo_unidad_id', 'servicio_id']);
        $this->forge->addForeignKey('tipo_unidad_id', 'tipos_unidad', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('servicio_id', 'servicios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tipo_servicios');

        // Excepciones de una cabaña concreta: «esta sí tiene» o «esta no tiene»
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id'   => ['type' => 'INT', 'unsigned' => true],
            'servicio_id' => ['type' => 'INT', 'unsigned' => true],
            'estado'      => ['type' => 'ENUM', 'constraint' => ['si', 'no'], 'default' => 'si'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['unidad_id', 'servicio_id']);
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('servicio_id', 'servicios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('unidad_servicios');

        // ── Inventario de enseres ──
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'grupo'             => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Dormitorio'],
            'valor_reposicion'  => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'cantidad_estandar' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'orden'             => ['type' => 'INT', 'default' => 0],
            'activo'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('inventario_items');

        // Lo que debe haber en cada cabaña
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id' => ['type' => 'INT', 'unsigned' => true],
            'item_id'   => ['type' => 'INT', 'unsigned' => true],
            'cantidad'  => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['unidad_id', 'item_id']);
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'inventario_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('unidad_inventario');

        // Revisiones: qué se encontró de verdad al repasar la cabaña
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id'   => ['type' => 'INT', 'unsigned' => true],
            'reserva_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'usuario_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'estado'      => ['type' => 'ENUM', 'constraint' => ['ok', 'incidencias'], 'default' => 'ok'],
            'faltantes'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'danados'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'notas'       => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventario_revisiones');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'revision_id' => ['type' => 'INT', 'unsigned' => true],
            'item_id'     => ['type' => 'INT', 'unsigned' => true],
            'esperada'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'encontrada'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'estado'      => ['type' => 'ENUM', 'constraint' => ['ok', 'falta', 'danado'], 'default' => 'ok'],
            'observacion' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('revision_id', 'inventario_revisiones', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'inventario_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventario_revision_lineas');

        // ── Datos propios de cada cabaña ──
        $this->forge->addColumn('unidades', [
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true, 'after' => 'nombre'],
            'ubicacion'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'descripcion'],
            'orden'       => ['type' => 'INT', 'default' => 0, 'after' => 'ubicacion'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('unidades', ['descripcion', 'ubicacion', 'orden']);
        $this->forge->dropTable('inventario_revision_lineas');
        $this->forge->dropTable('inventario_revisiones');
        $this->forge->dropTable('unidad_inventario');
        $this->forge->dropTable('inventario_items');
        $this->forge->dropTable('unidad_servicios');
        $this->forge->dropTable('tipo_servicios');
        $this->forge->dropTable('servicios');
        $this->forge->dropTable('medios');
    }
}
