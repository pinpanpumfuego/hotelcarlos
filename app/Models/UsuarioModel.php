<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table         = 'usuarios';
    protected $primaryKey    = 'id';
    // `rol` es el ENUM antiguo y `rol_id` el perfil nuevo. Conviven mientras
    // las rutas pasan de `rol:` a `permiso:`. Si `rol_id` no estuviera aquí,
    // CodeIgniter lo descartaría en silencio al guardar y el perfil no se
    // asignaría nunca, sin dar ningún error.
    protected $allowedFields = ['nombre', 'email', 'clave_hash', 'rol', 'rol_id', 'activo', 'ultimo_acceso'];
    protected $useTimestamps = true;

    public const ROLES = [
        'gerencia'  => 'Gerencia',
        'recepcion' => 'Recepción',
        'limpieza'  => 'Limpieza',
    ];

    protected $validationRules = [
        'nombre' => 'required|min_length[3]|max_length[150]',
        'email'  => 'required|valid_email|max_length[150]',
        'rol'    => 'required|in_list[gerencia,recepcion,limpieza]',
    ];

    protected $validationMessages = [
        'nombre' => ['required' => 'El nombre es obligatorio.'],
        'email'  => ['required' => 'El email es obligatorio.', 'valid_email' => 'El email no es válido.'],
        'rol'    => ['in_list' => 'Rol no válido.'],
    ];

    public function buscarPorEmail(string $email): ?array
    {
        return $this->where('email', strtolower(trim($email)))->first();
    }
}
