<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Plantillas, cola de envíos y automatizaciones.
 *
 * **El canal se guarda desde el principio aunque hoy solo funcione el correo.**
 * WhatsApp exige la API de Meta, cuenta de empresa verificada, plantillas
 * aprobadas una a una y coste por conversación; eso es una decisión del cliente,
 * no una de código. Pero si la columna no estuviera, el día que la tome habría
 * que migrar la cola entera con envíos vivos dentro.
 *
 * Dos decisiones que sostienen el módulo:
 *
 * 1. **Nada se envía en el momento: se encola.** Si el servidor de correo está
 *    caído o lento, encolar no bloquea a la recepcionista que acaba de crear la
 *    reserva. Y lo que falló se reintenta en vez de perderse.
 *
 * 2. **Cada envío guarda para qué finalidad se mandó.** No es burocracia: es lo
 *    que permite contestar «¿por qué recibí esto?» y lo que hace que el enlace
 *    de baja retire exactamente lo que hay que retirar y no todo.
 */
class Comunicaciones extends Migration
{
    public function up()
    {
        $this->plantillas();
        $this->envios();
        $this->automatizaciones();
        $this->sembrar();
        $this->permisos();
    }

    private function plantillas(): void
    {
        if ($this->db->tableExists('plantillas')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],

            // La clave es lo que busca el código; el nombre es para la persona.
            'clave'  => ['type' => 'VARCHAR', 'constraint' => 40],
            'idioma' => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'es'],
            'canal'  => ['type' => 'ENUM', 'constraint' => ['email', 'whatsapp'], 'default' => 'email'],

            'nombre'  => ['type' => 'VARCHAR', 'constraint' => 120],
            'asunto'  => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'cuerpo'  => ['type' => 'TEXT'],

