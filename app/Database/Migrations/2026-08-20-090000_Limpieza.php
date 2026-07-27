<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Limpieza: los seis estados, tareas con tipo, checklist e inspección.
 *
 * **La decisión de fondo: se separan dos cosas que hoy estaban mezcladas.**
 *
 * `unidades.estado` mezclaba si la cabaña se puede vender («disponible»,
 * «bloqueada») con en qué punto de la limpieza está («limpieza»). Son dos ejes
 * distintos: una cabaña ocupada puede estar sucia, y una limpia puede estar
 * bloqueada por una gotera. Al mezclarlos, cada vez que cambiaba uno se
 * perdía el otro.
 *
 * Ahora `estado` sigue diciendo si se vende y `estado_limpieza` en qué punto
 * está. El estado viejo se conserva tal cual para no romper nada de lo que ya
 * funciona.
 */
class Limpieza extends Migration
{
    public function up()
    {
        $this->estadosDeUnidad();
        $this->ampliarTareas();
        $this->checklist();
        $this->objetosOlvidados();
        $this->configuracion();
    }

    private function estadosDeUnidad(): void
    {
        $columnas = array_column($this->db->getFieldData('unidades'), 'name');

        if (! in_array('estado_limpieza', $columnas, true)) {
            $this->forge->addColumn('unidades', [
                'estado_limpieza' => [
                    'type'       => 'ENUM',
                    'constraint' => ['limpia', 'sucia', 'en_limpieza', 'inspeccionada', 'no_molestar'],
                    'default'    => 'limpia',
                    'after'      => 'estado',
                ],
                // Por qué está bloqueada. «Bloqueada» a secas no dice si es una
                // gotera, una desinfección o que la familia la está usando, y
                // eso cambia por completo cuándo vuelve a estar disponible.
                'motivo_bloqueo' => [
                    'type'       => 'ENUM',
                    'constraint' => ['dano', 'mantenimiento', 'bioseguridad', 'uso_propio', 'otro'],
                    'null'       => true,
                    'after'      => 'estado_limpieza',
                ],
                'nota_bloqueo'   => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true, 'after' => 'motivo_bloqueo'],
                'bloqueada_hasta' => ['type' => 'DATE', 'null' => true, 'after' => 'nota_bloqueo'],
            ]);

            // Lo que estaba en «limpieza» pasa a estar sucio: es lo que
            // significaba de verdad.
            $this->db->query("UPDATE `unidades` SET `estado_limpieza` = 'sucia' WHERE `estado` = 'limpieza'");
        }
    }

    /** Las tareas de limpieza dejan de ser todas iguales. */
    private function ampliarTareas(): void
    {
        $columnas = array_column($this->db->getFieldData('limpiezas'), 'name');

        $nuevas = [];

        if (! in_array('tipo', $columnas, true)) {
            $nuevas['tipo'] = [
                'type'       => 'ENUM',
                'constraint' => ['salida', 'estancia', 'profunda', 'preventiva', 'zona_comun'],
                'default'    => 'salida',
                'after'      => 'unidad_id',
            ];
        }

        if (! in_array('estado', $columnas, true)) {
            $nuevas['estado'] = [
                'type'       => 'ENUM',
                'constraint' => ['pendiente', 'en_curso', 'hecha', 'inspeccionada', 'rechazada'],
                'default'    => 'pendiente',
                'after'      => 'tipo',
            ];
        }

        if (! in_array('prioridad', $columnas, true)) {
            // Una cabaña con llegada hoy va antes que una que no se vende
            // hasta el jueves.
            $nuevas['prioridad'] = ['type' => 'ENUM', 'constraint' => ['normal', 'urgente'], 'default' => 'normal', 'after' => 'estado'];
            $nuevas['para_hoy']  = ['type' => 'DATE', 'null' => true, 'after' => 'prioridad'];
        }

        if (! in_array('inspector_id', $columnas, true)) {
            $nuevas['inspector_id']   = ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'fin'];
            $nuevas['inspeccion_en']  = ['type' => 'DATETIME', 'null' => true, 'after' => 'inspector_id'];
            $nuevas['motivo_rechazo'] = ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true, 'after' => 'inspeccion_en'];
            // Cuántas veces ha habido que rehacerla. Es la medida de reprocesos:
            // sirve para formar, no para castigar.
            $nuevas['reprocesos']     = ['type' => 'INT', 'unsigned' => true, 'default' => 0, 'after' => 'motivo_rechazo'];
        }

        if ($nuevas !== []) {
            $this->forge->addColumn('limpiezas', $nuevas);

            // Las que ya tenían fin estaban hechas
            $this->db->query("UPDATE `limpiezas` SET `estado` = 'hecha' WHERE `fin` IS NOT NULL");
            $this->db->query("UPDATE `limpiezas` SET `estado` = 'en_curso' WHERE `fin` IS NULL");
        }
    }

    /**
     * Checklist configurable por tipo de alojamiento.
     *
     * Por tipo y no por cabaña: si hay siete cabañas iguales, escribir el mismo
     * checklist siete veces es garantizar que acaben siendo distintos.
     */
    private function checklist(): void
    {
        if (! $this->db->tableExists('checklist_puntos')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tipo_unidad_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],

                // Qué tipos de limpieza lo incluyen. Vacío = todas. Una limpieza
                // durante la estancia no repasa lo mismo que una de salida.
                'tipos'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],

                'texto'         => ['type' => 'VARCHAR', 'constraint' => 200],
                'zona'          => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'orden'         => ['type' => 'INT', 'default' => 0],

                // Los puntos críticos exigen foto. Sin ella no se puede dar por
                // hecha la tarea: es lo que convierte «lo hice» en «aquí está».
                'exige_foto'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'activo'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('tipo_unidad_id');
            $this->forge->createTable('checklist_puntos');
        }

        if (! $this->db->tableExists('limpieza_puntos')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'limpieza_id' => ['type' => 'INT', 'unsigned' => true],
                'punto_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],

                // El texto se copia: si mañana cambia el checklist, lo que se
                // hizo ayer tiene que seguir diciendo lo que decía.
                'texto'       => ['type' => 'VARCHAR', 'constraint' => 200],
                'exige_foto'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'hecho'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'foto'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'nota'        => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('limpieza_id');
            $this->forge->addForeignKey('limpieza_id', 'limpiezas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('limpieza_puntos');
        }
    }

    /**
     * Objetos olvidados.
     *
     * Va aparte de las incidencias porque tiene una vida propia: aparece, se
     * guarda, se avisa al huésped y se devuelve o se tira pasado un plazo. Una
     * incidencia se resuelve y se acabó.
     */
    private function objetosOlvidados(): void
    {
        if ($this->db->tableExists('objetos_olvidados')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unidad_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'reserva_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'limpieza_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],

            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 300],
            'foto'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'donde'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],

            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['guardado', 'avisado', 'devuelto', 'desechado'],
                'default'    => 'guardado',
            ],

            'encontrado_por' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'encontrado_el'  => ['type' => 'DATE'],
            'avisado_el'     => ['type' => 'DATE', 'null' => true],
            'cerrado_el'     => ['type' => 'DATE', 'null' => true],
            'notas'          => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['estado', 'encontrado_el']);
        $this->forge->createTable('objetos_olvidados');
    }

    private function configuracion(): void
    {
        $config = $this->db->table('configuracion');

        $porDefecto = [
            // La propia tabla de estados dice «pendiente o no de inspección
            // según política»: tiene que poder apagarse.
            'limpieza_exige_inspeccion' => '1',
            // Días que se guarda un objeto olvidado antes de avisar de que toca
            // decidir qué hacer con él.
            'objetos_dias_guarda'       => '90',
        ];

        foreach ($porDefecto as $clave => $valor) {
            if ($config->where('clave', $clave)->countAllResults(false) === 0) {
                $config->resetQuery();
                $config->insert(['clave' => $clave, 'valor' => $valor]);
            }
            $config->resetQuery();
        }
    }

    public function down()
    {
        foreach (['limpieza_puntos', 'checklist_puntos', 'objetos_olvidados'] as $tabla) {
            if ($this->db->tableExists($tabla)) {
                $this->forge->dropTable($tabla);
            }
        }

        $unidades = array_column($this->db->getFieldData('unidades'), 'name');
        foreach (['estado_limpieza', 'motivo_bloqueo', 'nota_bloqueo', 'bloqueada_hasta'] as $c) {
            if (in_array($c, $unidades, true)) {
                $this->forge->dropColumn('unidades', $c);
            }
        }

        $limpiezas = array_column($this->db->getFieldData('limpiezas'), 'name');
        foreach (['tipo', 'estado', 'prioridad', 'para_hoy', 'inspector_id', 'inspeccion_en', 'motivo_rechazo', 'reprocesos'] as $c) {
            if (in_array($c, $limpiezas, true)) {
                $this->forge->dropColumn('limpiezas', $c);
            }
        }
    }
}
