<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gesto verde: el huésped renuncia al cambio de lencería y se lleva algo.
 *
 * El hotel se ahorra una lavada —agua, energía, detergente y el rato de una
 * persona— y a cambio invita a una consumición. La cuenta sale si la bebida
 * cuesta menos que la lavada, y por eso aquí se guardan las dos cosas: cuántas
 * lavadas se evitaron y cuánto se regaló. Un programa así sin ese número es un
 * gesto de cara a la galería.
 *
 * **Tres reglas que no son un detalle, son el programa:**
 *
 * 1. **Ni la noche de llegada ni la de salida.** El día que se va, la ropa se
 *    lava igual; premiar esa noche es pagar una bebida por un ahorro que no
 *    existe. Y el día que llega no hay nada que cambiar.
 * 2. **Un suelo de higiene.** Como mucho tres noches seguidas sin cambio, y es
 *    configurable. Una estancia de diez noches no puede pasar diez noches con
 *    las mismas sábanas porque alguien quiera diez refrescos.
 * 3. **El vale nace cuando housekeeping confirma** que de verdad no cambió
 *    nada. Si naciera al pedirlo, se pagaría por ahorros que no ocurrieron —y
 *    no es un riesgo teórico: quien lo pide por la mañana pide toallas por la
 *    tarde.
 */
class GestoVerde extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('gestos_verdes')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'reserva_id' => ['type' => 'INT', 'unsigned' => true],
                'unidad_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],

                // La noche a la que se refiere, no el día en que se pidió: es lo
                // que permite contar noches seguidas y no premiar dos veces.
                'fecha' => ['type' => 'DATE'],

                'origen' => [
                    'type'       => 'ENUM',
                    'constraint' => ['portal', 'recepcion'],
                    'default'    => 'portal',
                ],

                'estado' => [
                    'type' => 'ENUM',
                    // pedido → lo dijo el huésped. confirmado → housekeeping da
                    // fe de que no cambió nada, y ahí nace el vale. descartado →
                    // hubo que cambiar igual (una mancha, una sábana rota).
                    // canjeado → ya se tomó la consumición.
                    'constraint' => ['pedido', 'confirmado', 'descartado', 'canjeado'],
                    'default'    => 'pedido',
                ],

                'limpieza_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'confirmo_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'confirmado_en'  => ['type' => 'DATETIME', 'null' => true],
                'motivo'         => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

                'comanda_linea_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'canjeado_en'      => ['type' => 'DATETIME', 'null' => true],

                'usuario_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);

            $this->forge->addKey('id', true);
            // Una noche, un gesto. Que lo impida la base de datos y no solo el
            // código: por el portal y por recepción se llega a lo mismo.
            $this->forge->addUniqueKey(['reserva_id', 'fecha']);
            $this->forge->addKey(['estado', 'fecha']);
            $this->forge->addForeignKey('reserva_id', 'reservas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('gestos_verdes');
        }

        // Marca en la tarea de limpieza del día. Sin esto el programa es papel
        // mojado: quien limpia entra y cambia las toallas como cada día.
        $columnas = array_column($this->db->getFieldData('limpiezas'), 'name');

        if (! in_array('sin_lenceria', $columnas, true)) {
            $this->forge->addColumn('limpiezas', [
                'sin_lenceria' => [
                    'type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'para_hoy',
                ],
            ]);
        }

        $config     = $this->db->table('configuracion');
        $porDefecto = [
            // Apagado de fábrica: el premio y su coste los decide el hotel.
            'verde_activo'       => '0',
            // Categoría de la carta entre la que puede elegir. Sin alcohol por
            // defecto, y eso lo controla quién entra en la categoría.
            'verde_categoria_id' => '',
            // Housekeeping arranca a media mañana: pedirlo después ya no sirve.
            'verde_hora_tope'    => '10:00',
            'verde_max_seguidas' => '3',
            // Agua, energía, detergente y mano de obra de una lavada. Lo sabe el
            // hotel; en 0 el informe dice honestamente que no puede calcularlo.
            'verde_coste_lavada' => '0',
            'verde_texto'        => 'Si esta noche no necesitas toallas ni sábanas limpias, '
                . 'nos ahorras una lavada entera y te invitamos a algo.',
        ];

        foreach ($porDefecto as $clave => $valor) {
            if ($config->where('clave', $clave)->countAllResults(false) === 0) {
                $config->resetQuery();
                $config->insert(['clave' => $clave, 'valor' => $valor]);
            }

            $config->resetQuery();
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('gestos_verdes', true);

        if (in_array('sin_lenceria', array_column($this->db->getFieldData('limpiezas'), 'name'), true)) {
            $this->forge->dropColumn('limpiezas', 'sin_lenceria');
        }

        $this->db->table('configuracion')->like('clave', 'verde_', 'after')->delete();
    }
}
