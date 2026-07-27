<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Qr;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Los códigos QR.
 *
 * Un QR mal generado no se nota mirándolo: se nota cuando el técnico está
 * delante del calentador con el móvil en la mano y no engancha. Para entonces
 * la etiqueta ya está pegada.
 *
 * Por eso aquí abajo hay un **lector escrito aparte**, que no comparte una sola
 * línea con el generador: coge la matriz, saca la máscara de la información de
 * formato, la deshace, lee los módulos en zigzag, desentrelaza los bloques y
 * devuelve el texto. Si no coincide con lo que se pidió codificar, la prueba
 * falla.
 *
 * Además se comprueba la corrección de errores por un camino distinto al que
 * la genera: los síndromes de Reed-Solomon tienen que dar cero. Eso caza un
 * fallo que el ida y vuelta no vería, porque el ida y vuelta no toca la
 * corrección.
 *
 * @internal
 */
final class QrTest extends CIUnitTestCase
{
    /** Igual que en la librería, pero escrito aquí: si uno se equivoca, no cuadran. */
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

    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    // ── Lo que de verdad importa: que se pueda leer ─────────────────────

    /**
     * @dataProvider textos
     */
    public function testLoGeneradoSeVuelveALeer(string $texto): void
    {
        $matriz = Qr::matriz($texto);

        $this->assertSame($texto, $this->leer($matriz), 'El QR no devuelve lo que se codificó.');
    }

    public static function textos(): array
    {
        return [
            'una letra'          => ['A'],
            'un código de activo' => ['SAL-0042'],
            'la dirección real'  => ['https://sanantoniodeloslagos.com/activo/SAL-0042'],
            'con acentos'        => ['Calentador cabaña Sinsonte nº 3'],
            'justo en el límite v1' => [str_repeat('X', 14)],
            'un carácter más, salta a v2' => [str_repeat('X', 15)],
            'v3'                 => [str_repeat('m', 40)],
            'v4'                 => [str_repeat('m', 60)],
            'v5'                 => [str_repeat('m', 80)],
            'v6'                 => [str_repeat('m', 100)],
            'v7 (lleva versión escrita)' => [str_repeat('m', 120)],
            'v8'                 => [str_repeat('m', 150)],
            'v9'                 => [str_repeat('m', 175)],
            'v10 (longitud de 16 bits)' => [str_repeat('m', 210)],
            'el tope'            => [str_repeat('m', 213)],
        ];
    }

    public function testTextosAlAzarTambienSeLeen(): void
    {
        // Los casos elegidos a mano tienden a parecerse entre sí. Estos no.
        for ($i = 0; $i < 40; $i++) {
            $largo = random_int(1, 213);
            $texto = '';

            for ($j = 0; $j < $largo; $j++) {
                $texto .= chr(random_int(32, 126));
            }

            $this->assertSame($texto, $this->leer(Qr::matriz($texto)), 'Falló con: ' . $texto);
        }
    }

    // ── La corrección de errores, por otro camino ───────────────────────

    public function testLaCorreccionDeErroresEsValida(): void
    {
        // Los síndromes de Reed-Solomon: si el bloque está bien formado, dan
        // cero. Se calculan de otra forma que como se generaron.
        foreach (['SAL-0001', str_repeat('z', 120), str_repeat('z', 213)] as $texto) {
            $matriz  = Qr::matriz($texto);
            $version = (count($matriz) - 17) / 4;

            foreach ($this->bloquesDe($matriz, (int) $version) as $n => $bloque) {
                foreach ($this->sindromes($bloque, self::BLOQUES[$version][0]) as $i => $s) {
                    $this->assertSame(0, $s, "Bloque {$n}, síndrome {$i} distinto de cero.");
                }
            }
        }
    }

    // ── Estructura ──────────────────────────────────────────────────────

    public function testLosTresOjosEstanDondeTienenQueEstar(): void
    {
        $m    = Qr::matriz('https://sanantoniodeloslagos.com/activo/SAL-0042');
        $lado = count($m);

        foreach ([[0, 0], [0, $lado - 7], [$lado - 7, 0]] as [$fy, $fx]) {
            // Marco oscuro, anillo claro, centro oscuro
            $this->assertTrue($m[$fy][$fx]);
            $this->assertTrue($m[$fy + 6][$fx + 6]);
            $this->assertFalse($m[$fy + 1][$fx + 1]);
            $this->assertTrue($m[$fy + 3][$fx + 3]);
        }

        // La cuarta esquina no lleva ojo: es como el lector sabe la orientación
        $this->assertFalse($m[$lado - 1][$lado - 1]);
    }

    public function testElModuloOscuroObligatorioEstaPuesto(): void
    {
        foreach (['A', str_repeat('m', 100), str_repeat('m', 213)] as $texto) {
            $m       = Qr::matriz($texto);
            $version = (int) ((count($m) - 17) / 4);

            $this->assertTrue($m[4 * $version + 9][8], 'Falta el módulo oscuro fijo.');
        }
    }

