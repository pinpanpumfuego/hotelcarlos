<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Traducciones del contenido que escribe el hotel.
 *
 * Una sola tabla para todo en vez de columnas `nombre_en`, `nombre_fr`,
 * `nombre_de`… repartidas por siete tablas. Con cuatro idiomas eso serían
 * decenas de columnas, y añadir un quinto idioma obligaría a tocar todas las
 * tablas. Así, añadir un idioma no toca el esquema: son filas nuevas.
 *
 * El español no vive aquí: es el original, y está donde siempre. Si falta una
 * traducción se enseña el español, nunca un hueco.
 */
class Traducciones extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('traducciones')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // Qué se traduce: tabla, fila y campo del original
            'tabla'       => ['type' => 'VARCHAR', 'constraint' => 40],
            'registro_id' => ['type' => 'INT', 'unsigned' => true],
            'campo'       => ['type' => 'VARCHAR', 'constraint' => 40],
            'idioma'      => ['type' => 'VARCHAR', 'constraint' => 5],
            'texto'       => ['type' => 'TEXT', 'null' => true],
            'usuario_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Una sola traducción por campo e idioma
        $this->forge->addUniqueKey(['tabla', 'registro_id', 'campo', 'idioma']);
        // La consulta de la web siempre es «todo lo de esta tabla en este idioma»
        $this->forge->addKey(['tabla', 'idioma']);
        $this->forge->createTable('traducciones');
    }

    public function down()
    {
        $this->forge->dropTable('traducciones', true);
    }
}
