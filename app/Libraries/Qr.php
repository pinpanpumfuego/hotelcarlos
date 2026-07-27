<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Códigos QR, escritos aquí a propósito.
 *
 * La alternativa era una librería de terceros, y eso obliga a correr
 * `composer install` en el servidor en cada despliegue. El día que eso falle,
 * te quedas sin poder imprimir etiquetas. Esto son cuatrocientas líneas que no
 * se van a mover nunca: el formato QR está congelado desde el año 2000.
 *
 * Alcance a propósito estrecho: **modo byte, corrección M, versiones 1 a 10**.
 * Da para 213 caracteres, y las direcciones que metemos en las etiquetas no
 * llegan a 60. La corrección M aguanta un 15 % de la etiqueta borrada, que es
 * lo que hace falta cuando lleva año y medio pegada a un calentador.
 *
 * Lo que sale es SVG: se imprime nítido a cualquier tamaño y no pesa nada.
 *
 * @see tests/unit/QrTest.php — ahí hay un **lector** escrito aparte que vuelve
 *      a sacar el texto de la matriz. Si lo que genera esto no se puede leer,
 *      las pruebas fallan. Es la única forma honesta de comprobarlo sin tener
 *      un móvil delante.
 */
final class Qr
{
    /** Cuántos caracteres caben en cada versión (modo byte, corrección M). */
    private const CAPACIDAD = [
        1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84,
        6 => 106, 7 => 122, 8 => 152, 9 => 180, 10 => 213,
    ];

    /**
     * Por versión: [codewords de corrección por bloque, bloques del grupo 1,
     * datos por bloque del grupo 1, bloques del grupo 2, datos por bloque del
     * grupo 2].
     */
    private const BLOQUES = [
        1  => [10, 1, 16, 0, 0],
        2  => [16, 1, 28, 0, 0],
        3  => [26, 1, 44, 0, 0],
        4  => [18, 2, 32, 0, 0],
        5  => [24, 2, 43, 0, 0],
        6  => [16, 4, 27, 0, 0],
        7  => [18, 4, 31, 0, 0],
        8  => [22, 2, 38, 2, 39],
        9  => [22, 3, 36, 2, 37],
        10 => [26, 4, 43, 1, 44],
    ];