    public function testElTamanoCreceConElTexto(): void
    {
        $this->assertSame(21, count(Qr::matriz('A')), 'v1 son 21 módulos.');
        $this->assertSame(25, count(Qr::matriz(str_repeat('X', 15))), 'v2 son 25.');
        $this->assertSame(57, count(Qr::matriz(str_repeat('m', 213))), 'v10 son 57.');
    }

    // ── Bordes ──────────────────────────────────────────────────────────

    public function testUnTextoVacioNoSeCodifica(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Qr::matriz('');
    }

    public function testUnTextoDemasiadoLargoAvisaEnVezDeSalirMal(): void
    {
        // Callarse y recortar el texto sería peor: la etiqueta se imprimiría y
        // llevaría a una dirección que no existe.
        $this->expectException(\InvalidArgumentException::class);
        Qr::matriz(str_repeat('m', 214));
    }

    // ── El SVG ──────────────────────────────────────────────────────────

    public function testElSvgSaleConLaZonaTranquila(): void
    {
        $svg = Qr::svg('SAL-0042', 4, 4);

        // 21 módulos + 4 de margen a cada lado = 29, por 4 píxeles = 116
        $this->assertStringContainsString('width="116"', $svg);
        $this->assertStringContainsString('viewBox="0 0 116 116"', $svg);
        $this->assertStringContainsString('<rect', $svg, 'Sin fondo blanco no hay contraste al imprimir.');
        $this->assertStringContainsString('fill="#000"', $svg);
    }

    public function testElSvgNoLlevaNadaEjecutable(): void
    {
        // Se pinta dentro de páginas del panel: nada de scripts ni de enlaces.
        $svg = Qr::svg('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('href', $svg);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  El lector. Escrito aparte a propósito.
    // ═══════════════════════════════════════════════════════════════════

    /** @param list<list<bool>> $matriz */
    private function leer(array $matriz): string
    {
        $lado    = count($matriz);
        $version = (int) (($lado - 17) / 4);

        $mascara   = $this->mascaraDe($matriz, $lado);
        $reservado = $this->reservado($version, $lado);

        // Deshacer la máscara
        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                if (! $reservado[$y][$x] && $this->toca($mascara, $y, $x)) {
                    $matriz[$y][$x] = ! $matriz[$y][$x];
                }
            }
        }

        $codewords = $this->codewordsDe($matriz, $reservado, $lado);
        $datos     = $this->desentrelazar($codewords, $version);

        return $this->desempaquetar($datos, $version);
    }

    /**
     * La máscara, leída de la **segunda** copia de la información de formato.
     *
     * A propósito la segunda: así se comprueba de paso que las dos copias se
     * escribieron, que es lo que salva el código cuando una esquina se raya.
     *
     * @param list<list<bool>> $matriz
     */
    private function mascaraDe(array $matriz, int $lado): int
    {
        $bits = 0;

        for ($i = 0; $i < 15; $i++) {
            $bit = $i < 7 ? $matriz[$lado - 1 - $i][8] : $matriz[8][$lado - 15 + $i];

            if ($bit) {
                $bits |= 1 << $i;
            }
        }

        $bits ^= 0x5412;

        $nivel = ($bits >> 13) & 0b11;
        $this->assertSame(0b00, $nivel, 'El nivel de corrección debería ser M.');

        return ($bits >> 10) & 0b111;
    }

