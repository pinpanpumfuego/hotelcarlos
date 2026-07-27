<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Automatizaciones;
use App\Libraries\Mensajero;
use App\Models\AutomatizacionModel;
use App\Models\EnvioModel;
use App\Models\PlantillaModel;
use RuntimeException;

/**
 * Plantillas, automatizaciones y la cola de mensajes.
 *
 * La pantalla de envíos es la que más va a usarse: es donde se ve si algo no
 * está saliendo. Un módulo de comunicaciones que falla en silencio es peor que
 * no tenerlo, porque nadie se entera hasta que un huésped pregunta por qué no
 * le llegó la confirmación.
 */
class Comunicaciones extends BaseController
{
    private EnvioModel $envios;

    public function __construct()
    {
        $this->envios = new EnvioModel();
    }

    public function index()
    {
        $estado = (string) $this->request->getGet('estado');

        return view('comunicaciones/index', [
            'titulo'   => 'Comunicaciones',
            'seccion'  => 'huespedes',
            'envios'   => $this->envios->recientes($estado),
            'conteo'   => $this->envios->conteo(),
            'estados'  => EnvioModel::ESTADOS,
            'filtro'   => $estado,
            'correo_ok' => (new \App\Libraries\Correo())->configurado(),
            'reglas'   => (new AutomatizacionModel())->listar(),
            'eventos'  => AutomatizacionModel::EVENTOS,
            'ensayo'   => (new Automatizaciones())->ensayo(),
        ]);
    }

    // ── Plantillas ──────────────────────────────────────────────────────

    public function plantillas()
    {
        $plantillas = (new PlantillaModel())->listar();
        $modelo     = new PlantillaModel();

        foreach ($plantillas as &$p) {
            $p['desconocidas'] = $modelo->variablesDesconocidas($p);
        }

        return view('comunicaciones/plantillas', [
            'titulo'     => 'Plantillas de mensajes',
            'seccion'    => 'huespedes',
            'plantillas' => $plantillas,
            'variables'  => PlantillaModel::VARIABLES,
        ]);
    }

    public function editarPlantilla(int $id)
    {
        $plantilla = (new PlantillaModel())->find($id);

        if ($plantilla === null) {
            return redirect()->to('comunicaciones/plantillas')->with('error', 'Esa plantilla no existe.');
        }

        return view('comunicaciones/plantilla', [
            'titulo'    => 'Editar «' . $plantilla['nombre'] . '»',
            'seccion'   => 'huespedes',
            'plantilla' => $plantilla,
            'variables' => PlantillaModel::VARIABLES,
            'vista'     => $this->previsualizar($plantilla),
        ]);
    }

    public function guardarPlantilla(int $id)
    {
        $plantillas = new PlantillaModel();

        if ($plantillas->find($id) === null) {
            return redirect()->to('comunicaciones/plantillas')->with('error', 'Esa plantilla no existe.');
        }

        $datos = [
            'nombre' => trim((string) $this->request->getPost('nombre')),
            'asunto' => trim((string) $this->request->getPost('asunto')) ?: null,
            'cuerpo' => trim((string) $this->request->getPost('cuerpo')),
            'activa' => $this->request->getPost('activa') !== null ? 1 : 0,
        ];

        if (! $plantillas->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $plantillas->errors());
        }

        // Avisar de una variable mal escrita en el momento, no cuando salga el
        // correo con un hueco en medio de la frase.
        $malas = $plantillas->variablesDesconocidas($datos);
        $aviso = $malas === []
            ? ''
            : ' Ojo: {{' . implode('}}, {{', $malas) . '}} no existe y saldrá en blanco.';

