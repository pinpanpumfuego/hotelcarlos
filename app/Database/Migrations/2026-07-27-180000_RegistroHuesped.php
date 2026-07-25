<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registro en línea del huésped (autocheck-in).
 *
 * Guarda los datos exigidos para la Tarjeta de Registro de Alojamiento,
 * la trazabilidad del consentimiento de tratamiento de datos (Ley 1581 de 2012)
 * y los avisos de protección de menores (Ley 679 de 2001 y Ley 1098 de 2006).
 */
class RegistroHuesped extends Migration
{
    public function up()
    {
        // ── Registro por reserva ──
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reserva_id'         => ['type' => 'INT', 'unsigned' => true],
            'token'              => ['type' => 'VARCHAR', 'constraint' => 64],
            'estado'             => ['type' => 'ENUM', 'constraint' => ['pendiente', 'enviado', 'aprobado', 'rechazado'], 'default' => 'pendiente'],
            'expira_en'          => ['type' => 'DATETIME', 'null' => true],

            // Datos del titular exigidos para la TRA
            'motivo_viaje'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'pais_residencia'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'ciudad_residencia'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'direccion'          => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'fecha_nacimiento'   => ['type' => 'DATE', 'null' => true],
            'ocupacion'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'placa_vehiculo'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'hora_llegada'       => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'observaciones'      => ['type' => 'TEXT', 'null' => true],

            // Consentimientos con su versión, para poder demostrar qué se aceptó
            'acepta_datos'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'acepta_reglamento'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'acepta_escnna'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'version_politica'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'acepta_marketing'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            // Firma electrónica simple y su trazabilidad
            'firma_archivo'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'firmado_en'         => ['type' => 'DATETIME', 'null' => true],
            'firma_ip'           => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'firma_dispositivo'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            // Revisión por parte del hotel
            'enviado_en'         => ['type' => 'DATETIME', 'null' => true],
            'revisado_por'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'revisado_en'        => ['type' => 'DATETIME', 'null' => true],
            'motivo_rechazo'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            // Marcas de cumplimiento
            'hay_menores'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'hay_extranjeros'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'reportado_sire'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'reportado_tra'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('reserva_id');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('registros');

        // ── Acompañantes ──
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'registro_id'      => ['type' => 'INT', 'unsigned' => true],
            'nombre'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'apellidos'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'tipo_documento'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'CC'],
            'num_documento'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'nacionalidad'     => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Colombia'],
            'fecha_nacimiento' => ['type' => 'DATE', 'null' => true],
            'es_menor'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'parentesco'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('registro_id');
        $this->forge->addForeignKey('registro_id', 'registros', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('registro_acompanantes');

        // ── Documentos aportados (fuera de la carpeta pública) ──
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'registro_id'    => ['type' => 'INT', 'unsigned' => true],
            'acompanante_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'tipo'           => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'documento'],
            'archivo'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'nombre_original' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mime'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tamano'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('registro_id');
        $this->forge->addForeignKey('registro_id', 'registros', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('acompanante_id', 'registro_acompanantes', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('registro_documentos');
    }

    public function down()
    {
        $this->forge->dropTable('registro_documentos');
        $this->forge->dropTable('registro_acompanantes');
        $this->forge->dropTable('registros');
    }
}
