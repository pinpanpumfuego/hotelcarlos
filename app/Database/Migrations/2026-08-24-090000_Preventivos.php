<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mantenimiento preventivo y medidores.
 *
 * Hasta aquí el módulo solo sabía reaccionar: algo se rompe, alguien lo
 * reporta, alguien lo arregla. Esto es lo que evita que se rompa.
 *
 * **La tabla se llama `planes_mantenimiento` y no `planes`** porque `planes` ya
 * existe y son los planes de comidas. Dos cosas distintas con el mismo nombre
 * es de lo que más caro se paga meses después.
 *
 * Decisión de fondo en los medidores: se guarda **la lectura, no el consumo**.
 * El consumo se calcula restando la anterior. Si se guardara el consumo, un
 * error de tecleo se arrastraría para siempre sin forma de detectarlo; con la
 * lectura, el número está en el contador y se puede ir a mirar.
 */
class Preventivos extends Migration
{
    public function up()
    {
        $this->planes();
        $this->enlazarOrdenes();
        $this->medidores();
        $this->lecturas();
        $this->permisos();
    }

    private function planes(): void
    {
        if ($this->db->tableExists('planes_mantenimiento')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 150],

            // Un plan apunta a un equipo concreto («la bomba de la piscina») o a
            // toda una categoría («todos los extintores»). Lo segundo evita
            // tener que acordarse de crear el plan cada vez que se compra uno.
            'activo_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'categoria' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],

            'cada'    => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 1],
            'periodo' => ['type' => 'ENUM', 'constraint' => ['dias', 'meses'], 'default' => 'meses'],

            // Cuándo toca la próxima. Es el corazón del módulo: la orden se abre
            // sola unos días antes para que dé tiempo a organizarse.
            'proxima_fecha' => ['type' => 'DATE'],
            'aviso_dias'    => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 7],

            'prioridad'    => ['type' => 'ENUM', 'constraint' => ['baja', 'media', 'alta', 'urgente'], 'default' => 'media'],
            'asignado_a'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'duracion_min' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],

            // Lo que hay que hacer, paso a paso. Va en la orden que se genera:
            // quien la atienda seis meses después no tiene por qué acordarse.
            'instrucciones' => ['type' => 'TEXT', 'null' => true],

            // Lo que exige la ley colombiana (extintores, piscinas, plantas).
            // No se puede desactivar sin dejar constancia de por qué.
            'obligatorio' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'activo'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'ultima_fecha' => ['type' => 'DATE', 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['activo', 'proxima_fecha']);
        $this->forge->addForeignKey('activo_id', 'activos', 'id', '', 'CASCADE');
        $this->forge->createTable('planes_mantenimiento');
    }

    private function enlazarOrdenes(): void
    {
        $columnas = array_column($this->db->getFieldData('mantenimientos'), 'name');

        if (in_array('plan_id', $columnas, true)) {
            return;
        }

        $this->forge->addColumn('mantenimientos', [
            // De qué plan salió. Sirve para no abrir la revisión de octubre si
            // la de septiembre sigue sin hacerse: apilar órdenes idénticas es
            // la forma más rápida de que nadie mire ninguna.
            'plan_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'solicitud_id',
            ],
        ]);
    }

    private function medidores(): void
    {
        if ($this->db->tableExists('medidores')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 120],
            'tipo'   => [
                'type'       => 'ENUM',
                'constraint' => ['agua', 'energia', 'gas', 'combustible', 'horas', 'otro'],
                'default'    => 'agua',
            ],
            'unidad_medida' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'm³'],

            'unidad_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'activo_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'ubicacion' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],

            // Un contador de agua siempre sube; un tanque de gasolina se mide y
            // baja. El consumo se calcula distinto en cada caso.
            'acumulativo' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            // Consumo diario a partir del cual algo va mal. Una fuga en una
            // tubería enterrada no se ve: solo se nota en el contador.
            'alerta_diaria' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'null' => true],

            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'notas'      => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('unidad_id', 'unidades', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('activo_id', 'activos', 'id', '', 'SET NULL');
        $this->forge->createTable('medidores');
    }

    private function lecturas(): void
    {
        if ($this->db->tableExists('lecturas_medidor')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'medidor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'fecha'      => ['type' => 'DATE'],

            // Lo que marca el contador. El consumo NO se guarda como dato de
            // entrada: se calcula restando la lectura anterior, y así un error
            // de tecleo se ve al mirar el contador en vez de arrastrarse.
            'lectura' => ['type' => 'DECIMAL', 'constraint' => '14,3'],
            'consumo' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'null' => true],
            'dias'    => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],

            // Una lectura que baja en un contador acumulativo es una de tres:
            // se tecleó mal, el contador dio la vuelta, o lo cambiaron. Las
            // tres hay que mirarlas, así que se marca en vez de calcular un
            // consumo negativo que ensuciaría todos los informes.
            'sospechosa' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'nota'       => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        // Una lectura por medidor y día: dos del mismo día darían un consumo
        // partido en dos trozos sin sentido.
        $this->forge->addUniqueKey(['medidor_id', 'fecha']);
        $this->forge->addForeignKey('medidor_id', 'medidores', 'id', '', 'CASCADE');
        $this->forge->createTable('lecturas_medidor');
    }

    private function permisos(): void
    {
        $nuevos = ['mantenimiento.planes', 'medidores.ver', 'medidores.leer'];

        foreach ($nuevos as $clave) {
            $datos = \App\Libraries\Permisos\Catalogo::PERMISOS[$clave] ?? null;

            if ($datos === null) {
                continue;
            }

            if ($this->db->table('permisos')->where('clave', $clave)->countAllResults() === 0) {
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

    public function down()
    {
        $this->forge->dropTable('lecturas_medidor', true);
        $this->forge->dropTable('medidores', true);
        $this->forge->dropTable('planes_mantenimiento', true);
    }
}
