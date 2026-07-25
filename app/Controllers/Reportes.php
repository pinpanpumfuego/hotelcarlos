<?php

namespace App\Controllers;

use App\Models\FolioModel;
use App\Models\ReservaModel;
use App\Models\UnidadModel;

/** Reportes gerenciales: ocupación, ingresos y ventas por periodo. */
class Reportes extends BaseController
{
    private const MAX_DIAS = 92;

    /** Estados que cuentan como venta real (no pendientes ni canceladas). */
    private const ESTADOS_VENDIDOS = ['confirmada', 'checkin', 'checkout'];

    public function index()
    {
        [$desde, $hasta] = $this->rangoFechas();
        $datos           = $this->calcular($desde, $hasta);

        return view('reportes/index', $datos + [
            'titulo'  => 'Reportes',
            'seccion' => 'reportes',
            'desde'   => $desde,
            'hasta'   => $hasta,
        ]);
    }

    /** Exporta las reservas del periodo en CSV (compatible con Excel). */
    public function csv()
    {
        [$desde, $hasta] = $this->rangoFechas();

        $reservas = (new ReservaModel())
            ->select('reservas.codigo, huespedes.nombre, huespedes.apellidos, unidades.nombre AS unidad,
                      reservas.fecha_entrada, reservas.fecha_salida, reservas.adultos, reservas.ninos,
                      reservas.estado, reservas.total, reservas.created_at')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('reservas.fecha_entrada <=', $hasta)
            ->where('reservas.fecha_salida >=', $desde)
            ->orderBy('reservas.fecha_entrada')
            ->findAll();

        $salida = "\xEF\xBB\xBF"; // BOM para que Excel abra bien los acentos
        $salida .= "Codigo;Huesped;Cabana;Entrada;Salida;Adultos;Ninos;Estado;Total COP;Creada\n";
        foreach ($reservas as $r) {
            $salida .= implode(';', [
                $r['codigo'],
                '"' . str_replace('"', '""', $r['nombre'] . ' ' . $r['apellidos']) . '"',
                '"' . str_replace('"', '""', $r['unidad']) . '"',
                $r['fecha_entrada'],
                $r['fecha_salida'],
                $r['adultos'],
                $r['ninos'],
                $r['estado'],
                (int) $r['total'],
                $r['created_at'],
            ]) . "\n";
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="reservas_' . $desde . '_a_' . $hasta . '.csv"')
            ->setBody($salida);
    }

    private function rangoFechas(): array
    {
        $desde = \DateTime::createFromFormat('Y-m-d', (string) $this->request->getGet('desde')) ?: new \DateTime(date('Y-m-01'));
        $hasta = \DateTime::createFromFormat('Y-m-d', (string) $this->request->getGet('hasta')) ?: new \DateTime('today');

        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        if ($desde->diff($hasta)->days > self::MAX_DIAS) {
            $hasta = (clone $desde)->modify('+' . self::MAX_DIAS . ' days');
        }

        return [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
    }

    private function calcular(string $desde, string $hasta): array
    {
        $reservaModel = new ReservaModel();
        $folioModel   = new FolioModel();
        $db           = db_connect();

        $totalUnidades = (new UnidadModel())->countAllResults();
        $finExclusivo  = (new \DateTime($hasta))->modify('+1 day')->format('Y-m-d');

        // Reservas vendidas que tocan el periodo
        $vendidas = $reservaModel
            ->whereIn('estado', self::ESTADOS_VENDIDOS)
            ->where('fecha_entrada <', $finExclusivo)
            ->where('fecha_salida >', $desde)
            ->findAll();

        // Noches vendidas y ocupación por día
        $ocupacionDia = [];
        for ($d = new \DateTime($desde); $d->format('Y-m-d') <= $hasta; $d->modify('+1 day')) {
            $ocupacionDia[$d->format('Y-m-d')] = 0;
        }
        $nochesVendidas = 0;
        foreach ($vendidas as $r) {
            $ini = max($r['fecha_entrada'], $desde);
            $fin = min($r['fecha_salida'], $finExclusivo);
            for ($d = new \DateTime($ini); $d->format('Y-m-d') < $fin; $d->modify('+1 day')) {
                $clave = $d->format('Y-m-d');
                if (isset($ocupacionDia[$clave])) {
                    $ocupacionDia[$clave]++;
                    $nochesVendidas++;
                }
            }
        }

        $diasPeriodo    = count($ocupacionDia);
        $nochesPosibles = $diasPeriodo * $totalUnidades;

        // Dinero del folio en el periodo
        $pagos = (float) ($folioModel->selectSum('valor')->where('tipo', 'pago')
            ->where('DATE(created_at) >=', $desde)->where('DATE(created_at) <=', $hasta)->first()['valor'] ?? 0);
        $cargos = (float) ($folioModel->selectSum('valor')->where('tipo', 'cargo')
            ->where('DATE(created_at) >=', $desde)->where('DATE(created_at) <=', $hasta)->first()['valor'] ?? 0);
        $cargosAlojamiento = (float) ($folioModel->selectSum('valor')->where('tipo', 'cargo')
            ->like('concepto', 'Alojamiento', 'after')
            ->where('DATE(created_at) >=', $desde)->where('DATE(created_at) <=', $hasta)->first()['valor'] ?? 0);

        $pagosPorMetodo = $db->table('folio_movimientos')
            ->select("metodo, SUM(valor) AS total")
            ->where('tipo', 'pago')
            ->where('DATE(created_at) >=', $desde)->where('DATE(created_at) <=', $hasta)
            ->groupBy('metodo')->get()->getResultArray();

        $pagosPorDia = $db->table('folio_movimientos')
            ->select("DATE(created_at) AS dia, SUM(valor) AS total")
            ->where('tipo', 'pago')
            ->where('DATE(created_at) >=', $desde)->where('DATE(created_at) <=', $hasta)
            ->groupBy('DATE(created_at)')->get()->getResultArray();
        $ingresosDia = array_fill_keys(array_keys($ocupacionDia), 0);
        foreach ($pagosPorDia as $p) {
            $ingresosDia[$p['dia']] = (float) $p['total'];
        }

        // Actividad de reservas creadas/canceladas en el periodo
        $creadas = $reservaModel->where('DATE(created_at) >=', $desde)
            ->where('DATE(created_at) <=', $hasta)->countAllResults();
        $canceladas = $reservaModel->where('estado', 'cancelada')
            ->where('DATE(updated_at) >=', $desde)->where('DATE(updated_at) <=', $hasta)->countAllResults();

        return [
            'totalUnidades'   => $totalUnidades,
            'nochesVendidas'  => $nochesVendidas,
            'nochesPosibles'  => $nochesPosibles,
            'ocupacionPct'    => $nochesPosibles > 0 ? round($nochesVendidas / $nochesPosibles * 100, 1) : 0,
            'pagos'           => $pagos,
            'cargos'          => $cargos,
            'adr'             => $nochesVendidas > 0 ? round($cargosAlojamiento / $nochesVendidas) : 0,
            'revpar'          => $nochesPosibles > 0 ? round($cargosAlojamiento / $nochesPosibles) : 0,
            'ocupacionDia'    => $ocupacionDia,
            'ingresosDia'     => $ingresosDia,
            'pagosPorMetodo'  => $pagosPorMetodo,
            'creadas'         => $creadas,
            'canceladas'      => $canceladas,
        ];
    }
}
