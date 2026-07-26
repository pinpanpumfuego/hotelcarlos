<?php

namespace App\Models;

use CodeIgniter\Model;

/** Catálogo de servicios y equipamiento que se anuncian en la web. */
class ServicioModel extends Model
{
    protected $table         = 'servicios';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'grupo', 'icono', 'orden', 'activo'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre' => 'required|min_length[2]|max_length[80]',
        'grupo'  => 'required|max_length[40]',
    ];

    protected $validationMessages = [
        'nombre' => ['required' => 'El servicio necesita un nombre.'],
        'grupo'  => ['required' => 'Indica a qué grupo pertenece.'],
    ];

    /** Todos los activos, agrupados por su grupo. */
    public function porGrupo(bool $soloActivos = true): array
    {
        $consulta = $this->orderBy('grupo')->orderBy('orden')->orderBy('nombre');
        if ($soloActivos) {
            $consulta->where('activo', 1);
        }

        $agrupados = [];
        foreach ($consulta->findAll() as $s) {
            $agrupados[$s['grupo']][] = $s;
        }

        return $agrupados;
    }

    /** IDs de los servicios marcados en un tipo de alojamiento. */
    public function deTipo(int $tipoId): array
    {
        return array_map('intval', array_column(
            $this->db->table('tipo_servicios')->select('servicio_id')->where('tipo_unidad_id', $tipoId)->get()->getResultArray(),
            'servicio_id'
        ));
    }

    /** Guarda de golpe los servicios de un tipo. */
    public function fijarEnTipo(int $tipoId, array $servicioIds): void
    {
        $this->db->table('tipo_servicios')->where('tipo_unidad_id', $tipoId)->delete();

        foreach (array_unique(array_map('intval', $servicioIds)) as $id) {
            if ($id > 0) {
                $this->db->table('tipo_servicios')->insert(['tipo_unidad_id' => $tipoId, 'servicio_id' => $id]);
            }
        }
    }

    /** Excepciones de una cabaña: [servicio_id => 'si'|'no']. */
    public function excepcionesDeUnidad(int $unidadId): array
    {
        $filas = $this->db->table('unidad_servicios')->where('unidad_id', $unidadId)->get()->getResultArray();

        return array_column($filas, 'estado', 'servicio_id');
    }

    /** Guarda las excepciones de una cabaña; lo que va igual que su tipo no se guarda. */
    public function fijarExcepciones(int $unidadId, array $excepciones): void
    {
        $this->db->table('unidad_servicios')->where('unidad_id', $unidadId)->delete();

        foreach ($excepciones as $servicioId => $estado) {
            if (in_array($estado, ['si', 'no'], true)) {
                $this->db->table('unidad_servicios')->insert([
                    'unidad_id'   => $unidadId,
                    'servicio_id' => (int) $servicioId,
                    'estado'      => $estado,
                ]);
            }
        }
    }

    /**
     * Servicios reales de una cabaña: los de su tipo, más lo que se le añade
     * y menos lo que se le quita.
     */
    public function deUnidad(int $unidadId, int $tipoId): array
    {
        $delTipo     = $this->deTipo($tipoId);
        $excepciones = $this->excepcionesDeUnidad($unidadId);

        foreach ($excepciones as $servicioId => $estado) {
            $servicioId = (int) $servicioId;
            if ($estado === 'si' && ! in_array($servicioId, $delTipo, true)) {
                $delTipo[] = $servicioId;
            } elseif ($estado === 'no') {
                $delTipo = array_values(array_diff($delTipo, [$servicioId]));
            }
        }

        if ($delTipo === []) {
            return [];
        }

        return $this->whereIn('id', $delTipo)->where('activo', 1)
            ->orderBy('grupo')->orderBy('orden')->findAll();
    }

    /** Servicios completos de un tipo, listos para pintar en la web. */
    public function fichaDeTipo(int $tipoId): array
    {
        $ids = $this->deTipo($tipoId);
        if ($ids === []) {
            return [];
        }

        return $this->whereIn('id', $ids)->where('activo', 1)
            ->orderBy('grupo')->orderBy('orden')->findAll();
    }
}
