<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Ordenes;
use App\Models\ActivoModel;
use App\Models\MantenimientoFotoModel;
use App\Models\MantenimientoMaterialModel;
use App\Models\MantenimientoModel;
use App\Models\UnidadModel;
use App\Models\UsuarioModel;
use RuntimeException;

/**
 * Órdenes de trabajo.
 *
 * El controlador solo traduce entre el formulario y la librería: quién pulsó
 * qué, y qué mensaje se le enseña. Las reglas —qué estado puede pasar a cuál,
 * cuándo se suelta la cabaña, cuándo hace falta un visto bueno— viven en
 * `App\Libraries\Ordenes`, porque el plan preventivo va a abrirlas de
 * madrugada sin nadie delante y tiene que aplicar exactamente las mismas.
 */
class Mantenimiento extends BaseController
{
    private MantenimientoModel $incidencias;
    private UnidadModel $unidades;
    private Ordenes $ordenes;

    public function __construct()
    {
        $this->incidencias = new MantenimientoModel();
        $this->unidades    = new UnidadModel();
        $this->ordenes     = new Ordenes();
    }

    // ── Tablero ─────────────────────────────────────────────────────────

    public function index()
    {
        return view('mantenimiento/index', [
            'titulo'      => 'Mantenimiento',
            'seccion'     => 'mantenimiento',
            'abiertas'    => $this->incidencias->listar(true),
            'resueltas'   => $this->incidencias->listar(false),
            'porVerificar' => puede('mantenimiento.verificar') ? $this->incidencias->esperandoVisto() : [],
            'unidades'    => $this->unidades->orderBy('nombre')->findAll(),
            'prioridades' => MantenimientoModel::PRIORIDADES,
            // El desplegable solo si puede verlos: quien no tiene `activos.ver`
            // no debería enterarse aquí de qué equipos hay.
            'equipos'     => puede('activos.ver') ? (new ActivoModel())->listar() : [],
        ]);
    }

    /** La pantalla de una orden: es donde se trabaja de verdad. */
    public function ver(int $id)
    {
        $orden = $this->incidencias->detalle($id);

        if ($orden === null) {
            return redirect()->to('mantenimiento')->with('error', 'Esa orden no existe.');
        }

        $verCostos = puede('mantenimiento.costos');

        return view('mantenimiento/ver', [
            'titulo'      => 'Orden #' . $orden['id'],
            'seccion'     => 'mantenimiento',
            'orden'       => $orden,
            'prioridades' => MantenimientoModel::PRIORIDADES,
            'origenes'    => MantenimientoModel::ORIGENES,
            'fotos'       => (new MantenimientoFotoModel())->deOrden($id),
            'momentos'    => MantenimientoFotoModel::MOMENTOS,
            'materiales'  => $verCostos ? (new MantenimientoMaterialModel())->deOrden($id) : [],
            'ver_costos'  => $verCostos,
            'tecnicos'    => puede('mantenimiento.asignar')
                ? (new UsuarioModel())->conPermiso('mantenimiento.trabajar')
                : [],
            'insumos'     => $verCostos
                ? db_connect()->table('insumos')->where('activo', 1)->orderBy('nombre')->get()->getResultArray()
                : [],
            'bodegas'     => $verCostos
                ? db_connect()->table('bodegas')->where('activa', 1)->orderBy('nombre')->get()->getResultArray()
                : [],
            'exige_visto' => $this->ordenes->exigeVerificacion($orden),
        ]);
    }

    // ── Abrir ───────────────────────────────────────────────────────────

