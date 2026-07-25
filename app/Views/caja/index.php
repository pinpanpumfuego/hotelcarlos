<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4">Caja de recepción</h1>

<?php if ($turno === null): ?>
    <!-- ═══ Sin turno: abrir ═══ -->
    <div class="card border-0 shadow-sm mb-4" style="max-width: 520px;">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-unlock me-2 text-success"></i>Abrir turno de caja</div>
        <div class="card-body">
            <p class="text-muted small">Cuenta el efectivo con el que empieza el turno (la base o fondo de caja) y regístralo.</p>
            <form method="post" action="<?= site_url('caja/abrir') ?>" class="d-flex gap-2 flex-wrap">
                <?= csrf_field() ?>
                <div class="input-group" style="max-width: 260px;">
                    <span class="input-group-text">$</span>
                    <input type="number" name="base_inicial" class="form-control" min="0" step="1000" value="0" required>
                    <span class="input-group-text">COP</span>
                </div>
                <button class="btn btn-primary"><i class="bi bi-unlock me-1"></i>Abrir turno</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- ═══ Turno abierto ═══ -->
    <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="bi bi-unlock me-1"></i>
            Turno abierto por <strong><?= esc($turno['usuario_nombre']) ?></strong>
            desde el <?= date('d/m/Y H:i', strtotime($turno['apertura'])) ?>
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Base inicial</div>
                <div class="fs-4 fw-bold">$<?= number_format((float) $turno['base_inicial'], 0, ',', '.') ?></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Ingresos en efectivo</div>
                <div class="fs-4 fw-bold text-success">$<?= number_format($totales['ingresos'], 0, ',', '.') ?></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Egresos</div>
                <div class="fs-4 fw-bold text-danger">$<?= number_format($totales['egresos'], 0, ',', '.') ?></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-success"><div class="card-body">
                <div class="text-muted small">Debe haber en caja</div>
                <div class="fs-4 fw-bold">$<?= number_format($esperado, 0, ',', '.') ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-2"></i>Movimientos del turno</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Hora</th><th>Concepto</th><th class="text-end">Ingreso</th><th class="text-end">Egreso</th><th>Quién</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lista)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Sin movimientos todavía. Los pagos en efectivo del folio entran aquí solos.</td></tr>
                            <?php endif ?>
                            <?php foreach ($lista as $m): ?>
                                <tr>
                                    <td class="text-muted small"><?= date('H:i', strtotime($m['created_at'])) ?></td>
                                    <td><?= esc($m['concepto']) ?></td>
                                    <td class="text-end text-success"><?= $m['tipo'] === 'ingreso' ? '$' . number_format((float) $m['valor'], 0, ',', '.') : '' ?></td>
                                    <td class="text-end text-danger"><?= $m['tipo'] === 'egreso' ? '$' . number_format((float) $m['valor'], 0, ',', '.') : '' ?></td>
                                    <td class="text-muted small"><?= esc($m['usuario_nombre']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-slash-minus me-2"></i>Registrar movimiento manual</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('caja/movimiento') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-12">
                                <input type="text" name="concepto" class="form-control" placeholder="Concepto (compra insumos, cambio, propina...)" required>
                            </div>
                            <div class="col-5">
                                <select name="tipo" class="form-select">
                                    <option value="ingreso">Ingreso</option>
                                    <option value="egreso">Egreso</option>
                                </select>
                            </div>
                            <div class="col-7">
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="valor" class="form-control" min="1" step="any" required>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary mt-3 w-100"><i class="bi bi-plus-lg me-1"></i>Registrar</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm border-top border-4 border-warning">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-lock me-2"></i>Cerrar turno (arqueo)</div>
                <div class="card-body">
                    <p class="text-muted small">Cuenta todo el efectivo de la caja y escribe el total. El sistema calcula la diferencia con lo que debería haber.</p>
                    <form method="post" action="<?= site_url('caja/cerrar') ?>"
                          onsubmit="return confirm('¿Cerrar el turno con el conteo indicado? Esta acción no se puede deshacer.');">
                        <?= csrf_field() ?>
                        <div class="input-group mb-2">
                            <span class="input-group-text">$</span>
                            <input type="number" name="efectivo_contado" class="form-control" min="0" step="any" required placeholder="Efectivo contado">
                            <span class="input-group-text">COP</span>
                        </div>
                        <textarea name="notas" class="form-control mb-3" rows="2" placeholder="Notas del cierre (opcional)"></textarea>
                        <button class="btn btn-warning w-100"><i class="bi bi-lock me-1"></i>Cerrar turno</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>

<!-- ═══ Historial ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Turnos anteriores</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Abierto</th><th>Cerrado</th><th>Quién</th><th class="text-end">Base</th><th class="text-end">Contado</th><th class="text-end">Diferencia</th><th>Notas</th></tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay turnos cerrados.</td></tr>
                <?php endif ?>
                <?php foreach ($historial as $t): ?>
                    <?php $dif = (float) $t['diferencia']; ?>
                    <tr>
                        <td class="text-muted small"><?= date('d/m H:i', strtotime($t['apertura'])) ?></td>
                        <td class="text-muted small"><?= date('d/m H:i', strtotime($t['cierre'])) ?></td>
                        <td><?= esc($t['usuario_nombre']) ?></td>
                        <td class="text-end">$<?= number_format((float) $t['base_inicial'], 0, ',', '.') ?></td>
                        <td class="text-end">$<?= number_format((float) $t['efectivo_contado'], 0, ',', '.') ?></td>
                        <td class="text-end">
                            <?php if (abs($dif) < 0.01): ?>
                                <span class="badge text-bg-success">Cuadró</span>
                            <?php elseif ($dif < 0): ?>
                                <span class="badge text-bg-danger">Faltó $<?= number_format(abs($dif), 0, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-info">Sobró $<?= number_format($dif, 0, ',', '.') ?></span>
                            <?php endif ?>
                        </td>
                        <td class="text-muted small"><?= esc($t['notas'] ?? '—') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
