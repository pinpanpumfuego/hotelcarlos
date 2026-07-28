<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');

// `strftime` está obsoleto desde PHP 8.1 y no traduce los meses sin depender
// del locale del servidor, que en un hosting compartido no se controla.
$nombresMes = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
               '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
               '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];

// Las funciones flecha capturan el ámbito exterior por valor, así que
// `$nombresMes` entra sola: no hace falta `use`.
$mesLargo = static fn (string $fecha): string => ($nombresMes[date('m', strtotime($fecha))] ?? '')
    . ' ' . date('Y', strtotime($fecha));

/** Pinta la variación contra el año pasado, o calla si no hay con qué comparar. */
$variacion = static function (?float $pct) : string {
    if ($pct === null) {
        return '<span class="text-muted small">sin comparación</span>';
    }

    $color = $pct > 0 ? 'success' : ($pct < 0 ? 'danger' : 'muted');
    $signo = $pct > 0 ? '+' : '';

    return '<span class="small text-' . $color . '">' . $signo
        . number_format($pct, 1, ',', '.') . ' % vs. año pasado</span>';
};
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Cuadro de mando</h1>
        <p class="text-muted mb-0">
            <?= date('d/m/Y', strtotime($desde)) ?> — <?= date('d/m/Y', strtotime($hasta)) ?>
            · <?= (int) $a['dias'] ?> noches · <?= (int) $a['cabanas'] ?> cabañas vendibles
            <?php if ((int) $a['fuera_servicio'] > 0): ?>
                · <span class="text-danger"><?= (int) $a['fuera_servicio'] ?> fuera de servicio</span>
            <?php endif ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="get" class="d-flex gap-2 flex-wrap">
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= esc($desde) ?>">
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= esc($hasta) ?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i></button>
        </form>
        <a href="<?= site_url('reportes') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-cash-coin me-1"></i>Informe de caja
        </a>
    </div>
</div>

<!-- ═══ Los cuatro números ═══ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Ocupación</div>
                <div class="fs-2 fw-semibold"><?= number_format($a['ocupacion'], 1, ',', '.') ?> %</div>
                <div><?= $variacion($ind->variacion($a['ocupacion'], $b['ocupacion'])) ?></div>
                <div class="small text-muted mt-1">
                    <?= (int) $a['vendidas'] ?> de <?= (int) $a['disponibles'] ?> noches
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">ADR</div>
                <div class="fs-2 fw-semibold"><?= $dinero((float) $a['adr']) ?></div>
                <div><?= $variacion($ind->variacion((float) $a['adr'], (float) $b['adr'])) ?></div>
                <div class="small text-muted mt-1">precio medio por noche vendida</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body py-3">
                <div class="text-muted small">RevPAR</div>
                <div class="fs-2 fw-semibold"><?= $dinero((float) $a['revpar']) ?></div>
                <div><?= $variacion($ind->variacion((float) $a['revpar'], (float) $b['revpar'])) ?></div>
                <div class="small text-muted mt-1">por cabaña disponible</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">TRevPAR</div>
                <div class="fs-2 fw-semibold"><?= $dinero((float) $a['trevpar']) ?></div>
                <div><?= $variacion($ind->variacion((float) $a['trevpar'], (float) $b['trevpar'])) ?></div>
                <div class="small text-muted mt-1">con restaurante y extras</div>
            </div>
        </div>
    </div>
</div>

<?php // Esto va aquí y no en una nota al pie: la mitad de las discusiones sobre
      // un ADR vienen de que dos personas lo están calculando distinto. ?>
<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Cómo se calculan.</strong>
    El alojamiento se reparte <strong>noche a noche</strong>: una estancia de diez noches deja su
    parte en cada una, no toda en el día que se cobró.
    El <strong>ADR</strong> es solo alojamiento —el restaurante no entra— y se divide entre las
    noches vendidas descontando las cortesías, que ocupan pero no dejan dinero.
    El <strong>RevPAR</strong> divide entre las noches <em>disponibles</em>, y las cabañas
    bloqueadas por avería no cuentan como disponibles: no se podían vender.
