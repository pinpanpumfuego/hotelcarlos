<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$coloresUnidad = [
    'disponible' => ['success', 'bi-check-circle-fill', 'Disponible'],
    'ocupada'    => ['danger', 'bi-person-fill', 'Ocupada'],
    'limpieza'   => ['warning', 'bi-bucket-fill', 'En limpieza'],
    'bloqueada'  => ['secondary', 'bi-slash-circle-fill', 'Bloqueada'],
];
$puedeVerDinero = puede_alguno(['reportes.ingresos', 'caja.ver']);
$hora           = (int) date('H');
$saludo         = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$nombreCorto    = explode(' ', trim((string) session()->get('usuario_nombre')))[0];
$diasSemana     = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$meses          = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-1"><?= $saludo ?>, <?= esc($nombreCorto) ?></h1>
        <p class="text-muted mb-0">
            <?= $diasSemana[(int) date('w')] ?> <?= date('j') ?> de <?= $meses[(int) date('n')] ?> de <?= date('Y') ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('calendario') ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar3 me-1"></i>Calendario</a>
        <a href="<?= site_url('reservas/nueva') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva reserva</a>
    </div>
</div>

<!-- ═══ Lo que no puede esperar ═══ -->
<?php if (! empty($sobreventas)): ?>
    <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <i class="bi bi-exclamation-octagon me-1"></i>
            <strong><?= count($sobreventas) ?> posible sobreventa.</strong>
            Hay noches vendidas a la vez en tu web y en un portal.
        </div>
        <a href="<?= site_url('canales') ?>" class="btn btn-sm btn-danger">Resolver</a>
    </div>
<?php endif ?>

<?php foreach ($tarifasPendientes as $p): ?>
    <div class="alert alert-<?= $p['urgente'] ? 'warning' : 'info' ?> d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <i class="bi bi-tags me-1"></i>
            <strong>Tarifas de <?= esc($p['anio']) ?>.</strong> <?= esc($p['motivo']) ?>
        </div>
        <a href="<?= site_url('tarifas/agente?anio=' . $p['anio']) ?>" class="btn btn-sm btn-<?= $p['urgente'] ? 'warning' : 'primary' ?>">
            Preparar
        </a>
    </div>
<?php endforeach ?>

<!-- ═══ Indicadores ═══ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card kpi h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icono bg-success-subtle text-success"><i class="bi bi-pie-chart-fill"></i></div>
                <div class="min-w-0">
                    <div class="text-muted small">Ocupación hoy</div>
                    <div class="valor fs-3"><?= esc($ocupacionPct) ?>%</div>
                    <div class="small text-muted"><?= esc($ocupadas) ?> de <?= esc($totalUnidades) ?> cabañas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icono bg-primary-subtle text-primary"><i class="bi bi-box-arrow-in-right"></i></div>
                <div class="min-w-0">
                    <div class="text-muted small">Llegadas hoy</div>
                    <div class="valor fs-3"><?= count($llegadasHoy) ?></div>
                    <div class="small text-muted"><?= count($salidasHoy) ?> salida<?= count($salidasHoy) === 1 ? '' : 's' ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <a href="<?= site_url('registros') ?>" class="text-decoration-none">
            <div class="card kpi h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icono bg-warning-subtle text-warning-emphasis"><i class="bi bi-hourglass-split"></i></div>
                    <div class="min-w-0">
                        <div class="text-muted small">Por confirmar</div>
                        <div class="valor fs-3"><?= count($pendientes) ?></div>
                        <div class="small text-muted">
                            <?= $registrosPorRevisar > 0
                                ? $registrosPorRevisar . ' registro' . ($registrosPorRevisar > 1 ? 's' : '') . ' por revisar'
                                : 'reservas web' ?>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <?php if ($puedeVerDinero): ?>
                    <div class="kpi-icono bg-info-subtle text-info-emphasis"><i class="bi bi-cash-stack"></i></div>
                    <div class="min-w-0">
                        <div class="text-muted small">Cobrado este mes</div>
                        <div class="valor fs-4">$<?= number_format($ingresosMes, 0, ',', '.') ?></div>
                        <div class="small text-muted">pesos colombianos</div>
                    </div>
                <?php else: ?>
                    <div class="kpi-icono bg-warning-subtle text-warning-emphasis"><i class="bi bi-bucket"></i></div>
                    <div class="min-w-0">
                        <div class="text-muted small">Por limpiar</div>
                        <div class="valor fs-3"><?= esc($noVendibles) ?></div>
                        <div class="small text-muted">cabañas</div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- ═══ Ocupación de las últimas dos semanas ═══ -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-graph-up me-2 text-success"></i>Ocupación de las últimas dos semanas
            </div>
            <div class="card-body">
                <canvas id="graficaOcupacion" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- ═══ Estado de las cabañas ═══ -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-door-open me-2 text-success"></i>Cabañas ahora</span>
                <a href="<?= site_url('unidades') ?>" class="small text-decoration-none">Gestionar</a>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($unidades as $u): ?>
                        <?php [$color, $icono, $texto] = $coloresUnidad[$u['estado']] ?? $coloresUnidad['bloqueada']; ?>
                        <div class="d-flex align-items-center gap-2 px-2 py-1 rounded-3" style="background: var(--panel-tenue);">
                            <i class="bi <?= $icono ?> text-<?= $color ?>"></i>
                            <span class="fw-semibold small"><?= esc($u['nombre']) ?></span>
                            <span class="ms-auto small text-muted"><?= $texto ?></span>
                        </div>
                    <?php endforeach ?>
                    <?php if (empty($unidades)): ?>
                        <p class="text-muted text-center py-4 mb-0">Todavía no hay cabañas registradas.</p>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- ═══ Llegadas ═══ -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Llegadas de hoy</div>
            <div class="list-group list-group-flush">
                <?php if (empty($llegadasHoy)): ?>
                    <div class="list-group-item text-muted text-center py-5">
                        <i class="bi bi-moon-stars fs-3 d-block mb-2 opacity-50"></i>
                        No hay llegadas programadas para hoy.
                    </div>
                <?php endif ?>
                <?php foreach ($llegadasHoy as $r): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div>
                            <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($r['codigo']) ?></a>
                            · <?= esc($r['h_nombre']) ?> <?= esc($r['h_apellidos']) ?>
                            <div class="small text-muted"><?= esc($r['unidad_nombre']) ?> · hasta el <?= date('d/m', strtotime($r['fecha_salida'])) ?></div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <?php if ($r['estado'] === 'pendiente'): ?>
                                <span class="badge text-bg-warning">Sin confirmar</span>
                            <?php endif ?>
                            <form action="<?= site_url('reservas/checkin/' . $r['id']) ?>" method="post">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-success">Check-in</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>

    <!-- ═══ Salidas ═══ -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-right me-2 text-secondary"></i>Salidas de hoy</div>
            <div class="list-group list-group-flush">
                <?php if (empty($salidasHoy)): ?>
                    <div class="list-group-item text-muted text-center py-5">
                        <i class="bi bi-check2-circle fs-3 d-block mb-2 opacity-50"></i>
                        No hay salidas pendientes hoy.
                    </div>
                <?php endif ?>
                <?php foreach ($salidasHoy as $r): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div>
                            <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($r['codigo']) ?></a>
                            · <?= esc($r['h_nombre']) ?> <?= esc($r['h_apellidos']) ?>
                            <div class="small text-muted"><?= esc($r['unidad_nombre']) ?></div>
                        </div>
                        <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="btn btn-sm btn-outline-secondary">Revisar cuenta</a>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Reservas web por confirmar ═══ -->
