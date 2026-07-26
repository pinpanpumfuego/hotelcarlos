<?php

namespace App\Libraries;

use App\Models\MedioModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Subida y borrado de fotos de la galería.
 *
 * Las fotos se reducen al subirlas: una foto de móvil pesa 5 MB y tardaría
 * una eternidad en cargar desde el campo. Se guarda una versión grande para
 * verla y una miniatura para los listados.
 */
class Galeria
{
    /** Ancho máximo de la imagen grande y de la miniatura, en píxeles. */
    private const ANCHO_GRANDE = 1600;
    private const ANCHO_MINI   = 480;

    private const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];
    private const TAMANO_MAXIMO    = 12 * 1024 * 1024; // 12 MB

    private MedioModel $medios;

    public function __construct()
    {
        $this->medios = new MedioModel();
    }

    /**
     * Guarda una foto.
     *
     * $publico decide dónde acaba el archivo: las publicables van a public/
     * (las sirve Apache) y las internas a writable/, fuera del navegador.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function subirFoto(
        ?UploadedFile $archivo,
        ?int $tipoId,
        ?int $unidadId,
        string $alt = '',
        bool $publico = true,
        ?int $experienciaId = null
    ): array {
        if ($archivo === null || ! $archivo->isValid()) {
            $motivo = $archivo === null ? 'No llegó ningún archivo.' : $archivo->getErrorString();

            return ['ok' => false, 'mensaje' => 'No se pudo subir la foto: ' . $motivo];
        }

        if (! in_array($archivo->getMimeType(), self::TIPOS_PERMITIDOS, true)) {
            return ['ok' => false, 'mensaje' => 'Solo se admiten fotos JPG, PNG o WEBP.'];
        }

        if ($archivo->getSize() > self::TAMANO_MAXIMO) {
            return ['ok' => false, 'mensaje' => 'La foto pesa más de 12 MB. Hazla más pequeña e inténtalo otra vez.'];
        }

        $carpeta = $this->carpeta($tipoId, $publico, $experienciaId);
        if (! is_dir($carpeta) && ! @mkdir($carpeta, 0755, true) && ! is_dir($carpeta)) {
            return ['ok' => false, 'mensaje' => 'No se pudo crear la carpeta de las fotos. Revisa los permisos.'];
        }

        $nombre = $archivo->getRandomName();
        $archivo->move($carpeta, $nombre);

        $mini = null;
        try {
            $imagen = service('image');

            // Grande: solo se reduce si hace falta, nunca se agranda
            $imagen->withFile($carpeta . $nombre)
                ->resize(self::ANCHO_GRANDE, self::ANCHO_GRANDE, true, 'width')
                ->save($carpeta . $nombre, 82);

            $mini = 'mini_' . $nombre;
            $imagen->withFile($carpeta . $nombre)
                ->resize(self::ANCHO_MINI, self::ANCHO_MINI, true, 'width')
                ->save($carpeta . $mini, 78);
        } catch (\Throwable $e) {
            // Si GD falla, la foto original ya está guardada: se usa tal cual
            log_message('warning', 'No se pudo redimensionar la foto: ' . $e->getMessage());
            $mini = null;
        }

        $this->medios->insert([
            'tipo_unidad_id' => $tipoId,
            'unidad_id'      => $unidadId,
            'experiencia_id' => $experienciaId,
            'publico'        => $publico ? 1 : 0,
            'tipo'           => 'foto',
            'archivo'        => $nombre,
            'miniatura'      => $mini,
            'alt'            => trim($alt) !== '' ? trim($alt) : null,
            'orden'          => $this->medios->siguienteOrden($tipoId, $unidadId, $experienciaId),
            'portada'        => $publico && $this->galeriaVacia($tipoId, $unidadId, $experienciaId) ? 1 : 0,
            'usuario_id'     => session()->get('usuario_id'),
        ]);

        return ['ok' => true, 'mensaje' => 'Foto añadida a la galería.'];
    }

    /** Foto de una experiencia: siempre pública, es material de venta. */
    public function subirFotoExperiencia(?UploadedFile $archivo, int $experienciaId, string $alt = ''): array
    {
        return $this->subirFoto($archivo, null, null, $alt, true, $experienciaId);
    }

    /** Añade un vídeo de YouTube o Vimeo (no se sube el archivo: pesaría demasiado). */
    public function anadirVideo(?int $tipoId, ?int $unidadId, string $url, string $titulo = ''): array
    {
        $url = trim($url);

        if (MedioModel::embebido($url) === null) {
            return ['ok' => false, 'mensaje' => 'Pega el enlace de un vídeo de YouTube o Vimeo. '
                . 'Sirve tanto https://youtu.be/… como el enlace largo de la barra del navegador.'];
        }

        $this->medios->insert([
            'tipo_unidad_id' => $tipoId,
            'unidad_id'      => $unidadId,
            'publico'        => 1,
            'tipo'           => 'video',
            'url'            => $url,
            'titulo'         => trim($titulo) !== '' ? trim($titulo) : null,
            'orden'          => $this->medios->siguienteOrden($tipoId, $unidadId),
            'usuario_id'     => session()->get('usuario_id'),
        ]);

        return ['ok' => true, 'mensaje' => 'Vídeo añadido a la galería.'];
    }

    /** ¿Es el primer elemento publicable de esta galería? Entonces será la portada. */
    private function galeriaVacia(?int $tipoId, ?int $unidadId, ?int $experienciaId = null): bool
    {
        $medios = new MedioModel();

        if ($experienciaId !== null) {
            $medios->where('experiencia_id', $experienciaId);
        } elseif ($tipoId !== null) {
            $medios->where('tipo_unidad_id', $tipoId);
        } else {
            $medios->where('unidad_id', $unidadId);
        }

        return $medios->where('publico', 1)->countAllResults() === 0;
    }

    /** Borra el registro y sus archivos. */
    public function eliminar(int $id): bool
    {
        $medio = $this->medios->find($id);
        if ($medio === null) {
            return false;
        }

        if ($medio['tipo'] === 'foto') {
            $carpeta = $this->carpeta(
                $medio['tipo_unidad_id'] !== null ? (int) $medio['tipo_unidad_id'] : null,
                (int) $medio['publico'] === 1,
                $medio['experiencia_id'] !== null ? (int) $medio['experiencia_id'] : null
            );
            foreach ([$medio['archivo'], $medio['miniatura']] as $nombre) {
                if ($nombre !== null && is_file($carpeta . $nombre)) {
                    @unlink($carpeta . $nombre);
                }
            }
        }

        $eraPortada = (int) $medio['portada'] === 1;
        $this->medios->delete($id);

        // Si se borró la portada, la primera que quede ocupa su lugar
        if ($eraPortada) {
            $siguiente = $this->medios;
            $medio['tipo_unidad_id'] !== null
                ? $siguiente->where('tipo_unidad_id', $medio['tipo_unidad_id'])
                : $siguiente->where('unidad_id', $medio['unidad_id'])->where('publico', $medio['publico']);

            $primera = $siguiente->orderBy('orden')->orderBy('id')->first();
            if ($primera !== null) {
                $this->medios->update($primera['id'], ['portada' => 1]);
            }
        }

        return true;
    }

    /**
     * Cambia una foto de interna a publicable o al revés.
     * Mueve el archivo entre carpetas: si no, dejaría de encontrarse.
     *
     * @return array{ok: bool, mensaje: string, publico: bool}
     */
    public function cambiarVisibilidad(int $id, bool $aPublico): array
    {
        $medio = $this->medios->find($id);
        if ($medio === null || $medio['tipo'] !== 'foto') {
            return ['ok' => false, 'mensaje' => 'Esa foto no existe.', 'publico' => false];
        }

        $tipoId  = $medio['tipo_unidad_id'] !== null ? (int) $medio['tipo_unidad_id'] : null;
        $origen  = $this->carpeta($tipoId, (int) $medio['publico'] === 1);
        $destino = $this->carpeta($tipoId, $aPublico);

        if (! is_dir($destino) && ! @mkdir($destino, 0755, true) && ! is_dir($destino)) {
            return ['ok' => false, 'mensaje' => 'No se pudo preparar la carpeta de destino.', 'publico' => ! $aPublico];
        }

        foreach ([$medio['archivo'], $medio['miniatura']] as $nombre) {
            if ($nombre !== null && is_file($origen . $nombre)) {
                @rename($origen . $nombre, $destino . $nombre);
            }
        }

        $this->medios->update($id, [
            'publico' => $aPublico ? 1 : 0,
            'portada' => 0,
        ]);

        // Si la galería pública se queda sin portada, la primera toma el relevo
        if ($medio['unidad_id'] !== null) {
            $publicas = $this->medios->deUnidad((int) $medio['unidad_id'], true);
            $tienePortada = false;
            foreach ($publicas as $p) {
                if ((int) $p['portada'] === 1) {
                    $tienePortada = true;
                    break;
                }
            }
            if (! $tienePortada && $publicas !== []) {
                $this->medios->update($publicas[0]['id'], ['portada' => 1]);
            }
        }

        return [
            'ok'      => true,
            'publico' => $aPublico,
            'mensaje' => $aPublico
                ? 'La foto pasa a la galería: ya se ve en la web.'
                : 'La foto pasa a interna: deja de verse en la web.',
        ];
    }

    /** Ruta absoluta de una foto interna de cabaña, para servirla por controlador. */
    public function rutaPrivada(array $medio): ?string
    {
        if ($medio['unidad_id'] === null || $medio['archivo'] === null || (int) $medio['publico'] === 1) {
            return null;
        }

        $ruta = $this->carpeta(null, false) . $medio['archivo'];

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * Dónde va el archivo:
     *  · público de un tipo        → public/medios/tipos/
     *  · público de una cabaña     → public/medios/cabanas/
     *  · público de una experiencia → public/medios/experiencias/
     *  · interno                   → writable/uploads/unidades/ (fuera del navegador)
     */
    private function carpeta(?int $tipoId, bool $publico, ?int $experienciaId = null): string
    {
        if (! $publico) {
            return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . MedioModel::CARPETA_PRIVADA . DIRECTORY_SEPARATOR;
        }

        if ($experienciaId !== null) {
            $sub = MedioModel::CARPETA_EXPERIENCIAS;
        } elseif ($tipoId !== null) {
            $sub = MedioModel::CARPETA_TIPOS;
        } else {
            $sub = MedioModel::CARPETA_CABANAS;
        }

        return FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $sub) . DIRECTORY_SEPARATOR;
    }
}
