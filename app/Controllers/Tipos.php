<?php

namespace App\Controllers;

use App\Libraries\Galeria;
use App\Models\MedioModel;
use App\Models\ServicioModel;
use App\Models\TipoUnidadModel;

class Tipos extends BaseController
{
    private TipoUnidadModel $tipos;

    public function __construct()
    {
        $this->tipos = new TipoUnidadModel();
    }

    public function index()
    {
        $tipos     = $this->tipos->orderBy('nombre')->findAll();
        $medios    = new MedioModel();
        $servicios = new ServicioModel();

        // Cuántas fotos y servicios tiene cada tipo, para verlo de un vistazo
        $resumen = [];
        foreach ($tipos as $t) {
            $galeria = $medios->deTipo((int) $t['id']);
            $resumen[$t['id']] = [
                'fotos'     => count(array_filter($galeria, static fn ($m) => $m['tipo'] === 'foto')),
                'videos'    => count(array_filter($galeria, static fn ($m) => $m['tipo'] === 'video')),
                'servicios' => count($servicios->deTipo((int) $t['id'])),
                'portada'   => $galeria[0] ?? null,
            ];
        }

        return view('tipos/index', [
            'titulo'  => 'Tipos de alojamiento',
            'seccion' => 'tipos',
            'tipos'   => $tipos,
            'resumen' => $resumen,
        ]);
    }

    public function nuevo()
    {
        return view('tipos/form', [
            'titulo'  => 'Nuevo tipo de alojamiento',
            'seccion' => 'tipos',
        ]);
    }

    public function guardar()
    {
        $datos = $this->recogerDatos();

        if (! $this->tipos->insert($datos)) {
            return redirect()->back()->withInput()->with('errores', $this->tipos->errors());
        }

        return redirect()->to('tipos')->with('ok', 'Tipo de alojamiento creado correctamente.');
    }

    public function editar(int $id)
    {
        $tipo = $this->tipos->find($id);
        if ($tipo === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        return view('tipos/form', [
            'titulo'  => 'Editar tipo de alojamiento',
            'seccion' => 'tipos',
            'tipo'    => $tipo,
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->tipos->find($id) === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        $datos = $this->recogerDatos();

        if (! $this->tipos->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->tipos->errors());
        }

        return redirect()->to('tipos')->with('ok', 'Tipo de alojamiento actualizado.');
    }

    /** Campos del formulario; los topes vacíos se guardan como «sin límite». */
    private function recogerDatos(): array
    {
        $datos = $this->request->getPost([
            'nombre', 'descripcion', 'capacidad', 'tarifa_base',
            'precio_minimo', 'precio_maximo', 'personas_incluidas',
            'suplemento_adulto', 'suplemento_nino',
        ]);

        foreach (['precio_minimo', 'precio_maximo'] as $campo) {
            if (trim((string) ($datos[$campo] ?? '')) === '') {
                $datos[$campo] = null;
            }
        }

        $datos['personas_incluidas'] = max(1, (int) ($datos['personas_incluidas'] ?? 2));
        $datos['suplemento_adulto']  = (float) ($datos['suplemento_adulto'] ?? 0);
        $datos['suplemento_nino']    = (float) ($datos['suplemento_nino'] ?? 0);

        return $datos;
    }

    /** Ficha comercial: galería y servicios que se anuncian en la web. */
    public function ficha(int $id)
    {
        $tipo = $this->tipos->find($id);
        if ($tipo === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        $servicios = new ServicioModel();

        return view('tipos/ficha', [
            'titulo'    => $tipo['nombre'],
            'seccion'   => 'tipos',
            'tipo'      => $tipo,
            'medios'    => (new MedioModel())->deTipo($id),
            'catalogo'  => $servicios->porGrupo(),
            'marcados'  => $servicios->deTipo($id),
            'unidades'  => (new \App\Models\UnidadModel())->where('tipo_id', $id)->orderBy('nombre')->findAll(),
        ]);
    }

    public function guardarServicios(int $id)
    {
        if ($this->tipos->find($id) === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        (new ServicioModel())->fijarEnTipo($id, (array) $this->request->getPost('servicio'));

        return redirect()->to('tipos/ficha/' . $id . '#servicios')
            ->with('ok', 'Servicios guardados. Ya se ven en la web y en el motor de reservas.');
    }

    public function subirFoto(int $id)
    {
        if ($this->tipos->find($id) === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        $r = (new Galeria())->subirFoto(
            $this->request->getFile('foto'),
            $id,
            null,
            (string) $this->request->getPost('alt')
        );

        return redirect()->to('tipos/ficha/' . $id . '#galeria')->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    public function anadirVideo(int $id)
    {
        if ($this->tipos->find($id) === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        $r = (new Galeria())->anadirVideo(
            $id,
            null,
            (string) $this->request->getPost('url'),
            (string) $this->request->getPost('titulo')
        );

        return redirect()->to('tipos/ficha/' . $id . '#galeria')->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    public function portada(int $medioId)
    {
        $medio = (new MedioModel())->find($medioId);
        if ($medio === null || $medio['tipo_unidad_id'] === null) {
            return redirect()->to('tipos')->with('error', 'Esa foto no existe.');
        }

        (new MedioModel())->marcarPortada($medioId);

        return redirect()->to('tipos/ficha/' . $medio['tipo_unidad_id'] . '#galeria')
            ->with('ok', 'Portada cambiada: es la foto que se ve primero en la web.');
    }

    public function moverFoto(int $medioId)
    {
        $medios = new MedioModel();
        $medio  = $medios->find($medioId);
        if ($medio === null || $medio['tipo_unidad_id'] === null) {
            return redirect()->to('tipos')->with('error', 'Esa foto no existe.');
        }

        $direccion = $this->request->getPost('direccion') === 'abajo' ? 1 : -1;
        $medios->update($medioId, ['orden' => max(0, (int) $medio['orden'] + $direccion * 2)]);

        // Se renumera para que las posiciones queden 0,1,2… sin huecos
        $orden = 0;
        $lista = $medios->where('tipo_unidad_id', $medio['tipo_unidad_id'])
            ->orderBy('orden')->orderBy('id')->findAll();
        foreach ($lista as $m) {
            $medios->update($m['id'], ['orden' => $orden++]);
        }

        return redirect()->to('tipos/ficha/' . $medio['tipo_unidad_id'] . '#galeria');
    }

    public function eliminarFoto(int $medioId)
    {
        $medio = (new MedioModel())->find($medioId);
        if ($medio === null || $medio['tipo_unidad_id'] === null) {
            return redirect()->to('tipos')->with('error', 'Esa foto no existe.');
        }

        (new Galeria())->eliminar($medioId);

        return redirect()->to('tipos/ficha/' . $medio['tipo_unidad_id'] . '#galeria')->with('ok', 'Elemento eliminado de la galería.');
    }

    public function eliminar(int $id)
    {
        try {
            $this->tipos->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('tipos')->with('error', 'No se pudo eliminar: hay unidades que usan este tipo.');
        }

        return redirect()->to('tipos')->with('ok', 'Tipo de alojamiento eliminado.');
    }
}
