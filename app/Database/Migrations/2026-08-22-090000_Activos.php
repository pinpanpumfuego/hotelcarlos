<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Activos y equipos: el inventario de lo que se avería.
 *
 * **La decisión de fondo: no hay tabla nueva de órdenes de trabajo.** La de
 * incidencias que ya existía se amplía hasta serlo. Dos tablas que contestan a
 * «qué hay que arreglar» acaban discrepando, y en cuanto discrepan nadie se
 * fía de ninguna de las dos y todo el mundo vuelve al cuaderno.
 *
 * Lo que sí es nuevo:
 *
 * - `activos`: el calentador, la bomba de la piscina, el extintor. Cada uno con
 *   su código para la etiqueta QR, su serie, su garantía y su proveedor.
 * - `activo_documentos`: la factura, el manual, el certificado del extintor.
 * - `mantenimiento_fotos`: la foto de quien reporta y la de quien lo resolvió.
 *   Sin la segunda, «resuelta» es solo la palabra de alguien.
 *
 * Los ficheros van a `writable/uploads/`, fuera de la carpeta pública: una
 * factura de compra no tiene por qué ser accesible escribiendo la URL.
 */
class Activos extends Migration
{
    public function up()
    {
        $this->activos();
        $this->documentos();
        $this->ampliarIncidencias();
        $this->fotos();
        $this->permisos();
        $this->configuracion();
    }

    /**
     * Los dos permisos nuevos, dados a los perfiles que los necesitan.
     *
     * Sin esto, en una base ya en marcha los permisos existirían en el catálogo
     * pero nadie los tendría, y el menú no le saldría ni al técnico. Solo se
     * tocan los perfiles de sistema tal y como vienen definidos: si alguien
     * ajustó a mano un perfil, no se le pisa lo suyo.
     */
    private function permisos(): void
    {
        $permisos = $this->db->table('permisos');

        foreach (\App\Libraries\Permisos\Catalogo::PERMISOS as $clave => $datos) {
            if (! str_starts_with($clave, 'activos.')) {
                continue;
            }

            $existe = $permisos->where('clave', $clave)->countAllResults(false) > 0;
            $permisos->resetQuery();

            if (! $existe) {
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
                if (! str_starts_with($permiso, 'activos.') || ! isset($mapa[$permiso])) {
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

    private function activos(): void
    {
        if ($this->db->tableExists('activos')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],

            // El código va impreso en la etiqueta y en el QR. Es corto a
            // propósito: si el QR se despega, el técnico puede teclearlo.
            'codigo'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'categoria'   => [
                'type'       => 'ENUM',
                'constraint' => [
                    'cerradura', 'calentador', 'bomba', 'piscina', 'planta_electrica',
                    'extintor', 'cocina', 'frio', 'clima', 'vehiculo', 'mobiliario',
                    'electronica', 'red', 'jardin', 'otro',
                ],
                'default' => 'otro',
            ],

            // Dónde está. Si es una cabaña se enlaza; si es zona común, texto.
            'unidad_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'ubicacion' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],

            'marca'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'modelo' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'serie'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],

            'proveedor_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'fecha_compra'   => ['type' => 'DATE', 'null' => true],
            'valor_compra'   => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'garantia_hasta' => ['type' => 'DATE', 'null' => true],

            // Para decidir si vale la pena repararlo o cambiarlo
            'vida_util_meses' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],

            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['activo', 'averiado', 'reparacion', 'baja'],
                'default'    => 'activo',
            ],

            // Un activo crítico es el que, si falla, deja la cabaña sin vender o
            // pone en riesgo a alguien. Cambia la prioridad por defecto y hace
            // que el sistema proponga bloquear la unidad.
            'critico' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'notas'      => ['type' => 'TEXT', 'null' => true],
            'baja_en'    => ['type' => 'DATE', 'null' => true],
            'baja_motivo' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->addKey(['unidad_id', 'estado']);
        $this->forge->addKey('categoria');
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('proveedor_id', 'proveedores', 'id', '', 'SET NULL');
        $this->forge->createTable('activos');
    }

    private function documentos(): void
    {
        if ($this->db->tableExists('activo_documentos')) {
            return;
        }

        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'activo_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'tipo'      => [
                'type'       => 'ENUM',
                'constraint' => ['factura', 'manual', 'garantia', 'certificado', 'ficha', 'otro'],
                'default'    => 'otro',
            ],
            'archivo'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'nombre_original' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tamano'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'usuario_id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('activo_id');
        $this->forge->addForeignKey('activo_id', 'activos', 'id', '', 'CASCADE');
        $this->forge->createTable('activo_documentos');
    }

