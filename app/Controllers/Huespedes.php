<?php

namespace App\Controllers;

use App\Libraries\Crm;
use App\Models\ConsentimientoModel;
use App\Models\HuespedModel;
use App\Models\HuespedPreferenciaModel;
use RuntimeException;

class Huespedes extends BaseController
{
    private HuespedModel $huespedes;
    private Crm $crm;

    public function __construct()
    {
        $this->huespedes = new HuespedModel();
        $this->crm       = new Crm();
    }

    public function index()
    {
        $buscar = trim((string) $this->request->getGet('q'));

        // Los fusionados no salen: siguen en la tabla para no dejar reservas
        // huérfanas, pero como perfil ya no existen.
        $builder = $this->huespedes->activos()->orderBy('apellidos')->orderBy('nombre');

        if ($buscar !== '') {
            $builder = $builder->groupStart()
                ->like('nombre', $buscar)
                ->orLike('apellidos', $buscar)
                ->orLike('num_documento', $buscar)
                ->orLike('email', $buscar)
                ->orLike('telefono', $buscar)
                ->groupEnd();
        }

        return view('huespedes/index', [
            'titulo'      => 'Huéspedes',
            'seccion'     => 'huespedes',
            'huespedes'   => $builder->paginate(20),
            'paginador'   => $this->huespedes->pager,
            'totalActual' => $this->huespedes->pager->getTotal(),
            'buscar'      => $buscar,
        ]);
    }

    /**
     * La ficha completa: quién es, qué ha dejado, qué le pasa y qué autorizó.
     *
     * Es la pantalla que justifica el módulo. Antes había que abrir cuatro
     * sitios distintos para saber si alguien había venido ya.
     */
    public function ver(int $id)
    {
        $huesped = $this->huespedes->find($id);

        if ($huesped === null) {
            return redirect()->to('huespedes')->with('error', 'El huésped no existe.');
        }

        // Un perfil fusionado no tiene ficha propia: se manda a la buena, que
        // es donde están de verdad sus reservas.
        if ($huesped['estado'] === 'fusionado' && $huesped['fusionado_en'] !== null) {
            return redirect()->to('huespedes/ver/' . $huesped['fusionado_en'])
                ->with('ok', 'Ese perfil se fusionó en este.');
        }

        $verSensibles = puede('huespedes.sensibles');
        $historial    = $this->crm->historial($id);
        $valor        = $this->crm->valor($id);
        $niveles      = new \App\Models\NivelModel();
        $nivel        = $niveles->de($valor);
        $referidos    = new \App\Libraries\Referidos();

        return view('huespedes/ficha', [
            'titulo'      => trim($huesped['nombre'] . ' ' . $huesped['apellidos']),
            'seccion'     => 'huespedes',
            'huesped'     => $huesped,
            'valor'       => $valor,
            'nivel'       => $nivel,
            'beneficios'  => $niveles->beneficiosDe($nivel),
            'siguiente'   => $niveles->siguiente($valor),
            // El código solo se genera cuando el programa está en marcha: si no,
            // se llenaría la tabla de códigos que nadie va a usar.
            'codigo_referido' => $referidos->activo() ? $referidos->codigoDe($id) : null,
            'trajo'       => $referidos->deHuesped($id),
            'estados_ref' => \App\Models\ReferidoModel::ESTADOS,
            'reservas'    => $historial['reservas'],
            'encuestas'   => $historial['encuestas'],
            'solicitudes' => $historial['solicitudes'],
            'preferencias' => (new HuespedPreferenciaModel())->deHuesped($id, $verSensibles),
            'ver_sensibles' => $verSensibles,
            'tipos_pref'  => HuespedPreferenciaModel::TIPOS,
            'sensibles'   => HuespedPreferenciaModel::SENSIBLES,
            'consentimientos' => (new ConsentimientoModel())->estadoDe($id),
            'historial_consent' => puede('consentimientos.gestionar')
                ? (new ConsentimientoModel())->historial($id)
                : [],
            'finalidades' => ConsentimientoModel::FINALIDADES,
            'canales'     => ConsentimientoModel::CANALES,
            'duplicados'  => puede('huespedes.fusionar') ? $this->crm->posiblesDuplicados($huesped) : [],
            'origenes'    => HuespedModel::ORIGENES,
        ]);
    }

    public function nuevo()
    {
        return view('huespedes/form', [
            'titulo'   => 'Nuevo huésped',
            'seccion'  => 'huespedes',
            'origenes' => HuespedModel::ORIGENES,
        ]);
    }

