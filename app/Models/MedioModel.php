<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Galería de fotos y vídeos.
 *
 * Un medio pertenece a un tipo de alojamiento (lo genérico que se anuncia)
 * o a una cabaña concreta (sus vistas, su terraza, su vídeo).
 *
 * Lo que decide dónde se guarda el archivo es `publico`:
 *  · público  → public/medios/…, lo sirve Apache y se ve en la web.
 *  · interno  → writable/uploads/unidades/, fuera del alcance del navegador;
 *               solo se sirve por controlador al personal con sesión.
 */
class MedioModel extends Model
{
    protected $table         = 'medios';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'tipo_unidad_id', 'unidad_id', 'experiencia_id', 'publico', 'tipo', 'archivo', 'miniatura',
        'url', 'titulo', 'alt', 'orden', 'portada', 'usuario_id',
    ];
    protected $useTimestamps = true;

    public const CARPETA_TIPOS        = 'medios/tipos';
    public const CARPETA_CABANAS      = 'medios/cabanas';
    public const CARPETA_EXPERIENCIAS = 'medios/experiencias';
    public const CARPETA_PRIVADA      = 'unidades';

    /** Galería de un tipo de alojamiento, la portada primero. */
    public function deTipo(int $tipoId): array
    {
        return $this->where('tipo_unidad_id', $tipoId)
            ->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->orderBy('id')
            ->findAll();
    }

    /**
     * Galería de una cabaña.
     *
     * @param bool|null $publico true = solo las publicables, false = solo las
     *                           internas, null = todas.
     */
    public function deUnidad(int $unidadId, ?bool $publico = null): array
    {
        $this->where('unidad_id', $unidadId);

        if ($publico !== null) {
            $this->where('publico', $publico ? 1 : 0);
        }

        return $this->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->orderBy('id')
            ->findAll();
    }

    /** Galerías publicables de varias cabañas a la vez, indexadas por cabaña. */
    public function publicasDeUnidades(array $unidadIds): array
    {
        if ($unidadIds === []) {
            return [];
        }

        $filas = $this->whereIn('unidad_id', $unidadIds)
            ->where('publico', 1)
            ->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->orderBy('id')
            ->findAll();

        $porUnidad = [];
        foreach ($filas as $f) {
            $porUnidad[(int) $f['unidad_id']][] = $f;
        }

        return $porUnidad;
    }

    /** Portadas de todos los tipos, indexadas por tipo. */
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

    /** Portada propia de cada cabaña, cuando la tiene. */
    public function portadasPorUnidad(): array
    {
        $filas = $this->where('unidad_id IS NOT NULL')
            ->where('publico', 1)
            ->where('tipo', 'foto')
            ->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->findAll();

        $portadas = [];
        foreach ($filas as $f) {
            $portadas[(int) $f['unidad_id']] ??= $f;
        }

        return $portadas;
    }

    /** Deja una sola portada dentro de la misma galería. */
    public function marcarPortada(int $id): void
    {
        $medio = $this->find($id);
        if ($medio === null) {
            return;
        }

        $builder = $this->builder();
        $medio['tipo_unidad_id'] !== null
            ? $builder->where('tipo_unidad_id', $medio['tipo_unidad_id'])
            : $builder->where('unidad_id', $medio['unidad_id'])->where('publico', $medio['publico']);
        $builder->update(['portada' => 0]);

        $this->update($id, ['portada' => 1]);
    }

    /** Renumera 0,1,2… para que el orden quede sin huecos. */
    public function renumerar(?int $tipoId, ?int $unidadId, int $publico = 1): void
    {
        $this->where('publico', $publico);
        $tipoId !== null ? $this->where('tipo_unidad_id', $tipoId) : $this->where('unidad_id', $unidadId);

        $orden = 0;
        foreach ($this->orderBy('orden')->orderBy('id')->findAll() as $m) {
            $this->update($m['id'], ['orden' => $orden++]);
        }
    }

    /** Siguiente posición libre en la galería. */
    public function siguienteOrden(?int $tipoId, ?int $unidadId, ?int $experienciaId = null): int
    {
        $builder = $this->selectMax('orden');

        if ($experienciaId !== null) {
            $builder->where('experiencia_id', $experienciaId);
        } elseif ($tipoId !== null) {
            $builder->where('tipo_unidad_id', $tipoId);
        } else {
            $builder->where('unidad_id', $unidadId);
        }

        return (int) ($builder->first()['orden'] ?? 0) + 1;
    }

    /** Carpeta relativa dentro de public/ donde vive un medio publicado. */
    public static function carpetaDe(array $medio): string
    {
        if (($medio['experiencia_id'] ?? null) !== null) {
            return self::CARPETA_EXPERIENCIAS;
        }

        return $medio['tipo_unidad_id'] !== null ? self::CARPETA_TIPOS : self::CARPETA_CABANAS;
    }

    /** Galería de una experiencia, la portada primero. */
    public function deExperiencia(int $experienciaId): array
    {
        return $this->where('experiencia_id', $experienciaId)
            ->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->orderBy('id')
            ->findAll();
    }

    /** Portada de cada experiencia, indexada. */
    public function portadasPorExperiencia(): array
    {
        $filas = $this->where('experiencia_id IS NOT NULL')
            ->where('tipo', 'foto')
            ->orderBy('portada', 'DESC')
            ->orderBy('orden')
            ->findAll();

        $portadas = [];
        foreach ($filas as $f) {
            $portadas[(int) $f['experiencia_id']] ??= $f;
        }

        return $portadas;
    }

    /** URL de la imagen grande; si es vídeo, su enlace. */
    public static function urlPublica(array $medio): string
    {
        if ($medio['tipo'] === 'video') {
            return (string) $medio['url'];
        }

        if ((int) $medio['publico'] !== 1) {
            return site_url('unidades/foto/' . $medio['id']);
        }

        return base_url(self::carpetaDe($medio) . '/' . $medio['archivo']);
    }

    /** URL de la miniatura, con la imagen grande como respaldo. */
    public static function urlMiniatura(array $medio): string
    {
        if ($medio['tipo'] === 'video') {
            return (string) $medio['url'];
        }

        if ((int) $medio['publico'] !== 1) {
            return site_url('unidades/foto/' . $medio['id']);
        }

        return base_url(self::carpetaDe($medio) . '/' . ($medio['miniatura'] ?: $medio['archivo']));
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

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }

    /** Miniatura de un vídeo de YouTube, para no dejar un hueco gris. */
    public static function miniaturaVideo(?string $url): ?string
    {
        if ($url !== null && preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m)) {
            return 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
        }

        return null;
    }
}
