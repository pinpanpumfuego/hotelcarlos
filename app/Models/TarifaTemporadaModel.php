<?php

namespace App\Models;

use CodeIgniter\Model;

/** Precio cerrado por noche para un tipo de alojamiento dentro de una temporada. */
class TarifaTemporadaModel extends Model
{
    protected $table         = 'tarifas_temporada';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['tipo_unidad_id', 'temporada_id', 'precio'];
    protected $useTimestamps = true;

    /** Precios de una temporada indexados por tipo de alojamiento. */
    public function deTemporada(int $temporadaId): array
    {
        $filas = $this->where('temporada_id', $temporadaId)->findAll();

        return array_column($filas, 'precio', 'tipo_unidad_id');
    }

    /** Guarda (o borra si viene vacío) el precio de un tipo en una temporada. */
    public function fijar(int $temporadaId, int $tipoUnidadId, ?float $precio): void
    {
        $existente = $this->where('temporada_id', $temporadaId)
            ->where('tipo_unidad_id', $tipoUnidadId)
            ->first();

        if ($precio === null || $precio <= 0) {
            if ($existente !== null) {
                $this->delete($existente['id']);
            }

            return;
        }

        if ($existente !== null) {
            $this->update($existente['id'], ['precio' => $precio]);

            return;
        }

        $this->insert([
            'temporada_id'   => $temporadaId,
            'tipo_unidad_id' => $tipoUnidadId,
            'precio'         => $precio,
        ]);
    }
}
