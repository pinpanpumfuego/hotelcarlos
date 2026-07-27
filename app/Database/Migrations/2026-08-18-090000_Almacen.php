<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Núcleo del almacén: bodegas, proveedores, existencias, lotes y movimientos.
 *
 * Hasta ahora el sistema sabía cuánto **debería** costar un plato, pero no qué
 * había en la nevera. Esto es lo que cierra ese hueco.
 *
 * Dos decisiones de fondo:
 *
 * 1. **Las existencias se materializan.** Podrían deducirse sumando todos los
 *    movimientos, pero el stock se consulta en cada venta y esa suma crecería
 *    para siempre. Se guarda el saldo y se recalcula desde los movimientos
 *    cuando haga falta cuadrar.
 * 2. **El coste va por promedio ponderado, y los lotes por trazabilidad.**
 *    Valorar por lotes (FIFO) es más exacto pero obliga a arrastrar de qué lote
 *    salió cada gramo; para una cocina de hotel, el promedio da un número
 *    suficientemente bueno y los lotes siguen sirviendo para lo que de verdad
 *    importan: saber qué caduca y de dónde vino.
 */
class Almacen extends Migration
{
    public function up()
    {
        $this->bodegas();
        $this->proveedores();
        $this->ampliarInsumos();
        $this->existencias();
        $this->lotes();
        $this->movimientos();
        $this->sembrarBodegas();
    }

    private function bodegas(): void
    {
        if ($this->db->tableExists('bodegas')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 80],
            'clave'  => ['type' => 'VARCHAR', 'constraint' => 30],

            // Para qué es: agrupa y permite filtrar sin depender del nombre
            'tipo'   => [
                'type'       => 'ENUM',
                'constraint' => ['cocina', 'bar', 'minibar', 'lenceria', 'mantenimiento', 'aseo', 'general'],
                'default'    => 'general',
            ],

            // De dónde sale lo que se vende en el restaurante si nadie dice otra
            // cosa. Sin una por defecto, cada venta obligaría a elegir bodega.
            'por_defecto' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'notas'      => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('bodegas');
    }

    private function proveedores(): void
    {
        if ($this->db->tableExists('proveedores')) {
            return;
        }

        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'    => ['type' => 'VARCHAR', 'constraint' => 150],
            'nit'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'contacto'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'telefono'  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'email'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'direccion' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            // Cuántos días tarda en servir: es lo que decide cuándo hay que
            // pedirle, no solo el stock mínimo.
            'dias_entrega' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],

