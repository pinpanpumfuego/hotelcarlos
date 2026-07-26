<?php

namespace App\Commands;

use App\Libraries\SincronizadorCanales;
use App\Models\BloqueoModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Lee los calendarios de los portales.
 *
 * Pensado para una tarea programada cada hora:
 *   C:\xamppum\php\php.exe C:\xamppum\htdocs\hotelcarlos\spark canales:sincronizar
 */
class SincronizarCanales extends BaseCommand
{
    protected $group       = 'Hotel';
    protected $name        = 'canales:sincronizar';
    protected $description = 'Trae las fechas ocupadas de Booking, Airbnb y demás portales.';

    public function run(array $params)
    {
        $inicio = microtime(true);
        $r      = (new SincronizadorCanales())->sincronizarTodo();

        if ($r['leidas'] === 0 && $r['fallidas'] === 0) {
            CLI::write('No hay conexiones activas con dirección puesta.', 'yellow');

            return;
        }

        CLI::write(sprintf(
            '%d calendario(s) leído(s), %d fecha(s) ocupada(s), %d fallo(s) en %.1f s',
            $r['leidas'],
            $r['eventos'],
            $r['fallidas'],
            microtime(true) - $inicio
        ), $r['fallidas'] > 0 ? 'yellow' : 'green');

        foreach ($r['detalle'] as $linea) {
            CLI::error('  ' . $linea);
        }

        // La sobreventa hay que gritarla: es lo único que no puede esperar
        $conflictos = (new BloqueoModel())->conflictos();

        if ($conflictos !== []) {
            CLI::newLine();
            CLI::error('¡ATENCIÓN! ' . count($conflictos) . ' posible(s) sobreventa:');
            foreach ($conflictos as $c) {
                CLI::write(sprintf(
                    '  %s: %s (%s a %s) contra %s',
                    $c['unidad_nombre'],
                    $c['codigo'],
                    date('d/m', strtotime($c['fecha_entrada'])),
                    date('d/m', strtotime($c['fecha_salida'])),
                    $c['canal']
                ), 'red');
            }
            CLI::write('  Revísalo en el panel: Gerencia → Portales y canales', 'yellow');
        }
    }
}