    public function guardar()
    {
        // La ficha del equipo manda aquí también y quiere volver a lo suyo.
        $volver = (string) $this->request->getPost('volver');
        $volver = preg_match('#^activos/ver/\d+$#', $volver) === 1 ? $volver : 'mantenimiento';

        try {
            $id = $this->ordenes->abrir([
                'titulo'      => (string) $this->request->getPost('titulo'),
                'descripcion' => (string) $this->request->getPost('descripcion'),
                'prioridad'   => (string) $this->request->getPost('prioridad'),
                'origen'      => $this->origenDeQuienReporta(),
                'activo_id'   => (int) $this->request->getPost('activo_id'),
                'unidad_id'   => (int) $this->request->getPost('unidad_id'),
                'ubicacion'   => (string) $this->request->getPost('ubicacion'),
                'reporto_id'  => session()->get('usuario_id'),
                'bloquear'    => $this->request->getPost('bloquear') !== null,
            ]);
        } catch (RuntimeException $e) {
            return redirect()->to($volver)->with('error', $e->getMessage());
        }

        $orden = $this->incidencias->find($id);
        $aviso = (int) $orden['bloqueo_unidad'] === 1
            ? ' La cabaña quedó bloqueada y no se venderá hasta resolverla.'
            : '';

        return redirect()->to($volver)->with('ok', 'Incidencia reportada.' . $aviso);
    }

    // ── Trabajar ────────────────────────────────────────────────────────

    public function iniciar(int $id)
    {
        return $this->hacer($id, fn () => $this->ordenes->empezar($id, session()->get('usuario_id')), 'Orden en proceso.');
    }

    public function pausar(int $id)
    {
        return $this->hacer(
            $id,
            fn () => $this->ordenes->pausar($id, (string) $this->request->getPost('motivo')),
            'Orden en espera.'
        );
    }

    public function resolver(int $id)
    {
        $mensaje = 'Orden resuelta.';
        $orden   = $this->incidencias->find($id);

        if ($orden !== null && $this->ordenes->exigeVerificacion($orden)) {
            $mensaje = 'Orden resuelta. Falta que otra persona la dé por buena antes de cerrarla.';
        }

        return $this->hacer(
            $id,
            fn () => $this->ordenes->resolver($id, (string) $this->request->getPost('solucion'), session()->get('usuario_id')),
            $mensaje
        );
    }

    public function verificar(int $id)
    {
        return $this->hacer(
            $id,
            fn () => $this->ordenes->verificar($id, session()->get('usuario_id')),
            'Reparación dada por buena.'
        );
    }

    public function rechazar(int $id)
    {
        return $this->hacer(
            $id,
            fn () => $this->ordenes->rechazar($id, (string) $this->request->getPost('motivo'), session()->get('usuario_id')),
            'Devuelta al técnico.'
        );
    }

    public function anular(int $id)
    {
        return $this->hacer(
            $id,
            fn () => $this->ordenes->anular($id, (string) $this->request->getPost('motivo')),
            'Orden anulada.'
        );
    }

    public function asignar(int $id)
    {
        return $this->hacer(
            $id,
            fn () => $this->ordenes->asignar($id, (int) $this->request->getPost('asignado_a') ?: null),
            'Trabajo repartido.'
        );
    }

    public function prioridad(int $id)
    {
        return $this->hacer(
            $id,
            fn () => $this->ordenes->cambiarPrioridad($id, (string) $this->request->getPost('prioridad')),
            'Prioridad cambiada.'
        );
    }

    // ── Materiales ──────────────────────────────────────────────────────

    public function material(int $id)
    {
        $insumoId = (int) $this->request->getPost('insumo_id') ?: null;
        $bodegaId = (int) $this->request->getPost('bodega_id') ?: null;

        // Un insumo sin bodega no se puede descontar de ningún sitio
        if ($insumoId !== null && $bodegaId === null) {
            return redirect()->to('mantenimiento/ver/' . $id)
                ->with('error', 'Di de qué almacén sale el repuesto.');
        }

        $costo = $this->request->getPost('costo_unitario');

        return $this->hacer(
            $id,
            fn () => $this->ordenes->gastarMaterial(
                $id,
                (string) $this->request->getPost('descripcion'),
                (float) $this->request->getPost('cantidad'),
                $insumoId,
                $bodegaId,
                $costo !== null && $costo !== '' ? (float) $costo : null,
                session()->get('usuario_id')
            ),
            'Material apuntado.'
        );
    }

    public function quitarMaterial(int $lineaId)
    {
        $linea = (new MantenimientoMaterialModel())->find($lineaId);

        if ($linea === null) {
            return redirect()->to('mantenimiento')->with('error', 'Esa línea no existe.');
        }

        try {
            $this->ordenes->quitarMaterial($lineaId);
        } catch (RuntimeException $e) {
            return redirect()->to('mantenimiento/ver/' . $linea['mantenimiento_id'])->with('error', $e->getMessage());
        }

        return redirect()->to('mantenimiento/ver/' . $linea['mantenimiento_id'])
            ->with('ok', 'Material quitado y devuelto al almacén.');
    }