            'notas'      => ['type' => 'TEXT', 'null' => true],
            'activo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('nombre');
        $this->forge->createTable('proveedores');
    }

    /** Los insumos pasan a saber de proveedor, mínimos y caducidad. */
    private function ampliarInsumos(): void
    {
        $columnas = array_column($this->db->getFieldData('insumos'), 'name');

        $nuevas = [];

        if (! in_array('proveedor_id', $columnas, true)) {
            // La columna `proveedor` de texto se queda: es lo que hay escrito
            // hoy, y borrarla perdería datos. Se migra a mano desde el panel.
            $nuevas['proveedor_id'] = ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'proveedor'];
        }
        if (! in_array('categoria', $columnas, true)) {
            $nuevas['categoria'] = ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'nombre'];
        }
        if (! in_array('stock_minimo', $columnas, true)) {
            $nuevas['stock_minimo'] = ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0, 'after' => 'costo_unitario'];
            $nuevas['stock_maximo'] = ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0, 'after' => 'stock_minimo'];
        }
        if (! in_array('controla_lote', $columnas, true)) {
            // No todo necesita lote. Exigirlo para la sal es hacer imposible el
            // trabajo diario; para el pescado fresco es imprescindible.
            $nuevas['controla_lote'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'stock_maximo'];
            $nuevas['dias_aviso_caducidad'] = ['type' => 'INT', 'unsigned' => true, 'default' => 7, 'after' => 'controla_lote'];
        }

        if ($nuevas !== []) {
            $this->forge->addColumn('insumos', $nuevas);
        }
    }

    /** El saldo por insumo y bodega. */
    private function existencias(): void
    {
        if ($this->db->tableExists('existencias')) {
            return;
        }

        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'insumo_id' => ['type' => 'INT', 'unsigned' => true],
            'bodega_id' => ['type' => 'INT', 'unsigned' => true],

            // Puede quedar en negativo: significa que se ha vendido algo que el
            // sistema no sabía que había. Prohibirlo pararía el servicio, y es
            // peor. Sale en rojo y se cuadra con un conteo.
            'cantidad'    => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],

            // Promedio ponderado: se recalcula en cada entrada
            'costo_medio' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],

            'ultimo_movimiento' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Un saldo por insumo y bodega, ni dos ni ninguno
        $this->forge->addUniqueKey(['insumo_id', 'bodega_id']);
        $this->forge->addForeignKey('insumo_id', 'insumos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('bodega_id', 'bodegas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('existencias');
    }

    /** Lotes con caducidad, para lo que la tenga. */
    private function lotes(): void
    {
        if ($this->db->tableExists('lotes')) {
            return;
        }

        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'insumo_id' => ['type' => 'INT', 'unsigned' => true],
            'bodega_id' => ['type' => 'INT', 'unsigned' => true],
            'codigo'    => ['type' => 'VARCHAR', 'constraint' => 60],
            'caduca_el' => ['type' => 'DATE', 'null' => true],
            'cantidad'  => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'costo_unitario' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],
            'proveedor_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'recibido_el'    => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['insumo_id', 'bodega_id']);
        // Para la pantalla de «qué caduca pronto»
        $this->forge->addKey('caduca_el');
        $this->forge->addForeignKey('insumo_id', 'insumos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('bodega_id', 'bodegas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('lotes');
    }

    /**
     * Todo lo que entra, sale o se mueve.
     *
     * **Esta tabla no se edita ni se borra nunca.** Un error se corrige con un
     * movimiento contrario, igual que en contabilidad: si se pudiera retocar,
     * dejaría de servir para explicar por qué el stock es el que es.
     */
    private function movimientos(): void
    {
        if ($this->db->tableExists('movimientos_stock')) {
            return;
        }

        $this->forge->addField([
            'id'        => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'insumo_id' => ['type' => 'INT', 'unsigned' => true],
            'bodega_id' => ['type' => 'INT', 'unsigned' => true],
            'lote_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['entrada', 'salida', 'traslado', 'ajuste', 'merma', 'consumo_interno', 'cortesia', 'devolucion'],
            ],

            // Positiva en lo que suma, negativa en lo que resta. Guardar el
            // signo aquí evita tener que recordar en cada consulta qué tipos
            // suman y cuáles restan.
            'cantidad'       => ['type' => 'DECIMAL', 'constraint' => '14,3'],
            'costo_unitario' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],

            // El saldo que quedó tras este movimiento: permite reconstruir la
            // película sin volver a sumarlo todo.
            'saldo' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],

            // De dónde viene: 'comanda', 'orden_compra', 'conteo', 'traslado'…
            'referencia_tipo' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'referencia_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            'motivo'     => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'usuario_nombre' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['insumo_id', 'bodega_id', 'created_at']);
        $this->forge->addKey(['referencia_tipo', 'referencia_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('insumo_id', 'insumos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('bodega_id', 'bodegas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('movimientos_stock');
    }

    /**
     * Las bodegas que pide la lista, creadas de entrada.
     *
     * Un almacén vacío obliga a inventarse la estructura antes de poder hacer
     * nada. Estas seis cubren un hotel con restaurante; sobran las que no se
     * usen y se desactivan en dos clics.
     */
    private function sembrarBodegas(): void
    {
        $tabla = $this->db->table('bodegas');
        if ($tabla->countAllResults(false) > 0) {
            $tabla->resetQuery();

            return;
        }
        $tabla->resetQuery();

        $tabla->insertBatch([
            ['clave' => 'cocina', 'nombre' => 'Cocina', 'tipo' => 'cocina', 'por_defecto' => 1, 'activa' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['clave' => 'bar', 'nombre' => 'Bar', 'tipo' => 'bar', 'por_defecto' => 0, 'activa' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['clave' => 'minibar', 'nombre' => 'Minibar', 'tipo' => 'minibar', 'por_defecto' => 0, 'activa' => 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['clave' => 'lenceria', 'nombre' => 'Lencería', 'tipo' => 'lenceria', 'por_defecto' => 0, 'activa' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['clave' => 'mantenimiento', 'nombre' => 'Mantenimiento', 'tipo' => 'mantenimiento', 'por_defecto' => 0, 'activa' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['clave' => 'aseo', 'nombre' => 'Aseo', 'tipo' => 'aseo', 'por_defecto' => 0, 'activa' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
        ]);
    }

    public function down()
    {
        foreach (['movimientos_stock', 'lotes', 'existencias', 'proveedores', 'bodegas'] as $tabla) {
            if ($this->db->tableExists($tabla)) {
                $this->forge->dropTable($tabla);
            }
        }

        $columnas = array_column($this->db->getFieldData('insumos'), 'name');
        foreach (['proveedor_id', 'categoria', 'stock_minimo', 'stock_maximo', 'controla_lote', 'dias_aviso_caducidad'] as $c) {
            if (in_array($c, $columnas, true)) {
                $this->forge->dropColumn('insumos', $c);
            }
        }
    }
}
