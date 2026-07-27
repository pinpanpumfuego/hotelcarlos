<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Libraries\Permisos\Catalogo;
use CodeIgniter\Database\Migration;

/**
 * Perfiles de acceso con permisos por acción.
 *
 * Hasta ahora el control era `usuarios.rol` (un ENUM de tres) y un filtro que
 * miraba la ruta entera. Eso no llega para once perfiles con matices como
 * «anulaciones» o «consulta limitada de ocupación»: no es «puede entrar a esta
 * pantalla», es «puede hacer esta acción».
 *
 * **Esta migración no cambia el comportamiento de nada.** La columna `rol`
 * antigua se queda donde está y las 296 rutas siguen usando el filtro `rol:`.
 * Se añade lo nuevo al lado y se traduce a cada usuario su perfil equivalente,
 * para que el cambio de las rutas se pueda hacer después, una por una y con
 * calma. Cambiar las dos cosas a la vez es la forma segura de dejar a alguien
 * fuera de su trabajo un lunes por la mañana.
 */
class RolesPermisos extends Migration
{
    /** Los tres roles de antes y su perfil nuevo. */
    private const EQUIVALENCIA = [
        'gerencia'  => 'gerencia',
        'recepcion' => 'recepcion',
        'limpieza'  => 'housekeeping',
    ];

    public function up()
    {
        $this->tablas();
        $this->sembrarPermisos();
        $this->sembrarRoles();
        $this->enlazarUsuarios();
        $this->topeDescuento();
    }

    private function tablas(): void
    {
        if (! $this->db->tableExists('roles')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'clave'       => ['type' => 'VARCHAR', 'constraint' => 40],
                'nombre'      => ['type' => 'VARCHAR', 'constraint' => 80],
                'descripcion' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                // Un rol de sistema no se puede borrar. Gerencia además no se
                // puede editar: es la salida de emergencia.
                'es_sistema'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('clave');
            $this->forge->createTable('roles');
        }

        if (! $this->db->tableExists('permisos')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'clave'       => ['type' => 'VARCHAR', 'constraint' => 60],
                'modulo'      => ['type' => 'VARCHAR', 'constraint' => 30],
                'nombre'      => ['type' => 'VARCHAR', 'constraint' => 120],
                'es_sensible' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('clave');
            $this->forge->addKey('modulo');
            $this->forge->createTable('permisos');
        }

        if (! $this->db->tableExists('rol_permisos')) {
            $this->forge->addField([
                'rol_id'     => ['type' => 'INT', 'unsigned' => true],
                'permiso_id' => ['type' => 'INT', 'unsigned' => true],
            ]);
            // La clave primaria compuesta es lo que impide conceder dos veces
            // el mismo permiso al mismo perfil.
            $this->forge->addPrimaryKey(['rol_id', 'permiso_id']);
            $this->forge->addForeignKey('rol_id', 'roles', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('permiso_id', 'permisos', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('rol_permisos');
        }

        $columnas = array_column($this->db->getFieldData('usuarios'), 'name');
        if (! in_array('rol_id', $columnas, true)) {
            $this->forge->addColumn('usuarios', [
                'rol_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'rol'],
            ]);
        }
    }

    private function sembrarPermisos(): void
    {
        $tabla      = $this->db->table('permisos');
        $existentes = array_column($tabla->select('clave')->get()->getResultArray(), 'clave');

        $lote = [];
        foreach (Catalogo::PERMISOS as $clave => $datos) {
            if (in_array($clave, $existentes, true)) {
                continue;
            }

            $lote[] = [
                'clave'       => $clave,
                'modulo'      => $datos['modulo'],
                'nombre'      => $datos['nombre'],
                'es_sensible' => $datos['sensible'] ? 1 : 0,
            ];
        }

        if ($lote !== []) {
            $tabla->insertBatch($lote);
        }
    }

    private function sembrarRoles(): void
    {
        $roles    = $this->db->table('roles');
        $permisos = $this->db->table('permisos');

        $mapa = [];
        foreach ($permisos->select('id, clave')->get()->getResultArray() as $p) {
            $mapa[$p['clave']] = (int) $p['id'];
        }

        foreach (Catalogo::PERFILES as $clave => $def) {
            if ($roles->where('clave', $clave)->countAllResults(false) > 0) {
                $roles->resetQuery();

                continue;
            }
            $roles->resetQuery();

            $roles->insert([
                'clave'       => $clave,
                'nombre'      => $def['nombre'],
                'descripcion' => $def['descripcion'],
                'es_sistema'  => $def['sistema'] ? 1 : 0,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $rolId = (int) $this->db->insertID();

            // A gerencia no se le cuelgan permisos: los tiene todos por
            // definición, en código. Así no hay forma de dejarla sin ellos.
            if ($clave === Catalogo::ROL_TOTAL) {
                continue;
            }

            $lote = [];
            foreach (Catalogo::permisosDe($clave) as $permiso) {
                if (isset($mapa[$permiso])) {
                    $lote[] = ['rol_id' => $rolId, 'permiso_id' => $mapa[$permiso]];
                }
            }

            if ($lote !== []) {
                $this->db->table('rol_permisos')->insertBatch($lote);
            }
        }
    }

    /**
     * Da a cada usuario el perfil equivalente al rol que ya tenía.
     *
     * Sin esto, aplicar la migración dejaría a todo el mundo sin perfil.
     */
    private function enlazarUsuarios(): void
    {
        $roles = [];
        foreach ($this->db->table('roles')->select('id, clave')->get()->getResultArray() as $r) {
            $roles[$r['clave']] = (int) $r['id'];
        }

        foreach (self::EQUIVALENCIA as $antiguo => $nuevo) {
            if (! isset($roles[$nuevo])) {
                continue;
            }

            $this->db->table('usuarios')
                ->where('rol', $antiguo)
                ->where('rol_id', null)
                ->update(['rol_id' => $roles[$nuevo]]);
        }
    }

    /** Tope de descuento para quien no tenga `folio.descuento.sintope`. */
    private function topeDescuento(): void
    {
        $config = $this->db->table('configuracion');

        if ($config->where('clave', 'descuento_tope')->countAllResults(false) === 0) {
            $config->resetQuery();
            $config->insert([
                'clave' => 'descuento_tope',
                'valor' => '15',
            ]);
        }

        $config->resetQuery();
    }

    public function down()
    {
        $columnas = array_column($this->db->getFieldData('usuarios'), 'name');
        if (in_array('rol_id', $columnas, true)) {
            $this->forge->dropColumn('usuarios', 'rol_id');
        }

        foreach (['rol_permisos', 'permisos', 'roles'] as $tabla) {
            if ($this->db->tableExists($tabla)) {
                $this->forge->dropTable($tabla);
            }
        }
    }
}
