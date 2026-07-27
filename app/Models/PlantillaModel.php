<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Los textos que se le mandan al huésped.
 *
 * Estaban escritos dentro del código PHP, así que cambiar una coma exigía un
 * despliegue. Ahora se editan desde el panel, que es donde tienen que estar:
 * quien sabe cómo hay que hablarle a un huésped no es quien programa.
 *
 * Las variables van entre llaves dobles: `{{nombre}}`, `{{codigo}}`. Lo que no
 * exista se queda en blanco en vez de imprimir el nombre de la variable — un
 * correo que dice «Hola {{nombre}}» es peor que uno que dice «Hola».
 */
class PlantillaModel extends Model
{
    protected $table         = 'plantillas';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['clave', 'idioma', 'canal', 'nombre', 'asunto', 'cuerpo', 'finalidad', 'activa'];

    /**
     * Las variables que se pueden usar, con lo que significan.
     *
     * Sale en la pantalla de edición: sin esta lista, quien edita una plantilla
     * tiene que adivinar.
     */
    public const VARIABLES = [
        'nombre'    => 'Nombre del huésped',
        'apellidos' => 'Sus apellidos',
        'hotel'     => 'Nombre del alojamiento',
        'codigo'    => 'Código de la reserva',
        'entrada'   => 'Fecha de llegada',
        'salida'    => 'Fecha de salida',
        'noches'    => 'Cuántas noches',
        'cabana'    => 'Nombre de la cabaña',
        'adultos'   => 'Cuántos adultos',
        'total'     => 'Total de la reserva',
        'saldo'     => 'Lo que queda por pagar',
        'portal'    => 'Enlace a su portal',
        'registro'  => 'Enlace al registro de llegada',
        'encuesta'  => 'Enlace a la encuesta',
        'pago'      => 'Enlace para pagar',
        'telefono'  => 'Teléfono del hotel',
        'whatsapp'  => 'WhatsApp del hotel',
    ];

    protected $validationRules = [
        'clave'  => 'required|max_length[40]',
        'nombre' => 'required|max_length[120]',
        'cuerpo' => 'required',
    ];

    protected $validationMessages = [
        'cuerpo' => ['required' => 'Una plantilla sin texto no manda nada.'],
    ];

    /**
     * La plantilla de una clave, en el idioma del huésped si existe.
     *
     * Si no está traducida se cae al español en vez de no mandar nada: un
     * correo en un idioma que no es el suyo sigue siendo mejor que el silencio.
     */
    public function buscar(string $clave, string $idioma = 'es', string $canal = 'email'): ?array
    {
        $exacta = $this->where('clave', $clave)
            ->where('idioma', $idioma)
            ->where('canal', $canal)
            ->where('activa', 1)
            ->first();

        if ($exacta !== null) {
            return $exacta;
        }

        return $this->where('clave', $clave)
            ->where('idioma', 'es')
            ->where('canal', $canal)
            ->where('activa', 1)
            ->first();
    }

    public function listar(): array
    {
        return $this->orderBy('finalidad')->orderBy('clave')->orderBy('idioma')->findAll();
    }

    /** Las variables que usa una plantilla, para avisar de las que no existen. */
    public function variablesUsadas(string $texto): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $texto, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** Las que se escribieron mal. Un aviso a tiempo ahorra un correo raro. */
    public function variablesDesconocidas(array $plantilla): array
    {
        $usadas = $this->variablesUsadas(
            (string) ($plantilla['asunto'] ?? '') . ' ' . (string) $plantilla['cuerpo']
        );

        return array_values(array_diff($usadas, array_keys(self::VARIABLES)));
    }
}
