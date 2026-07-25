<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Módulo de personal: empleados, turnos y ausencias.
 *
 * Incluye los datos de seguridad social exigidos en Colombia (EPS, ARL,
 * fondo de pensiones y caja de compensación). No cubre nómina: la
 * liquidación se lleva en el software contable del hotel.
 */
class Personal extends Migration
{
    public function up()
    {
        // ── Empleados ──
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'usuario_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            // Datos personales
            'nombre'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'apellidos'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'tipo_documento'    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'CC'],
            'num_documento'     => ['type' => 'VARCHAR', 'constraint' => 50],
            'fecha_nacimiento'  => ['type' => 'DATE', 'null' => true],
            'telefono'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'direccion'         => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'ciudad'            => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],

            // Datos laborales
            'cargo'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'area'              => ['type' => 'ENUM', 'constraint' => ['recepcion', 'limpieza', 'cocina', 'mantenimiento', 'administracion', 'otro'], 'default' => 'otro'],
            'tipo_contrato'     => ['type' => 'ENUM', 'constraint' => ['indefinido', 'fijo', 'obra', 'prestacion', 'aprendiz'], 'default' => 'indefinido'],
            'fecha_ingreso'     => ['type' => 'DATE', 'null' => true],
            'fecha_salida'      => ['type' => 'DATE', 'null' => true],
            'salario'           => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'jornada'           => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            // Seguridad social (Colombia)
            'eps'               => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'arl'               => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'fondo_pension'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'caja_compensacion' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'banco'             => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'cuenta_bancaria'   => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            // Contacto de emergencia
            'emergencia_nombre'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'emergencia_telefono'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'emergencia_parentesco' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            'notas'             => ['type' => 'TEXT', 'null' => true],
            'activo'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tipo_documento', 'num_documento']);
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('empleados');

        // ── Turnos ──
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'empleado_id' => ['type' => 'INT', 'unsigned' => true],
            'fecha'       => ['type' => 'DATE'],
            'hora_inicio' => ['type' => 'TIME'],
            'hora_fin'    => ['type' => 'TIME'],
            'puesto'      => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'notas'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['fecha', 'empleado_id']);
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('turnos');

        // ── Ausencias ──
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'empleado_id'  => ['type' => 'INT', 'unsigned' => true],
            'tipo'         => ['type' => 'ENUM', 'constraint' => ['vacaciones', 'incapacidad', 'permiso', 'licencia', 'falta'], 'default' => 'permiso'],
            'desde'        => ['type' => 'DATE'],
            'hasta'        => ['type' => 'DATE'],
            'motivo'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'estado'       => ['type' => 'ENUM', 'constraint' => ['solicitada', 'aprobada', 'rechazada'], 'default' => 'solicitada'],
            'aprobada_por' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'aprobada_en'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('empleado_id');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ausencias');

        // ── Documentos del empleado (fuera de la carpeta pública) ──
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'empleado_id'     => ['type' => 'INT', 'unsigned' => true],
            'tipo'            => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'otro'],
            'archivo'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'nombre_original' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mime'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tamano'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('empleado_id');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('empleado_documentos');
    }

    public function down()
    {
        $this->forge->dropTable('empleado_documentos');
        $this->forge->dropTable('ausencias');
        $this->forge->dropTable('turnos');
        $this->forge->dropTable('empleados');
    }
}
