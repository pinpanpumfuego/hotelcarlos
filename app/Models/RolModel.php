<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Permisos\Catalogo;
use CodeIgniter\Model;

/**
 * Perfiles de acceso.
 *
 * Los perfiles son **datos**: gerencia puede crear «Recepción de fin de semana»
 * desde el panel sin que nadie toque código. Los permisos que se les cuelgan sí
 * son código, porque cada uno corresponde a una acción que existe de verdad.
 */
class RolModel extends Model
{
    protected $table         = 'roles';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['clave', 'nombre', 'descripcion', 'es_sistema'];
    protected $useTimestamps = true;

    public function porClave(string $clave): ?array
    {
        return $this->where('clave', $clave)->first();
    }

    /**
     * Claves de permiso de un rol.
     *
     * Se filtran contra el catálogo: si un permiso se retiró del código pero
     * quedó la fila en la tabla, no debe seguir concediendo nada.
     *
     * @return list<string>
     */
    public function permisos(int $rolId): array
    {
        $filas = $this->db->table('rol_permisos rp')
            ->select('p.clave')
            ->join('permisos p', 'p.id = rp.permiso_id')
            ->where('rp.rol_id', $rolId)
            ->get()
            ->getResultArray();

        return array_values(array_filter(
            array_column($filas, 'clave'),
            static fn (string $c): bool => Catalogo::existe($c)
        ));
    }

    /**
     * Sustituye los permisos de un rol por la lista dada.
     *
     * Borrar y volver a insertar, en vez de ir comparando: es la única forma de
     * que un permiso desmarcado desaparezca de verdad. Va en transacción porque
     * un fallo a mitad dejaría el perfil sin permisos, sin que nadie se entere
     * hasta que alguien no pueda trabajar.
     *
     * @param list<string> $claves
     */
    public function fijarPermisos(int $rolId, array $claves): bool
    {
        $rol = $this->find($rolId);

        // Gerencia no se toca. Sin esta salvaguarda, un despiste en la pantalla
        // de perfiles deja el sistema sin nadie que pueda arreglarlo.
        if ($rol === null || $rol['clave'] === Catalogo::ROL_TOTAL) {
            return false;
        }

        $validas = array_values(array_filter($claves, static fn ($c): bool => Catalogo::existe((string) $c)));

        $this->db->transStart();

        $this->db->table('rol_permisos')->where('rol_id', $rolId)->delete();

        if ($validas !== []) {
            $ids = $this->db->table('permisos')
                ->select('id')
                ->whereIn('clave', $validas)
                ->get()
                ->getResultArray();

            $lote = array_map(
                static fn (array $p): array => ['rol_id' => $rolId, 'permiso_id' => (int) $p['id']],
                $ids
            );

            if ($lote !== []) {
                $this->db->table('rol_permisos')->insertBatch($lote);
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /** Roles con cuántos usuarios y cuántos permisos tiene cada uno. */
    public function listado(): array
    {
        $roles = $this->orderBy('es_sistema', 'DESC')->orderBy('nombre')->findAll();

        foreach ($roles as &$rol) {
            $rol['usuarios'] = $this->db->table('usuarios')
                ->where('rol_id', $rol['id'])
                ->countAllResults();

            $rol['permisos'] = $rol['clave'] === Catalogo::ROL_TOTAL
                ? count(Catalogo::claves())
                : count($this->permisos((int) $rol['id']));
        }

        return $roles;
    }

    /**
     * ¿Se puede borrar este perfil?
     *
     * No, si es de sistema o si hay gente usándolo: dejar usuarios sin perfil
     * es dejarlos sin poder trabajar, y el error aparecería mañana por la
     * mañana, no ahora.
     */
    public function sePuedeEliminar(int $rolId): bool
    {
        $rol = $this->find($rolId);

        if ($rol === null || (int) $rol['es_sistema'] === 1) {
            return false;
        }

        return $this->db->table('usuarios')->where('rol_id', $rolId)->countAllResults() === 0;
    }
}
