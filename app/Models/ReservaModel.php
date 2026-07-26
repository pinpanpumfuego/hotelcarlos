<?php

namespace App\Models;

use CodeIgniter\Model;

class ReservaModel extends Model
{
    protected $table         = 'reservas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['codigo', 'huesped_id', 'unidad_id', 'fecha_entrada', 'fecha_salida', 'adultos', 'ninos', 'estado', 'total', 'desglose_precio', 'notas'];
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
     * El día de salida no cuenta como ocupado: otra reserva puede entrar ese mismo día.
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

        return $builder->countAllResults() === 0;
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
