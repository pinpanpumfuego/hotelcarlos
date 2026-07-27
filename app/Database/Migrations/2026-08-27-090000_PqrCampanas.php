<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * PQR y campañas.
 *
 * **Las PQR llevan plazo legal, y por eso van aparte de `solicitudes`.** Una
 * petición de toallas y una queja formal no son lo mismo aunque las dos las
 * escriba el huésped: la segunda la ampara la Ley 1755/2015, tiene quince días
 * hábiles para contestarse y hay que poder demostrar que se contestó. Meterlas
 * en la misma tabla haría que la queja se perdiera entre las toallas.
 *
 * Las campañas guardan **los filtros, no la lista**. Guardar la lista congelada
 * significaría mandarle la campaña a quien se dio de baja entre que se preparó
 * y se envió; con los filtros, la lista se vuelve a calcular al salir y pasa
 * otra vez por la puerta del consentimiento.
 */
class PqrCampanas extends Migration
{
    public function up()
    {
        $this->pqr();
        $this->notas();
        $this->campanas();
        $this->permisos();
    }

    private function pqr(): void
    {
        if ($this->db->tableExists('pqr')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],

            // Un número que el huésped pueda citar por teléfono. Sin él, «lo de
            // la queja de la semana pasada» no se encuentra.
            'codigo' => ['type' => 'VARCHAR', 'constraint' => 20],

            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['peticion', 'queja', 'reclamo', 'sugerencia', 'felicitacion'],
                'default'    => 'queja',
            ],

            'huesped_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'reserva_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            // Por dónde entró. Una queja que llega por Booking hay que
            // contestarla también allí, y eso se olvida si no queda apuntado.
            'canal_entrada' => [
                'type'       => 'ENUM',
                'constraint' => ['presencial', 'telefono', 'email', 'portal', 'web', 'ota', 'redes', 'otro'],
                'default'    => 'presencial',
            ],

            // Quien la pone puede no ser huésped: un vecino, un proveedor.
            'nombre_contacto'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'email_contacto'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'telefono_contacto' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],

            'asunto'  => ['type' => 'VARCHAR', 'constraint' => 200],
            'detalle' => ['type' => 'TEXT'],

            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['recibida', 'en_gestion', 'respondida', 'cerrada', 'reabierta'],
                'default'    => 'recibida',
            ],
            'prioridad' => [
                'type'       => 'ENUM',
                'constraint' => ['baja', 'media', 'alta', 'urgente'],
                'default'    => 'media',
            ],

            'responsable_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            // El plazo legal, en días HÁBILES, contando los festivos
            // colombianos. Un plazo en días naturales daría una fecha que no es
            // la que exige la ley, y eso es peor que no calcularlo.
            'vence_en'    => ['type' => 'DATE', 'null' => true],
            'respuesta'   => ['type' => 'TEXT', 'null' => true],
            'respondida_en' => ['type' => 'DATETIME', 'null' => true],
            'respondio_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'cerrada_en'    => ['type' => 'DATETIME', 'null' => true],

            // Qué le pareció la respuesta. Es lo único que dice si el problema
            // se resolvió o solo se contestó.
            'satisfaccion' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => true],

            // Qué causó el problema, para poder contar cuántas veces se repite
            'causa' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->addKey(['estado', 'vence_en']);
        $this->forge->addKey('huesped_id');
        $this->forge->createTable('pqr');
    }

    /**
     * El rastro. Se escribe, no se corrige.
     *
     * Sin esto, «se habló con el huésped» es la palabra de alguien. Con esto se
     * puede reconstruir qué se hizo, cuándo y quién, que es lo que hace falta
     * cuando la queja se convierte en una reclamación de verdad.
     */
    private function notas(): void
    {
        if ($this->db->tableExists('pqr_notas')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'pqr_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'tipo'   => [
                'type'       => 'ENUM',
                'constraint' => ['nota', 'estado', 'asignacion', 'contacto', 'respuesta'],
                'default'    => 'nota',
            ],
            'texto'      => ['type' => 'VARCHAR', 'constraint' => 500],
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('pqr_id');
        $this->forge->addForeignKey('pqr_id', 'pqr', 'id', '', 'CASCADE');
        $this->forge->createTable('pqr_notas');
    }

    private function campanas(): void
    {
        if ($this->db->tableExists('campanas')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 120],

            'plantilla_clave' => ['type' => 'VARCHAR', 'constraint' => 40],
            'canal'           => ['type' => 'ENUM', 'constraint' => ['email', 'whatsapp'], 'default' => 'email'],

            // Los filtros, no la lista. Guardar la lista congelada significaría
            // mandársela a quien se dio de baja entre que se preparó y se envió.
            'filtros' => ['type' => 'TEXT', 'null' => true],

            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['borrador', 'enviada', 'cancelada'],
                'default'    => 'borrador',
            ],

            'encolados'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'saltados'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'enviada_en' => ['type' => 'DATETIME', 'null' => true],
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('campanas');
    }

    private function permisos(): void
    {
        $nuevos = ['pqr.ver', 'pqr.gestionar', 'pqr.responder', 'campanas.gestionar'];

        foreach ($nuevos as $clave) {
            $datos = \App\Libraries\Permisos\Catalogo::PERMISOS[$clave] ?? null;

            if ($datos === null) {
                continue;
            }

            if ($this->db->table('permisos')->where('clave', $clave)->countAllResults() === 0) {
                $this->db->table('permisos')->insert([
                    'clave'       => $clave,
                    'modulo'      => $datos['modulo'],
                    'nombre'      => $datos['nombre'],
                    'es_sensible' => $datos['sensible'] ? 1 : 0,
                ]);
            }
        }

        $mapa = [];

        foreach ($this->db->table('permisos')->select('id, clave')->get()->getResultArray() as $p) {
            $mapa[$p['clave']] = (int) $p['id'];
        }

        foreach (['recepcion', 'restaurante', 'housekeeping', 'mantenimiento'] as $clave) {
            $rol = $this->db->table('roles')->where('clave', $clave)->get()->getRowArray();

            if ($rol === null) {
                continue;
            }

            foreach (\App\Libraries\Permisos\Catalogo::permisosDe($clave) as $permiso) {
                if (! in_array($permiso, $nuevos, true) || ! isset($mapa[$permiso])) {
                    continue;
                }

                $ya = $this->db->table('rol_permisos')
                    ->where('rol_id', $rol['id'])
                    ->where('permiso_id', $mapa[$permiso])
                    ->countAllResults();

                if ($ya === 0) {
                    $this->db->table('rol_permisos')->insert([
                        'rol_id'     => (int) $rol['id'],
                        'permiso_id' => $mapa[$permiso],
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('campanas', true);
        $this->forge->dropTable('pqr_notas', true);
        $this->forge->dropTable('pqr', true);
    }
}
