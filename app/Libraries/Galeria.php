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
     * Guarda una foto. Si $tipoId viene, es comercial y va a public/;
     * si viene $unidadId, es interna y va a writable/ (no accesible desde fuera).
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function subirFoto(?UploadedFile $archivo, ?int $tipoId, ?int $unidadId, string $alt = ''): array
    {
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

        $carpeta = $this->carpeta($tipoId);
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
            'tipo'           => 'foto',
            'archivo'        => $nombre,
            'miniatura'      => $mini,
            'alt'            => trim($alt) !== '' ? trim($alt) : null,
            'orden'          => $this->medios->siguienteOrden($tipoId, $unidadId),
            'portada'        => $tipoId !== null && $this->medios->where('tipo_unidad_id', $tipoId)->countAllResults() === 0 ? 1 : 0,
            'usuario_id'     => session()->get('usuario_id'),
        ]);

        return ['ok' => true, 'mensaje' => 'Foto añadida a la galería.'];
    }

    /** Añade un vídeo de YouTube o Vimeo (no se sube el archivo: pesaría demasiado). */
    public function anadirVideo(?int $tipoId, ?int $unidadId, string $url, string $titulo = ''): array
    {
        $url = trim($url);

        if (MedioModel::embebido($url) === null) {
            return ['ok' => false, 'mensaje' => 'Pega el enlace de un vídeo de YouTube o Vimeo.'];
        }

        $this->medios->insert([
            'tipo_unidad_id' => $tipoId,
            'unidad_id'      => $unidadId,
            'tipo'           => 'video',
            'url'            => $url,
            'titulo'         => trim($titulo) !== '' ? trim($titulo) : null,
            'orden'          => $this->medios->siguienteOrden($tipoId, $unidadId),
            'usuario_id'     => session()->get('usuario_id'),
        ]);

        return ['ok' => true, 'mensaje' => 'Vídeo añadido a la galería.'];
    }

    /** Borra el registro y sus archivos. */
    public function eliminar(int $id): bool
    {
        $medio = $this->medios->find($id);
        if ($medio === null) {
            return false;
        }

        if ($medio['tipo'] === 'foto') {
            $carpeta = $this->carpeta($medio['tipo_unidad_id'] !== null ? (int) $medio['tipo_unidad_id'] : null);
            foreach ([$medio['archivo'], $medio['miniatura']] as $nombre) {
                if ($nombre !== null && is_file($carpeta . $nombre)) {
                    @unlink($carpeta . $nombre);
                }
            }
        }

        $eraPortada = (int) $medio['portada'] === 1;
        $this->medios->delete($id);

        // Si se borró la portada, la primera que quede ocupa su lugar
        if ($eraPortada && $medio['tipo_unidad_id'] !== null) {
            $siguiente = $this->medios->where('tipo_unidad_id', $medio['tipo_unidad_id'])->orderBy('orden')->first();
            if ($siguiente !== null) {
                $this->medios->update($siguiente['id'], ['portada' => 1]);
            }
        }

        return true;
    }

    /** Ruta absoluta de una foto interna de cabaña, para servirla por controlador. */
    public function rutaPrivada(array $medio): ?string
    {
        if ($medio['unidad_id'] === null || $medio['archivo'] === null) {
            return null;
        }

        $ruta = $this->carpeta(null) . $medio['archivo'];

        return is_file($ruta) ? $ruta : null;
    }

    /** public/medios/tipos/ para lo comercial; writable/uploads/unidades/ para lo interno. */
    private function carpeta(?int $tipoId): string
    {
        return $tipoId !== null
            ? FCPATH . MedioModel::CARPETA_PUBLICA . DIRECTORY_SEPARATOR
            : WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . MedioModel::CARPETA_PRIVADA . DIRECTORY_SEPARATOR;
    }
}
