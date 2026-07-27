<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Las versiones de los textos legales.
 *
 * `registros.version_politica` ya guardaba **qué versión** aceptó cada huésped,
 * pero no había ninguna tabla que dijera **qué decía esa versión**. Guardar el
 * número sin el texto es guardar una referencia a un documento que nadie puede
 * recuperar — justo lo que habría que enseñar si alguien reclama.
 *
 * Por eso el texto de una versión que ya aceptó gente no se puede cambiar: eso
 * convertiría su firma en una firma sobre un documento distinto. Para cambiarlo
 * se crea una versión nueva, que es lo que significa versionar.
 */
class PoliticaModel extends Model
{
    protected $table         = 'politicas';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['tipo', 'version', 'titulo', 'texto', 'vigente_desde', 'publicada', 'usuario_id'];

    public const TIPOS = [
        'datos'       => 'Tratamiento de datos personales',
        'reglamento'  => 'Reglamento del alojamiento',
        'cancelacion' => 'Política de cancelación',
        'escnna'      => 'Advertencia ESCNNA',
        'cookies'     => 'Cookies de la web',
    ];

    protected $validationRules = [
        'version' => 'required|max_length[20]',
        'titulo'  => 'required|max_length[150]',
        'texto'   => 'required',
    ];

    protected $validationMessages = [
        'version' => ['required' => 'Ponle un número de versión: 1.0, 2.1…'],
        'texto'   => ['required' => 'Una política vacía no se puede aceptar.'],
    ];

    public function listar(): array
    {
        return $this->orderBy('tipo')->orderBy('vigente_desde', 'DESC')->findAll();
    }

    /** La que rige hoy para un tipo. */
    public function vigente(string $tipo = 'datos'): ?array
    {
        return $this->where('tipo', $tipo)
            ->where('publicada', 1)
            ->where('vigente_desde <=', date('Y-m-d'))
            ->orderBy('vigente_desde', 'DESC')
            ->first();
    }

    /**
     * Cuánta gente aceptó una versión.
     *
     * Se mira en los registros de llegada, que es donde se firma. En cuanto
     * hay uno, el texto queda congelado.
     */
    public function cuantosAceptaron(string $version): int
    {
        return db_connect()->table('registros')
            ->where('version_politica', $version)
            ->where('acepta_datos', 1)
            ->countAllResults();
    }
}
