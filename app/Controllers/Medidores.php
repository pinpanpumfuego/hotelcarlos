<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ActivoModel;
use App\Models\LecturaMedidorModel;
use App\Models\MedidorModel;
use App\Models\UnidadModel;
use RuntimeException;

/**
 * Contadores de agua, luz, gas y combustible.
 *
 * En un alojamiento junto a un lago, el agua y la luz son de los gastos que más
 * se van sin que nadie se entere. Una fuga en una tubería enterrada no se ve
 * nunca: solo se nota porque el contador sube igual con las cabañas vacías.
 */
class Medidores extends BaseController
{
    private MedidorModel $medidores;
    private LecturaMedidorModel $lecturas;

    public function __construct()
    {
        $this->medidores = new MedidorModel();
        $this->lecturas  = new LecturaMedidorModel();
    }

    public function index()
    {
        return view('medidores/index', [
            'titulo'    => 'Medidores y consumos',
            'seccion'   => 'mantenimiento',
            'medidores' => $this->medidores->conUltimaLectura(false),
            'tipos'     => MedidorModel::TIPOS,
            'unidades'  => (new UnidadModel())->orderBy('nombre')->findAll(),
            'activos'   => (new ActivoModel())->listar(),
        ]);
    }

    public function ver(int $id)
    {
        $medidor = $this->medidores->find($id);

        if ($medidor === null) {
            return redirect()->to('medidores')->with('error', 'Ese medidor no existe.');
        }

        $historial = $this->lecturas->historial($id, 60);

        return view('medidores/ver', [
            'titulo'    => $medidor['nombre'],
            'seccion'   => 'mantenimiento',
            'medidor'   => $medidor,
            'tipos'     => MedidorModel::TIPOS,
            'historial' => $historial,
            'diario'    => $this->lecturas->consumoDiarioReciente($id),
            'mes'       => $this->lecturas->consumoEntre($id, date('Y-m-01'), date('Y-m-d')),
            'unidades'  => (new UnidadModel())->orderBy('nombre')->findAll(),
            'activos'   => (new ActivoModel())->listar(),
        ]);
    }

    public function guardar(?int $id = null)
    {
        $tipo = (string) $this->request->getPost('tipo');
        $tipo = array_key_exists($tipo, MedidorModel::TIPOS) ? $tipo : 'otro';

        $unidadMedida = trim((string) $this->request->getPost('unidad_medida'));

        $datos = [
            'nombre'        => trim((string) $this->request->getPost('nombre')),
            'tipo'          => $tipo,
            // Si no la escriben, la del tipo: nadie tiene por qué acordarse de
            // que el agua va en metros cúbicos.
            'unidad_medida' => $unidadMedida !== '' ? mb_substr($unidadMedida, 0, 20) : MedidorModel::UNIDADES[$tipo],
            'unidad_id'     => (int) $this->request->getPost('unidad_id') ?: null,
            'activo_id'     => (int) $this->request->getPost('activo_id') ?: null,
            'ubicacion'     => trim((string) $this->request->getPost('ubicacion')) ?: null,
            'acumulativo'   => $this->request->getPost('acumulativo') !== null ? 1 : 0,
            'alerta_diaria' => $this->request->getPost('alerta_diaria') !== ''
                ? (float) $this->request->getPost('alerta_diaria')
                : null,
            'activa' => $this->request->getPost('activa') !== null ? 1 : 0,
            'notas'  => trim((string) $this->request->getPost('notas')) ?: null,
        ];

        if ($id === null) {
            if (! $this->medidores->insert($datos)) {
                return redirect()->to('medidores')->with('errores', $this->medidores->errors());
            }

            return redirect()->to('medidores/ver/' . $this->medidores->getInsertID())
                ->with('ok', 'Medidor creado. Apunta su lectura de hoy para empezar a contar.');
        }

        if (! $this->medidores->update($id, $datos)) {
            return redirect()->to('medidores/ver/' . $id)->with('errores', $this->medidores->errors());
        }

        return redirect()->to('medidores/ver/' . $id)->with('ok', 'Medidor actualizado.');
    }

