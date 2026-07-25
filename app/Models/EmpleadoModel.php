<?php

namespace App\Models;

use CodeIgniter\Model;

class EmpleadoModel extends Model
{
    protected $table         = 'empleados';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'usuario_id', 'nombre', 'apellidos', 'tipo_documento', 'num_documento',
        'fecha_nacimiento', 'telefono', 'email', 'direccion', 'ciudad',
        'cargo', 'area', 'tipo_contrato', 'fecha_ingreso', 'fecha_salida', 'salario', 'jornada',
        'eps', 'arl', 'fondo_pension', 'caja_compensacion', 'banco', 'cuenta_bancaria',
        'emergencia_nombre', 'emergencia_telefono', 'emergencia_parentesco',
        'notas', 'activo',
    ];
    protected $useTimestamps = true;

    public const AREAS = [
        'recepcion'      => 'Recepción',
        'limpieza'       => 'Limpieza',
        'cocina'         => 'Cocina y restaurante',
        'mantenimiento'  => 'Mantenimiento',
        'administracion' => 'Administración',
        'otro'           => 'Otra',
    ];

    public const CONTRATOS = [
        'indefinido'  => 'Término indefinido',
        'fijo'        => 'Término fijo',
        'obra'        => 'Obra o labor',
        'prestacion'  => 'Prestación de servicios',
        'aprendiz'    => 'Contrato de aprendizaje',
    ];

    public const TIPOS_DOCUMENTO = [
        'CC'        => 'Cédula de ciudadanía',
        'CE'        => 'Cédula de extranjería',
        'PASAPORTE' => 'Pasaporte',
        'PEP'       => 'Permiso especial de permanencia',
        'OTRO'      => 'Otro',
    ];

    protected $validationRules = [
        'nombre'        => 'required|min_length[2]|max_length[100]',
        'apellidos'     => 'required|min_length[2]|max_length[150]',
        'num_documento' => 'required|max_length[50]',
        'cargo'         => 'required|max_length[100]',
        'email'         => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'nombre'        => ['required' => 'El nombre es obligatorio.'],
        'apellidos'     => ['required' => 'Los apellidos son obligatorios.'],
        'num_documento' => ['required' => 'El número de documento es obligatorio.'],
        'cargo'         => ['required' => 'Indica el cargo.'],
    ];

    /** Empleados con el nombre de su cuenta de acceso, si la tienen. */
    public function conUsuario(bool $soloActivos = false): array
    {
        $builder = $this->select('empleados.*, usuarios.nombre AS usuario_nombre, usuarios.rol AS usuario_rol')
            ->join('usuarios', 'usuarios.id = empleados.usuario_id', 'left');

        if ($soloActivos) {
            $builder->where('empleados.activo', 1);
        }

        return $builder->orderBy('empleados.activo', 'DESC')
            ->orderBy('empleados.apellidos')
            ->orderBy('empleados.nombre')
            ->findAll();
    }

    /** Solo los que están en plantilla, para turnos y ausencias. */
    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('apellidos')->orderBy('nombre')->findAll();
    }

    /** Nombre completo listo para mostrar. */
    public static function nombreCompleto(array $empleado): string
    {
        return trim($empleado['nombre'] . ' ' . $empleado['apellidos']);
    }

    /** Antigüedad en años y meses. */
    public static function antiguedad(?string $ingreso, ?string $salida = null): ?string
    {
        if ($ingreso === null || trim($ingreso) === '') {
            return null;
        }

        try {
            $desde = new \DateTime($ingreso);
            $hasta = $salida !== null && trim($salida) !== '' ? new \DateTime($salida) : new \DateTime('today');
        } catch (\Throwable $e) {
            return null;
        }

        if ($hasta < $desde) {
            return null;
        }

        $dif    = $desde->diff($hasta);
        $partes = [];
        if ($dif->y > 0) {
            $partes[] = $dif->y . ' año' . ($dif->y > 1 ? 's' : '');
        }
        if ($dif->m > 0) {
            $partes[] = $dif->m . ' mes' . ($dif->m > 1 ? 'es' : '');
        }
        if ($partes === []) {
            $partes[] = $dif->d . ' día' . ($dif->d === 1 ? '' : 's');
        }

        return implode(' y ', $partes);
    }
}
