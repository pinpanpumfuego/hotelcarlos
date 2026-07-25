<?php

namespace App\Models;

use CodeIgniter\Model;

class AcompananteModel extends Model
{
    protected $table         = 'registro_acompanantes';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['registro_id', 'nombre', 'apellidos', 'tipo_documento',
        'num_documento', 'nacionalidad', 'fecha_nacimiento', 'es_menor', 'parentesco'];
    protected $useTimestamps = true;

    public const TIPOS_DOCUMENTO = [
        'CC'        => 'Cédula de ciudadanía',
        'CE'        => 'Cédula de extranjería',
        'TI'        => 'Tarjeta de identidad',
        'RC'        => 'Registro civil',
        'PASAPORTE' => 'Pasaporte',
        'PEP'       => 'Permiso especial de permanencia',
        'OTRO'      => 'Otro',
    ];

    public function deRegistro(int $registroId): array
    {
        return $this->where('registro_id', $registroId)->orderBy('id')->findAll();
    }

    /** ¿La persona es menor de edad en la fecha de entrada? */
    public static function esMenor(?string $fechaNacimiento): bool
    {
        if ($fechaNacimiento === null || trim($fechaNacimiento) === '') {
            return false;
        }

        try {
            $nacimiento = new \DateTime($fechaNacimiento);
        } catch (\Throwable $e) {
            return false;
        }

        return $nacimiento->diff(new \DateTime('today'))->y < 18;
    }
}
