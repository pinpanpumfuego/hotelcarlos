<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Niveles, beneficios y referidos.
 *
 * **Los niveles no se guardan en el huésped: se calculan.** Guardar «esta
 * persona es nivel 3» significa que el día que se cambie el umbral, o que se
 * anule una reserva vieja, media base de datos queda mintiendo y nadie sabe
 * cuál. Se calcula a partir de sus estancias y su gasto, que son datos reales,
 * cada vez que hace falta.
 *
 * Lo que sí se guarda son **los umbrales y los beneficios**, porque eso es una
 * decisión de gerencia y tiene que poder cambiarse sin tocar código.
 *
 * Los beneficios se guardan como texto y no como reglas ejecutables a
 * propósito. «Late checkout si hay hueco» o «una botella de vino en la primera
 * noche» no son cosas que un programa pueda hacer solo: las hace una persona.
 * Fingir que el sistema las aplica sería peor que decir claramente que hay que
 * mirarlas.
 */
class Fidelizacion extends Migration
{
    public function up()
    {
        $this->niveles();
        $this->codigosDeReferido();
        $this->referidos();
        $this->sembrar();
        $this->permisos();
    }

    private function niveles(): void
    {
        if ($this->db->tableExists('niveles')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'clave'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 60],

            // De menor a mayor. El primero que cumpla, contando desde arriba.
            'orden' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],

            // Basta con cumplir UNO de los dos. Alguien que vino dos veces y
            // gastó mucho, y alguien que vino seis veces gastando poco, los dos
            // son buenos clientes por razones distintas.
            'estancias_min' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'gasto_min'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            'color' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'secondary'],

            // Una línea por beneficio. Texto, porque los hace una persona.
            'beneficios' => ['type' => 'TEXT', 'null' => true],

            // Si gerencia quiere que además lleve descuento. Se ENSEÑA, no se
            // aplica solo: un descuento automático es dinero que se va sin que
            // nadie lo haya decidido esa vez.
            'descuento_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],

            'activo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('niveles');
    }

    private function codigosDeReferido(): void
    {
        $columnas = array_column($this->db->getFieldData('huespedes'), 'name');

        if (in_array('codigo_referido', $columnas, true)) {
            return;
        }

        $this->forge->addColumn('huespedes', [
            // Corto y sin letras que se confundan al dictarlo por teléfono.
            // Se genera cuando hace falta, no para todo el mundo: la mayoría no
            // va a recomendar a nadie y llenar la tabla de códigos muertos no
            // ayuda.
            'codigo_referido' => [
                'type' => 'VARCHAR', 'constraint' => 12, 'null' => true, 'after' => 'origen',
            ],
        ]);

        $this->db->query('CREATE UNIQUE INDEX `idx_huespedes_referido` ON `huespedes` (`codigo_referido`)');
    }

    private function referidos(): void
    {
        if ($this->db->tableExists('referidos')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'referidor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'referido_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'reserva_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            'codigo_usado' => ['type' => 'VARCHAR', 'constraint' => 12],

            // El premio no se da al reservar sino al SALIR. Si se diera al
            // reservar, una reserva cancelada dejaría dos cupones regalados por
            // una estancia que no ocurrió.
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['pendiente', 'cumplido', 'anulado'],
                'default'    => 'pendiente',
            ],

            'cupon_referidor' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'cupon_referido'  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'cumplido_en'     => ['type' => 'DATETIME', 'null' => true],
            'nota'            => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('referidor_id');
        // Una reserva solo puede venir de una recomendación
        $this->forge->addUniqueKey('reserva_id');
        $this->forge->createTable('referidos');
    }

    /**
     * Cuatro niveles de partida.
     *
     * Los nombres y los beneficios son un punto de partida para que se vea
     * funcionando; están pensados para cambiarse desde el panel en cuanto el
     * cliente decida qué quiere regalar de verdad. Los umbrales sí son
     * razonables para un alojamiento de siete cabañas: con cuatro niveles y
     * cincuenta estancias al año, pedir veinte para el último sería un nivel
     * que no alcanzaría nadie.
     */
    private function sembrar(): void
    {
        if ($this->db->table('niveles')->countAllResults() > 0) {
            return;
        }

        $ahora   = date('Y-m-d H:i:s');
        $niveles = [
            [
                'clave' => 'visitante', 'nombre' => 'Visitante', 'orden' => 0,
                'estancias_min' => 0, 'gasto_min' => 0, 'color' => 'secondary',
                'descuento_pct' => 0,
                'beneficios' => "Bienvenida con café de la zona\nMapa de senderos y guía de aves",
            ],
            [
                'clave' => 'amigo', 'nombre' => 'Amigo del lago', 'orden' => 1,
                'estancias_min' => 2, 'gasto_min' => 2000000, 'color' => 'info',
                'descuento_pct' => 5,
                'beneficios' => "Salida tardía hasta las 14:00 si hay hueco\n"
                    . "Elección de cabaña entre las libres\n"
                    . "Café de bienvenida para llevarse a casa",
            ],
            [
                'clave' => 'habitual', 'nombre' => 'Habitual', 'orden' => 2,
                'estancias_min' => 5, 'gasto_min' => 5000000, 'color' => 'primary',
                'descuento_pct' => 10,
                'beneficios' => "Todo lo anterior\n"
                    . "Entrada anticipada desde las 12:00 si hay hueco\n"
                    . "Una salida de avistamiento al año sin coste\n"
                    . "Su cabaña preferida reservada siempre que se pueda",
            ],
            [
                'clave' => 'anfitrion', 'nombre' => 'De la casa', 'orden' => 3,
                'estancias_min' => 10, 'gasto_min' => 12000000, 'color' => 'warning',
                'descuento_pct' => 15,
                'beneficios' => "Todo lo anterior\n"
                    . "Cambio de cabaña sin coste si hay una mejor libre\n"
                    . "Cancelación flexible hasta 48 horas antes\n"
                    . "Cena para dos en su cumpleaños si viene esa semana",
            ],
        ];

        foreach ($niveles as $n) {
            $this->db->table('niveles')->insert($n + ['activo' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }

        // Qué se regala por recomendar. En 0 el módulo funciona igual pero no
        // reparte nada: el programa de referidos queda apagado hasta que
        // gerencia decida cuánto vale traer a alguien.
        $pares = [
            'referido_premio_pct'       => '10',
            'referido_premio_dias'      => '365',
            'referido_activo'           => '0',
        ];

        foreach ($pares as $clave => $valor) {
            if ($this->db->table('configuracion')->where('clave', $clave)->countAllResults() === 0) {
                $this->db->table('configuracion')->insert(['clave' => $clave, 'valor' => $valor]);
            }
        }
    }

    private function permisos(): void
    {
        $nuevos = ['niveles.gestionar'];

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
    }

    public function down()
    {
        $this->forge->dropTable('referidos', true);
        $this->forge->dropTable('niveles', true);
    }
}
