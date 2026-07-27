<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Qr;
use App\Models\ActivoDocumentoModel;
use App\Models\ActivoModel;
use App\Models\MantenimientoModel;
use App\Models\ProveedorModel;
use App\Models\UnidadModel;

/**
 * Inventario de equipos.
 *
 * La pantalla que más se va a usar no es el listado: es la que sale al escanear
 * la etiqueta con el móvil. Alguien está delante del calentador, ve que gotea, y
 * en dos toques tiene la avería reportada con el equipo correcto y la cabaña
 * correcta, sin buscar nada en una lista.
 */
class Activos extends BaseController
{
    private ActivoModel $activos;

    public function __construct()
    {
        $this->activos = new ActivoModel();
    }

    // ── Listado y ficha ─────────────────────────────────────────────────

    public function index()
    {
        $filtros = [
            'categoria' => (string) $this->request->getGet('categoria'),
            'unidad_id' => (int) $this->request->getGet('unidad_id'),
            'estado'    => (string) $this->request->getGet('estado'),
            'buscar'    => (string) $this->request->getGet('buscar'),
        ];

        return view('activos/index', [
            'titulo'     => 'Equipos y activos',
            'seccion'    => 'mantenimiento',
            'activos'    => $this->activos->listar($filtros),
            'filtros'    => $filtros,
            'unidades'   => (new UnidadModel())->orderBy('nombre')->findAll(),
            'categorias' => ActivoModel::CATEGORIAS,
            'estados'    => ActivoModel::ESTADOS,
            'conteo'     => $this->activos->conteoPorEstado(),
            'garantias'  => $this->activos->garantiasQueVencen(),
        ]);
    }

    public function ver(int $id)
    {
        $activo = $this->activos->find($id);

        if ($activo === null) {
            return redirect()->to('activos')->with('error', 'Ese equipo no existe.');
        }

        return $this->ficha($activo);
    }

    /**
     * Lo que sale al escanear la etiqueta.
     *
     * Pide sesión como cualquier pantalla del panel. El QR no es una llave: si
     * alguien lo fotografía, sin cuenta no ve nada.
     */
    public function escanear(string $codigo)
    {
        $activo = $this->activos->porCodigo($codigo);

        if ($activo === null) {
            return view('activos/no_encontrado', [
                'titulo'  => 'Equipo no encontrado',
                'seccion' => 'mantenimiento',
                'codigo'  => strtoupper(trim($codigo)),
            ]);
        }

        return $this->ficha($activo, true);
    }

    private function ficha(array $activo, bool $desdeQr = false)
    {
        $activo = $this->activos->porCodigo($activo['codigo']);

        $incidencias = (new MantenimientoModel())
            ->select('mantenimientos.*, resuelve.nombre AS resolvio_nombre')
            ->join('usuarios AS resuelve', 'resuelve.id = mantenimientos.resolvio_id', 'left')
            ->where('mantenimientos.activo_id', $activo['id'])
            ->orderBy('mantenimientos.created_at', 'DESC')
            ->findAll(30);

        return view('activos/ver', [
            'titulo'      => $activo['nombre'],
            'seccion'     => 'mantenimiento',
            'activo'      => $activo,
            'desde_qr'    => $desdeQr,
            'documentos'  => (new ActivoDocumentoModel())->deActivo((int) $activo['id']),
            'incidencias' => $incidencias,
            'abiertas'    => array_filter(
                $incidencias,
                static fn (array $i): bool => in_array($i['estado'], ['abierta', 'en_proceso', 'pausada'], true)
            ),
            'categorias'  => ActivoModel::CATEGORIAS,
            'estados'     => ActivoModel::ESTADOS,
            'tipos_doc'   => ActivoDocumentoModel::TIPOS,
            'prioridades' => MantenimientoModel::PRIORIDADES,
            'en_garantia' => $this->activos->enGarantia($activo),
            'enlace'      => site_url('activo/' . $activo['codigo']),
        ]);
    }

    // ── Alta y edición ──────────────────────────────────────────────────

    public function nuevo()
    {
        return view('activos/editar', [
            'titulo'     => 'Nuevo equipo',
            'seccion'    => 'mantenimiento',
            'activo'     => null,
            'codigo'     => $this->activos->siguienteCodigo(),
            'unidades'   => (new UnidadModel())->orderBy('nombre')->findAll(),
            'proveedores' => (new ProveedorModel())->orderBy('nombre')->findAll(),
            'categorias' => ActivoModel::CATEGORIAS,
            'estados'    => ActivoModel::ESTADOS,
        ]);
    }

