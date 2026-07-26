<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

$primero    = sprintf('%04d-%02d-01', $anio, $mes);
$diasMes    = (int) date('t', strtotime($primero));
$huecoIni   = (int) date('N', strtotime($primero)) - 1; // lunes = 0
$hoy        = date('Y-m-d');
$anterior   = date('Y-n', strtotime($primero . ' -1 month'));
$siguiente  = date('Y-n', strtotime($primero . ' +1 month'));
[$antAnio, $antMes] = explode('-', $anterior);
[$sigAnio, $sigMes] = explode('-', $siguiente);

$precios = array_column($dias, 'precio');
$minimo  = $precios !== [] ? min($precios) : 0;
$maximo  = $precios !== [] ? max($precios) : 0;
$media   = $precios !== [] ? array_sum($precios) / count($precios) : 0;

$enlace = static fn (array $extra) => site_url('tarifas/calendario') . '?' . http_build_query($extra);

// Temporadas que tocan este mes, para la leyenda
$ultimoDia = date('Y-m-t', strtotime($primero));
$leyenda   = array_filter($temporadas, static fn ($t) => $t['desde'] <= $ultimoDia && $t['hasta'] >= $primero);
?>

<div class="mb-4">
    <a href="<?= site_url('tarifas') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Tarifas
    </a>
    <h1 class="h3 mb-0 mt-1">Calendario de tarifas</h1>
    <p class="text-muted mb-0 mt-1">El precio que pagaría hoy un huésped por cada noche, ya con temporadas y reglas aplicadas.</p>
</div>

