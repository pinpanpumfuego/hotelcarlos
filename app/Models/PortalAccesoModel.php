<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El enlace que le da al huésped acceso a su estancia.
 *
 * **El enlace es la credencial.** No hay contraseña porque nadie se crea una
 * cuenta para pedir toallas, y una contraseña más es una contraseña que se
 * apunta en un papel. A cambio, el enlace es largo, aleatorio, caduca solo y se
 * puede revocar desde recepción si alguien lo comparte donde no debe.
 */
class PortalAccesoModel extends Model
{
    protected $table         = 'portal_accesos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'reserva_id', 'token', 'idioma', 'expira_en', 'revocado',
        'accesos', 'ultimo_acceso', 'ultima_ip',
    ];
    protected $useTimestamps = true;

    /**
     * Días que el enlace sigue vivo después de la salida.
     *
     * No muere en el check-out: el huésped todavía quiere su comprobante, y la
     * encuesta de salida se contesta cuando ya se ha ido.
     */
    public const DIAS_TRAS_SALIDA = 14;

    /**
     * Crea el acceso de una reserva, o devuelve el que ya tenga.
     *
     * Idempotente a propósito: se llama al confirmar la reserva y también al
     * reenviar el correo, y en ninguno de los dos casos debe cambiar el enlace
     * que el huésped ya tiene guardado.
     */
    public function asegurar(array $reserva, string $idioma = 'es'): array
    {
        $existente = $this->where('reserva_id', $reserva['id'])->where('revocado', 0)->first();

        if ($existente !== null) {
            // Si la reserva se alargó, el enlace tiene que durar más
            $caduca = $this->caducidad($reserva);
            if ($existente['expira_en'] < $caduca) {
                $this->update($existente['id'], ['expira_en' => $caduca]);
                $existente['expira_en'] = $caduca;
            }

            return $existente;
        }

        $token = bin2hex(random_bytes(24));

        $id = $this->insert([
            'reserva_id' => $reserva['id'],
            'token'      => $token,
            'idioma'     => in_array($idioma, ['es', 'en', 'fr', 'de'], true) ? $idioma : 'es',
            'expira_en'  => $this->caducidad($reserva),
        ], true);

        return $this->find($id);
    }

    private function caducidad(array $reserva): string
    {
        return (new \DateTime($reserva['fecha_salida']))
            ->modify('+' . self::DIAS_TRAS_SALIDA . ' days')
            ->format('Y-m-d H:i:s');
    }

    /**
     * Busca por token, solo si sirve.
     *
     * Devuelve `null` para todo lo que no valga —caducado, revocado,
     * inexistente— sin distinguir cuál de las tres cosas es: decirle a quien
     * prueba enlaces cuál de ellos existió es ayudarle.
     */
    public function porToken(string $token): ?array
    {
        if (strlen($token) !== 48 || ! ctype_xdigit($token)) {
            return null;
        }

        $acceso = $this->where('token', $token)->where('revocado', 0)->first();

        if ($acceso === null || strtotime((string) $acceso['expira_en']) < time()) {
            return null;
        }

        return $acceso;
    }

    /** Deja constancia de que se ha usado. */
    public function anotarUso(int $id, ?string $ip): void
    {
        $this->builder()
            ->where('id', $id)
            ->set('accesos', 'accesos + 1', false)
            ->update([
                'ultimo_acceso' => date('Y-m-d H:i:s'),
                'ultima_ip'     => $ip,
            ]);
    }

    /** Corta el acceso. Para cuando un enlace acaba donde no debe. */
    public function revocar(int $reservaId): void
    {
        $this->builder()->where('reserva_id', $reservaId)->update(['revocado' => 1]);
    }

    /** El acceso vigente de una reserva, para enseñar el enlace en el panel. */
    public function deReserva(int $reservaId): ?array
    {
        return $this->where('reserva_id', $reservaId)
            ->where('revocado', 0)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** La dirección completa que se le manda al huésped. */
    public function enlace(array $acceso): string
    {
        $prefijo = ($acceso['idioma'] ?? 'es') === 'es' ? '' : $acceso['idioma'] . '/';

        return site_url($prefijo . 'estancia/' . $acceso['token']);
    }
}
