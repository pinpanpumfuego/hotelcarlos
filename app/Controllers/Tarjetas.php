<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Qr;
use App\Models\HuespedModel;
use App\Models\TarjetaModel;
use App\Models\TarjetaMovimientoModel;
use App\Models\TipoTarjetaModel;
use RuntimeException;

/**
 * Tarjetas de saldo con QR.
 *
 * El QR **identifica, no autoriza**: lleva el código de la tarjeta y nada más.
 * Cobrar lo hace siempre una persona desde el panel o el TPV, y por encima de
 * un importe hace falta el PIN. Un QR se fotografía sin querer en cualquier
 * mesa; si bastara para pagar, la primera foto sería el último saldo.
 */
class Tarjetas extends BaseController
{
    private TarjetaModel $tarjetas;
    private \App\Libraries\Tarjetas $motor;

    public function __construct()
    {
        $this->tarjetas = new TarjetaModel();
        $this->motor    = new \App\Libraries\Tarjetas();
    }

    public function index()
    {
        $filtros = [
            'estado'  => (string) $this->request->getGet('estado'),
            'tipo_id' => (int) $this->request->getGet('tipo_id'),
            'buscar'  => (string) $this->request->getGet('buscar'),
        ];

        return view('tarjetas/index', [
            'titulo'     => 'Tarjetas de saldo',
            'seccion'    => 'tarjetas',
            'tarjetas'   => $this->tarjetas->listar($filtros),
            'filtros'    => $filtros,
            'tipos'      => (new TipoTarjetaModel())->activos(),
            'estados'    => TarjetaModel::ESTADOS,
            'circulacion' => $this->tarjetas->saldoEnCirculacion(),
            'pendientes' => $this->tarjetas->pendientesDeRecarga(),
            'descuadres' => puede('tarjetas.gestionar') ? $this->motor->descuadres() : [],
        ]);
    }

    public function ver(int $id)
    {
        $tarjeta = $this->tarjetas->detalle($id);

        if ($tarjeta === null) {
            return redirect()->to('tarjetas')->with('error', 'Esa tarjeta no existe.');
        }

        return view('tarjetas/ver', [
            'titulo'      => $tarjeta['titular'],
            'seccion'     => 'tarjetas',
            't'           => $tarjeta,
            'movimientos' => (new TarjetaMovimientoModel())->deTarjeta($id),
            'tipos_mov'   => TarjetaMovimientoModel::TIPOS,
            'origenes'    => TarjetaMovimientoModel::ORIGENES,
            'estados'     => TarjetaModel::ESTADOS,
            'enlace'      => site_url('tarjeta/' . $tarjeta['codigo']),
        ]);
    }

    // ── Emitir ──────────────────────────────────────────────────────────

    public function nueva()
    {
        return view('tarjetas/nueva', [
            'titulo'    => 'Nueva tarjeta',
            'seccion'   => 'tarjetas',
            'tipos'     => (new TipoTarjetaModel())->activos(),
            'huespedes' => (new HuespedModel())->activos()->orderBy('apellidos')->findAll(300),
            'cuentas'   => (new \App\Models\CuentaCarteraModel())->where('estado', 'activa')->orderBy('nombre')->findAll(),
        ]);
    }