        return redirect()->to('comunicaciones/plantilla/' . $id)
            ->with($malas === [] ? 'ok' : 'error', 'Plantilla guardada.' . $aviso);
    }

    /** Cómo quedaría con datos de ejemplo. */
    private function previsualizar(array $plantilla): array
    {
        $ejemplo = [
            'nombre' => 'Marta', 'apellidos' => 'Ruiz', 'hotel' => config('Hotel')->nombre,
            'codigo' => 'SAL-2048', 'entrada' => date('d/m/Y', strtotime('+5 days')),
            'salida' => date('d/m/Y', strtotime('+8 days')), 'noches' => '3',
            'cabana' => 'Sinsonte', 'adultos' => '2', 'total' => '$1.350.000',
            'saldo' => '$405.000', 'portal' => site_url('estancia/…'),
            'registro' => site_url('registro/…'), 'encuesta' => site_url('estancia/…/encuesta'),
            'pago' => site_url('pago/reserva/SAL-2048/total'),
            'telefono' => (string) (config('Hotel')->telefono ?? ''),
            'whatsapp' => (string) (config('Hotel')->whatsapp ?? ''),
        ];

        $mensajero = new Mensajero();

        return [
            'asunto' => $mensajero->rellenar((string) $plantilla['asunto'], $ejemplo),
            'cuerpo' => $mensajero->rellenar((string) $plantilla['cuerpo'], $ejemplo),
        ];
    }

    // ── Automatizaciones ────────────────────────────────────────────────

    public function guardarRegla(int $id)
    {
        $reglas = new AutomatizacionModel();

        if ($reglas->find($id) === null) {
            return redirect()->to('comunicaciones')->with('error', 'Esa regla no existe.');
        }

        $hora = (string) $this->request->getPost('hora');

        $reglas->update($id, [
            'dias'   => (int) $this->request->getPost('dias'),
            // Un correo a las cuatro de la mañana parece una máquina, porque
            // lo es. Fuera de horas razonables se corrige sin preguntar.
            'hora'   => preg_match('/^\d{2}:\d{2}$/', $hora) === 1 ? $hora . ':00' : '09:00:00',
            'activa' => $this->request->getPost('activa') !== null ? 1 : 0,
        ]);

        return redirect()->to('comunicaciones')->with('ok', 'Regla guardada.');
    }

    // ── La cola ─────────────────────────────────────────────────────────

    public function ver(int $id)
    {
        $envio = $this->envios->find($id);

        if ($envio === null) {
            return redirect()->to('comunicaciones')->with('error', 'Ese mensaje no existe.');
        }

        return view('comunicaciones/envio', [
            'titulo'  => 'Mensaje #' . $envio['id'],
            'seccion' => 'huespedes',
            'envio'   => $envio,
            'estados' => EnvioModel::ESTADOS,
        ]);
    }

    /** Lanza la cola desde el panel, sin esperar a la tarea programada. */
    public function procesar()
    {
        $r = (new Mensajero())->procesar();

        $mensaje = sprintf(
            '%d enviado(s), %d fallido(s), %d saltado(s).',
            $r['enviados'],
            $r['fallidos'],
            $r['saltados']
        );

        return redirect()->to('comunicaciones')->with($r['fallidos'] > 0 ? 'error' : 'ok', $mensaje);
    }

    /** Encola lo que toque hoy, para verlo antes de que corra la tarea. */
    public function encolarHoy()
    {
        $r = (new Automatizaciones())->correr();

        return redirect()->to('comunicaciones')->with(
            'ok',
            $r['encolados'] === 0
                ? 'No tocaba ningún mensaje hoy.'
                : $r['encolados'] . ' mensaje(s) encolado(s). Míralos antes de mandarlos.'
        );
    }

    public function reintentar(int $id)
    {
        try {
            $ok = (new Mensajero())->mandarAhora($id);
        } catch (RuntimeException $e) {
            return redirect()->to('comunicaciones')->with('error', $e->getMessage());
        }

        return redirect()->to('comunicaciones/ver/' . $id)
            ->with($ok ? 'ok' : 'error', $ok ? 'Enviado.' : 'Volvió a fallar. Mira el error abajo.');
    }

    public function cancelar(int $id)
    {
        $envio = $this->envios->find($id);

        if ($envio === null || $envio['estado'] === 'enviado') {
            return redirect()->to('comunicaciones')->with('error', 'Ese mensaje ya no se puede cancelar.');
        }

        $this->envios->update($id, ['estado' => 'cancelado', 'error' => 'Cancelado a mano desde el panel.']);

        return redirect()->to('comunicaciones')->with('ok', 'Mensaje cancelado.');
    }
}
