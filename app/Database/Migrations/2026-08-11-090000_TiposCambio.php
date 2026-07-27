<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tipos de cambio para enseñar los precios en la moneda del visitante.
 *
 * Se guarda **cuántos pesos vale una unidad** de la otra moneda (unos 4.000
 * por dólar), y no al revés, porque es la cifra que una persona reconoce de
 * un vistazo. Si algún día hay que corregirla a mano, se ve enseguida si el
 * número tiene sentido.
 *
 * El cobro siempre es en pesos: esto es solo orientativo para el visitante.
 */
class TiposCambio extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tipos_cambio')) {
            return;
        }

        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'moneda'  => ['type' => 'VARCHAR', 'constraint' => 3],
            // Pesos colombianos por 1 unidad de esa moneda
            'pesos'   => ['type' => 'DECIMAL', 'constraint' => '14,4'],
            // 'auto' si lo trajo la tarea diaria, 'manual' si lo puso gerencia
            'origen'  => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'auto'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('moneda');
        $this->forge->createTable('tipos_cambio');
    }

    public function down()
    {
        $this->forge->dropTable('tipos_cambio', true);
    }
}