    public function emitir()
    {
        $huespedId = (int) $this->request->getPost('huesped_id') ?: null;
        $titular   = trim((string) $this->request->getPost('titular'));

        // Si se eligió un huésped y no se escribió nombre, se toma el suyo:
        // pedirlo dos veces es pedir que alguien se equivoque.
        if ($titular === '' && $huespedId !== null) {
            $h = (new HuespedModel())->find($huespedId);

            if ($h !== null) {
                $titular = trim($h['nombre'] . ' ' . $h['apellidos']);
            }
        }

        $descuento = $this->request->getPost('descuento_pct');

        try {
            $id = $this->motor->emitir((int) $this->request->getPost('tipo_id'), $titular, [
                'huesped_id' => $huespedId,
                'cuenta_id'  => (int) $this->request->getPost('cuenta_id') ?: null,
                'pin'        => (string) $this->request->getPost('pin'),
                // Solo quien puede gestionar pone un descuento propio: es
                // crear un precio nuevo para una persona.
                'descuento_pct' => $descuento !== null && $descuento !== '' && puede('tarjetas.gestionar')
                    ? max(0, min(100, (float) $descuento))
                    : null,
                'recarga_mensual' => (float) $this->request->getPost('recarga_mensual'),
                'notas'      => trim((string) $this->request->getPost('notas')) ?: null,
                'usuario_id' => session()->get('usuario_id'),
            ]);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        // Carga inicial en el mismo paso, que es como se entrega una tarjeta
        $inicial = (float) $this->request->getPost('carga_inicial');

        if ($inicial > 0 && puede('tarjetas.cargar')) {
            try {
                $this->motor->cargar(
                    $id,
                    $inicial,
                    (string) $this->request->getPost('origen') ?: 'efectivo',
                    (string) $this->request->getPost('referencia') ?: null,
                    session()->get('usuario_id')
                );
            } catch (RuntimeException $e) {
                return redirect()->to('tarjetas/ver/' . $id)
                    ->with('error', 'La tarjeta se creó pero la carga falló: ' . $e->getMessage());
            }
        }

        return redirect()->to('tarjetas/ver/' . $id)
            ->with('ok', 'Tarjeta emitida. Imprímela y entrégasela.');
    }

    // ── Cargar ──────────────────────────────────────────────────────────

    public function cargar(int $id)
    {
        try {
            $r = $this->motor->cargar(
                $id,
                (float) $this->request->getPost('valor'),
                (string) $this->request->getPost('origen') ?: 'efectivo',
                (string) $this->request->getPost('referencia') ?: null,
                session()->get('usuario_id')
            );
        } catch (RuntimeException $e) {
            return redirect()->to('tarjetas/ver/' . $id)->with('error', $e->getMessage());
        }

        $aviso = $r['bonus'] > 0
            ? ' Se le regalaron $' . number_format($r['bonus'], 0, ',', '.') . ' más.'
            : '';

        return redirect()->to('tarjetas/ver/' . $id)->with('ok', sprintf(
            'Cargados $%s.%s Saldo: $%s.',
            number_format($r['cargado'], 0, ',', '.'),
            $aviso,
            number_format($r['saldo'], 0, ',', '.')
        ));
    }

    /**
     * Recarga a todas las que la tienen programada.
     *
     * A mano y no sola: soltar dinero en cincuenta tarjetas sin que nadie
     * pulse un botón es la clase de automatismo que se descubre tarde.
     */
    public function recargaMensual()
    {
        $hechas = 0;
        $fallos = [];

        foreach ($this->tarjetas->pendientesDeRecarga() as $t) {
            try {
                $this->motor->cargar(
                    (int) $t['id'],
                    (float) $t['recarga_mensual'],
                    'empresa',
                    'Recarga de ' . date('m/Y'),
                    session()->get('usuario_id')
                );
                $hechas++;
            } catch (RuntimeException $e) {
                $fallos[] = $t['codigo'] . ': ' . $e->getMessage();
            }
        }

        $mensaje = $hechas . ' tarjeta(s) recargada(s).';

        if ($fallos !== []) {
            $mensaje .= ' Fallaron: ' . implode(' · ', array_slice($fallos, 0, 5));
        }

        return redirect()->to('tarjetas')->with($fallos === [] ? 'ok' : 'error', $mensaje);
    }

    // ── Estado ──────────────────────────────────────────────────────────

    public function estado(int $id)
    {
        try {
            $this->motor->cambiarEstado(
                $id,
                (string) $this->request->getPost('estado'),
                (string) $this->request->getPost('motivo'),
                session()->get('usuario_id')
            );
        } catch (RuntimeException $e) {
            return redirect()->to('tarjetas/ver/' . $id)->with('error', $e->getMessage());
        }

        return redirect()->to('tarjetas/ver/' . $id)->with('ok', 'Estado cambiado.');
    }

    public function pin(int $id)
    {
        try {
            $this->motor->cambiarPin($id, (string) $this->request->getPost('pin'));
        } catch (RuntimeException $e) {
            return redirect()->to('tarjetas/ver/' . $id)->with('error', $e->getMessage());
        }

        return redirect()->to('tarjetas/ver/' . $id)->with('ok', 'PIN cambiado.');
    }

    // ── QR e impresión ──────────────────────────────────────────────────

    public function qr(int $id)
    {
        $tarjeta = $this->tarjetas->find($id);

        if ($tarjeta === null) {
            return $this->response->setStatusCode(404)->setBody('No existe.');
        }

        return $this->response
            ->setHeader('Content-Type', 'image/svg+xml')
            ->setBody(Qr::svg(site_url('tarjeta/' . $tarjeta['codigo']), 8, 4));
    }

    /** La tarjeta lista para imprimir y plastificar. */
    public function imprimir(int $id)
    {
        $tarjeta = $this->tarjetas->detalle($id);

        if ($tarjeta === null) {
            return redirect()->to('tarjetas')->with('error', 'Esa tarjeta no existe.');
        }

        return view('tarjetas/imprimir', [
            'titulo'  => 'Tarjeta ' . $tarjeta['codigo'],
            't'       => $tarjeta,
            'hotel'   => config('Hotel'),
            'ambitos' => TipoTarjetaModel::AMBITOS,
            'qr'      => Qr::svg(site_url('tarjeta/' . $tarjeta['codigo']), 4, 2),
        ]);
    }

    // ── Cobrar ──────────────────────────────────────────────────────────

    /**
     * Qué pasaría al cobrar, para enseñárselo a quien atiende antes de
     * confirmar. Devuelve JSON porque lo llama el TPV.
     */
    public function simular()
    {
        $r = $this->motor->simular(
            (string) $this->request->getGet('codigo'),
            (float) $this->request->getGet('importe'),
            (string) $this->request->getGet('ambito') ?: 'todo'
        );

        // El saldo entero no se devuelve: quien pregunta puede ser cualquiera
        // con el código, y no tiene por qué saber cuánto hay dentro.
        return $this->response->setJSON([
            'ok'        => $r['ok'],
            'motivo'    => $r['motivo'],
            'titular'   => $r['tarjeta']['titular'] ?? null,
            'descuento' => $r['descuento'],
            'cubre'     => $r['cubre'],
            'falta'     => $r['falta'],
            'pide_pin'  => $r['pide_pin'],
        ]);
    }

    /** Cobra contra el folio de una reserva. */
    public function cobrarFolio(int $reservaId)
    {
        $folio     = new \App\Models\FolioModel();
        $reserva   = (new \App\Models\ReservaModel())->find($reservaId);

        if ($reserva === null) {
            return redirect()->to('reservas')->with('error', 'Esa reserva no existe.');
        }

        $pendiente = round($folio->saldo($reservaId), 2);

        if ($pendiente <= 0) {
            return redirect()->to('reservas/ver/' . $reservaId)->with('error', 'No hay nada pendiente.');
        }

        try {
            $r = $this->motor->cobrar(
                (string) $this->request->getPost('codigo'),
                $pendiente,
                'alojamiento',
                [
                    'pin'        => (string) $this->request->getPost('pin'),
                    'reserva_id' => $reservaId,
                    'usuario_id' => session()->get('usuario_id'),
                    'concepto'   => 'Reserva ' . $reserva['codigo'],
                ]
            );
        } catch (RuntimeException $e) {
            return redirect()->to('reservas/ver/' . $reservaId)->with('error', $e->getMessage());
        }

        // El descuento se apunta como descuento en el folio, no como «pagó
        // menos»: si no, la venta quedaría registrada por menos y nadie sabría
        // nunca cuánto se está regalando en tarjetas.
        if ($r['descuento'] > 0) {
            $folio->insert([
                'reserva_id' => $reservaId,
                'tipo'       => 'descuento',
                'concepto'   => 'Descuento de tarjeta de saldo',
                'valor'      => $r['descuento'],
                'usuario_id' => session()->get('usuario_id'),
            ]);
        }

        $folio->insert([
            'reserva_id' => $reservaId,
            'tipo'       => 'pago',
            'concepto'   => 'Tarjeta de saldo',
            'valor'      => $r['cobrado'],
            'metodo'     => 'tarjeta_saldo',
            'usuario_id' => session()->get('usuario_id'),
        ]);

        $aviso = $r['falta'] > 0
            ? ' Quedan $' . number_format($r['falta'], 0, ',', '.') . ' por cobrar por otro medio.'
            : '';

        return redirect()->to('reservas/ver/' . $reservaId)->with('ok', sprintf(
            'Cobrados $%s de la tarjeta.%s Le queda $%s de saldo.',
            number_format($r['cobrado'], 0, ',', '.'),
            $aviso,
            number_format($r['saldo'], 0, ',', '.')
        ));
    }

    // ── Modalidades ─────────────────────────────────────────────────────

    public function tipos()
    {
        return view('tarjetas/tipos', [
            'titulo'  => 'Modalidades de tarjeta',
            'seccion' => 'tarjetas',
            'tipos'   => (new TipoTarjetaModel())->listar(),
            'ambitos' => TipoTarjetaModel::AMBITOS,
        ]);
    }

    public function guardarTipo(?int $id = null)
    {
        $tipos = new TipoTarjetaModel();

        $datos = [
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'recargable' => $this->request->getPost('recargable') !== null ? 1 : 0,
            'acumula'    => $this->request->getPost('acumula') !== null ? 1 : 0,
            'caduca_meses' => (int) $this->request->getPost('caduca_meses') ?: null,
            'bonus_pct'  => max(0, min(100, (float) $this->request->getPost('bonus_pct'))),
            'descuento_pct' => max(0, min(100, (float) $this->request->getPost('descuento_pct'))),
            'ambito'     => array_key_exists((string) $this->request->getPost('ambito'), TipoTarjetaModel::AMBITOS)
                ? (string) $this->request->getPost('ambito') : 'todo',
            'pin_desde'  => max(0, (float) $this->request->getPost('pin_desde')),
            'activo'     => $this->request->getPost('activo') !== null ? 1 : 0,
        ];

        if ($id === null) {
            $clave = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $datos['nombre']));
            $datos['clave'] = trim($clave, '-') ?: 'tipo-' . random_int(100, 999);

            if ($tipos->where('clave', $datos['clave'])->countAllResults() > 0) {
                $datos['clave'] .= '-' . random_int(10, 99);
            }

            if (! $tipos->insert($datos)) {
                return redirect()->to('tarjetas/tipos')->with('errores', $tipos->errors());
            }

            return redirect()->to('tarjetas/tipos')->with('ok', 'Modalidad creada.');
        }

        if (! $tipos->update($id, $datos)) {
            return redirect()->to('tarjetas/tipos')->with('errores', $tipos->errors());
        }

        // Cambiar el descuento de una modalidad cambia el precio de todas las
        // tarjetas que la usan: conviene decirlo en el momento.
        $cuantas = $this->tarjetas->where('tipo_id', $id)->where('estado', 'activa')->countAllResults();

        $aviso = $cuantas > 0
            ? ' Afecta a ' . $cuantas . ' tarjeta(s) activas.'
            : '';

        return redirect()->to('tarjetas/tipos')->with('ok', 'Modalidad guardada.' . $aviso);
    }
}
