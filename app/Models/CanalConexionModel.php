<?php

namespace App\Models;

use CodeIgniter\Model;

/** Una conexión por cabaña y plataforma. */
class CanalConexionModel extends Model
{
    protected $table         = 'canal_conexiones';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'unidad_id', 'canal', 'nombre', 'url_importar', 'activa',
        'ultima_sync', 'ultimo_error', 'eventos',
    ];
    protected $useTimestamps = true;

    /** Plataformas conocidas, con su color e icono para el panel. */
    public const CANALES = [
        'directa' => ['Reserva directa', 'bi-house-heart', '#1f4d36', 0],
        'booking' => ['Booking.com', 'bi-globe2', '#003580', 17],
        'airbnb'  => ['Airbnb', 'bi-house-door', '#ff5a5f', 15],
        'expedia' => ['Expedia', 'bi-airplane', '#00355f', 18],
        'otro'    => ['Otro portal', 'bi-link-45deg', '#7b8a81', 15],
    ];

    /** Los que se pueden sincronizar (la venta directa no viene de fuera). */
    public static function sincronizables(): array
    {
        $lista = self::CANALES;
        unset($lista['directa']);

        return $lista;
    }

    public static function nombreCanal(string $canal): string
    {
        return self::CANALES[$canal][0] ?? ucfirst($canal);
    }

    public static function colorCanal(string $canal): string
    {
        return self::CANALES[$canal][2] ?? '#7b8a81';
    }

    /** Comisión típica del canal, en porcentaje. Solo orientativa. */
    public static function comisionCanal(string $canal): float
    {
        return (float) (self::CANALES[$canal][3] ?? 0);
    }

    /** Conexiones con el nombre de la cabaña. */
    public function listado(): array
    {
        return $this->select('canal_conexiones.*, unidades.nombre AS unidad_nombre')
            ->join('unidades', 'unidades.id = canal_conexiones.unidad_id')
            ->orderBy('unidades.orden')
            ->orderBy('unidades.nombre')
            ->orderBy('canal_conexiones.canal')
            ->findAll();
    }

    /** Las que hay que leer: activas y con dirección puesta. */
    public function paraSincronizar(): array
    {
        return $this->where('activa', 1)
            ->where('url_importar IS NOT NULL')
            ->where('url_importar !=', '')
            ->findAll();
    }

    /** ¿Hace cuánto que no se sincroniza nada? Sirve para avisar. */
    public function masAntigua(): ?string
    {
        $fila = $this->where('activa', 1)
            ->where('url_importar IS NOT NULL')
            ->orderBy('ultima_sync', 'ASC')
            ->first();

        return $fila['ultima_sync'] ?? null;
    }
}
