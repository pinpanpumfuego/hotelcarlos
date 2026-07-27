<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * La cola de mensajes.
 *
 * **Nada se manda en el momento: se encola.** Si el servidor de correo está
 * caído o lento, encolar no bloquea a la recepcionista que acaba de confirmar
 * una reserva, y lo que falló se reintenta en vez de perderse en un `catch`.
 *
 * El texto se guarda ya montado. Si mañana se cambia la plantilla, lo que se
 * mandó ayer tiene que seguir siendo exactamente lo que se mandó ayer — es la
 * única forma de contestar «yo no recibí eso» sin adivinar.
 */
class EnvioModel extends Model
{
    protected $table         = 'envios';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'huesped_id', 'reserva_id', 'plantilla_clave', 'canal', 'finalidad',
        'destino', 'asunto', 'cuerpo', 'estado', 'programado_para', 'intentos',
        'enviado_en', 'error', 'token', 'abierto_en', 'automatizacion_id',
    ];

    public const ESTADOS = [
        'pendiente' => 'En cola',
        'enviado'   => 'Enviado',
        'fallido'   => 'Falló',
        'cancelado' => 'Cancelado',
        'rebotado'  => 'Rebotó',
    ];

    /** Tres y se para. Insistir con una dirección muerta no la resucita. */
    public const MAX_INTENTOS = 3;

    /**
     * Lo que toca mandar ahora.
     *
     * Se limita a propósito: un servidor de correo compartido corta la conexión
     * si le llegan doscientos de golpe, y entonces no sale ninguno.
     */
    public function pendientes(int $tope = 40): array
    {
        return $this->where('estado', 'pendiente')
            ->where('programado_para <=', date('Y-m-d H:i:s'))
            ->where('intentos <', self::MAX_INTENTOS)
            ->orderBy('programado_para')
            ->findAll($tope);
    }

    public function porToken(string $token): ?array
    {
        // Se comprueba la forma antes de tocar la base: así una URL manipulada
        // no llega ni a consultar.
        if (strlen($token) !== 48 || ! ctype_xdigit($token)) {
            return null;
        }

        return $this->where('token', $token)->first();
    }

    /**
     * ¿Ya se le mandó esto por esta reserva?
     *
     * Es lo que impide que un fallo del reloj o dos ejecuciones seguidas de la
     * tarea programada le manden al huésped la misma encuesta tres veces.
     */
    public function yaExiste(int $reservaId, string $clave): bool
    {
        return $this->where('reserva_id', $reservaId)
            ->where('plantilla_clave', $clave)
            ->whereIn('estado', ['pendiente', 'enviado'])
            ->countAllResults() > 0;
    }

    public function anotarEnviado(int $id): void
    {
        $this->update($id, [
            'estado'     => 'enviado',
            'enviado_en' => date('Y-m-d H:i:s'),
            'error'      => null,
        ]);
    }

    public function anotarFallo(int $id, string $error, int $intentos): void
    {
        $this->update($id, [
            // Al tercer intento se deja de insistir y se marca como fallido,
            // para que salga en la pantalla en vez de morir en silencio.
            'estado'   => $intentos + 1 >= self::MAX_INTENTOS ? 'fallido' : 'pendiente',
            'intentos' => $intentos + 1,
            'error'    => mb_substr($error, 0, 500),
            // Se reintenta más tarde, no inmediatamente: si el servidor está
            // caído, insistir en el mismo segundo solo gasta el cupo diario.
            'programado_para' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ]);
    }

    /** El historial de un huésped, para su ficha. */
    public function deHuesped(int $huespedId, int $cuantos = 30): array
    {
        return $this->where('huesped_id', $huespedId)
            ->orderBy('created_at', 'DESC')
            ->findAll($cuantos);
    }

    /** Lo que ha pasado últimamente, para la pantalla de comunicaciones. */
    public function recientes(string $estado = '', int $cuantos = 100): array
    {
        $q = $this->select('envios.*, huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos,
                            reservas.codigo AS reserva_codigo')
            ->join('huespedes', 'huespedes.id = envios.huesped_id', 'left')
            ->join('reservas', 'reservas.id = envios.reserva_id', 'left');

        if ($estado !== '' && array_key_exists($estado, self::ESTADOS)) {
            $q->where('envios.estado', $estado);
        }

        return $q->orderBy('envios.created_at', 'DESC')->findAll($cuantos);
    }

    /** Cuántos hay de cada estado. */
    public function conteo(): array
    {
        $filas  = $this->select('estado, COUNT(*) AS cuantos')->groupBy('estado')->findAll();
        $conteo = array_fill_keys(array_keys(self::ESTADOS), 0);

        foreach ($filas as $f) {
            $conteo[$f['estado']] = (int) $f['cuantos'];
        }

        return $conteo;
    }
}