    public function guardar()
    {
        $datos = $this->request->getPost([
            'nombre', 'apellidos', 'tipo_documento', 'num_documento', 'nacionalidad',
            'fecha_nacimiento', 'idioma', 'ciudad', 'pais', 'empresa', 'empresa_nit',
            'origen', 'telefono', 'email', 'notas', 'notas_internas',
        ]);

        try {
            if (! $this->huespedes->insert($datos)) {
                return redirect()->back()->withInput()->with('errores', $this->huespedes->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe un huésped con ese documento.']);
        }

        return redirect()->to('huespedes')->with('ok', 'Huésped registrado correctamente.');
    }

    public function editar(int $id)
    {
        $huesped = $this->huespedes->find($id);
        if ($huesped === null) {
            return redirect()->to('huespedes')->with('error', 'El huésped no existe.');
        }

        return view('huespedes/form', [
            'titulo'  => 'Editar huésped',
            'seccion' => 'huespedes',
            'huesped'  => $huesped,
            'origenes' => HuespedModel::ORIGENES,
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->huespedes->find($id) === null) {
            return redirect()->to('huespedes')->with('error', 'El huésped no existe.');
        }

        $datos = $this->request->getPost([
            'nombre', 'apellidos', 'tipo_documento', 'num_documento', 'nacionalidad',
            'fecha_nacimiento', 'idioma', 'ciudad', 'pais', 'empresa', 'empresa_nit',
            'origen', 'telefono', 'email', 'notas', 'notas_internas',
        ]);

        try {
            if (! $this->huespedes->update($id, $datos)) {
                return redirect()->back()->withInput()->with('errores', $this->huespedes->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe otro huésped con ese documento.']);
        }

        return redirect()->to('huespedes')->with('ok', 'Huésped actualizado correctamente.');
    }

    public function eliminar(int $id)
    {
        try {
            $this->huespedes->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('huespedes')->with('error', 'No se pudo eliminar: el huésped tiene reservas asociadas.');
        }

        return redirect()->to('huespedes')->with('ok', 'Huésped eliminado.');
    }

    // ── Preferencias y alergias ─────────────────────────────────────────

    public function anadirPreferencia(int $id)
    {
        if ($this->huespedes->find($id) === null) {
            return redirect()->to('huespedes')->with('error', 'El huésped no existe.');
        }

        $tipo = (string) $this->request->getPost('tipo');
        $tipo = array_key_exists($tipo, HuespedPreferenciaModel::TIPOS) ? $tipo : 'otro';

        // Apuntar una alergia sin poder verla después dejaría un dato de salud
        // escrito por alguien que no puede consultarlo. O las dos cosas o ninguna.
        if (in_array($tipo, HuespedPreferenciaModel::SENSIBLES, true) && ! puede('huespedes.sensibles')) {
            return redirect()->to('huespedes/ver/' . $id)
                ->with('error', 'No tienes permiso para apuntar alergias ni datos de salud.');
        }

        $preferencias = new HuespedPreferenciaModel();

        $ok = $preferencias->insert([
            'huesped_id' => $id,
            'tipo'       => $tipo,
            'valor'      => trim((string) $this->request->getPost('valor')),
            'nota'       => trim((string) $this->request->getPost('nota')) ?: null,
            'origen'     => 'recepcion',
            // Una alergia siempre es crítica: no es una preferencia, es algo
            // que puede acabar en un hospital.
            'critica'    => $tipo === 'alergia' ? 1 : ($this->request->getPost('critica') !== null ? 1 : 0),
            'usuario_id' => session()->get('usuario_id'),
        ]);

        if (! $ok) {
            return redirect()->to('huespedes/ver/' . $id)->with('errores', $preferencias->errors());
        }

        return redirect()->to('huespedes/ver/' . $id)->with('ok', 'Apuntado.');
    }

    public function borrarPreferencia(int $prefId)
    {
        $preferencias = new HuespedPreferenciaModel();
        $pref         = $preferencias->find($prefId);

        if ($pref === null) {
            return redirect()->to('huespedes')->with('error', 'Eso ya no existe.');
        }

        if (in_array($pref['tipo'], HuespedPreferenciaModel::SENSIBLES, true) && ! puede('huespedes.sensibles')) {
            return redirect()->to('huespedes/ver/' . $pref['huesped_id'])
                ->with('error', 'No tienes permiso para tocar datos de salud.');
        }

        $preferencias->delete($prefId);

        return redirect()->to('huespedes/ver/' . $pref['huesped_id'])->with('ok', 'Borrado.');
    }

    // ── Consentimientos ─────────────────────────────────────────────────

    /**
     * Apunta que alguien dio o retiró una autorización, en persona.
     *
     * Se guarda quién lo apuntó y desde dónde: si un día lo discuten, «lo dijo
     * de palabra en recepción» no vale de nada sin decir quién lo recogió.
     */
    public function consentimiento(int $id)
    {
        $finalidad = (string) $this->request->getPost('finalidad');
        $canal     = (string) $this->request->getPost('canal');
        $otorgar   = $this->request->getPost('otorgar') === '1';

        $prueba = [
            'origen'      => 'recepcion',
            'ip'          => $this->request->getIPAddress(),
            'dispositivo' => $this->request->getUserAgent()->getAgentString(),
            'nota'        => trim((string) $this->request->getPost('nota')) ?: null,
            'usuario_id'  => session()->get('usuario_id'),
        ];

        try {
            $otorgar
                ? $this->crm->otorgar($id, $finalidad, $canal, $prueba)
                : $this->crm->retirar($id, $finalidad, $canal, $prueba);
        } catch (RuntimeException $e) {
            return redirect()->to('huespedes/ver/' . $id)->with('error', $e->getMessage());
        }

        return redirect()->to('huespedes/ver/' . $id)->with(
            'ok',
            $otorgar ? 'Autorización apuntada.' : 'Autorización retirada. No se le escribirá más para eso.'
        );
    }

    // ── Duplicados ──────────────────────────────────────────────────────

    public function fusionar(int $ganadorId)
    {
        $perdedorId = (int) $this->request->getPost('perdedor_id');

        try {
            $r = $this->crm->fusionar($ganadorId, $perdedorId, session()->get('usuario_id'));
        } catch (RuntimeException $e) {
            return redirect()->to('huespedes/ver/' . $ganadorId)->with('error', $e->getMessage());
        }

        return redirect()->to('huespedes/ver/' . $ganadorId)->with('ok', sprintf(
            'Perfiles fusionados: %d reserva(s), %d preferencia(s) y %d consentimiento(s) pasaron a esta ficha. '
            . 'El perfil viejo se conserva apuntando a este.',
            $r['reservas'],
            $r['preferencias'],
            $r['consentimientos']
        ));
    }
}
