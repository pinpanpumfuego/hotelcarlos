<?php

namespace App\Models;

use CodeIgniter\Model;

class TemporadaModel extends Model
{
    protected $table         = 'temporadas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'desde', 'hasta', 'tipo_ajuste', 'ajuste', 'prioridad', 'color', 'activa'];
    protected $useTimestamps = true;

    /** Cómo se aplica el ajuste de la temporada sobre la tarifa base. */
    public const AJUSTES = [
        'porcentaje' => 'Porcentaje sobre la tarifa base',
        'valor'      => 'Suma o resta un valor fijo',
        'fijo'       => 'Precio cerrado por noche',
    ];

    protected $validationRules = [
        'nombre'      => 'required|min_length[3]|max_length[100]',
        'desde'       => 'required|valid_date[Y-m-d]',
        'hasta'       => 'required|valid_date[Y-m-d]',
        'tipo_ajuste' => 'required|in_list[porcentaje,valor,fijo]',
        'ajuste'      => 'required|numeric',
    ];

    protected $validationMessages = [
        'nombre' => [
            'required'   => 'Ponle un nombre a la temporada.',
            'min_length' => 'El nombre debe tener al menos 3 caracteres.',
        ],
        'desde'  => ['required' => 'Indica la fecha de inicio.'],
        'hasta'  => ['required' => 'Indica la fecha de fin.'],
        'ajuste' => ['required' => 'Indica el ajuste.', 'numeric' => 'El ajuste debe ser un número.'],
    ];

    /** Temporadas activas ordenadas por prioridad (la más alta manda). */
    public function activas(): array
    {
        return $this->where('activa', 1)
            ->orderBy('prioridad', 'DESC')
            ->orderBy('desde')
            ->findAll();
    }

    /** Todas, con el número de precios cerrados que tienen definidos. */
    public function conPrecios(): array
    {
        return $this->select('temporadas.*, COUNT(tarifas_temporada.id) AS precios')
            ->join('tarifas_temporada', 'tarifas_temporada.temporada_id = temporadas.id', 'left')
            ->groupBy('temporadas.id')
            ->orderBy('temporadas.desde', 'DESC')
            ->findAll();
    }

    /** Etiqueta legible del ajuste, para listados. */
    public static function textoAjuste(array $t): string
    {
        $valor = (float) $t['ajuste'];

        return match ($t['tipo_ajuste']) {
            'porcentaje' => ($valor >= 0 ? '+' : '') . rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',') . ' %',
            'valor'      => ($valor >= 0 ? '+' : '-') . '$' . number_format(abs($valor), 0, ',', '.'),
            default      => '$' . number_format($valor, 0, ',', '.') . ' / noche',
        };
    }
}
