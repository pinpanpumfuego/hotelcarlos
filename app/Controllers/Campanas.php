<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Mensajero;
use App\Libraries\Segmentos;
use App\Models\CampanaModel;
use App\Models\PlantillaModel;

/**
 * Campañas a un grupo de huéspedes.
 *
 * La pantalla obliga a mirar antes de mandar: se ve a cuántos afecta y quiénes
 * son. Es a propósito. Un botón que manda a media base de datos sin enseñar
 * primero a quién es un botón que alguien va a pulsar sin querer.
 */
class Campanas extends BaseController
{
    private CampanaModel $campanas;
    private Segmentos $segmentos;

    public function __construct()
    {
        $this->campanas  = new CampanaModel();
        $this->segmentos = new Segmentos();
    }

    public function index()
    {
        return view('campanas/index', [
            'titulo'   => 'Campañas',
            'seccion'  => 'huespedes',
            'campanas' => $this->campanas->listar(),
            'segmentos' => $this->segmentos,
        ]);
    }

    /** El armador: filtros a la izquierda, a quién le llega a la derecha. */
    public function nueva()
    {
        $filtros = $this->segmentos->limpiar((array) $this->request->getGet());

        return view('campanas/nueva', [
            'titulo'     => 'Nueva campaña',
            'seccion'    => 'huespedes',
            'filtros'    => $filtros,
            'cuantos'    => $this->segmentos->contar($filtros),
            'muestra'    => $this->segmentos->buscar($filtros, 25),
            'descripcion' => $this->segmentos->describir($filtros),
            'etiquetas'  => Segmentos::FILTROS,
            'valores'    => $this->segmentos->valoresDisponibles(),
            // Solo lo que necesita permiso: mandar un aviso operativo a mano a
            // media base de datos no es una campaña, es un error.
            'plantillas' => (new PlantillaModel())
                ->whereIn('finalidad', ['marketing', 'encuestas'])
                ->where('activa', 1)->where('idioma', 'es')
                ->orderBy('nombre')->findAll(),
        ]);
    }

    public function guardar()
    {
        $filtros = $this->segmentos->limpiar((array) $this->request->getPost());

        $datos = [
            'nombre'          => trim((string) $this->request->getPost('nombre')),
            'plantilla_clave' => (string) $this->request->getPost('plantilla_clave'),
            'canal'           => 'email',
            'filtros'         => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            'estado'          => 'borrador',
            'usuario_id'      => session()->get('usuario_id'),
        ];

        if (! $this->campanas->insert($datos)) {
            return redirect()->back()->withInput()->with('errores', $this->campanas->errors());
        }

        return redirect()->to('campanas/ver/' . $this->campanas->getInsertID())
            ->with('ok', 'Campaña guardada como borrador. Míralo bien antes de mandarla.');
    }

    public function ver(int $id)
    {
        $campana = $this->campanas->find($id);

        if ($campana === null) {
            return redirect()->to('campanas')->with('error', 'Esa campaña no existe.');
        }

        $filtros   = $this->campanas->filtrosDe($campana);
        $plantilla = (new PlantillaModel())->buscar($campana['plantilla_clave'], 'es', $campana['canal']);

        return view('campanas/ver', [
            'titulo'      => $campana['nombre'],
            'seccion'     => 'huespedes',
            'campana'     => $campana,
            'plantilla'   => $plantilla,
            'filtros'     => $filtros,
            'descripcion' => $this->segmentos->describir($filtros),
            // Cuántos cumplen los filtros HOY, no cuando se guardó
            'cuantos'     => $this->segmentos->contar($filtros),
            'muestra'     => $this->segmentos->buscar($filtros, 50),
            'con_permiso' => $this->conPermiso($filtros, $plantilla),
        ]);
    }

    /**
     * De los que cumplen los filtros, a cuántos se les puede escribir de verdad.
     *
     * La diferencia entre los dos números es lo más honesto de esta pantalla:
     * enseña que un segmento de 300 personas puede ser una campaña de 40.
     */
    private function conPermiso(array $filtros, ?array $plantilla): int
    {
        if ($plantilla === null) {
            return 0;
        }

        $permitidos = (new \App\Models\ConsentimientoModel())
            ->huespedesQuePermiten($plantilla['finalidad'], 'email');

        if ($permitidos === []) {
            return 0;
        }

        $ids = array_map(
            static fn (array $h): int => (int) $h['id'],
            $this->segmentos->buscar($filtros, 5000)
        );

        return count(array_intersect($ids, $permitidos));
    }

    /**
     * Encola la campaña.
     *
     * No manda: encola. Lo que sale de la cola lo decide `Mensajero`, que
     * vuelve a mirar el consentimiento de cada uno justo antes.
     */
    public function enviar(int $id)
    {
        $campana = $this->campanas->find($id);

        if ($campana === null || $campana['estado'] !== 'borrador') {
            return redirect()->to('campanas')->with('error', 'Esa campaña ya no se puede mandar.');
        }

        // Confirmación escrita: un clic de más antes de escribirle a media base
        // de datos es barato comparado con el correo que no se puede recoger.
        if (trim((string) $this->request->getPost('confirmar')) !== 'ENVIAR') {
            return redirect()->to('campanas/ver/' . $id)
                ->with('error', 'Para mandarla, escribe ENVIAR en el recuadro.');
        }

        $filtros   = $this->campanas->filtrosDe($campana);
        $mensajero = new Mensajero();
        $encolados = 0;
        $saltados  = 0;

        foreach ($this->segmentos->buscar($filtros, 5000) as $huesped) {
            $envio = $mensajero->encolar((int) $huesped['id'], $campana['plantilla_clave'], [
                'canal' => $campana['canal'],
                // Una campaña no va contra una reserva concreta, así que la
                // regla de «no dos veces por reserva» no aplica aquí.
                'unica' => false,
            ]);

            $envio === null ? $saltados++ : $encolados++;
        }

        $this->campanas->update($id, [
            'estado'     => 'enviada',
            'encolados'  => $encolados,
            'saltados'   => $saltados,
            'enviada_en' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('campanas/ver/' . $id)->with('ok', sprintf(
            '%d mensaje(s) en cola. %d se saltaron por no tener permiso o correo. '
            . 'Saldrán con la próxima pasada de la cola.',
            $encolados,
            $saltados
        ));
    }

    public function cancelar(int $id)
    {
        $campana = $this->campanas->find($id);

        if ($campana === null || $campana['estado'] === 'enviada') {
            return redirect()->to('campanas')->with('error', 'Esa campaña ya se mandó.');
        }

        $this->campanas->update($id, ['estado' => 'cancelada']);

        return redirect()->to('campanas')->with('ok', 'Campaña cancelada.');
    }
}
