<?php

namespace App\Controllers;

use App\Libraries\MotorTarifas;
use App\Models\ReglaPrecioModel;
use App\Models\TarifaTemporadaModel;
use App\Models\TemporadaModel;
use App\Models\TipoUnidadModel;

/** Gestión del motor de tarifas: temporadas, reglas, calendario y simulador. */
class Tarifas extends BaseController
{
    private TemporadaModel $temporadas;
    private ReglaPrecioModel $reglas;
    private TarifaTemporadaModel $precios;
    private TipoUnidadModel $tipos;

    public function __construct()
    {
        $this->temporadas = new TemporadaModel();
        $this->reglas     = new ReglaPrecioModel();
        $this->precios    = new TarifaTemporadaModel();
        $this->tipos      = new TipoUnidadModel();
    }

    // ─────────────────────────────────────────────────────────────
    //  Pantalla principal
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        return view('tarifas/index', [
            'titulo'     => 'Tarifas',
            'seccion'    => 'tarifas',
            'tipos'      => $this->tipos->orderBy('nombre')->findAll(),
            'temporadas' => $this->temporadas->conPrecios(),
            'reglas'     => $this->reglas->listado(),
            'precios'    => $this->preciosPorTemporada(),
        ]);
    }

    /** Precios cerrados agrupados: [temporada_id][tipo_unidad_id] = precio. */
    private function preciosPorTemporada(): array
    {
        $mapa = [];
        foreach ($this->precios->findAll() as $p) {
            $mapa[(int) $p['temporada_id']][(int) $p['tipo_unidad_id']] = (float) $p['precio'];
        }

        return $mapa;
    }

    // ─────────────────────────────────────────────────────────────
    //  Temporadas
    // ─────────────────────────────────────────────────────────────

    public function guardarTemporada()
    {
        $datos = $this->datosTemporada();

        if (($error = $this->fechasCoherentes($datos)) !== null) {
            return redirect()->to('tarifas')->withInput()->with('error', $error);
        }

        if (! $this->temporadas->insert($datos)) {
            return redirect()->to('tarifas')->withInput()->with('errores', $this->temporadas->errors());
        }

        return redirect()->to('tarifas')->with('ok', 'Temporada creada. Ya se aplica a las nuevas cotizaciones.');
    }

    public function actualizarTemporada(int $id)
    {
        if ($this->temporadas->find($id) === null) {
            return redirect()->to('tarifas')->with('error', 'La temporada no existe.');
        }

        $datos = $this->datosTemporada();

        if (($error = $this->fechasCoherentes($datos)) !== null) {
            return redirect()->to('tarifas/temporada/' . $id)->withInput()->with('error', $error);
        }

        if (! $this->temporadas->update($id, $datos)) {
            return redirect()->to('tarifas/temporada/' . $id)->withInput()->with('errores', $this->temporadas->errors());
        }

        return redirect()->to('tarifas/temporada/' . $id)->with('ok', 'Temporada actualizada.');
    }

    public function temporada(int $id)
    {
        $temporada = $this->temporadas->find($id);
        if ($temporada === null) {
            return redirect()->to('tarifas')->with('error', 'La temporada no existe.');
        }

        return view('tarifas/temporada', [
            'titulo'    => 'Temporada ' . $temporada['nombre'],
            'seccion'   => 'tarifas',
            'temporada' => $temporada,
            'tipos'     => $this->tipos->orderBy('nombre')->findAll(),
            'precios'   => $this->precios->deTemporada($id),
        ]);
    }

    /** Precios cerrados por tipo dentro de la temporada. */
    public function guardarPrecios(int $id)
    {
        if ($this->temporadas->find($id) === null) {
            return redirect()->to('tarifas')->with('error', 'La temporada no existe.');
        }

        $entradas = (array) $this->request->getPost('precio');

        foreach ($this->tipos->findAll() as $tipo) {
            $valor = trim((string) ($entradas[$tipo['id']] ?? ''));
            $this->precios->fijar($id, (int) $tipo['id'], $valor === '' ? null : (float) $valor);
        }

        return redirect()->to('tarifas/temporada/' . $id)
            ->with('ok', 'Precios de la temporada guardados. Un precio cerrado manda sobre el ajuste porcentual.');
    }

    public function alternarTemporada(int $id)
    {
        $temporada = $this->temporadas->find($id);
        if ($temporada === null) {
            return redirect()->to('tarifas')->with('error', 'La temporada no existe.');
        }

        $activa = (int) $temporada['activa'] === 1 ? 0 : 1;
        $this->temporadas->update($id, ['activa' => $activa]);

        return redirect()->to('tarifas')->with(
            'ok',
            $activa === 1 ? 'Temporada activada.' : 'Temporada desactivada: deja de aplicarse desde ahora.'
        );
    }

    public function eliminarTemporada(int $id)
    {
        $this->temporadas->delete($id);

        return redirect()->to('tarifas')->with('ok', 'Temporada eliminada. Las reservas ya hechas conservan su precio.');
    }

    private function datosTemporada(): array
    {
        return [
            'nombre'      => trim((string) $this->request->getPost('nombre')),
            'desde'       => (string) $this->request->getPost('desde'),
            'hasta'       => (string) $this->request->getPost('hasta'),
            'tipo_ajuste' => in_array($this->request->getPost('tipo_ajuste'), ['porcentaje', 'valor', 'fijo'], true)
                ? $this->request->getPost('tipo_ajuste') : 'porcentaje',
            'ajuste'      => (float) $this->request->getPost('ajuste'),
            'prioridad'   => (int) $this->request->getPost('prioridad'),
            'color'       => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->request->getPost('color'))
                ? (string) $this->request->getPost('color') : '#b9873f',
            'activa'      => $this->request->getPost('activa') !== null ? 1 : 0,
        ];
    }

    private function fechasCoherentes(array $datos): ?string
    {
        if ($datos['desde'] === '' || $datos['hasta'] === '') {
            return 'Indica las fechas de inicio y fin de la temporada.';
        }
        if ($datos['hasta'] < $datos['desde']) {
            return 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Reglas
    // ─────────────────────────────────────────────────────────────

    public function guardarRegla()
    {
        if (! $this->reglas->insert($this->datosRegla())) {
            return redirect()->to('tarifas')->withInput()->with('errores', $this->reglas->errors());
        }

        return redirect()->to('tarifas')->with('ok', 'Regla creada.');
    }

    public function actualizarRegla(int $id)
    {
        if ($this->reglas->find($id) === null) {
            return redirect()->to('tarifas')->with('error', 'La regla no existe.');
        }

        if (! $this->reglas->update($id, $this->datosRegla())) {
            return redirect()->to('tarifas')->withInput()->with('errores', $this->reglas->errors());
        }

        return redirect()->to('tarifas')->with('ok', 'Regla actualizada.');
    }

    public function alternarRegla(int $id)
    {
        $regla = $this->reglas->find($id);
        if ($regla === null) {
            return redirect()->to('tarifas')->with('error', 'La regla no existe.');
        }

        $activa = (int) $regla['activa'] === 1 ? 0 : 1;
        $this->reglas->update($id, ['activa' => $activa]);

        return redirect()->to('tarifas')->with('ok', $activa === 1 ? 'Regla activada.' : 'Regla desactivada.');
    }

    public function eliminarRegla(int $id)
    {
        $this->reglas->delete($id);

        return redirect()->to('tarifas')->with('ok', 'Regla eliminada.');
    }

    private function datosRegla(): array
    {
        $tipo = in_array($this->request->getPost('tipo'), array_keys(ReglaPrecioModel::TIPOS), true)
            ? (string) $this->request->getPost('tipo') : 'dia_semana';

        // Días de la semana: se guardan como "5,6"
        $dias = null;
        if ($tipo === 'dia_semana') {
            $marcados = array_filter(
                array_map('intval', (array) $this->request->getPost('dias')),
                static fn ($d) => $d >= 1 && $d <= 7
            );
            sort($marcados);
            $dias = implode(',', $marcados);
        }

        $desde = $this->request->getPost('valor_desde');
        $hasta = $this->request->getPost('valor_hasta');

        return [
            'nombre'         => trim((string) $this->request->getPost('nombre')),
            'tipo'           => $tipo,
            'dias'           => $dias,
            'valor_desde'    => $tipo !== 'dia_semana' && trim((string) $desde) !== '' ? (int) $desde : null,
            'valor_hasta'    => $tipo !== 'dia_semana' && trim((string) $hasta) !== '' ? (int) $hasta : null,
            'tipo_ajuste'    => $this->request->getPost('tipo_ajuste') === 'valor' ? 'valor' : 'porcentaje',
            'ajuste'         => (float) $this->request->getPost('ajuste'),
            'tipo_unidad_id' => (int) $this->request->getPost('tipo_unidad_id') ?: null,
            'prioridad'      => (int) $this->request->getPost('prioridad'),
            'activa'         => $this->request->getPost('activa') !== null ? 1 : 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Calendario de tarifas
    // ─────────────────────────────────────────────────────────────

    public function calendario()
    {
        $tipos = $this->tipos->orderBy('nombre')->findAll();
        if ($tipos === []) {
            return redirect()->to('tipos')->with('error', 'Primero crea al menos un tipo de alojamiento.');
        }

        $tipoId = (int) ($this->request->getGet('tipo') ?: $tipos[0]['id']);
        if (! in_array($tipoId, array_map('intval', array_column($tipos, 'id')), true)) {
            $tipoId = (int) $tipos[0]['id'];
        }

        $mes  = (int) ($this->request->getGet('mes') ?: date('n'));
        $anio = (int) ($this->request->getGet('anio') ?: date('Y'));
        $mes  = max(1, min(12, $mes));
        $anio = max(2020, min(2100, $anio));

        $motor = new MotorTarifas();

        return view('tarifas/calendario', [
            'titulo'     => 'Calendario de tarifas',
            'seccion'    => 'tarifas',
            'tipos'      => $tipos,
            'tipoId'     => $tipoId,
            'mes'        => $mes,
            'anio'       => $anio,
            'dias'       => $motor->mes($tipoId, $anio, $mes),
            'temporadas' => $this->temporadas->activas(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Simulador
    // ─────────────────────────────────────────────────────────────

    public function simulador()
    {
        $tipos = $this->tipos->orderBy('nombre')->findAll();
        if ($tipos === []) {
            return redirect()->to('tipos')->with('error', 'Primero crea al menos un tipo de alojamiento.');
        }

        $entrada = (string) ($this->request->getGet('entrada') ?: date('Y-m-d'));
        $salida  = (string) ($this->request->getGet('salida') ?: date('Y-m-d', strtotime('+2 days')));
        $adultos = max(1, (int) ($this->request->getGet('adultos') ?: 2));
        $ninos   = max(0, (int) ($this->request->getGet('ninos') ?: 0));
        $tipoId  = (int) ($this->request->getGet('tipo') ?: $tipos[0]['id']);

        $cotizacion = null;
        $aviso      = null;

        if ($salida <= $entrada) {
            $aviso = 'La fecha de salida debe ser posterior a la de entrada.';
        } elseif ((new \DateTime($entrada))->diff(new \DateTime($salida))->days > 60) {
            $aviso = 'El simulador trabaja con estancias de hasta 60 noches.';
        } else {
            $cotizacion = (new MotorTarifas())->cotizar($tipoId, $entrada, $salida, $adultos, $ninos);
        }

        return view('tarifas/simulador', [
            'titulo'     => 'Simulador de precios',
            'seccion'    => 'tarifas',
            'tipos'      => $tipos,
            'tipoId'     => $tipoId,
            'entrada'    => $entrada,
            'salida'     => $salida,
            'adultos'    => $adultos,
            'ninos'      => $ninos,
            'cotizacion' => $cotizacion,
            'aviso'      => $aviso,
        ]);
    }
}
