<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cada cabaña puede tener su propia galería publicable, además de las fotos
 * internas. Lo que decide dónde se guarda el archivo ya no es a quién
 * pertenece, sino si se publica o no.
 */
class MediosPublicos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('medios', [
            'publico' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'unidad_id'],
        ]);

        // Lo que ya existía de cabañas era interno; lo de los tipos, público
        $this->db->query('UPDATE medios SET publico = 0 WHERE unidad_id IS NOT NULL');
        $this->db->query('UPDATE medios SET publico = 1 WHERE tipo_unidad_id IS NOT NULL');
    }

    public function down()
    {
        $this->forge->dropColumn('medios', 'publico');
    }
}
