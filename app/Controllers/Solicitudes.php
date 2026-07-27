<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EncuestaModel;
use App\Models\SolicitudModel;

/**
 * Lo que piden los huéspedes desde su móvil, visto desde recepción.
 *
 * Sin esta pantalla el portal sería un buzón sin cartero: el huésped pide
 * toallas y la petición se queda en una tabla que nadie mira. Va junto a las
 * encuestas porque son la misma conversación vista por los dos lados.
 */
class Solicitudes extends BaseController
{
    private SolicitudModel $solicitudes;

    public function __construct()
    {
        $this->solicitudes = new SolicitudModel();
    }

    public function index()
    {
        $filtros = [
            'estado' => $this->request->getGet('estado'),
            'tipo'   => $this->request->getGet('tipo'),
            'desde'  => $this->request->getGet('desde'),
            'hasta'  => $this->request->getGet('hasta'),
        ];

        // Sin filtros, lo que hay que atender ahora. Es lo que se mira 9 de
        // cada 10 veces que se abre esta pantalla.
        $sinFiltrar = array_filter($filtros) === [];

        return view('solicitudes/index', [
            'titulo'      => 'Peticiones de huéspedes',
            'seccion'     => 'solicitudes',
            'filtros'     => $filtros,
            'solicitudes' => $sinFiltrar ? $this->solicitudes->pendientes() : $this->solicitudes->buscar($filtros)->findAll(200),
            'sinFiltrar'  => $sinFiltrar,
            'tipos'       => SolicitudModel::TIPOS,
            'encuestas'   => (new EncuestaModel())->requierenAtencion(),
            'umbral'      => EncuestaModel::UMBRAL_ATENCION,
        ]);
    }

    public function atender(int $id)
    {
        $estado = (string) $this->request->getPost('estado');

        $ok = $this->solicitudes->atender(
            $id,
            $estado,
            mb_substr(trim((string) $this->request->getPost('respuesta')), 0, 400) ?: null,
            (int) session()->get('usuario_id')
        );

        return redirect()->to('solicitudes')->with(
            $ok ? 'ok' : 'error',
            $ok ? 'Petición actualizada. El huésped lo ve en su móvil.' : 'No se pudo actualizar la petición.'
        );
    }

    /** Marcar una encuesta como leída, para que deje de pedir atención. */
    public function leerEncuesta(int $id)
    {
        (new EncuestaModel())->marcarLeida($id);

        return redirect()->to('solicitudes')->with('ok', 'Encuesta marcada como leída.');
    }
}
