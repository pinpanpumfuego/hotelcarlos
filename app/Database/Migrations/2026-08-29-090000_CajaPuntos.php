<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Puntos de caja, arqueo por denominaciones y medios de pago configurables.
 *
 * **El problema de fondo que arregla esto:** los medios de pago estaban
 * escritos como ENUM en tres sitios distintos —el folio, los pagos de comanda y
 * los cupones— y no coincidían entre sí. El folio conocía «bono» y la comanda
 * «habitación»; ninguno de los dos sabía del otro. Cualquier informe que
 * cruzara los dos daba números que no cuadran, y no había forma de añadir uno
 * nuevo sin tocar código y migrar tablas.
 *
 * Ahora hay un catálogo. Los ENUM viejos se conservan tal cual —romperlos
 * dejaría sin leer todo lo cobrado hasta hoy— y las tablas apuntan además al
 * catálogo, que es lo que manda de aquí en adelante.
 *
 * **La distinción que lo sostiene todo es `afecta_caja`.** Un cobro con tarjeta
 * es un ingreso, pero no hay un solo peso más dentro del cajón. Contarlo en el
 * arqueo hace que la caja «falte» exactamente lo que se cobró con tarjeta, y a
 * partir de ahí nadie se fía del arqueo.
 */
class CajaPuntos extends Migration
{
    public function up()
    {
        $this->mediosPago();
        $this->puntos();
        $this->ampliarTurnos();
        $this->ampliarMovimientos();
        $this->denominaciones();
        $this->sembrar();
        $this->permisos();
    }

    private function mediosPago(): void
    {
        if ($this->db->tableExists('medios_pago')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'clave'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 60],

            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['efectivo', 'tarjeta', 'transferencia', 'pasarela', 'bono', 'cartera', 'cortesia', 'otro'],
                'default'    => 'otro',
            ],

            // LA columna importante. Solo el efectivo entra en el cajón; todo
            // lo demás es un ingreso que no se puede contar a mano.
            'afecta_caja' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            // Un datáfono sin número de aprobación es un cobro que no se puede
            // reclamar si el banco no lo abona.
            'requiere_referencia' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            // Lo que se queda el banco o la pasarela. Sirve para saber cuánto
            // llega de verdad, que nunca es lo que dice el ticket.
            'comision_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],

