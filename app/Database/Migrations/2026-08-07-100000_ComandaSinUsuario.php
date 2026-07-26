<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Una comanda puede no tener usuario del sistema.
 *
 * Hasta ahora toda comanda nacía en una pantalla con sesión abierta, así que
 * `usuario_id` era obligatorio. Con el comandero deja de ser cierto: el
 * camarero toma nota desde su teléfono identificado con su PIN de fichaje,
 * y no tiene usuario del panel. Quien responde de esa comanda es el
 * `empleado_id`, que ya existía y sí queda apuntado.
 */
class ComandaSinUsuario extends Migration
{
    public function up()
    {
        $campo = $this->db->getFieldData('comandas');
        foreach ($campo as $c) {
            if ($c->name === 'usuario_id' && $c->nullable) {
                return;   // ya estaba hecho
            }
        }

        $this->forge->modifyColumn('comandas', [
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
    }

    public function down()
    {
        // No se deshace: volver a NOT NULL rompería las comandas ya tomadas
        // desde el móvil, que legítimamente no tienen usuario.
    }
}
