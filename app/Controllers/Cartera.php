<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CarteraMovimientoModel;
use App\Models\CuentaCarteraModel;
use App\Models\MedioPagoModel;
use RuntimeException;

/**
 * Cuentas de empresa y agencia.
 *
 * La pantalla está ordenada por lo vencido y no por el saldo: lo que hay que
 * mirar primero no es quién debe más, es quién lleva más tiempo debiendo.
 */
class Cartera extends BaseController
{
    private CuentaCarteraModel $cuentas;
    private \App\Libraries\Cartera $cartera;

    public function __construct()
    {
        $this->cuentas = new CuentaCarteraModel();
        $this->cartera = new \App\Libraries\Cartera();
    }

    public function index()
    {
        $cuentas = $this->cuentas->listar();

        // Por lo vencido, no por el saldo: quien lleva cuatro meses debiendo
        // medio millón es más urgente que quien debe dos y aún está en plazo.
        usort($cuentas, static fn (array $a, array $b): int => $b['vencido'] <=> $a['vencido']);

        $totalDeuda   = 0.0;
        $totalVencido = 0.0;

        foreach ($cuentas as $c) {
            $totalDeuda   += max(0, $c['saldo']);
            $totalVencido += $c['vencido'];
        }

        return view('cartera/index', [
            'titulo'   => 'Cartera',
            'seccion'  => 'caja',
            'cuentas'  => $cuentas,
            'tipos'    => CuentaCarteraModel::TIPOS,
            'estados'  => CuentaCarteraModel::ESTADOS,
            'deuda'    => round($totalDeuda, 2),
            'vencido'  => round($totalVencido, 2),
        ]);
    }

    public function ver(int $id)
    {
        try {
            $estado = $this->cartera->estadoDeCuenta($id);
        } catch (RuntimeException $e) {
            return redirect()->to('cartera')->with('error', $e->getMessage());
        }

        return view('cartera/ver', [
            'titulo'   => $estado['cuenta']['nombre'],
            'seccion'  => 'caja',
            'e'        => $estado,
            'vencidos' => (new CarteraMovimientoModel())->vencidos($id),
            'tipos'    => CuentaCarteraModel::TIPOS,
            'estados'  => CuentaCarteraModel::ESTADOS,
            'tipos_mov' => CarteraMovimientoModel::TIPOS,
            'medios'   => (new MedioPagoModel())->disponibles('recepcion'),
        ]);
    }

    public function guardar(?int $id = null)
    {
        $datos = [
            'nombre'    => trim((string) $this->request->getPost('nombre')),
            'tipo'      => array_key_exists((string) $this->request->getPost('tipo'), CuentaCarteraModel::TIPOS)
                ? (string) $this->request->getPost('tipo') : 'empresa',
            'nit'       => trim((string) $this->request->getPost('nit')) ?: null,
            'contacto'  => trim((string) $this->request->getPost('contacto')) ?: null,
            'email'     => trim((string) $this->request->getPost('email')) ?: null,
            'telefono'  => trim((string) $this->request->getPost('telefono')) ?: null,
            'direccion' => trim((string) $this->request->getPost('direccion')) ?: null,
            'cupo'      => max(0, (float) $this->request->getPost('cupo')),
            'plazo_dias' => max(0, min(365, (int) $this->request->getPost('plazo_dias'))),
            'descuento_pct' => max(0, min(50, (float) $this->request->getPost('descuento_pct'))),
            'notas'     => trim((string) $this->request->getPost('notas')) ?: null,
        ];

        if ($id === null) {
            $datos['codigo'] = $this->cuentas->siguienteCodigo();
            $datos['estado'] = 'activa';

            if (! $this->cuentas->insert($datos)) {
                return redirect()->back()->withInput()->with('errores', $this->cuentas->errors());
            }

            return redirect()->to('cartera/ver/' . $this->cuentas->getInsertID())->with('ok', 'Cuenta creada.');
        }

        if (! $this->cuentas->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->cuentas->errors());
        }

