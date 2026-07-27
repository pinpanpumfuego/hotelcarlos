<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\ChannelManager\Exceptions\MappingNotFoundException;
use CodeIgniter\Model;

/**
 * Qué alojamiento del proveedor corresponde a qué cabañas nuestras.
 *
 * El mapeo es por **tipo de alojamiento**, no cabaña a cabaña: en Beds24 las
 * siete cabañas se publican como un solo alojamiento con `numAvail: 7`. Qué
 * cabaña concreta le toca a cada huésped lo decide este sistema, que es el
 * único que sabe qué está limpia y qué está en obra.
 *
 * `local_unit_id = 0` significa «todas las del tipo». El día que las cabañas
 * tengan precios distintos se crearán mapeos con la cabaña concreta y este
 * modelo no cambia.
 */
class ChannelUnitMappingModel extends Model
{
    protected $table         = 'channel_unit_mappings';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'channel', 'local_property_id', 'local_room_type_id', 'local_unit_id',
        'external_property_id', 'external_unit_id', 'external_room_type_id',
        'external_rate_plan_id', 'allotment', 'is_active',
    ];
    protected $useTimestamps = true;

    /** Todos los mapeos activos de un canal. */
    public function active(string $channel): array
    {
        return $this->where('channel', $channel)
            ->where('is_active', 1)
            ->orderBy('local_room_type_id')
            ->findAll();
    }

    /**
     * De un alojamiento externo a un tipo local.
     *
     * @throws MappingNotFoundException cuando no existe. No devuelve `null` a
     *         propósito: dejar pasar una reserva cuya cabaña no conocemos es
     *         peor que pararla y avisar.
     */
    public function resolveLocalRoomType(string $channel, string $externalUnitId): array
    {
        $fila = $this->where('channel', $channel)
            ->where('external_unit_id', $externalUnitId)
            ->where('is_active', 1)
            ->first();

        if ($fila === null) {
            throw MappingNotFoundException::forExternalUnit($channel, $externalUnitId);
        }

        return $fila;
    }

    /** Del tipo local al alojamiento externo. */
    public function resolveExternalUnit(string $channel, int $roomTypeId): array
    {
        $fila = $this->where('channel', $channel)
            ->where('local_room_type_id', $roomTypeId)
            ->where('local_unit_id', 0)
            ->where('is_active', 1)
            ->first();

        if ($fila === null) {
            throw MappingNotFoundException::forLocalRoomType($channel, $roomTypeId);
        }

        return $fila;
    }

    /** Igual que el anterior pero sin reventar: para pintar el panel. */
    public function findExternalUnit(string $channel, int $roomTypeId): ?array
    {
        return $this->where('channel', $channel)
            ->where('local_room_type_id', $roomTypeId)
            ->where('local_unit_id', 0)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Tipos de alojamiento que existen aquí y **no** están mapeados.
     *
     * Es la lista de «Unidades sin mapear» del panel. Mientras algo esté ahí,
     * sus reservas no pueden entrar y sus fechas no se envían: el hueco se
     * vende dos veces sin que nadie se entere.
     *
     * @return list<array{id: int, nombre: string, unidades: int}>
     */
    public function unmappedRoomTypes(string $channel): array
    {
        $tipos = $this->db->table('tipos_unidad tu')
            ->select('tu.id, tu.nombre, COUNT(u.id) AS unidades')
            ->join('unidades u', 'u.tipo_id = tu.id', 'left')
            ->groupBy('tu.id, tu.nombre')
            ->get()
            ->getResultArray();

        $mapeados = array_column($this->active($channel), 'local_room_type_id');

        return array_values(array_filter(
            array_map(
                static fn (array $t): array => [
                    'id'       => (int) $t['id'],
                    'nombre'   => (string) $t['nombre'],
                    'unidades' => (int) $t['unidades'],
                ],
                $tipos
            ),
            static fn (array $t): bool => ! in_array($t['id'], array_map('intval', $mapeados), true)
        ));
    }

    /**
     * Cuántas cabañas debería representar cada mapeo, según lo que hay.
     *
     * Sirve para detectar el descuadre del día que se añade una cabaña octava y
     * nadie se acuerda de subir el `allotment`: Beds24 seguiría vendiendo siete.
     *
     * @return list<array{mapeo: array, reales: int, declaradas: int}>
     */
    public function allotmentMismatches(string $channel): array
    {
        $reales = [];
        foreach ($this->db->table('unidades')
            ->select('tipo_id, COUNT(*) AS total')
            ->whereNotIn('estado', ['bloqueada'])
            ->groupBy('tipo_id')
            ->get()->getResultArray() as $f) {
            $reales[(int) $f['tipo_id']] = (int) $f['total'];
        }

        $desajustes = [];
        foreach ($this->active($channel) as $m) {
            if ((int) $m['local_unit_id'] !== 0) {
                continue;   // un mapeo cabaña a cabaña siempre vale 1
            }

            $tipo   = (int) $m['local_room_type_id'];
            $cuenta = $reales[$tipo] ?? 0;

            if ($cuenta !== (int) $m['allotment']) {
                $desajustes[] = [
                    'mapeo'      => $m,
                    'reales'     => $cuenta,
                    'declaradas' => (int) $m['allotment'],
                ];
            }
        }

        return $desajustes;
    }
}
