<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tarjetas de saldo personalizadas, con QR.
 *
 * **La diferencia con los bonos regalo, que ya existen y se quedan como
 * están:** un bono se emite una vez, se gasta y se acaba. Una tarjeta tiene
 * dueño, se recarga, puede llevar descuento y puede congelarse. Migrar los
 * bonos a esto sería tocar algo que funciona sin necesidad.
 *
 * **Por qué el saldo SÍ se guarda aquí, cuando en la cartera se calcula:** en
 * la cartera el saldo es un informe, y calcularlo garantiza que nunca miente.
 * Aquí el saldo es una *puerta*: hay que comprobarlo y descontarlo en el mismo
 * instante, o dos cobros simultáneos leen el mismo saldo y los dos pasan. Se
 * guarda para poder hacer `UPDATE ... WHERE saldo >= importe`, que la base de
 * datos resuelve de forma atómica. Los movimientos siguen estando: son la
 * prueba, y hay una comprobación que avisa si alguna vez dejan de cuadrar.
 */
class Tarjetas extends Migration
{
    public function up()
    {
        $this->tipos();
        $this->tarjetas();
        $this->movimientos();
        $this->sembrar();
        $this->permisos();
    }

    private function tipos(): void
    {
        if ($this->db->tableExists('tipos_tarjeta')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'clave'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 60],

            'recargable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            // Si acumula, lo que no se gastó sigue ahí el mes siguiente. Si no,
            // la recarga reemplaza el saldo: eso es un auxilio, no un monedero.
            'acumula' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            // Meses hasta que caduca el saldo. `null` es sin caducidad, que es
            // lo que se siembra: la caducidad de saldos tiene reglas de
            // consumidor y la confirma el asesor, no este programa.
            'caduca_meses' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],

            // Regalo por recargar: cargas 100.000 y te quedan 110.000. El
            // regalo NO es dinero cobrado, así que se apunta aparte.
            'bonus_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],

            // Descuento sobre lo que compre quien pague con esta tarjeta.
            'descuento_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],

            'ambito' => [
                'type'       => 'ENUM',
                'constraint' => ['alojamiento', 'restaurante', 'todo'],
                'default'    => 'todo',
            ],

