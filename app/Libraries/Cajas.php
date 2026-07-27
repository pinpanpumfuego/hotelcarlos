<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\CajaMovimientoModel;
use App\Models\CajaTurnoModel;
use App\Models\MedioPagoModel;
use App\Models\PuntoCajaModel;
use RuntimeException;

/**
 * Turnos de caja, arqueo y cierre.
 *
 * **La regla que sostiene todo el módulo:** en el arqueo solo se cuenta lo que
 * de verdad está dentro del cajón. Un cobro con tarjeta es un ingreso, pero no
 * hay un peso más que contar. Si se sumara, la caja «faltaría» exactamente lo
 * cobrado con tarjeta todos los días, nadie se fiaría del descuadre y un robo
 * pequeño no se notaría nunca.
 *
 * Un **retiro** no es un egreso: es plata que sale del cajón y sigue siendo del
 * hotel, normalmente hacia la caja fuerte. Mezclarlos hace que el día parezca
 * que costó lo que en realidad está guardado.
 */
class Cajas
{
    /** Los billetes y monedas que circulan en Colombia. */
    public const DENOMINACIONES = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50];

    private CajaTurnoModel $turnos;
    private CajaMovimientoModel $movimientos;
    private MedioPagoModel $medios;

    public function __construct()
    {
        $this->turnos      = new CajaTurnoModel();
        $this->movimientos = new CajaMovimientoModel();
        $this->medios      = new MedioPagoModel();
    }

    // ── Abrir y cerrar ──────────────────────────────────────────────────

    /**
     * Abre un turno en un punto.
     *
     * Un punto no puede tener dos turnos abiertos: si los tuviera, ningún
     * arqueo podría explicarse, porque los dos estarían contando el mismo
     * cajón.
     */
    public function abrir(int $puntoId, int $usuarioId, float $base): int
    {
        $punto = (new PuntoCajaModel())->find($puntoId);

        if ($punto === null || (int) $punto['activo'] !== 1) {
            throw new RuntimeException('Ese punto de caja no existe o está apagado.');
        }

        if ($base < 0) {
            throw new RuntimeException('La base inicial no puede ser negativa.');
        }

        if ($this->abiertoEn($puntoId) !== null) {
            throw new RuntimeException('Ya hay un turno abierto en ' . $punto['nombre'] . '. Ciérralo antes de abrir otro.');
        }

        $this->turnos->insert([
            'punto_id'     => $puntoId,
            'usuario_id'   => $usuarioId,
            'base_inicial' => $base,
            'apertura'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->turnos->getInsertID();
    }

    public function abiertoEn(int $puntoId): ?array
    {
        return $this->turnos->where('punto_id', $puntoId)->where('cierre IS NULL')->first();
    }

    /**
     * Lo que debería haber en el cajón ahora mismo.
     *
     * Base + lo cobrado en efectivo − lo gastado en efectivo − lo retirado.
     * Nada más entra en esta cuenta.
     */
    public function esperado(int $turnoId): float
    {
        $turno = $this->turnos->find($turnoId);

        if ($turno === null) {
            return 0;
        }

        $t = $this->totales($turnoId);

        return round((float) $turno['base_inicial'] + $t['ingresos'] - $t['egresos'] - $t['retiros'], 2);
    }

    /**
     * Los totales del turno, separando lo que toca el cajón de lo que no.
     *
     * @return array{ingresos: float, egresos: float, retiros: float,
     *               otros_medios: array<string, float>, total_cobrado: float}
     */
    public function totales(int $turnoId): array
    {
        $claves = $this->medios->clavesDeCaja();

        // Sin ningún medio marcado como efectivo, la cuenta del cajón sería
        // siempre la base: es un caso imposible, pero calcularlo mal en
        // silencio sería peor que devolver ceros.
        $enCaja = $claves === [] ? "''" : "'" . implode("','", array_map(
            static fn (string $c): string => str_replace("'", '', $c),
            $claves
        )) . "'";

        $fila = db_connect()->query(
            "SELECT
                SUM(CASE WHEN m.tipo = 'ingreso' AND (mp.clave IN ({$enCaja}) OR m.medio_id IS NULL)
                         THEN m.valor ELSE 0 END) AS ingresos,
                SUM(CASE WHEN m.tipo = 'egreso' THEN m.valor ELSE 0 END) AS egresos,
                SUM(CASE WHEN m.tipo = 'retiro' THEN m.valor ELSE 0 END) AS retiros,
                SUM(CASE WHEN m.tipo = 'ingreso' THEN m.valor ELSE 0 END) AS total_cobrado
             FROM caja_movimientos m
             LEFT JOIN medios_pago mp ON mp.id = m.medio_id
             WHERE m.turno_id = ?",
            [$turnoId]
        )->getRowArray();

        // Lo cobrado por cada medio que NO entra en el cajón. Se enseña al
        // cerrar para poder cuadrarlo con el datáfono y con el banco.
        $otros = [];

        $filas = db_connect()->query(
            "SELECT mp.nombre, SUM(m.valor) AS total
             FROM caja_movimientos m
             JOIN medios_pago mp ON mp.id = m.medio_id
             WHERE m.turno_id = ? AND m.tipo = 'ingreso' AND mp.afecta_caja = 0
             GROUP BY mp.id ORDER BY mp.orden",
            [$turnoId]
        )->getResultArray();

        foreach ($filas as $f) {
            $otros[$f['nombre']] = (float) $f['total'];
        }

        return [
            'ingresos'      => round((float) ($fila['ingresos'] ?? 0), 2),
            'egresos'       => round((float) ($fila['egresos'] ?? 0), 2),
            'retiros'       => round((float) ($fila['retiros'] ?? 0), 2),
            'total_cobrado' => round((float) ($fila['total_cobrado'] ?? 0), 2),
            'otros_medios'  => $otros,
        ];
    }

    /**
     * Cierra el turno con lo que se contó.
     *
     * @param array<int, int> $denominaciones billete => cuántos
     */
    public function cerrar(int $turnoId, array $denominaciones, ?int $usuarioId, string $justificacion = ''): array
    {
        $turno = $this->turnos->find($turnoId);

        if ($turno === null || $turno['cierre'] !== null) {
            throw new RuntimeException('Ese turno ya está cerrado.');
        }

        $punto = (new PuntoCajaModel())->find($turno['punto_id']);

        $contado = $this->guardarConteo($turnoId, $denominaciones);
        $esperado = $this->esperado($turnoId);
        $diferencia = round($contado - $esperado, 2);
        $totales    = $this->totales($turnoId);

        $tolerancia = $punto !== null ? (float) $punto['tolerancia'] : 2000;

        // Un descuadre por encima de la tolerancia hay que explicarlo. No es
        // desconfianza: es que dentro de un mes nadie se acordará de por qué
        // faltaban treinta mil pesos, y sin explicación solo queda la sospecha.
        if (abs($diferencia) > $tolerancia && trim($justificacion) === '') {
            throw new RuntimeException(sprintf(
                'El descuadre es de $%s y hay que explicarlo. Escribe qué pasó antes de cerrar.',
                number_format(abs($diferencia), 0, ',', '.')
            ));
        }

        $this->turnos->update($turnoId, [
            'cierre'           => date('Y-m-d H:i:s'),
            'cerro_id'         => $usuarioId,
            'efectivo_contado' => $contado,
            'retiros'          => $totales['retiros'],
            // Se congela: recalcularlo después daría otro número si alguien
            // tocara un movimiento viejo, y el descuadre firmado ese día
            // dejaría de poder explicarse.
            'esperado'         => $esperado,
            'diferencia'       => $diferencia,
            'justificacion'    => trim($justificacion) !== '' ? mb_substr(trim($justificacion), 0, 300) : null,
        ]);

        return [
            'contado'    => $contado,
            'esperado'   => $esperado,
            'diferencia' => $diferencia,
            'totales'    => $totales,
        ];
    }

    /**
     * Guarda el conteo y devuelve el total.
     *
     * **El total lo calcula el sistema, no lo escribe quien cuenta.** Escribir
     * «hay 847.000» de memoria y que cuadre es sospechosamente fácil; contar
     * billete a billete obliga a abrir el cajón.
     *
     * @param array<int, int> $denominaciones
     */
    public function guardarConteo(int $turnoId, array $denominaciones): float
    {
        $db    = db_connect();
        $total = 0.0;

        $db->table('arqueo_denominaciones')->where('turno_id', $turnoId)->delete();

        foreach (self::DENOMINACIONES as $valor) {
            $cantidad = max(0, (int) ($denominaciones[$valor] ?? 0));

            if ($cantidad === 0) {
                continue;
            }

            $db->table('arqueo_denominaciones')->insert([
                'turno_id'     => $turnoId,
                'denominacion' => $valor,
                'cantidad'     => $cantidad,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $total += $valor * $cantidad;
        }

        return round($total, 2);
    }

    /** @return array<int, int> */
    public function conteoDe(int $turnoId): array
    {
        $filas   = db_connect()->table('arqueo_denominaciones')->where('turno_id', $turnoId)->get()->getResultArray();
        $conteo  = [];

        foreach ($filas as $f) {
            $conteo[(int) $f['denominacion']] = (int) $f['cantidad'];
        }

        return $conteo;
    }

    // ── Movimientos ─────────────────────────────────────────────────────

    /**
     * Apunta un movimiento en el turno abierto de un punto.
     *
     * @param string $tipo `ingreso`, `egreso`, `retiro` o `ajuste`
     */
    public function apuntar(
        int $puntoId,
        string $tipo,
        string $concepto,
        float $valor,
        ?string $medioClave = null,
        ?string $referencia = null,
        ?int $usuarioId = null,
    ): int {
        if (! in_array($tipo, ['ingreso', 'egreso', 'retiro', 'ajuste'], true)) {
            throw new RuntimeException('Ese tipo de movimiento no existe.');
        }

        if ($valor <= 0) {
            throw new RuntimeException('El valor tiene que ser mayor que cero.');
        }

        if (trim($concepto) === '') {
            throw new RuntimeException('Di de qué es el movimiento: dentro de un mes nadie se acordará.');
        }

        $turno = $this->abiertoEn($puntoId);

        if ($turno === null) {
            throw new RuntimeException('No hay ningún turno abierto en ese punto.');
        }

        $medio = null;

        // Los gastos y los retiros salen del cajón: siempre son efectivo, diga
        // lo que diga el formulario. Se decide ANTES de validar la referencia:
        // si no, un gasto marcado por error como «tarjeta» pediría un número de
        // aprobación que no existe, para acabar guardándose como efectivo.
        if (in_array($tipo, ['egreso', 'retiro'], true)) {
            $medio = $this->medios->porClave('efectivo');
        } elseif ($medioClave !== null && $medioClave !== '') {
            $medio = $this->medios->porClave($medioClave);

            if ($medio === null) {
                throw new RuntimeException('Ese medio de pago no existe.');
            }

            // Un datáfono sin número de aprobación es un cobro que no se puede
            // reclamar el día que el banco no lo abone.
            if ((int) $medio['requiere_referencia'] === 1 && trim((string) $referencia) === '') {
                throw new RuntimeException('«' . $medio['nombre'] . '» necesita el número de referencia o aprobación.');
            }
        }

        $this->movimientos->insert([
            'turno_id'   => (int) $turno['id'],
            'tipo'       => $tipo,
            'medio_id'   => $medio !== null ? (int) $medio['id'] : null,
            'concepto'   => mb_substr(trim($concepto), 0, 150),
            'referencia' => $referencia !== null ? mb_substr(trim($referencia), 0, 80) : null,
            'valor'      => $valor,
            'usuario_id' => $usuarioId,
        ]);

        return (int) $this->movimientos->getInsertID();
    }

    /**
     * Un retiro a la caja fuerte.
     *
     * No se puede retirar más de lo que hay: dejaría la caja en negativo, que
     * es un estado que no existe en la realidad y que hace imposible cuadrar
     * nada después.
     */
    public function retirar(int $puntoId, float $valor, string $motivo, ?int $usuarioId = null): int
    {
        $turno = $this->abiertoEn($puntoId);

        if ($turno === null) {
            throw new RuntimeException('No hay ningún turno abierto en ese punto.');
        }

        $hay = $this->esperado((int) $turno['id']);

        if ($valor > $hay) {
            throw new RuntimeException(sprintf(
                'En la caja hay $%s: no se pueden retirar $%s.',
                number_format($hay, 0, ',', '.'),
                number_format($valor, 0, ',', '.')
            ));
        }

        return $this->apuntar($puntoId, 'retiro', $motivo, $valor, null, null, $usuarioId);
    }
}
