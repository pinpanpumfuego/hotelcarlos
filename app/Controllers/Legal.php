<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Cumplimiento;
use App\Libraries\ExpedienteDatos;
use App\Models\ConfiguracionModel;
use App\Models\PoliticaModel;
use App\Models\SolicitudDatosModel;

/**
 * Cumplimiento: panel, datos legales, políticas y derechos del titular.
 *
 * El panel no certifica nada. Mira los datos que ya están en el sistema y dice
 * qué falta, qué vence y qué lleva demasiado tiempo sin hacerse. La
 * responsabilidad sigue siendo del prestador; esto solo se encarga de que nadie
 * pueda decir «no me acordaba».
 */
class Legal extends BaseController
{
    public function index()
    {
        $cumplimiento = new Cumplimiento();
        $revision     = $cumplimiento->revisar();

        return view('legal/index', [
            'titulo'   => 'Cumplimiento',
            'seccion'  => 'sistema',
            'revision' => $revision,
            'resumen'  => $cumplimiento->resumen($revision),
            'derechos' => (new SolicitudDatosModel())->listar('abiertas'),
            'tipos'    => SolicitudDatosModel::TIPOS,
        ]);
    }

    // ── Datos legales ───────────────────────────────────────────────────

    public function datos()
    {
        $config = new ConfiguracionModel();
        $claves = [
            'legal_razon_social', 'legal_nit', 'legal_rnt', 'legal_rnt_desde',
            'legal_rnt_renovar', 'legal_representante', 'legal_responsable_datos',
            'legal_correo_datos', 'datos_plazo_consulta', 'datos_plazo_reclamo',
            'datos_retencion_meses', 'legal_protocolo_menores', 'legal_escnna_texto',
            'seguridad_ultima_copia', 'seguridad_aviso_dias',
        ];

        $valores = [];

        foreach ($claves as $c) {
            $valores[$c] = (string) $config->obtener($c, '');
        }

        return view('legal/datos', [
            'titulo'  => 'Datos legales del prestador',
            'seccion' => 'sistema',
            'v'       => $valores,
        ]);
    }

    public function guardarDatos()
    {
        $config = new ConfiguracionModel();

        $texto = [
            'legal_razon_social', 'legal_nit', 'legal_rnt', 'legal_rnt_desde',
            'legal_representante', 'legal_responsable_datos', 'legal_correo_datos',
            'legal_protocolo_menores', 'legal_escnna_texto',
        ];

        $pares = [];

        foreach ($texto as $c) {
            $pares[$c] = trim((string) $this->request->getPost($c));
        }

        // La fecha de renovación va como MM-DD: el año lo pone el sistema, y
        // guardarlo entero obligaría a corregirlo cada enero.
        $renovar = trim((string) $this->request->getPost('legal_rnt_renovar'));
        $pares['legal_rnt_renovar'] = preg_match('/^\d{2}-\d{2}$/', $renovar) === 1 ? $renovar : '03-31';

        // Los plazos legales tienen suelo: un plazo de cero días no existe, y
        // uno de cien no es el que manda la ley.
        $pares['datos_plazo_consulta'] = (string) max(1, min(60, (int) $this->request->getPost('datos_plazo_consulta')));
        $pares['datos_plazo_reclamo']  = (string) max(1, min(60, (int) $this->request->getPost('datos_plazo_reclamo')));
        $pares['datos_retencion_meses'] = (string) max(1, (int) $this->request->getPost('datos_retencion_meses'));
        $pares['seguridad_aviso_dias']  = (string) max(1, (int) $this->request->getPost('seguridad_aviso_dias'));

        $config->guardarPares($pares);

        return redirect()->to('legal/datos')->with('ok', 'Datos legales guardados.');
    }

    /** Apunta que hoy se comprobó la copia de seguridad. */
    public function copiaHecha()
    {
        (new ConfiguracionModel())->guardarPares(['seguridad_ultima_copia' => date('Y-m-d')]);

        return redirect()->to('legal')->with(
            'ok',
            'Apuntado. Recuerda que una copia que nadie ha restaurado nunca no es una copia: '
            . 'es un archivo. Prueba a restaurarla de vez en cuando.'
        );
    }

    // ── Políticas ───────────────────────────────────────────────────────

    public function politicas()
    {
        return view('legal/politicas', [
            'titulo'    => 'Políticas y textos legales',
            'seccion'   => 'sistema',
            'politicas' => (new PoliticaModel())->listar(),
            'tipos'     => PoliticaModel::TIPOS,
        ]);
    }

