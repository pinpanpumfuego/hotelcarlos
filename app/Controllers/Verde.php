<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\GestoVerde;
use App\Models\ConfiguracionModel;
use App\Models\GestoVerdeModel;
use RuntimeException;

/**
 * El tablero del gesto verde.
 *
 * Dos cosas en una pantalla, a propósito: **lo que hay que confirmar hoy** y
 * **si el programa sale a cuenta**. Separarlas haría que la segunda no la
 * mirara nadie, y un programa de ahorro que nadie mide se convierte en un
 * gesto de cara a la galería.
 */
class Verde extends BaseController
{
    private GestoVerde $verde;
    private GestoVerdeModel $gestos;

    public function __construct()
    {
        $this->verde  = new GestoVerde();
        $this->gestos = new GestoVerdeModel();
    }

    public function index()
    {
        $desde = (string) ($this->request->getGet('desde') ?: date('Y-m-01'));
        $hasta = (string) ($this->request->getGet('hasta') ?: date('Y-m-d'));

        return view('verde/index', [
            'titulo'      => 'Gesto verde',
            'seccion'     => 'limpieza',
            'activo'      => $this->verde->activo(),
            'pendientes'  => $this->gestos->pendientesDe(date('Y-m-d')),
            'balance'     => $this->verde->balance($desde, $hasta),
            'desde'       => $desde,
            'hasta'       => $hasta,
            'max'         => $this->verde->maxSeguidas(),
            'hora_tope'   => $this->verde->horaTope(),
            'alojados'    => $this->alojadosSinPedir(),
            'estados'     => GestoVerdeModel::ESTADOS,
        ]);
    }

    /** Recepción lo apunta por el huésped: el colgante de la puerta. */
    public function pedir()
    {
        try {
            $this->verde->pedir(
                (int) $this->request->getPost('reserva_id'),
                'recepcion',
                session()->get('usuario_id')
            );
        } catch (RuntimeException $e) {
            return redirect()->to('verde')->with('error', $e->getMessage());
        }

        return redirect()->to('verde')->with('ok', 'Apuntado. La tarea de limpieza de hoy sale marcada.');
    }

    public function confirmar(int $id)
    {
        try {
            $this->verde->confirmar($id, null, session()->get('usuario_id'));
        } catch (RuntimeException $e) {
            return redirect()->to('verde')->with('error', $e->getMessage());
        }

        return redirect()->to('verde')->with('ok', 'Confirmado. El huésped ya tiene su invitación.');
    }

    public function descartar(int $id)
    {
        try {
            $this->verde->descartar($id, (string) $this->request->getPost('motivo'), session()->get('usuario_id'));
        } catch (RuntimeException $e) {
            return redirect()->to('verde')->with('error', $e->getMessage());
        }

        return redirect()->to('verde')->with('ok', 'Anotado: hubo que cambiarla y no hay premio.');
    }

    /**
     * Quién está alojado hoy y todavía no ha pedido nada.
     *
     * Es la lista que usa recepción cuando alguien lo dice de viva voz o deja
     * el colgante puesto.
     */
    private function alojadosSinPedir(): array
    {
        if (! $this->verde->activo()) {
            return [];
        }

        $hoy = date('Y-m-d');

        $reservas = db_connect()->query(
            "SELECT r.id, r.codigo, r.fecha_entrada, r.fecha_salida, r.estado,
                    u.nombre AS unidad, h.nombre AS huesped, h.apellidos
             FROM reservas r
             LEFT JOIN unidades u  ON u.id = r.unidad_id
             LEFT JOIN huespedes h ON h.id = r.huesped_id
             WHERE r.estado = 'checkin' AND ? > r.fecha_entrada AND ? < r.fecha_salida
               AND NOT EXISTS (SELECT 1 FROM gestos_verdes g WHERE g.reserva_id = r.id AND g.fecha = ?)
             ORDER BY u.nombre",
            [$hoy, $hoy, $hoy]
        )->getResultArray();

        // Se filtra por el mismo camino que usa el portal, para que recepción no
        // pueda apuntar lo que al huésped se le habría negado —el tope de
        // noches seguidas, sobre todo.
        return array_values(array_filter(
            $reservas,
            fn (array $r): bool => $this->verde->disponible($r, $hoy)['ok']
        ));
    }
}
