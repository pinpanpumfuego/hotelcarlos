<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Órdenes de trabajo: materiales, evidencias y verificación.
 *
 * Lo que faltaba para que una orden sirva de algo más que de recordatorio:
 *
 * - **Materiales.** Salen del almacén de verdad, con su movimiento de stock.
 *   Si el repuesto sale del inventario sin apuntarse, el día del conteo falta
 *   y nadie sabe por qué.
 * - **Verificación.** «Resuelta» la marca quien lo arregló, y eso es su
 *   palabra. Cuando la avería comprometía seguridad, hace falta que otro lo
 *   dé por bueno — el mismo criterio que ya se usa en la inspección de
 *   limpieza.
 *
 * Las fotos ya tienen su tabla desde la migración de activos.
 */
class OrdenesTrabajo extends Migration
{
    public function up()
    {
        $this->materiales();
        $this->bodegaDeMantenimiento();
        $this->permisos();
        $this->configuracion();
    }

    private function materiales(): void
    {
        if ($this->db->tableExists('mantenimiento_materiales')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'mantenimiento_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

            // Puede salir del almacén o comprarse en la ferretería del pueblo
            // esa misma mañana. Lo segundo no tiene insumo, pero sí cuesta
            // dinero y tiene que aparecer en el costo de la reparación.
            'insumo_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 150],

            'cantidad'       => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 1],
            'costo_unitario' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'default' => 0],
            'costo_total'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],

            // El apunte de stock que generó, para poder deshacerlo si alguien
            // se equivoca de orden.
            'movimiento_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'bodega_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('mantenimiento_id');
        $this->forge->addForeignKey('mantenimiento_id', 'mantenimientos', 'id', '', 'CASCADE');
        $this->forge->createTable('mantenimiento_materiales');
    }

    /** El almacén de repuestos, si no lo hay. */
    private function bodegaDeMantenimiento(): void
    {
        $existe = $this->db->table('bodegas')->where('tipo', 'mantenimiento')->countAllResults();

        if ($existe > 0) {
            return;
        }

        $this->db->table('bodegas')->insert([
            'nombre'      => 'Taller y repuestos',
            'clave'       => 'taller',
            'tipo'        => 'mantenimiento',
            'por_defecto' => 0,
            'activa'      => 1,
            'notas'       => 'Repuestos, herramienta y consumibles de mantenimiento.',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function permisos(): void
    {
        $nuevos = ['mantenimiento.asignar', 'mantenimiento.verificar', 'mantenimiento.costos'];

        foreach ($nuevos as $clave) {
            $datos = \App\Libraries\Permisos\Catalogo::PERMISOS[$clave] ?? null;

            if ($datos === null) {
                continue;
            }

            $existe = $this->db->table('permisos')->where('clave', $clave)->countAllResults();

            if ($existe === 0) {
                $this->db->table('permisos')->insert([
                    'clave'       => $clave,
                    'modulo'      => $datos['modulo'],
                    'nombre'      => $datos['nombre'],
                    'es_sensible' => $datos['sensible'] ? 1 : 0,
                ]);
            }
        }

        $mapa = [];

        foreach ($this->db->table('permisos')->select('id, clave')->get()->getResultArray() as $p) {
            $mapa[$p['clave']] = (int) $p['id'];
        }

        foreach (['recepcion', 'housekeeping', 'mantenimiento'] as $clave) {
            $rol = $this->db->table('roles')->where('clave', $clave)->get()->getRowArray();

            if ($rol === null) {
                continue;
            }

            foreach (\App\Libraries\Permisos\Catalogo::permisosDe($clave) as $permiso) {
                if (! in_array($permiso, $nuevos, true) || ! isset($mapa[$permiso])) {
                    continue;
                }

                $ya = $this->db->table('rol_permisos')
                    ->where('rol_id', $rol['id'])
                    ->where('permiso_id', $mapa[$permiso])
                    ->countAllResults();

                if ($ya === 0) {
                    $this->db->table('rol_permisos')->insert([
                        'rol_id'     => (int) $rol['id'],
                        'permiso_id' => $mapa[$permiso],
                    ]);
                }
            }
        }
    }

    private function configuracion(): void
    {
        $pares = [
            // Coste de una hora de técnico, para poder comparar reparar contra
            // reemplazar. En 0 el módulo funciona igual: solo deja de sumar la
            // mano de obra al coste, que es mejor que sumar una cifra inventada.
            'mant_costo_hora' => '0',

            // Qué averías exigen que otro las dé por buenas antes de cerrarlas.
            'mant_verifica_urgentes' => '1',
        ];

        foreach ($pares as $clave => $valor) {
            $existe = $this->db->table('configuracion')->where('clave', $clave)->countAllResults();

            if ($existe === 0) {
                $this->db->table('configuracion')->insert(['clave' => $clave, 'valor' => $valor]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('mantenimiento_materiales', true);
    }
}