    public function editarPolitica(?int $id = null)
    {
        $politicas = new PoliticaModel();
        $politica  = $id !== null ? $politicas->find($id) : null;

        if ($id !== null && $politica === null) {
            return redirect()->to('legal/politicas')->with('error', 'Esa versión no existe.');
        }

        return view('legal/politica', [
            'titulo'   => $politica === null ? 'Nueva versión' : 'Versión ' . $politica['version'],
            'seccion'  => 'sistema',
            'politica' => $politica,
            'tipos'    => PoliticaModel::TIPOS,
            // Cuánta gente aceptó esta versión: dice si se puede tocar o no
            'aceptada_por' => $politica !== null ? $politicas->cuantosAceptaron($politica['version']) : 0,
        ]);
    }

    public function guardarPolitica(?int $id = null)
    {
        $politicas = new PoliticaModel();

        $datos = [
            'tipo'    => array_key_exists((string) $this->request->getPost('tipo'), PoliticaModel::TIPOS)
                ? (string) $this->request->getPost('tipo') : 'datos',
            'version' => trim((string) $this->request->getPost('version')),
            'titulo'  => trim((string) $this->request->getPost('titulo')),
            'texto'   => (string) $this->request->getPost('texto'),
            'vigente_desde' => (string) $this->request->getPost('vigente_desde') ?: date('Y-m-d'),
            'publicada'     => $this->request->getPost('publicada') !== null ? 1 : 0,
            'usuario_id'    => session()->get('usuario_id'),
        ];

        if ($id !== null) {
            $politica = $politicas->find($id);

            // Cambiar el texto de una versión que ya aceptó gente convierte su
            // firma en una firma sobre un documento distinto. Para cambiarla se
            // crea una versión nueva; eso es lo que significa versionar.
            if ($politica !== null && $politicas->cuantosAceptaron($politica['version']) > 0
                && $datos['texto'] !== $politica['texto']) {
                return redirect()->back()->withInput()->with(
                    'error',
                    'Esta versión ya la aceptaron ' . $politicas->cuantosAceptaron($politica['version'])
                    . ' huéspedes: su texto no se puede cambiar. Crea una versión nueva.'
                );
            }

            if (! $politicas->update($id, $datos)) {
                return redirect()->back()->withInput()->with('errores', $politicas->errors());
            }

            return redirect()->to('legal/politicas')->with('ok', 'Versión guardada.');
        }

        if (! $politicas->insert($datos)) {
            return redirect()->back()->withInput()->with('errores', $politicas->errors());
        }

        return redirect()->to('legal/politicas')->with('ok', 'Versión creada.');
    }

    // ── Derechos del titular ────────────────────────────────────────────

    public function derechos()
    {
        $estado = (string) ($this->request->getGet('estado') ?? 'abiertas');

        return view('legal/derechos', [
            'titulo'  => 'Derechos del titular',
            'seccion' => 'sistema',
            'lista'   => (new SolicitudDatosModel())->listar($estado),
            'estado'  => $estado,
            'tipos'   => SolicitudDatosModel::TIPOS,
            'estados' => SolicitudDatosModel::ESTADOS,
        ]);
    }

    public function verDerecho(int $id)
    {
        $modelo = new SolicitudDatosModel();
        $s      = $modelo->detalle($id);

        if ($s === null) {
            return redirect()->to('legal/derechos')->with('error', 'Esa solicitud no existe.');
        }

        return view('legal/derecho', [
            'titulo'  => $s['codigo'],
            'seccion' => 'sistema',
            's'       => $s,
            'tipos'   => SolicitudDatosModel::TIPOS,
            'estados' => SolicitudDatosModel::ESTADOS,
            // Si no está enlazada a nadie, se busca por documento para que
            // recepción no tenga que ir a buscarlo a mano.
            'probable' => $s['huesped_id'] === null ? $modelo->huespedProbable($s['documento']) : null,
        ]);
    }

    public function registrarDerecho()
    {
        $modelo = new SolicitudDatosModel();

        $tipo = (string) $this->request->getPost('tipo');
        $tipo = array_key_exists($tipo, SolicitudDatosModel::TIPOS) ? $tipo : 'acceso';

        $documento = trim((string) $this->request->getPost('documento'));
        $probable  = $modelo->huespedProbable($documento);

        $datos = [
            'codigo'     => $modelo->siguienteCodigo(),
            'tipo'       => $tipo,
            'huesped_id' => $probable !== null ? (int) $probable['id'] : null,
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'documento'  => $documento,
            'email'      => trim((string) $this->request->getPost('email')) ?: null,
            'telefono'   => trim((string) $this->request->getPost('telefono')) ?: null,
            'detalle'    => trim((string) $this->request->getPost('detalle')) ?: null,
            'estado'     => 'recibida',
            // Se congela al recibirla: recalcularlo movería la fecha límite
            // cada vez que se abre la pantalla.
            'vence_en'   => $modelo->vencimiento($tipo),
            'origen'     => 'presencial',
            'ip'         => $this->request->getIPAddress(),
        ];

        if (! $modelo->insert($datos)) {
            return redirect()->to('legal/derechos')->with('errores', $modelo->errors());
        }

        return redirect()->to('legal/derecho/' . $modelo->getInsertID())->with(
            'ok',
            'Registrada como ' . $datos['codigo'] . '. Hay que contestar antes del '
            . date('d/m/Y', strtotime($datos['vence_en'])) . '.'
        );
    }