    public function editar(int $id)
    {
        $activo = $this->activos->find($id);

        if ($activo === null) {
            return redirect()->to('activos')->with('error', 'Ese equipo no existe.');
        }

        return view('activos/editar', [
            'titulo'     => 'Editar ' . $activo['nombre'],
            'seccion'    => 'mantenimiento',
            'activo'     => $activo,
            'codigo'     => $activo['codigo'],
            'unidades'   => (new UnidadModel())->orderBy('nombre')->findAll(),
            'proveedores' => (new ProveedorModel())->orderBy('nombre')->findAll(),
            'categorias' => ActivoModel::CATEGORIAS,
            'estados'    => ActivoModel::ESTADOS,
        ]);
    }

    public function guardar(?int $id = null)
    {
        $unidadId = (int) $this->request->getPost('unidad_id') ?: null;

        $datos = [
            'nombre'    => trim((string) $this->request->getPost('nombre')),
            'categoria' => (string) $this->request->getPost('categoria'),
            'unidad_id' => $unidadId,
            // Si está en una cabaña, la ubicación libre sobra: sería otra
            // verdad sobre lo mismo, y acabarían discrepando.
            'ubicacion' => $unidadId === null
                ? (trim((string) $this->request->getPost('ubicacion')) ?: 'Zonas comunes')
                : null,
            'marca'           => trim((string) $this->request->getPost('marca')) ?: null,
            'modelo'          => trim((string) $this->request->getPost('modelo')) ?: null,
            'serie'           => trim((string) $this->request->getPost('serie')) ?: null,
            'proveedor_id'    => (int) $this->request->getPost('proveedor_id') ?: null,
            'fecha_compra'    => $this->request->getPost('fecha_compra') ?: null,
            'valor_compra'    => $this->request->getPost('valor_compra') !== '' ? (float) $this->request->getPost('valor_compra') : null,
            'garantia_hasta'  => $this->request->getPost('garantia_hasta') ?: null,
            'vida_util_meses' => (int) $this->request->getPost('vida_util_meses') ?: null,
            'estado'          => array_key_exists((string) $this->request->getPost('estado'), ActivoModel::ESTADOS)
                ? (string) $this->request->getPost('estado') : 'activo',
            'critico' => $this->request->getPost('critico') !== null ? 1 : 0,
            'notas'   => trim((string) $this->request->getPost('notas')) ?: null,
        ];

        if ($id === null) {
            // El código se genera aquí y no se acepta del formulario: si dos
            // personas dan de alta a la vez, el que llega segundo se llevaría
            // el mismo número.
            $datos['codigo'] = $this->activos->siguienteCodigo();

            if (! $this->activos->insert($datos)) {
                return redirect()->back()->withInput()->with('errores', $this->activos->errors());
            }

            $id = (int) $this->activos->getInsertID();

            return redirect()->to('activos/ver/' . $id)
                ->with('ok', 'Equipo dado de alta con el código ' . $datos['codigo'] . '. Imprime su etiqueta y pégala.');
        }

        if (! $this->activos->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->activos->errors());
        }