            // Para qué se manda. Decide si hace falta consentimiento y qué
            // retira el enlace de baja.
            'finalidad' => [
                'type'       => 'ENUM',
                'constraint' => ['marketing', 'encuestas', 'operativo'],
                'default'    => 'operativo',
            ],

            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        // Una por clave, idioma y canal: el mismo aviso en español y en inglés
        // son dos plantillas, no una con un `if` dentro.
        $this->forge->addUniqueKey(['clave', 'idioma', 'canal']);
        $this->forge->createTable('plantillas');
    }

    private function envios(): void
    {
        if ($this->db->tableExists('envios')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'huesped_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'reserva_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            'plantilla_clave' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'canal'           => ['type' => 'ENUM', 'constraint' => ['email', 'whatsapp'], 'default' => 'email'],
            'finalidad'       => [
                'type'       => 'ENUM',
                'constraint' => ['marketing', 'encuestas', 'operativo'],
                'default'    => 'operativo',
            ],

            // El texto ya montado. Se guarda tal cual salió y no se vuelve a
            // generar: si mañana se cambia la plantilla, lo que se mandó ayer
            // tiene que seguir siendo lo que se mandó ayer.
            'destino' => ['type' => 'VARCHAR', 'constraint' => 200],
            'asunto'  => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'cuerpo'  => ['type' => 'TEXT'],

            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['pendiente', 'enviado', 'fallido', 'cancelado', 'rebotado'],
                'default'    => 'pendiente',
            ],

            // Cuándo toca mandarlo. Un pre-arrival se encola hoy para dentro de
            // tres días: encolarlo tarde es no mandarlo.
            'programado_para' => ['type' => 'DATETIME'],
            'intentos'        => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'enviado_en'      => ['type' => 'DATETIME', 'null' => true],
            'error'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],

            // Para el enlace de baja y para saber si lo abrió. El token es
            // largo y aleatorio: va en una URL que acaba en el historial del
            // navegador y en los registros de medio internet.
            'token'      => ['type' => 'VARCHAR', 'constraint' => 48, 'null' => true],
            'abierto_en' => ['type' => 'DATETIME', 'null' => true],

            // De qué automatización salió, para no mandar dos veces lo mismo
            'automatizacion_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['estado', 'programado_para']);
        $this->forge->addKey(['reserva_id', 'plantilla_clave']);
        $this->forge->addUniqueKey('token');
        $this->forge->createTable('envios');
    }

    private function automatizaciones(): void
    {
        if ($this->db->tableExists('automatizaciones')) {
            return;
        }

        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 120],

            'evento' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'reserva_confirmada', 'pago_pendiente', 'antes_llegada',
                    'dia_llegada', 'durante', 'dia_salida', 'tras_salida', 'recuperacion',
                ],
            ],

            'plantilla_clave' => ['type' => 'VARCHAR', 'constraint' => 40],
            'canal'           => ['type' => 'ENUM', 'constraint' => ['email', 'whatsapp'], 'default' => 'email'],

            // Cuántos días respecto al evento. Negativo es antes.
            'dias'    => ['type' => 'SMALLINT', 'constraint' => 5, 'default' => 0],
            // A qué hora del día sale. Mandar un correo a las 4 de la mañana
            // parece una máquina, porque lo es.
            'hora'    => ['type' => 'TIME', 'default' => '09:00:00'],

            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['activa', 'evento']);
        $this->forge->createTable('automatizaciones');
    }

    /**
     * Plantillas y automatizaciones de partida.
     *
     * Se siembran apagadas menos las operativas. Encender solas unas campañas
     * de marketing el día que se despliega sería la peor forma posible de
     * estrenar el módulo.
     */
    private function sembrar(): void
    {
        if ($this->db->table('plantillas')->countAllResults() > 0) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');

        $plantillas = [
            [
                'clave' => 'confirmacion', 'finalidad' => 'operativo',
                'nombre' => 'Confirmación de la reserva',
                'asunto' => 'Tu reserva en {{hotel}} está confirmada',
                'cuerpo' => "Hola {{nombre}}:\n\n"
                    . "Tu reserva {{codigo}} está confirmada.\n\n"
                    . "Llegada: {{entrada}}\nSalida: {{salida}}\nAlojamiento: {{cabana}}\n\n"
                    . "Aquí tienes tu espacio para prepararlo todo: {{portal}}\n\n"
                    . "Nos vemos pronto.",
            ],
            [
                'clave' => 'pago_pendiente', 'finalidad' => 'operativo',
                'nombre' => 'Queda un pago pendiente',
                'asunto' => 'Tu reserva {{codigo}} está a un paso',
                'cuerpo' => "Hola {{nombre}}:\n\n"
                    . "Nos falta el pago para dejar tu reserva {{codigo}} asegurada.\n\n"
                    . "Puedes hacerlo aquí: {{pago}}\n\n"
                    . "Si ya lo hiciste, no hagas caso de este mensaje.",
            ],
            [
                'clave' => 'antes_llegada', 'finalidad' => 'operativo',
                'nombre' => 'Unos días antes de llegar',
                'asunto' => 'Te esperamos el {{entrada}}',
                'cuerpo' => "Hola {{nombre}}:\n\n"
                    . "Quedan pocos días para tu llegada. Para que la entrada sea rápida, "
                    . "puedes dejar hechos tus datos aquí: {{registro}}\n\n"
                    . "Si vienes en carro, avísanos la hora aproximada.\n\n"
                    . "Cualquier cosa, escríbenos.",
            ],
            [
                'clave' => 'bienvenida', 'finalidad' => 'operativo',
                'nombre' => 'El día de la llegada',
                'asunto' => 'Bienvenido a {{hotel}}',
                'cuerpo' => "Hola {{nombre}}:\n\n"
                    . "Ya estás en casa. En este enlace tienes la carta, las actividades "
                    . "y todo lo que necesites pedir sin levantarte: {{portal}}",
            ],
            [
                'clave' => 'despedida', 'finalidad' => 'operativo',
                'nombre' => 'El día de la salida',
                'asunto' => 'Gracias por venir',
                'cuerpo' => "Hola {{nombre}}:\n\n"
                    . "Gracias por estos días. Aquí queda tu comprobante: {{portal}}\n\n"
                    . "Ojalá volvamos a verte.",
            ],
            [
                'clave' => 'encuesta', 'finalidad' => 'encuestas',
                'nombre' => 'Encuesta tras la salida',
                'asunto' => '¿Qué tal estuvo, {{nombre}}?',
                'cuerpo' => "Hola {{nombre}}:\n\n"
                    . "Nos ayudaría mucho saber qué tal te fue. Son dos minutos: {{encuesta}}\n\n"
                    . "Gracias.",
            ],
            [
                'clave' => 'recuperacion', 'finalidad' => 'marketing',
                'nombre' => 'Hace tiempo que no vienes',
                'asunto' => 'Los pájaros preguntan por ti',
                'cuerpo' => "Hola {{nombre}}:\n\n"
                    . "Ha pasado un tiempo desde tu última visita. El lago sigue aquí, "
                    . "y las aves también.\n\n"
                    . "Si te apetece volver, escríbenos y te contamos qué hay de nuevo.",
            ],
        ];

        foreach ($plantillas as $p) {
            $this->db->table('plantillas')->insert($p + [
                'idioma' => 'es', 'canal' => 'email', 'activa' => 1,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        $automatizaciones = [
            ['nombre' => 'Confirmación al confirmar', 'evento' => 'reserva_confirmada', 'plantilla_clave' => 'confirmacion', 'dias' => 0, 'hora' => '09:00:00', 'activa' => 1],
            ['nombre' => 'Recordatorio de pago', 'evento' => 'pago_pendiente', 'plantilla_clave' => 'pago_pendiente', 'dias' => 0, 'hora' => '10:00:00', 'activa' => 0],
            ['nombre' => 'Tres días antes', 'evento' => 'antes_llegada', 'plantilla_clave' => 'antes_llegada', 'dias' => -3, 'hora' => '10:00:00', 'activa' => 1],
            ['nombre' => 'Bienvenida', 'evento' => 'dia_llegada', 'plantilla_clave' => 'bienvenida', 'dias' => 0, 'hora' => '14:00:00', 'activa' => 0],
            ['nombre' => 'Despedida', 'evento' => 'dia_salida', 'plantilla_clave' => 'despedida', 'dias' => 0, 'hora' => '08:00:00', 'activa' => 0],
            ['nombre' => 'Encuesta al día siguiente', 'evento' => 'tras_salida', 'plantilla_clave' => 'encuesta', 'dias' => 1, 'hora' => '11:00:00', 'activa' => 1],
            ['nombre' => 'Recuperación a los 12 meses', 'evento' => 'recuperacion', 'plantilla_clave' => 'recuperacion', 'dias' => 365, 'hora' => '10:00:00', 'activa' => 0],
        ];

        foreach ($automatizaciones as $a) {
            $this->db->table('automatizaciones')->insert($a + [
                'canal' => 'email', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    private function permisos(): void
    {
        $nuevos = ['comunicaciones.ver', 'comunicaciones.plantillas', 'comunicaciones.enviar'];

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

        foreach (['recepcion'] as $clave) {
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
        $this->forge->dropTable('envios', true);
        $this->forge->dropTable('automatizaciones', true);
        $this->forge->dropTable('plantillas', true);
    }
}