<!-- ── Controles ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label mb-1">Alojamiento</label>
                <select name="tipo" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (int) $t['id'] === $tipoId ? 'selected' : '' ?>>
                            <?= esc($t['nombre']) ?> · base $<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1">Mes</label>
                <select name="mes" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($meses as $n => $nombre): ?>
                        <option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= ucfirst($nombre) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Año</label>
                <select name="anio" class="form-select" onchange="this.form.submit()">
                    <?php for ($a = (int) date('Y') - 1; $a <= (int) date('Y') + 3; $a++): ?>
                        <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <a class="btn btn-outline-secondary flex-fill" title="Mes anterior"
                   href="<?= $enlace(['tipo' => $tipoId, 'mes' => (int) $antMes, 'anio' => (int) $antAnio]) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a class="btn btn-outline-secondary flex-fill" title="Mes siguiente"
                   href="<?= $enlace(['tipo' => $tipoId, 'mes' => (int) $sigMes, 'anio' => (int) $sigAnio]) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ── Resumen del mes ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Precio medio</div>
            <div class="valor h4 mb-0">$<?= number_format($media, 0, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Noche más barata</div>
            <div class="valor h4 mb-0 text-success">$<?= number_format($minimo, 0, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Noche más cara</div>
            <div class="valor h4 mb-0"><?= '$' . number_format($maximo, 0, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Ingreso potencial</div>
            <div class="valor h4 mb-0">$<?= number_format(array_sum($precios), 0, ',', '.') ?></div>
            <div class="text-muted small">si se llenara una cabaña todo el mes</div>
        </div></div>
    </div>
</div>

<!-- ── Rejilla ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-calendar3 me-2"></i><?= ucfirst($meses[$mes]) ?> de <?= $anio ?></span>
        <span class="text-muted small fw-normal">Pasa el ratón por una noche para ver su desglose</span>
    </div>
    <div class="card-body p-3">
        <div class="rejilla-cal">
            <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d): ?>
                <div class="cabecera-dia"><?= $d ?></div>
            <?php endforeach ?>

            <?php for ($i = 0; $i < $huecoIni; $i++): ?>
                <div class="celda vacia"></div>
            <?php endfor ?>

            <?php for ($d = 1; $d <= $diasMes; $d++): ?>
                <?php
                $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
                $info  = $dias[$fecha] ?? null;
                if ($info === null) {
                    continue;
                }
                $finde   = in_array((int) date('N', strtotime($fecha)), [5, 6], true);
                $esHoy   = $fecha === $hoy;
                $pasado  = $fecha < $hoy;
                $ocup    = (float) ($info['ocupacion'] ?? 0);
                $delta   = $media > 0 ? ($info['precio'] - $media) / $media : 0;
                $color   = null;
                foreach ($temporadas as $t) {
                    if ($fecha >= $t['desde'] && $fecha <= $t['hasta']) {
                        $color = $t['color'];
                        break;
                    }
                }

                $titulo = $info['dia'] . ' ' . date('d/m', strtotime($fecha)) . "\n";
                $titulo .= 'Base: $' . number_format($info['base'], 0, ',', '.') . "\n";
                foreach ($info['ajustes'] as $a) {
                    $titulo .= $a['concepto'] . ' (' . $a['texto'] . '): '
                        . ($a['importe'] >= 0 ? '+' : '−') . '$' . number_format(abs($a['importe']), 0, ',', '.') . "\n";
                }
                $titulo .= 'Precio: $' . number_format($info['precio'], 0, ',', '.') . "\n";
                $titulo .= 'Ocupación: ' . $ocup . ' %';
                ?>
                <div class="celda <?= $finde ? 'finde' : '' ?> <?= $esHoy ? 'hoy' : '' ?> <?= $pasado ? 'pasado' : '' ?>"
                     title="<?= esc($titulo) ?>">
                    <?php if ($color !== null): ?>
                        <span class="franja" style="background: <?= esc($color) ?>"></span>
                    <?php endif ?>
                    <div class="dia"><?= $d ?></div>
                    <div class="precio <?= $delta > 0.02 ? 'alto' : ($delta < -0.02 ? 'bajo' : '') ?>">
                        <span class="d-none d-md-inline">$<?= number_format($info['precio'], 0, ',', '.') ?></span>
                        <span class="d-md-none">$<?= number_format($info['precio'] / 1000, 0, ',', '.') ?>k</span>
                    </div>
                    <div class="barra-ocup" aria-hidden="true">
                        <span style="width: <?= min(100, $ocup) ?>%"></span>
                    </div>
                    <?php if (! empty($info['ajustes'])): ?>
                        <div class="marcas">
                            <?php foreach (array_slice($info['ajustes'], 0, 3) as $a): ?>
                                <span class="marca <?= $a['importe'] >= 0 ? 'sube' : 'baja' ?>"></span>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endfor ?>
        </div>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap gap-3 align-items-center small text-muted p-3">
        <span><span class="marca sube me-1"></span>Recargo</span>
        <span><span class="marca baja me-1"></span>Descuento</span>
        <span><span class="muestra-ocup me-1"></span>Ocupación de esa noche</span>
        <?php foreach ($leyenda as $t): ?>
            <span><span class="punto-color me-1" style="background: <?= esc($t['color']) ?>"></span><?= esc($t['nombre']) ?></span>
        <?php endforeach ?>
    </div>
</div>

<div class="alert alert-light">
    <i class="bi bi-info-circle me-1"></i>
    Las reglas de <strong>antelación</strong> se calculan respecto a hoy, así que el precio de un día lejano
    puede cambiar a medida que se acerque. El precio de una reserva se congela en el momento de crearla.
</div>

<style>
    .rejilla-cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: .4rem; }
    .cabecera-dia { text-align: center; font-size: .72rem; text-transform: uppercase;
                    letter-spacing: .07em; font-weight: 600; color: var(--tinta-suave); padding-bottom: .2rem; }
    .celda { position: relative; overflow: hidden; min-height: 78px; padding: .4rem .45rem;
             border: 1px solid var(--borde); border-radius: var(--radio-sm);
             background: var(--panel); transition: box-shadow .15s ease, transform .15s ease; }
    .celda:hover { box-shadow: var(--sombra-2); transform: translateY(-1px); z-index: 2; }
    .celda.vacia { background: transparent; border-color: transparent; min-height: 0; }
    .celda.finde { background: var(--panel-tenue); }
    .celda.pasado { opacity: .5; }
    .celda.hoy { border-color: var(--bosque); box-shadow: 0 0 0 2px rgba(31,77,54,.15); }
    .celda .franja { position: absolute; inset: 0 0 auto 0; height: 3px; }
    .celda .dia { font-size: .72rem; color: var(--tinta-suave); font-weight: 600; }
    .celda .precio { font-family: 'Fraunces', serif; font-weight: 600; font-size: .92rem; line-height: 1.25; }
    .celda .precio.alto { color: var(--arena); }
    .celda .precio.bajo { color: #1c7a4f; }
    .barra-ocup { height: 4px; border-radius: 2px; background: var(--borde); margin-top: .3rem; overflow: hidden; }
    .barra-ocup span { display: block; height: 100%; background: var(--bosque-claro); }
    .marcas { display: flex; gap: 3px; margin-top: .3rem; }
    .marca { display: inline-block; width: 7px; height: 7px; border-radius: 50%; }
    .marca.sube { background: var(--arena); }
    .marca.baja { background: #1c7a4f; }
    .muestra-ocup { display: inline-block; width: 18px; height: 4px; border-radius: 2px;
                    background: var(--bosque-claro); vertical-align: middle; }
    .punto-color { display: inline-block; width: 10px; height: 10px; border-radius: 50%; vertical-align: middle; }
    @media (max-width: 575px) {
        .rejilla-cal { gap: .25rem; }
        .celda { min-height: 62px; padding: .3rem; }
        .celda .precio { font-size: .82rem; }
    }
</style>

<?= $this->endSection() ?>
