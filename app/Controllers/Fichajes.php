<?php

namespace App\Controllers;

use App\Models\EmpleadoModel;
use App\Models\FichajeModel;

/** Control de jornada para gerencia: quién está, cuántas horas y correcciones. */
class Fichajes extends BaseController
{
    private FichajeModel $fichajes;
    private EmpleadoModel $empleados;

    public function __construct()
    {
        $this->fichajes  = new FichajeModel();
        $this->empleados = new EmpleadoModel();
    }

    public function index()
    {
        [$desde, $hasta] = $this->periodo();
        $empleadoId = (int) $this->request->getGet('empleado') ?: null;

        return view('fichajes/index', [
            'titulo'    => 'Control de jornada',
            'seccion'   => 'fichajes',
            'desde'     => $desde,
            'hasta'     => $hasta,
            'empleadoId' => $empleadoId,
            'empleados' => $this->empleados->where('activo', 1)->orderBy('apellidos')->findAll(),
            'presentes' => $this->fichajes->presentes(),
            'resumen'   => $this->fichajes->resumen($desde, $hasta),
            'marcas'    => $this->fichajes->listado($desde, $hasta, $empleadoId),
            'sinPin'    => $this->empleados->sinPin(),
        ]);
    }

    /** Ficha de jornada de un empleado, día a día. */
    public function empleado(int $id)
    {
        $empleado = $this->empleados->find($id);
        if ($empleado === null) {
            return redirect()->to('fichajes')->with('error', 'El empleado no existe.');
        }

        [$desde, $hasta] = $this->periodo();

        return view('fichajes/empleado', [
            'titulo'   => 'Jornada de ' . $empleado['nombre'],
            'seccion'  => 'fichajes',
            'empleado' => $empleado,
            'desde'    => $desde,
            'hasta'    => $hasta,
            'jornadas' => array_reverse($this->fichajes->jornadas($id, $desde, $hasta), true),
        ]);
    }