            // La cuenta del plan contable. Se deja para que la rellene el
            // contador: inventarla yo sería meterle un número en su balance.
            'cuenta_contable' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],

            // Dónde se puede usar
            'en_recepcion' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'en_tpv'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'en_web'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'orden'      => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'activo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('medios_pago');
    }

    private function puntos(): void
    {
        if ($this->db->tableExists('puntos_caja')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'clave'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 60],
            'tipo'   => [
                'type'       => 'ENUM',
                'constraint' => ['recepcion', 'restaurante', 'bar', 'tienda', 'otro'],
                'default'    => 'otro',
            ],

            // Cada punto tiene su propia base. La de recepción y la del bar no
            // son la misma plata ni la cuenta la misma persona.
            'base_sugerida' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            // Con el arqueo por denominaciones, cerrar obliga a contar billete a
            // billete. Se puede apagar en un punto que apenas mueva efectivo.
            'exige_denominaciones' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            // A partir de cuánto un descuadre deja de ser un redondeo
            'tolerancia' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 2000],

            'activo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('puntos_caja');
    }

    private function ampliarTurnos(): void
    {
        $columnas = array_column($this->db->getFieldData('caja_turnos'), 'name');
        $nuevas   = [];

        if (! in_array('punto_id', $columnas, true)) {
            $nuevas['punto_id'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'id',
            ];
        }

        if (! in_array('cerro_id', $columnas, true)) {
            // Quien abre y quien cierra pueden no ser la misma persona: el
            // turno de noche lo cierra quien entra de mañana.
            $nuevas['cerro_id'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'cierre',
            ];
        }

        if (! in_array('retiros', $columnas, true)) {
            // Un retiro NO es un gasto: es plata que sale del cajón y sigue
            // siendo del hotel. Mezclarlo con los egresos hace que el día
            // parezca que costó lo que en realidad está en la caja fuerte.
            $nuevas['retiros'] = [
                'type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'efectivo_contado',
            ];
        }

        if (! in_array('esperado', $columnas, true)) {
            // Se congela al cerrar. Recalcularlo después daría otro número si
            // alguien tocara un movimiento viejo, y entonces el descuadre
            // firmado aquel día dejaría de poder explicarse.
            $nuevas['esperado'] = [
                'type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'retiros',
            ];
        }

        if (! in_array('justificacion', $columnas, true)) {
            $nuevas['justificacion'] = [
                'type' => 'VARCHAR', 'constraint' => 300, 'null' => true, 'after' => 'diferencia',
            ];
        }

        if ($nuevas !== []) {
            $this->forge->addColumn('caja_turnos', $nuevas);
        }
    }

    private function ampliarMovimientos(): void
    {
        $columnas = array_column($this->db->getFieldData('caja_movimientos'), 'name');
        $nuevas   = [];

        if (! in_array('medio_id', $columnas, true)) {
            $nuevas['medio_id'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'tipo',
            ];
        }

        if (! in_array('referencia', $columnas, true)) {
            $nuevas['referencia'] = [
                'type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'concepto',
            ];
        }

        if (! in_array('comanda_id', $columnas, true)) {
            $nuevas['comanda_id'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'folio_movimiento_id',
            ];
        }

        if ($nuevas !== []) {
            $this->forge->addColumn('caja_movimientos', $nuevas);
        }

        // `retiro` es su propio tipo, no un egreso disfrazado.
        $this->db->query(
            "ALTER TABLE `caja_movimientos` MODIFY `tipo`
             ENUM('ingreso','egreso','retiro','ajuste') NOT NULL DEFAULT 'ingreso'"
        );

        // Un movimiento lo puede generar el sistema sin que haya nadie delante:
        // el TPV al cobrar una comanda, o la pasarela al confirmar un pago.
        // Con la columna obligatoria, esos apuntes reventaban.
        $this->db->query('ALTER TABLE `caja_movimientos` MODIFY `usuario_id` INT(10) UNSIGNED NULL');
    }

    /**
     * El arqueo billete a billete.
     *
     * Escribir «hay 847.000» de memoria y que cuadre es sospechosamente fácil.
     * Contar por denominaciones obliga a abrir el cajón, y el total lo calcula
     * el sistema: quien cuenta no puede ajustar el número para que cuadre.
     */
    private function denominaciones(): void
    {
        if ($this->db->tableExists('arqueo_denominaciones')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'turno_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'denominacion' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'cantidad'     => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['turno_id', 'denominacion']);
        $this->forge->addForeignKey('turno_id', 'caja_turnos', 'id', '', 'CASCADE');
        $this->forge->createTable('arqueo_denominaciones');
    }

    private function sembrar(): void
    {
        $ahora = date('Y-m-d H:i:s');

        if ($this->db->table('medios_pago')->countAllResults() === 0) {
            // Las claves son las mismas que ya usaban los ENUM viejos: así lo
            // cobrado hasta hoy se puede enlazar sin traducir nada.
            $medios = [
                ['clave' => 'efectivo', 'nombre' => 'Efectivo', 'tipo' => 'efectivo',
                 'afecta_caja' => 1, 'requiere_referencia' => 0, 'orden' => 1, 'en_web' => 0],
                ['clave' => 'tarjeta', 'nombre' => 'Tarjeta (datáfono)', 'tipo' => 'tarjeta',
                 'afecta_caja' => 0, 'requiere_referencia' => 1, 'orden' => 2, 'en_web' => 0],
                ['clave' => 'transferencia', 'nombre' => 'Transferencia', 'tipo' => 'transferencia',
                 'afecta_caja' => 0, 'requiere_referencia' => 1, 'orden' => 3, 'en_web' => 0],
                ['clave' => 'wompi', 'nombre' => 'Pago en línea (Wompi)', 'tipo' => 'pasarela',
                 'afecta_caja' => 0, 'requiere_referencia' => 1, 'orden' => 4, 'en_tpv' => 0, 'en_web' => 1],
                ['clave' => 'bono', 'nombre' => 'Bono regalo', 'tipo' => 'bono',
                 'afecta_caja' => 0, 'requiere_referencia' => 1, 'orden' => 5, 'en_web' => 0],
                ['clave' => 'habitacion', 'nombre' => 'A la cuenta de la cabaña', 'tipo' => 'cartera',
                 'afecta_caja' => 0, 'requiere_referencia' => 0, 'orden' => 6, 'en_web' => 0],
                ['clave' => 'cartera', 'nombre' => 'A crédito (empresa o agencia)', 'tipo' => 'cartera',
                 'afecta_caja' => 0, 'requiere_referencia' => 0, 'orden' => 7, 'activo' => 0, 'en_web' => 0],
                // Cortesía no es un cobro de cero: es un ingreso que se decide
                // no cobrar, y tiene que verse como tal en los informes.
                ['clave' => 'cortesia', 'nombre' => 'Cortesía de la casa', 'tipo' => 'cortesia',
                 'afecta_caja' => 0, 'requiere_referencia' => 0, 'orden' => 8, 'en_web' => 0],
                ['clave' => 'otro', 'nombre' => 'Otro', 'tipo' => 'otro',
                 'afecta_caja' => 0, 'requiere_referencia' => 0, 'orden' => 9, 'en_web' => 0],
            ];

            foreach ($medios as $m) {
                $this->db->table('medios_pago')->insert($m + [
                    'comision_pct' => 0, 'en_recepcion' => 1, 'en_tpv' => 1, 'activo' => 1,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }

        if ($this->db->table('puntos_caja')->countAllResults() === 0) {
            $puntos = [
                ['clave' => 'recepcion', 'nombre' => 'Recepción', 'tipo' => 'recepcion', 'base_sugerida' => 200000],
                ['clave' => 'restaurante', 'nombre' => 'Restaurante', 'tipo' => 'restaurante', 'base_sugerida' => 100000],
            ];

            foreach ($puntos as $p) {
                $this->db->table('puntos_caja')->insert($p + [
                    'exige_denominaciones' => 1, 'tolerancia' => 2000, 'activo' => 1,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }

        // Los turnos que ya existan se quedan en recepción: es donde estaban.
        $recepcion = $this->db->table('puntos_caja')->where('clave', 'recepcion')->get()->getRowArray();

        if ($recepcion !== null) {
            $this->db->table('caja_turnos')->where('punto_id IS NULL')->update(['punto_id' => $recepcion['id']]);
        }
    }

    private function permisos(): void
    {
        $nuevos = ['caja.retirar', 'caja.puntos', 'medios.gestionar'];

        foreach ($nuevos as $clave) {
            $datos = \App\Libraries\Permisos\Catalogo::PERMISOS[$clave] ?? null;

            if ($datos === null) {
                continue;
            }

            if ($this->db->table('permisos')->where('clave', $clave)->countAllResults() === 0) {
                $this->db->table('permisos')->insert([
                    'clave'       => $clave,
                    'modulo'      => $datos['modulo'],
                    'nombre'      => $datos['nombre'],
                    'es_sensible' => $datos['sensible'] ? 1 : 0,
                ]);
            }
        }

        // Quien ya lleva la caja puede retirar. Recepción no: cobra y apunta
        // gastos, pero vaciar el cajón hacia la caja fuerte es otra cosa.
        $mapa = [];

        foreach ($this->db->table('permisos')->select('id, clave')->get()->getResultArray() as $p) {
            $mapa[$p['clave']] = (int) $p['id'];
        }

        $rol = $this->db->table('roles')->where('clave', 'caja')->get()->getRowArray();

        if ($rol !== null && isset($mapa['caja.retirar'])) {
            $ya = $this->db->table('rol_permisos')
                ->where('rol_id', $rol['id'])
                ->where('permiso_id', $mapa['caja.retirar'])
                ->countAllResults();

            if ($ya === 0) {
                $this->db->table('rol_permisos')->insert([
                    'rol_id'     => (int) $rol['id'],
                    'permiso_id' => $mapa['caja.retirar'],
                ]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('arqueo_denominaciones', true);
        $this->forge->dropTable('puntos_caja', true);
        $this->forge->dropTable('medios_pago', true);
    }
}
