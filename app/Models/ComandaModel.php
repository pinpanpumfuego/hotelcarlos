<?php

namespace App\Models;

use CodeIgniter\Model;

class ComandaModel extends Model
{
    protected $table         = 'comandas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'numero', 'mesa', 'mesa_id', 'comensales', 'reserva_id', 'estado', 'total',
        'cliente_nombre', 'cliente_documento', 'cliente_telefono',
        'descuento', 'motivo_descuento', 'cupon_id', 'propina', 'forma_pago', 'recibido', 'cambio',
        'usuario_id', 'cerrada_en', 'notas',
    ];
    protected $useTimestamps = true;

    public const FORMAS_PAGO = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'wompi'         => 'Wompi',
        'habitacion'    => 'Cargar a la cabaña',
        'bono'          => 'Bono regalo',
    ];

    /** Número correlativo diario, p. ej. C-0725-03. */
    public function generarNumero(): string
    {
        $prefijo = 'C-' . date('md') . '-';
        $ultimas = $this->like('numero', $prefijo, 'after')->countAllResults();

        return $prefijo . str_pad((string) ($ultimas + 1), 2, '0', STR_PAD_LEFT);
    }

    /** Comandas abiertas, con el huésped si están asociadas a una reserva. */
    public function abiertas(): array
    {
        return $this->select('comandas.*, reservas.codigo AS reserva_codigo,
                              huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos,
                              unidades.nombre AS unidad_nombre')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->orderBy('comandas.created_at')
            ->findAll();
    }

    /** Comandas cerradas recientes. */
    public function historial(int $limite = 15): array
    {
        return $this->select('comandas.*, usuarios.nombre AS usuario_nombre, reservas.codigo AS reserva_codigo')
            ->join('usuarios', 'usuarios.id = comandas.usuario_id')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->whereIn('comandas.estado', ['cobrada', 'anulada'])
            ->orderBy('comandas.cerrada_en', 'DESC')
            ->findAll($limite);
    }

    /** Recalcula y guarda el total a partir de sus líneas. */
    public function recalcularTotal(int $comandaId): float
    {
        $fila = $this->db->table('comanda_lineas')
            ->select('SUM(precio_unitario * cantidad) AS subtotal', false)
            ->where('comanda_id', $comandaId)
            ->get()->getRowArray();

        $total = (float) ($fila['subtotal'] ?? 0);
        $this->update($comandaId, ['total' => $total]);

        return $total;
    }
}
