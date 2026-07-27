<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El catálogo de medios de pago.
 *
 * Antes eran tres ENUM distintos en tres tablas que no coincidían: el folio
 * conocía «bono» y la comanda «habitación», y ninguno sabía del otro. Cualquier
 * informe que cruzara los dos daba números que no cuadran.
 *
 * **`afecta_caja` es la columna que lo sostiene todo.** Un cobro con tarjeta es
 * un ingreso, pero no hay un peso más dentro del cajón. Contarlo en el arqueo
 * hace que la caja «falte» exactamente lo que se cobró con tarjeta, y a partir
 * de ahí nadie se fía del arqueo — que es la forma más segura de que un robo
 * pequeño no se note nunca.
 */
class MedioPagoModel extends Model
{
    protected $table         = 'medios_pago';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'clave', 'nombre', 'tipo', 'afecta_caja', 'requiere_referencia',
        'comision_pct', 'cuenta_contable', 'en_recepcion', 'en_tpv', 'en_web', 'orden', 'activo',
    ];

    public const TIPOS = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'pasarela'      => 'Pasarela de pago',
        'bono'          => 'Bono regalo',
        'cartera'       => 'A crédito',
        'cortesia'      => 'Cortesía',
        'otro'          => 'Otro',
    ];

    protected $validationRules = [
        'nombre' => 'required|max_length[60]',
    ];

    /**
     * Los que se pueden usar en un sitio.
     *
     * @param string $donde `recepcion`, `tpv` o `web`
     */
    public function disponibles(string $donde = 'recepcion'): array
    {
        $columna = match ($donde) {
            'tpv'   => 'en_tpv',
            'web'   => 'en_web',
            default => 'en_recepcion',
        };

        return $this->where('activo', 1)->where($columna, 1)->orderBy('orden')->orderBy('nombre')->findAll();
    }

    /** Para los desplegables: clave => nombre. */
    public function opciones(string $donde = 'recepcion'): array
    {
        $lista = [];

        foreach ($this->disponibles($donde) as $m) {
            $lista[$m['clave']] = $m['nombre'];
        }

        return $lista;
    }

    public function porClave(string $clave): ?array
    {
        return $this->where('clave', $clave)->first();
    }

    /** ¿Ese cobro entra en el cajón? Ante la duda, no. */
    public function entraEnCaja(string $clave): bool
    {
        $medio = $this->porClave($clave);

        return $medio !== null && (int) $medio['afecta_caja'] === 1;
    }

    /** Las claves que sí son efectivo, para las consultas de arqueo. */
    public function clavesDeCaja(): array
    {
        return array_column($this->where('afecta_caja', 1)->findAll(), 'clave');
    }

    public function listar(): array
    {
        return $this->orderBy('orden')->orderBy('nombre')->findAll();
    }
}