    /** @return list<list<bool>> */
    private function reservado(int $version, int $lado): array
    {
        $r = array_fill(0, $lado, array_fill(0, $lado, false));

        foreach ([[0, 0], [0, $lado - 7], [$lado - 7, 0]] as [$fy, $fx]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    if (isset($r[$fy + $y][$fx + $x])) {
                        $r[$fy + $y][$fx + $x] = true;
                    }
                }
            }
        }

        for ($i = 0; $i < $lado; $i++) {
            $r[6][$i] = true;
            $r[$i][6] = true;
        }

        $centros = self::ALINEACION[$version];

        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                $pisa = ($cy <= 8 && $cx <= 8)
                    || ($cy <= 8 && $cx >= $lado - 9)
                    || ($cy >= $lado - 9 && $cx <= 8);

                if ($pisa) {
                    continue;
                }

                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $r[$cy + $y][$cx + $x] = true;
                    }
                }
            }
        }

        $r[4 * $version + 9][8] = true;

        for ($i = 0; $i < 9; $i++) {
            $r[8][$i] = true;
            $r[$i][8] = true;
        }

        for ($i = 0; $i < 8; $i++) {
            $r[8][$lado - 1 - $i] = true;
            $r[$lado - 1 - $i][8] = true;
        }

        if ($version >= 7) {
            for ($i = 0; $i < 18; $i++) {
                $y = intdiv($i, 3);
                $x = $i % 3;

                $r[$y][$lado - 11 + $x] = true;
                $r[$lado - 11 + $x][$y] = true;
            }
        }

        return $r;
    }

    private function toca(int $mascara, int $y, int $x): bool
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
     * @param list<list<bool>> $matriz
     * @param list<list<bool>> $reservado
     *
     * @return list<int>
     */
    private function codewordsDe(array $matriz, array $reservado, int $lado): array
    {
        $bits   = '';
        $arriba = true;

        for ($col = $lado - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }

            for ($paso = 0; $paso < $lado; $paso++) {
                $fila = $arriba ? $lado - 1 - $paso : $paso;

                foreach ([$col, $col - 1] as $c) {
                    if (! $reservado[$fila][$c]) {
                        $bits .= $matriz[$fila][$c] ? '1' : '0';
                    }
                }
            }

            $arriba = ! $arriba;
        }

        $codewords = [];

        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $byte) {
            $codewords[] = bindec($byte);
        }

        return $codewords;
    }

    /**
     * Deshace el entrelazado y devuelve solo los codewords de datos.
     *
     * @param list<int> $codewords
     *
     * @return list<int>
     */
    private function desentrelazar(array $codewords, int $version): array
    {
        [, $bloques1, $datos1, $bloques2, $datos2] = self::BLOQUES[$version];

        $tamanos = array_merge(
            array_fill(0, $bloques1, $datos1),
            array_fill(0, $bloques2, $datos2)
        );

        $bloques = array_fill(0, count($tamanos), []);
        $pos     = 0;
        $maximo  = max($tamanos);

        for ($i = 0; $i < $maximo; $i++) {
            foreach ($tamanos as $b => $tamano) {
                if ($i < $tamano) {
                    $bloques[$b][$i] = $codewords[$pos++];
                }
            }
        }

        return array_merge(...array_map(static fn (array $b): array => array_values($b), $bloques));
    }

    /**
     * Bloques completos (datos + corrección), tal y como los ve un lector.
     *
     * @param list<list<bool>> $matriz
     *
     * @return list<list<int>>
     */
    private function bloquesDe(array $matriz, int $version): array
    {
        $lado      = count($matriz);
        $mascara   = $this->mascaraDe($matriz, $lado);
        $reservado = $this->reservado($version, $lado);

        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                if (! $reservado[$y][$x] && $this->toca($mascara, $y, $x)) {
                    $matriz[$y][$x] = ! $matriz[$y][$x];
                }
            }
        }

        $codewords = $this->codewordsDe($matriz, $reservado, $lado);
        [$ec, $bloques1, $datos1, $bloques2, $datos2] = self::BLOQUES[$version];

        $tamanos = array_merge(array_fill(0, $bloques1, $datos1), array_fill(0, $bloques2, $datos2));
        $cuantos = count($tamanos);

        $datos = array_fill(0, $cuantos, []);
        $pos   = 0;

        for ($i = 0, $maximo = max($tamanos); $i < $maximo; $i++) {
            foreach ($tamanos as $b => $tamano) {
                if ($i < $tamano) {
                    $datos[$b][] = $codewords[$pos++];
                }
            }
        }

        $correccion = array_fill(0, $cuantos, []);

        for ($i = 0; $i < $ec; $i++) {
            for ($b = 0; $b < $cuantos; $b++) {
                $correccion[$b][] = $codewords[$pos++];
            }
        }

        $salida = [];

        for ($b = 0; $b < $cuantos; $b++) {
            $salida[] = array_merge($datos[$b], $correccion[$b]);
        }

        return $salida;
    }

    /**
     * @param list<int> $datos
     */
    private function desempaquetar(array $datos, int $version): string
    {
        $bits = '';

        foreach ($datos as $c) {
            $bits .= str_pad(decbin($c), 8, '0', STR_PAD_LEFT);
        }

        $modo = bindec(substr($bits, 0, 4));
        $this->assertSame(0b0100, $modo, 'El modo debería ser byte.');

        $anchoLargo = $version >= 10 ? 16 : 8;
        $largo      = bindec(substr($bits, 4, $anchoLargo));

        $texto = '';

        for ($i = 0; $i < $largo; $i++) {
            $texto .= chr(bindec(substr($bits, 4 + $anchoLargo + $i * 8, 8)));
        }

        return $texto;
    }

    // ── Reed-Solomon, solo para comprobar ───────────────────────────────

    /**
     * @param list<int> $bloque
     *
     * @return list<int>
     */
    private function sindromes(array $bloque, int $cuantos): array
    {
        [$exp, $log] = $this->campo();

        $mul = static function (int $a, int $b) use ($exp, $log): int {
            return $a === 0 || $b === 0 ? 0 : $exp[$log[$a] + $log[$b]];
        };

        $n         = count($bloque);
        $sindromes = [];

        for ($i = 0; $i < $cuantos; $i++) {
            $s = 0;

            for ($j = 0; $j < $n; $j++) {
                $s ^= $mul($bloque[$j], $exp[($i * ($n - 1 - $j)) % 255]);
            }

            $sindromes[] = $s;
        }

        return $sindromes;
    }

    /** @return array{0: list<int>, 1: array<int, int>} */
    private function campo(): array
    {
        $exp = [];
        $log = [];
        $x   = 1;

        for ($i = 0; $i < 256; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        for ($i = 256; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        return [$exp, $log];
    }
}
