<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use App\Models\EmpleadoModel;
use App\Models\FichajeModel;

/**
 * Registrar un fichaje: la parte que comparten el terminal y el móvil.
 *
 * La foto disuade, no demuestra. Sirve para que nadie fiche por un compañero
 * a la ligera, pero no es un control biométrico: quien quiera hacer trampa
 * encontrará la manera. Por eso el valor está en el registro completo
 * (hora, origen, IP, ubicación) y en que nada se pueda borrar sin dejar rastro.
 */
class Fichaje
{
    private FichajeModel $fichajes;
    private EmpleadoModel $empleados;

    public function __construct()
    {
        $this->fichajes  = new FichajeModel();
        $this->empleados = new EmpleadoModel();
    }

    /**
     * Registra un fichaje.
     *
     * @param array $datos tipo, origen, foto (base64 opcional), latitud, longitud, precision_m
     *
     * @return array{ok: bool, mensaje: string, fichaje: array|null}
     */
    public function marcar(array $empleado, array $datos): array
    {
        $tipo = (string) ($datos['tipo'] ?? '');
        if (! array_key_exists($tipo, FichajeModel::TIPOS)) {
            return ['ok' => false, 'mensaje' => 'Tipo de fichaje no válido.', 'fichaje' => null];
        }

        $empleadoId = (int) $empleado['id'];
        $posibles   = $this->fichajes->accionesPosibles($empleadoId);

        if (! in_array($tipo, $posibles, true)) {
            $estado = $this->fichajes->estado($empleadoId);
            $frase  = match ($estado) {
                'fuera' => 'Ahora mismo figuras fuera: lo que toca es marcar entrada.',
                'pausa' => 'Estás en pausa: primero vuelve de la pausa o marca la salida.',
                default => 'Ya estás dentro: lo que toca es una pausa o la salida.',
            };

            return ['ok' => false, 'mensaje' => $frase, 'fichaje' => null];
        }

        // Dos marcas seguidas en menos de un minuto casi siempre son un doble toque
        $ultimo = $this->fichajes->ultimo($empleadoId);
        if ($ultimo !== null && (time() - strtotime($ultimo['marcado_en'])) < 60) {
            return [
                'ok'      => false,
                'mensaje' => 'Acabas de fichar hace unos segundos. Espera un momento.',
                'fichaje' => null,
            ];
        }

        $origen = in_array($datos['origen'] ?? '', ['terminal', 'movil', 'manual'], true)
            ? $datos['origen'] : 'terminal';

        $foto = null;
        if (! empty($datos['foto'])) {
            $foto = $this->guardarFoto((string) $datos['foto'], $empleadoId);
        }

        $distancia = null;
        if (isset($datos['latitud'], $datos['longitud']) && $datos['latitud'] !== null && $datos['longitud'] !== null) {
            $distancia = $this->distanciaAlHotel((float) $datos['latitud'], (float) $datos['longitud']);
        }

        $id = $this->fichajes->insert([
            'empleado_id' => $empleadoId,
            'tipo'        => $tipo,
            'marcado_en'  => date('Y-m-d H:i:s'),
            'origen'      => $origen,
            'foto'        => $foto,
            'latitud'     => $datos['latitud'] ?? null,
            'longitud'    => $datos['longitud'] ?? null,
            'precision_m' => isset($datos['precision_m']) ? (int) $datos['precision_m'] : null,
            'distancia_m' => $distancia,
            'ip'          => service('request')->getIPAddress(),
            'observacion' => isset($datos['observacion']) ? mb_substr(trim((string) $datos['observacion']), 0, 200) : null,
        ]);

        $fichaje = $this->fichajes->find($id);

        log_message('info', 'Fichaje {tipo} de empleado {e} desde {o}', [
            'tipo' => $tipo, 'e' => $empleadoId, 'o' => $origen,
        ]);

        return [
            'ok'      => true,
            'mensaje' => $this->saludo($empleado, $tipo),
            'fichaje' => $fichaje,
        ];
    }

    /** Mensaje humano de confirmación: el trabajador debe ver que quedó registrado. */
    private function saludo(array $empleado, string $tipo): string
    {
        $hora   = date('H:i');
        $nombre = $empleado['nombre'];

        return match ($tipo) {
            'entrada'      => "Buen turno, {$nombre}. Entrada registrada a las {$hora}.",
            'salida'       => "Hasta luego, {$nombre}. Salida registrada a las {$hora}.",
            'pausa_inicio' => "Pausa iniciada a las {$hora}. Que aproveche, {$nombre}.",
            default        => "De vuelta a las {$hora}. Gracias, {$nombre}.",
        };
    }

    /**
     * Guarda la foto que envía el navegador en base64.
     * Va a writable/, fuera del alcance del navegador: es un dato personal.
     */
    private function guardarFoto(string $base64, int $empleadoId): ?string
    {
        if (! preg_match('~^data:image/(jpeg|png|webp);base64,~', $base64, $m)) {
            return null;
        }

        $datos = base64_decode(substr($base64, strpos($base64, ',') + 1), true);
        if ($datos === false || strlen($datos) < 500 || strlen($datos) > 3 * 1024 * 1024) {
            return null;
        }

        $carpeta = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'fichajes' . DIRECTORY_SEPARATOR;
        if (! is_dir($carpeta) && ! @mkdir($carpeta, 0755, true) && ! is_dir($carpeta)) {
            log_message('error', 'No se pudo crear la carpeta de fotos de fichaje.');

            return null;
        }

        $nombre = date('Ymd_His') . '_' . $empleadoId . '_' . bin2hex(random_bytes(4)) . '.jpg';

        return file_put_contents($carpeta . $nombre, $datos) !== false ? $nombre : null;
    }

    /** Metros entre la marca y el hotel, si están configuradas sus coordenadas. */
    private function distanciaAlHotel(float $lat, float $lon): ?int
    {
        $config = new ConfiguracionModel();
        $latHotel = (float) $config->obtener('hotel_latitud', '0');
        $lonHotel = (float) $config->obtener('hotel_longitud', '0');

        if ($latHotel === 0.0 || $lonHotel === 0.0) {
            return null;
        }

        // Fórmula del semiverseno (haversine)
        $radio = 6371000;
        $dLat  = deg2rad($lat - $latHotel);
        $dLon  = deg2rad($lon - $lonHotel);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($latHotel)) * cos(deg2rad($lat)) * sin($dLon / 2) ** 2;

        return (int) round($radio * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /** Radio permitido para fichar desde el móvil, en metros (0 = sin límite). */
    public static function radioPermitido(): int
    {
        return (int) (new ConfiguracionModel())->obtener('fichaje_radio_m', '0');
    }
}
