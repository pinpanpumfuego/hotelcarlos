<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$coloresUnidad = ['disponible' => 'success', 'ocupada' => 'danger', 'limpieza' => 'warning', 'bloqueada' => 'secondary'];
$puedeVerDinero = in_array(session()->get('usuario_rol'), ['gerencia', 'recepcion'], true);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Panel de control</h1>
    <a href="<?= site_url('reservas/nueva') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva reserva</a>
</div>

<!-- ── Indicadores ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card kpi border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icono bg-success-subtle text-success"><i class="bi bi-pie-chart"></i></div>
                <div>
                    <div class="text-muted small">Ocupación hoy</div>
                    <div class="fs-3 fw-bold"><?= esc($ocupacionPct) ?>%</div>
                    <div class="small text-muted"><?= esc($ocupadas) ?> de <?= esc($totalUnidades) ?> cabañas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icono bg-primary-subtle text-primary"><i class="bi bi-box-arrow-in-right"></i></div>
                <div>
                    <div class="text-muted small">Llegadas hoy</div>
                    <div class="fs-3 fw-bold"><?= count($llegadasHoy) ?></div>
                    <div class="small text-muted">salidas: <?= count($salidasHoy) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icono bg-warning-subtle text-warning-emphasis"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="text-muted small">Por confirmar</div>
                    <div class="fs-3 fw-bold"><?= count($pendientes) ?></div>
                    <div class="small text-muted">reservas web</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <?php if ($puedeVerDinero): ?>
                    <div class="kpi-icono bg-info-subtle text-info-emphasis"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="text-muted small">Cobrado este mes</div>
                        <div class="fs-4 fw-bold">$<?= number_format($ingresosMes, 0, ',', '.') ?></div>
                        <div class="small text-muted">COP</div>
                    </div>
                <?php else: ?>
                    <div class="kpi-icono bg-warning-subtle text-warning-emphasis"><i class="bi bi-bucket"></i></div>
                    <div>
                        <div class="text-muted small">Por limpiar</div>
                        <div class="fs-3 fw-bold"><?= esc($noVendibles) ?></div>
                        <div class="small text-muted">cabañas</div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- ── Llegadas de hoy ── -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Llegadas de hoy</div>
            <div class="list-group list-group-flush">
                <?php if (empty($llegadasHoy)): ?>
                    <div class="list-group-item text-muted text-center py-4">No hay llegadas programadas para hoy.</div>
                <?php endif ?>
                <?php foreach ($llegadasHoy as $r): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div>
                            <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($r['codigo']) ?></a>
                            · <?= esc($r['h_nombre']) ?> <?= esc($r['h_apellidos']) ?>
                            <div class="small text-muted"><?= esc($r['unidad_nombre']) ?> · hasta <?= date('d/m', strtotime($r['fecha_salida'])) ?></div>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($r['estado'] === 'pendiente'): ?>
                                <span class="badge text-bg-warning align-self-center">Sin confirmar</span>
                            <?php endif ?>
                            <form action="<?= site_url('reservas/checkin/' . $r['id']) ?>" method="post">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-success" <?= $r['estado'] === 'pendiente' ? 'title="Confirma la reserva primero desde su ficha"' : '' ?>>
                                    Check-in
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>

    <!-- ── Salidas de hoy ── -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-right me-2 text-secondary"></i>Salidas de hoy</div>
            <div class="list-group list-group-flush">
                <?php if (empty($salidasHoy)): ?>
                    <div class="list-group-item text-muted text-center py-4">No hay salidas pendientes hoy.</div>
                <?php endif ?>
                <?php foreach ($salidasHoy as $r): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div>
                            <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($r['codigo']) ?></a>
                            · <?= esc($r['h_nombre']) ?> <?= esc($r['h_apellidos']) ?>
                            <div class="small text-muted"><?= esc($r['unidad_nombre']) ?></div>
                        </div>
                        <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="btn btn-sm btn-outline-secondary">Revisar folio y salida</a>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ── Reservas por confirmar ── -->
    <?php if ($puedeVerDinero): ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass-split me-2 text-warning"></i>Reservas web por confirmar</div>
            <div class="list-group list-group-flush">
                <?php if (empty($pendientes)): ?>
                    <div class="list-group-item text-muted text-center py-4">Nada pendiente. ¡Todo al día!</div>
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
    </div>

    <?php endif ?>

    <!-- ── Estado de cabañas ── -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-door-open me-2 text-success"></i>Cabañas ahora</span>
                <a href="<?= site_url('unidades') ?>" class="small text-decoration-none">Gestionar</a>
            </div>
            <div class="card-body d-flex flex-wrap gap-2 align-content-start">
                <?php foreach ($unidades as $u): ?>
                    <span class="badge text-bg-<?= $coloresUnidad[$u['estado']] ?? 'secondary' ?> p-2 fs-6 fw-normal">
                        <?= esc($u['nombre']) ?>
                    </span>
                <?php endforeach ?>
            </div>
            <div class="card-footer bg-white small text-muted d-flex gap-3 flex-wrap">
                <span><span class="badge text-bg-success">&nbsp;</span> Disponible</span>
                <span><span class="badge text-bg-danger">&nbsp;</span> Ocupada</span>
                <span><span class="badge text-bg-warning">&nbsp;</span> Limpieza</span>
                <span><span class="badge text-bg-secondary">&nbsp;</span> Bloqueada</span>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
