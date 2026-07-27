<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * La carpeta de cada equipo: factura, manual, garantía, certificado.
 *
 * Los ficheros viven en `writable/uploads/activos/`, fuera de la carpeta
 * pública. Una factura de compra no tiene por qué poder abrirse escribiendo la
 * dirección a mano.
 */
class ActivoDocumentoModel extends Model
{
    protected $table         = 'activo_documentos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['activo_id', 'tipo', 'archivo', 'nombre_original', 'mime', 'tamano', 'usuario_id'];

    public const TIPOS = [
        'factura'     => 'Factura de compra',
        'manual'      => 'Manual de uso',
        'garantia'    => 'Garantía',
        'certificado' => 'Certificado o revisión',
        'ficha'       => 'Ficha técnica',
        'otro'        => 'Otro',
    ];

    /** Lo que se acepta subir. Nada ejecutable, por razones evidentes. */
    public const MIMES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
    ];

    public const TOPE_BYTES = 8 * 1024 * 1024;

    public function deActivo(int $activoId): array
    {
        return $this->where('activo_id', $activoId)->orderBy('tipo')->orderBy('created_at', 'DESC')->findAll();
    }

    /** Certificados que caducan, por categoría de activo. */
    public function ultimoCertificado(int $activoId): ?array
    {
        return $this->where('activo_id', $activoId)
            ->where('tipo', 'certificado')
            ->orderBy('created_at', 'DESC')
            ->first();
    }
}
