<?php

namespace App\Controllers;

use App\Libraries\Galeria;
use App\Models\InventarioItemModel;
use App\Models\MedioModel;
use App\Models\RevisionInventarioModel;
use App\Models\ServicioModel;
use App\Models\TipoUnidadModel;
use App\Models\UnidadModel;

class Unidades extends BaseController
{
    private UnidadModel $unidades;
    private TipoUnidadModel $tipos;
    private $db;

    public function __construct()
    {
        $this->unidades = new UnidadModel();
        $this->tipos    = new TipoUnidadModel();
        $this->db       = db_connect();
    }

    public function index()
    {
        $medios    = new MedioModel();
        $revisions = (new RevisionInventarioModel())->ultimasPorUnidad();

        return view('unidades/index', [
            'titulo'    => 'Cabañas',
            'seccion'   => 'unidades',
            'unidades'  => $this->unidades->conTipo(),
            // Cada cabaña enseña su propia foto; si no tiene, la de su tipo
            'portadas'  => $medios->portadasPorTipo(),
            'propias'   => $medios->portadasPorUnidad(),
            'galerias'  => $this->conteoGalerias(),
            'revisiones' => $revisions,
        ]);
    }

    /** Ficha completa: estado, reservas, servicios, inventario, fotos e incidencias. */
    public function ver(int $id)
    {
        $unidad = $this->unidades->conTipoUna($id);
        if ($unidad === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        $servicios = new ServicioModel();

        return view('unidades/ver', [
            'titulo'      => $unidad['nombre'],
            'seccion'     => 'unidades',
            'unidad'      => $unidad,
            'reservas'    => $this->unidades->proximasReservas($id),
            'servicios'   => $servicios->deUnidad($id, (int) $unidad['tipo_id']),
            'catalogo'    => $servicios->porGrupo(),
            'delTipo'     => $servicios->deTipo((int) $unidad['tipo_id']),
            'excepciones' => $servicios->excepcionesDeUnidad($id),
            'inventario'  => (new InventarioItemModel())->deUnidad($id),
            'revisiones'  => (new RevisionInventarioModel())->deUnidad($id),
            'galeria'     => (new MedioModel())->deUnidad($id, true),
            'fotos'       => (new MedioModel())->deUnidad($id, false),
            'galeriaTipo' => (new MedioModel())->deTipo((int) $unidad['tipo_id']),
            'mantenimientos' => $this->incidenciasAbiertas($id),
            'limpiezas'   => $this->ultimasLimpiezas($id),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Alta y edición
    // ─────────────────────────────────────────────────────────────

    public function nueva()
    {
        return view('unidades/form', [
            'titulo'  => 'Nueva cabaña',
            'seccion' => 'unidades',
            'tipos'   => $this->tipos->orderBy('nombre')->findAll(),
        ]);
    }

    public function guardar()
    {
        $datos = $this->datos();

        $id = $this->unidades->insert($datos);
        if ($id === false) {
            return redirect()->back()->withInput()->with('errores', $this->unidades->errors());
        }

        // Nace con el inventario estándar del catálogo: así no hay que teclearlo
        $items      = new InventarioItemModel();
        $cantidades = [];
        foreach ($items->activos() as $item) {
            $cantidades[$item['id']] = (int) $item['cantidad_estandar'];
        }
        $items->fijarEnUnidad((int) $id, $cantidades);

        return redirect()->to('unidades/ver/' . $id)
            ->with('ok', 'Cabaña creada con el inventario estándar. Ajústalo si en esta hay algo distinto.');
    }

    public function editar(int $id)
    {
        $unidad = $this->unidades->find($id);
        if ($unidad === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        return view('unidades/form', [
            'titulo'  => 'Editar cabaña',
            'seccion' => 'unidades',
            'unidad'  => $unidad,
            'tipos'   => $this->tipos->orderBy('nombre')->findAll(),
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->unidades->find($id) === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        // El personal de limpieza solo puede cambiar el estado, no el resto de la ficha
        $datos = session()->get('usuario_rol') === 'limpieza'
            ? ['estado' => (string) $this->request->getPost('estado')]
            : $this->datos();

        if (! $this->unidades->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->unidades->errors());
        }

        return redirect()->to('unidades/ver/' . $id)->with('ok', 'Cabaña actualizada.');
    }

    public function eliminar(int $id)
    {
        try {
            $this->unidades->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('unidades')->with('error', 'No se pudo eliminar: la cabaña tiene datos asociados (p. ej. reservas).');
        }

        return redirect()->to('unidades')->with('ok', 'Cabaña eliminada.');
    }

    private function datos(): array
    {
        return [
            'nombre'      => trim((string) $this->request->getPost('nombre')),
            'tipo_id'     => (int) $this->request->getPost('tipo_id'),
            'descripcion' => trim((string) $this->request->getPost('descripcion')) ?: null,
            'ubicacion'   => trim((string) $this->request->getPost('ubicacion')) ?: null,
            'orden'       => (int) $this->request->getPost('orden'),
            'estado'      => (string) $this->request->getPost('estado'),
            'notas'       => trim((string) $this->request->getPost('notas')) ?: null,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Servicios propios de la cabaña
    // ─────────────────────────────────────────────────────────────

    public function guardarServicios(int $id)
    {
        $unidad = $this->unidades->find($id);
        if ($unidad === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        $servicios = new ServicioModel();
        $delTipo   = $servicios->deTipo((int) $unidad['tipo_id']);
        $marcados  = array_map('intval', (array) $this->request->getPost('servicio'));

        // Solo se guarda lo que se aparta de su tipo: así, si cambias el tipo,
        // las cabañas siguen a la vez sin tener que tocarlas una a una.
        $excepciones = [];
        foreach ($servicios->findAll() as $s) {
            $sid      = (int) $s['id'];
            $enTipo   = in_array($sid, $delTipo, true);
            $marcado  = in_array($sid, $marcados, true);

            if ($marcado && ! $enTipo) {
                $excepciones[$sid] = 'si';
            } elseif (! $marcado && $enTipo) {
                $excepciones[$sid] = 'no';
            }
        }

        $servicios->fijarExcepciones($id, $excepciones);

        $mensaje = $excepciones === []
            ? 'Esta cabaña queda igual que su tipo de alojamiento.'
            : 'Servicios guardados: ' . count($excepciones) . ' diferencia' . (count($excepciones) > 1 ? 's' : '') . ' respecto a su tipo.';

        return redirect()->to('unidades/ver/' . $id . '#servicios')->with('ok', $mensaje);
    }

    // ─────────────────────────────────────────────────────────────
    //  Inventario
    // ─────────────────────────────────────────────────────────────

    public function guardarInventario(int $id)
    {
        if ($this->unidades->find($id) === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        (new InventarioItemModel())->fijarEnUnidad($id, (array) $this->request->getPost('cantidad'));

        return redirect()->to('unidades/ver/' . $id . '#inventario')->with('ok', 'Inventario de la cabaña guardado.');
    }

    public function copiarInventario(int $id)
    {
        if ($this->unidades->find($id) === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        $copiadas = (new InventarioItemModel())->copiarATodas($id);

        return redirect()->to('unidades/ver/' . $id . '#inventario')
            ->with('ok', $copiadas === 0
                ? 'No hay otras cabañas del mismo tipo a las que copiar.'
                : 'Inventario copiado a ' . $copiadas . ' cabaña' . ($copiadas > 1 ? 's' : '') . ' más.');
    }

    /** Pantalla de revisión: se marca lo que falta o está roto. */
    public function revisar(int $id)
    {
        $unidad = $this->unidades->conTipoUna($id);
        if ($unidad === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        // Si hay alguien alojado o acaba de salir, se ofrece cargarle lo que falte
        $reserva = $this->db->table('reservas')
            ->select('reservas.id, reservas.codigo, huespedes.nombre, huespedes.apellidos')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->where('reservas.unidad_id', $id)
            ->whereIn('reservas.estado', ['checkin', 'checkout'])
            ->orderBy('reservas.fecha_salida', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return view('unidades/revisar', [
            'titulo'     => 'Revisar ' . $unidad['nombre'],
            'seccion'    => 'unidades',
            'unidad'     => $unidad,
            'inventario' => (new InventarioItemModel())->deUnidad($id),
            'reserva'    => $reserva,
        ]);
    }

    /**
     * Guarda la revisión. Lo que falta o está roto puede convertirse en
     * un aviso de mantenimiento y, si el huésped se lo llevó, en un cargo al folio.
     */
    public function guardarRevision(int $id)
    {
        $unidad = $this->unidades->find($id);
        if ($unidad === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        $items     = (new InventarioItemModel())->deUnidad($id);
        $estados   = (array) $this->request->getPost('estado');
        $encontrado = (array) $this->request->getPost('encontrada');
        $observaciones = (array) $this->request->getPost('observacion');

        $lineas    = [];
        $faltantes = 0;
        $danados   = 0;
        $aCobrar   = 0.0;
        $detalle   = [];

        foreach ($items as $item) {
            if ((int) $item['cantidad'] === 0) {
                continue;
            }

            $itemId = (int) $item['id'];
            $estado = in_array($estados[$itemId] ?? 'ok', ['ok', 'falta', 'danado'], true)
                ? $estados[$itemId] : 'ok';

            $cantidad = $estado === 'ok'
                ? (int) $item['cantidad']
                : max(0, min((int) $item['cantidad'], (int) ($encontrado[$itemId] ?? 0)));

            $lineas[] = [
                'item_id'     => $itemId,
                'esperada'    => (int) $item['cantidad'],
                'encontrada'  => $cantidad,
                'estado'      => $estado,
                'observacion' => trim((string) ($observaciones[$itemId] ?? '')) ?: null,
            ];

            if ($estado === 'falta') {
                $faltan = (int) $item['cantidad'] - $cantidad;
                $faltantes += $faltan;
                $aCobrar   += $faltan * (float) $item['valor_reposicion'];
                $detalle[] = $faltan . ' × ' . $item['nombre'];
            } elseif ($estado === 'danado') {
                $danados++;
                $detalle[] = $item['nombre'] . ' (dañado)';
            }
        }

        $revisiones = new RevisionInventarioModel();
        $reservaId  = (int) $this->request->getPost('reserva_id') ?: null;

        $revisionId = $revisiones->insert([
            'unidad_id'  => $id,
            'reserva_id' => $reservaId,
            'usuario_id' => session()->get('usuario_id'),
            'estado'     => ($faltantes > 0 || $danados > 0) ? 'incidencias' : 'ok',
            'faltantes'  => $faltantes,
            'danados'    => $danados,
            'notas'      => trim((string) $this->request->getPost('notas')) ?: null,
        ]);

        foreach ($lineas as $linea) {
            $this->db->table('inventario_revision_lineas')->insert(array_merge(['revision_id' => $revisionId], $linea));
        }

        $avisos = [];

        // Lo dañado se convierte en un aviso de mantenimiento: si no, se olvida
        if ($danados > 0 && $this->request->getPost('crear_mantenimiento') !== null) {
            (new \App\Models\MantenimientoModel())->insert([
                'unidad_id'   => $id,
                'titulo'      => 'Revisión de inventario: ' . $danados . ' artículo' . ($danados > 1 ? 's dañados' : ' dañado'),
                'descripcion' => implode(', ', $detalle),
                'prioridad'   => 'media',
                'estado'      => 'abierta',
                'reporto_id'  => session()->get('usuario_id'),
            ]);
            $avisos[] = 'Se abrió un aviso de mantenimiento.';
        }

        // Lo que falta se le puede cobrar al huésped que acaba de salir
        if ($aCobrar > 0 && $reservaId !== null && $this->request->getPost('cargar_folio') !== null) {
            (new \App\Models\FolioModel())->insert([
                'reserva_id' => $reservaId,
                'tipo'       => 'cargo',
                'concepto'   => 'Reposición de enseres: ' . implode(', ', $detalle),
                'valor'      => $aCobrar,
                'usuario_id' => session()->get('usuario_id'),
            ]);
            $avisos[] = 'Se cargaron $' . number_format($aCobrar, 0, ',', '.') . ' al folio de la reserva.';
        }

        $resumen = ($faltantes === 0 && $danados === 0)
            ? 'Revisión guardada: todo completo.'
            : 'Revisión guardada: ' . $faltantes . ' faltante' . ($faltantes === 1 ? '' : 's')
                . ' y ' . $danados . ' dañado' . ($danados === 1 ? '' : 's') . '.';

        return redirect()->to('unidades/ver/' . $id . '#inventario')
            ->with('ok', trim($resumen . ' ' . implode(' ', $avisos)));
    }

    // ─────────────────────────────────────────────────────────────
    //  Fotos internas de la cabaña
    // ─────────────────────────────────────────────────────────────

    /**
     * Sube una foto de la cabaña. Con «publicar» marcado va a la galería que
     * se ve en la web; sin marcar queda como foto interna del equipo.
     */
    public function subirFoto(int $id)
    {
        if ($this->unidades->find($id) === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        $publico = $this->request->getPost('publicar') !== null;

        $r = (new Galeria())->subirFoto(
            $this->request->getFile('foto'),
            null,
            $id,
            (string) $this->request->getPost('alt'),
            $publico
        );

        $ancla = $publico ? '#galeria' : '#fotos';

        return redirect()->to('unidades/ver/' . $id . $ancla)->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    /** Añade un vídeo a la galería de la cabaña. */
    public function anadirVideo(int $id)
    {
        if ($this->unidades->find($id) === null) {
            return redirect()->to('unidades')->with('error', 'La cabaña no existe.');
        }

        $r = (new Galeria())->anadirVideo(
            null,
            $id,
            (string) $this->request->getPost('url'),
            (string) $this->request->getPost('titulo')
        );

        return redirect()->to('unidades/ver/' . $id . '#galeria')->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    public function portadaFoto(int $medioId)
    {
        $medios = new MedioModel();
        $medio  = $medios->find($medioId);
        if ($medio === null || $medio['unidad_id'] === null) {
            return redirect()->to('unidades')->with('error', 'Esa foto no existe.');
        }

        $medios->marcarPortada($medioId);

        return redirect()->to('unidades/ver/' . $medio['unidad_id'] . '#galeria')
            ->with('ok', 'Portada cambiada: es la primera que se ve de esta cabaña.');
    }

    public function moverFoto(int $medioId)
    {
        $medios = new MedioModel();
        $medio  = $medios->find($medioId);
        if ($medio === null || $medio['unidad_id'] === null) {
            return redirect()->to('unidades')->with('error', 'Esa foto no existe.');
        }

        $direccion = $this->request->getPost('direccion') === 'abajo' ? 1 : -1;
        $medios->update($medioId, ['orden' => max(0, (int) $medio['orden'] + $direccion * 2)]);
        $medios->renumerar(null, (int) $medio['unidad_id'], (int) $medio['publico']);

        return redirect()->to('unidades/ver/' . $medio['unidad_id'] . '#galeria');
    }

    /** Pasa una foto de interna a publicable, o al revés: mueve el archivo de sitio. */
    public function alternarPublica(int $medioId)
    {
        $medios = new MedioModel();
        $medio  = $medios->find($medioId);
        if ($medio === null || $medio['unidad_id'] === null) {
            return redirect()->to('unidades')->with('error', 'Esa foto no existe.');
        }

        if ($medio['tipo'] === 'video') {
            return redirect()->to('unidades/ver/' . $medio['unidad_id'] . '#galeria')
                ->with('error', 'Los vídeos siempre son públicos: si no quieres publicarlo, quítalo.');
        }

        $r = (new Galeria())->cambiarVisibilidad($medioId, (int) $medio['publico'] !== 1);

        return redirect()->to('unidades/ver/' . $medio['unidad_id'] . ($r['publico'] ? '#galeria' : '#fotos'))
            ->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    public function eliminarFoto(int $medioId)
    {
        $medio = (new MedioModel())->find($medioId);
        if ($medio === null || $medio['unidad_id'] === null) {
            return redirect()->to('unidades')->with('error', 'Esa foto no existe.');
        }

        (new Galeria())->eliminar($medioId);

        return redirect()->to('unidades/ver/' . $medio['unidad_id'] . '#galeria')->with('ok', 'Elemento eliminado.');
    }

    /**
     * Sirve una foto interna. No están en public/ a propósito: pueden mostrar
     * cerraduras, llaves o desperfectos, y eso no debe verse desde fuera.
     */
    public function foto(int $medioId)
    {
        $medio = (new MedioModel())->find($medioId);
        if ($medio === null || $medio['unidad_id'] === null) {
            return $this->response->setStatusCode(404)->setBody('No encontrada.');
        }

        $ruta = (new Galeria())->rutaPrivada($medio);
        if ($ruta === null) {
            return $this->response->setStatusCode(404)->setBody('No encontrada.');
        }

        log_message('info', 'Foto de cabaña {id} consultada por el usuario {u}', [
            'id' => $medioId, 'u' => session()->get('usuario_id'),
        ]);

        return $this->response
            ->setHeader('Content-Type', mime_content_type($ruta) ?: 'image/jpeg')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody(file_get_contents($ruta));
    }

    // ─────────────────────────────────────────────────────────────

    /** Cuántos elementos publicables tiene la galería de cada cabaña. */
    private function conteoGalerias(): array
    {
        $filas = $this->db->table('medios')
            ->select('unidad_id, COUNT(*) AS total')
            ->where('unidad_id IS NOT NULL')
            ->where('publico', 1)
            ->groupBy('unidad_id')
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($filas, 'total', 'unidad_id'));
    }

    private function incidenciasAbiertas(int $unidadId): array
    {
        return $this->db->table('mantenimientos')
            ->where('unidad_id', $unidadId)
            ->whereIn('estado', ['abierta', 'en_proceso'])
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function ultimasLimpiezas(int $unidadId): array
    {
        return $this->db->table('limpiezas')
            ->select('limpiezas.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id', 'left')
            ->where('limpiezas.unidad_id', $unidadId)
            ->orderBy('limpiezas.id', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();
    }
}