    // ── Fotos ───────────────────────────────────────────────────────────

    public function foto(int $id)
    {
        $orden = $this->incidencias->find($id);

        if ($orden === null) {
            return redirect()->to('mantenimiento')->with('error', 'Esa orden no existe.');
        }

        $fotos = new MantenimientoFotoModel();

        if ($fotos->cuantasTiene($id) >= MantenimientoFotoModel::TOPE_POR_ORDEN) {
            return redirect()->to('mantenimiento/ver/' . $id)
                ->with('error', 'Esta orden ya tiene ' . MantenimientoFotoModel::TOPE_POR_ORDEN . ' fotos. Con eso sobra.');
        }

        $archivo = $this->request->getFile('foto');

        if ($archivo === null || ! $archivo->isValid()) {
            return redirect()->to('mantenimiento/ver/' . $id)->with('error', 'No llegó ninguna foto.');
        }

        if ($archivo->getSize() > MantenimientoFotoModel::TOPE_BYTES) {
            return redirect()->to('mantenimiento/ver/' . $id)
                ->with('error', 'La foto pasa de 6 MB. Hazla con menos resolución.');
        }

        // El tipo se mira por el contenido, no por la extensión
        if (! in_array($archivo->getMimeType(), MantenimientoFotoModel::MIMES, true)) {
            return redirect()->to('mantenimiento/ver/' . $id)->with('error', 'Solo se aceptan imágenes.');
        }

        $destino = WRITEPATH . 'uploads/mantenimiento/';

        if (! is_dir($destino)) {
            mkdir($destino, 0755, true);
        }

        $nombre = $archivo->getRandomName();
        $archivo->move($destino, $nombre);

        $fotos->insert([
            'mantenimiento_id' => $id,
            'momento'          => $this->request->getPost('momento') === 'despues' ? 'despues' : 'antes',
            'archivo'          => $nombre,
            'usuario_id'       => session()->get('usuario_id'),
        ]);

        return redirect()->to('mantenimiento/ver/' . $id)->with('ok', 'Foto guardada.');
    }

    /** Las fotos se sirven desde PHP: están fuera de la carpeta pública. */
    public function verFoto(int $fotoId)
    {
        $foto = (new MantenimientoFotoModel())->find($fotoId);

        if ($foto === null) {
            return $this->response->setStatusCode(404)->setBody('No existe.');
        }

        $ruta = WRITEPATH . 'uploads/mantenimiento/' . $foto['archivo'];

        if (! is_file($ruta)) {
            return $this->response->setStatusCode(404)->setBody('El fichero ya no está.');
        }

        return $this->response
            ->setHeader('Content-Type', mime_content_type($ruta) ?: 'image/jpeg')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($ruta));
    }

    // ── Lo común ────────────────────────────────────────────────────────

    /**
     * Ejecuta una acción y traduce su fallo a un mensaje.
     *
     * Las razones por las que algo no se puede hacer las decide la librería y
     * ya vienen explicadas; aquí solo se enseñan.
     */
    private function hacer(int $id, callable $accion, string $exito)
    {
        try {
            $accion();
        } catch (RuntimeException $e) {
            return redirect()->to('mantenimiento/ver/' . $id)->with('error', $e->getMessage());
        }

        $destino = $this->request->getPost('volver_al_tablero') !== null
            ? 'mantenimiento'
            : 'mantenimiento/ver/' . $id;

        return redirect()->to($destino)->with('ok', $exito);
    }

    /**
     * De dónde salió la avería, según quién la reporta.
     *
     * Importa para saber si los huéspedes se están encontrando las averías
     * antes que nosotros, que es la señal de que el preventivo no funciona.
     */
    private function origenDeQuienReporta(): string
    {
        return match (true) {
            puede('mantenimiento.trabajar') => 'tecnico',
            puede('limpieza.trabajar')      => 'limpieza',
            puede('reservas.checkin')       => 'recepcion',
            default                         => 'otro',
        };
    }
}
