<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lo que la tarifa ya incluye, y lo que se regala o se devuelve.
 *
 * Tres cosas que se echan de menos **todos los días**, no de vez en cuando:
 *
 * 1. **Planes incluidos.** Si la tarifa lleva desayuno y el sistema no lo sabe,
 *    o se cobra dos veces o alguien lo descuenta a mano cada mañana. Con siete
 *    cabañas llenas eso son siete errores diarios.
 * 2. **Cortesías.** Anular una línea no es lo mismo que invitarla: al anularla
 *    el consumo desaparece, y entonces el escandallo miente sobre lo que ha
 *    salido de la cocina.
 * 3. **Franjas horarias.** Una carta única obliga a marcar y desmarcar platos
 *    dos veces al día a mano.
 */
class PlanesYCortesias extends Migration
{
    public function up()
    {
        $this->planes();
        $this->consumos();
        $this->cortesias();
        $this->franjas();
    }

    /** Qué incluye cada plan. */
    private function planes(): void
    {
        if (! $this->db->tableExists('planes')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'nombre'      => ['type' => 'VARCHAR', 'constraint' => 80],
                'descripcion' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'activo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('planes');
        }

        if (! $this->db->tableExists('plan_lineas')) {
            $this->forge->addField([
                'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'plan_id' => ['type' => 'INT', 'unsigned' => true],

                // Se puede incluir un producto concreto («café con leche») o una
                // categoría entera («cualquier desayuno»). Lo segundo es lo
                // normal: al huésped se le deja elegir dentro de lo incluido.
                'categoria_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'producto_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],

                'cantidad' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],

                // Por persona y día es lo habitual en un desayuno; por estancia,
                // para una botella de bienvenida.
                'por'      => ['type' => 'ENUM', 'constraint' => ['persona_dia', 'persona_estancia', 'estancia'], 'default' => 'persona_dia'],

                // En qué franja se puede gastar. Vacío = a cualquier hora.
                'franja'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],

                // Tope de dinero, para los planes de «hasta X pesos». 0 = sin tope.
                'tope'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('plan_id');
            $this->forge->addForeignKey('plan_id', 'planes', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('plan_lineas');
        }

        // El plan se cuelga del tipo de alojamiento (la tarifa lo incluye) y se
        // puede pisar en una reserva concreta, para el día que se negocia.
        $tipos = array_column($this->db->getFieldData('tipos_unidad'), 'name');
        if (! in_array('plan_id', $tipos, true)) {
            $this->forge->addColumn('tipos_unidad', [
                'plan_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'tarifa_base'],
            ]);
        }

        $reservas = array_column($this->db->getFieldData('reservas'), 'name');
        if (! in_array('plan_id', $reservas, true)) {
            $this->forge->addColumn('reservas', [
                'plan_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'unidad_id'],
            ]);
        }
    }

    /**
     * Lo que ya se ha gastado del plan.
     *
     * Se apunta al consumir y no se calcula al vuelo a propósito: el derecho es
     * «uno por persona y día», y saber cuántos quedan hoy exige haber apuntado
     * cuándo se gastó cada uno.
     */
    private function consumos(): void
    {
        if ($this->db->tableExists('plan_consumos')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reserva_id' => ['type' => 'INT', 'unsigned' => true],
            'linea_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'comanda_linea_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'fecha'      => ['type' => 'DATE'],
            'cantidad'   => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'valor'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['reserva_id', 'fecha']);
        $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('plan_consumos');
    }

    /** Cortesías y devoluciones sobre una línea de comanda. */
    private function cortesias(): void
    {
        $columnas = array_column($this->db->getFieldData('comanda_lineas'), 'name');

        if (! in_array('estado_linea', $columnas, true)) {
            $this->forge->addColumn('comanda_lineas', [
                // `normal` se cobra. `incluida` la paga el plan. `cortesia` la
                // regala la casa. `devuelta` volvió a cocina y no se cobra.
                //
                // Las tres últimas **siguen contando como consumo**: la cocina
                // gastó el producto igual, y si desaparecieran el escandallo
                // mentiría sobre lo que ha salido.
                'estado_linea' => [
                    'type'       => 'ENUM',
                    'constraint' => ['normal', 'incluida', 'cortesia', 'devuelta'],
                    'default'    => 'normal',
                    'after'      => 'cantidad',
                ],
                'motivo_linea' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'estado_linea'],
                'autorizo_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'motivo_linea'],
            ]);
        }
    }

    /**
     * Franjas horarias de la carta.
     *
     * En `carta_categorias` y no en cada producto: se decide una vez por
     * «Desayunos» en lugar de cuarenta veces, plato a plato.
     */
    private function franjas(): void
    {
        $columnas = array_column($this->db->getFieldData('carta_categorias'), 'name');

        if (! in_array('franjas', $columnas, true)) {
            // Lista separada por comas: «desayuno,comida». Vacío = a todas horas.
            $this->forge->addColumn('carta_categorias', [
                'franjas' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'orden'],
            ]);
        }

        // Horas de cada franja, editables desde Administración
        $config = $this->db->table('configuracion');
        $porDefecto = [
            'carta_franja_desayuno' => '06:30-11:00',
            'carta_franja_comida'   => '12:00-16:00',
            'carta_franja_cena'     => '18:30-22:00',
        ];

        foreach ($porDefecto as $clave => $valor) {
            if ($config->where('clave', $clave)->countAllResults(false) === 0) {
                $config->resetQuery();
                $config->insert(['clave' => $clave, 'valor' => $valor]);
            }
            $config->resetQuery();
        }
    }

    public function down()
    {
        foreach (['plan_consumos', 'plan_lineas', 'planes'] as $tabla) {
            if ($this->db->tableExists($tabla)) {
                $this->forge->dropTable($tabla);
            }
        }

        foreach ([['tipos_unidad', 'plan_id'], ['reservas', 'plan_id'], ['carta_categorias', 'franjas']] as [$tabla, $columna]) {
            if (in_array($columna, array_column($this->db->getFieldData($tabla), 'name'), true)) {
                $this->forge->dropColumn($tabla, $columna);
            }
        }

        foreach (['estado_linea', 'motivo_linea', 'autorizo_id'] as $columna) {
            if (in_array($columna, array_column($this->db->getFieldData('comanda_lineas'), 'name'), true)) {
                $this->forge->dropColumn('comanda_lineas', $columna);
            }
        }
    }
}
