<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * TPV compartido entre varios camareros.
 *
 * Hasta ahora todo se apuntaba a quien inició sesión por la mañana. Con
 * varios turnos en la misma pantalla eso no sirve ni para pagar propinas
 * ni para saber quién anuló una comanda.
 *
 * Cada paso comprueba antes si ya está hecho: MySQL no sabe deshacer un
 * ALTER TABLE a medias, así que una migración que toca varias tablas tiene
 * que poder repetirse sin romperse.
 */
class TpvCompartido extends Migration
{
    public function up()
    {
        // ── El empleado se identifica con su PIN de fichaje o con una tarjeta ──
        // El lector RFID se comporta como un teclado: «escribe» el número y un Enter
        $this->anadirColumna('empleados', 'tarjeta_uid', [
            'tarjeta_uid' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'ficha_movil'],
        ]);
        $this->anadirColumna('empleados', 'rol_tpv', [
            'rol_tpv' => ['type' => 'ENUM', 'constraint' => ['ninguno', 'camarero', 'encargado'], 'default' => 'ninguno', 'after' => 'ficha_movil'],
        ]);
        $this->anadirIndice('empleados', 'empleados_tarjeta', 'ALTER TABLE empleados ADD UNIQUE KEY empleados_tarjeta (tarjeta_uid)');

        // ── Quién hizo cada cosa ──
        $this->anadirColumna('comandas', 'empleado_id', [
            'empleado_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'usuario_id'],
        ]);
        $this->anadirColumna('comandas', 'autorizo_id', [
            'autorizo_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'usuario_id'],
        ]);
        $this->anadirIndice('comandas', 'comandas_empleado_id_foreign',
            'ALTER TABLE comandas ADD CONSTRAINT comandas_empleado_id_foreign
             FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL');

        // Las líneas no guardaban a nadie: quien añade un plato debe constar
        $this->anadirColumna('comanda_lineas', 'empleado_id', [
            'empleado_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'notas'],
        ]);
        $this->anadirIndice('comanda_lineas', 'comanda_lineas_empleado_id_foreign',
            'ALTER TABLE comanda_lineas ADD CONSTRAINT comanda_lineas_empleado_id_foreign
             FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL');

        $this->anadirColumna('comanda_pagos', 'empleado_id', [
            'empleado_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'usuario_id'],
        ]);
        $this->anadirIndice('comanda_pagos', 'comanda_pagos_empleado_id_foreign',
            'ALTER TABLE comanda_pagos ADD CONSTRAINT comanda_pagos_empleado_id_foreign
             FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL');
    }

    public function down()
    {
        foreach ([
            ['comanda_pagos', 'comanda_pagos_empleado_id_foreign', ['empleado_id']],
            ['comanda_lineas', 'comanda_lineas_empleado_id_foreign', ['empleado_id']],
            ['comandas', 'comandas_empleado_id_foreign', ['empleado_id', 'autorizo_id']],
        ] as [$tabla, $clave, $columnas]) {
            if ($this->existeIndice($tabla, $clave)) {
                $this->db->query("ALTER TABLE {$tabla} DROP FOREIGN KEY {$clave}");
            }
            foreach ($columnas as $columna) {
                if ($this->existeColumna($tabla, $columna)) {
                    $this->forge->dropColumn($tabla, $columna);
                }
            }
        }

        if ($this->existeIndice('empleados', 'empleados_tarjeta')) {
            $this->db->query('ALTER TABLE empleados DROP INDEX empleados_tarjeta');
        }
        foreach (['tarjeta_uid', 'rol_tpv'] as $columna) {
            if ($this->existeColumna('empleados', $columna)) {
                $this->forge->dropColumn('empleados', $columna);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────

    private function anadirColumna(string $tabla, string $columna, array $definicion): void
    {
        if (! $this->existeColumna($tabla, $columna)) {
            $this->forge->addColumn($tabla, $definicion);
        }
    }

    private function anadirIndice(string $tabla, string $nombre, string $sql): void
    {
        if (! $this->existeIndice($tabla, $nombre)) {
            $this->db->query($sql);
        }
    }

    private function existeColumna(string $tabla, string $columna): bool
    {
        return in_array($columna, $this->db->getFieldNames($tabla), true);
    }

    private function existeIndice(string $tabla, string $nombre): bool
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total FROM information_schema.table_constraints
             WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$tabla, $nombre]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0) > 0;
    }
}
