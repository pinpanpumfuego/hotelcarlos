<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pagos en línea con Wompi.
 *
 * Hasta ahora la web pública tomaba reservas y **no se podía cobrar ninguna**.
 * Cada reserva que entraba era una promesa sin garantía.
 *
 * Dos decisiones de fondo:
 *
 * 1. **Nada se da por pagado por lo que diga el navegador.** El huésped vuelve
 *    de Wompi con un `id` en la dirección, pero eso solo dice «mira esta
 *    transacción», no «está pagada». El estado bueno se pregunta siempre a la
 *    API con nuestras credenciales. Fiarse del navegador es dejar la caja
 *    abierta.
 * 2. **El intento se guarda antes de mandar a nadie a pagar.** Si el huésped
 *    paga y se le cae el móvil antes de volver, el webhook llega igual y
 *    encuentra a quién apuntárselo. Sin registro previo, ese pago se pierde.
 */
class Pagos extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('pagos_online')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],

                // Nuestra referencia, única y sin datos dentro. Va en la URL de
                // la pasarela, así que no puede llevar nada del huésped.
                'referencia' => ['type' => 'VARCHAR', 'constraint' => 60],

                'pasarela'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'wompi'],
                'ambiente'   => ['type' => 'ENUM', 'constraint' => ['pruebas', 'produccion'], 'default' => 'pruebas'],

                // A qué se aplica: una reserva, un folio, una comanda o un bono
                'concepto_tipo' => ['type' => 'VARCHAR', 'constraint' => 20],
                'concepto_id'   => ['type' => 'INT', 'unsigned' => true],

                'valor'    => ['type' => 'DECIMAL', 'constraint' => '14,2'],
                'moneda'   => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'COP'],

                'estado' => [
                    'type'       => 'ENUM',
                    // Los cinco de Wompi más `creado`, que es antes de que la
                    // pasarela sepa siquiera que existe.
                    'constraint' => ['creado', 'PENDING', 'APPROVED', 'DECLINED', 'VOIDED', 'ERROR'],
                    'default'    => 'creado',
                ],

                // El id que da la pasarela. Se rellena al volver o al recibir el
                // aviso, no al crear.
                'transaccion_id' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'metodo'         => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],

                'email'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'telefono'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],

                // La respuesta tal cual, ya depurada. Para poder explicar un
                // cobro dudoso seis meses después.
                'respuesta'  => ['type' => 'JSON', 'null' => true],

                // Cuándo se apuntó en el folio. Sin esto, dos avisos seguidos
                // de la pasarela cobrarían dos veces.
                'aplicado_en' => ['type' => 'DATETIME', 'null' => true],
                'pagado_en'   => ['type' => 'DATETIME', 'null' => true],
                'expira_en'   => ['type' => 'DATETIME', 'null' => true],

                'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('referencia');
            // Un mismo id de transacción no se puede procesar dos veces
            $this->forge->addUniqueKey('transaccion_id');
            $this->forge->addKey(['concepto_tipo', 'concepto_id']);
            $this->forge->addKey(['estado', 'created_at']);
            $this->forge->createTable('pagos_online');
        }

        // Cuánto hay que pagar por adelantado para que una reserva de la web
        // valga. En 0, se sigue reservando sin pagar, como hasta ahora.
        $config = $this->db->table('configuracion');
        foreach ([
            'wompi_anticipo_pct' => '0',
            'wompi_activo'       => '0',
        ] as $clave => $valor) {
            if ($config->where('clave', $clave)->countAllResults(false) === 0) {
                $config->resetQuery();
                $config->insert(['clave' => $clave, 'valor' => $valor]);
            }
            $config->resetQuery();
        }
    }

    public function down()
    {
        if ($this->db->tableExists('pagos_online')) {
            $this->forge->dropTable('pagos_online');
        }
    }
}
