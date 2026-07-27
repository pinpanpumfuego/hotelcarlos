<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Crm;
use App\Models\ConfiguracionModel;
use App\Models\NivelModel;
use App\Models\ReferidoModel;

/**
 * Niveles, beneficios y referidos.
 *
 * La pantalla enseña, junto a cada nivel, **a cuánta gente le tocaría hoy**.
 * Sin ese número, ajustar un umbral es adivinar: se pone «cinco estancias»
 * porque suena bien y resulta que no lo cumple nadie, o lo cumplen todos.
 */
class Niveles extends BaseController
{
    private NivelModel $niveles;

    public function __construct()
    {
        $this->niveles = new NivelModel();
    }

    public function index()
    {
        $niveles = $this->niveles->listar();
        $reparto = $this->reparto();

        foreach ($niveles as &$n) {
            $n['cuantos'] = $reparto[$n['clave']] ?? 0;
        }

        $config = new ConfiguracionModel();

        return view('niveles/index', [
            'titulo'   => 'Niveles y referidos',
            'seccion'  => 'huespedes',
            'niveles'  => $niveles,
            'total'    => array_sum($reparto),
            'referidos' => (new ReferidoModel())->listar(),
            'estados_ref' => ReferidoModel::ESTADOS,
            'ref' => [
                'activo' => $config->obtener('referido_activo', '0') === '1',
                'pct'    => (float) $config->obtener('referido_premio_pct', '10'),
                'dias'   => (int) $config->obtener('referido_premio_dias', '365'),
            ],
        ]);
    }

    /**
     * Cuántos huéspedes caen hoy en cada nivel.
     *
     * Se calcula recorriendo a los que han venido alguna vez. Con siete cabañas
     * eso son cientos, no millones: una consulta agregada sería más rápida pero
     * duplicaría la regla de «basta con cumplir uno de los dos criterios», y
     * dos copias de una regla acaban discrepando.
     *
     * @return array<string, int>
     */
    private function reparto(): array
    {
        $crm     = new Crm();
        $reparto = [];

        $huespedes = db_connect()->query(
            "SELECT DISTINCT h.id
             FROM huespedes h
             JOIN reservas r ON r.huesped_id = h.id AND r.estado IN ('checkout','checkin')
             WHERE h.estado = 'activo'"
        )->getResultArray();

        foreach ($huespedes as $h) {
            $nivel = $this->niveles->de($crm->valor((int) $h['id']));

            if ($nivel !== null) {
                $reparto[$nivel['clave']] = ($reparto[$nivel['clave']] ?? 0) + 1;
            }
        }

        return $reparto;
    }

    public function guardar(int $id)
    {
        if ($this->niveles->find($id) === null) {
            return redirect()->to('niveles')->with('error', 'Ese nivel no existe.');
        }

        $descuento = (float) $this->request->getPost('descuento_pct');

        $datos = [
            'nombre'        => trim((string) $this->request->getPost('nombre')),
            'estancias_min' => max(0, (int) $this->request->getPost('estancias_min')),
            'gasto_min'     => max(0, (float) $this->request->getPost('gasto_min')),
            // Un 100 % es una estancia regalada. Si algún día se quiere, que se
            // decida a mano y no por un dedo en el teclado numérico.
            'descuento_pct' => max(0, min(50, $descuento)),
            'beneficios'    => trim((string) $this->request->getPost('beneficios')) ?: null,
            'activo'        => $this->request->getPost('activo') !== null ? 1 : 0,
        ];

        if (! $this->niveles->update($id, $datos)) {
            return redirect()->to('niveles')->with('errores', $this->niveles->errors());
        }

        $aviso = $descuento > 50 ? ' El descuento se limitó al 50 %.' : '';

        return redirect()->to('niveles')->with('ok', 'Nivel guardado.' . $aviso);
    }

    public function guardarReferidos()
    {
        $pct = (float) $this->request->getPost('premio_pct');

        (new ConfiguracionModel())->guardarPares([
            'referido_activo'      => $this->request->getPost('activo') !== null ? '1' : '0',
            'referido_premio_pct'  => (string) max(0, min(50, $pct)),
            'referido_premio_dias' => (string) max(30, (int) $this->request->getPost('premio_dias')),
        ]);

        return redirect()->to('niveles')->with('ok', 'Programa de referidos guardado.');
    }
}
