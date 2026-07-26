<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lo que un camarero lleva apuntado en el móvil y todavía no ha enviado.
 *
 * No son líneas de comanda y no deben serlo: si lo fueran, saldrían en cocina
 * y sumarían al total antes de que el camarero termine de tomar nota. Esto es
 * solo una foto de lo que hay en la pantalla del teléfono, para que quien
 * esté en el TPV vea que esa mesa se está atendiendo y qué llevan pedido.
 *
 * Es informativo y se borra en cuanto la ronda se envía de verdad.
 */
class ComanderoBorradores extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('comandero_borradores')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'empleado_id' => ['type' => 'INT', 'unsigned' => true],
            // La misma clave de destino que usa el teléfono: mesa-3, llevar-…, cabana-12
            'clave'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'mesa_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'comanda_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'destino'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            // Foto de las líneas: nombre, cantidad y precio. No se factura desde aquí.
            'resumen'     => ['type' => 'TEXT', 'null' => true],
            'unidades'    => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'importe'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['empleado_id', 'clave']);
        $this->forge->addKey('mesa_id');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', '', 'CASCADE');
        $this->forge->createTable('comandero_borradores');
    }

    public function down()
    {
        $this->forge->dropTable('comandero_borradores', true);
    }
}
