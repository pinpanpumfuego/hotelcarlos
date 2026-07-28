<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Entrar al panel con correo y PIN de 4 dígitos.
 *
 * **Cuatro dígitos son diez mil combinaciones.** Contra una pantalla abierta a
 * internet donde hay dinero y documentos de identidad, eso no se sostiene solo.
 * Se sostiene con tres cosas a la vez, y esta tabla es la primera:
 *
 * 1. **El equipo tiene que estar reconocido.** La primera vez en cada
 *    dispositivo hay que entrar con la contraseña completa; eso deja aquí una
 *    marca válida un año. Desde un navegador desconocido el PIN no vale ni
 *    aunque sea el correcto, así que adivinarlo desde fuera no lleva a ninguna
 *    parte.
 * 2. **Freno de intentos por persona**, y el PIN se apaga solo si insisten.
 * 3. **Una sesión abierta con PIN no puede hacer lo sensible** sin confirmar la
 *    contraseña. Eso no necesita tabla: vive en la sesión.
 *
 * El token del dispositivo se guarda como resumen SHA-256, no en claro: si
 * alguien se lleva esta tabla, no se lleva ninguna llave. Y es un valor
 * aleatorio de 256 bits, así que no hay nada que adivinar por fuerza bruta.
 */
class AccesoPin extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('dispositivos_confiables')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'usuario_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],

                'token_hash' => ['type' => 'CHAR', 'constraint' => 64],

                // Para que en su perfil se reconozca cuál es cuál y pueda
                // quitar el que ya no usa. Sale del navegador, no es fiable
                // como identificación: es una etiqueta para humanos.
                'nombre'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],

                'ip_alta'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'ultimo_uso' => ['type' => 'DATETIME', 'null' => true],
                'expira'     => ['type' => 'DATE'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('token_hash');
            $this->forge->addKey('usuario_id');
            $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', '', 'CASCADE');
            $this->forge->createTable('dispositivos_confiables');
        }

        $columnas = array_column($this->db->getFieldData('empleados'), 'name');

        // Que un empleado tenga PIN para fichar no significa que deba poder
        // entrar al panel con él. Se decide por persona, y de fábrica está
        // apagado: dar acceso es un acto, no un descuido.
        if (! in_array('pin_panel', $columnas, true)) {
            $this->forge->addColumn('empleados', [
                'pin_panel' => [
                    'type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'pin_actualizado',
                ],
            ]);
        }

        // Se apaga solo tras varias tandas de fallos. Lo vuelve a encender la
        // propia persona entrando con su contraseña.
        if (! in_array('pin_bloqueado', $columnas, true)) {
            $this->forge->addColumn('empleados', [
                'pin_bloqueado' => [
                    'type' => 'DATETIME', 'null' => true, 'after' => 'pin_panel',
                ],
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('dispositivos_confiables', true);

        $columnas = array_column($this->db->getFieldData('empleados'), 'name');

        foreach (['pin_panel', 'pin_bloqueado'] as $columna) {
            if (in_array($columna, $columnas, true)) {
                $this->forge->dropColumn('empleados', $columna);
            }
        }
    }
}
