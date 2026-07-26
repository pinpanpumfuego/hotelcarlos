<?php

namespace App\Controllers;

use App\Libraries\Galeria;
use App\Models\ExperienciaModel;
use App\Models\ExperienciaReservaModel;
use App\Models\FolioModel;
use App\Models\MedioModel;

/** Catálogo de experiencias y agenda de salidas. */
class Experiencias extends BaseController
{
    private ExperienciaModel $experiencias;
    private ExperienciaReservaModel $salidas;

    public function __construct()
    {
        $this->experiencias = new ExperienciaModel();
        $this->salidas      = new ExperienciaReservaModel();
    }

    // ─────────────────────────────────────────────────────────────
    //  Agenda: lo que ve recepción cada día
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        $fecha = (string) ($this->request->getGet('fecha') ?: date('Y-m-d'));
        if (strtotime($fecha) === false) {
            $fecha = date('Y-m-d');
        }

        return view('experiencias/agenda', [
            'titulo'       => 'Experiencias',
            'seccion'      => 'experiencias',
            'fecha'        => $fecha,
            'salidas'      => $this->salidas->delDia($fecha),
            'porConfirmar' => $this->salidas->porConfirmar(),
            'catalogo'     => $this->experiencias->activas(),
            'semana'       => $this->resumenSemana($fecha),
        ]);
    }

    /** Cuántas salidas y personas hay cada día de la semana de esa fecha. */
    private function resumenSemana(string $fecha): array
    {
        $lunes   = date('Y-m-d', strtotime('monday this week', strtotime($fecha)));
        $domingo = date('Y-m-d', strtotime('sunday this week', strtotime($fecha)));

        $dias = [];
        for ($d = $lunes; $d <= $domingo; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $dias[$d] = ['salidas' => 0, 'personas' => 0];
        }

        foreach ($this->salidas->agenda($lunes, $domingo) as $r) {
            if (! in_array($r['estado'], ExperienciaReservaModel::ESTADOS_VIVOS, true)) {
                continue;
            }
            $dias[$r['fecha']]['salidas']++;
            $dias[$r['fecha']]['personas'] += (int) $r['adultos'] + (int) $r['ninos'];
        }

        return $dias;
    }

    // ─────────────────────────────────────────────────────────────
    //  Catálogo
    // ─────────────────────────────────────────────────────────────

    public function catalogo()
    {
        $medios   = new MedioModel();
        $portadas = [];
        foreach ($this->experiencias->findAll() as $e) {
            $galeria = $medios->where('experiencia_id', $e['id'])
                ->orderBy('portada', 'DESC')->orderBy('orden')->findAll();
            $portadas[$e['id']] = ['portada' => $galeria[0] ?? null, 'total' => count($galeria)];
        }

        return view('experiencias/catalogo', [
            'titulo'   => 'Catálogo de experiencias',
            'seccion'  => 'experiencias',
            'lista'    => $this->experiencias->orderBy('orden')->orderBy('nombre')->findAll(),
            'portadas' => $portadas,
        ]);
    }

    public function nueva()
    {
        return view('experiencias/form', [
            'titulo'  => 'Nueva experiencia',
            'seccion' => 'experiencias',
        ]);
    }

    public function guardar()
    {
        $datos = $this->datos();

        $id = $this->experiencias->insert($datos);
        if ($id === false) {
            return redirect()->back()->withInput()->with('errores', $this->experiencias->errors());
        }

        return redirect()->to('experiencias/ficha/' . $id)
            ->with('ok', 'Experiencia creada. Ahora súbele fotos: es lo que la vende.');
    }

    public function ficha(int $id)
    {
        $exp = $this->experiencias->find($id);
        if ($exp === null) {
            return redirect()->to('experiencias/catalogo')->with('error', 'La experiencia no existe.');
        }

        $desde = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime('+60 days'));

        return view('experiencias/ficha', [
            'titulo'      => $exp['nombre'],
            'seccion'     => 'experiencias',
            'exp'         => $exp,
            'medios'      => (new MedioModel())->where('experiencia_id', $id)
                ->orderBy('portada', 'DESC')->orderBy('orden')->findAll(),
            'proximas'    => $this->salidas->agenda($desde, $hasta, $id),
            'realizadas'  => $this->contarRealizadas($id),
        ]);
    }

    public function editar(int $id)
    {
        $exp = $this->experiencias->find($id);
        if ($exp === null) {
            return redirect()->to('experiencias/catalogo')->with('error', 'La experiencia no existe.');
        }

        return view('experiencias/form', [
            'titulo'  => 'Editar ' . $exp['nombre'],
            'seccion' => 'experiencias',
            'exp'     => $exp,
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->experiencias->find($id) === null) {
            return redirect()->to('experiencias/catalogo')->with('error', 'La experiencia no existe.');
        }

        if (! $this->experiencias->update($id, $this->datos())) {
            return redirect()->back()->withInput()->with('errores', $this->experiencias->errors());
        }

        return redirect()->to('experiencias/ficha/' . $id)->with('ok', 'Experiencia actualizada.');
    }

    public function eliminar(int $id)
    {
        $vendidas = $this->salidas->where('experiencia_id', $id)->countAllResults();

        if ($vendidas > 0) {
            $this->experiencias->update($id, ['activa' => 0, 'publicada' => 0]);

            return redirect()->to('experiencias/catalogo')->with(
                'ok',
                'Esa experiencia ya se ha vendido ' . $vendidas . ' vez' . ($vendidas > 1 ? 'es' : '')
                    . ', así que se desactivó en vez de borrarla (se conserva el historial).'
            );
        }

        $this->experiencias->delete($id);

        return redirect()->to('experiencias/catalogo')->with('ok', 'Experiencia eliminada.');
    }

    private function datos(): array
    {
        $horas = array_filter(array_map('trim', explode(',', (string) $this->request->getPost('horarios'))));
        $horas = array_values(array_filter($horas, static fn ($h) => preg_match('/^\d{1,2}:\d{2}$/', $h) === 1));

        $dias = array_filter(array_map('intval', (array) $this->request->getPost('dias')));
        sort($dias);

        return [
            'nombre'          => trim((string) $this->request->getPost('nombre')),
            'descripcion'     => trim((string) $this->request->getPost('descripcion')) ?: null,
            'incluye'         => trim((string) $this->request->getPost('incluye')) ?: null,
            'no_incluye'      => trim((string) $this->request->getPost('no_incluye')) ?: null,
            'categoria'       => array_key_exists((string) $this->request->getPost('categoria'), ExperienciaModel::CATEGORIAS)
                ? (string) $this->request->getPost('categoria') : 'Naturaleza',
            'tipo_precio'     => $this->request->getPost('tipo_precio') === 'grupo' ? 'grupo' : 'persona',
            'precio'          => (float) $this->request->getPost('precio'),
            'precio_nino'     => trim((string) $this->request->getPost('precio_nino')) !== ''
                ? (float) $this->request->getPost('precio_nino') : null,
            'coste'           => (float) $this->request->getPost('coste'),
            'duracion_min'    => max(5, (int) $this->request->getPost('duracion_min')),
            'capacidad'       => max(1, (int) $this->request->getPost('capacidad')),
            'minimo'          => max(1, (int) $this->request->getPost('minimo')),
            'edad_minima'     => trim((string) $this->request->getPost('edad_minima')) !== ''
                ? (int) $this->request->getPost('edad_minima') : null,
            'horarios'        => implode(',', $horas) ?: null,
            'dias'            => implode(',', $dias) ?: '1,2,3,4,5,6,7',
            'aviso_horas'     => max(0, (int) $this->request->getPost('aviso_horas')),
            'punto_encuentro' => trim((string) $this->request->getPost('punto_encuentro')) ?: null,
            'proveedor'       => trim((string) $this->request->getPost('proveedor')) ?: null,
            'notas_internas'  => trim((string) $this->request->getPost('notas_internas')) ?: null,
            'activa'          => $this->request->getPost('activa') !== null ? 1 : 0,
            'publicada'       => $this->request->getPost('publicada') !== null ? 1 : 0,
            'orden'           => (int) $this->request->getPost('orden'),
        ];
    }

    private function contarRealizadas(int $id): array
    {
        $filas = $this->salidas->select('estado, COUNT(*) AS total, SUM(total) AS importe')
            ->where('experiencia_id', $id)
            ->groupBy('estado')
            ->findAll();

        $conteo = ['realizadas' => 0, 'ingresos' => 0.0];
        foreach ($filas as $f) {
            if ($f['estado'] === 'realizada') {
                $conteo['realizadas'] = (int) $f['total'];
                $conteo['ingresos']   = (float) $f['importe'];
            }
        }

        return $conteo;
    }

    // ─────────────────────────────────────────────────────────────
    //  Galería
    // ─────────────────────────────────────────────────────────────

    public function subirFoto(int $id)
    {
        if ($this->experiencias->find($id) === null) {
            return redirect()->to('experiencias/catalogo')->with('error', 'La experiencia no existe.');
        }

        $r = (new Galeria())->subirFotoExperiencia(
            $this->request->getFile('foto'),
            $id,
            (string) $this->request->getPost('alt')
        );

        return redirect()->to('experiencias/ficha/' . $id . '#galeria')->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    public function anadirVideo(int $id)
    {
        if ($this->experiencias->find($id) === null) {
            return redirect()->to('experiencias/catalogo')->with('error', 'La experiencia no existe.');
        }

        $medios = new MedioModel();
        $url    = trim((string) $this->request->getPost('url'));

        if (MedioModel::embebido($url) === null) {
            return redirect()->to('experiencias/ficha/' . $id . '#galeria')
                ->with('error', 'Pega el enlace de un vídeo de YouTube o Vimeo.');
        }

        $medios->insert([
            'experiencia_id' => $id,
            'publico'        => 1,
            'tipo'           => 'video',
            'url'            => $url,
            'titulo'         => trim((string) $this->request->getPost('titulo')) ?: null,
            'orden'          => 99,
            'usuario_id'     => session()->get('usuario_id'),
        ]);

        return redirect()->to('experiencias/ficha/' . $id . '#galeria')->with('ok', 'Vídeo añadido.');
    }

    public function portadaFoto(int $medioId)
    {
        $medios = new MedioModel();
        $medio  = $medios->find($medioId);
        if ($medio === null || $medio['experiencia_id'] === null) {
            return redirect()->to('experiencias/catalogo')->with('error', 'Esa foto no existe.');
        }

        $medios->builder()->where('experiencia_id', $medio['experiencia_id'])->update(['portada' => 0]);
        $medios->update($medioId, ['portada' => 1]);

        return redirect()->to('experiencias/ficha/' . $medio['experiencia_id'] . '#galeria')->with('ok', 'Portada cambiada.');
    }

    public function eliminarFoto(int $medioId)
    {
        $medio = (new MedioModel())->find($medioId);
        if ($medio === null || $medio['experiencia_id'] === null) {
            return redirect()->to('experiencias/catalogo')->with('error', 'Esa foto no existe.');
        }

        (new Galeria())->eliminar($medioId);

        return redirect()->to('experiencias/ficha/' . $medio['experiencia_id'] . '#galeria')->with('ok', 'Elemento eliminado.');
    }

    // ─────────────────────────────────────────────────────────────
    //  Venta
    // ─────────────────────────────────────────────────────────────

    /** Apunta a alguien en una salida. */
    public function reservar()
    {
        $expId = (int) $this->request->getPost('experiencia_id');
        $exp   = $this->experiencias->find($expId);

        if ($exp === null) {
            return redirect()->back()->with('error', 'Esa experiencia no existe.');
        }

        $fecha   = (string) $this->request->getPost('fecha');
        $hora    = trim((string) $this->request->getPost('hora')) ?: null;
        $adultos = max(1, (int) $this->request->getPost('adultos'));
        $ninos   = max(0, (int) $this->request->getPost('ninos'));

        if (strtotime($fecha) === false) {
            return redirect()->back()->withInput()->with('error', 'Revisa la fecha.');
        }
        if ($fecha < date('Y-m-d')) {
            return redirect()->back()->withInput()->with('error', 'No se pueden apuntar salidas en días pasados.');
        }
        if (! ExperienciaModel::seHace($exp, $fecha)) {
            return redirect()->back()->withInput()->with(
                'error',
                esc($exp['nombre']) . ' no se hace ese día de la semana (' . ExperienciaModel::textoDias($exp) . ').'
            );
        }

        // El cupo es de verdad: no se puede vender más de lo que cabe
        $libres = $this->salidas->plazasLibres($exp, $fecha, $hora);
        if ($adultos + $ninos > $libres) {
            return redirect()->back()->withInput()->with(
                'error',
                $libres === 0
                    ? 'Esa salida está completa.'
                    : 'Solo quedan ' . $libres . ' plaza' . ($libres > 1 ? 's' : '') . ' en esa salida.'
            );
        }

        $reservaId  = (int) $this->request->getPost('reserva_id') ?: null;
        $huespedId  = null;

        if ($reservaId !== null) {
            $reserva = (new \App\Models\ReservaModel())->find($reservaId);
            if ($reserva === null) {
                return redirect()->back()->with('error', 'Esa reserva no existe.');
            }
            $huespedId = (int) $reserva['huesped_id'];
        }

        $id = $this->salidas->insert([
            'experiencia_id'  => $expId,
            'reserva_id'      => $reservaId,
            'huesped_id'      => $huespedId,
            'cliente_nombre'  => $reservaId === null ? trim((string) $this->request->getPost('cliente_nombre')) ?: null : null,
            'cliente_telefono' => $reservaId === null ? trim((string) $this->request->getPost('cliente_telefono')) ?: null : null,
            'fecha'           => $fecha,
            'hora'            => $hora,
            'adultos'         => $adultos,
            'ninos'           => $ninos,
            'precio_unitario' => (float) $exp['precio'],
            'precio_nino'     => $exp['precio_nino'],
            'total'           => ExperienciaModel::calcularTotal($exp, $adultos, $ninos),
            'estado'          => 'confirmada',
            'notas'           => trim((string) $this->request->getPost('notas')) ?: null,
            'usuario_id'      => session()->get('usuario_id'),
        ]);

        $aviso = '';
        if ($adultos + $ninos < (int) $exp['minimo']) {
            $aviso = ' Ojo: esta salida pide un mínimo de ' . (int) $exp['minimo']
                . ' personas y ahora mismo hay menos.';
        }

        return redirect()->to($reservaId !== null ? 'reservas/ver/' . $reservaId : 'experiencias?fecha=' . $fecha)
            ->with('ok', 'Apuntado a ' . $exp['nombre'] . ' el ' . date('d/m', strtotime($fecha))
                . ($hora !== null ? ' a las ' . substr($hora, 0, 5) : '') . '.' . $aviso);
    }

    /**
     * Cambia el estado. Al marcarla como realizada se carga al folio:
     * se cobra lo que de verdad se hizo, no lo que se apuntó.
     */
    public function estado(int $id)
    {
        $salida = $this->salidas->find($id);
        if ($salida === null) {
            return redirect()->back()->with('error', 'Esa salida no existe.');
        }

        $nuevo = (string) $this->request->getPost('estado');
        if (! array_key_exists($nuevo, ExperienciaReservaModel::ESTADOS)) {
            return redirect()->back()->with('error', 'Estado no válido.');
        }

        $exp    = $this->experiencias->find((int) $salida['experiencia_id']);
        $cambio = ['estado' => $nuevo, 'motivo' => trim((string) $this->request->getPost('motivo')) ?: null];
        $aviso  = '';

        if ($nuevo === 'realizada' && $salida['folio_movimiento_id'] === null) {
            if ($salida['reserva_id'] !== null && (int) $salida['cobrado_aparte'] !== 1) {
                $folioId = (new FolioModel())->insert([
                    'reserva_id' => $salida['reserva_id'],
                    'tipo'       => 'cargo',
                    'concepto'   => $exp['nombre'] . ' (' . ((int) $salida['adultos'] + (int) $salida['ninos']) . ' pers.)',
                    'valor'      => $salida['total'],
                    'usuario_id' => session()->get('usuario_id'),
                ]);
                $cambio['folio_movimiento_id'] = $folioId;
                $aviso = ' Se cargaron $' . number_format((float) $salida['total'], 0, ',', '.') . ' al folio.';
            } else {
                $aviso = ' Cóbralo aparte: no hay folio al que cargarlo.';
            }
        }

        $this->salidas->update($id, $cambio);

        return redirect()->back()->with('ok', 'Salida marcada como '
            . mb_strtolower(ExperienciaReservaModel::ESTADOS[$nuevo]) . '.' . $aviso);
    }

    /** Plazas libres de una salida, para el formulario de venta. */
    public function disponibilidad()
    {
        $exp = $this->experiencias->find((int) $this->request->getGet('experiencia'));
        if ($exp === null) {
            return $this->response->setJSON(['ok' => false]);
        }

        $fecha = (string) $this->request->getGet('fecha');
        if (strtotime($fecha) === false) {
            return $this->response->setJSON(['ok' => false]);
        }

        $horarios = ExperienciaModel::horariosDe($exp);
        $salidas  = [];

        foreach ($horarios ?: [null] as $hora) {
            $salidas[] = [
                'hora'   => $hora,
                'libres' => $this->salidas->plazasLibres($exp, $fecha, $hora),
            ];
        }

        return $this->response->setJSON([
            'ok'       => true,
            'se_hace'  => ExperienciaModel::seHace($exp, $fecha),
            'dias'     => ExperienciaModel::textoDias($exp),
            'capacidad' => (int) $exp['capacidad'],
            'salidas'  => $salidas,
            'precio'   => (float) $exp['precio'],
            'precio_nino' => $exp['precio_nino'] !== null ? (float) $exp['precio_nino'] : null,
            'tipo_precio' => $exp['tipo_precio'],
        ]);
    }
}
