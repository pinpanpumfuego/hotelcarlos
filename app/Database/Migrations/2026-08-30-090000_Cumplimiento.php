<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cumplimiento: RNT, política de datos y derechos del titular.
 *
 * **Lo que este módulo NO hace, y conviene que quede escrito:** no tramita el
 * RNT, no decide si un huésped es menor, no clasifica impuestos y no sustituye
 * a un abogado. Lo que hace es guardar los datos, avisar de los plazos y dejar
 * la evidencia de que se hizo lo que se dice que se hizo. La responsabilidad
 * sigue siendo del prestador; el sistema solo se encarga de que nadie pueda
 * decir «no me acordaba».
 *
 * Las fechas y los plazos van en configuración y no escritos en el código a
 * propósito: las normas cambian, y una fecha metida en un `if` se descubre
 * tarde y mal.
 */
class Cumplimiento extends Migration
{
    public function up()
    {
        $this->politicas();
        $this->derechos();
        $this->configuracion();
        $this->permisos();
    }

    /**
     * Las versiones de la política de tratamiento de datos.
     *
     * Se versiona porque `registros.version_politica` ya guardaba qué versión
     * aceptó cada huésped, pero no había ninguna tabla que dijera **qué decía
     * esa versión**. Guardar el número sin el texto es guardar una referencia a
     * un documento que nadie puede recuperar: exactamente lo que haría falta
     * enseñar si alguien reclama.
     */
    private function politicas(): void
    {
        if ($this->db->tableExists('politicas')) {
            return;
        }

        $this->forge->addField([
            'id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['datos', 'reglamento', 'cancelacion', 'escnna', 'cookies'],
                'default'    => 'datos',
            ],
            'version' => ['type' => 'VARCHAR', 'constraint' => 20],
            'titulo'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'texto'   => ['type' => 'LONGTEXT'],

            // Desde cuándo rige. Lo aceptado antes se rige por la versión
            // anterior: cambiar la política no cambia lo que alguien firmó.
            'vigente_desde' => ['type' => 'DATE'],
            'publicada'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tipo', 'version']);
        $this->forge->createTable('politicas');
    }

    /**
     * Solicitudes de derechos del titular (Ley 1581/2012).
     *
     * Van aparte de las PQR porque el plazo, el fundamento y lo que hay que
     * entregar son distintos. Una queja por la ducha y una petición de «dígame
     * qué datos míos tiene» se parecen en el formulario y en nada más.
     */
    private function derechos(): void
    {
        if ($this->db->tableExists('solicitudes_datos')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'codigo' => ['type' => 'VARCHAR', 'constraint' => 20],

            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['acceso', 'rectificacion', 'actualizacion', 'supresion', 'revocatoria', 'prueba'],
                'default'    => 'acceso',
            ],

            'huesped_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            // Quien pide puede no estar en la base: hay que poder identificarlo
            // antes de entregarle nada. Entregar datos a quien no es el titular
            // es peor que no entregarlos.
            'nombre'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'documento'     => ['type' => 'VARCHAR', 'constraint' => 50],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'telefono'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'identificado'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'como_identifico' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],

            'detalle' => ['type' => 'TEXT', 'null' => true],

            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['recibida', 'en_tramite', 'atendida', 'rechazada'],
                'default'    => 'recibida',
            ],

            // En días HÁBILES, como manda la ley. Se congela al recibirla.
            'vence_en'    => ['type' => 'DATE', 'null' => true],
            'prorrogada'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'respuesta'     => ['type' => 'TEXT', 'null' => true],
            'atendida_en'   => ['type' => 'DATETIME', 'null' => true],
            'atendio_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'motivo_rechazo' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],

            'origen' => [
                'type'       => 'ENUM',
                'constraint' => ['web', 'email', 'presencial', 'telefono', 'otro'],
                'default'    => 'web',
            ],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->addKey(['estado', 'vence_en']);
        $this->forge->createTable('solicitudes_datos');
    }

    private function configuracion(): void
    {
        $pares = [
            // ── Prestador y RNT ──────────────────────────────────────────
            'legal_razon_social'   => '',
            'legal_nit'            => '',
            'legal_rnt'            => '',
            'legal_rnt_desde'      => '',
            // La renovación del RNT se hace cada año dentro del primer
            // trimestre. La fecha va aquí y no escrita en el código porque el
            // plazo lo fija la norma y la norma cambia: confírmalo cada año.
            'legal_rnt_renovar'    => '03-31',
            'legal_representante'  => '',

            // ── Protección de datos ──────────────────────────────────────
            'legal_responsable_datos' => '',
            'legal_correo_datos'      => '',
            // Plazos de la Ley 1581/2012, en días hábiles. Configurables
            // porque una norma que cambie no puede exigir un despliegue.
            'datos_plazo_consulta'    => '10',
            'datos_plazo_reclamo'     => '15',
            // Cuánto se conserva un dato después de la última estancia. La
            // norma exige conservarlo mientras dure la finalidad, no para
            // siempre: guardar de más también es incumplir.
            'datos_retencion_meses'   => '60',

            // ── Menores ──────────────────────────────────────────────────
            // El protocolo lo decide el hotel. Aquí solo se guarda para que
            // esté a mano en recepción cuando llegue el caso.
            'legal_protocolo_menores' => '',
            'legal_escnna_texto'      => '',

            // ── Copias de seguridad ──────────────────────────────────────
            // El sistema no puede hacerlas solo: dependen del hosting. Lo que
            // sí puede es avisar de que hace mucho que nadie apunta una.
            'seguridad_ultima_copia'  => '',
            'seguridad_aviso_dias'    => '7',
        ];

        foreach ($pares as $clave => $valor) {
            if ($this->db->table('configuracion')->where('clave', $clave)->countAllResults() === 0) {
                $this->db->table('configuracion')->insert(['clave' => $clave, 'valor' => $valor]);
            }
        }
    }

    private function permisos(): void
    {
        $nuevos = ['cumplimiento.ver', 'cumplimiento.gestionar', 'datos.derechos'];

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

        // Recepción ve el panel: es quien atiende a quien llega preguntando.
        $rol = $this->db->table('roles')->where('clave', 'recepcion')->get()->getRowArray();

        if ($rol !== null && isset($mapa['cumplimiento.ver'])) {
            $ya = $this->db->table('rol_permisos')
                ->where('rol_id', $rol['id'])
                ->where('permiso_id', $mapa['cumplimiento.ver'])
                ->countAllResults();

            if ($ya === 0) {
                $this->db->table('rol_permisos')->insert([
                    'rol_id'     => (int) $rol['id'],
                    'permiso_id' => $mapa['cumplimiento.ver'],
                ]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('solicitudes_datos', true);
        $this->forge->dropTable('politicas', true);
    }
}