    /** Centros de los patrones de alineación de cada versión. */
    private const ALINEACION = [
        1  => [],
        2  => [6, 18],
        3  => [6, 22],
        4  => [6, 26],
        5  => [6, 30],
        6  => [6, 34],
        7  => [6, 22, 38],
        8  => [6, 24, 42],
        9  => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /** @var list<int> Antilogaritmos de GF(256). */
    private static array $exp = [];

    /** @var list<int> Logaritmos de GF(256). */
    private static array $log = [];

    // ── Lo que se usa desde fuera ───────────────────────────────────────

    /**
     * El código como SVG, listo para meter en una página.
     *
     * @param int $modulo Píxeles por módulo (el cuadradito).
     * @param int $margen Módulos de zona tranquila. Cuatro es lo que manda la
     *                    norma; con menos, muchos lectores no enganchan.
     */
    public static function svg(string $texto, int $modulo = 4, int $margen = 4): string
    {
        $matriz = self::matriz($texto);
        $lado   = count($matriz);
        $total  = ($lado + $margen * 2) * $modulo;

        $caminos = '';

        foreach ($matriz as $y => $fila) {
            foreach ($fila as $x => $oscuro) {
                if ($oscuro) {
                    $caminos .= sprintf(
                        'M%d %dh%dv%dh-%dz',
                        ($x + $margen) * $modulo,
                        ($y + $margen) * $modulo,
                        $modulo,
                        $modulo,
                        $modulo
                    );
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" '
            . 'shape-rendering="crispEdges" role="img" aria-label="Código QR">'
            . '<rect width="%d" height="%d" fill="#fff"/>'
            . '<path d="%s" fill="#000"/></svg>',
            $total,
            $total,
            $total,
            $total,
            $total,
            $total,
            $caminos
        );
    }

    /**
     * La matriz de módulos: `true` es oscuro.
     *
     * @return list<list<bool>>
     */
    public static function matriz(string $texto): array
    {
        if ($texto === '') {
            throw new \InvalidArgumentException('No hay nada que codificar.');
        }

        $version = self::versionQueCabe($texto);
        $lado    = 17 + $version * 4;

        $codewords = self::codewords($texto, $version);

        // Se pintan primero los patrones fijos, marcando en `reservado` lo que
        // los datos no pueden pisar.
        [$matriz, $reservado] = self::patrones($version, $lado);

        self::colocarDatos($matriz, $reservado, $codewords, $lado);

        // De las ocho máscaras se elige la que menos penaliza. No es cosmético:
        // una máscara mala deja zonas grandes del mismo color y el lector se
        // pierde buscando dónde empieza cada módulo.
        $mejor    = 0;
        $menor    = PHP_INT_MAX;
        $opciones = [];

        for ($m = 0; $m < 8; $m++) {
            $prueba = self::enmascarar($matriz, $reservado, $lado, $m);
            self::formato($prueba, $lado, $m);
            $opciones[$m] = $prueba;

            $castigo = self::castigo($prueba, $lado);
            if ($castigo < $menor) {
                $menor = $castigo;
                $mejor = $m;
            }
        }

        return $opciones[$mejor];
    }

    // ── De texto a codewords ────────────────────────────────────────────

    private static function versionQueCabe(string $texto): int
    {
        $largo = strlen($texto);

        foreach (self::CAPACIDAD as $version => $cabe) {
            if ($largo <= $cabe) {
                return $version;
            }
        }

        throw new \InvalidArgumentException(
            'El texto no cabe en un QR de este tipo: ' . $largo . ' caracteres, el tope son '
            . self::CAPACIDAD[10] . '.'
        );
    }

    /**
     * Bits de datos + relleno + corrección de errores, ya entrelazados.
     *
     * @return list<int>
     */
    private static function codewords(string $texto, int $version): array
    {
        [$ecPorBloque, $bloques1, $datos1, $bloques2, $datos2] = self::BLOQUES[$version];

        $totalDatos = $bloques1 * $datos1 + $bloques2 * $datos2;

        // Cabecera: 0100 = modo byte. La longitud ocupa 8 bits hasta la v9 y 16
        // desde la v10; equivocarse aquí produce un código que nadie lee.
        $bits = '0100';
        $bits .= str_pad(decbin(strlen($texto)), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT);

        foreach (str_split($texto) as $letra) {
            $bits .= str_pad(decbin(ord($letra)), 8, '0', STR_PAD_LEFT);
        }

        // Terminador: hasta cuatro ceros, y solo si queda sitio.
        $bits .= str_repeat('0', min(4, $totalDatos * 8 - strlen($bits)));

        // Cuadrar a byte
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        $flujo = [];

        foreach (str_split($bits, 8) as $byte) {
            $flujo[] = bindec($byte);
        }

        // Relleno alternando 236 y 17, que es lo que manda la norma
        $relleno = [236, 17];
        $i       = 0;

        while (count($flujo) < $totalDatos) {
            $flujo[] = $relleno[$i % 2];
            $i++;
        }

        // Repartir en bloques y sacarle la corrección a cada uno
        $datosBloques = [];
        $ecBloques    = [];
        $pos          = 0;

        foreach ([[$bloques1, $datos1], [$bloques2, $datos2]] as [$cuantos, $tamano]) {
            for ($b = 0; $b < $cuantos; $b++) {
                $bloque         = array_slice($flujo, $pos, $tamano);
                $pos            += $tamano;
                $datosBloques[] = $bloque;
                $ecBloques[]    = self::correccion($bloque, $ecPorBloque);
            }
        }

        // Entrelazado: primero el codeword 0 de cada bloque, luego el 1... Así
        // un borrón concentrado reparte el daño entre bloques en vez de
        // cargárselos todos en uno.
        $salida = [];
        $maximo = max(array_map('count', $datosBloques));

        for ($i = 0; $i < $maximo; $i++) {
            foreach ($datosBloques as $bloque) {
                if (isset($bloque[$i])) {
                    $salida[] = $bloque[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPorBloque; $i++) {
            foreach ($ecBloques as $bloque) {
                $salida[] = $bloque[$i];
            }
        }

        return $salida;
    }

    // ── Reed-Solomon sobre GF(256) ──────────────────────────────────────

    private static function prepararCampo(): void
    {
        if (self::$exp !== []) {
            return;
        }

        $x = 1;

        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;   // el polinomio primitivo que usa QR
            }
        }

        for ($i = 256; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function multiplicar(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /**
     * Los codewords de corrección de un bloque.
     *
     * @param list<int> $datos
     *
     * @return list<int>
     */
    private static function correccion(array $datos, int $cuantos): array
    {
        self::prepararCampo();

        // Polinomio generador: (x-a^0)(x-a^1)...(x-a^(n-1))
        $generador = [1];

        for ($i = 0; $i < $cuantos; $i++) {
            $nuevo = array_fill(0, count($generador) + 1, 0);

            foreach ($generador as $j => $coef) {
                // El índice 0 es el grado más alto: multiplicar por x deja el
                // coeficiente donde está, y multiplicar por a^i lo baja uno.
                // Cambiarlos de sitio da un polinomio que parece válido y deja
                // la corrección de errores sin servir para nada.
                $nuevo[$j]     ^= $coef;
                $nuevo[$j + 1] ^= self::multiplicar($coef, self::$exp[$i]);
            }

            $generador = $nuevo;
        }

        // División polinómica: el resto es la corrección
        $resto = array_merge($datos, array_fill(0, $cuantos, 0));

        for ($i = 0, $n = count($datos); $i < $n; $i++) {
            $guia = $resto[$i];

            if ($guia === 0) {
                continue;
            }

            foreach ($generador as $j => $coef) {
                $resto[$i + $j] ^= self::multiplicar($coef, $guia);
            }
        }

        return array_slice($resto, count($datos));
    }

    // ── Los patrones fijos ──────────────────────────────────────────────

    /**
     * @return array{0: list<list<bool>>, 1: list<list<bool>>}
     */
    private static function patrones(int $version, int $lado): array
    {
        $matriz    = array_fill(0, $lado, array_fill(0, $lado, false));
        $reservado = array_fill(0, $lado, array_fill(0, $lado, false));

        // Los tres ojos de las esquinas, con su separador blanco
        foreach ([[0, 0], [0, $lado - 7], [$lado - 7, 0]] as [$fy, $fx]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $py = $fy + $y;
                    $px = $fx + $x;

                    if ($py < 0 || $py >= $lado || $px < 0 || $px >= $lado) {
                        continue;
                    }

                    $borde  = $y === 0 || $y === 6 || $x === 0 || $x === 6;
                    $centro = $y >= 2 && $y <= 4 && $x >= 2 && $x <= 4;
                    $dentro = $y >= 0 && $y <= 6 && $x >= 0 && $x <= 6;

                    $matriz[$py][$px]    = $dentro && ($borde || $centro);
                    $reservado[$py][$px] = true;
                }
            }
        }

        // Las dos líneas de sincronismo
        for ($i = 8; $i < $lado - 8; $i++) {
            $matriz[6][$i]    = $i % 2 === 0;
            $matriz[$i][6]    = $i % 2 === 0;
            $reservado[6][$i] = true;
            $reservado[$i][6] = true;
        }

        // Patrones de alineación, saltando los que caerían sobre un ojo
        $centros = self::ALINEACION[$version];

        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                if (self::pisaUnOjo($cy, $cx, $lado)) {
                    continue;
                }

                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $matriz[$cy + $y][$cx + $x]    = abs($y) === 2 || abs($x) === 2 || ($y === 0 && $x === 0);
                        $reservado[$cy + $y][$cx + $x] = true;
                    }
                }
            }
        }

        // El módulo oscuro de siempre, y el hueco de la información de formato
        $matriz[4 * $version + 9][8]    = true;
        $reservado[4 * $version + 9][8] = true;

        for ($i = 0; $i < 9; $i++) {
            if (! $reservado[8][$i]) {
                $reservado[8][$i] = true;
            }
            if (! $reservado[$i][8]) {
                $reservado[$i][8] = true;
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $reservado[8][$lado - 1 - $i] = true;
            $reservado[$lado - 1 - $i][8] = true;
        }

        // Desde la versión 7 hay que decir qué versión es, por duplicado
        if ($version >= 7) {
            $bits = self::bitsVersion($version);

            for ($i = 0; $i < 18; $i++) {
                $bit = (bool) (($bits >> $i) & 1);
                $y   = intdiv($i, 3);
                $x   = $i % 3;

                $matriz[$y][$lado - 11 + $x]    = $bit;
                $reservado[$y][$lado - 11 + $x] = true;
                $matriz[$lado - 11 + $x][$y]    = $bit;
                $reservado[$lado - 11 + $x][$y] = true;
            }
        }

        return [$matriz, $reservado];
    }

    private static function pisaUnOjo(int $cy, int $cx, int $lado): bool
    {
        return ($cy <= 8 && $cx <= 8)
            || ($cy <= 8 && $cx >= $lado - 9)
            || ($cy >= $lado - 9 && $cx <= 8);
    }

    /** Los 18 bits de la versión: 6 de dato y 12 de corrección BCH. */
    private static function bitsVersion(int $version): int
    {
        $bits = $version << 12;
        $resto = $bits;

        for ($i = 17; $i >= 12; $i--) {
            if ($resto & (1 << $i)) {
                $resto ^= 0x1F25 << ($i - 12);
            }
        }

        return $bits | $resto;
    }

    // ── Datos, máscara y formato ────────────────────────────────────────

    /**
     * @param list<list<bool>> $matriz
     * @param list<list<bool>> $reservado
     * @param list<int>        $codewords
     */
    private static function colocarDatos(array &$matriz, array $reservado, array $codewords, int $lado): void
    {
        // Los bits van en zigzag, de abajo a la derecha hacia arriba, en
        // columnas de dos en dos. Los módulos que sobran al final se quedan
        // claros, que es justo lo que manda la norma.
        $bits = '';

        foreach ($codewords as $c) {
            $bits .= str_pad(decbin($c), 8, '0', STR_PAD_LEFT);
        }

        $i      = 0;
        $largo  = strlen($bits);
        $arriba = true;

        for ($col = $lado - 1; $col > 0; $col -= 2) {
            // La columna 6 es la de sincronismo: no se cuenta
            if ($col === 6) {
                $col--;
            }

            for ($paso = 0; $paso < $lado; $paso++) {
                $fila = $arriba ? $lado - 1 - $paso : $paso;

                foreach ([$col, $col - 1] as $c) {
                    if ($reservado[$fila][$c]) {
                        continue;
                    }

                    $matriz[$fila][$c] = $i < $largo && $bits[$i] === '1';
                    $i++;
                }
            }

            $arriba = ! $arriba;
        }
    }

    /**
     * @param list<list<bool>> $matriz
     * @param list<list<bool>> $reservado
     *
     * @return list<list<bool>>
     */
    private static function enmascarar(array $matriz, array $reservado, int $lado, int $mascara): array
    {
        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                if ($reservado[$y][$x]) {
                    continue;
                }

                if (self::toca($mascara, $y, $x)) {
                    $matriz[$y][$x] = ! $matriz[$y][$x];
                }
            }
        }

        return $matriz;
    }

