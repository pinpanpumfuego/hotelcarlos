<?php

namespace App\Models;

use CodeIgniter\Model;

class MesaModel extends Model
{
    protected $table         = 'mesas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'zona', 'capacidad', 'orden', 'activa'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre' => 'required|max_length[60]',
    ];

    /** Mesas activas con su comanda abierta (si la tienen). */
    public function conEstado(): array
    {
        $mesas = $this->where('activa', 1)->orderBy('zona')->orderBy('orden')->orderBy('nombre')->findAll();

        $abiertas = db_connect()->table('comandas')
            ->select('id, mesa_id, total, created_at, comensales')
            ->where('estado', 'abierta')
            ->where('mesa_id IS NOT NULL')
            ->get()->getResultArray();

        $porMesa = [];
        foreach ($abiertas as $c) {
            $porMesa[$c['mesa_id']] = $c;
        }

        foreach ($mesas as &$m) {
            $comanda           = $porMesa[$m['id']] ?? null;
            $m['comanda_id']   = $comanda['id'] ?? null;
            $m['total']        = (float) ($comanda['total'] ?? 0);
            $m['comensales']   = (int) ($comanda['comensales'] ?? 0);
            $m['abierta_hace'] = $comanda !== null ? (int) ((time() - strtotime($comanda['created_at'])) / 60) : null;
            $m['ocupada']      = $comanda !== null;
        }

        return $mesas;
    }
}
