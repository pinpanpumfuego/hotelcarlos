<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sincronización por iCal con Booking, Airbnb y demás.
 *
 * Es lo único que esas plataformas abren a cualquier alojamiento: un
 * calendario de fechas ocupadas. No trae precios ni datos del huésped,
 * pero evita vender dos veces la misma noche.
 */
class Canales extends Migration
{
    public function up()
    {
        // ── De dónde vino cada reserva ──
        $this->forge->addColumn('reservas', [
            'canal'       => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'directa', 'after' => 'estado'],
            'comision'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'canal'],
            'referencia_externa' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'comision'],
        ]);
        $this->db->query("UPDATE reservas SET canal = 'directa'");

        // ── Conexiones: una por cabaña y plataforma ──
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id' => ['type' => 'INT', 'unsigned' => true],
            'canal'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'booking'],
            'nombre'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],

            // Dirección que dan ellos y que nosotros leemos
            'url_importar' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],

            'activa'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            // Cómo fue la última lectura
            'ultima_sync'   => ['type' => 'DATETIME', 'null' => true],
            'ultimo_error'  => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'eventos'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['unidad_id', 'canal']);
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('canal_conexiones');

        // ── Bloqueos traídos de fuera ──
        // No son reservas nuestras: no tienen huésped ni folio. Solo ocupan.
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id'  => ['type' => 'INT', 'unsigned' => true],
            'conexion_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'canal'      => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'booking'],

            'uid'        => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'resumen'    => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'fecha_entrada' => ['type' => 'DATE'],
            'fecha_salida'  => ['type' => 'DATE'],

            'origen'     => ['type' => 'ENUM', 'constraint' => ['ical', 'manual'], 'default' => 'ical'],
            'notas'      => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['unidad_id', 'fecha_entrada', 'fecha_salida']);
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('conexion_id', 'canal_conexiones', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('bloqueos');

        // Cada cabaña tiene una dirección secreta que se le pasa a las plataformas
        $this->forge->addColumn('unidades', [
            'token_ical' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'orden'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('unidades', 'token_ical');
        $this->forge->dropTable('bloqueos');
        $this->forge->dropTable('canal_conexiones');
        $this->forge->dropColumn('reservas', ['canal', 'comision', 'referencia_externa']);
    }
}
