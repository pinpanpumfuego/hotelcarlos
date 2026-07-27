<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CRM: perfil único, preferencias y consentimientos.
 *
 * **Lo primero es lo legal, y no por prudencia sino porque todo lo demás cuelga
 * de ahí.** No se puede mandar una campaña sin saber quién autorizó qué, para
 * qué y por qué canal. Lo que había —un `acepta_marketing` sí/no por reserva—
 * no vale para la Ley 1581/2012: no distingue finalidad, ni canal, ni guarda
 * prueba de cuándo se dio, ni permite retirarlo.
 *
 * Tres decisiones de fondo:
 *
 * 1. **`consentimientos` es append-only.** Una retirada NO actualiza la fila:
 *    escribe otra. Lo que valdría ante una queja no es el estado de hoy, es
 *    poder demostrar qué autorizó esa persona el 3 de marzo y cuándo lo quitó.
 *    Un UPDATE borra justo la prueba que hace falta.
 *
 * 2. **Las alergias van en su propia tabla, no en `notas`.** Son dato de salud
 *    y por tanto sensible: exigen autorización expresa y no las puede ver
 *    cualquiera. En el campo de notas las lee todo el mundo.
 *
 * 3. **Los duplicados se fusionan, no se borran.** El perdedor se queda
 *    apuntando al ganador. Borrarlo dejaría reservas huérfanas y, sobre todo,
 *    haría imposible explicar por qué desapareció alguien de la base.
 */
class Crm extends Migration
{
    public function up()
    {
        $this->ampliarHuespedes();
        $this->preferencias();
        $this->consentimientos();
        $this->migrarConsentimientosViejos();
        $this->permisos();
    }

