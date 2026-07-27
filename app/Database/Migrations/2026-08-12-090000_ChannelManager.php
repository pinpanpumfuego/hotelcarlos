<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tablas del módulo de channel manager.
 *
 * Ninguna tiene clave foránea hacia `reservas` o `unidades` **a propósito**:
 * son tablas de frontera, y una reserva externa puede existir aquí antes —o
 * sin— tener equivalente local (una cabaña sin mapear, por ejemplo). Con una
 * foránea, ese caso sería imposible de registrar, que es justo cuando más
 * falta hace tener constancia de lo que llegó.
 *
 * Idempotente: MySQL no sabe deshacer un CREATE TABLE a medias, así que cada
 * paso comprueba antes si ya está hecho.
 */
class ChannelManager extends Migration
{
    public function up()
    {
        $this->propiedades();
        $this->mapeoUnidades();
        $this->reservas();
        $this->eventos();
        $this->registros();
    }

    /** Qué propiedad nuestra es qué propiedad del proveedor. */
    private function propiedades(): void
    {
        if ($this->db->tableExists('channel_properties')) {
            return;
        }

        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'channel'              => ['type' => 'VARCHAR', 'constraint' => 30],
            'local_property_id'    => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'external_property_id' => ['type' => 'VARCHAR', 'constraint' => 60],

            // El refresh token, cifrado con la clave de la aplicación. Se guarda
            // porque hay que poder rotarlo y revocarlo; no se enseña nunca.
            'credentials_encrypted' => ['type' => 'TEXT', 'null' => true],

            // Beds24 mata un refresh token que pase 30 días sin usarse, y cuando
            // eso ocurre la sincronización se apaga en silencio. Esta fecha es
            // lo que permite avisar antes de que pase.
            'credentials_used_at'  => ['type' => 'DATETIME', 'null' => true],

            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['channel', 'local_property_id']);
        // Y al revés: una propiedad del proveedor no puede estar mapeada a dos
        // propiedades nuestras. Un error de dedo aquí manda reservas a otro hotel.
        $this->forge->addUniqueKey(['channel', 'external_property_id']);
        $this->forge->createTable('channel_properties');
    }

    /**
     * Qué alojamiento del proveedor corresponde a qué cabañas.
     *
     * El mapeo es por **tipo de alojamiento**, no por cabaña. En Beds24 las
     * siete cabañas se publican como un solo alojamiento con `numAvail: 7`, y
     * qué cabaña concreta le toca a cada huésped lo decide este sistema, que es
     * el único que sabe de limpieza y de mantenimiento.
     */
    private function mapeoUnidades(): void
    {
        if ($this->db->tableExists('channel_unit_mappings')) {
            return;
        }

        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'channel'             => ['type' => 'VARCHAR', 'constraint' => 30],
            'local_property_id'   => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'local_room_type_id'  => ['type' => 'INT', 'unsigned' => true],

            // 0 = «todas las cabañas del tipo». Es un centinela, no un NULL, y
            // el motivo es concreto: en MySQL dos NULL **no** chocan en un
            // índice único, así que con NULL se podrían crear dos mapeos del
            // mismo tipo sin que la base de datos dijera nada. Con 0, choca.
            'local_unit_id'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],

            'external_property_id' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'external_unit_id'     => ['type' => 'VARCHAR', 'constraint' => 60],
            'external_room_type_id' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            // En Beds24 no hay id de plan tarifario: hay dieciséis huecos de
            // precio por alojamiento. Aquí va el número de hueco, '1'…'16'.
            'external_rate_plan_id' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '1'],

            // Cuántas cabañas representa este mapeo. Es el `numAvail` máximo.
            'allotment'           => ['type' => 'INT', 'unsigned' => true, 'default' => 1],

            'is_active'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['channel', 'local_room_type_id', 'local_unit_id']);
        $this->forge->addKey(['channel', 'external_unit_id']);
        $this->forge->createTable('channel_unit_mappings');
    }

    /** La equivalencia entre una reserva del proveedor y una nuestra. */
    private function reservas(): void
    {
        if ($this->db->tableExists('channel_reservations')) {
            return;
        }

        $this->forge->addField([
            'id'                      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'channel'                 => ['type' => 'VARCHAR', 'constraint' => 30],
            'external_reservation_id' => ['type' => 'VARCHAR', 'constraint' => 60],

            // Nullable a propósito: entre registrar lo que llegó y crear la
            // reserva local hay un instante, y si la cabaña no está mapeada
            // puede que nunca llegue a haber reserva local. Ese caso hay que
            // poder guardarlo, no perderlo.
            'local_reservation_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            'external_property_id'    => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'external_unit_id'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_channel'          => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'external_reference'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],

            'status'                  => ['type' => 'VARCHAR', 'constraint' => 20],
            'check_in'                => ['type' => 'DATE'],
            'check_out'               => ['type' => 'DATE'],
            'guest_name'              => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'guest_email'             => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'guest_phone'             => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'adults'                  => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'children'                => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_amount'            => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'currency'                => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'COP'],

            // Copia original, ya depurada de tarjetas y credenciales. Se purga
            // pasado el plazo de la configuración (Ley 1581/2012: no guardar
            // datos personales más de lo que hagan falta).
            'raw_payload'             => ['type' => 'JSON', 'null' => true],

            'cancelled_at'            => ['type' => 'DATETIME', 'null' => true],
            'cancelled_source'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'last_external_update_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // La pieza que hace idempotente todo el flujo entrante.
        $this->forge->addUniqueKey(['channel', 'external_reservation_id']);
        $this->forge->addKey('local_reservation_id');
        $this->forge->addKey(['check_in', 'check_out']);
        $this->forge->createTable('channel_reservations');
    }

    /** Todo lo que llega, antes de intentar entenderlo. */
    private function eventos(): void
    {
        if ($this->db->tableExists('channel_events')) {
            return;
        }

        $this->forge->addField([
            'id'                      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'channel'                 => ['type' => 'VARCHAR', 'constraint' => 30],
            'external_event_id'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'event_type'              => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'booking'],
            'external_reservation_id' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'payload'                 => ['type' => 'JSON', 'null' => true],

            // Aquí está la idempotencia de verdad. No se usa `external_event_id`
            // porque no está confirmado que Beds24 mande uno: el mismo aviso
            // repetido tiene el mismo hash, y el índice único lo rechaza.
            'payload_hash'            => ['type' => 'CHAR', 'constraint' => 64],

            'status'                  => ['type' => 'ENUM', 'constraint' => ['pending', 'processing', 'processed', 'failed', 'ignored'], 'default' => 'pending'],
            'attempts'                => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'error_message'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'source_ip'               => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'correlation_id'          => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],

            // Cuándo ocurrió, frente a cuándo lo guardamos: si un aviso se
            // recupera con retraso, no son la misma cosa.
            'received_at'             => ['type' => 'DATETIME', 'null' => true],
            'processed_at'            => ['type' => 'DATETIME', 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['channel', 'payload_hash']);
        // Para que buscar lo pendiente no recorra la tabla entera.
        $this->forge->addKey(['status', 'attempts']);
        $this->forge->addKey('external_reservation_id');
        $this->forge->createTable('channel_events');
    }

    /**
     * Bitácora y cola de salida a la vez.
     *
     * Una fila `direction=outbound` con `status=pending` es un envío que falta
     * por hacer. No hace falta una tabla de cola aparte: sería la misma
     * información contada dos veces, y con el riesgo de que se separen.
     */
    private function registros(): void
    {
        if ($this->db->tableExists('channel_sync_logs')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'channel'          => ['type' => 'VARCHAR', 'constraint' => 30],
            'operation'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'direction'        => ['type' => 'ENUM', 'constraint' => ['inbound', 'outbound'], 'default' => 'outbound'],
            'reference_type'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'reference_id'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            // Ya depurados: cualquier campo con pinta de token o de tarjeta se
            // sustituye por *** antes de llegar aquí.
            'request_payload'  => ['type' => 'JSON', 'null' => true],
            'response_payload' => ['type' => 'JSON', 'null' => true],

            'http_status'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['pending', 'processing', 'success', 'failed', 'skipped'], 'default' => 'pending'],
            'attempts'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'error_message'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'correlation_id'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'next_attempt_at'  => ['type' => 'DATETIME', 'null' => true],
            'started_at'       => ['type' => 'DATETIME', 'null' => true],
            'finished_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'direction', 'next_attempt_at']);
        $this->forge->addKey('correlation_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('channel_sync_logs');
    }

    public function down()
    {
        foreach (['channel_sync_logs', 'channel_events', 'channel_reservations', 'channel_unit_mappings', 'channel_properties'] as $tabla) {
            if ($this->db->tableExists($tabla)) {
                $this->forge->dropTable($tabla);
            }
        }
    }
}