            // Por debajo de este importe se cobra sin PIN: un café en la barra
            // con PIN es una tarjeta que nadie usa. Por encima, PIN.
            'pin_desde' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 50000],

            'color'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'primary'],
            'activo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('tipos_tarjeta');
    }

    private function tarjetas(): void
    {
        if ($this->db->tableExists('tarjetas')) {
            return;
        }

        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'tipo_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

            // Va impreso en la tarjeta y dentro del QR. Sin letras que se
            // confundan al dictarlo por teléfono.
            'codigo' => ['type' => 'VARCHAR', 'constraint' => 20],

            // De quién es. Puede ser un huésped, un empleado o una empresa: las
            // tres cosas pasan y ninguna excluye a las otras.
            'huesped_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'empleado_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'cuenta_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            // El nombre que va impreso, que puede no ser el de ningún registro
            'titular' => ['type' => 'VARCHAR', 'constraint' => 150],

            // El PIN se guarda cifrado, nunca en claro: es lo único que separa
            // una tarjeta perdida de un saldo perdido.
            'pin_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            // La puerta. Ver la nota de la cabecera sobre por qué se guarda.
            'saldo' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            // Descuento propio, que pisa el del tipo. `null` usa el del tipo.
            'descuento_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],

            // Cuánto se recarga cada mes, si es de recarga periódica. En 0 solo
            // se recarga a mano.
            'recarga_mensual' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'ultima_recarga'  => ['type' => 'DATE', 'null' => true],

            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['activa', 'congelada', 'anulada'],
                'default'    => 'activa',
            ],
            'motivo_estado' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'caduca'     => ['type' => 'DATE', 'null' => true],
            'notas'      => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->addKey(['estado', 'tipo_id']);
        $this->forge->addKey('huesped_id');
        $this->forge->addForeignKey('tipo_id', 'tipos_tarjeta', 'id', '', 'RESTRICT');
        $this->forge->createTable('tarjetas');
    }

    private function movimientos(): void
    {
        if ($this->db->tableExists('tarjeta_movimientos')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'tarjeta_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['carga', 'bonus', 'consumo', 'devolucion', 'ajuste', 'caducidad'],
                'default'    => 'carga',
            ],

            // De dónde salió el dinero de una carga. Importa para el arqueo:
            // una carga en efectivo entra en el cajón y una por Wompi no.
            'origen' => [
                'type'       => 'ENUM',
                'constraint' => ['efectivo', 'tarjeta', 'transferencia', 'wompi', 'empresa', 'cortesia', 'otro'],
                'null'       => true,
            ],

            'valor'         => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'saldo_despues' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'concepto'      => ['type' => 'VARCHAR', 'constraint' => 150],

            'reserva_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'comanda_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'pago_online_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'referencia'     => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],

            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['tarjeta_id', 'created_at']);
        $this->forge->addForeignKey('tarjeta_id', 'tarjetas', 'id', '', 'CASCADE');
        $this->forge->createTable('tarjeta_movimientos');
    }

    private function sembrar(): void
    {
        if ($this->db->table('tipos_tarjeta')->countAllResults() > 0) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');

        // Tres modalidades de partida. Los números son un punto de partida
        // razonable, no una recomendación: se cambian desde el panel.
        $tipos = [
            [
                'clave' => 'regalo', 'nombre' => 'Tarjeta regalo',
                'recargable' => 1, 'acumula' => 1, 'caduca_meses' => null,
                'bonus_pct' => 0, 'descuento_pct' => 0, 'ambito' => 'todo',
                'pin_desde' => 100000, 'color' => 'warning',
            ],
            [
                'clave' => 'fidelizacion', 'nombre' => 'Tarjeta de cliente',
                'recargable' => 1, 'acumula' => 1, 'caduca_meses' => null,
                // Recarga 100.000 y le quedan 110.000: es el gancho de un
                // monedero prepago, y sale más barato que un descuento directo
                // porque el dinero entra antes de consumirse.
                'bonus_pct' => 10, 'descuento_pct' => 5, 'ambito' => 'todo',
                'pin_desde' => 50000, 'color' => 'primary',
            ],
            [
                'clave' => 'personal', 'nombre' => 'Tarjeta del personal',
                'recargable' => 1, 'acumula' => 1, 'caduca_meses' => null,
                'bonus_pct' => 0, 'descuento_pct' => 20, 'ambito' => 'restaurante',
                'pin_desde' => 30000, 'color' => 'success',
            ],
        ];

        foreach ($tipos as $t) {
            $this->db->table('tipos_tarjeta')->insert($t + [
                'activo' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        // El medio de pago, para que aparezca en el TPV y en recepción y para
        // que el arqueo sepa que no entra en el cajón.
        if ($this->db->table('medios_pago')->where('clave', 'tarjeta_saldo')->countAllResults() === 0) {
            $this->db->table('medios_pago')->insert([
                'clave' => 'tarjeta_saldo', 'nombre' => 'Tarjeta de saldo',
                'tipo' => 'bono', 'afecta_caja' => 0, 'requiere_referencia' => 0,
                'comision_pct' => 0, 'en_recepcion' => 1, 'en_tpv' => 1, 'en_web' => 0,
                'orden' => 6, 'activo' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    private function permisos(): void
    {
        $nuevos = ['tarjetas.ver', 'tarjetas.gestionar', 'tarjetas.cargar', 'tarjetas.cobrar'];

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

        foreach (['recepcion', 'caja', 'restaurante'] as $clave) {
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
        $this->forge->dropTable('tarjeta_movimientos', true);
        $this->forge->dropTable('tarjetas', true);
        $this->forge->dropTable('tipos_tarjeta', true);
    }
}
