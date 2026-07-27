<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\AuditoriaModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Borra la auditoría más antigua de lo que hace falta conservar.
 *
 * Pensado para una tarea programada mensual:
 *
 *   php /home/USUARIO/hotelcarlos/spark auditoria:purgar
 *
 * No guardar de más también es parte de tratar bien los datos de las personas
 * (Ley 1581/2012). Dos años cubre de sobra una revisión contable.
 */
class PurgarAuditoria extends BaseCommand
{
    protected $group       = 'Hotel';
    protected $name        = 'auditoria:purgar';
    protected $description = 'Borra los registros de auditoría más antiguos que el plazo de conservación.';
    protected $usage       = 'auditoria:purgar [--dias N] [--simular]';
    protected $options     = [
        '--dias'    => 'Días a conservar. Por defecto, ' . AuditoriaModel::DIAS_CONSERVACION . '.',
        '--simular' => 'Dice cuántos borraría, sin borrar nada.',
    ];

    public function run(array $params)
    {
        $modelo = new AuditoriaModel();
        $dias   = (int) ($params['dias'] ?? CLI::getOption('dias') ?? AuditoriaModel::DIAS_CONSERVACION);

        if ($dias < 30) {
            CLI::error('Conservar menos de 30 días no tiene sentido: se perdería el rastro del mes en curso.');

            return EXIT_ERROR;
        }

        $limite = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days'));
        $viejos = $modelo->where('created_at <', $limite)->countAllResults();

        if ($viejos === 0) {
            CLI::write('No hay nada más viejo de ' . $dias . ' días.', 'green');

            return EXIT_SUCCESS;
        }

        if (array_key_exists('simular', $params) || CLI::getOption('simular')) {
            CLI::write(sprintf('Se borrarían %d registro(s) anteriores al %s.', $viejos, substr($limite, 0, 10)), 'yellow');

            return EXIT_SUCCESS;
        }

        $borrados = $modelo->purgar($dias);

        CLI::write(sprintf('%d registro(s) de auditoría borrados. Se conservan los últimos %d días.', $borrados, $dias), 'green');

        return EXIT_SUCCESS;
    }
}
