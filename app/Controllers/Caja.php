<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Cajas;
use App\Models\CajaMovimientoModel;
use App\Models\CajaTurnoModel;
use App\Models\MedioPagoModel;
use App\Models\PuntoCajaModel;
use RuntimeException;

/**
 * Cajas por punto: turnos, movimientos, retiros y arqueo.
 *
 * El controlador solo traduce entre el formulario y la librería. Las reglas
 * —qué entra en el cajón, cuánto debería haber, cuándo hay que explicar un
 * descuadre— viven en `App\Libraries\Cajas`, porque el TPV va a apuntar cobros
 * ahí sin pasar por esta pantalla.
 */
class Caja extends BaseController
{
    private CajaTurnoModel $turnos;
    private CajaMovimientoModel $movimientos;
    private Cajas $cajas;

    public function __construct()
    {
        $this->turnos      = new CajaTurnoModel();
        $this->movimientos = new CajaMovimientoModel();
        $this->cajas       = new Cajas();
    }

    public function index()
    {
        $puntos = (new PuntoCajaModel())->activos();

        // El punto elegido se recuerda en la sesión: quien trabaja en el bar no
        // quiere elegirlo cada vez que abre la pantalla.
        $puntoId = (int) ($this->request->getGet('punto') ?: session()->get('caja_punto') ?: 0);

        if ($puntoId <= 0 || ! in_array($puntoId, array_map('intval', array_column($puntos, 'id')), true)) {
            $porDefecto = (new PuntoCajaModel())->porDefecto();
            $puntoId    = $porDefecto !== null ? (int) $porDefecto['id'] : 0;
        }

        session()->set('caja_punto', $puntoId);

        $turno = $puntoId > 0 ? $this->turnos->abierto($puntoId) : null;

        $datos = [
            'titulo'     => 'Caja',
            'seccion'    => 'caja',
            'puntos'     => $puntos,
            'punto_id'   => $puntoId,
            'punto'      => $puntoId > 0 ? (new PuntoCajaModel())->find($puntoId) : null,
            'turno'      => $turno,
            'abiertos'   => $this->turnos->abiertos(),
            'historial'  => $this->turnos->historial(10, $puntoId ?: null),
            'medios'     => (new MedioPagoModel())->disponibles('recepcion'),
            'denominaciones' => Cajas::DENOMINACIONES,
        ];

        if ($turno !== null) {
            $datos['totales']  = $this->cajas->totales((int) $turno['id']);
            $datos['esperado'] = $this->cajas->esperado((int) $turno['id']);
            $datos['lista']    = $this->movimientos->delTurno((int) $turno['id']);
        }

        return view('caja/index', $datos);
    }

    public function abrir()
    {
        $puntoId = (int) $this->request->getPost('punto_id');

        try {
            $this->cajas->abrir(
                $puntoId,
                (int) session()->get('usuario_id'),
                (float) $this->request->getPost('base_inicial')
            );
        } catch (RuntimeException $e) {
            return redirect()->to('caja?punto=' . $puntoId)->with('error', $e->getMessage());
        }

        session()->set('caja_punto', $puntoId);

        return redirect()->to('caja')->with('ok', 'Turno abierto.');
    }

    public function movimiento()
    {
        $puntoId = (int) session()->get('caja_punto');
        $tipo    = (string) $this->request->getPost('tipo');

        try {
            $this->cajas->apuntar(
                $puntoId,
                $tipo === 'egreso' ? 'egreso' : 'ingreso',
                (string) $this->request->getPost('concepto'),
                (float) $this->request->getPost('valor'),
                (string) $this->request->getPost('medio') ?: null,
                (string) $this->request->getPost('referencia') ?: null,
                session()->get('usuario_id')
            );
        } catch (RuntimeException $e) {
            return redirect()->to('caja')->with('error', $e->getMessage());
        }

        return redirect()->to('caja')->with('ok', 'Movimiento apuntado.');
    }

    /**
     * Retirar efectivo a la caja fuerte.
     *
     * Va en su propio permiso: quien puede retirar puede vaciar el cajón.
     */
    public function retirar()
    {
        $puntoId = (int) session()->get('caja_punto');

        try {
            $this->cajas->retirar(
                $puntoId,
                (float) $this->request->getPost('valor'),
                (string) $this->request->getPost('motivo') ?: 'Retiro a la caja fuerte',
                session()->get('usuario_id')
            );
        } catch (RuntimeException $e) {
            return redirect()->to('caja')->with('error', $e->getMessage());
        }

        return redirect()->to('caja')->with('ok', 'Retiro apuntado. La plata sale del cajón pero sigue siendo del hotel.');
    }

    public function cerrar()
    {
        $puntoId = (int) session()->get('caja_punto');
        $turno   = $this->turnos->abierto($puntoId);

        if ($turno === null) {
            return redirect()->to('caja')->with('error', 'No hay ningún turno abierto en este punto.');
        }

        // El conteo llega billete a billete: el total lo calcula el sistema.
        $conteo = [];

        foreach (Cajas::DENOMINACIONES as $d) {
            $conteo[$d] = (int) $this->request->getPost('d' . $d);
        }

        try {
            $r = $this->cajas->cerrar(
                (int) $turno['id'],
                $conteo,
                session()->get('usuario_id'),
                (string) $this->request->getPost('justificacion')
            );
        } catch (RuntimeException $e) {
            return redirect()->to('caja')->with('error', $e->getMessage());
        }

        $mensaje = sprintf(
            'Turno cerrado. Contado $%s, esperado $%s. ',
            number_format($r['contado'], 0, ',', '.'),
            number_format($r['esperado'], 0, ',', '.')
        );

        if (abs($r['diferencia']) < 0.01) {
            $mensaje .= 'La caja cuadró.';
        } else {
            $mensaje .= ($r['diferencia'] < 0 ? 'Faltan $' : 'Sobran $')
                . number_format(abs($r['diferencia']), 0, ',', '.') . '.';
        }

        return redirect()->to('caja')->with(abs($r['diferencia']) < 0.01 ? 'ok' : 'error', $mensaje);
    }

