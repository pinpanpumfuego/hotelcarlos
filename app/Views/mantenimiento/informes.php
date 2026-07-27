<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$total  = array_sum($origenes);
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Informes de mantenimiento</h1>
        <p class="text-muted mb-0">Lo que sirve para decidir si algo se arregla otra vez o se cambia.</p>
    </div>
    <a href="<?= site_url('mantenimiento') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Al tablero
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= esc($desde) ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= esc($hasta) ?>">
            </div>
            <div class="col-sm-4 col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>Ver</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Lo grande ═══ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Averías</div>
                <div class="fs-3 fw-semibold text-danger"><?= $resumen['correctiva'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Revisiones programadas</div>
                <div class="fs-3 fw-semibold text-success"><?= $resumen['preventiva'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Fuera de plazo</div>
                <div class="fs-3 fw-semibold <?= $resumen['tarde'] > 0 ? 'text-warning' : '' ?>"><?= $resumen['tarde'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Cabañas sin vender</div>
                <div class="fs-3 fw-semibold"><?= number_format($parados['horas'] / 24, 1, ',', '.') ?></div>
                <div class="small text-muted">días en total</div>
            </div>
        </div>
    </div>
</div>

<?php if ($resumen['correctiva'] > 0 && $resumen['preventiva'] < $resumen['correctiva'] / 2): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-fire fs-5"></i>
        <div>
            <strong>Se está apagando fuegos.</strong>
            Hay <?= $resumen['correctiva'] ?> averías frente a <?= $resumen['preventiva'] ?> revisiones
            programadas. Donde el preventivo funciona, lo programado pesa más que lo roto.
            <a href="<?= site_url('preventivos') ?>">Mira el plan preventivo.</a>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <!-- ═══ De dónde salen ═══ -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-signpost-split me-2"></i>Quién las encuentra</div>
            <div class="card-body">
                <?php if ($total === 0): ?>
                    <p class="text-muted small mb-0">Nada en este periodo.</p>
                <?php else: ?>
                    <?php foreach ($etiquetas as $clave => $nombre): ?>
                        <?php $n = $origenes[$clave] ?? 0; ?>
                        <?php if ($n === 0) { continue; } ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= esc($nombre) ?></span>
                                <span class="text-muted"><?= $n ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= $clave === 'huesped' ? 'bg-danger' : ($clave === 'preventivo' ? 'bg-success' : '') ?>"
                                     style="width: <?= round($n / $total * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach ?>

                    <?php if (($origenes['huesped'] ?? 0) > 0): ?>
                        <div class="alert alert-light border small mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong><?= $origenes['huesped'] ?></strong> las encontró un huésped antes que nosotros.
                            Esa es la cifra que dice si el preventivo funciona, no el total de órdenes.
                        </div>
                    <?php endif ?>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- ═══ Fin de vida ═══ -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass-bottom me-2"></i>Se acercan al final de su vida</div>
            <?php if ($vida === []): ?>
                <div class="card-body text-muted small">
                    Ninguno, o falta poner la fecha de compra y la vida útil en las fichas.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($vida as $a): ?>
                        <a href="<?= site_url('activos/ver/' . $a['id']) ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <span class="fw-semibold"><?= esc($a['nombre']) ?></span>
                                <div class="small text-muted"><?= esc($categorias[$a['categoria']] ?? $a['categoria']) ?></div>
                            </div>
                            <span class="badge text-bg-<?= $a['meses_restantes'] <= 0 ? 'danger' : 'warning' ?> text-nowrap">
                                <?= $a['meses_restantes'] <= 0
                                    ? 'Pasado ' . abs($a['meses_restantes']) . ' meses'
                                    : 'Quedan ' . $a['meses_restantes'] . ' meses' ?>
                            </span>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<!-- ═══ Equipo por equipo ═══ -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-tools me-2"></i>Qué se rompe, y cuánto cuesta
    </div>
    <?php if ($activos === []): ?>
        <div class="card-body text-muted small">
            Ninguna avería con equipo asociado en este periodo. Si se reportan sin decir qué equipo
            falló, esta tabla se queda vacía: es la razón de las etiquetas QR.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Equipo</th>
                        <th class="text-end">Averías</th>
                        <th class="text-end d-none d-md-table-cell">Coste</th>
                        <th class="text-end d-none d-lg-table-cell">Costó</th>
                        <th>Qué hacer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activos as $a): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('activos/ver/' . $a['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($a['nombre']) ?>
                                </a>
                                <div class="small text-muted font-monospace"><?= esc($a['codigo']) ?></div>
                            </td>
                            <td class="text-end">
                                <?= $a['averias'] ?>
                                <?php if ($a['bloqueos'] > 0): ?>
                                    <div class="small text-danger"><?= $a['bloqueos'] ?> con cabaña parada</div>
                                <?php endif ?>
                            </td>
                            <td class="text-end d-none d-md-table-cell"><?= $dinero($a['costo']) ?></td>
                            <td class="text-end d-none d-lg-table-cell text-muted">
                                <?= $a['valor_compra'] !== null ? $dinero((float) $a['valor_compra']) : '—' ?>
                            </td>
                            <td>
                                <span class="small text-<?= $a['veredicto']['nivel'] ?>">
                                    <?= esc($a['veredicto']['texto']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<!-- ═══ Tiempo parado ═══ -->
<?php if ($parados['detalle'] !== []): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-door-closed me-2"></i>Cabañas que no se pudieron vender
            <span class="badge text-bg-secondary ms-1"><?= $parados['ordenes'] ?></span>
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-0">
                Es el número que traduce el mantenimiento a dinero: una cabaña bloqueada tres días
                en temporada son tres noches que no se vendieron.
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Avería</th>
                        <th class="d-none d-md-table-cell">Cabaña</th>
                        <th>Desde</th>
                        <th class="text-end">Parada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parados['detalle'] as $p): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('mantenimiento/ver/' . $p['id']) ?>" class="text-decoration-none">
                                    <?= esc($p['titulo']) ?>
                                </a>
                            </td>
                            <td class="d-none d-md-table-cell small text-muted"><?= esc($p['unidad_nombre']) ?: '—' ?></td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                            <td class="text-end">
                                <?= number_format($p['horas'] / 24, 1, ',', '.') ?> días
                                <?php if ($p['abierta']): ?>
                                    <span class="badge text-bg-danger ms-1">Sigue parada</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
