<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Documentos de identidad aportados por el huésped.
 * Los archivos se guardan fuera de la carpeta pública: solo se sirven
 * a través del controlador, y únicamente a personal autenticado.
 */
class DocumentoModel extends Model
{
    protected $table         = 'registro_documentos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['registro_id', 'acompanante_id', 'tipo', 'archivo',
        'nombre_original', 'mime', 'tamano'];
    protected $useTimestamps = true;

    /** Carpeta de almacenamiento, fuera de public/. */
    public static function carpeta(): string
    {
        return WRITEPATH . 'uploads/documentos/';
    }

    public const TIPOS = [
        'identidad_frente'  => 'Documento (frente)',
        'identidad_reverso' => 'Documento (reverso)',
        'acompanante'       => 'Documento de acompañante',
        'autorizacion_menor' => 'Autorización de viaje del menor',
        'otro'              => 'Otro documento',
    ];

    public function deRegistro(int $registroId): array
    {
        return $this->where('registro_id', $registroId)->orderBy('id')->findAll();
    }

    /** Borra también el archivo del disco. */
    public function eliminarConArchivo(int $id): void
    {
        $doc = $this->find($id);
        if ($doc === null) {
            return;
        }

        $ruta = self::carpeta() . $doc['archivo'];
        if (is_file($ruta)) {
            @unlink($ruta);
        }

        $this->delete($id);
    }
}
