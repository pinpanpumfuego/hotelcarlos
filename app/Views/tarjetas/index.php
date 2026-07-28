<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$colorEstado = ['activa' => 'success', 'congelada' => 'warning', 'anulada' => 'secondary'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Tarjetas de saldo</h1>
        <p class="text-muted mb-0">Personalizadas, con QR y recargables.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (puede('tarjetas.gestionar')): ?>
            <a href="<?= site_url('tarjetas/tipos') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i>Modalidades
            </a>
            <a href="<?= site_url('tarjetas/nueva') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nueva tarjeta
            </a>
        <?php endif ?>
    </div>
</div>

<?= view('partes/errores') ?>

<?php // El saldo en circulación es una DEUDA del hotel, no un ingreso: el
      // ingreso ocurre cuando se gasta. Va dicho donde se lee. ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Saldo en circulación</div>
                <div class="fs-3 fw-semibold"><?= $dinero($circulacion) ?></div>
                <div class="small text-muted">es una deuda, no un ingreso</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Tarjetas</div>
                <div class="fs-3 fw-semibold"><?= count($tarjetas) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Esperan recarga mensual</div>
                <div class="fs-3 fw-semibold"><?= count($pendientes) ?></div>
                <?php if ($pendientes !== [] && puede('tarjetas.cargar')): ?>
                    <form method="post" action="<?= site_url('tarjetas/recarga-mensual') ?>" class="mt-1"
                          onsubmit="return confirm('¿Recargar <?= count($pendientes) ?> tarjeta(s)?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-primary">Recargarlas todas</button>
                    </form>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?php if ($descuadres !== []): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-octagon me-1"></i>Hay saldos que no cuadran con su historial.</strong>
        <div class="small mt-1">
            El saldo se guarda para poder descontarlo de golpe al cobrar, pero tiene que coincidir
            siempre con la suma de sus movimientos. Que no coincida significa que algo se escribió
            fuera del sistema.
        </div>
        <ul class="mb-0 mt-2 small">
            <?php foreach ($descuadres as $d): ?>
                <li>
                    <a href="<?= site_url('tarjetas/ver/' . $d['tarjeta']['id']) ?>"><?= esc($d['tarjeta']['codigo']) ?></a>
                    · guardado <?= $dinero($d['guardado']) ?> · según movimientos <?= $dinero($d['calculado']) ?>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="search" name="buscar" class="form-control" value="<?= esc($filtros['buscar']) ?>"
                       placeholder="Código o titular">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Modalidad</label>
                <select name="tipo_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filtros['tipo_id'] === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= esc($t['nombre']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $e => $etiqueta): ?>
                        <option value="<?= $e ?>" <?= $filtros['estado'] === $e ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>
</div>

<?php if ($tarjetas === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-credit-card-2-front fs-1 d-block mb-3"></i>
            Ninguna tarjeta todavía.
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tarjeta</th>
                        <th class="d-none d-md-table-cell">Modalidad</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-end d-none d-lg-table-cell">Descuento</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tarjetas as $t): ?>
                        <?php $desc = $t['descuento_pct'] !== null ? (float) $t['descuento_pct'] : (float) $t['tipo_descuento']; ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('tarjetas/ver/' . $t['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($t['titular']) ?>
                                </a>
                                <div class="small text-muted font-monospace"><?= esc($t['codigo']) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge text-bg-<?= esc($t['color']) ?>"><?= esc($t['tipo_nombre']) ?></span>
                            </td>
                            <td class="text-end fw-semibold"><?= $dinero((float) $t['saldo']) ?></td>
                            <td class="text-end d-none d-lg-table-cell">
                                <?= $desc > 0 ? rtrim(rtrim(number_format($desc, 2, ',', '.'), '0'), ',') . ' %' : '—' ?>
                                <?php if ($t['descuento_pct'] !== null): ?>
                                    <div class="small text-muted">propio</div>
                                <?php endif ?>
                            </td>
                            <td><span class="badge text-bg-<?= $colorEstado[$t['estado']] ?>"><?= esc($estados[$t['estado']]) ?></span></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