    /**
     * Añade una marca a mano: alguien se olvidó de fichar.
     * Queda marcada como «manual» y con quién la puso.
     */
    public function anadir()
    {
        $empleadoId = (int) $this->request->getPost('empleado_id');
        $fecha      = (string) $this->request->getPost('fecha');
        $hora       = (string) $this->request->getPost('hora');
        $tipo       = (string) $this->request->getPost('tipo');

        if ($this->empleados->find($empleadoId) === null) {
            return redirect()->to('fichajes')->with('error', 'Elige un empleado.');
        }
        if (! array_key_exists($tipo, FichajeModel::TIPOS)) {
            return redirect()->to('fichajes')->with('error', 'Tipo de marca no válido.');
        }
        if (strtotime($fecha . ' ' . $hora) === false) {
            return redirect()->to('fichajes')->with('error', 'Revisa la fecha y la hora.');
        }
        if (strtotime($fecha . ' ' . $hora) > time() + 300) {
            return redirect()->to('fichajes')->with('error', 'No se pueden anotar marcas en el futuro.');
        }

        $motivo = trim((string) $this->request->getPost('motivo'));
        if ($motivo === '') {
            return redirect()->to('fichajes')->with('error', 'Escribe por qué añades esta marca a mano.');
        }

        $this->fichajes->insert([
            'empleado_id' => $empleadoId,
            'tipo'        => $tipo,
            'marcado_en'  => date('Y-m-d H:i:s', strtotime($fecha . ' ' . $hora)),
            'origen'      => 'manual',
            'ip'          => $this->request->getIPAddress(),
            'motivo'      => $motivo,
            'editado_por' => session()->get('usuario_id'),
            'editado_en'  => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('fichajes')->with('ok', 'Marca añadida a mano y anotada como tal.');
    }

    /** Anula una marca sin borrarla: el registro tiene que ser fiable. */
    public function anular(int $id)
    {
        $fichaje = $this->fichajes->find($id);
        if ($fichaje === null) {
            return redirect()->to('fichajes')->with('error', 'Esa marca no existe.');
        }

        $motivo = trim((string) $this->request->getPost('motivo'));
        if ($motivo === '') {
            return redirect()->to('fichajes')->with('error', 'Indica el motivo de la anulación.');
        }

        $this->fichajes->update($id, [
            'anulado'     => 1,
            'motivo'      => $motivo,
            'editado_por' => session()->get('usuario_id'),
            'editado_en'  => date('Y-m-d H:i:s'),
        ]);

        return redirect()->back()->with('ok', 'Marca anulada. Sigue en el historial con el motivo.');
    }

    /** Sirve la foto de un fichaje. Solo gerencia, y queda registrado quién la ve. */
    public function foto(int $id)
    {
        $fichaje = $this->fichajes->find($id);
        if ($fichaje === null || $fichaje['foto'] === null) {
            return $this->response->setStatusCode(404)->setBody('No encontrada.');
        }

        $ruta = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'fichajes' . DIRECTORY_SEPARATOR . $fichaje['foto'];
        if (! is_file($ruta)) {
            return $this->response->setStatusCode(404)->setBody('No encontrada.');
        }

        log_message('info', 'Foto del fichaje {f} consultada por el usuario {u}', [
            'f' => $id, 'u' => session()->get('usuario_id'),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'image/jpeg')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody(file_get_contents($ruta));
    }

    /** Genera o cambia el PIN de un empleado. */
    public function pin(int $empleadoId)
    {
        $empleado = $this->empleados->find($empleadoId);
        if ($empleado === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        $pin = trim((string) $this->request->getPost('pin'));

        // Sin PIN escrito se genera uno al azar: es más seguro que dejarlo elegir
        if ($pin === '') {
            do {
                $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $r   = $this->empleados->fijarPin($empleadoId, $pin);
            } while (! $r['ok']);

            return redirect()->to('personal/ver/' . $empleadoId)
                ->with('ok', 'PIN generado: ' . $pin . ' — anótalo ahora, no se vuelve a mostrar.');
        }

        $r = $this->empleados->fijarPin($empleadoId, $pin);

        return redirect()->to('personal/ver/' . $empleadoId)->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    /** Quita el PIN: el empleado deja de poder fichar. */
    public function quitarPin(int $empleadoId)
    {
        if ($this->empleados->find($empleadoId) === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        $this->empleados->update($empleadoId, ['pin_hash' => null, 'pin_actualizado' => null]);

        return redirect()->to('personal/ver/' . $empleadoId)
            ->with('ok', 'PIN retirado. Esta persona ya no puede fichar hasta que se le dé uno nuevo.');
    }

    /** Permite o impide que una persona fiche desde su móvil. */
    public function alternarMovil(int $empleadoId)
    {
        $empleado = $this->empleados->find($empleadoId);
        if ($empleado === null) {
            return redirect()->to('personal')->with('error', 'El empleado no existe.');
        }

        $nuevo = (int) $empleado['ficha_movil'] === 1 ? 0 : 1;
        $this->empleados->update($empleadoId, ['ficha_movil' => $nuevo]);

        return redirect()->to('personal/ver/' . $empleadoId)->with(
            'ok',
            $nuevo === 1
                ? 'Ya puede fichar desde su móvil.'
                : 'Solo podrá fichar en el terminal del hotel.'
        );
    }

    /** Periodo consultado: por defecto, la semana en curso. */
    private function periodo(): array
    {
        $desde = (string) ($this->request->getGet('desde') ?: date('Y-m-d', strtotime('monday this week')));
        $hasta = (string) ($this->request->getGet('hasta') ?: date('Y-m-d'));

        if (strtotime($desde) === false) {
            $desde = date('Y-m-d', strtotime('monday this week'));
        }
        if (strtotime($hasta) === false || $hasta < $desde) {
            $hasta = date('Y-m-d');
        }
        // Un rango enorme haría lentísima la reconstrucción de jornadas
        if ((strtotime($hasta) - strtotime($desde)) / 86400 > 92) {
            $desde = date('Y-m-d', strtotime($hasta . ' -92 days'));
        }

        return [$desde, $hasta];
    }
}
