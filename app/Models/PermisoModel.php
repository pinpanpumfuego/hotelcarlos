<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Permisos\Catalogo;
use CodeIgniter\Model;

/**
 * Los permisos que existen.
 *
 * La tabla es un reflejo del catálogo de código, no una fuente aparte: sirve
 * para poder unirla con `rol_permisos` en una consulta. Quien manda es
 * `Catalogo`, y `sincronizar()` es lo que mantiene la tabla al día.
 */
class PermisoModel extends Model
{
    protected $table         = 'permisos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['clave', 'modulo', 'nombre', 'es_sensible'];
    protected $useTimestamps = false;

    /**
     * Pone la tabla al día con el catálogo.
     *
     * Se ejecuta en cada migración y desde el panel. Añade lo nuevo y actualiza
     * los textos, pero **no borra** lo que ya no está en el catálogo: si un
     * permiso desaparece del código, borrar su fila arrastraría en cascada las
     * de `rol_permisos`, y si mañana vuelve —porque se renombró mal, por
     * ejemplo— los perfiles se habrían quedado vacíos por el camino. Filtrar al
     * leer (`RolModel::permisos()`) consigue lo mismo sin perder nada.
     *
     * @return array{nuevos: int, actualizados: int}
     */
    public function sincronizar(): array
    {
        $existentes = [];
        foreach ($this->findAll() as $fila) {
            $existentes[$fila['clave']] = $fila;
        }

        $nuevos = 0;
        $tocados = 0;

        foreach (Catalogo::PERMISOS as $clave => $datos) {
            $valores = [
                'clave'       => $clave,
                'modulo'      => $datos['modulo'],
                'nombre'      => $datos['nombre'],
                'es_sensible' => $datos['sensible'] ? 1 : 0,
            ];

            if (! isset($existentes[$clave])) {
                $this->insert($valores);
                $nuevos++;

                continue;
            }

            $antes = $existentes[$clave];
            if ($antes['nombre'] !== $valores['nombre']
                || $antes['modulo'] !== $valores['modulo']
                || (int) $antes['es_sensible'] !== $valores['es_sensible']) {
                $this->update($antes['id'], $valores);
                $tocados++;
            }
        }

        return ['nuevos' => $nuevos, 'actualizados' => $tocados];
    }

    /** @return array<string, int> clave => id */
    public function mapaClaveId(): array
    {
        $mapa = [];
        foreach ($this->select('id, clave')->findAll() as $fila) {
            $mapa[$fila['clave']] = (int) $fila['id'];
        }

        return $mapa;
    }

    /**
     * Permisos que están en la tabla pero ya no en el código.
     *
     * Sale en el panel como aviso. No hacen daño —al leer se filtran— pero
     * indican que alguien renombró un permiso y olvidó migrar los perfiles.
     *
     * @return list<string>
     */
    public function huerfanos(): array
    {
        return array_values(array_filter(
            array_column($this->select('clave')->findAll(), 'clave'),
            static fn (string $c): bool => ! Catalogo::existe($c)
        ));
    }
}
