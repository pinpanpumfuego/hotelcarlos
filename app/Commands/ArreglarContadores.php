<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Devuelve el contador automático a las tablas que lo perdieron.
 *
 * **Por qué existe esto.** Al montar el servidor, la base de datos se importó
 * de una forma que dejó a docenas de tablas con su columna `id` sin
 * `AUTO_INCREMENT`. Una tabla así no se queja: simplemente escribe un 0 en cada
 * fila nueva. La primera cuela y la segunda choca con
 * `Duplicate entry '0' for key 'PRIMARY'`, que es el error con el que se
 * atascaron las migraciones. Y mientras tanto, cualquier pantalla del hotel que
 * guarde algo se rompe igual, sin que nadie sepa por qué.
 *
 * **Por qué una orden y no un puñado de ALTER a mano.** Son más de cuarenta
 * tablas, y escribirlas a mano es garantizar que una se escriba mal. Además
 * esta usa la conexión del `.env`, así que no hay que teclear contraseñas.
 *
 * **Lo que NO hace, a propósito:**
 *
 * - No toca columnas que no se llamen `id`, ni claves primarias compuestas
 *   (`rol_permisos` y compañía tienen dos columnas de clave y están bien así).
 * - No toca nada que no sea un entero.
 * - No arregla nada si hay filas con `id = 0`, porque reponer el contador les
 *   cambia el número y algo podría estar apuntando a ellas. Avisa y se para.
 *
 * Sin `--arreglar` solo mira y cuenta. Mirar primero es gratis.
 *
 *   php spark db:contadores
 *   php spark db:contadores --arreglar
 */
class ArreglarContadores extends BaseCommand
{
    protected $group       = 'Hotel';
    protected $name        = 'db:contadores';
    protected $description = 'Busca tablas cuyo id perdió el AUTO_INCREMENT y, si se le pide, se lo devuelve.';
    protected $usage       = 'db:contadores [--arreglar] [--renumerar]';
    protected $options     = [
        '--arreglar'  => 'Aplica el arreglo. Sin esto solo informa.',
        '--renumerar' => 'Además, da número a las filas con id 0 de las tablas a las que no apunta nadie.',
    ];

