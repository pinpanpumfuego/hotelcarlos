<?php

namespace App\Controllers;

use App\Models\AusenciaModel;
use App\Models\EmpleadoModel;
use App\Models\TurnoModel;
use App\Models\UsuarioModel;

/**
 * Personal del ecolodge: fichas, turnos, ausencias y documentos.
 * No cubre nómina: la liquidación se lleva en el software contable.
 */
class Personal extends BaseController
{
    private EmpleadoModel $empleados;

    /** Documentos laborales admitidos. */
    private const MIMES_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const TAMANO_MAXIMO    = 8388608; // 8 MB

    public const TIPOS_DOCUMENTO = [
        'contrato'    => 'Contrato',
        'identidad'   => 'Documento de identidad',
        'hoja_vida'   => 'Hoja de vida',
        'certificado' => 'Certificado o diploma',
        'afiliacion'  => 'Afiliación a seguridad social',
        'examen'      => 'Examen médico ocupacional',
        'otro'        => 'Otro',
    ];

    public function __construct()
    {
        $this->empleados = new EmpleadoModel();
    }

    // ─────────────────────────── Empleados ───────────────────────────

    public function index()
    {
        $empleados = $this->empleados->conUsuario();

        // Ausencias en curso, para marcar quién no está hoy
        $hoy       = date('Y-m-d');
        $ausentes  = (new AusenciaModel())->enRango($hoy, $hoy);

        return view('personal/index', [
            'titulo'    => 'Personal',
            'seccion'   => 'personal',
            'empleados' => $empleados,
            'ausentes'  => $ausentes,
            'areas'     => EmpleadoModel::AREAS,
            'contratos' => EmpleadoModel::CONTRATOS,
            'tiposAusencia' => AusenciaModel::TIPOS,
        ]);
    }

    public function nuevo()
    {
        return view('personal/form', [
            'titulo'   => 'Nuevo empleado',
            'seccion'  => 'personal',
            'areas'    => EmpleadoModel::AREAS,
            'contratos' => EmpleadoModel::CONTRATOS,
            'tiposDoc' => EmpleadoModel::TIPOS_DOCUMENTO,
            'usuarios' => $this->usuariosDisponibles(),
        ]);
    }

