<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cartera: cuentas de empresa y agencia.
 *
 * **La decisión de fondo: la cartera NO duplica el folio.** Una reserva que se
 * carga a una empresa sigue teniendo su folio con todos sus consumos; lo que
 * cambia es quién paga. Si la deuda se copiara a otra tabla, el día que alguien
 * añada un cargo al folio la cartera dejaría de cuadrar, y a partir de ahí
 * ninguno de los dos números sirve.
 *
 * Lo que sí es nuevo es el **movimiento de cuenta**: los abonos que hace la
 * empresa no van contra una reserva concreta, van contra su cuenta. Un pago de
 * cinco millones puede saldar cuatro facturas y dejar saldo a favor.
 */
class Cartera extends Migration
{
    public function up()
    {
        $this->cuentas();
        $this->movimientos();
        $this->enlazarReservas();
        $this->permisos();
    }

    private function cuentas(): void
    {
        if ($this->db->tableExists('cuentas_cartera')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'codigo' => ['type' => 'VARCHAR', 'constraint' => 20],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 200],
            'tipo'   => [
                'type'       => 'ENUM',
                'constraint' => ['empresa', 'agencia', 'ota', 'particular'],
                'default'    => 'empresa',
            ],

            'nit'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'contacto'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'email'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'telefono'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'direccion' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            // El cupo es lo que impide que una empresa acumule una deuda que
            // nunca va a poder pagar. En 0 significa «sin límite», que es una
            // decisión consciente y no un descuido.
            'cupo' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            // Días para pagar desde que se cierra la cuenta. Es lo que decide
            // cuándo una factura pasa a estar vencida.
            'plazo_dias' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 30],

            // Descuento pactado, si lo hay. Se ENSEÑA al cargar, no se aplica
            // solo: un descuento automático es dinero que se va sin que nadie
            // lo haya decidido esa vez.
            'descuento_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],

            // Una cuenta bloqueada no admite cargos nuevos. Es el freno para
            // cuando alguien lleva tres meses sin pagar.
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['activa', 'bloqueada', 'cerrada'],
                'default'    => 'activa',
            ],
            'motivo_bloqueo' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'notas'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->addKey(['estado', 'tipo']);
        $this->forge->createTable('cuentas_cartera');
    }

    /**
     * Los movimientos de la cuenta.
     *
     * Un cargo nace cuando una reserva se cierra contra la cuenta; un abono,
     * cuando la empresa paga. **El saldo se calcula sumando, nunca se guarda en
     * una columna**: un saldo guardado se desincroniza el primer día que algo
     * falle a mitad, y entonces nadie sabe cuál de los dos números es el bueno.
     */
    private function movimientos(): void
    {
        if ($this->db->tableExists('cartera_movimientos')) {
            return;
        }

        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'cuenta_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['cargo', 'abono', 'nota_credito', 'ajuste'],
                'default'    => 'cargo',
            ],

            'concepto' => ['type' => 'VARCHAR', 'constraint' => 200],
            'valor'    => ['type' => 'DECIMAL', 'constraint' => '12,2'],

            // De qué reserva viene, si viene de una
            'reserva_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'factura_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            // Cuándo vence este cargo. Los abonos no vencen.
            'fecha'    => ['type' => 'DATE'],
            'vence_en' => ['type' => 'DATE', 'null' => true],

            'medio_pago' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'referencia' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],

            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['cuenta_id', 'fecha']);
        $this->forge->addKey('vence_en');
        $this->forge->addForeignKey('cuenta_id', 'cuentas_cartera', 'id', '', 'CASCADE');
        $this->forge->createTable('cartera_movimientos');
    }

    private function enlazarReservas(): void
    {
        $columnas = array_column($this->db->getFieldData('reservas'), 'name');

        if (in_array('cuenta_id', $columnas, true)) {
            return;
        }

        $this->forge->addColumn('reservas', [
            // Quién paga esta reserva. `null` es el propio huésped, que es lo
            // normal; con cuenta, la factura va a la empresa.
            'cuenta_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'huesped_id',
            ],
        ]);
    }

    private function permisos(): void
    {
        $nuevos = ['cartera.ver', 'cartera.gestionar', 'cartera.cobrar'];

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

        $mapa = [];

        foreach ($this->db->table('permisos')->select('id, clave')->get()->getResultArray() as $p) {
            $mapa[$p['clave']] = (int) $p['id'];
        }

        foreach (['recepcion', 'caja'] as $clave) {
            $rol = $this->db->table('roles')->where('clave', $clave)->get()->getRowArray();

            if ($rol === null) {
                continue;
            }

            foreach (\App\Libraries\Permisos\Catalogo::permisosDe($clave) as $permiso) {
                if (! in_array($permiso, $nuevos, true) || ! isset($mapa[$permiso])) {
                    continue;
                }

                $ya = $this->db->table('rol_permisos')
                    ->where('rol_id', $rol['id'])
                    ->where('permiso_id', $mapa[$permiso])
                    ->countAllResults();

                if ($ya === 0) {
                    $this->db->table('rol_permisos')->insert([
                        'rol_id'     => (int) $rol['id'],
                        'permiso_id' => $mapa[$permiso],
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('cartera_movimientos', true);
        $this->forge->dropTable('cuentas_cartera', true);
    }
}