</div>

<!-- ═══ De dónde sale el dinero ═══ -->
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-2"></i>De dónde sale</div>
            <div class="card-body">
                <?php
                $partes = [
                    'Alojamiento' => (float) $a['alojamiento'],
                    'Restaurante' => (float) $a['otros']['restaurante'],
                    'Extras y actividades' => (float) $a['otros']['extras'],
                ];
                $total = array_sum($partes);
                ?>

                <?php if ($total <= 0): ?>
                    <p class="text-muted small mb-0">Sin ingresos en este periodo.</p>
                <?php else: ?>
                    <?php foreach ($partes as $nombre => $valor): ?>
                        <?php if ($valor <= 0) { continue; } ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small">
                                <span><?= $nombre ?></span>
                                <strong><?= $dinero($valor) ?></strong>
                            </div>
                            <div class="progress" style="height: 7px;">
                                <div class="progress-bar" style="width: <?= round($valor / $total * 100) ?>%"></div>
                            </div>
                            <div class="small text-muted"><?= round($valor / $total * 100) ?> % del total</div>
                        </div>
                    <?php endforeach ?>

                    <div class="border-top pt-2 d-flex justify-content-between">
                        <strong>Total</strong>
                        <strong><?= $dinero($total) ?></strong>
                    </div>

                    <?php if ((float) $a['otros']['descuentos'] > 0): ?>
                        <div class="d-flex justify-content-between small text-danger mt-1">
                            <span>Descuentos dados</span>
                            <span>−<?= $dinero((float) $a['otros']['descuentos']) ?></span>
                        </div>
                    <?php endif ?>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart me-2"></i>Ocupación noche a noche</div>
            <div class="card-body">
                <?php $maximo = max(1, (int) $a['cabanas']); ?>
                <div class="d-flex align-items-end gap-1" style="height: 150px;">
                    <?php foreach ($a['por_noche'] as $fecha => $n): ?>
                        <?php $alto = round($n['vendidas'] / $maximo * 100); ?>
                        <div class="flex-fill d-flex flex-column justify-content-end" style="min-width: 3px;"
                             title="<?= date('d/m', strtotime($fecha)) ?>: <?= $n['vendidas'] ?> de <?= $maximo ?>">
                            <div style="height: <?= $alto ?>%; background: var(--bosque, #1f4d36);
                                        border-radius: 2px 2px 0 0; min-height: 2px;"></div>
                        </div>
                    <?php endforeach ?>
                </div>
                <div class="d-flex justify-content-between small text-muted mt-2">
                    <span><?= date('d/m', strtotime($desde)) ?></span>
                    <span>Lleno = <?= $maximo ?> cabañas</span>
                    <span><?= date('d/m', strtotime($hasta)) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Movimiento ═══ -->
