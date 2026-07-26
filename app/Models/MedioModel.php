<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Galería de fotos y vídeos.
 *
 * Las de un tipo de alojamiento son comerciales y públicas (se ven en la web).
 * Las de una cabaña concreta son internas: pueden mostrar cerraduras, llaves o
 * desperfectos, así que se guardan fuera de public/ y se sirven por controlador.
 */
class MedioModel extends Model
{
    protected $table         = 'medios';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'tipo_unidad_id', 'unidad_id', 'tipo', 'archivo', 'miniatura',
        'url', 'titulo', 'alt', 'orden', 'portada', 'usuario_id',
    ];
    protected $useTimestamps = true;

    /** Carpeta pública de las fotos comerciales. */
    public const CARPETA_PUBLICA = 'medios/tipos';

    /** Carpeta protegida de las fotos internas de cada cabaña. */
    public const CARPETA_PRIVADA = 'unidades';

    /** Galería de un tipo de alojamiento, la portada primero. */
    public function deTipo(int $tipoId): array
    {
        return $this->where('tipo_unidad_id', $tipoId)
            ->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->orderBy('id')
            ->findAll();
    }

    /** Galería interna de una cabaña. */
    public function deUnidad(int $unidadId): array
    {
        return $this->where('unidad_id', $unidadId)
            ->orderBy('orden')
            ->orderBy('id')
            ->findAll();
    }

    /** Portadas de todos los tipos, indexadas por tipo, para pintar listados. */
    public function portadasPorTipo(): array
    {
        $filas = $this->where('tipo_unidad_id IS NOT NULL')
            ->where('tipo', 'foto')
            ->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->findAll();

        $portadas = [];
        foreach ($filas as $f) {
            $portadas[(int) $f['tipo_unidad_id']] ??= $f;
        }

        return $portadas;
    }

    /** Deja una sola portada por tipo. */
    public function marcarPortada(int $id): void
    {
        $medio = $this->find($id);
        if ($medio === null || $medio['tipo_unidad_id'] === null) {
            return;
        }

        $this->builder()->where('tipo_unidad_id', $medio['tipo_unidad_id'])->update(['portada' => 0]);
        $this->update($id, ['portada' => 1]);
    }

    /** Siguiente posición libre en la galería. */
    public function siguienteOrden(?int $tipoId, ?int $unidadId): int
    {
        $builder = $this->selectMax('orden');
        $tipoId !== null ? $builder->where('tipo_unidad_id', $tipoId) : $builder->where('unidad_id', $unidadId);

        return (int) ($builder->first()['orden'] ?? 0) + 1;
    }

    /** URL pública de una foto de tipo, o del embebido si es vídeo. */
    public static function urlPublica(array $medio): string
    {
        if ($medio['tipo'] === 'video') {
            return (string) $medio['url'];
        }

        return base_url(self::CARPETA_PUBLICA . '/' . $medio['archivo']);
    }

    /** URL de la miniatura, con la imagen grande como respaldo. */
    public static function urlMiniatura(array $medio): string
    {
        if ($medio['tipo'] === 'video') {
            return (string) $medio['url'];
        }

        return base_url(self::CARPETA_PUBLICA . '/' . ($medio['miniatura'] ?: $medio['archivo']));
    }

    /**
     * Identificador del vídeo si es de YouTube o Vimeo, para poder incrustarlo.
     * Devuelve null si no se reconoce: entonces se muestra como enlace.
     */
    public static function embebido(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([\w-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }
}
