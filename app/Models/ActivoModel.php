<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Los activos: lo que se avería y hay que mantener.
 *
 * Un activo es cualquier cosa con la que alguien puede aparecer diciendo «esto
 * no funciona»: el calentador de la cabaña Sinsonte, la bomba de la piscina, la
 * planta eléctrica, la camioneta. Lo que los distingue de un mueble cualquiera
 * es que tienen historia: se compran, se rompen, se arreglan y un día no
 * compensa arreglarlos más.
 */
class ActivoModel extends Model
{
    protected $table         = 'activos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'codigo', 'nombre', 'categoria', 'unidad_id', 'ubicacion',
        'marca', 'modelo', 'serie', 'proveedor_id', 'fecha_compra',
        'valor_compra', 'garantia_hasta', 'vida_util_meses',
        'estado', 'critico', 'notas', 'baja_en', 'baja_motivo',
    ];

    public const CATEGORIAS = [
        'cerradura'        => 'Cerraduras',
        'calentador'       => 'Calentadores de agua',
        'bomba'            => 'Bombas y motores',
        'piscina'          => 'Piscina',
        'planta_electrica' => 'Planta eléctrica',
        'extintor'         => 'Extintores',
        'cocina'           => 'Equipos de cocina',
        'frio'             => 'Neveras y congeladores',
        'clima'            => 'Ventilación y clima',
        'vehiculo'         => 'Vehículos',
        'mobiliario'       => 'Mobiliario',
        'electronica'      => 'Electrónica y sonido',
        'red'              => 'Red e internet',
        'jardin'           => 'Jardín y exteriores',
        'otro'             => 'Otros',
    ];

    public const ESTADOS = [
        'activo'     => 'En servicio',
        'averiado'   => 'Averiado',
        'reparacion' => 'En reparación',
        'baja'       => 'Dado de baja',
    ];

    /** Categorías que la ley colombiana obliga a revisar con periodicidad. */
    public const CON_REVISION_OBLIGATORIA = ['extintor', 'planta_electrica', 'piscina'];

    protected $validationRules = [
        'nombre'    => 'required|min_length[2]|max_length[120]',
        'categoria' => 'required|in_list[cerradura,calentador,bomba,piscina,planta_electrica,extintor,cocina,frio,clima,vehiculo,mobiliario,electronica,red,jardin,otro]',
    ];

    protected $validationMessages = [
        'nombre'    => ['required' => 'Ponle un nombre al equipo.'],
        'categoria' => ['required' => 'Elige una categoría.'],
    ];

    /**
     * Un código corto y único para la etiqueta.
     *
     * Va impreso debajo del QR a propósito: si la etiqueta se despega o se
     * quema, el técnico puede teclearlo. Por eso no lleva letras que se
     * confundan al leerlas a mano (I con 1, O con 0).
     */
    public function siguienteCodigo(?string $prefijo = null): string
    {
        $prefijo = strtoupper(trim($prefijo ?? (string) (new ConfiguracionModel())->obtener('mant_prefijo', 'SAL')));
        $prefijo = preg_replace('/[^A-Z0-9]/', '', $prefijo) ?: 'SAL';

        $ultimo = $this->like('codigo', $prefijo . '-', 'after')
            ->orderBy('id', 'DESC')
            ->first();

        $numero = 1;

        if ($ultimo !== null && preg_match('/-(\d+)$/', $ultimo['codigo'], $m) === 1) {
            $numero = (int) $m[1] + 1;
        }

        // Si alguien creó uno a mano con ese número, se sigue subiendo
        do {
            $codigo = $prefijo . '-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
            $numero++;
        } while ($this->where('codigo', $codigo)->countAllResults() > 0);

        return $codigo;
    }

    public function porCodigo(string $codigo): ?array
    {
        return $this->consulta()->where('activos.codigo', strtoupper(trim($codigo)))->first();
    }

    /**
     * El listado, con el nombre de la cabaña y del proveedor.
     *
     * @param array{categoria?: string, unidad_id?: int, estado?: string, buscar?: string} $filtros
     */
    public function listar(array $filtros = []): array
    {
        $q = $this->consulta();

        if (($filtros['categoria'] ?? '') !== '') {
            $q->where('activos.categoria', $filtros['categoria']);
        }

        if (($filtros['unidad_id'] ?? 0) > 0) {
            $q->where('activos.unidad_id', $filtros['unidad_id']);
        }

        if (($filtros['estado'] ?? '') !== '') {
            $q->where('activos.estado', $filtros['estado']);
        } else {
            // Los dados de baja no estorban salvo que se pidan
            $q->where('activos.estado !=', 'baja');
        }

        if (trim((string) ($filtros['buscar'] ?? '')) !== '') {
            $texto = trim((string) $filtros['buscar']);
            $q->groupStart()
                ->like('activos.nombre', $texto)
                ->orLike('activos.codigo', $texto)
                ->orLike('activos.serie', $texto)
                ->orLike('activos.marca', $texto)
                ->orLike('activos.modelo', $texto)
                ->groupEnd();
        }

        return $q->orderBy('activos.categoria')->orderBy('activos.nombre')->findAll();
    }

    /** Los que están en una cabaña, para enseñarlos al reportar una avería. */
    public function deUnidad(int $unidadId): array
    {
        return $this->consulta()
            ->where('activos.unidad_id', $unidadId)
            ->where('activos.estado !=', 'baja')
            ->orderBy('activos.nombre')
            ->findAll();
    }

    /**
     * Garantías que se acaban pronto.
     *
     * Enterarse de que la garantía venció hace un mes, con el equipo ya roto,
     * es de las cosas que más rabia dan y más fáciles son de evitar.
     */
    public function garantiasQueVencen(int $dias = 60): array
    {
        return $this->consulta()
            ->where('activos.estado !=', 'baja')
            ->where('activos.garantia_hasta IS NOT NULL')
            ->where('activos.garantia_hasta >=', date('Y-m-d'))
            ->where('activos.garantia_hasta <=', date('Y-m-d', strtotime('+' . $dias . ' days')))
            ->orderBy('activos.garantia_hasta')
            ->findAll();
    }

    /** ¿Está en garantía hoy? Cambia quién paga la reparación. */
    public function enGarantia(array $activo): bool
    {
        return $activo['garantia_hasta'] !== null && $activo['garantia_hasta'] >= date('Y-m-d');
    }

    /** Cuántos hay de cada estado, para las pastillas del listado. */
    public function conteoPorEstado(): array
    {
        $filas = $this->select('estado, COUNT(*) AS cuantos')
            ->groupBy('estado')
            ->findAll();

        $conteo = array_fill_keys(array_keys(self::ESTADOS), 0);

        foreach ($filas as $f) {
            $conteo[$f['estado']] = (int) $f['cuantos'];
        }

        return $conteo;
    }

    private function consulta()
    {
        return $this->select('activos.*, unidades.nombre AS unidad_nombre, proveedores.nombre AS proveedor_nombre')
            ->join('unidades', 'unidades.id = activos.unidad_id', 'left')
            ->join('proveedores', 'proveedores.id = activos.proveedor_id', 'left');
    }
}
