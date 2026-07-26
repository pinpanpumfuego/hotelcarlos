<?php

namespace App\Models;

use CodeIgniter\Model;

class ComandaLineaModel extends Model
{
    protected $table         = 'comanda_lineas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['comanda_id', 'producto_id', 'nombre_producto', 'destino', 'precio_unitario',
        'cantidad', 'entregado', 'servido', 'listo_en', 'enviado_cocina', 'notas',
        'composicion', 'empleado_id'];
    protected $useTimestamps = true;

    public function deComanda(int $comandaId): array
    {
        return $this->where('comanda_id', $comandaId)
            ->orderBy('id')
            ->findAll();
    }

    /**
     * Pendientes de una zona de preparación ('cocina' o 'barra'):
     * solo lo que el mesero ya envió y aún no está listo.
     * El reloj cuenta desde el envío, no desde que se abrió la comanda.
     */
    public function pendientesDeZona(string $zona): array
    {
        return $this->select('comanda_lineas.*, comandas.numero, comandas.mesa,
                              comandas.cliente_nombre, unidades.nombre AS unidad_nombre,
                              comanda_lineas.updated_at AS enviado_en')
            ->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.enviado_cocina', 1)
            ->where('comanda_lineas.entregado', 0)
            ->where('comanda_lineas.destino', $zona)
            ->orderBy('comanda_lineas.updated_at')
            ->orderBy('comanda_lineas.id')
            ->findAll();
    }

    /** Compatibilidad: pendientes de cocina y barra juntos. */
    public function pendientesCocina(): array
    {
        return $this->select('comanda_lineas.*, comandas.numero, comandas.mesa,
                              comandas.cliente_nombre, unidades.nombre AS unidad_nombre,
                              comanda_lineas.updated_at AS enviado_en')
            ->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.enviado_cocina', 1)
            ->where('comanda_lineas.entregado', 0)
            ->whereIn('comanda_lineas.destino', ['cocina', 'barra'])
            ->orderBy('comanda_lineas.updated_at')
            ->orderBy('comanda_lineas.id')
            ->findAll();
    }

    /** Cuántos platos hay listos en cocina esperando a ser servidos. */
    public function listosParaServir(): int
    {
        return $this->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.entregado', 1)
            ->where('comanda_lineas.servido', 0)
            ->countAllResults();
    }
}