        return redirect()->to('cartera/ver/' . $id)->with('ok', 'Cuenta guardada.');
    }

    /** Bloquear o desbloquear. Bloqueada no admite cargos nuevos. */
    public function estado(int $id)
    {
        $cuenta = $this->cuentas->find($id);

        if ($cuenta === null) {
            return redirect()->to('cartera')->with('error', 'Esa cuenta no existe.');
        }

        $nuevo = (string) $this->request->getPost('estado');

        if (! array_key_exists($nuevo, CuentaCarteraModel::ESTADOS)) {
            return redirect()->to('cartera/ver/' . $id)->with('error', 'Ese estado no existe.');
        }

        $motivo = trim((string) $this->request->getPost('motivo_bloqueo'));

        // Bloquear sin decir por qué deja a quien atienda mañana sin saber si
        // puede desbloquearla o si hay una razón seria detrás.
        if ($nuevo === 'bloqueada' && $motivo === '') {
            return redirect()->to('cartera/ver/' . $id)
                ->with('error', 'Di por qué se bloquea: quien atienda mañana necesita saberlo.');
        }

        $this->cuentas->update($id, [
            'estado'         => $nuevo,
            'motivo_bloqueo' => $nuevo === 'bloqueada' ? mb_substr($motivo, 0, 200) : null,
        ]);

        return redirect()->to('cartera/ver/' . $id)->with('ok', 'Cuenta ' . CuentaCarteraModel::ESTADOS[$nuevo] . '.');
    }

    public function abonar(int $id)
    {
        try {
            $this->cartera->abonar(
                $id,
                (float) $this->request->getPost('valor'),
                (string) $this->request->getPost('medio') ?: 'transferencia',
                (string) $this->request->getPost('referencia') ?: null,
                session()->get('usuario_id'),
                (string) $this->request->getPost('concepto') ?: null
            );
        } catch (RuntimeException $e) {
            return redirect()->to('cartera/ver/' . $id)->with('error', $e->getMessage());
        }

        return redirect()->to('cartera/ver/' . $id)->with('ok', 'Abono apuntado.');
    }

    public function ajustar(int $id)
    {
        $valor = (float) $this->request->getPost('valor');

        // El signo lo elige quien ajusta: subir la deuda y bajarla son cosas
        // muy distintas y no se pueden confundir con un descuido de teclado.
        if ((string) $this->request->getPost('sentido') === 'baja') {
            $valor = -abs($valor);
        } else {
            $valor = abs($valor);
        }

        try {
            $this->cartera->ajustar($id, $valor, (string) $this->request->getPost('motivo'), session()->get('usuario_id'));
        } catch (RuntimeException $e) {
            return redirect()->to('cartera/ver/' . $id)->with('error', $e->getMessage());
        }

        return redirect()->to('cartera/ver/' . $id)->with('ok', 'Ajuste apuntado.');
    }

    /** Carga el folio de una reserva a la cuenta. */
    public function cargarReserva()
    {
        $reservaId = (int) $this->request->getPost('reserva_id');
        $cuentaId  = (int) $this->request->getPost('cuenta_id');

        try {
            $this->cartera->cargarReserva($reservaId, $cuentaId, session()->get('usuario_id'));
        } catch (RuntimeException $e) {
            return redirect()->to('reservas/ver/' . $reservaId)->with('error', $e->getMessage());
        }

        return redirect()->to('reservas/ver/' . $reservaId)
            ->with('ok', 'La cuenta se cargó a la empresa. El folio queda saldado y la deuda pasa a cartera.');
    }

    /** El estado de cuenta en texto, para mandárselo a la empresa. */
    public function estadoTexto(int $id)
    {
        try {
            $texto = $this->cartera->comoTexto($id);
        } catch (RuntimeException $e) {
            return redirect()->to('cartera')->with('error', $e->getMessage());
        }

        $cuenta = $this->cuentas->find($id);

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="cuenta-' . $cuenta['codigo'] . '.txt"')
            ->setBody($texto);
    }
}
