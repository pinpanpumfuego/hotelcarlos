<?php

namespace App\Models;

use CodeIgniter\Model;

class ComandaLineaModel extends Model
{
    protected $table         = 'comanda_lineas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['comanda_id', 'producto_id', 'nombre_producto', 'precio_unitario', 'cantidad', 'entregado', 'notas'];
    protected $useTimestamps = true;

    public function deComanda(int $comandaId): array
    {
        return $this->where('comanda_id', $comandaId)
            ->orderBy('id')
            ->findAll();
    }

    /** Líneas pendientes de entregar, agrupadas por comanda (para cocina). */
    public function pendientesCocina(): array
    {
        return $this->select('comanda_lineas.*, comandas.numero, comandas.mesa, comandas.created_at AS comanda_hora')
            ->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.entregado', 0)
            ->orderBy('comandas.created_at')
            ->orderBy('comanda_lineas.id')
            ->findAll();
    }
}
