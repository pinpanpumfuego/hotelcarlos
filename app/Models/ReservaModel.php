<?php

namespace App\Models;

use CodeIgniter\Model;

class ReservaModel extends Model
{
    protected $table         = 'reservas';
    protected $primaryKey    = 'id';
    // `cancelada_en` y `cancelada_origen` faltaban aquí: existían en la tabla
    // desde hace tres migraciones y CodeIgniter las descartaba en silencio al
    // guardar, así que ninguna cancelación dejaba rastro de cuándo ni por qué.
    protected $allowedFields = ['codigo', 'huesped_id', 'unidad_id', 'fecha_entrada', 'fecha_salida', 'adultos', 'ninos', 'estado', 'canal', 'comision', 'referencia_externa', 'total', 'desglose_precio', 'notas', 'expira_en', 'cancelada_en', 'cancelada_origen', 'cuenta_id'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'huesped_id'    => 'required|is_natural_no_zero',
        'unidad_id'     => 'required|is_natural_no_zero',
        'fecha_entrada' => 'required|valid_date[Y-m-d]',
        'fecha_salida'  => 'required|valid_date[Y-m-d]',
        'adultos'       => 'required|is_natural_no_zero',
        'estado'        => 'required|in_list[pendiente,confirmada,checkin,checkout,cancelada]',
    ];

    protected $validationMessages = [
        'huesped_id' => ['required' => 'Debes elegir un huésped.', 'is_natural_no_zero' => 'Huésped no válido.'],
        'unidad_id'  => ['required' => 'Debes elegir una unidad.', 'is_natural_no_zero' => 'Unidad no válida.'],
        'adultos'    => ['required' => 'Indica el número de adultos.', 'is_natural_no_zero' => 'Debe haber al menos un adulto.'],
    ];