    /**
     * La tabla de incidencias se convierte en la de órdenes de trabajo.
     *
     * Todo lo que había se conserva: las incidencias viejas siguen siendo
     * órdenes correctivas de origen interno, que es lo que eran.
     */
    private function ampliarIncidencias(): void
    {
        $columnas = array_column($this->db->getFieldData('mantenimientos'), 'name');

        $nuevas = [];

        if (! in_array('activo_id', $columnas, true)) {
            $nuevas['activo_id'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'unidad_id',
            ];
        }

        if (! in_array('tipo', $columnas, true)) {
            $nuevas['tipo'] = [
                'type' => 'ENUM', 'constraint' => ['correctiva', 'preventiva'],
                'default' => 'correctiva', 'after' => 'titulo',
            ];
        }

        if (! in_array('origen', $columnas, true)) {
            // De dónde salió. Importa para saber si los huéspedes se están
            // encontrando las averías antes que nosotros, que es la señal de
            // que el preventivo no está funcionando.
            $nuevas['origen'] = [
                'type'       => 'ENUM',
                'constraint' => ['recepcion', 'limpieza', 'tecnico', 'huesped', 'preventivo', 'otro'],
                'default'    => 'otro',
                'after'      => 'tipo',
            ];
        }

        if (! in_array('solicitud_id', $columnas, true)) {
            $nuevas['solicitud_id'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'origen',
            ];
        }

        if (! in_array('asignado_a', $columnas, true)) {
            $nuevas['asignado_a'] = [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'reporto_id',
            ];
        }

        if (! in_array('vence_en', $columnas, true)) {
            // El plazo interno. No es una promesa a nadie de fuera: es lo que
            // permite ver de un vistazo qué se está quedando atrás.
            $nuevas['vence_en'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'asignado_a'];
        }

        if (! in_array('iniciada_en', $columnas, true)) {
            $nuevas['iniciada_en'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'vence_en'];
        }

        if (! in_array('minutos', $columnas, true)) {
            $nuevas['minutos'] = ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'resuelta_en'];
        }

        if (! in_array('costo_materiales', $columnas, true)) {
            $nuevas['costo_materiales'] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'minutos'];
        }

        if (! in_array('costo_mano_obra', $columnas, true)) {
            $nuevas['costo_mano_obra'] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'costo_materiales'];
        }

        if (! in_array('bloqueo_unidad', $columnas, true)) {
            $nuevas['bloqueo_unidad'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'costo_mano_obra'];
        }

        if ($nuevas !== []) {
            $this->forge->addColumn('mantenimientos', $nuevas);
        }

        // `resolvió` no basta: hace falta que otro lo dé por bueno cuando la
        // avería comprometía seguridad. Se añaden dos estados más.
        $this->db->query(
            "ALTER TABLE `mantenimientos` MODIFY `estado`
             ENUM('abierta','en_proceso','pausada','resuelta','verificada','anulada')
             NOT NULL DEFAULT 'abierta'"
        );

        // Quien reporta puede no ser un usuario del panel: el huésped no lo es.
        $this->db->query('ALTER TABLE `mantenimientos` MODIFY `reporto_id` INT(10) UNSIGNED NULL');
    }

    private function fotos(): void
    {
        if ($this->db->tableExists('mantenimiento_fotos')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'mantenimiento_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

            // «Antes» es lo que se encontró; «después» es la prueba de que se
            // hizo. Sin la segunda, resuelta es solo la palabra de alguien.
            'momento' => ['type' => 'ENUM', 'constraint' => ['antes', 'despues'], 'default' => 'antes'],

            'archivo'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('mantenimiento_id');
        $this->forge->addForeignKey('mantenimiento_id', 'mantenimientos', 'id', '', 'CASCADE');
        $this->forge->createTable('mantenimiento_fotos');
    }

    private function configuracion(): void
    {
        $db = $this->db;

        // Plazos internos por prioridad, en horas. Se pueden cambiar desde
        // Administración; estos son un punto de partida razonable para un
        // alojamiento de siete cabañas con un técnico.
        $pares = [
            'mant_sla_urgente' => '4',
            'mant_sla_alta'    => '24',
            'mant_sla_media'   => '72',
            'mant_sla_baja'    => '168',
            'mant_prefijo'     => 'SAL',
        ];

        foreach ($pares as $clave => $valor) {
            $existe = $db->table('configuracion')->where('clave', $clave)->countAllResults();

            if ($existe === 0) {
                $db->table('configuracion')->insert([
                    'clave' => $clave,
                    'valor' => $valor,
                ]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('mantenimiento_fotos', true);
        $this->forge->dropTable('activo_documentos', true);
        $this->forge->dropTable('activos', true);
    }
}