    private static function toca(int $mascara, int $y, int $x): bool
    {
        return match ($mascara) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
            6 => (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0,
            7 => (($y + $x) % 2 + ($y * $x) % 3) % 2 === 0,
        };
    }

    /**
     * Escribe la información de formato: nivel de corrección y máscara.
     *
     * @param list<list<bool>> $matriz
     */
    private static function formato(array &$matriz, int $lado, int $mascara): void
    {
        // 00 = corrección M
        $datos = (0b00 << 3) | $mascara;
        $resto = $datos << 10;

        for ($i = 14; $i >= 10; $i--) {
            if ($resto & (1 << $i)) {
                $resto ^= 0x537 << ($i - 10);
            }
        }

        $bits = (($datos << 10) | $resto) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($bits >> $i) & 1);

            // Primera copia, alrededor del ojo de arriba a la izquierda
            if ($i < 6) {
                $matriz[8][$i] = $bit;
            } elseif ($i === 6) {
                $matriz[8][7] = $bit;
            } elseif ($i === 7) {
                $matriz[8][8] = $bit;
            } elseif ($i === 8) {
                $matriz[7][8] = $bit;
            } else {
                $matriz[14 - $i][8] = $bit;
            }

            // Segunda copia, repartida entre los otros dos ojos: siete módulos
            // en la columna y ocho en la fila. Uno más en la columna pisaría el
            // módulo oscuro fijo, y entonces ningún lector se orienta.
            if ($i < 7) {
                $matriz[$lado - 1 - $i][8] = $bit;
            } else {
                $matriz[8][$lado - 15 + $i] = $bit;
            }
        }
    }

    /**
     * Lo fea que queda una máscara. Cuanto más alto, peor.
     *
     * @param list<list<bool>> $matriz
     */
    private static function castigo(array $matriz, int $lado): int
    {
        $total = 0;

        // Regla 1: tiras de cinco o más del mismo color
        for ($y = 0; $y < $lado; $y++) {
            for ($eje = 0; $eje < 2; $eje++) {
                $anterior = null;
                $seguidos = 0;

                for ($x = 0; $x < $lado; $x++) {
                    $valor = $eje === 0 ? $matriz[$y][$x] : $matriz[$x][$y];

                    if ($valor === $anterior) {
                        $seguidos++;
                    } else {
                        if ($seguidos >= 5) {
                            $total += 3 + ($seguidos - 5);
                        }
                        $anterior = $valor;
                        $seguidos = 1;
                    }
                }

                if ($seguidos >= 5) {
                    $total += 3 + ($seguidos - 5);
                }
            }
        }

        // Regla 2: cuadrados de dos por dos del mismo color
        for ($y = 0; $y < $lado - 1; $y++) {
            for ($x = 0; $x < $lado - 1; $x++) {
                $v = $matriz[$y][$x];

                if ($v === $matriz[$y][$x + 1] && $v === $matriz[$y + 1][$x] && $v === $matriz[$y + 1][$x + 1]) {
                    $total += 3;
                }
            }
        }

        // Regla 3: dibujos que se confunden con un ojo
        $trampas = ['10111010000', '00001011101'];

        for ($y = 0; $y < $lado; $y++) {
            $fila = '';
            $col  = '';

            for ($x = 0; $x < $lado; $x++) {
                $fila .= $matriz[$y][$x] ? '1' : '0';
                $col  .= $matriz[$x][$y] ? '1' : '0';
            }

            foreach ($trampas as $trampa) {
                $total += substr_count($fila, $trampa) * 40;
                $total += substr_count($col, $trampa) * 40;
            }
        }

        // Regla 4: lo lejos que queda del cincuenta por ciento de oscuros
        $oscuros = 0;

        foreach ($matriz as $fila) {
            $oscuros += count(array_filter($fila));
        }

        $porciento = $oscuros * 100 / ($lado * $lado);
        $total     += (int) (abs($porciento - 50) / 5) * 10;

        return $total;
    }
}
