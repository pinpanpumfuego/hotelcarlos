<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Una tarea pendiente todavía no tiene quién la haga ni cuándo empezó.
 *
 * `limpiezas` nació cuando una fila solo existía si alguien ya estaba
 * limpiando: por eso `usuario_id` e `inicio` eran obligatorios. Ahora la tarea
 * nace **antes**, al hacer el check-out, y se queda esperando a que alguien la
 * coja. En ese momento no hay ni persona ni hora de inicio.
 *
 * Es el tercer sitio de este proyecto donde una columna obligatoria impedía
 * guardar algo que existe de verdad —pasó con `comandas.usuario_id` y con
 * `reservas.unidad_id`—, y las tres veces el síntoma fue el mismo: el
 * `insert()` fallaba y el trabajo se perdía.
 */
class TareaSinAsignar extends Migration
{
    public function up()
    {
        $campos = [];
        foreach ($this->db->getFieldData('limpiezas') as $c) {
            $campos[$c->name] = $c;
        }

        if (isset($campos['usuario_id']) && $campos['usuario_id']->nullable === false) {
            $this->db->query('ALTER TABLE `limpiezas` MODIFY `usuario_id` INT UNSIGNED NULL');
        }

        if (isset($campos['inicio']) && $campos['inicio']->nullable === false) {
            $this->db->query('ALTER TABLE `limpiezas` MODIFY `inicio` DATETIME NULL');
        }
    }

    public function down()
    {
        // No se devuelven a NOT NULL: si ya hay tareas pendientes sin asignar,
        // hacerlo fallaría o —peor— obligaría a inventarles un responsable.
    }
}