        return redirect()->to('activos/ver/' . $id)->with('ok', 'Equipo actualizado.');
    }

    /**
     * Baja de un equipo.
     *
     * No se borra: su historial de averías y costos es justo lo que hace falta
     * para decidir si el siguiente se compra de otra marca.
     */
    public function darDeBaja(int $id)
    {
        $activo = $this->activos->find($id);

        if ($activo === null) {
            return redirect()->to('activos')->with('error', 'Ese equipo no existe.');
        }

        $motivo = trim((string) $this->request->getPost('baja_motivo'));

        if ($motivo === '') {
            return redirect()->to('activos/ver/' . $id)->with('error', 'Di por qué se da de baja.');
        }

        $this->activos->update($id, [
            'estado'      => 'baja',
            'baja_en'     => date('Y-m-d'),
            'baja_motivo' => mb_substr($motivo, 0, 200),
        ]);

        return redirect()->to('activos/ver/' . $id)
            ->with('ok', 'Equipo dado de baja. Su historial se conserva.');
    }

    // ── Etiquetas ───────────────────────────────────────────────────────

    /**
     * La hoja de etiquetas para imprimir y pegar.
     *
     * Cada etiqueta lleva el QR y, debajo, el código en grande. Lo segundo no
     * es adorno: cuando el QR se despegue o se queme —y se va a despegar—, el
     * código tecleado sigue llevando al mismo sitio.
     */
    public function etiquetas()
    {
        $ids = array_filter(array_map('intval', (array) $this->request->getGet('id')));

        $activos = $ids === []
            ? $this->activos->listar(['categoria' => (string) $this->request->getGet('categoria')])
            : $this->activos->whereIn('id', $ids)->orderBy('codigo')->findAll();

        $etiquetas = [];

        foreach ($activos as $a) {
            $etiquetas[] = [
                'activo' => $a,
                'qr'     => Qr::svg(site_url('activo/' . $a['codigo']), 3, 2),
            ];
        }

        return view('activos/etiquetas', [
            'titulo'    => 'Etiquetas',
            'etiquetas' => $etiquetas,
            'hotel'     => config('Hotel'),
        ]);
    }

    /** El QR suelto, para pegarlo en un documento o mandarlo por chat. */
    public function qr(int $id)
    {
        $activo = $this->activos->find($id);

        if ($activo === null) {
            return $this->response->setStatusCode(404)->setBody('No existe.');
        }

        return $this->response
            ->setHeader('Content-Type', 'image/svg+xml')
            ->setHeader('Content-Disposition', 'inline; filename="' . $activo['codigo'] . '.svg"')
            ->setBody(Qr::svg(site_url('activo/' . $activo['codigo']), 8, 4));
    }

    // ── Documentos ──────────────────────────────────────────────────────

    public function subirDocumento(int $id)
    {
        $activo = $this->activos->find($id);

        if ($activo === null) {
            return redirect()->to('activos')->with('error', 'Ese equipo no existe.');
        }

        $archivo = $this->request->getFile('documento');

        if ($archivo === null || ! $archivo->isValid()) {
            return redirect()->to('activos/ver/' . $id)->with('error', 'No llegó ningún fichero.');
        }

        if ($archivo->getSize() > ActivoDocumentoModel::TOPE_BYTES) {
            return redirect()->to('activos/ver/' . $id)
                ->with('error', 'El fichero pasa de 8 MB. Comprímelo o haz una foto de menos resolución.');
        }

        // El tipo se mira por el contenido, no por la extensión: renombrar un
        // .php a .pdf es el truco más viejo que hay.
        if (! in_array($archivo->getMimeType(), ActivoDocumentoModel::MIMES, true)) {
            return redirect()->to('activos/ver/' . $id)
                ->with('error', 'Solo se aceptan PDF e imágenes.');
        }

        $destino = WRITEPATH . 'uploads/activos/';

        if (! is_dir($destino)) {
            mkdir($destino, 0755, true);
        }

        $nombre = $archivo->getRandomName();
        $archivo->move($destino, $nombre);

        (new ActivoDocumentoModel())->insert([
            'activo_id'       => $id,
            'tipo'            => array_key_exists((string) $this->request->getPost('tipo'), ActivoDocumentoModel::TIPOS)
                ? (string) $this->request->getPost('tipo') : 'otro',
            'archivo'         => $nombre,
            'nombre_original' => mb_substr($archivo->getClientName(), 0, 255),
            'mime'            => $archivo->getClientMimeType(),
            'tamano'          => $archivo->getSize(),
            'usuario_id'      => session()->get('usuario_id'),
        ]);

        return redirect()->to('activos/ver/' . $id)->with('ok', 'Documento guardado.');
    }

    /** Los documentos se sirven desde PHP: están fuera de la carpeta pública. */
    public function documento(int $docId)
    {
        $doc = (new ActivoDocumentoModel())->find($docId);

        if ($doc === null) {
            return $this->response->setStatusCode(404)->setBody('No existe.');
        }

        $ruta = WRITEPATH . 'uploads/activos/' . $doc['archivo'];

        if (! is_file($ruta)) {
            return $this->response->setStatusCode(404)->setBody('El fichero ya no está.');
        }

        return $this->response->download($ruta, null)->setFileName($doc['nombre_original']);
    }

    public function borrarDocumento(int $docId)
    {
        $documentos = new ActivoDocumentoModel();
        $doc        = $documentos->find($docId);

        if ($doc === null) {
            return redirect()->to('activos')->with('error', 'Ese documento no existe.');
        }

        $ruta = WRITEPATH . 'uploads/activos/' . $doc['archivo'];

        if (is_file($ruta)) {
            unlink($ruta);
        }

        $documentos->delete($docId);

        return redirect()->to('activos/ver/' . $doc['activo_id'])->with('ok', 'Documento borrado.');
    }
}
