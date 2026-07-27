<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Proveedores.
 *
 * Sustituye al campo de texto libre que había en cada insumo. Con texto libre,
 * «Distribuidora del Valle», «Distr. del Valle» y «distribuidora valle» eran
 * tres proveedores distintos, y no había forma de saber a quién se le compra
 * más ni a quién hay que pedirle mañana.
 */
class ProveedorModel extends Model
{
    protected $table         = 'proveedores';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'nombre', 'nit', 'contacto', 'telefono', 'email', 'direccion',
        'dias_entrega', 'notas', 'activo',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre' => 'required|min_length[2]|max_length[150]',
    ];

    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('nombre')->findAll();
    }

    /** Con cuántos insumos le compramos. */
    public function conResumen(): array
    {
        $proveedores = $this->orderBy('activo', 'DESC')->orderBy('nombre')->findAll();

        foreach ($proveedores as &$p) {
            $p['insumos'] = $this->db->table('insumos')
                ->where('proveedor_id', $p['id'])
                ->where('activo', 1)
                ->countAllResults();
        }

        return $proveedores;
    }

    /**
     * Migra los proveedores que estaban escritos a mano en los insumos.
     *
     * Se ejecuta desde el panel, una vez. Agrupa por nombre normalizado para no
     * crear tres fichas de lo mismo por una tilde o una mayúscula.
     *
     * @return array{creados: int, enlazados: int}
     */
    public function migrarDesdeTexto(): array
    {
        $insumos = $this->db->table('insumos')
            ->select('id, proveedor')
            ->where('proveedor IS NOT NULL')
            ->where('proveedor !=', '')
            ->where('proveedor_id IS NULL')
            ->get()->getResultArray();

        $creados   = 0;
        $enlazados = 0;
        $cache     = [];

        foreach ($insumos as $insumo) {
            $nombre = trim((string) $insumo['proveedor']);
            $clave  = $this->normalizar($nombre);

            if (! isset($cache[$clave])) {
                $existente = null;
                foreach ($this->findAll() as $p) {
                    if ($this->normalizar($p['nombre']) === $clave) {
                        $existente = $p;
                        break;
                    }
                }

                if ($existente !== null) {
                    $cache[$clave] = (int) $existente['id'];
                } else {
                    $cache[$clave] = (int) $this->insert(['nombre' => $nombre, 'activo' => 1], true);
                    $creados++;
                }
            }

            $this->db->table('insumos')->where('id', $insumo['id'])
                ->update(['proveedor_id' => $cache[$clave]]);
            $enlazados++;
        }

        return ['creados' => $creados, 'enlazados' => $enlazados];
    }

    /** Sin tildes, sin mayúsculas y sin espacios de más. */
    private function normalizar(string $texto): string
    {
        $sinTildes = strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $sinTildes) ?? $sinTildes;
    }

    /** No se borra si hay insumos o lotes colgando: se desactiva. */
    public function sePuedeEliminar(int $id): bool
    {
        $enInsumos = $this->db->table('insumos')->where('proveedor_id', $id)->countAllResults();
        $enLotes   = $this->db->table('lotes')->where('proveedor_id', $id)->countAllResults();

        return $enInsumos === 0 && $enLotes === 0;
    }
}
