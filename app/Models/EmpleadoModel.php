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
        'pin_hash', 'pin_actualizado', 'ficha_movil', 'foto',
        'tarjeta_uid', 'rol_tpv',
        'pin_panel', 'pin_bloqueado',
    ];

    /** Qué puede hacer cada uno en el TPV. */
    public const ROLES_TPV = [
        'ninguno'   => 'Sin acceso al TPV',
        'camarero'  => 'Camarero',
        'encargado' => 'Encargado',
    ];
    protected $useTimestamps = true;

    /** Longitud del PIN de fichaje. Cuatro dígitos es lo que la gente recuerda. */
    public const LONGITUD_PIN = 4;

    /**
     * Guarda el PIN de fichaje, siempre cifrado.
     *
     * Como el terminal identifica al empleado solo por el PIN, dos personas no
     * pueden compartirlo: se comprueba contra todos antes de guardarlo.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function fijarPin(int $empleadoId, string $pin): array
    {
        $pin = trim($pin);

        if (! preg_match('/^\d{' . self::LONGITUD_PIN . '}$/', $pin)) {
            return ['ok' => false, 'mensaje' => 'El PIN debe tener exactamente ' . self::LONGITUD_PIN . ' dígitos.'];
        }

        // Un PIN demasiado obvio se adivina en tres intentos
        if (in_array($pin, ['0000', '1111', '1234', '4321', '2222', '9999'], true)) {
            return ['ok' => false, 'mensaje' => 'Ese PIN es demasiado fácil de adivinar. Elige otro.'];
        }

        foreach ($this->where('id !=', $empleadoId)->where('pin_hash IS NOT NULL')->findAll() as $otro) {
            if (password_verify($pin, $otro['pin_hash'])) {
                return ['ok' => false, 'mensaje' => 'Ese PIN ya lo usa otra persona. Elige otro.'];
            }
        }

        $this->update($empleadoId, [
            'pin_hash'        => password_hash($pin, PASSWORD_DEFAULT),
            'pin_actualizado' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'mensaje' => 'PIN guardado. Anótalo y entrégaselo en mano al trabajador.'];
    }

    /**
     * Busca al empleado activo que tiene ese PIN.
     *
     * Recorre a todos porque el hash es distinto para cada uno; con una
     * plantilla pequeña el coste es despreciable y a cambio el PIN nunca
     * se guarda en claro.
     */
    public function porPin(string $pin): ?array
    {
        if (! preg_match('/^\d{' . self::LONGITUD_PIN . '}$/', trim($pin))) {
            return null;
        }

        foreach ($this->where('activo', 1)->where('pin_hash IS NOT NULL')->findAll() as $empleado) {
            if (password_verify(trim($pin), $empleado['pin_hash'])) {
                return $empleado;
            }
        }

        return null;
    }

    /**
     * Busca al empleado por el número de su tarjeta.
     *
     * A diferencia del PIN, el número de tarjeta no se cifra: no lo elige la
     * persona, va impreso en el plástico y hay que poder buscarlo. La tarjeta
     * da velocidad, no seguridad — quien la preste responde por lo que se haga.
     */
    public function porTarjeta(string $uid): ?array
    {
        $uid = self::normalizarTarjeta($uid);

        if ($uid === '') {
            return null;
        }

        return $this->where('activo', 1)->where('tarjeta_uid', $uid)->first();
    }

    /** Los lectores añaden ceros a la izquierda o saltos: se limpia todo. */
    public static function normalizarTarjeta(string $uid): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($uid)));
    }

    /**
     * Asigna una tarjeta comprobando que no la tenga otro.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function fijarTarjeta(int $empleadoId, string $uid): array
    {
        $uid = self::normalizarTarjeta($uid);

        if ($uid === '') {
            $this->update($empleadoId, ['tarjeta_uid' => null]);

            return ['ok' => true, 'mensaje' => 'Tarjeta retirada.'];
        }

        if (strlen($uid) < 4) {
            return ['ok' => false, 'mensaje' => 'Ese código es demasiado corto para ser una tarjeta.'];
        }

        $otro = $this->where('id !=', $empleadoId)->where('tarjeta_uid', $uid)->first();
        if ($otro !== null) {
            return [
                'ok'      => false,
                'mensaje' => 'Esa tarjeta ya es de ' . $otro['nombre'] . ' ' . $otro['apellidos'] . '.',
            ];
        }

        $this->update($empleadoId, ['tarjeta_uid' => $uid]);

        return ['ok' => true, 'mensaje' => 'Tarjeta asignada.'];
    }

    /** Quienes pueden usar el TPV. */
    public function delTpv(): array
    {
        return $this->where('activo', 1)
            ->whereIn('rol_tpv', ['camarero', 'encargado'])
            ->orderBy('nombre')
            ->findAll();
    }

    /** Empleados que aún no tienen PIN: no pueden fichar. */
    public function sinPin(): array
    {
        return $this->where('activo', 1)->where('pin_hash IS NULL')->orderBy('apellidos')->findAll();
    }

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
