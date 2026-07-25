<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNucleoHotel extends Migration
{
    public function up()
    {
        // Tipos de alojamiento: habitación, cabaña, glamping, casa, parcela...
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'descripcion' => ['type' => 'TEXT', 'null' => true],
            'capacidad'   => ['type' => 'INT', 'unsigned' => true, 'default' => 2],
            'tarifa_base' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tipos_unidad');

        // Unidades físicas de alojamiento
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tipo_id'    => ['type' => 'INT', 'unsigned' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'estado'     => ['type' => 'ENUM', 'constraint' => ['disponible', 'ocupada', 'limpieza', 'bloqueada'], 'default' => 'disponible'],
            'notas'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('tipo_id', 'tipos_unidad', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('unidades');

        // Huéspedes
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'apellidos'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'tipo_documento' => ['type' => 'ENUM', 'constraint' => ['CC', 'CE', 'PASAPORTE', 'TI', 'OTRO'], 'default' => 'CC'],
            'num_documento'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'nacionalidad'   => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Colombia'],
            'telefono'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'notas'          => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tipo_documento', 'num_documento']);
        $this->forge->createTable('huespedes');

        // Reservas
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'codigo'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'huesped_id'    => ['type' => 'INT', 'unsigned' => true],
            'unidad_id'     => ['type' => 'INT', 'unsigned' => true],
            'fecha_entrada' => ['type' => 'DATE'],
            'fecha_salida'  => ['type' => 'DATE'],
            'adultos'       => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'ninos'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'estado'        => ['type' => 'ENUM', 'constraint' => ['pendiente', 'confirmada', 'checkin', 'checkout', 'cancelada'], 'default' => 'pendiente'],
            'total'         => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'notas'         => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->addForeignKey('huesped_id', 'huespedes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('reservas');
    }

    public function down()
    {
        $this->forge->dropTable('reservas');
        $this->forge->dropTable('huespedes');
        $this->forge->dropTable('unidades');
        $this->forge->dropTable('tipos_unidad');
    }
}
