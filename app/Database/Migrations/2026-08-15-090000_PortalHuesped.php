<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Portal del huésped: acceso, solicitudes y encuestas.
 *
 * El registro en línea que ya existe sirve **una vez, antes de llegar**. Esto es
 * distinto: acompaña toda la estancia. Por eso el acceso va en su propia tabla
 * y no reutiliza el token del registro — son dos cosas con vidas distintas, y
 * mezclarlas obligaría a que caducaran a la vez.
 *
 * **No hay contraseña a propósito.** Nadie se crea una cuenta para pedir
 * toallas. El enlace es la credencial: largo, aleatorio y con fecha de muerte.
 */
class PortalHuesped extends Migration
{
    public function up()
    {
        $this->acceso();
        $this->solicitudes();
        $this->encuestas();
    }

    /** El enlace que acompaña al huésped desde la confirmación hasta la salida. */
    private function acceso(): void
    {
        if ($this->db->tableExists('portal_accesos')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true],

            // 48 caracteres hexadecimales: imposible de adivinar probando.
            'token'      => ['type' => 'VARCHAR', 'constraint' => 64],

            // El idioma en que reservó. Si alguien reserva en alemán y el portal
            // le sale en español, el trabajo de traducir la web se pierde justo
            // en el momento en que más importa.
            'idioma'     => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'es'],

            'expira_en'  => ['type' => 'DATETIME'],
            'revocado'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            // Rastro de uso: sirve para saber si el huésped llegó a abrirlo y
            // para detectar un enlace que circula donde no debe.
            'accesos'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'ultimo_acceso' => ['type' => 'DATETIME', 'null' => true],
            'ultima_ip'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('reserva_id');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('portal_accesos');
    }

    /** Lo que el huésped pide durante la estancia. */
    private function solicitudes(): void
    {
        if ($this->db->tableExists('solicitudes')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true],
            'unidad_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            'tipo'       => [
                'type'       => 'ENUM',
                'constraint' => ['limpieza', 'toallas', 'amenidades', 'mantenimiento', 'cuna', 'decoracion', 'salida_tarde', 'transporte', 'otro'],
                'default'    => 'otro',
            ],
            'detalle'    => ['type' => 'TEXT', 'null' => true],

            // Quién lo pide y cuándo lo quiere. `para_cuando` es texto libre
            // porque «después de comer» es una respuesta perfectamente útil que
            // ninguna lista desplegable recoge bien.
            'para_cuando' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],

            'estado'     => ['type' => 'ENUM', 'constraint' => ['pendiente', 'en_curso', 'resuelta', 'rechazada'], 'default' => 'pendiente'],
            'prioridad'  => ['type' => 'ENUM', 'constraint' => ['normal', 'urgente'], 'default' => 'normal'],

            'respuesta'    => ['type' => 'VARCHAR', 'constraint' => 400, 'null' => true],
            'atendida_por' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'atendida_en'  => ['type' => 'DATETIME', 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['estado', 'created_at']);
        $this->forge->addKey('reserva_id');
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('solicitudes');
    }

    /** Cómo fue. Durante la estancia y al terminar. */
    private function encuestas(): void
    {
        if ($this->db->tableExists('encuestas')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true],

            // Preguntar a mitad de estancia permite arreglar las cosas mientras
            // el huésped sigue aquí, que es cuando aún se puede.
            'momento'    => ['type' => 'ENUM', 'constraint' => ['estancia', 'salida'], 'default' => 'salida'],

            'general'    => ['type' => 'TINYINT', 'unsigned' => true],
            'limpieza'   => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'atencion'   => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'comida'     => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'entorno'    => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],

            'comentario' => ['type' => 'TEXT', 'null' => true],

            // Autorización explícita para usar el comentario. Sin ella, no se
            // publica ni se cita, por muy bueno que sea.
            'publicable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'leida_en'   => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Una encuesta por reserva y momento: si contesta dos veces, se
        // actualiza la suya en lugar de contarla dos veces en la media.
        $this->forge->addUniqueKey(['reserva_id', 'momento']);
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('encuestas');
    }

    public function down()
    {
        foreach (['encuestas', 'solicitudes', 'portal_accesos'] as $tabla) {
            if ($this->db->tableExists($tabla)) {
                $this->forge->dropTable($tabla);
            }
        }
    }
}