    /**
     * Confirma que quien pide es de verdad el titular.
     *
     * Es el paso que no se puede saltar. Entregarle los datos de alguien a
     * quien no es esa persona es la misma infracción que se intenta evitar,
     * hecha por escrito y con acuse de recibo.
     */
    public function identificar(int $id)
    {
        $modelo = new SolicitudDatosModel();
        $s      = $modelo->find($id);

        if ($s === null) {
            return redirect()->to('legal/derechos')->with('error', 'Esa solicitud no existe.');
        }

        $como = trim((string) $this->request->getPost('como_identifico'));

        if ($como === '') {
            return redirect()->to('legal/derecho/' . $id)
                ->with('error', 'Escribe cómo comprobaste que es esa persona. Sin eso no se puede entregar nada.');
        }

        $huespedId = (int) $this->request->getPost('huesped_id') ?: null;

        $modelo->update($id, [
            'identificado'    => 1,
            'como_identifico' => mb_substr($como, 0, 200),
            'estado'          => 'en_tramite',
            'huesped_id'      => $huespedId ?? $s['huesped_id'],
        ]);

        return redirect()->to('legal/derecho/' . $id)->with('ok', 'Identidad confirmada. Ya se le puede entregar.');
    }

    /** El expediente completo, en texto, listo para entregar. */
    public function expediente(int $id)
    {
        $modelo = new SolicitudDatosModel();
        $s      = $modelo->find($id);

        if ($s === null) {
            return redirect()->to('legal/derechos')->with('error', 'Esa solicitud no existe.');
        }

        if ((int) $s['identificado'] !== 1) {
            return redirect()->to('legal/derecho/' . $id)
                ->with('error', 'Antes de entregar nada hay que confirmar que quien pide es el titular.');
        }

        if ($s['huesped_id'] === null) {
            return redirect()->to('legal/derecho/' . $id)
                ->with('error', 'La solicitud no está enlazada a ningún perfil. Enlázala antes de generar el expediente.');
        }

        $texto = (new ExpedienteDatos())->comoTexto((int) $s['huesped_id']);

        if ($texto === null) {
            return redirect()->to('legal/derecho/' . $id)->with('error', 'Ese perfil ya no existe.');
        }

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="datos-' . $s['codigo'] . '.txt"')
            ->setBody($texto);
    }

    public function atenderDerecho(int $id)
    {
        $modelo = new SolicitudDatosModel();
        $s      = $modelo->find($id);

        if ($s === null) {
            return redirect()->to('legal/derechos')->with('error', 'Esa solicitud no existe.');
        }

        $respuesta = trim((string) $this->request->getPost('respuesta'));

        if ($respuesta === '') {
            return redirect()->to('legal/derecho/' . $id)->with('error', 'Escribe qué se le contestó.');
        }

        if ((int) $s['identificado'] !== 1) {
            return redirect()->to('legal/derecho/' . $id)
                ->with('error', 'Sin confirmar la identidad no se puede dar por atendida.');
        }

        $tarde = $s['vence_en'] !== null && date('Y-m-d') > $s['vence_en'];

        $modelo->update($id, [
            'estado'      => 'atendida',
            'respuesta'   => $respuesta,
            'atendida_en' => date('Y-m-d H:i:s'),
            'atendio_id'  => session()->get('usuario_id'),
        ]);

        return redirect()->to('legal/derecho/' . $id)->with(
            $tarde ? 'error' : 'ok',
            $tarde
                ? 'Atendida, pero fuera del plazo legal. Queda apuntado.'
                : 'Atendida dentro de plazo.'
        );
    }

    public function rechazarDerecho(int $id)
    {
        $modelo = new SolicitudDatosModel();

        if ($modelo->find($id) === null) {
            return redirect()->to('legal/derechos')->with('error', 'Esa solicitud no existe.');
        }

        $motivo = trim((string) $this->request->getPost('motivo_rechazo'));

        if ($motivo === '') {
            return redirect()->to('legal/derecho/' . $id)
                ->with('error', 'Rechazar sin motivo escrito no vale: hay que poder explicarlo.');
        }

        $modelo->update($id, [
            'estado'         => 'rechazada',
            'motivo_rechazo' => mb_substr($motivo, 0, 300),
            'atendida_en'    => date('Y-m-d H:i:s'),
            'atendio_id'     => session()->get('usuario_id'),
        ]);

        return redirect()->to('legal/derecho/' . $id)->with('ok', 'Rechazada, con el motivo apuntado.');
    }
}
