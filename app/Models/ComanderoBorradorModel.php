<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lo que cada camarero lleva apuntado en el móvil sin enviar todavía.
 *
 * Es una foto, no un documento: sirve para que quien está en el TPV vea que
 * una mesa se está atendiendo y qué llevan pedido. No suma al total, no va a
 * cocina y no se factura.
 *
 * Se caduca sola: si el teléfono deja de dar señales de vida (se apagó, se
 * quedó sin batería, el camarero cerró la aplicación), el borrador deja de
 * mostrarse. Mejor no enseñar nada que enseñar algo de hace media hora como
 * si estuviera pasando ahora.
 */
class ComanderoBorradorModel extends Model
{
    protected $table         = 'comandero_borradores';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['empleado_id', 'clave', 'mesa_id', 'comanda_id', 'destino',
        'resumen', 'unidades', 'importe', 'updated_at'];
    protected $useTimestamps = false;

    /** Minutos que un borrador se sigue considerando vivo. */
    public const VIGENCIA_MIN = 8;

    /** Guarda (o reemplaza) lo que ese camarero lleva en ese destino. */
    public function guardar(int $empleadoId, string $clave, array $datos): void
    {
        $lineas = array_map(static fn ($l) => [
            'nombre'   => (string) ($l['nombre'] ?? ''),
            'cantidad' => (int) ($l['cantidad'] ?? 1),
            'importe'  => (float) ($l['importe'] ?? 0),
        ], (array) ($datos['lineas'] ?? []));

        // Sin nada apuntado no hay nada que enseñar
        if ($lineas === []) {
            $this->borrar($empleadoId, $clave);

            return;
        }

        $fila = [
            'empleado_id' => $empleadoId,
            'clave'       => $clave,
            'mesa_id'     => ! empty($datos['mesa_id']) ? (int) $datos['mesa_id'] : null,
            'comanda_id'  => ! empty($datos['comanda_id']) ? (int) $datos['comanda_id'] : null,
            'destino'     => mb_substr(trim((string) ($datos['destino'] ?? '')), 0, 120) ?: null,
            'resumen'     => json_encode(array_slice($lineas, 0, 40), JSON_UNESCAPED_UNICODE),
            'unidades'    => array_sum(array_column($lineas, 'cantidad')),
            'importe'     => array_sum(array_map(static fn ($l) => $l['importe'] * $l['cantidad'], $lineas)),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $existe = $this->where('empleado_id', $empleadoId)->where('clave', $clave)->first();

        if ($existe !== null) {
            $this->update($existe['id'], $fila);

            return;
        }

        $this->insert($fila);
    }

    public function borrar(int $empleadoId, string $clave): void
    {
        $this->where('empleado_id', $empleadoId)->where('clave', $clave)->delete();
    }

    /** Se llama al enviar la ronda: lo apuntado ya es real, el borrador sobra. */
    public function borrarDeComanda(int $comandaId): void
    {
        $this->where('comanda_id', $comandaId)->delete();
    }

    /**
     * Borradores vivos, por mesa.
     *
     * @return array<int, array> mesa_id => borrador
     */
    public function porMesa(): array
    {
        $filas = $this->select('comandero_borradores.*, empleados.nombre AS camarero')
            ->join('empleados', 'empleados.id = comandero_borradores.empleado_id', 'left')
            ->where('comandero_borradores.updated_at >=', date('Y-m-d H:i:s', time() - self::VIGENCIA_MIN * 60))
            ->where('comandero_borradores.mesa_id IS NOT NULL')
            ->orderBy('comandero_borradores.updated_at')
            ->findAll();

        $porMesa = [];
        foreach ($filas as $f) {
            $porMesa[(int) $f['mesa_id']] = $this->presentar($f);
        }

        return $porMesa;
    }

    /** Borradores vivos de una comanda o de una mesa concretas. */
    public function deMesaOComanda(?int $mesaId, ?int $comandaId): array
    {
        if ($mesaId === null && $comandaId === null) {
            return [];
        }

        $b = $this->select('comandero_borradores.*, empleados.nombre AS camarero')
            ->join('empleados', 'empleados.id = comandero_borradores.empleado_id', 'left')
            ->where('comandero_borradores.updated_at >=', date('Y-m-d H:i:s', time() - self::VIGENCIA_MIN * 60))
            ->groupStart();

        if ($mesaId !== null) {
            $b->where('comandero_borradores.mesa_id', $mesaId);
        }
        if ($comandaId !== null) {
            $b->orWhere('comandero_borradores.comanda_id', $comandaId);
        }

        return array_map([$this, 'presentar'], $b->groupEnd()->findAll());
    }

    private function presentar(array $f): array
    {
        return [
            'camarero'  => $f['camarero'] ?? null,
            'destino'   => $f['destino'],
            'unidades'  => (int) $f['unidades'],
            'importe'   => (float) $f['importe'],
            'lineas'    => json_decode((string) $f['resumen'], true) ?: [],
            'hace_min'  => (int) floor((time() - strtotime($f['updated_at'])) / 60),
        ];
    }

    /** Tira los que ya no valen. Se llama de vez en cuando, no hace falta cron. */
    public function purgar(): void
    {
        $this->where('updated_at <', date('Y-m-d H:i:s', time() - 60 * 60))->delete();
    }
}
