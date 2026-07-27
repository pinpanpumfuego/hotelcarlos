<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Preventivos;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Abre las órdenes del plan preventivo que ya toquen.
 *
 * Pensado para una tarea programada diaria:
 *
 *   php /home/USUARIO/hotelcarlos/spark mantenimiento:preventivo
 *
 * Correrlo dos veces el mismo día no duplica nada: cada plan comprueba si ya
 * tiene una orden sin cerrar antes de abrir otra. Eso importa porque las tareas
 * programadas de los hostings a veces se disparan más de una vez, y tres
 * revisiones idénticas en el tablero son la forma más rápida de que nadie mire
 * ninguna.
 */
class GenerarPreventivos extends BaseCommand
{
    protected $group       = 'Hotel';
    protected $name        = 'mantenimiento:preventivo';
    protected $description = 'Abre las órdenes de mantenimiento preventivo que ya toquen.';
    protected $usage       = 'mantenimiento:preventivo [fecha]';

    public function run(array $params)
    {
        // La fecha se puede forzar para probar el plan sin esperar meses
        $fecha = $params[0] ?? null;

        if ($fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            CLI::error('La fecha tiene que ir como 2026-08-24.');

            return EXIT_ERROR;
        }

        $r = (new Preventivos())->generar($fecha);

        foreach ($r['detalle'] as $linea) {
            CLI::write('  ' . $linea, str_starts_with($linea, 'Saltado') ? 'dark_gray' : 'green');
        }

        if ($r['abiertas'] === 0 && $r['saltadas'] === 0) {
            CLI::write('No tocaba ninguna revisión.', 'dark_gray');

            return EXIT_SUCCESS;
        }

        CLI::write(sprintf(
            '%d orden(es) abierta(s), %d saltada(s) por tener una sin cerrar.',
            $r['abiertas'],
            $r['saltadas']
        ), 'green');

        return EXIT_SUCCESS;
    }
}