    public function leer(int $id)
    {
        $lectura = $this->request->getPost('lectura');

        if ($lectura === null || $lectura === '') {
            return redirect()->to('medidores/ver/' . $id)->with('error', 'Escribe lo que marca el contador.');
        }

        $fecha = (string) $this->request->getPost('fecha') ?: date('Y-m-d');

        try {
            $lecturaId = $this->lecturas->apuntar(
                $id,
                (float) $lectura,
                $fecha,
                session()->get('usuario_id'),
                (string) $this->request->getPost('nota') ?: null
            );
        } catch (RuntimeException $e) {
            return redirect()->to('medidores/ver/' . $id)->with('error', $e->getMessage());
        }

        $fila    = $this->lecturas->find($lecturaId);
        $medidor = $this->medidores->find($id);
        $aviso   = '';

        if ((int) $fila['sospechosa'] === 1) {
            $aviso = ' Ojo: marca menos que la anterior. O se tecleó mal, o el contador dio la vuelta, o lo cambiaron.';
        } elseif (
            $fila['consumo'] !== null
            && $fila['dias'] > 0
            && $medidor['alerta_diaria'] !== null
            && (float) $fila['consumo'] / (int) $fila['dias'] > (float) $medidor['alerta_diaria']
        ) {
            $aviso = ' Consumo por encima de lo normal: '
                . rtrim(rtrim(number_format((float) $fila['consumo'] / (int) $fila['dias'], 2, ',', '.'), '0'), ',')
                . ' ' . $medidor['unidad_medida'] . ' al día. Merece la pena mirar si hay una fuga.';
        }

        return redirect()->to('medidores/ver/' . $id)
            ->with($aviso === '' ? 'ok' : 'error', 'Lectura apuntada.' . $aviso);
    }

    public function borrarLectura(int $lecturaId)
    {
        $fila = $this->lecturas->find($lecturaId);

        if ($fila === null) {
            return redirect()->to('medidores')->with('error', 'Esa lectura no existe.');
        }

        $this->lecturas->delete($lecturaId);

        // El consumo de las siguientes se calculó restando esta: al quitarla,
        // el hueco queda mal. Se recalcula toda la serie del medidor.
        $this->recalcular((int) $fila['medidor_id']);

        return redirect()->to('medidores/ver/' . $fila['medidor_id'])
            ->with('ok', 'Lectura borrada y consumos recalculados.');
    }

    /**
     * Rehace los consumos de un medidor a partir de sus lecturas.
     *
     * Hace falta porque el consumo de cada lectura depende de la anterior: al
     * borrar una del medio, la siguiente quedaría con un consumo que no cuadra
     * con ningún par de lecturas reales.
     */
    private function recalcular(int $medidorId): void
    {
        $medidor = $this->medidores->find($medidorId);

        if ($medidor === null) {
            return;
        }

        $filas = $this->lecturas->where('medidor_id', $medidorId)->orderBy('fecha')->findAll();
        $previa = null;

        foreach ($filas as $f) {
            $consumo    = null;
            $dias       = null;
            $sospechosa = 0;

            if ($previa !== null) {
                $dias  = max(1, (int) round((strtotime($f['fecha']) - strtotime($previa['fecha'])) / 86400));
                $bruto = (float) $f['lectura'] - (float) $previa['lectura'];

                if ((int) $medidor['acumulativo'] === 1) {
                    $bruto < 0 ? $sospechosa = 1 : $consumo = round($bruto, 3);
                } else {
                    $consumo = $bruto < 0 ? round(-$bruto, 3) : null;
                }
            }

            $this->lecturas->update($f['id'], [
                'consumo'    => $consumo,
                'dias'       => $dias,
                'sospechosa' => $sospechosa,
            ]);

            $previa = $f;
        }
    }
}
