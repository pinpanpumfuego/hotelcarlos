<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El paso que faltaba: cocina ha visto la comanda.
 *
 * Hasta ahora una línea iba de «enviada» a «lista» sin nada en medio, así que
 * una comanda que nadie hubiera mirado y una que se estuviera preparando se
 * veían igual. El caso malo es el clásico: el ticket se queda ahí, el cliente
 * espera, y no se nota hasta que pregunta.
 *
 * Se marca por comanda entera, no plato a plato: el cocinero mira el ticket y
 * dice «la veo». Lo que sí es plato a plato es marcar lo que ya está listo.
 */
class LineaRecibida extends Migration
{
    public function up()
    {
        if ($this->existeColumna('recibido')) {
            return;
        }

        $this->forge->addColumn('comanda_lineas', [
            'recibido' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'enviado_cocina',
            ],
            'recibido_en' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'recibido',
            ],
        ]);

        // Lo ya enviado y en marcha se da por recibido: si no, todas las
        // comandas de hoy aparecerían de golpe como «sin ver» en rojo
        $this->db->query('UPDATE comanda_lineas SET recibido = 1, recibido_en = updated_at
                          WHERE enviado_cocina = 1');
    }

    public function down()
    {
        if ($this->existeColumna('recibido')) {
            $this->forge->dropColumn('comanda_lineas', ['recibido', 'recibido_en']);
        }
    }

    private function existeColumna(string $nombre): bool
    {
        foreach ($this->db->getFieldData('comanda_lineas') as $campo) {
            if ($campo->name === $nombre) {
                return true;
            }
        }

        return false;
    }
}
