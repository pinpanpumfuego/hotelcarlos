<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Los niveles de fidelización.
 *
 * **El nivel de una persona no se guarda: se calcula.** Guardar «esta persona
 * es nivel 3» significa que el día que se cambie un umbral, o que se anule una
 * reserva vieja, media base de datos queda mintiendo y nadie sabe cuál. Se
 * calcula a partir de sus estancias y su gasto, que son datos reales.
 *
 * Basta con cumplir **uno** de los dos criterios: alguien que vino dos veces y
 * gastó mucho, y alguien que vino seis veces gastando poco, los dos son buenos
 * clientes por razones distintas.
 */
class NivelModel extends Model
{
    protected $table         = 'niveles';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'clave', 'nombre', 'orden', 'estancias_min', 'gasto_min',
        'color', 'beneficios', 'descuento_pct', 'activo',
    ];

    protected $validationRules = [
        'nombre' => 'required|max_length[60]',
    ];

    /** De mayor a menor: el primero que cumpla es el suyo. */
    public function escalera(): array
    {
        return $this->where('activo', 1)->orderBy('orden', 'DESC')->findAll();
    }

    public function listar(): array
    {
        return $this->orderBy('orden')->findAll();
    }

    /**
     * En qué nivel está alguien, dados sus números.
     *
     * @param array{estancias: int, gasto: float} $valor
     */
    public function de(array $valor): ?array
    {
        foreach ($this->escalera() as $nivel) {
            $porEstancias = (int) $nivel['estancias_min'] > 0
                && $valor['estancias'] >= (int) $nivel['estancias_min'];

            $porGasto = (float) $nivel['gasto_min'] > 0
                && $valor['gasto'] >= (float) $nivel['gasto_min'];

            // El nivel de entrada (los dos umbrales en cero) lo cumple todo el
            // mundo: es el suelo, no un logro.
            $esSuelo = (int) $nivel['estancias_min'] === 0 && (float) $nivel['gasto_min'] <= 0;

            if ($porEstancias || $porGasto || $esSuelo) {
                return $nivel;
            }
        }

        return null;
    }

    /**
     * Cuánto le falta para el siguiente.
     *
     * Enseñárselo a recepción sirve para algo muy concreto: «le falta una
     * estancia para el siguiente nivel» es la frase que hace que alguien
     * reserve otra vez.
     *
     * @return array{nivel: array, faltan_estancias: ?int, falta_gasto: ?float}|null
     */
    public function siguiente(array $valor): ?array
    {
        $actual   = $this->de($valor);
        $ordenHoy = $actual !== null ? (int) $actual['orden'] : -1;

        $candidatos = $this->where('activo', 1)
            ->where('orden >', $ordenHoy)
            ->orderBy('orden')
            ->findAll();

        if ($candidatos === []) {
            return null;
        }

        $siguiente = $candidatos[0];

        $faltanEstancias = (int) $siguiente['estancias_min'] > 0
            ? max(0, (int) $siguiente['estancias_min'] - $valor['estancias'])
            : null;

        $faltaGasto = (float) $siguiente['gasto_min'] > 0
            ? max(0, (float) $siguiente['gasto_min'] - $valor['gasto'])
            : null;

        return [
            'nivel'            => $siguiente,
            'faltan_estancias' => $faltanEstancias,
            'falta_gasto'      => $faltaGasto,
        ];
    }

    /** @return list<string> */
    public function beneficiosDe(?array $nivel): array
    {
        if ($nivel === null || trim((string) $nivel['beneficios']) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $nivel['beneficios']))));
    }
}
