<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Qué propiedad nuestra es qué propiedad del proveedor, y sus credenciales.
 *
 * El *refresh token* se guarda **cifrado** con la clave de la aplicación. Se
 * guarda —en vez de dejarlo solo en `.env`— porque hay que poder rotarlo y
 * revocarlo desde el panel sin editar ficheros en el servidor. No se muestra
 * nunca, ni entero ni a medias: un token a medias sigue siendo una pista.
 */
class ChannelPropertyMappingModel extends Model
{
    protected $table         = 'channel_properties';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'channel', 'local_property_id', 'external_property_id',
        'credentials_encrypted', 'credentials_used_at', 'is_active',
    ];
    protected $useTimestamps = true;

    /**
     * Días sin usar tras los cuales Beds24 mata el refresh token.
     *
     * Está documentado por ellos: «Refresh tokens do not expire so long as they
     * have been used within the past 30 days». Si el ecolodge pasa un mes
     * tranquilo, o el cron se para en temporada baja, la sincronización se
     * apaga **sin dar ningún error**.
     */
    public const DIAS_CADUCIDAD_TOKEN = 30;

    /** A cuántos días de margen se empieza a avisar. */
    public const DIAS_AVISO = 7;

    public function forChannel(string $channel): ?array
    {
        return $this->where('channel', $channel)->where('is_active', 1)->first();
    }

    /** Guarda el refresh token cifrado. Nunca en claro, en ninguna parte. */
    public function storeRefreshToken(string $channel, string $refreshToken): bool
    {
        $fila = $this->where('channel', $channel)->first();
        if ($fila === null) {
            return false;
        }

        return (bool) $this->update($fila['id'], [
            'credentials_encrypted' => service('encrypter')->encrypt($refreshToken),
            'credentials_used_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Devuelve el refresh token descifrado, o `null`.
     *
     * Si la clave de cifrado cambió —restauración de copia, `.env` nuevo— el
     * descifrado falla. Se devuelve `null` en vez de propagar la excepción:
     * quien llama tiene que tratar «no hay credenciales» de todos modos, y así
     * el fallo se ve como un aviso en el panel y no como una pantalla blanca.
     */
    public function refreshToken(string $channel): ?string
    {
        $fila = $this->forChannel($channel);
        if ($fila === null || empty($fila['credentials_encrypted'])) {
            return null;
        }

        try {
            $token = service('encrypter')->decrypt($fila['credentials_encrypted']);
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo descifrar el token de {canal}: {m}', [
                'canal' => $channel,
                'm'     => $e->getMessage(),
            ]);

            return null;
        }

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Anota que el token se acaba de usar.
     *
     * Es lo que mantiene vivo el refresh token y lo que permite avisar antes
     * de que caduque.
     */
    public function touch(string $channel): void
    {
        $fila = $this->where('channel', $channel)->first();
        if ($fila !== null) {
            $this->update($fila['id'], ['credentials_used_at' => date('Y-m-d H:i:s')]);
        }
    }

    /**
     * Cuántos días quedan antes de que el token muera por desuso.
     *
     * `null` si no hay credenciales guardadas. Negativo si ya pasó el plazo.
     */
    public function daysUntilTokenExpiry(string $channel): ?int
    {
        $fila = $this->forChannel($channel);
        if ($fila === null || empty($fila['credentials_used_at'])) {
            return null;
        }

        $transcurridos = (time() - strtotime((string) $fila['credentials_used_at'])) / 86400;

        return (int) floor(self::DIAS_CADUCIDAD_TOKEN - $transcurridos);
    }

    /** ¿Toca avisar a gerencia de que el token está por morir? */
    public function tokenNeedsAttention(string $channel): bool
    {
        $dias = $this->daysUntilTokenExpiry($channel);

        return $dias !== null && $dias <= self::DIAS_AVISO;
    }

    /** Borra las credenciales. El botón «Desconectar» del panel. */
    public function forgetCredentials(string $channel): void
    {
        $fila = $this->where('channel', $channel)->first();
        if ($fila !== null) {
            $this->update($fila['id'], [
                'credentials_encrypted' => null,
                'credentials_used_at'   => null,
                'is_active'             => 0,
            ]);
        }
    }
}
