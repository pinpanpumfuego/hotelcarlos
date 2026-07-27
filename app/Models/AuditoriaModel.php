<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Permisos\Catalogo;
use CodeIgniter\Model;

/**
 * El registro de quién hizo qué.
 *
 * Es **solo de escritura y lectura**: no tiene método para modificar ni para
 * borrar una fila suelta. Una auditoría que se puede editar no es una
 * auditoría. Lo único que la vacía es la purga por antigüedad, que borra en
 * bloque y por fecha.
 */
class AuditoriaModel extends Model
{
    protected $table         = 'auditoria';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'usuario_id', 'usuario_nombre', 'perfil', 'permiso', 'metodo', 'ruta',
        'referencia', 'resultado', 'http', 'ip', 'created_at',
    ];
    protected $useTimestamps = false;

    /** Cuánto se conserva. Dos años cubre de sobra una revisión contable. */
    public const DIAS_CONSERVACION = 730;

    /**
     * Deja constancia de una acción.
     *
     * No lanza nunca: si la auditoría fallara, no puede tumbar la operación
     * que el usuario estaba haciendo. Un fallo aquí se registra en el log y se
     * sigue adelante — perder una línea de auditoría es malo, pero impedirle a
     * recepción cerrar un check-in por eso es peor.
     */
    public function registrar(array $datos): void
    {
        try {
            $this->insert([
                'usuario_id'     => $datos['usuario_id'] ?? null,
                'usuario_nombre' => mb_substr((string) ($datos['usuario_nombre'] ?? 'desconocido'), 0, 150),
                'perfil'         => $datos['perfil'] ?? null,
                'permiso'        => mb_substr((string) $datos['permiso'], 0, 60),
                'metodo'         => mb_substr((string) ($datos['metodo'] ?? 'GET'), 0, 10),
                'ruta'           => mb_substr((string) ($datos['ruta'] ?? ''), 0, 200),
                'referencia'     => $datos['referencia'] ?? null,
                'resultado'      => $datos['resultado'] ?? 'ok',
                'http'           => $datos['http'] ?? null,
                'ip'             => $datos['ip'] ?? null,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo registrar en la auditoría: {m}', ['m' => $e->getMessage()]);
        }
    }

    /**
     * Consulta filtrada para la pantalla.
     *
     * @param array<string, mixed> $filtros
     */
    public function buscar(array $filtros): self
    {
        if (! empty($filtros['usuario_id'])) {
            $this->where('usuario_id', (int) $filtros['usuario_id']);
        }
        if (! empty($filtros['permiso'])) {
            $this->where('permiso', $filtros['permiso']);
        }
        if (! empty($filtros['modulo'])) {
            $delModulo = array_keys(array_filter(
                Catalogo::PERMISOS,
                static fn (array $p): bool => $p['modulo'] === $filtros['modulo']
            ));
            $this->whereIn('permiso', $delModulo ?: ['']);
        }
        if (! empty($filtros['resultado'])) {
            $this->where('resultado', $filtros['resultado']);
        }
        if (! empty($filtros['referencia'])) {
            $this->like('referencia', (string) $filtros['referencia']);
        }
        if (! empty($filtros['desde'])) {
            $this->where('created_at >=', $filtros['desde'] . ' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $this->where('created_at <=', $filtros['hasta'] . ' 23:59:59');
        }

        return $this->orderBy('id', 'DESC');
    }

    /** Quién ha dejado rastro, para el desplegable del filtro. */
    public function usuariosConRastro(): array
    {
        return $this->select('usuario_id, usuario_nombre')
            ->where('usuario_id IS NOT NULL')
            ->groupBy('usuario_id, usuario_nombre')
            ->orderBy('usuario_nombre')
            ->findAll();
    }

    /**
     * Intentos denegados de los últimos días.
     *
     * Es lo que más dice de un vistazo: alguien buscando puertas que no le
     * corresponden. Casi siempre es un menú mal configurado o un enlace viejo,
     * pero conviene mirarlo.
     */
    public function denegadosRecientes(int $dias = 7): array
    {
        return $this->select('usuario_nombre, permiso, COUNT(*) AS veces, MAX(created_at) AS ultima')
            ->where('resultado', 'denegado')
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-' . $dias . ' days')))
            ->groupBy('usuario_nombre, permiso')
            ->orderBy('veces', 'DESC')
            ->findAll(20);
    }

    /** Todo lo hecho sobre una cosa concreta: «reserva 34». */
    public function historialDe(string $referencia): array
    {
        return $this->where('referencia', $referencia)
            ->orderBy('id', 'DESC')
            ->findAll(100);
    }

    /** Borra lo más viejo. Lo lanza una tarea programada. */
    public function purgar(?int $dias = null): int
    {
        $limite = date('Y-m-d H:i:s', strtotime('-' . ($dias ?? self::DIAS_CONSERVACION) . ' days'));

        $this->builder()->where('created_at <', $limite)->delete();

        return $this->db->affectedRows();
    }
}