    public function run(array $params)
    {
        $db         = db_connect();
        $arreglar   = array_key_exists('arreglar', $params) || CLI::getOption('arreglar');
        $renumerar  = array_key_exists('renumerar', $params) || CLI::getOption('renumerar');

        $tablas = $db->query(
            "SELECT TABLE_NAME AS tabla, COLUMN_TYPE AS tipo
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND COLUMN_NAME  = 'id'
               AND COLUMN_KEY   = 'PRI'
               AND DATA_TYPE IN ('int', 'bigint', 'smallint', 'mediumint', 'tinyint')
               AND EXTRA NOT LIKE '%auto_increment%'
             ORDER BY TABLE_NAME",
            [$db->getDatabase()]
        )->getResultArray();

        if ($tablas === []) {
            CLI::write('Todas las tablas tienen su contador. No hay nada que arreglar.', 'green');

            return EXIT_SUCCESS;
        }

        CLI::write(count($tablas) . ' tabla(s) sin contador automático en el id:', 'yellow');
        CLI::newLine();

        // Una clave primaria compuesta puede incluir `id` sin ser autoincremental
        // y estar perfectamente bien. Esas se dejan en paz.
        $candidatas = [];
        $compuestas = [];
        $conCeros   = [];

        foreach ($tablas as $t) {
            $columnasPk = (int) $db->query(
                "SELECT COUNT(*) AS n FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = 'PRIMARY'",
                [$db->getDatabase(), $t['tabla']]
            )->getRowArray()['n'];

            if ($columnasPk > 1) {
                $compuestas[] = $t['tabla'];

                continue;
            }

            $ceros = (int) $db->query(
                'SELECT COUNT(*) AS n FROM ' . $db->protectIdentifiers($t['tabla']) . ' WHERE id = 0'
            )->getRowArray()['n'];

            if ($ceros > 0) {
                $conCeros[$t['tabla']] = $ceros;
            }

            $candidatas[] = $t;
        }

        foreach ($candidatas as $t) {
            $aviso = isset($conCeros[$t['tabla']])
                ? CLI::color('  ← ' . $conCeros[$t['tabla']] . ' fila(s) con id 0', 'red')
                : '';

            CLI::write('  · ' . str_pad($t['tabla'], 34) . $t['tipo'] . $aviso);
        }

        if ($compuestas !== []) {
            CLI::newLine();
            CLI::write('Se dejan como están (clave primaria de varias columnas, es correcto): '
                . implode(', ', $compuestas), 'dark_gray');
        }

        if ($conCeros !== [] && ! $renumerar) {
            CLI::newLine();
            CLI::error('Hay filas con id 0. No se toca nada.');
            CLI::write(
                'Reponer el contador les cambiaría el número, y si algo apunta a esas filas se '
                . 'rompería la relación.',
                'yellow'
            );
            CLI::write('Si quieres que les dé número (solo a las tablas a las que no apunte nadie):');
            CLI::write('  php spark db:contadores --arreglar --renumerar');

            return EXIT_ERROR;
        }

        // Darles número es seguro **solo** si ninguna otra tabla apunta a esta
        // por clave foránea. Si alguien apunta, cambiar el 0 dejaría esa
        // referencia colgando y eso no lo arregla nadie después.
        if ($conCeros !== [] && $renumerar) {
            CLI::newLine();

            foreach ($conCeros as $tabla => $cuantas) {
                $apuntan = $db->query(
                    "SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?",
                    [$db->getDatabase(), $tabla]
                )->getResultArray();

                if ($apuntan !== []) {
                    CLI::error(
                        $tabla . ': tiene ' . $cuantas . ' fila(s) con id 0 y le apunta '
                        . implode(', ', array_column($apuntan, 'TABLE_NAME')) . '. No se toca.'
                    );

                    return EXIT_ERROR;
                }

                if (! $arreglar) {
                    CLI::write($tabla . ': ' . $cuantas . ' fila(s) con id 0, y no le apunta nadie. Se le daría número.', 'yellow');

                    continue;
                }

                $siguiente = 1 + (int) $db->query(
                    'SELECT COALESCE(MAX(id), 0) AS n FROM ' . $db->protectIdentifiers($tabla)
                )->getRowArray()['n'];

                $db->query(
                    'UPDATE ' . $db->protectIdentifiers($tabla) . ' SET id = ? WHERE id = 0',
                    [$siguiente]
                );

                CLI::write('  ✓ ' . $tabla . ': la fila con id 0 pasa a ser la ' . $siguiente, 'green');
            }
        }

        if (! $arreglar) {
            CLI::newLine();
            CLI::write('Esto solo ha mirado. Para aplicarlo:', 'yellow');
            CLI::write('  php spark db:contadores --arreglar');
            CLI::newLine();
            CLI::write('Haz antes una copia de la base de datos.', 'yellow');

            return EXIT_SUCCESS;
        }

        CLI::newLine();
        CLI::write('Aplicando…', 'yellow');

        $hechas = 0;
        $fallos = [];

        foreach ($candidatas as $t) {
            // MySQL no sabe deshacer un ALTER, así que van de una en una y se
            // dice cuál falló: si se corta a mitad, se sabe exactamente dónde.
            try {
                $db->query(sprintf(
                    'ALTER TABLE %s MODIFY %s %s NOT NULL AUTO_INCREMENT',
                    $db->protectIdentifiers($t['tabla']),
                    $db->protectIdentifiers('id'),
                    $t['tipo']
                ));

                CLI::write('  ✓ ' . $t['tabla'], 'green');
                $hechas++;
            } catch (Throwable $e) {
                CLI::write('  ✗ ' . $t['tabla'] . ' — ' . $e->getMessage(), 'red');
                $fallos[] = $t['tabla'];
            }
        }

        CLI::newLine();
        CLI::write($hechas . ' tabla(s) arregladas.', 'green');

        if ($fallos !== []) {
            CLI::error('No se pudo con: ' . implode(', ', $fallos));

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
