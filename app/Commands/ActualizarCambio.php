<?php

namespace App\Commands;

use App\Libraries\Monedas;
use App\Models\TipoCambioModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Trae el tipo de cambio del peso una vez al día.
 *
 * Pensado para una tarea programada:
 *
 *   php /home/USUARIO/hotelcarlos/spark cambio:actualizar
 *
 * Si el servicio no contesta **no pasa nada**: se conserva el último cambio
 * conocido y la web sigue funcionando. Solo cuando no hay ninguno se dejan de
 * enseñar las conversiones, que es lo correcto: mejor solo pesos que una cifra
 * inventada.
 */
class ActualizarCambio extends BaseCommand
{
    protected $group       = 'Hotel';
    protected $name        = 'cambio:actualizar';
    protected $description = 'Trae el tipo de cambio del peso colombiano frente al dólar y el euro.';

    /** Servicio gratuito y sin registro. Devuelve cuántas divisas vale 1 COP. */
    private const SERVICIO = 'https://open.er-api.com/v6/latest/COP';

    /** Un cambio absurdo delata un error del servicio: mejor no guardarlo. */
    private const PESOS_MINIMO = 500;
    private const PESOS_MAXIMO = 20000;

    public function run(array $params)
    {
        $datos = $this->consultar();

        if ($datos === null) {
            CLI::error('No se pudo consultar el cambio. Se conserva el último conocido.');

            return EXIT_ERROR;
        }

        $modelo    = new TipoCambioModel();
        $guardados = 0;

        foreach (array_keys(Monedas::MONEDAS) as $moneda) {
            if ($moneda === Monedas::ORIGINAL) {
                continue;
            }

            $porPeso = $datos[$moneda] ?? null;
            if ($porPeso === null || $porPeso <= 0) {
                CLI::write('Sin dato para ' . $moneda, 'yellow');

                continue;
            }

            // El servicio da divisas por peso; se guarda pesos por divisa,
            // que es la cifra que una persona reconoce (unos 4.000 por dólar)
            $pesos = 1 / $porPeso;

            if ($pesos < self::PESOS_MINIMO || $pesos > self::PESOS_MAXIMO) {
                CLI::error(sprintf('Cambio inverosímil para %s (%.2f). No se guarda.', $moneda, $pesos));

                continue;
            }

            if ($modelo->guardar($moneda, round($pesos, 4))) {
                CLI::write(sprintf('%s: %s pesos', $moneda, number_format($pesos, 2, ',', '.')), 'green');
                $guardados++;
            } else {
                CLI::write($moneda . ': lo mantiene gerencia a mano, no se toca', 'dark_gray');
            }
        }

        CLI::write($guardados . ' cambio(s) actualizado(s).', 'green');

        return EXIT_SUCCESS;
    }

    /** @return array<string,float>|null divisas por 1 peso, o null si falla */
    private function consultar(): ?array
    {
        try {
            $respuesta = service('curlrequest')->get(self::SERVICIO, [
                // Corto a propósito: esto corre en una tarea programada y no
                // debe quedarse colgado si el servicio va lento
                'timeout'     => 12,
                'http_errors' => false,
            ]);

            if ($respuesta->getStatusCode() !== 200) {
                return null;
            }

            $cuerpo = json_decode((string) $respuesta->getBody(), true);

            if (! is_array($cuerpo) || ($cuerpo['result'] ?? '') !== 'success' || empty($cuerpo['rates'])) {
                return null;
            }

            return $cuerpo['rates'];
        } catch (\Throwable $e) {
            log_message('error', 'Cambio de divisas: {m}', ['m' => $e->getMessage()]);

            return null;
        }
    }
}
