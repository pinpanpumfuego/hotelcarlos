<?php

namespace App\Commands;

use App\Models\UsuarioModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Crea un usuario del panel preguntando la contraseña por teclado.
 *
 * Existe porque la semilla `UsuarioAdmin` trae una contraseña escrita en el
 * código, y el repositorio es **público**: usarla en el servidor sería
 * publicar la llave de gerencia de un hotel con datos de clientes dentro.
 * Aquí la contraseña la teclea una persona, no aparece en pantalla y no
 * queda en el historial de comandos.
 *
 *   php spark usuario:crear
 */
class CrearUsuario extends BaseCommand
{
    protected $group       = 'Hotel';
    protected $name        = 'usuario:crear';
    protected $description = 'Crea un usuario del panel pidiendo la contraseña por teclado, sin dejarla escrita en ningún sitio.';

    /**
     * Mínimo que exige Javier.
     *
     * El login tiene freno de 5 intentos por minuto y por IP, que es lo que
     * hace viable un mínimo tan corto: probar un millón de combinaciones desde
     * una sola dirección llevaría meses. Aun así, por debajo de 12 se avisa,
     * porque un atacante con muchas direcciones sí puede repartirse el trabajo.
     */
    private const LARGO_MINIMO = 6;

    /** A partir de aquí ya no se avisa. */
    private const LARGO_COMODO = 12;

    public function run(array $params)
    {
        $usuarios = new UsuarioModel();

        CLI::write('Crear usuario del panel', 'yellow');
        CLI::newLine();

        $nombre = trim((string) CLI::prompt('Nombre', null, 'required'));
        $email  = strtolower(trim((string) CLI::prompt('Correo', null, 'required|valid_email')));

        if ($usuarios->where('email', $email)->countAllResults() > 0) {
            CLI::error('Ya existe un usuario con ese correo.');

            return EXIT_ERROR;
        }

        $roles = array_keys(UsuarioModel::ROLES);
        $rol   = CLI::prompt('Rol', $roles, 'required');

        $clave = $this->pedirClave();
        if ($clave === null) {
            return EXIT_ERROR;
        }

        $usuarios->insert([
            'nombre'     => $nombre,
            'email'      => $email,
            'clave_hash' => password_hash($clave, PASSWORD_DEFAULT),
            'rol'        => $rol,
            'activo'     => 1,
        ]);

        CLI::newLine();
        CLI::write('Usuario creado: ' . $email . ' (' . $rol . ')', 'green');
        CLI::write('Entra en ' . rtrim(config('App')->baseURL, '/') . '/login', 'dark_gray');

        return EXIT_SUCCESS;
    }

    /**
     * Pide la contraseña dos veces sin mostrarla.
     *
     * En Linux y macOS se apaga el eco del terminal con `stty`. En Windows no
     * hay equivalente sencillo, así que se avisa de que se verá al escribir:
     * es preferible decirlo a que alguien crea que está oculta y no lo esté.
     */
    private function pedirClave(): ?string
    {
        $puedeOcultar = ! str_starts_with(strtolower(PHP_OS_FAMILY), 'win')
            && function_exists('shell_exec');

        if (! $puedeOcultar) {
            CLI::write('Aviso: en este sistema la contraseña se verá mientras la escribes.', 'yellow');
        }

        $primera = $this->leerOculta('Contraseña', $puedeOcultar);
        if (strlen($primera) < self::LARGO_MINIMO) {
            CLI::error('Demasiado corta: mínimo ' . self::LARGO_MINIMO . ' caracteres.');

            return null;
        }

        $segunda = $this->leerOculta('Repite la contraseña', $puedeOcultar);
        if (! hash_equals($primera, $segunda)) {
            CLI::error('No coinciden.');

            return null;
        }

        // Avisa, pero no impide: la longitud la decide quien monta el hotel
        if (strlen($primera) < self::LARGO_COMODO) {
            CLI::newLine();
            CLI::write('Aviso: es una contraseña corta para una cuenta que da acceso a', 'yellow');
            CLI::write('los datos de los huéspedes. El login frena a 5 intentos por minuto,', 'yellow');
            CLI::write('así que sirve, pero conviene alargarla cuando puedas.', 'yellow');
        }

        return $primera;
    }

    private function leerOculta(string $etiqueta, bool $ocultar): string
    {
        CLI::print($etiqueta . ': ');

        if ($ocultar) {
            shell_exec('stty -echo 2>/dev/null');
        }

        $valor = trim((string) fgets(STDIN));

        if ($ocultar) {
            shell_exec('stty echo 2>/dev/null');
            CLI::newLine();
        }

        return $valor;
    }
}
