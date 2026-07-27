<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Intentos de cobro en línea.
 *
 * **El intento se guarda antes de mandar a nadie a pagar.** Si el huésped paga
 * y se le cae el móvil antes de volver, el aviso de la pasarela llega igual y
 * encuentra a quién apuntárselo. Sin registro previo, ese pago existiría en el
 * banco y no en el hotel.
 */
class PagoOnlineModel extends Model
{
    protected $table         = 'pagos_online';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'referencia', 'pasarela', 'ambiente', 'concepto_tipo', 'concepto_id',
        'valor', 'moneda', 'estado', 'transaccion_id', 'metodo', 'email',
        'telefono', 'respuesta', 'aplicado_en', 'pagado_en', 'expira_en', 'ip',
    ];
    protected $useTimestamps = true;

    /** A qué se puede aplicar un cobro. */
    public const CONCEPTOS = [
        'reserva' => 'Reserva',
        'folio'   => 'Cuenta de la estancia',
        'comanda' => 'Comanda del restaurante',
        'bono'    => 'Bono regalo',
    ];

    /**
     * Referencia única, sin nada del huésped dentro.
     *
     * Va en la URL de la pasarela y queda en sus registros, así que no puede
     * llevar ni el nombre ni el documento de nadie.
     */
    public function generarReferencia(string $tipo, int $id): string
    {
        return sprintf('%s-%d-%s', strtoupper(substr($tipo, 0, 3)), $id, bin2hex(random_bytes(6)));
    }

    /** Abre un intento de cobro. */
    public function abrir(string $tipo, int $conceptoId, float $valor, array $extra = []): array
    {
        $referencia = $this->generarReferencia($tipo, $conceptoId);

        $id = (int) $this->insert([
            'referencia'    => $referencia,
            'pasarela'      => 'wompi',
            'ambiente'      => $extra['ambiente'] ?? 'pruebas',
            'concepto_tipo' => $tipo,
            'concepto_id'   => $conceptoId,
            'valor'         => round($valor, 2),
            'moneda'        => 'COP',
            'estado'        => 'creado',
            'email'         => $extra['email'] ?? null,
            'telefono'      => $extra['telefono'] ?? null,
            'expira_en'     => $extra['expira_en'] ?? null,
            'ip'            => $extra['ip'] ?? null,
        ], true);

        return $this->find($id);
    }

    public function porReferencia(string $referencia): ?array
    {
        return $this->where('referencia', $referencia)->first();
    }

    public function porTransaccion(string $transaccionId): ?array
    {
        return $this->where('transaccion_id', $transaccionId)->first();
    }

    /**
     * Guarda cómo quedó la transacción.
     *
     * El estado viene siempre de la API, nunca del navegador. Aquí solo se
     * escribe lo que la pasarela ya ha dicho.
     */
    public function anotarResultado(int $id, array $transaccion): bool
    {
        $estado = (string) ($transaccion['status'] ?? 'ERROR');

        if (! isset(\App\Libraries\Wompi::ESTADOS[$estado])) {
            $estado = 'ERROR';
        }

        return (bool) $this->update($id, [
            'estado'         => $estado,
            'transaccion_id' => $transaccion['id'] ?? null,
            'metodo'         => $transaccion['payment_method_type'] ?? null,
            'respuesta'      => json_encode($this->depurar($transaccion), JSON_UNESCAPED_UNICODE),
            'pagado_en'      => $estado === 'APPROVED' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * Marca el pago como ya llevado a la cuenta.
     *
     * Es lo que impide cobrar dos veces: la pasarela puede mandar el mismo
     * aviso varias veces, y el huésped puede recargar la página de vuelta.
     */
    public function marcarAplicado(int $id): bool
    {
        return (bool) $this->update($id, ['aplicado_en' => date('Y-m-d H:i:s')]);
    }

    public function yaAplicado(array $pago): bool
    {
        return ! empty($pago['aplicado_en']);
    }

    /** Los pagos de una reserva, folio o comanda. */
    public function deConcepto(string $tipo, int $id): array
    {
        return $this->where('concepto_tipo', $tipo)
            ->where('concepto_id', $id)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /** Lo cobrado y aprobado de un concepto. */
    public function totalAprobado(string $tipo, int $id): float
    {
        $fila = $this->selectSum('valor', 'total')
            ->where('concepto_tipo', $tipo)
            ->where('concepto_id', $id)
            ->where('estado', 'APPROVED')
            ->first();

        return (float) ($fila['total'] ?? 0);
    }

    /**
     * Intentos que se quedaron a medias.
     *
     * Un pago aprobado sin aplicar es dinero cobrado que no está en la cuenta
     * del huésped: es lo primero que hay que mirar cada mañana.
     */
    public function sinCuadrar(): array
    {
        return $this->where('estado', 'APPROVED')
            ->where('aplicado_en', null)
            ->orderBy('id', 'DESC')
            ->findAll(50);
    }

    /** Los que llevan demasiado en `creado` o `PENDING`: hay que preguntarlos. */
    public function colgados(int $minutos = 30): array
    {
        return $this->whereIn('estado', ['creado', 'PENDING'])
            ->where('created_at <', date('Y-m-d H:i:s', strtotime('-' . $minutos . ' minutes')))
            ->orderBy('id', 'DESC')
            ->findAll(100);
    }

    public function historial(array $filtros = []): self
    {
        if (! empty($filtros['estado'])) {
            $this->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['tipo'])) {
            $this->where('concepto_tipo', $filtros['tipo']);
        }
        if (! empty($filtros['desde'])) {
            $this->where('created_at >=', $filtros['desde'] . ' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $this->where('created_at <=', $filtros['hasta'] . ' 23:59:59');
        }

        return $this->orderBy('id', 'DESC');
    }

    /**
     * Quita de la respuesta lo que no debe quedar guardado.
     *
     * La pasarela devuelve datos de la tarjeta enmascarados, pero también
     * correo y teléfono del pagador. Se guarda lo justo para poder explicar el
     * cobro, y nada más.
     *
     * @param array<mixed> $datos
     *
     * @return array<mixed>
     */
    private function depurar(array $datos): array
    {
        $prohibidas = ['number', 'cvc', 'cvv', 'exp_month', 'exp_year', 'card_holder', 'token'];
        $limpio     = [];

        foreach ($datos as $clave => $valor) {
            if (in_array(strtolower((string) $clave), $prohibidas, true)) {
                $limpio[$clave] = '***';

                continue;
            }

            $limpio[$clave] = is_array($valor) ? $this->depurar($valor) : $valor;
        }

        return $limpio;
    }
}
