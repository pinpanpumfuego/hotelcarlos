<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$num    = static fn (?float $n, int $d = 1): string => $n === null
    ? '—'
    : number_format($n, $d, ',', '.');
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Operación</h1>
        <p class="text-muted mb-0">
            <?= date('d/m/Y', strtotime($desde)) ?> — <?= date('d/m/Y', strtotime($hasta)) ?>
            · si el hotel <em>funciona</em> bien, que no es lo mismo que si se vende bien
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="get" class="d-flex gap-2 flex-wrap">
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= esc($desde) ?>">
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= esc($hasta) ?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button>
        </form>
        <a href="<?= site_url('reportes/gerencia') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer me-1"></i>Cuadro de mando
        </a>
    </div>
</div>

<!-- ═══ HUÉSPEDES ═══ -->
<h2 class="h5 mt-2 mb-3"><i class="bi bi-people me-2 text-muted"></i>Huéspedes</h2>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Vinieron</div>
                <div class="fs-3 fw-semibold"><?= (int) $huespedes['total'] ?></div>
                <div class="small text-muted">
                    <?= (int) $huespedes['nuevos'] ?> nuevos ·
                    <?= (int) $huespedes['recurrentes'] ?> repiten
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Repetición</div>
                <div class="fs-3 fw-semibold"><?= $num($huespedes['repeticion']) ?> %</div>
                <div class="small text-muted">ya habían venido antes</div>
            </div>
        </div>
    </div>
    <?php if ($ver_dinero): ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Gasto por huésped</div>
                    <div class="fs-3 fw-semibold"><?= $dinero((float) $huespedes['gasto_medio']) ?></div>
                    <div class="small text-muted">todo incluido</div>
                </div>
            </div>
        </div>
    <?php endif ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Satisfacción</div>
                <div class="fs-3 fw-semibold">
                    <?= $huespedes['satisfaccion'] !== null ? $num($huespedes['satisfaccion'], 2) : '—' ?>
                </div>
                <div class="small text-muted">
                    <?= (int) $huespedes['encuestas'] ?> encuestas
                    <?php if ((int) $huespedes['notas_bajas'] > 0): ?>
                        · <span class="text-danger"><?= (int) $huespedes['notas_bajas'] ?> bajas</span>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-geo me-2"></i>De dónde vienen</div>
            <?php if ($huespedes['procedencia'] === []): ?>
                <div class="card-body text-muted small">Nadie en este periodo.</div>
            <?php else: ?>
                <?php $maxProc = max(array_column($huespedes['procedencia'], 'n')); ?>
                <div class="card-body">
                    <?php foreach ($huespedes['procedencia'] as $p): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= esc($p['lugar']) ?></span>
                                <span class="text-muted"><?= (int) $p['n'] ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" style="width: <?= round($p['n'] / $maxProc * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-envelope-check me-2"></i>A cuántos se les puede escribir</div>
            <div class="card-body">
                <div class="d-flex align-items-end gap-3 mb-2">
                    <div>
                        <div class="fs-2 fw-semibold"><?= (int) $huespedes['con_permiso'] ?></div>
                        <div class="small text-muted">de <?= (int) $huespedes['base_activa'] ?> perfiles</div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: <?= min(100, $huespedes['permiso_pct']) ?>%"></div>
                        </div>
                        <div class="small text-muted mt-1"><?= $num($huespedes['permiso_pct']) ?> % han autorizado</div>
                    </div>
                </div>
                <?php // Casi siempre es mucho menor de lo que la gente cree, y
                      // es el techo real de cualquier campaña. ?>
                <p class="small text-muted mb-0">
                    Es el <strong>techo real</strong> de cualquier campaña. A los demás no se les
                    puede escribir aunque estén en la base: no lo autorizaron.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ═══ RESTAURANTE ═══ -->
<h2 class="h5 mt-4 mb-3"><i class="bi bi-cup-hot me-2 text-muted"></i>Restaurante</h2>

<div class="row g-3 mb-4">
    <?php
    $rest = [
        ['Comandas', (string) $restaurante['comandas'], null],
        ['Venta', $ver_dinero ? $dinero((float) $restaurante['venta']) : '—', null],
        ['Ticket medio', $ver_dinero ? $dinero((float) $restaurante['ticket']) : '—', null],
        ['Por comensal', $ver_dinero ? $dinero((float) $restaurante['por_persona']) : '—',
            (int) $restaurante['comensales'] . ' comensales'],
        ['Espera en cocina', $num($restaurante['espera']) . ' min', 'desde que se pide'],
        ['Preparación', $num($restaurante['preparacion']) . ' min', 'hasta que está listo'],
    ];
    ?>
    <?php foreach ($rest as [$titulo, $valor, $pie]): ?>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small"><?= $titulo ?></div>
                    <div class="fs-5 fw-semibold"><?= esc($valor) ?></div>
                    <?php if ($pie !== null): ?>
                        <div class="text-muted" style="font-size: .72rem;"><?= esc($pie) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php if ((int) $restaurante['lineas_medidas'] === 0): ?>
    <div class="alert alert-light border small">
        <i class="bi bi-stopwatch me-1"></i>
        No hay tiempos que medir: nadie está marcando «recibido» y «listo» en la pantalla de cocina.
        Sin esos dos toques, el tiempo de servicio no se puede saber — y es el número que más
        recuerda un huésped.
    </div>
<?php endif ?>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Lo que no se cobró</div>
            <div class="card-body">
                <?php // Cortesía y devolución son dos cosas distintas: una se
                      // regala y la otra se rechaza. El remedio no es el mismo,
                      // así que no se suman. ?>
                <dl class="row mb-0 small">
                    <dt class="col-7 text-muted fw-normal">Cortesías</dt>
                    <dd class="col-5 text-end">
                        <?= (int) $restaurante['cortesias']['n'] ?>
                        <?php if ($ver_dinero): ?>
                            <div class="text-muted"><?= $dinero((float) $restaurante['cortesias']['valor']) ?></div>
                        <?php endif ?>
                    </dd>
                    <dt class="col-7 text-muted fw-normal">Devoluciones</dt>
                    <dd class="col-5 text-end">
                        <?= (int) $restaurante['devueltas']['n'] ?>
                        <?php if ($ver_dinero): ?>
                            <div class="text-muted"><?= $dinero((float) $restaurante['devueltas']['valor']) ?></div>
                        <?php endif ?>
                    </dd>
                    <dt class="col-7 text-muted fw-normal">Comandas anuladas</dt>
                    <dd class="col-5 text-end">
                        <?= (int) $restaurante['anuladas'] ?>
                        <?php if ($ver_dinero): ?>
                            <div class="text-muted"><?= $dinero((float) $restaurante['anuladas_valor']) ?></div>
                        <?php endif ?>
                    </dd>
                    <?php if ($ver_dinero): ?>
                        <dt class="col-7 text-muted fw-normal border-top pt-2">Descuentos dados</dt>
                        <dd class="col-5 text-end border-top pt-2"><?= $dinero((float) $restaurante['descuentos']) ?></dd>
                    <?php endif ?>
                </dl>
                <div class="form-text mt-2">
                    Una cortesía se regala; una devolución la rechazó el huésped. No se suman
                    porque el remedio no es el mismo.
                </div>
            </div>
        </div>
    </div>

    <?php if ($ver_dinero): ?>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-box-seam me-2"></i>Lo que debería haberse gastado, contra lo que se gastó
                </div>
                <div class="card-body">
                    <?php $desv = $cocina['desviacion']; ?>
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <div class="text-muted small">Teórico</div>
                            <div class="fs-5 fw-semibold"><?= $dinero((float) $cocina['teorico']) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Real</div>
                            <div class="fs-5 fw-semibold"><?= $dinero((float) $cocina['real']) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Diferencia</div>
                            <div class="fs-5 fw-semibold <?= $desv > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= ($desv > 0 ? '+' : '') . $dinero((float) $desv) ?>
                            </div>
                            <?php if ($cocina['desviacion_pct'] !== null): ?>
                                <div class="text-muted" style="font-size: .72rem;">
                                    <?= $num($cocina['desviacion_pct']) ?> %
                                </div>
                            <?php endif ?>
                        </div>
                    </div>

                    <?php if ($cocina['lineas'] === []): ?>
                        <p class="small text-muted mb-0">
                            Sin datos todavía. Hace falta que las recetas estén cargadas y que las
                            salidas de almacén se estén apuntando.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 260px;">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Insumo</th>
                                        <th class="text-end">Debía</th>
                                        <th class="text-end">Gastó</th>
                                        <th class="text-end">Cuesta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cocina['lineas'] as $l): ?>
                                        <tr>
                                            <td><?= esc($l['insumo']) ?></td>
                                            <td class="text-end text-muted"><?= $num((float) $l['teorico'], 2) ?></td>
                                            <td class="text-end"><?= $num((float) $l['real'], 2) ?></td>
                                            <td class="text-end <?= (float) $l['valor'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                <?= ((float) $l['valor'] > 0 ? '+' : '') . $dinero((float) $l['valor']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text mt-2">
                            La diferencia son mermas, raciones generosas o cosas que salieron por la
                            puerta. Ninguna de las tres se arregla mirando el total de ventas.
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    <?php endif ?>
</div>

