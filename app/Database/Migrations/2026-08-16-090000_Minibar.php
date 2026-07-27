<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Minibar: puede haber o no.
 *
 * Se decide en **dos niveles a propósito**. Uno general, para el día que se
 * quite en todo el alojamiento sin tener que ir cabaña por cabaña. Y otro por
 * cabaña, porque en un ecolodge de siete cabañas es perfectamente normal que
 * tres tengan nevera y cuatro no.
 *
 * El catálogo **no es una tabla nueva**: son los productos de la carta marcados
 * como de minibar. Duplicarlo significaría mantener dos veces el mismo precio,
 * y el día que suba la cerveza subiría en un sitio y no en el otro.
 */
class Minibar extends Migration
{
    public function up()
    {
        // ── Qué cabañas lo tienen ──
        $columnasUnidad = array_column($this->db->getFieldData('unidades'), 'name');
        if (! in_array('minibar', $columnasUnidad, true)) {
            $this->forge->addColumn('unidades', [
                'minibar' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'estado'],
            ]);
        }

        // ── Qué productos hay dentro ──
        $columnasProducto = array_column($this->db->getFieldData('carta_productos'), 'name');
        if (! in_array('en_minibar', $columnasProducto, true)) {
            $this->forge->addColumn('carta_productos', [
                'en_minibar' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'disponible'],
            ]);
        }

        // ── Poder pedir que lo repongan ──
        $tipos = $this->db->getFieldData('solicitudes');
        foreach ($tipos as $campo) {
            if ($campo->name === 'tipo' && ! str_contains((string) $campo->type, 'minibar')) {
                $this->db->query(
                    "ALTER TABLE `solicitudes` MODIFY `tipo`
                     ENUM('limpieza','toallas','amenidades','mantenimiento','cuna','decoracion',
                          'salida_tarde','transporte','minibar','otro')
                     NOT NULL DEFAULT 'otro'"
                );
                break;
            }
        }

        // Apagado de fábrica: si el hotel no tiene minibar, no aparece por
        // ninguna parte y nadie tiene que ir a desactivarlo.
        $config = $this->db->table('configuracion');
        if ($config->where('clave', 'portal_minibar')->countAllResults(false) === 0) {
            $config->resetQuery();
            $config->insert(['clave' => 'portal_minibar', 'valor' => '0']);
        }
        $config->resetQuery();
    }

    public function down()
    {
        if (in_array('minibar', array_column($this->db->getFieldData('unidades'), 'name'), true)) {
            $this->forge->dropColumn('unidades', 'minibar');
        }
        if (in_array('en_minibar', array_column($this->db->getFieldData('carta_productos'), 'name'), true)) {
            $this->forge->dropColumn('carta_productos', 'en_minibar');
        }
    }
}
