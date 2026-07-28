<?php

namespace App\Models;

use CodeIgniter\Model;

class FolioModel extends Model
{
    protected $table         = 'folio_movimientos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['reserva_id', 'tipo', 'concepto', 'valor', 'metodo', 'usuario_id'];
    protected $useTimestamps = true;

    public const METODOS = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'wompi'         => 'Wompi',
        'bono'          => 'Bono regalo',
        'tarjeta_saldo' => 'Tarjeta de saldo',
        'otro'          => 'Otro',
    ];

    /** Movimientos de una reserva con el nombre de quien los registró. */
    public function movimientosDeReserva(int $reservaId): array
    {
        return $this->select('folio_movimientos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = folio_movimientos.usuario_id', 'left')
            ->where('reserva_id', $reservaId)
            ->orderBy('folio_movimientos.created_at')
            ->orderBy('folio_movimientos.id')
            ->findAll();
    }

    /** Saldo pendiente: cargos menos pagos y descuentos. */
    public function saldo(int $reservaId): float
    {
        $fila = $this->select("SUM(CASE WHEN tipo = 'cargo' THEN valor ELSE -valor END) AS saldo")
            ->where('reserva_id', $reservaId)
            ->first();

        return (float) ($fila['saldo'] ?? 0);
    }

    /** Suma de los descuentos aplicados a una reserva. */
    public function totalDescuentos(int $reservaId): float
    {
        $fila = $this->selectSum('valor')->where(['reserva_id' => $reservaId, 'tipo' => 'descuento'])->first();

        return (float) ($fila['valor'] ?? 0);
    }

    /** Total pagado de una reserva. */
    public function totalPagado(int $reservaId): float
    {
        $fila = $this->selectSum('valor')->where(['reserva_id' => $reservaId, 'tipo' => 'pago'])->first();

        return (float) ($fila['valor'] ?? 0);
    }

    /**
     * Garantiza que el folio tenga el cargo inicial de alojamiento.
     * Idempotente: si ya existe, no hace nada.
     */
    public function asegurarCargoAlojamiento(array $reserva): void
    {
        $existe = $this->where('reserva_id', $reserva['id'])
            ->where('tipo', 'cargo')
            ->like('concepto', 'Alojamiento', 'after')
            ->countAllResults();

        if ($existe === 0 && (float) $reserva['total'] > 0) {
            $noches = (new \DateTime($reserva['fecha_entrada']))->diff(new \DateTime($reserva['fecha_salida']))->days;
            $this->insert([
                'reserva_id' => $reserva['id'],
                'tipo'       => 'cargo',
                'concepto'   => 'Alojamiento (' . $noches . ' noche' . ($noches > 1 ? 's' : '') . ')',
                'valor'      => $reserva['total'],
                'usuario_id' => session()->get('usuario_id'),
            ]);
        }
    }
}
