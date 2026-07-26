<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Experiencias y actividades: cabalgata, paseo en lancha, avistamiento de aves…
 *
 * Es venta de mayor margen que la habitación, así que interesa que se pueda
 * ofrecer en tres sitios: la web, el motor de reservas y el mostrador.
 */
class Experiencias extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'descripcion' => ['type' => 'TEXT', 'null' => true],
            'incluye'     => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'no_incluye'  => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'categoria'   => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Naturaleza'],

            // Precio: por persona (cabalgata) o cerrado por grupo (lancha entera)
            'tipo_precio' => ['type' => 'ENUM', 'constraint' => ['persona', 'grupo'], 'default' => 'persona'],
            'precio'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'precio_nino' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'coste'       => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            'duracion_min'   => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'capacidad'      => ['type' => 'INT', 'unsigned' => true, 'default' => 8],
            'minimo'         => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'edad_minima'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            // «08:00,15:00» y «1,2,3,4,5,6,7» (1 = lunes). Se edita a mano
            // porque así es como el hotel piensa sus salidas.
            'horarios'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'dias'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '1,2,3,4,5,6,7'],
            'aviso_horas'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],

            'punto_encuentro' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'proveedor'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'notas_internas'  => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],

            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'publicada'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'orden'      => ['type' => 'INT', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('experiencias');

        // ── Salidas contratadas ──
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'experiencia_id' => ['type' => 'INT', 'unsigned' => true],
            'reserva_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'huesped_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            // Para quien no está alojado: un vecino, un visitante del día
            'cliente_nombre'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'cliente_telefono' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],

            'fecha'   => ['type' => 'DATE'],
            'hora'    => ['type' => 'TIME', 'null' => true],
            'adultos' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'ninos'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],

            'precio_unitario' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'precio_nino'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'total'           => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            'estado'      => ['type' => 'ENUM', 'constraint' => ['solicitada', 'confirmada', 'realizada', 'cancelada', 'no_show'], 'default' => 'solicitada'],
            'empleado_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'folio_movimiento_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cobrado_aparte' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'notas'      => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'motivo'     => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['fecha', 'hora']);
        $this->forge->addForeignKey('experiencia_id', 'experiencias', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('huesped_id', 'huespedes', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('experiencia_reservas');

        // La galería ya existe: se reutiliza para las fotos de cada experiencia
        $this->forge->addColumn('medios', [
            'experiencia_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'unidad_id'],
        ]);
        $this->db->query('ALTER TABLE medios ADD CONSTRAINT medios_experiencia_id_foreign
                          FOREIGN KEY (experiencia_id) REFERENCES experiencias(id) ON DELETE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE medios DROP FOREIGN KEY medios_experiencia_id_foreign');
        $this->forge->dropColumn('medios', 'experiencia_id');
        $this->forge->dropTable('experiencia_reservas');
        $this->forge->dropTable('experiencias');
    }
}