    public function guardar()
    {
        $datos = $this->datosEmpleado();

        try {
            if (! $this->empleados->insert($datos)) {
                return redirect()->back()->withInput()->with('errores', $this->empleados->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe un empleado con ese documento.']);
        }

        return redirect()->to('personal')->with('ok', 'Empleado registrado.');
    }

    public function editar(int $id)
    {
        $empleado = $this->empleados->find($id);
        if ($empleado === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        return view('personal/form', [
            'titulo'    => 'Editar ' . EmpleadoModel::nombreCompleto($empleado),
            'seccion'   => 'personal',
            'empleado'  => $empleado,
            'areas'     => EmpleadoModel::AREAS,
            'contratos' => EmpleadoModel::CONTRATOS,
            'tiposDoc'  => EmpleadoModel::TIPOS_DOCUMENTO,
            'usuarios'  => $this->usuariosDisponibles($id),
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->empleados->find($id) === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        $datos = $this->datosEmpleado();

        try {
            if (! $this->empleados->update($id, $datos)) {
                return redirect()->back()->withInput()->with('errores', $this->empleados->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe otro empleado con ese documento.']);
        }

        return redirect()->to('personal/ver/' . $id)->with('ok', 'Ficha actualizada.');
    }

    /** Ficha completa: datos, turnos recientes, ausencias y documentos. */
    public function ver(int $id)
    {
        $empleado = $this->empleados
            ->select('empleados.*, usuarios.nombre AS usuario_nombre, usuarios.email AS usuario_email, usuarios.rol AS usuario_rol')
            ->join('usuarios', 'usuarios.id = empleados.usuario_id', 'left')
            ->where('empleados.id', $id)
            ->first();

        if ($empleado === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        $turnos = (new TurnoModel())
            ->where('empleado_id', $id)
            ->where('fecha >=', date('Y-m-d', strtotime('-14 days')))
            ->orderBy('fecha', 'DESC')->orderBy('hora_inicio')
            ->findAll(20);

        $ausencias = (new AusenciaModel())
            ->where('empleado_id', $id)
            ->orderBy('desde', 'DESC')
            ->findAll(12);

        return view('personal/ver', [
            'titulo'      => EmpleadoModel::nombreCompleto($empleado),
            'seccion'     => 'personal',
            'empleado'    => $empleado,
            'turnos'      => $turnos,
            'ausencias'   => $ausencias,
            'documentos'  => db_connect()->table('empleado_documentos')
                ->where('empleado_id', $id)->orderBy('id', 'DESC')->get()->getResultArray(),
            'areas'       => EmpleadoModel::AREAS,
            'contratos'   => EmpleadoModel::CONTRATOS,
            'tiposAusencia' => AusenciaModel::TIPOS,
            'tiposDocumento' => self::TIPOS_DOCUMENTO,
            'antiguedad'  => EmpleadoModel::antiguedad($empleado['fecha_ingreso'], $empleado['fecha_salida']),
        ]);
    }

    /** Da de baja o reincorpora sin perder el historial. */
    public function alternarActivo(int $id)
    {
        $empleado = $this->empleados->find($id);
        if ($empleado === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        $activo = (int) $empleado['activo'] === 1 ? 0 : 1;
        $datos  = ['activo' => $activo];

        // Al dar de baja se anota la fecha de salida si no la había
        if ($activo === 0 && empty($empleado['fecha_salida'])) {
            $datos['fecha_salida'] = date('Y-m-d');
        }

        $this->empleados->update($id, $datos);

        return redirect()->to('personal/ver/' . $id)->with('ok', $activo === 1
            ? 'Empleado reincorporado a la plantilla.'
            : 'Empleado dado de baja. Su historial de turnos y ausencias se conserva.');
    }

    // ─────────────────────────── Documentos ───────────────────────────

    public function subirDocumento(int $id)
    {
        if ($this->empleados->find($id) === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        $archivo = $this->request->getFile('documento');
        if ($archivo === null || ! $archivo->isValid()) {
            return redirect()->to('personal/ver/' . $id)->with('error', 'No se pudo leer el archivo.');
        }
        if ($archivo->getSize() > self::TAMANO_MAXIMO) {
            return redirect()->to('personal/ver/' . $id)->with('error', 'El archivo pesa más de 8 MB.');
        }

        $mime = $archivo->getMimeType();
        if (! in_array($mime, self::MIMES_PERMITIDOS, true)) {
            return redirect()->to('personal/ver/' . $id)->with('error', 'Formato no admitido. Sube una imagen o un PDF.');
        }

        $tipo   = (string) $this->request->getPost('tipo');
        $tipo   = array_key_exists($tipo, self::TIPOS_DOCUMENTO) ? $tipo : 'otro';
        $nombre = $archivo->getRandomName();
        $archivo->move(WRITEPATH . 'uploads/empleados/', $nombre);

        db_connect()->table('empleado_documentos')->insert([
            'empleado_id'     => $id,
            'tipo'            => $tipo,
            'archivo'         => $nombre,
            'nombre_original' => mb_substr($archivo->getClientName(), 0, 255),
            'mime'            => $mime,
            'tamano'          => $archivo->getSize(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('personal/ver/' . $id)->with('ok', 'Documento guardado.');
    }

    /** Sirve un documento laboral: fuera de la web, solo a gerencia. */
    public function documento(int $docId)
    {
        $doc = db_connect()->table('empleado_documentos')->where('id', $docId)->get()->getRowArray();
        if ($doc === null) {
            return $this->response->setStatusCode(404)->setBody('Documento no encontrado.');
        }

        $ruta = WRITEPATH . 'uploads/empleados/' . $doc['archivo'];
        if (! is_file($ruta)) {
            return $this->response->setStatusCode(404)->setBody('El archivo ya no está disponible.');
        }

        log_message('info', 'Documento laboral {doc} consultado por {usuario}.', [
            'doc'     => $docId,
            'usuario' => session()->get('usuario_nombre') . ' (#' . session()->get('usuario_id') . ')',
        ]);

        return $this->response
            ->setHeader('Content-Type', $doc['mime'] ?: 'application/octet-stream')
            ->setHeader('Content-Disposition', 'inline; filename="documento-' . $docId . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody(file_get_contents($ruta));
    }

    public function eliminarDocumento(int $docId)
    {
        $tabla = db_connect()->table('empleado_documentos');
        $doc   = $tabla->where('id', $docId)->get()->getRowArray();
        if ($doc === null) {
            return redirect()->to('personal');
        }

        $ruta = WRITEPATH . 'uploads/empleados/' . $doc['archivo'];
        if (is_file($ruta)) {
            @unlink($ruta);
        }
        $tabla->where('id', $docId)->delete();

        return redirect()->to('personal/ver/' . $doc['empleado_id'])->with('ok', 'Documento eliminado.');
    }

    // ─────────────────────────── Ayudantes ───────────────────────────

    /** Cuentas del sistema que aún no están vinculadas a otro empleado. */
    private function usuariosDisponibles(?int $empleadoId = null): array
    {
        $ocupados = array_column(
            db_connect()->table('empleados')
                ->select('usuario_id')->where('usuario_id IS NOT NULL', null, false)
                ->get()->getResultArray(),
            'usuario_id'
        );

        // El usuario del empleado que se está editando sí debe seguir disponible
        if ($empleadoId !== null) {
            $actual = $this->empleados->find($empleadoId);
            if ($actual !== null && $actual['usuario_id'] !== null) {
                $ocupados = array_diff($ocupados, [$actual['usuario_id']]);
            }
        }

        $usuarios = (new UsuarioModel())->orderBy('nombre')->findAll();

        return array_values(array_filter(
            $usuarios,
            static fn ($u) => ! in_array($u['id'], $ocupados, false)
        ));
    }

    private function datosEmpleado(): array
    {
        $post = $this->request->getPost();

        $usuarioId = (int) ($post['usuario_id'] ?? 0);

        return [
            'usuario_id'       => $usuarioId > 0 ? $usuarioId : null,
            'nombre'           => trim((string) ($post['nombre'] ?? '')),
            'apellidos'        => trim((string) ($post['apellidos'] ?? '')),
            'tipo_documento'   => array_key_exists($post['tipo_documento'] ?? '', EmpleadoModel::TIPOS_DOCUMENTO)
                ? $post['tipo_documento'] : 'CC',
            'num_documento'    => trim((string) ($post['num_documento'] ?? '')),
            'fecha_nacimiento' => $this->fecha($post['fecha_nacimiento'] ?? ''),
            'telefono'         => trim((string) ($post['telefono'] ?? '')) ?: null,
            'email'            => strtolower(trim((string) ($post['email'] ?? ''))) ?: null,
            'direccion'        => trim((string) ($post['direccion'] ?? '')) ?: null,
            'ciudad'           => trim((string) ($post['ciudad'] ?? '')) ?: null,

            'cargo'            => trim((string) ($post['cargo'] ?? '')),
            'area'             => array_key_exists($post['area'] ?? '', EmpleadoModel::AREAS) ? $post['area'] : 'otro',
            'tipo_contrato'    => array_key_exists($post['tipo_contrato'] ?? '', EmpleadoModel::CONTRATOS)
                ? $post['tipo_contrato'] : 'indefinido',
            'fecha_ingreso'    => $this->fecha($post['fecha_ingreso'] ?? ''),
            'fecha_salida'     => $this->fecha($post['fecha_salida'] ?? ''),
            'salario'          => (float) ($post['salario'] ?? 0),
            'jornada'          => trim((string) ($post['jornada'] ?? '')) ?: null,

            'eps'               => trim((string) ($post['eps'] ?? '')) ?: null,
            'arl'               => trim((string) ($post['arl'] ?? '')) ?: null,
            'fondo_pension'     => trim((string) ($post['fondo_pension'] ?? '')) ?: null,
            'caja_compensacion' => trim((string) ($post['caja_compensacion'] ?? '')) ?: null,
            'banco'             => trim((string) ($post['banco'] ?? '')) ?: null,
            'cuenta_bancaria'   => trim((string) ($post['cuenta_bancaria'] ?? '')) ?: null,

            'emergencia_nombre'     => trim((string) ($post['emergencia_nombre'] ?? '')) ?: null,
            'emergencia_telefono'   => trim((string) ($post['emergencia_telefono'] ?? '')) ?: null,
            'emergencia_parentesco' => trim((string) ($post['emergencia_parentesco'] ?? '')) ?: null,

            'notas'  => trim((string) ($post['notas'] ?? '')) ?: null,
            'activo' => ! empty($post['activo']) ? 1 : 0,
        ];
    }

    private function fecha(string $valor): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', trim($valor));

        return $d !== false ? $d->format('Y-m-d') : null;
    }
}