<!-- ═══ OPERACIÓN ═══ -->
<h2 class="h5 mt-4 mb-3"><i class="bi bi-tools me-2 text-muted"></i>Limpieza y mantenimiento</h2>

<div class="row g-3 mb-4">
    <?php
    $tarjetas = [
        ['Limpiezas', (string) $op['limpiezas'], $num($op['limpieza_min']) . ' min de media'],
        ['Reprocesos', $num($op['reproceso_pct']) . ' %', $op['rechazadas'] . ' rechazadas'],
        ['Órdenes de trabajo', (string) $op['ordenes'],
            $op['preventivas'] . ' preventivas · ' . $op['correctivas'] . ' averías'],
        ['Dentro de plazo', $num($op['sla_pct']) . ' %',
            $op['fuera_plazo'] > 0 ? $op['fuera_plazo'] . ' fuera' : 'todas a tiempo'],
        ['Cabañas paradas', $num($op['dias_paradas']) . ' días', $op['bloqueos'] . ' bloqueos'],
        ['Coste del mantenimiento', $ver_dinero ? $dinero((float) $op['mant_costo']) : '—',
            'materiales y mano de obra'],
    ];
    ?>
    <?php foreach ($tarjetas as [$titulo, $valor, $pie]): ?>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small"><?= $titulo ?></div>
                    <div class="fs-5 fw-semibold"><?= esc($valor) ?></div>
                    <div class="text-muted" style="font-size: .72rem;"><?= esc($pie) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php if ((int) $op['correctivas'] > 0 && (int) $op['preventivas'] < (int) $op['correctivas'] / 2): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-fire me-1"></i>
        <strong>Se está apagando fuegos.</strong>
        <?= (int) $op['correctivas'] ?> averías frente a <?= (int) $op['preventivas'] ?> revisiones
        programadas. Donde el preventivo funciona, lo programado pesa más que lo roto.
    </div>
