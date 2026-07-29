<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cartel de «todavía no abrimos» sobre la web pública.
 *
 * **Se enciende de fábrica, al revés que casi todo lo demás.** El hotel está en
 * obras y la web tiene cabañas sin fotos, tarifas sin cargar y un motor de
 * reservas que no puede cobrar porque la pasarela está apagada. Que eso esté
 * abierto al público es peor que no tener web.
 *
 * La clave se siembra en `1234` porque es la que pidió el hotel para poder
 * enseñarlo. Es una cortina, no una cerradura: detrás no hay datos de nadie,
 * solo la web comercial. Se cambia desde Administración.
 */
class CartelObras extends Migration
{
    public function up(): void
    {
        $config     = $this->db->table('configuracion');
        $porDefecto = [
            'obras_activo' => '1',
            'obras_clave'  => password_hash('1234', PASSWORD_DEFAULT),
            'obras_titulo' => 'Estamos terminando de construirlo',
            'obras_texto'  => "Un refugio entre montañas y agua, a un rato de Cali.\n"
                . 'Abriremos pronto y podrás reservar desde aquí.',
            'obras_fecha'  => '',
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
        $this->db->table('configuracion')->like('clave', 'obras_', 'after')->delete();
    }
}
