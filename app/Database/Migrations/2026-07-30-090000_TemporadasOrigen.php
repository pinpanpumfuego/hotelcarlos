<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Marca de dónde salió cada temporada. La «clave» permite que el agente
 * reconozca lo que ya creó y no lo duplique al volver a pasar.
 */
class TemporadasOrigen extends Migration
{
    public function up()
    {
        $this->forge->addColumn('temporadas', [
            'origen' => ['type' => 'ENUM', 'constraint' => ['manual', 'agente'], 'default' => 'manual', 'after' => 'activa'],
            'clave'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'origen'],
        ]);
        $this->db->query('ALTER TABLE temporadas ADD UNIQUE KEY temporadas_clave (clave)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE temporadas DROP INDEX temporadas_clave');
        $this->forge->dropColumn('temporadas', ['origen', 'clave']);
    }
}
