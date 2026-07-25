<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Configuración clave-valor del sistema.
 * Las claves listadas en CIFRADAS se guardan cifradas en la base de datos
 * (requiere encryption.key en el .env, generada con `php spark key:generate`).
 */
class ConfiguracionModel extends Model
{
    protected $table         = 'configuracion';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['clave', 'valor'];
    protected $useTimestamps = true;

    public const CIFRADAS = [
        'correo_clave', 'wompi_llave_privada', 'wompi_secreto_integridad',
        'siigo_usuario', 'siigo_access_key', 'siigo_partner_id', 'siigo_token',
    ];

    public function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $fila = $this->where('clave', $clave)->first();
        if ($fila === null || $fila['valor'] === null || $fila['valor'] === '') {
            return $porDefecto;
        }

        $valor = $fila['valor'];
        if (in_array($clave, self::CIFRADAS, true)) {
            try {
                $valor = service('encrypter')->decrypt(base64_decode($valor, true));
            } catch (\Throwable $e) {
                return $porDefecto;
            }
        }

        return $valor;
    }

    /** ¿Hay un valor guardado para esta clave? (sin descifrarlo) */
    public function existe(string $clave): bool
    {
        $fila = $this->where('clave', $clave)->first();

        return $fila !== null && $fila['valor'] !== null && $fila['valor'] !== '';
    }

    /** Guarda varios pares clave => valor (cifra los sensibles). */
    public function guardarPares(array $pares): void
    {
        foreach ($pares as $clave => $valor) {
            if ($valor !== null && $valor !== '' && in_array($clave, self::CIFRADAS, true)) {
                $valor = base64_encode(service('encrypter')->encrypt($valor));
            }

            $existente = $this->where('clave', $clave)->first();
            if ($existente !== null) {
                $this->update($existente['id'], ['valor' => $valor]);
            } else {
                $this->insert(['clave' => $clave, 'valor' => $valor]);
            }
        }
    }

    /** Valores de un grupo por prefijo, sin descifrar (para lecturas masivas no sensibles). */
    public function grupo(string $prefijo): array
    {
        $filas = $this->like('clave', $prefijo, 'after')->findAll();
        $mapa  = [];
        foreach ($filas as $f) {
            if (! in_array($f['clave'], self::CIFRADAS, true)) {
                $mapa[$f['clave']] = $f['valor'];
            }
        }

        return $mapa;
    }

    /** Configuración lista para el servicio de Email de CodeIgniter. */
    public function configCorreo(): ?array
    {
        $host = $this->obtener('correo_host');
        if ($host === null) {
            return null;
        }

        return [
            'protocol'   => 'smtp',
            'SMTPHost'   => $host,
            'SMTPPort'   => (int) $this->obtener('correo_puerto', '587'),
            'SMTPUser'   => (string) $this->obtener('correo_usuario', ''),
            'SMTPPass'   => (string) $this->obtener('correo_clave', ''),
            'SMTPCrypto' => $this->obtener('correo_cifrado', 'tls'),
            'mailType'   => 'html',
            'charset'    => 'UTF-8',
            'newline'    => "\r\n",
        ];
    }
}
