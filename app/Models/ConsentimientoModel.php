<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El libro de consentimientos. **Se escribe, no se corrige.**
 *
 * Una retirada no actualiza la fila del «sí»: escribe otra fila con el «no».
 * Parece un rodeo hasta que alguien se queja a la Superintendencia y hay que
 * demostrar qué autorizó esa persona el 3 de marzo y cuándo lo quitó. Un
 * UPDATE borra justo la prueba que hace falta.
 *
 * El estado de hoy es siempre «la última fila de esa persona para esa finalidad
 * y ese canal».
 */
class ConsentimientoModel extends Model
{
    protected $table         = 'consentimientos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'huesped_id', 'finalidad', 'canal', 'otorgado', 'origen',
        'version_politica', 'ip', 'dispositivo', 'nota', 'reserva_id', 'usuario_id',
    ];

    public const FINALIDADES = [
        'marketing' => 'Ofertas y novedades',
        'encuestas' => 'Encuestas de satisfacción',
        'operativo' => 'Avisos de su reserva',
        'fotos'     => 'Publicar sus fotos',
        'perfilado' => 'Analizar sus gustos para personalizar',
        'terceros'  => 'Compartir con terceros',
    ];

    public const CANALES = [
        'email'      => 'Correo',
        'whatsapp'   => 'WhatsApp',
        'sms'        => 'SMS',
        'llamada'    => 'Llamada',
        'cualquiera' => 'Cualquier canal',
    ];

    /**
     * Lo operativo no se pide: es lo que hace falta para prestar el servicio.
     *
     * Mandarle a alguien la confirmación de su reserva no es marketing, y
     * pedirle permiso para eso sería absurdo. Lo que no se puede es colar
     * publicidad dentro de un correo operativo.
     */
    public const SIN_PERMISO = ['operativo'];

    /**
     * ¿Se le puede escribir hoy para esto?
     *
     * Por defecto **NO**. El silencio no es un sí: alguien de quien no consta
     * nada no ha autorizado nada, y esa es exactamente la gente a la que no se
     * le puede escribir.
     */
    public function permite(int $huespedId, string $finalidad, string $canal = 'email'): bool
    {
        if (in_array($finalidad, self::SIN_PERMISO, true)) {
            return true;
        }

        $ultimo = $this->ultimo($huespedId, $finalidad, $canal);

        return $ultimo !== null && (int) $ultimo['otorgado'] === 1;
    }

    /**
     * La última palabra sobre una finalidad y un canal.
     *
     * **Manda lo más reciente**, sin más reglas. Un consentimiento dado para
     * «cualquier canal» cuenta también para el canal concreto, y una retirada
     * posterior lo tumba aunque fuera más específica o más general.
     *
     * Se hizo así y no con jerarquías de «lo específico pesa más» porque este
     * método y el que arma las campañas tienen que contestar exactamente lo
     * mismo. Si difieren, una campaña acaba incluyendo a alguien que la ficha
     * dice que se dio de baja, y eso es la clase de fallo que se descubre por
     * una queja.
     */
    public function ultimo(int $huespedId, string $finalidad, string $canal = 'email'): ?array
    {
        $canales = $canal === 'cualquiera' ? ['cualquiera'] : [$canal, 'cualquiera'];

        return $this->where('huesped_id', $huespedId)
            ->where('finalidad', $finalidad)
            ->whereIn('canal', $canales)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * El estado actual de todo, para pintar la ficha.
     *
     * @return array<string, array<string, array{otorgado: bool, fila: ?array}>>
     */
    public function estadoDe(int $huespedId): array
    {
        $estado = [];

        foreach (array_keys(self::FINALIDADES) as $finalidad) {
            if (in_array($finalidad, self::SIN_PERMISO, true)) {
                continue;
            }

            foreach (['email', 'whatsapp'] as $canal) {
                $fila = $this->ultimo($huespedId, $finalidad, $canal);

                $estado[$finalidad][$canal] = [
                    'otorgado' => $fila !== null && (int) $fila['otorgado'] === 1,
                    'fila'     => $fila,
                ];
            }
        }

        return $estado;
    }

    /** El rastro completo, del más nuevo al más viejo. */
    public function historial(int $huespedId): array
    {
        return $this->select('consentimientos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = consentimientos.usuario_id', 'left')
            ->where('huesped_id', $huespedId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Los huéspedes a los que hoy se les puede escribir para algo.
     *
     * Se calcula con la última fila de cada pareja finalidad/canal. No se puede
     * hacer con un simple WHERE porque la tabla guarda la historia entera: una
     * consulta ingenua metería en la campaña a quien se dio de baja ayer.
     *
     * @return list<int>
     */
    public function huespedesQuePermiten(string $finalidad, string $canal = 'email'): array
    {
        $filas = $this->db->query(
            "SELECT c.huesped_id, c.otorgado
             FROM consentimientos c
             JOIN (
                 SELECT huesped_id, MAX(id) AS ultimo
                 FROM consentimientos
                 WHERE finalidad = ? AND canal IN (?, 'cualquiera')
                 GROUP BY huesped_id
             ) u ON u.ultimo = c.id
             JOIN huespedes h ON h.id = c.huesped_id
             WHERE c.otorgado = 1 AND h.estado = 'activo'",
            [$finalidad, $canal]
        )->getResultArray();

        return array_map(static fn (array $f): int => (int) $f['huesped_id'], $filas);
    }
}
