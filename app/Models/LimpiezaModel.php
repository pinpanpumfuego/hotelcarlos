<?php

namespace App\Models;

use CodeIgniter\Model;

class LimpiezaModel extends Model
{
    protected $table         = 'limpiezas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['unidad_id', 'usuario_id', 'inicio', 'fin', 'notas'];
    protected $useTimestamps = true;

    /** Limpieza en curso de una unidad (o null). */
    public function enCurso(int $unidadId): ?array
    {
        return $this->where('unidad_id', $unidadId)->where('fin IS NULL')->first();
    }

    /** Mapa unidad_id → limpieza en curso, para pintar el tablero sin N consultas. */
    public function todasEnCurso(): array
    {
        $abiertas = $this->select('limpiezas.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id')
            ->where('fin IS NULL')
            ->findAll();

        $mapa = [];
        foreach ($abiertas as $l) {
            $mapa[$l['unidad_id']] = $l;
        }

        return $mapa;
    }

    /** Últimos registros terminados, con unidad y persona. */
    public function historial(int $limite = 10): array
    {
        return $this->select('limpiezas.*, usuarios.nombre AS usuario_nombre, unidades.nombre AS unidad_nombre')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id')
            ->join('unidades', 'unidades.id = limpiezas.unidad_id')
            ->where('fin IS NOT NULL')
            ->orderBy('fin', 'DESC')
            ->findAll($limite);
    }
}
