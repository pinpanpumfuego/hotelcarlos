<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lotes con caducidad.
 *
 * Solo para lo que lo necesita. Exigir lote para la sal hace imposible el
 * trabajo diario; para el pescado fresco es imprescindible. Lo decide cada
 * insumo con `controla_lote`.
 */
class LoteModel extends Model
{
    protected $table         = 'lotes';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'insumo_id', 'bodega_id', 'codigo', 'caduca_el', 'cantidad',
        'costo_unitario', 'proveedor_id', 'recibido_el',
    ];
    protected $useTimestamps = true;

    /**
     * Encuentra el lote o lo crea, y le suma la cantidad.
     *
     * Un mismo código de lote que vuelve a entrar es el mismo lote: sumar en
     * vez de duplicar evita que la nevera tenga dos filas de lo mismo con
     * caducidades distintas.
     */
    public function asegurar(
        int $insumoId,
        int $bodegaId,
        string $codigo,
        ?string $caducaEl,
        float $cantidad,
        float $costo,
        ?int $proveedorId = null,
    ): int {
        $lote = $this->where('insumo_id', $insumoId)
            ->where('bodega_id', $bodegaId)
            ->where('codigo', $codigo)
            ->first();

        if ($lote !== null) {
            $this->update($lote['id'], [
                'cantidad'  => round((float) $lote['cantidad'] + $cantidad, 3),
                // La caducidad se respeta si ya estaba: cambiarla desde una
                // entrada posterior podría alargar la vida de algo caducado.
                'caduca_el' => $lote['caduca_el'] ?? $caducaEl,
            ]);

            return (int) $lote['id'];
        }

        return (int) $this->insert([
            'insumo_id'      => $insumoId,
            'bodega_id'      => $bodegaId,
            'codigo'         => $codigo,
            'caduca_el'      => $caducaEl ?: null,
            'cantidad'       => round($cantidad, 3),
            'costo_unitario' => $costo,
            'proveedor_id'   => $proveedorId,
            'recibido_el'    => date('Y-m-d'),
        ], true);
    }

    /**
     * Lo que caduca pronto o ya caducó.
     *
     * Los días de aviso los pone cada insumo: una lechuga avisa con dos días y
     * una conserva con treinta.
     */
    public function porCaducar(): array
    {
        return $this->select('lotes.*, insumos.nombre AS insumo, insumos.unidad,
                              insumos.dias_aviso_caducidad, bodegas.nombre AS bodega,
                              DATEDIFF(lotes.caduca_el, CURDATE()) AS dias')
            ->join('insumos', 'insumos.id = lotes.insumo_id')
            ->join('bodegas', 'bodegas.id = lotes.bodega_id')
            ->where('lotes.caduca_el IS NOT NULL')
            ->where('lotes.cantidad >', 0)
            ->where('DATEDIFF(lotes.caduca_el, CURDATE()) <= insumos.dias_aviso_caducidad', null, false)
            ->orderBy('lotes.caduca_el')
            ->findAll(100);
    }

    /** Lotes vivos de un insumo, el más viejo primero: primero entra, primero sale. */
    public function deInsumo(int $insumoId, ?int $bodegaId = null): array
    {
        $this->select('lotes.*, bodegas.nombre AS bodega, proveedores.nombre AS proveedor')
            ->join('bodegas', 'bodegas.id = lotes.bodega_id')
            ->join('proveedores', 'proveedores.id = lotes.proveedor_id', 'left')
            ->where('lotes.insumo_id', $insumoId)
            ->where('lotes.cantidad >', 0);

        if ($bodegaId !== null) {
            $this->where('lotes.bodega_id', $bodegaId);
        }

        return $this->orderBy('lotes.caduca_el IS NULL', '', false)
            ->orderBy('lotes.caduca_el')
            ->findAll();
    }

    /** Descuenta de los lotes más antiguos primero. */
    public function consumir(int $insumoId, int $bodegaId, float $cantidad): void
    {
        $porGastar = $cantidad;

        foreach ($this->deInsumo($insumoId, $bodegaId) as $lote) {
            if ($porGastar <= 0) {
                break;
            }

            $sale = min($porGastar, (float) $lote['cantidad']);
            $this->update($lote['id'], ['cantidad' => round((float) $lote['cantidad'] - $sale, 3)]);
            $porGastar = round($porGastar - $sale, 3);
        }
    }
}