    /** El detalle de un turno cerrado, con su conteo. */
    public function turno(int $id)
    {
        $turno = $this->turnos
            ->select('caja_turnos.*, usuarios.nombre AS usuario_nombre, cierra.nombre AS cerro_nombre,
                      puntos_caja.nombre AS punto_nombre')
            ->join('usuarios', 'usuarios.id = caja_turnos.usuario_id', 'left')
            ->join('usuarios AS cierra', 'cierra.id = caja_turnos.cerro_id', 'left')
            ->join('puntos_caja', 'puntos_caja.id = caja_turnos.punto_id', 'left')
            ->where('caja_turnos.id', $id)
            ->first();

        if ($turno === null) {
            return redirect()->to('caja')->with('error', 'Ese turno no existe.');
        }

        return view('caja/turno', [
            'titulo'  => 'Turno del ' . date('d/m/Y', strtotime($turno['apertura'])),
            'seccion' => 'caja',
            'turno'   => $turno,
            'lista'   => $this->movimientos->delTurno($id),
            'conteo'  => $this->cajas->conteoDe($id),
            'totales' => $this->cajas->totales($id),
            'denominaciones' => Cajas::DENOMINACIONES,
        ]);
    }

    // ── Configuración ───────────────────────────────────────────────────

    public function configurar()
    {
        return view('caja/configurar', [
            'titulo'  => 'Puntos de caja y medios de pago',
            'seccion' => 'caja',
            'puntos'  => (new PuntoCajaModel())->orderBy('nombre')->findAll(),
            'medios'  => (new MedioPagoModel())->listar(),
            'tipos_punto' => PuntoCajaModel::TIPOS,
            'tipos_medio' => MedioPagoModel::TIPOS,
        ]);
    }

    public function guardarPunto(?int $id = null)
    {
        $puntos = new PuntoCajaModel();

        $datos = [
            'nombre'        => trim((string) $this->request->getPost('nombre')),
            'tipo'          => array_key_exists((string) $this->request->getPost('tipo'), PuntoCajaModel::TIPOS)
                ? (string) $this->request->getPost('tipo') : 'otro',
            'base_sugerida' => max(0, (float) $this->request->getPost('base_sugerida')),
            'tolerancia'    => max(0, (float) $this->request->getPost('tolerancia')),
            'exige_denominaciones' => $this->request->getPost('exige_denominaciones') !== null ? 1 : 0,
            'activo'        => $this->request->getPost('activo') !== null ? 1 : 0,
        ];

        if ($id === null) {
            $clave          = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $datos['nombre']));
            $datos['clave'] = trim($clave, '-') ?: 'punto-' . random_int(100, 999);

            if ($puntos->where('clave', $datos['clave'])->countAllResults() > 0) {
                $datos['clave'] .= '-' . random_int(10, 99);
            }

            if (! $puntos->insert($datos)) {
                return redirect()->to('caja/configurar')->with('errores', $puntos->errors());
            }

            return redirect()->to('caja/configurar')->with('ok', 'Punto de caja creado.');
        }

        if (! $puntos->update($id, $datos)) {
            return redirect()->to('caja/configurar')->with('errores', $puntos->errors());
        }

        return redirect()->to('caja/configurar')->with('ok', 'Punto guardado.');
    }

    public function guardarMedio(int $id)
    {
        $medios = new MedioPagoModel();
        $medio  = $medios->find($id);

        if ($medio === null) {
            return redirect()->to('caja/configurar')->with('error', 'Ese medio no existe.');
        }

        $afecta = $this->request->getPost('afecta_caja') !== null ? 1 : 0;

        $medios->update($id, [
            'nombre'              => trim((string) $this->request->getPost('nombre')),
            'afecta_caja'         => $afecta,
            'requiere_referencia' => $this->request->getPost('requiere_referencia') !== null ? 1 : 0,
            'comision_pct'        => max(0, min(20, (float) $this->request->getPost('comision_pct'))),
            'cuenta_contable'     => trim((string) $this->request->getPost('cuenta_contable')) ?: null,
            'en_recepcion'        => $this->request->getPost('en_recepcion') !== null ? 1 : 0,
            'en_tpv'              => $this->request->getPost('en_tpv') !== null ? 1 : 0,
            'en_web'              => $this->request->getPost('en_web') !== null ? 1 : 0,
            'activo'              => $this->request->getPost('activo') !== null ? 1 : 0,
        ]);

        // Cambiar esto recoloca todos los arqueos futuros: conviene decirlo en
        // el momento, no cuando la caja empiece a descuadrar.
        $aviso = $afecta !== (int) $medio['afecta_caja']
            ? ($afecta === 1
                ? ' Ojo: ahora «' . $medio['nombre'] . '» se contará dentro del cajón en los arqueos.'
                : ' Ojo: ahora «' . $medio['nombre'] . '» deja de contarse dentro del cajón.')
            : '';

        return redirect()->to('caja/configurar')->with('ok', 'Medio de pago guardado.' . $aviso);
    }
}
