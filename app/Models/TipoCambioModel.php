<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoCambioModel extends Model
{
    protected $table         = 'tipos_cambio';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['moneda', 'pesos', 'origen'];
    protected $useTimestamps = true;

    /**
     * A partir de cuántos días un cambio se considera demasiado viejo.
     *
     * El peso colombiano se mueve, y enseñar una conversión de hace un mes es
     * peor que no enseñar ninguna: el visitante hace cuentas con una cifra que
     * ya no existe.
     */
    public const DIAS_VALIDEZ = 7;

    /**
     * Cambios utilizables ahora mismo.
     *
     * @return array<string,float> moneda => pesos por unidad
     */
    public function vigentes(): array
    {
        $limite = date('Y-m-d H:i:s', strtotime('-' . self::DIAS_VALIDEZ . ' days'));

        $filas = $this->select('moneda, pesos')
            ->where('pesos >', 0)
            ->groupStart()
                ->where('updated_at >=', $limite)
                // Lo que puso gerencia a mano no caduca: es una decisión, no un dato
                ->orWhere('origen', 'manual')
            ->groupEnd()
            ->findAll();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[$f['moneda']] = (float) $f['pesos'];
        }

        return $mapa;
    }

    /** Guarda un cambio, respetando lo que gerencia haya fijado a mano. */
    public function guardar(string $moneda, float $pesos, string $origen = 'auto'): bool
    {
        $moneda = strtoupper($moneda);
        $existe = $this->where('moneda', $moneda)->first();

        // Un cambio automático no pisa nunca uno puesto a mano
        if ($existe !== null && $existe['origen'] === 'manual' && $origen === 'auto') {
            return false;
        }

        if ($existe !== null) {
            $this->update($existe['id'], ['pesos' => $pesos, 'origen' => $origen]);

            return true;
        }

        $this->insert(['moneda' => $moneda, 'pesos' => $pesos, 'origen' => $origen]);

        return true;
    }

    /** Para el panel: qué hay guardado y cuándo se actualizó. */
    public function listado(): array
    {
        return $this->orderBy('moneda')->findAll();
    }
}
