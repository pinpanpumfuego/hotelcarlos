<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Permisos\Catalogo;
use App\Models\AuditoriaModel;

/**
 * Quién hizo qué.
 *
 * Solo lectura: no hay forma de editar ni de borrar una línea desde aquí. Una
 * auditoría que se puede retocar no sirve para lo único que sirve una
 * auditoría.
 */
class Auditoria extends BaseController
{
    private AuditoriaModel $auditoria;

    public function __construct()
    {
        $this->auditoria = new AuditoriaModel();
    }

    public function index()
    {
        $filtros = [
            'usuario_id' => $this->request->getGet('usuario_id'),
            'permiso'    => $this->request->getGet('permiso'),
            'modulo'     => $this->request->getGet('modulo'),
            'resultado'  => $this->request->getGet('resultado'),
            'referencia' => trim((string) $this->request->getGet('referencia')),
            'desde'      => $this->request->getGet('desde'),
            'hasta'      => $this->request->getGet('hasta'),
        ];

        // Por defecto, el último mes: abrir la pantalla con dos años de golpe
        // no ayuda a nadie y carga la base sin motivo.
        if ($filtros['desde'] === null && $filtros['hasta'] === null && $filtros['referencia'] === '') {
            $filtros['desde'] = date('Y-m-d', strtotime('-30 days'));
        }

        $porPagina = 60;

        return view('auditoria/index', [
            'titulo'     => 'Auditoría',
            'seccion'    => 'auditoria',
            'filtros'    => $filtros,
            'registros'  => $this->auditoria->buscar($filtros)->paginate($porPagina),
            'paginador'  => $this->auditoria->pager,
            'usuarios'   => (new AuditoriaModel())->usuariosConRastro(),
            'modulos'    => Catalogo::MODULOS,
            'sensibles'  => $this->sensiblesPorNombre(),
            'denegados'  => (new AuditoriaModel())->denegadosRecientes(),
        ]);
    }

    /** Todo lo que se hizo sobre una cosa: «reservas:34». */
    public function referencia(string $referencia)
    {
        return view('auditoria/referencia', [
            'titulo'     => 'Historial de ' . $referencia,
            'seccion'    => 'auditoria',
            'referencia' => $referencia,
            'registros'  => $this->auditoria->historialDe($referencia),
            'sensibles'  => $this->sensiblesPorNombre(),
        ]);
    }

    /**
     * clave => nombre legible, solo de los sensibles.
     *
     * @return array<string, string>
     */
    private function sensiblesPorNombre(): array
    {
        $mapa = [];
        foreach (Catalogo::sensibles() as $clave) {
            $mapa[$clave] = Catalogo::PERMISOS[$clave]['nombre'];
        }

        return $mapa;
    }
}
