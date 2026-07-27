<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ajustes del núcleo que hace falta para poder recibir reservas de OTA.
 *
 * Son tres cosas pequeñas y una importante.
 *
 * **La importante:** `reservas.unidad_id` pasa a admitir NULL. Cuando Beds24
 * vende «una cabaña» —las siete se publican como un solo alojamiento— la
 * reserva que llega **no dice cuál**. Normalmente el sistema asigna una libre,
 * pero si no queda ninguna (la OTA vendió una noche ya vendida) la reserva no
 * se puede rechazar: ya está vendida, y rechazarla aquí no la deshace. Se
 * guarda sin cabaña y salta un aviso para que una persona lo resuelva.
 *
 * Con la columna en NOT NULL ese `insert()` devolvería 0 **en silencio** y la
 * reserva se perdería. Ya pasó una vez en este proyecto con
 * `comandas.usuario_id`, y las comandas del móvil se evaporaban sin dar error.
 */
class ReservaSinUnidad extends Migration
{
    public function up()
    {
        $campos = $this->db->getFieldData('reservas');
        $porNombre = [];
        foreach ($campos as $c) {
            $porNombre[$c->name] = $c;
        }

        // ── 1. La cabaña deja de ser obligatoria ──
        if (isset($porNombre['unidad_id']) && $porNombre['unidad_id']->nullable === false) {
            $this->db->query('ALTER TABLE `reservas` MODIFY `unidad_id` INT UNSIGNED NULL');
        }

        // ── 2. Falta un estado: no presentado ──
        // `modified` no se añade porque no es un estado, es un suceso, y ya
        // queda registrado en `channel_events`. `completed` tampoco: eso ya es
        // `checkout`. El único que falta de verdad es este.
        if (isset($porNombre['estado']) && ! str_contains((string) $porNombre['estado']->type, 'no_show')) {
            $this->db->query(
                "ALTER TABLE `reservas` MODIFY `estado`
                 ENUM('pendiente','confirmada','checkin','checkout','cancelada','no_show')
                 NOT NULL DEFAULT 'pendiente'"
            );
        }

        // ── 3. Reservas pendientes que caducan ──
        // Hasta ahora una reserva `pendiente` ocupaba para siempre. Con OTAs
        // conectadas eso es inventario bloqueado que nadie va a pagar.
        if (! isset($porNombre['expira_en'])) {
            $this->forge->addColumn('reservas', [
                'expira_en' => ['type' => 'DATETIME', 'null' => true, 'after' => 'estado'],
            ]);
        }

        if (! isset($porNombre['cancelada_en'])) {
            $this->forge->addColumn('reservas', [
                'cancelada_en'     => ['type' => 'DATETIME', 'null' => true, 'after' => 'expira_en'],
                'cancelada_origen' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'cancelada_en'],
            ]);
        }

        // ── 4. Por qué está bloqueada una fecha ──
        // Cubre el «mantenimiento» y el «cierre de ventas» que faltaban, sin
        // inventar una tabla nueva de disponibilidad: `bloqueos` ya es eso.
        $camposBloqueo = array_column($this->db->getFieldData('bloqueos'), 'name');
        if (! in_array('motivo', $camposBloqueo, true)) {
            $this->forge->addColumn('bloqueos', [
                'motivo' => [
                    'type'       => 'ENUM',
                    'constraint' => ['ical', 'obra', 'mantenimiento', 'uso_propio', 'cierre_ventas', 'canal', 'otro'],
                    'default'    => 'otro',
                    'after'      => 'origen',
                ],
            ]);
            $this->db->query("UPDATE `bloqueos` SET `motivo` = 'ical' WHERE `origen` = 'ical'");
        }
    }

    public function down()
    {
        // `unidad_id` no se devuelve a NOT NULL: si ya hay reservas sin cabaña
        // asignada, hacerlo fallaría o —peor— obligaría a inventarles una.
        $campos = array_column($this->db->getFieldData('reservas'), 'name');

        foreach (['expira_en', 'cancelada_en', 'cancelada_origen'] as $col) {
            if (in_array($col, $campos, true)) {
                $this->forge->dropColumn('reservas', $col);
            }
        }

        if (in_array('motivo', array_column($this->db->getFieldData('bloqueos'), 'name'), true)) {
            $this->forge->dropColumn('bloqueos', 'motivo');
        }
    }
}
