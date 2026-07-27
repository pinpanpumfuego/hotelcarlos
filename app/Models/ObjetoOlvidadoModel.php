<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Objetos olvidados.
 *
 * Va aparte de las incidencias porque tiene una vida propia: aparece, se
 * guarda, se avisa al huésped, y acaba devuelto o desechado pasado un plazo.
 * Una incidencia se resuelve y se acabó.
 */
class ObjetoOlvidadoModel extends Model
{
    protected $table         = 'objetos_olvidados';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'unidad_id', 'reserva_id', 'limpieza_id', 'descripcion', 'foto', 'donde',
        'estado', 'encontrado_por', 'encontrado_el', 'avisado_el', 'cerrado_el', 'notas',
    ];
    protected $useTimestamps = true;

    public const ESTADOS = [
        'guardado'  => 'Guardado',
        'avisado'   => 'Avisado al huésped',
        'devuelto'  => 'Devuelto',
        'desechado' => 'Desechado',
    ];

    /**
     * Apunta un objeto y, si se puede, a quién era.
     *
     * Se busca la última reserva que salió de esa cabaña: casi siempre es de
     * quien acaba de irse, y adivinarlo ahorra tener que buscarlo a mano.
     */
    public function apuntar(array $datos): int
    {
        $reservaId = $datos['reserva_id'] ?? null;

        if ($reservaId === null && ! empty($datos['unidad_id'])) {
            $ultima = $this->db->table('reservas')
                ->select('id')
                ->where('unidad_id', (int) $datos['unidad_id'])
                ->whereIn('estado', ['checkout', 'checkin'])
                ->orderBy('fecha_salida', 'DESC')
                ->get(1)->getRowArray();

            $reservaId = $ultima['id'] ?? null;
        }

        return (int) $this->insert([
            'unidad_id'      => $datos['unidad_id'] ?? null,
            'reserva_id'     => $reservaId,
            'limpieza_id'    => $datos['limpieza_id'] ?? null,
            'descripcion'    => mb_substr((string) $datos['descripcion'], 0, 300),
            'foto'           => $datos['foto'] ?? null,
            'donde'          => isset($datos['donde']) ? mb_substr((string) $datos['donde'], 0, 150) : null,
            'estado'         => 'guardado',
            'encontrado_por' => session()->get('usuario_id'),
            'encontrado_el'  => date('Y-m-d'),
        ], true);
    }

    /** Con la cabaña y el huésped, para la pantalla de recepción. */
    public function listado(?string $estado = null): array
    {
        $this->select('objetos_olvidados.*, unidades.nombre AS cabana,
                       reservas.codigo AS reserva_codigo,
                       huespedes.nombre, huespedes.apellidos, huespedes.email, huespedes.telefono')
            ->join('unidades', 'unidades.id = objetos_olvidados.unidad_id', 'left')
            ->join('reservas', 'reservas.id = objetos_olvidados.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left');

        if ($estado !== null && $estado !== '') {
            $this->where('objetos_olvidados.estado', $estado);
        }

        return $this->orderBy('objetos_olvidados.encontrado_el', 'DESC')->findAll(200);
    }

    public function cambiarEstado(int $id, string $estado, ?string $notas = null): bool
    {
        if (! isset(self::ESTADOS[$estado])) {
            return false;
        }

        $datos = ['estado' => $estado];

        if ($estado === 'avisado') {
            $datos['avisado_el'] = date('Y-m-d');
        }
        if (in_array($estado, ['devuelto', 'desechado'], true)) {
            $datos['cerrado_el'] = date('Y-m-d');
        }
        if ($notas !== null) {
            $datos['notas'] = mb_substr($notas, 0, 300) ?: null;
        }

        return (bool) $this->update($id, $datos);
    }

    /**
     * Lo que lleva guardado más del plazo.
     *
     * No se tira solo: se avisa de que toca decidir. Tirar la cámara de alguien
     * por un vencimiento automático es la clase de error que no se arregla.
     */
    public function pasadosDePlazo(): array
    {
        $dias   = (int) (new ConfiguracionModel())->obtener('objetos_dias_guarda', '90');
        $limite = date('Y-m-d', strtotime('-' . max(1, $dias) . ' days'));

        return $this->select('objetos_olvidados.*, unidades.nombre AS cabana')
            ->join('unidades', 'unidades.id = objetos_olvidados.unidad_id', 'left')
            ->whereIn('objetos_olvidados.estado', ['guardado', 'avisado'])
            ->where('objetos_olvidados.encontrado_el <', $limite)
            ->orderBy('objetos_olvidados.encontrado_el')
            ->findAll();
    }

    public function sinCerrar(): int
    {
        return $this->whereIn('estado', ['guardado', 'avisado'])->countAllResults();
    }
}
