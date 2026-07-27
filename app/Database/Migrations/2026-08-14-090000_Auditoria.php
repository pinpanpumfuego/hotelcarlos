<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Quién hizo cada cosa de las que importan.
 *
 * Solo se registran las acciones marcadas como **sensibles**: las que mueven
 * dinero, tocan datos personales o cambian la configuración. Registrarlo todo
 * daría una tabla enorme donde lo importante no se encuentra, que es la forma
 * más eficaz de no tener auditoría teniéndola.
 *
 * El nombre del usuario se guarda **copiado**, no solo su id. Una auditoría que
 * dice «el usuario 7 canceló la reserva» deja de servir el día que el usuario 7
 * se borra, que es justo el día en que más falta hace.
 */
class Auditoria extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('auditoria')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],

            'usuario_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'usuario_nombre' => ['type' => 'VARCHAR', 'constraint' => 150],
            'perfil'         => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],

            // Qué permiso se ejerció y qué se pidió
            'permiso'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'metodo'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'ruta'           => ['type' => 'VARCHAR', 'constraint' => 200],

            // Sobre qué: «reserva 34», «usuario 8»
            'referencia'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            // Si la acción salió adelante. Un intento fallido también interesa:
            // dice que alguien buscó una puerta que no le corresponde.
            'resultado'      => ['type' => 'ENUM', 'constraint' => ['ok', 'error', 'denegado'], 'default' => 'ok'],
            'http'           => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],

            'ip'             => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);

        // Sin foránea a `usuarios` a propósito: si se borra el usuario, su
        // rastro tiene que quedarse. Para eso está el nombre copiado.
        $this->forge->addKey('usuario_id');
        $this->forge->addKey('permiso');
        $this->forge->addKey('created_at');
        $this->forge->addKey(['referencia', 'created_at']);

        $this->forge->createTable('auditoria');
    }

    public function down()
    {
        if ($this->db->tableExists('auditoria')) {
            $this->forge->dropTable('auditoria');
        }
    }
}