<?php endif ?>

<?php if ((int) $op['dias_paradas'] > 0): ?>
    <div class="alert alert-light border small">
        <i class="bi bi-door-closed me-1"></i>
        <strong><?= $num($op['dias_paradas']) ?> días</strong> de cabaña sin poder venderse por
        averías. Es la cifra que traduce el mantenimiento a dinero: tres días bloqueada en
        temporada son tres noches que no se vendieron.
    </div>
<?php endif ?>

<!-- ═══ FINANZAS ═══ -->
<?php if ($ver_dinero): ?>
    <h2 class="h5 mt-4 mb-3"><i class="bi bi-cash-stack me-2 text-muted"></i>Dinero</h2>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Cajas cuadradas</div>
                    <div class="fs-3 fw-semibold <?= $finanzas['cuadre_pct'] < 90 ? 'text-warning' : '' ?>">
                        <?= $num($finanzas['cuadre_pct']) ?> %
                    </div>
                    <div class="small text-muted">
                        <?= (int) $finanzas['turnos'] ?> turnos ·
                        <?= (int) $finanzas['con_descuadre'] ?> con diferencia
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Descuadre acumulado</div>
                    <div class="fs-3 fw-semibold"><?= $dinero((float) $finanzas['descuadre']) ?></div>
                    <div class="small text-muted">en valor absoluto</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body py-3">
                    <div class="text-muted small">Por cobrar hoy</div>
                    <div class="fs-3 fw-semibold"><?= $dinero((float) $finanzas['por_cobrar']) ?></div>
                    <div class="small text-muted"><?= (int) $finanzas['deudores'] ?> reservas con saldo</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Anticipos en casa</div>
                    <div class="fs-3 fw-semibold"><?= $dinero((float) $finanzas['anticipos']) ?></div>
                    <div class="small text-muted">de estancias que no han empezado</div>
                </div>
            </div>
        </div>
    </div>

    <?php // «Por cobrar» y «anticipos» no se refieren al periodo sino a HOY: una
          // deuda no pertenece al mes en que nació, pertenece al presente. ?>
    <div class="alert alert-light border small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Por cobrar</strong> y <strong>anticipos</strong> no son del periodo elegido: son de
        hoy. Una deuda no pertenece al mes en que nació, pertenece al presente.
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2 me-2"></i>Cómo pagaron</div>
                <?php if ($finanzas['por_medio'] === []): ?>
                    <div class="card-body text-muted small">Sin cobros en el periodo.</div>
                <?php else: ?>
                    <?php $totalMedios = array_sum(array_column($finanzas['por_medio'], 'total')); ?>
                    <div class="card-body">
                        <?php foreach ($finanzas['por_medio'] as $m): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span><?= esc(ucfirst($m['metodo'])) ?></span>
                                    <strong><?= $dinero((float) $m['total']) ?></strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar"
                                         style="width: <?= $totalMedios > 0 ? round($m['total'] / $totalMedios * 100) : 0 ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2"></i>Facturación</div>
                <div class="card-body">
                    <?php if ($finanzas['facturas'] === []): ?>
                        <p class="text-muted small mb-0">Ninguna factura en el periodo.</p>
                    <?php else: ?>
                        <dl class="row mb-0 small">
                            <?php foreach ($finanzas['facturas'] as $f): ?>
                                <dt class="col-7 text-muted fw-normal"><?= esc(ucfirst($f['estado'])) ?></dt>
                                <dd class="col-5 text-end">
                                    <?= (int) $f['n'] ?>
                                    <div class="text-muted"><?= $dinero((float) $f['total']) ?></div>
                                </dd>
                            <?php endforeach ?>
                        </dl>
                    <?php endif ?>

                    <div class="border-top mt-3 pt-3 small">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Cobrado en línea (Wompi)</span>
                            <strong><?= $dinero((float) $finanzas['online_total']) ?></strong>
                        </div>
                        <div class="text-muted"><?= (int) $finanzas['online'] ?> pagos aprobados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
