<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Automatizaciones;
use App\Libraries\Mensajero;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Encola lo que toca hoy y vacía la cola.
 *
 * Pensado para dos tareas programadas distintas, aunque la orden es la misma:
 *
 *   php spark comunicaciones:correr            (una vez al día, de mañana)
 *   php spark comunicaciones:correr --solo-cola (cada hora)
 *
 * Se separa porque las automatizaciones se calculan una vez al día, pero la
 * cola conviene vaciarla más a menudo: un mensaje que se encola a las nueve y
 * sale a las nueve de la mañana siguiente llega tarde.
 *
 * Correrlo de más no duplica nada.
 */
class Comunicaciones extends BaseCommand
{
    protected $group       = 'Hotel';
    protected $name        = 'comunicaciones:correr';
    protected $description = 'Encola los mensajes automáticos del día y manda los que están en cola.';
    protected $usage       = 'comunicaciones:correr [--solo-cola] [--ensayo] [fecha]';

    public function run(array $params)
    {
        $soloCola = array_key_exists('solo-cola', $params) || in_array('--solo-cola', $params, true);
        $ensayo   = array_key_exists('ensayo', $params) || in_array('--ensayo', $params, true);

        $fecha = null;

        foreach ($params as $p) {
            if (is_string($p) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $p) === 1) {
                $fecha = $p;
            }
        }

        // Ensayo: enseña qué saldría sin mandar ni encolar nada
        if ($ensayo) {
            CLI::write('Lo que saldría hoy, sin tocar nada:', 'yellow');

            foreach ((new Automatizaciones())->ensayo($fecha) as $linea) {
                CLI::write(sprintf(
                    '  %-40s %3d  %s',
                    $linea['regla'],
                    $linea['cuantos'],
                    $linea['activa'] ? '' : '(apagada)'
                ), $linea['activa'] ? 'green' : 'dark_gray');
            }

            return EXIT_SUCCESS;
        }

        if (! $soloCola) {
            $r = (new Automatizaciones())->correr($fecha);

            foreach ($r['detalle'] as $linea) {
                CLI::write('  ' . $linea, 'green');
            }

            CLI::write($r['encolados'] . ' mensaje(s) encolado(s).', 'green');
        }

        $envio = (new Mensajero())->procesar();

        CLI::write(sprintf(
            '%d enviado(s), %d fallido(s), %d saltado(s).',
            $envio['enviados'],
            $envio['fallidos'],
            $envio['saltados']
        ), $envio['fallidos'] > 0 ? 'yellow' : 'green');

        if ($envio['saltados'] > 0) {
            CLI::write(
                'Saltados: o se dieron de baja antes de que les tocara, o iban por WhatsApp, que aún no está.',
                'dark_gray'
            );
        }

        return EXIT_SUCCESS;
    }
}
