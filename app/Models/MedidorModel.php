<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Contadores de agua, luz, gas y combustible.
 *
 * En un alojamiento junto a un lago, el agua y la luz son de los gastos que más
 * se van sin que nadie se entere. Una fuga en una tubería enterrada no se ve
 * nunca: solo se nota porque el contador sube igual con la casa vacía.
 */
class MedidorModel extends Model
{
    protected $table         = 'medidores';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'nombre', 'tipo', 'unidad_medida', 'unidad_id', 'activo_id',
        'ubicacion', 'acumulativo', 'alerta_diaria', 'activa', 'notas',
    ];

    public const TIPOS = [
        'agua'        => 'Agua',
        'energia'     => 'Energía eléctrica',
        'gas'         => 'Gas',
        'combustible' => 'Combustible',
        'horas'       => 'Horas de funcionamiento',
        'otro'        => 'Otro',
    ];

    /** Unidad que se propone según el tipo, para no tener que pensarla. */
    public const UNIDADES = [
        'agua'        => 'm³',
        'energia'     => 'kWh',
        'gas'         => 'kg',
        'combustible' => 'gal',
        'horas'       => 'h',
        'otro'        => '',
    ];

    protected $validationRules = [
        'nombre' => 'required|min_length[2]|max_length[120]',
        'tipo'   => 'required|in_list[agua,energia,gas,combustible,horas,otro]',
    ];

    protected $validationMessages = [
        'nombre' => ['required' => 'Ponle un nombre al medidor.'],
    ];

    /** Los medidores con su última lectura y su consumo diario reciente. */
    public function conUltimaLectura(bool $soloActivos = true): array
    {
        $q = $this->select('medidores.*, unidades.nombre AS unidad_nombre, activos.nombre AS activo_nombre')
            ->join('unidades', 'unidades.id = medidores.unidad_id', 'left')
            ->join('activos', 'activos.id = medidores.activo_id', 'left');

        if ($soloActivos) {
            $q->where('medidores.activa', 1);
        }

        $medidores = $q->orderBy('medidores.tipo')->orderBy('medidores.nombre')->findAll();
        $lecturas  = new LecturaMedidorModel();

        foreach ($medidores as &$m) {
            $m['ultima']        = $lecturas->ultima((int) $m['id']);
            $m['consumo_diario'] = $lecturas->consumoDiarioReciente((int) $m['id']);
            $m['dispara_alerta'] = $m['alerta_diaria'] !== null
                && $m['consumo_diario'] !== null
                && $m['consumo_diario'] > (float) $m['alerta_diaria'];
        }

        return $medidores;
    }
}