    private function ampliarHuespedes(): void
    {
        $columnas = array_column($this->db->getFieldData('huespedes'), 'name');
        $nuevas   = [];

        $anadir = static function (string $nombre, array $def) use (&$nuevas, $columnas): void {
            if (! in_array($nombre, $columnas, true)) {
                $nuevas[$nombre] = $def;
            }
        };

        $anadir('fecha_nacimiento', ['type' => 'DATE', 'null' => true, 'after' => 'nacionalidad']);
        $anadir('idioma', ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'es', 'after' => 'fecha_nacimiento']);
        $anadir('ciudad', ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'idioma']);
        $anadir('pais', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'ciudad']);

        // Un mismo perfil puede venir por su cuenta o de parte de una empresa.
        // Interesa para facturar y para segmentar: quien viene de empresa
        // vuelve por motivos distintos que quien viene de vacaciones.
        $anadir('empresa', ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'pais']);
        $anadir('empresa_nit', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'empresa']);

        // Cómo nos conoció. Es lo único que dice si la inversión en un canal
        // sirve de algo, y no se puede deducir de ningún otro dato.
        $anadir('origen', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'empresa_nit']);

        $anadir('vip', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'origen']);

        // Notas que el huésped nunca debería leer si algún día pide sus datos.
        // Separadas de `notas` a propósito: ese campo acaba imprimiéndose.
        $anadir('notas_internas', ['type' => 'TEXT', 'null' => true, 'after' => 'notas']);

        $anadir('estado', [
            'type' => 'ENUM', 'constraint' => ['activo', 'fusionado', 'anonimizado'],
            'default' => 'activo', 'after' => 'notas_internas',
        ]);

        // A qué perfil se absorbió este. No se borra nunca: borrarlo dejaría
        // reservas huérfanas y haría imposible explicar la desaparición.
        $anadir('fusionado_en', ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'estado']);
        $anadir('anonimizado_en', ['type' => 'DATETIME', 'null' => true, 'after' => 'fusionado_en']);

        if ($nuevas !== []) {
            $this->forge->addColumn('huespedes', $nuevas);
        }

        // Buscar por email y teléfono es lo que hace el buscador de duplicados
        $this->indice('huespedes', 'idx_huespedes_email', 'email');
        $this->indice('huespedes', 'idx_huespedes_telefono', 'telefono');
    }

    private function preferencias(): void
    {
        if ($this->db->tableExists('huesped_preferencias')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'huesped_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['alergia', 'dieta', 'accesibilidad', 'habitacion', 'interes', 'celebracion', 'otro'],
                'default'    => 'otro',
            ],

            'valor' => ['type' => 'VARCHAR', 'constraint' => 150],
            'nota'  => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],

            // Quién lo dijo. Lo que contó el huésped y lo que dedujo recepción
            // no valen lo mismo: lo segundo se le pregunta antes de darlo por
            // bueno, y sobre todo antes de enseñárselo.
            'origen' => [
                'type'       => 'ENUM',
                'constraint' => ['huesped', 'recepcion', 'restaurante', 'sistema'],
                'default'    => 'recepcion',
            ],

            // Lo que hay que ver sí o sí antes de servirle un plato
            'critica' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['huesped_id', 'tipo']);
        $this->forge->addForeignKey('huesped_id', 'huespedes', 'id', '', 'CASCADE');
        $this->forge->createTable('huesped_preferencias');
    }

    private function consentimientos(): void
    {
        if ($this->db->tableExists('consentimientos')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'huesped_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

            // PARA QUÉ. «Acepta que le escribamos» no es un consentimiento
            // válido: hay que decir para qué.
            'finalidad' => [
                'type'       => 'ENUM',
                'constraint' => ['marketing', 'encuestas', 'operativo', 'fotos', 'perfilado', 'terceros'],
            ],

            // POR DÓNDE. Autorizar el correo no autoriza el WhatsApp.
            'canal' => [
                'type'       => 'ENUM',
                'constraint' => ['email', 'whatsapp', 'sms', 'llamada', 'cualquiera'],
                'default'    => 'email',
            ],

            // `true` es dar, `false` es retirar. La tabla NO se actualiza
            // nunca: retirar escribe otra fila. Lo que vale ante una queja es
            // poder demostrar qué autorizó esa persona y cuándo lo quitó, y un
            // UPDATE borra justo esa prueba.
            'otorgado' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],

            'origen' => [
                'type'       => 'ENUM',
                'constraint' => ['registro', 'portal', 'recepcion', 'web', 'importacion', 'baja_email'],
                'default'    => 'recepcion',
            ],

            // La prueba. Sin esto la autorización es la palabra del hotel.
            'version_politica' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'ip'               => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'dispositivo'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nota'             => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],

            'reserva_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['huesped_id', 'finalidad', 'canal', 'created_at']);
        $this->forge->addForeignKey('huesped_id', 'huespedes', 'id', '', 'CASCADE');
        $this->forge->createTable('consentimientos');
    }

    /**
     * Rescata lo que se autorizó hasta hoy.
     *
     * `registros.acepta_marketing` es lo único que hay, y viene por reserva.
     * Se pasa al huésped como consentimiento de marketing por email, con
     * origen «registro» y la fecha de la firma: es exactamente lo que pasó, y
     * tirarlo obligaría a volver a pedírselo a gente que ya dijo que sí.
     */
    private function migrarConsentimientosViejos(): void
    {
        if ($this->db->table('consentimientos')->countAllResults() > 0) {
            return;
        }

        $filas = $this->db->query(
            "SELECT r.reserva_id, r.acepta_marketing, r.version_politica, r.firma_ip,
                    r.firmado_en, r.created_at, res.huesped_id
             FROM registros r
             JOIN reservas res ON res.id = r.reserva_id
             WHERE r.acepta_marketing = 1 AND res.huesped_id IS NOT NULL
             ORDER BY r.id"
        )->getResultArray();

        foreach ($filas as $f) {
            $cuando = $f['firmado_en'] ?? $f['created_at'] ?? date('Y-m-d H:i:s');

            $this->db->table('consentimientos')->insert([
                'huesped_id'       => (int) $f['huesped_id'],
                'finalidad'        => 'marketing',
                'canal'            => 'email',
                'otorgado'         => 1,
                'origen'           => 'registro',
                'version_politica' => $f['version_politica'],
                'ip'               => $f['firma_ip'],
                'nota'             => 'Rescatado del registro de llegada al montar el CRM.',
                'reserva_id'       => (int) $f['reserva_id'],
                'created_at'       => $cuando,
                'updated_at'       => $cuando,
            ]);
        }
    }

    private function permisos(): void
    {
        $nuevos = ['huespedes.sensibles', 'huespedes.fusionar', 'consentimientos.gestionar'];

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

        foreach (['recepcion', 'restaurante'] as $clave) {
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

    /** MySQL no tiene `CREATE INDEX IF NOT EXISTS`: hay que mirar antes. */
    private function indice(string $tabla, string $nombre, string $columna): void
    {
        $existe = $this->db->query(
            'SHOW INDEX FROM `' . $tabla . '` WHERE Key_name = ?',
            [$nombre]
        )->getNumRows();

        if ($existe === 0) {
            $this->db->query('CREATE INDEX `' . $nombre . '` ON `' . $tabla . '` (`' . $columna . '`)');
        }
    }

    public function down()
    {
        $this->forge->dropTable('consentimientos', true);
        $this->forge->dropTable('huesped_preferencias', true);
    }
}