<div class="row g-3 mb-4">
    <?php
    $movimiento = [
        ['Llegadas', (string) $a['llegadas'], 'box-arrow-in-right'],
        ['Salidas', (string) $a['salidas'], 'box-arrow-right'],
        ['Estancia media', number_format((float) $a['estancia_media'], 1, ',', '.') . ' noches', 'moon'],
        ['Se reserva con', number_format((float) $reservas['anticipacion_media'], 0, ',', '.') . ' días', 'calendar-check'],
        ['Reservas creadas', (string) $reservas['creadas'], 'plus-circle'],
        ['Canceladas', $reservas['canceladas'] . ' (' . number_format($reservas['cancelacion_pct'], 1, ',', '.') . ' %)', 'x-circle'],
    ];
    ?>
    <?php foreach ($movimiento as [$titulo, $valor, $icono]): ?>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small"><i class="bi bi-<?= $icono ?> me-1"></i><?= $titulo ?></div>
                    <div class="fs-5 fw-semibold"><?= esc($valor) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<!-- ═══ Canales ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Qué deja cada canal</div>
    <div class="card-body py-2">
        <p class="small text-muted mb-0">
            <strong>Lo que importa es la columna «queda»</strong>, no la de producción. Un canal puede
            traer más reservas y dejar menos: se ve aquí y en ningún otro sitio.
        </p>
    </div>
    <?php if ($canales === []): ?>
        <div class="card-body text-muted small border-top">Sin reservas en este periodo.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Canal</th>
                        <th class="text-end">Vendidas</th>
                        <th class="text-end d-none d-md-table-cell">Noches</th>
                        <th class="text-end">Produce</th>
                        <th class="text-end d-none d-lg-table-cell">Comisión</th>
                        <th class="text-end">Queda</th>
                        <th class="text-end d-none d-lg-table-cell">ADR neto</th>
                        <th class="text-end">Cancela</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($canales as $c): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc(ucfirst($c['canal'])) ?></td>
                            <td class="text-end"><?= $c['vendidas'] ?></td>
                            <td class="text-end d-none d-md-table-cell text-muted"><?= $c['noches'] ?></td>
                            <td class="text-end"><?= $dinero($c['produccion']) ?></td>
                            <td class="text-end d-none d-lg-table-cell text-danger">
                                <?= $c['comision'] > 0 ? '−' . $dinero($c['comision']) : '—' ?>
                                <?php if ($c['comision_pct'] > 0): ?>
                                    <div class="small text-muted"><?= number_format($c['comision_pct'], 1, ',', '.') ?> %</div>
                                <?php endif ?>
                            </td>
                            <td class="text-end fw-semibold"><?= $dinero($c['neto']) ?></td>
                            <td class="text-end d-none d-lg-table-cell"><?= $dinero((float) $c['adr_neto']) ?></td>
                            <td class="text-end">
                                <span class="<?= $c['cancelacion_pct'] >= 25 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= number_format($c['cancelacion_pct'], 1, ',', '.') ?> %
                                </span>
                                <?php if ($c['no_shows'] > 0): ?>
                                    <div class="small text-muted"><?= $c['no_shows'] ?> no-show</div>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<div class="row g-4">
    <!-- ═══ Pickup ═══ -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-graph-up-arrow me-2"></i>Lo que ya está vendido para adelante
            </div>
            <div class="card-body py-2">
                <p class="small text-muted mb-0">
                    Es lo único de esta pantalla que mira hacia adelante. Todo lo demás cuenta lo que
                    ya pasó, y con eso no se decide un precio.
                </p>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mes</th>
                            <th class="text-end">Noches</th>
                            <th class="text-end">Ocupación</th>
                            <th class="text-end">Vendido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pickup as $p): ?>
                            <tr>
                                <td><?= esc($mesLargo($p['mes'])) ?></td>
                                <td class="text-end"><?= $p['noches'] ?></td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 90px;">
                                            <div class="progress-bar" style="width: <?= min(100, $p['ocupacion']) ?>%"></div>
                                        </div>
                                        <span class="small"><?= number_format($p['ocupacion'], 1, ',', '.') ?> %</span>
                                    </div>
                                </td>
                                <td class="text-end"><?= $dinero($p['produccion']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══ Cómo nos conocen ═══ -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-signpost me-2"></i>Cómo nos conocieron</div>
            <div class="card-body">
                <?php $totalOrigen = array_sum(array_column($reservas['por_origen'], 'n')); ?>

                <?php if ($totalOrigen === 0): ?>
                    <p class="text-muted small mb-0">
                        Nadie tiene apuntado cómo nos conoció. Es el único dato que dice si lo que se
                        invierte en un canal sirve de algo, y no se puede deducir de ningún otro.
                    </p>
                <?php else: ?>
                    <?php foreach ($reservas['por_origen'] as $o): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= esc($origenes[$o['origen']] ?? 'Sin saber') ?></span>
                                <span class="text-muted"><?= (int) $o['n'] ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= $o['origen'] === 'sin_saber' ? 'bg-secondary' : '' ?>"
                                     style="width: <?= round($o['n'] / $totalOrigen * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
