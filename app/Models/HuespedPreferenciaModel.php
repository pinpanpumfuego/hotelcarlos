<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lo que hay que saber de cada huésped antes de que llegue.
 *
 * **Las alergias tienen tabla propia y no van en `notas` a propósito.** Son
 * dato de salud, y la Ley 1581/2012 los llama sensibles: no los ve cualquiera y
 * exigen autorización expresa. En el campo de notas los lee todo el que abra la
 * ficha, incluido quien solo entró a corregir un teléfono.
 *
 * Lo demás —almohada dura, mesa junto a la ventana, le gustan las aves— no es
 * sensible y es justo lo que hace que alguien vuelva.
 */
class HuespedPreferenciaModel extends Model
{
    protected $table         = 'huesped_preferencias';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['huesped_id', 'tipo', 'valor', 'nota', 'origen', 'critica', 'usuario_id'];

    public const TIPOS = [
        'alergia'       => 'Alergia',
        'dieta'         => 'Dieta',
        'accesibilidad' => 'Accesibilidad',
        'habitacion'    => 'La cabaña',
        'interes'       => 'Le interesa',
        'celebracion'   => 'Fecha especial',
        'otro'          => 'Otro',
    ];

    /** Las que son dato de salud y por tanto sensible. */
    public const SENSIBLES = ['alergia', 'dieta', 'accesibilidad'];

    protected $validationRules = [
        'valor' => 'required|max_length[150]',
    ];

    protected $validationMessages = [
        'valor' => ['required' => 'Escribe qué es.'],
    ];

    /**
     * Las de un huésped.
     *
     * @param bool $incluirSensibles Solo `true` si quien mira tiene permiso.
     *                               El filtro va aquí y no en la vista para
     *                               que ningún sitio nuevo se lo salte.
     */
    public function deHuesped(int $huespedId, bool $incluirSensibles): array
    {
        $q = $this->select('huesped_preferencias.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = huesped_preferencias.usuario_id', 'left')
            ->where('huesped_id', $huespedId);

        if (! $incluirSensibles) {
            $q->whereNotIn('tipo', self::SENSIBLES);
        }

        return $q->orderBy('critica', 'DESC')->orderBy('tipo')->findAll();
    }

    /**
     * Lo que la cocina tiene que ver sí o sí de una reserva.
     *
     * Se pide por reserva y no por huésped porque quien está en cocina no sabe
     * quién duerme en la 3: sabe que la comanda es de la 3.
     */
    public function criticasDeReserva(int $reservaId): array
    {
        return $this->select('huesped_preferencias.*')
            ->join('reservas', 'reservas.huesped_id = huesped_preferencias.huesped_id')
            ->where('reservas.id', $reservaId)
            ->whereIn('huesped_preferencias.tipo', ['alergia', 'dieta'])
            ->orderBy('huesped_preferencias.critica', 'DESC')
            ->findAll();
    }

    /** ¿Tiene algo que pueda mandarle al hospital? */
    public function tieneAlergias(int $huespedId): bool
    {
        return $this->where('huesped_id', $huespedId)->where('tipo', 'alergia')->countAllResults() > 0;
    }

    /** Mueve las preferencias de un perfil a otro al fusionar duplicados. */
    public function trasladar(int $desde, int $hacia): int
    {
        $movidas = 0;

        foreach ($this->where('huesped_id', $desde)->findAll() as $p) {
            // Sin repetir lo que ya tenía el que se queda
            $ya = $this->where('huesped_id', $hacia)
                ->where('tipo', $p['tipo'])
                ->where('valor', $p['valor'])
                ->countAllResults();

            if ($ya === 0) {
                $this->update($p['id'], ['huesped_id' => $hacia]);
                $movidas++;
            } else {
                $this->delete($p['id']);
            }
        }

        return $movidas;
    }
}
