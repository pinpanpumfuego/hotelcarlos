<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Motor de tarifas dinámicas: temporadas y reglas que se apilan
 * sobre la tarifa base de cada tipo de alojamiento.
 */
class Tarifas extends Migration
{
    public function up()
    {
        // ── Temporadas: rangos de fechas con su ajuste ──
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'desde'      => ['type' => 'DATE'],
            'hasta'      => ['type' => 'DATE'],
            'tipo_ajuste' => ['type' => 'ENUM', 'constraint' => ['porcentaje', 'valor', 'fijo'], 'default' => 'porcentaje'],
            'ajuste'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'prioridad'  => ['type' => 'INT', 'default' => 0],
            'color'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '#b9873f'],
            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['desde', 'hasta']);
        $this->forge->createTable('temporadas');

        // ── Reglas de precio ──
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'tipo'        => ['type' => 'ENUM', 'constraint' => ['dia_semana', 'ocupacion', 'anticipacion', 'duracion'], 'default' => 'dia_semana'],

            // Condición: su significado depende del tipo
            //  dia_semana   → dias = "5,6" (viernes y sábado, 1=lunes)
            //  ocupacion    → desde/hasta en porcentaje
            //  anticipacion → desde/hasta en días hasta la llegada
            //  duracion     → desde/hasta en noches
            'dias'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'valor_desde' => ['type' => 'INT', 'null' => true],
            'valor_hasta' => ['type' => 'INT', 'null' => true],

            'tipo_ajuste' => ['type' => 'ENUM', 'constraint' => ['porcentaje', 'valor'], 'default' => 'porcentaje'],
            'ajuste'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'tipo_unidad_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'prioridad'   => ['type' => 'INT', 'default' => 0],
            'activa'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('tipo_unidad_id', 'tipos_unidad', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('reglas_precio');

        // ── Tarifa por temporada y tipo (precio cerrado, si se prefiere a un %) ──
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tipo_unidad_id' => ['type' => 'INT', 'unsigned' => true],
            'temporada_id'   => ['type' => 'INT', 'unsigned' => true],
            'precio'         => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tipo_unidad_id', 'temporada_id']);
        $this->forge->addForeignKey('tipo_unidad_id', 'tipos_unidad', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('temporada_id', 'temporadas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tarifas_temporada');

        // Topes y suplementos por tipo de alojamiento
        $this->forge->addColumn('tipos_unidad', [
            'precio_minimo'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'tarifa_base'],
            'precio_maximo'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'precio_minimo'],
            'personas_incluidas' => ['type' => 'INT', 'unsigned' => true, 'default' => 2, 'after' => 'precio_maximo'],
            'suplemento_adulto' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'personas_incluidas'],
            'suplemento_nino'  => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'suplemento_adulto'],
        ]);

        // Desglose del precio guardado en la reserva, para poder explicarlo después
        $this->forge->addColumn('reservas', [
            'desglose_precio' => ['type' => 'TEXT', 'null' => true, 'after' => 'total'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('reservas', 'desglose_precio');
        $this->forge->dropColumn('tipos_unidad', [
            'precio_minimo', 'precio_maximo', 'personas_incluidas',
            'suplemento_adulto', 'suplemento_nino',
        ]);
        $this->forge->dropTable('tarifas_temporada');
        $this->forge->dropTable('reglas_precio');
        $this->forge->dropTable('temporadas');
    }
}
