<?php

namespace App\Libraries;

use App\Models\BloqueoModel;
use App\Models\CanalConexionModel;
use App\Models\ReservaModel;
use App\Models\UnidadModel;

/**
 * Trae los calendarios de las plataformas y publica el nuestro.
 *
 * Aviso honesto sobre los límites de esto: las plataformas leen nuestro
 * calendario cada dos o cuatro horas, no al instante. Entre que alguien
 * reserva en Booking y nosotros nos enteramos puede pasar un rato, así que
 * la sobreventa no es imposible: por eso existe la pantalla de conflictos.
 */
class SincronizadorCanales
{
    private CanalConexionModel $conexiones;
    private BloqueoModel $bloqueos;

    public function __construct()
    {
        $this->conexiones = new CanalConexionModel();
        $this->bloqueos   = new BloqueoModel();
    }

    /**
     * Lee todas las conexiones activas.
     *
     * @return array{leidas: int, fallidas: int, eventos: int, detalle: list<string>}
     */
    public function sincronizarTodo(): array
    {
        $leidas   = 0;
        $fallidas = 0;
        $eventos  = 0;
        $detalle  = [];

        foreach ($this->conexiones->paraSincronizar() as $conexion) {
            $r = $this->sincronizar($conexion);

            if ($r['ok']) {
                $leidas++;
                $eventos += $r['eventos'];
            } else {
                $fallidas++;
                $detalle[] = CanalConexionModel::nombreCanal($conexion['canal']) . ': ' . $r['error'];
            }
        }

        return ['leidas' => $leidas, 'fallidas' => $fallidas, 'eventos' => $eventos, 'detalle' => $detalle];
    }

    /**
     * Lee una conexión concreta.
     *
     * @return array{ok: bool, eventos: int, error: string}
     */
    public function sincronizar(array $conexion): array
    {
        $descarga = Ical::descargar((string) $conexion['url_importar']);

        if (! $descarga['ok']) {
            $this->conexiones->update($conexion['id'], [
                'ultimo_error' => mb_substr($descarga['error'], 0, 300),
                'ultima_sync'  => date('Y-m-d H:i:s'),
            ]);

            log_message('warning', 'Fallo al sincronizar el canal {c} de la unidad {u}: {e}', [
                'c' => $conexion['canal'], 'u' => $conexion['unidad_id'], 'e' => $descarga['error'],
            ]);

            return ['ok' => false, 'eventos' => 0, 'error' => $descarga['error']];
        }

        $eventos   = Ical::leer($descarga['contenido']);
        $guardados = $this->bloqueos->reemplazarDeConexion(
            (int) $conexion['id'],
            (int) $conexion['unidad_id'],
            (string) $conexion['canal'],
            $eventos
        );

        $this->conexiones->update($conexion['id'], [
            'ultima_sync'  => date('Y-m-d H:i:s'),
            'ultimo_error' => null,
            'eventos'      => $guardados,
        ]);

        log_message('info', 'Canal {c} sincronizado: {n} bloqueos en la unidad {u}', [
            'c' => $conexion['canal'], 'n' => $guardados, 'u' => $conexion['unidad_id'],
        ]);

        return ['ok' => true, 'eventos' => $guardados, 'error' => ''];
    }

    /**
     * Calendario de una cabaña para que lo lean las plataformas.
     *
     * Incluye las reservas propias y los bloqueos manuales, pero **no** los
     * bloqueos que vinieron de otra plataforma: devolvérselos crearía un eco
     * entre portales y acabaría bloqueando fechas que en realidad están libres.
     */
    public function calendarioDeUnidad(array $unidad): string
    {
        $eventos = [];
        $dominio = parse_url(base_url(), PHP_URL_HOST) ?: 'sanantoniodeloslagos.com';

        $reservas = (new ReservaModel())
            ->select('reservas.id, reservas.codigo, reservas.fecha_entrada, reservas.fecha_salida')
            ->where('unidad_id', $unidad['id'])
            ->whereIn('estado', ReservaModel::ESTADOS_ACTIVOS)
            ->where('fecha_salida >=', date('Y-m-d', strtotime('-1 month')))
            ->orderBy('fecha_entrada')
            ->findAll();

        foreach ($reservas as $r) {
            $eventos[] = [
                'uid'     => 'reserva-' . $r['id'] . '@' . $dominio,
                'desde'   => $r['fecha_entrada'],
                'hasta'   => $r['fecha_salida'],
                // Sin nombres: el calendario lo lee un tercero
                'resumen' => 'Ocupada (' . $r['codigo'] . ')',
            ];
        }

        $manuales = (new BloqueoModel())
            ->where('unidad_id', $unidad['id'])
            ->where('origen', 'manual')
            ->where('fecha_salida >=', date('Y-m-d', strtotime('-1 month')))
            ->findAll();

        foreach ($manuales as $b) {
            $eventos[] = [
                'uid'     => 'bloqueo-' . $b['id'] . '@' . $dominio,
                'desde'   => $b['fecha_entrada'],
                'hasta'   => $b['fecha_salida'],
                'resumen' => $b['resumen'] ?? 'No disponible',
            ];
        }

        return Ical::generar($eventos, $unidad['nombre'] . ' · ' . config('Hotel')->nombre);
    }

    /** Crea el token de la dirección secreta si la cabaña aún no lo tiene. */
    public function asegurarToken(int $unidadId): string
    {
        $unidades = new UnidadModel();
        $unidad   = $unidades->find($unidadId);

        if ($unidad === null) {
            return '';
        }

        if (trim((string) $unidad['token_ical']) !== '') {
            return $unidad['token_ical'];
        }

        $token = bin2hex(random_bytes(24));
        $unidades->update($unidadId, ['token_ical' => $token]);

        return $token;
    }
}
