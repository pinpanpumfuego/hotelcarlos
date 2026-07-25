<?php

namespace App\Models;

use CodeIgniter\Model;

/** Modificadores elegidos en una línea de comanda. */
class LineaModificadorModel extends Model
{
    protected $table         = 'comanda_linea_modificadores';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['linea_id', 'modificador_id', 'nombre', 'precio_extra'];
    protected $useTimestamps = true;

    /** Modificadores de varias líneas a la vez: [linea_id => [...]]. */
    public function deLineas(array $lineaIds): array
    {
        if ($lineaIds === []) {
            return [];
        }

        $filas = $this->whereIn('linea_id', $lineaIds)->orderBy('id')->findAll();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['linea_id']][] = [
                'id'           => (int) $f['id'],
                'nombre'       => $f['nombre'],
                'precio_extra' => (float) $f['precio_extra'],
            ];
        }

        return $mapa;
    }

    /** Suma de extras de una línea (por unidad de producto). */
    public function extrasDeLinea(int $lineaId): float
    {
        $fila = $this->selectSum('precio_extra')->where('linea_id', $lineaId)->first();

        return (float) ($fila['precio_extra'] ?? 0);
    }
}