    /** Estados que ocupan inventario: bloquean la unidad en sus fechas. */
    public const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'checkin'];

    /** Genera un código de reserva único, p. ej. HC-4F7A2B. */
    public function generarCodigo(): string
    {
        do {
            $codigo = 'HC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while ($this->where('codigo', $codigo)->countAllResults() > 0);

        return $codigo;
    }

    /** Reservas con datos del huésped y de la unidad. */
    public function conDetalles()
    {
        return $this->consultaDetallada()->findAll();
    }

    /**
     * Consulta base de reservas con huésped y unidad, lista para filtrar o paginar.
     * $filtros admite: estado, buscar (código, nombre, apellidos o documento).
     */
    public function consultaDetallada(array $filtros = []): self
    {
        $this->select('reservas.*, huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos,
                       huespedes.num_documento, unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id');

        if (! empty($filtros['estado'])) {
            if ($filtros['estado'] === 'activas') {
                $this->whereIn('reservas.estado', self::ESTADOS_ACTIVOS);
            } else {
                $this->where('reservas.estado', $filtros['estado']);
            }
        }

        if (! empty($filtros['buscar'])) {
            $texto = $filtros['buscar'];
            $this->groupStart()
                ->like('reservas.codigo', $texto)
                ->orLike('huespedes.nombre', $texto)
                ->orLike('huespedes.apellidos', $texto)
                ->orLike('huespedes.num_documento', $texto)
                ->groupEnd();
        }

        return $this->orderBy('reservas.fecha_entrada', 'DESC')->orderBy('reservas.id', 'DESC');
    }

    /**
     * Comprueba si la unidad está libre en el rango [entrada, salida).
     *
     * El día de salida no cuenta como ocupado: otra reserva puede entrar ese
     * mismo día. Además de nuestras reservas se miran los **bloqueos**: las
     * noches vendidas en Booking o Airbnb, o cerradas a mano.
     */
    public function unidadDisponible(int $unidadId, string $entrada, string $salida, ?int $excluirReservaId = null): bool
    {
        $builder = $this->where('unidad_id', $unidadId)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->where('fecha_entrada <', $salida)
            ->where('fecha_salida >', $entrada);

        if ($excluirReservaId !== null) {
            $builder->where('id !=', $excluirReservaId);
        }

        if ($builder->countAllResults() > 0) {
            return false;
        }

        return ! (new BloqueoModel())->unidadBloqueada($unidadId, $entrada, $salida);
    }

    /**
     * Cuántas unidades quedan libres **cada noche** de un rango.
     *
     * Existe para el calendario de la web, que necesita saberlo de 60 o 90
     * noches seguidas. Hacerlo con `unidadesLibresDelTipo()` por día serían
     * cientos de consultas; aquí se piden las reservas y los bloqueos **una
     * sola vez** y se reparten por noche en memoria.
     *
     * Una reserva del 10 al 12 ocupa las noches del 10 y el 11, no la del 12:
     * el día de salida la cabaña vuelve a estar libre. Confundir eso es el
     * error clásico que hace perder una noche por reserva.
     *
     * @return array<string, array<int,int>> fecha => tipo_id => unidades libres
     */
    public function libresPorNoche(string $desde, string $hasta): array
    {
        $db = $this->db;

        // Unidades vendibles, agrupadas por tipo
        $unidades = $db->table('unidades')
            ->select('id, tipo_id')
            ->whereNotIn('estado', ['bloqueada'])
            ->get()->getResultArray();

        $tipoDeUnidad = [];
        $totalPorTipo = [];
        foreach ($unidades as $u) {
            $tipoDeUnidad[(int) $u['id']] = (int) $u['tipo_id'];
            $totalPorTipo[(int) $u['tipo_id']] = ($totalPorTipo[(int) $u['tipo_id']] ?? 0) + 1;
        }

        // Todo lo que ocupa, de una vez: reservas y bloqueos (que incluyen las
        // noches vendidas en Booking o Airbnb)
        $ocupaciones = [];

        $reservas = $this->select('unidad_id, fecha_entrada, fecha_salida')
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->where('unidad_id IS NOT NULL')
            ->where('fecha_entrada <', $hasta)
            ->where('fecha_salida >', $desde)
            ->findAll();

        foreach ($reservas as $r) {
            $ocupaciones[] = [(int) $r['unidad_id'], $r['fecha_entrada'], $r['fecha_salida']];
        }

        foreach ((new BloqueoModel())->enRango($desde, $hasta) as $b) {
            if (! empty($b['unidad_id'])) {
                $ocupaciones[] = [(int) $b['unidad_id'], $b['fecha_entrada'], $b['fecha_salida']];
            }
        }

        // Se marca noche a noche qué unidades están tomadas
        $tomadas = [];
        foreach ($ocupaciones as [$unidadId, $entrada, $salida]) {
            $noche = max($entrada, $desde);
            $fin   = min($salida, $hasta);
            while ($noche < $fin) {
                $tomadas[$noche][$unidadId] = true;
                $noche = date('Y-m-d', strtotime($noche . ' +1 day'));
            }
        }

        $libres = [];
        for ($f = $desde; $f < $hasta; $f = date('Y-m-d', strtotime($f . ' +1 day'))) {
            $libres[$f] = $totalPorTipo;   // se parte del total y se descuenta
            foreach (array_keys($tomadas[$f] ?? []) as $unidadId) {
                $tipo = $tipoDeUnidad[$unidadId] ?? null;
                if ($tipo !== null && isset($libres[$f][$tipo])) {
                    $libres[$f][$tipo]--;
                }
            }
        }

        return $libres;
    }

    /** Unidades de un tipo que están libres en el rango [entrada, salida). */
    public function unidadesLibresDelTipo(int $tipoId, string $entrada, string $salida): array
    {
        $unidades = $this->db->table('unidades')
            ->where('tipo_id', $tipoId)
            ->whereNotIn('estado', ['bloqueada'])
            ->orderBy('nombre')
            ->get()
            ->getResultArray();

        return array_values(array_filter(
            $unidades,
            fn ($u) => $this->unidadDisponible((int) $u['id'], $entrada, $salida)
        ));
    }

    /**
     * Cotización completa de la estancia con el motor de tarifas:
     * precio noche a noche, ajustes aplicados y suplementos.
     */
    public function cotizar(int $unidadId, string $entrada, string $salida, int $adultos = 2, int $ninos = 0, ?int $excluirReservaId = null): array
    {
        return (new \App\Libraries\MotorTarifas())
            ->cotizarUnidad($unidadId, $entrada, $salida, $adultos, $ninos, $excluirReservaId);
    }

    /** Total de la estancia según el motor de tarifas (temporadas, reglas y topes). */
    public function calcularTotal(int $unidadId, string $entrada, string $salida, int $adultos = 2, int $ninos = 0, ?int $excluirReservaId = null): float
    {
        return (float) $this->cotizar($unidadId, $entrada, $salida, $adultos, $ninos, $excluirReservaId)['total'];
    }
}
