<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use App\Models\CuponModel;
use App\Models\HuespedModel;
use App\Models\ReferidoModel;
use RuntimeException;

/**
 * «Recomiéndanos y os llevamos los dos algo».
 *
 * **El premio se entrega al SALIR, no al reservar.** Si se diera al reservar,
 * una reserva cancelada dejaría dos cupones regalados por una estancia que no
 * ocurrió, y basta con que alguien se dé cuenta una vez para que empiecen a
 * aparecer reservas que nadie piensa cumplir.
 *
 * Los cupones se emiten con el módulo que ya existe: un referido no es un tipo
 * nuevo de descuento, es un motivo nuevo para dar uno.
 */
class Referidos
{
    private HuespedModel $huespedes;
    private ReferidoModel $referidos;
    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->huespedes = new HuespedModel();
        $this->referidos = new ReferidoModel();
        $this->config    = new ConfiguracionModel();
    }

    public function activo(): bool
    {
        return $this->config->obtener('referido_activo', '0') === '1'
            && (float) $this->config->obtener('referido_premio_pct', '0') > 0;
    }

    /**
     * El código de alguien, creándolo si aún no lo tiene.
     *
     * No se generan para todo el mundo por adelantado: la mayoría no va a
     * recomendar a nadie, y llenar la tabla de códigos muertos no ayuda a nada.
     */
    public function codigoDe(int $huespedId): ?string
    {
        $huesped = $this->huespedes->find($huespedId);

        if ($huesped === null || $huesped['estado'] !== 'activo') {
            return null;
        }

        if (! empty($huesped['codigo_referido'])) {
            return $huesped['codigo_referido'];
        }

        $codigo = $this->generar();
        $this->huespedes->update($huespedId, ['codigo_referido' => $codigo]);

        return $codigo;
    }

    /**
     * Un código corto que se pueda dictar por teléfono.
     *
     * Sin las letras y los números que se confunden al oírlos o al leerlos a
     * mano: la I con el 1, la O con el 0, la S con el 5.
     */
    private function generar(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

        for ($intento = 0; $intento < 20; $intento++) {
            $codigo = '';

            for ($i = 0; $i < 6; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }

            if ($this->huespedes->where('codigo_referido', $codigo)->countAllResults() === 0) {
                return $codigo;
            }
        }

        throw new RuntimeException('No se pudo generar un código de referido.');
    }

    /** De quién es un código. */
    public function duenoDe(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));

        if ($codigo === '') {
            return null;
        }

        return $this->huespedes->where('codigo_referido', $codigo)->where('estado', 'activo')->first();
    }

    /**
     * Apunta que una reserva viene de una recomendación.
     *
     * Todavía no reparte nada: eso pasa cuando el huésped se va de verdad.
     */
    public function apuntar(int $reservaId, string $codigo, ?int $referidoId = null): ?int
    {
        if (! $this->activo()) {
            return null;
        }

        $dueno = $this->duenoDe($codigo);

        if ($dueno === null) {
            return null;
        }

        // Recomendarse a uno mismo es la primera idea que se le ocurre a
        // cualquiera al ver un programa de referidos.
        if ($referidoId !== null && (int) $dueno['id'] === $referidoId) {
            return null;
        }

        if ($this->referidos->where('reserva_id', $reservaId)->countAllResults() > 0) {
            return null;
        }

        $this->referidos->insert([
            'referidor_id' => (int) $dueno['id'],
            'referido_id'  => $referidoId,
            'reserva_id'   => $reservaId,
            'codigo_usado' => strtoupper(trim($codigo)),
            'estado'       => 'pendiente',
        ]);

        return (int) $this->referidos->getInsertID();
    }

    /**
     * La estancia ocurrió: se reparten los cupones.
     *
     * Se llama al hacer el check-out. Devuelve `null` si no había nada que
     * repartir, que es lo normal en la mayoría de salidas.
     *
     * @return array{referidor: string, referido: ?string}|null
     */
    public function cumplir(int $reservaId): ?array
    {
        $fila = $this->referidos->where('reserva_id', $reservaId)->where('estado', 'pendiente')->first();

        if ($fila === null || ! $this->activo()) {
            return null;
        }

        $pct  = (float) $this->config->obtener('referido_premio_pct', '10');
        $dias = max(30, (int) $this->config->obtener('referido_premio_dias', '365'));

        $cupones = new CuponModel();

        $cuponReferidor = $this->emitir($cupones, $pct, $dias, (int) $fila['referidor_id'], 'por recomendar');
        $cuponReferido  = $fila['referido_id'] !== null
            ? $this->emitir($cupones, $pct, $dias, (int) $fila['referido_id'], 'por venir recomendado')
            : null;

        $this->referidos->update($fila['id'], [
            'estado'          => 'cumplido',
            'cupon_referidor' => $cuponReferidor,
            'cupon_referido'  => $cuponReferido,
            'cumplido_en'     => date('Y-m-d H:i:s'),
        ]);

        return ['referidor' => $cuponReferidor, 'referido' => $cuponReferido];
    }

    private function emitir(CuponModel $cupones, float $pct, int $dias, int $huespedId, string $motivo): string
    {
        $codigo = 'REF-' . strtoupper(bin2hex(random_bytes(3)));

        $cupones->insert([
            'codigo'      => $codigo,
            'descripcion' => 'Referido: ' . $motivo,
            'tipo'        => 'porcentaje',
            'valor'       => $pct,
            'ambito'      => 'alojamiento',
            'desde'       => date('Y-m-d'),
            'hasta'       => date('Y-m-d', strtotime('+' . $dias . ' days')),
            // Un solo uso y una sola persona: un cupón de referido que circula
            // por WhatsApp deja de ser un premio y pasa a ser una tarifa.
            'limite_usos'        => 1,
            'limite_por_huesped' => 1,
            'en_web'             => 1,
            'en_recepcion'       => 1,
            'en_tpv'             => 0,
            'activo'             => 1,
        ]);

        unset($huespedId);

        return $codigo;
    }

    /** Si la reserva se cae, el referido se anula sin repartir nada. */
    public function anular(int $reservaId, string $motivo = ''): void
    {
        $fila = $this->referidos->where('reserva_id', $reservaId)->where('estado', 'pendiente')->first();

        if ($fila === null) {
            return;
        }

        $this->referidos->update($fila['id'], [
            'estado' => 'anulado',
            'nota'   => mb_substr($motivo ?: 'La reserva no llegó a cumplirse.', 0, 200),
        ]);
    }

    /** Lo que ha traído alguien, para su ficha. */
    public function deHuesped(int $huespedId): array
    {
        return $this->referidos->deReferidor($huespedId);
    }
}