<?php if ($puedeVerDinero): ?>
    <div class="card">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass-split me-2 text-warning"></i>Reservas web por confirmar</div>
        <div class="list-group list-group-flush">
            <?php if (empty($pendientes)): ?>
                <div class="list-group-item text-muted text-center py-5">
                    <i class="bi bi-check2-all fs-3 d-block mb-2 text-success opacity-75"></i>
                    Nada pendiente. ¡Todo al día!
                </div>
            <?php endif ?>
            <?php foreach ($pendientes as $r): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($r['codigo']) ?></a>
                        · <?= esc($r['h_nombre']) ?> <?= esc($r['h_apellidos']) ?>
                        <div class="small text-muted">
                            <?= date('d/m', strtotime($r['fecha_entrada'])) ?>–<?= date('d/m', strtotime($r['fecha_salida'])) ?>
                            · <?= esc($r['unidad_nombre']) ?> · $<?= number_format((float) $r['total'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <form action="<?= site_url('reservas/confirmar/' . $r['id']) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-primary">Confirmar</button>
                    </form>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const lienzo = document.getElementById('graficaOcupacion');
    if (!lienzo || typeof Chart === 'undefined') return;

    const dias   = <?= json_encode(array_map(static fn ($d) => date('d/m', strtotime($d)), array_keys($ocupacionDias))) ?>;
    const datos  = <?= json_encode(array_values($ocupacionDias)) ?>;
    const total  = <?= max(1, (int) $totalUnidades) ?>;

    const degradado = lienzo.getContext('2d').createLinearGradient(0, 0, 0, 190);
    degradado.addColorStop(0, 'rgba(31,77,54,.22)');
    degradado.addColorStop(1, 'rgba(31,77,54,.01)');

    new Chart(lienzo, {
        type: 'line',
        data: {
            labels: dias,
            datasets: [{
                label: 'Cabañas ocupadas',
                data: datos,
                borderColor: '#1f4d36',
                backgroundColor: degradado,
                borderWidth: 2.5,
                fill: true,
                tension: .35,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#1f4d36',
            }],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: {
                    beginAtZero: true, max: total,
                    ticks: { stepSize: 1, color: '#7b8a81', font: { size: 11 } },
                    grid: { color: 'rgba(28,42,35,.06)' },
                },
                x: {
                    ticks: { color: '#7b8a81', font: { size: 11 }, maxRotation: 0, autoSkipPadding: 14 },
                    grid: { display: false },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1c2a23', padding: 10, cornerRadius: 8, displayColors: false,
                    callbacks: {
                        label: (c) => c.parsed.y + ' de ' + total + ' cabañas ('
                            + Math.round(c.parsed.y / total * 100) + '%)',
                    },
                },
            },
        },
    });
}());
</script>

<?= $this->endSection() ?>
