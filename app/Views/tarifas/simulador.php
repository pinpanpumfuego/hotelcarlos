<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="mb-4">
    <a href="<?= site_url('tarifas') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Tarifas
    </a>
    <h1 class="h3 mb-0 mt-1">Simulador de precios</h1>
    <p class="text-muted mb-0 mt-1">
        Comprueba qué cobraría el sistema por una estancia concreta, y de dónde sale cada peso.
    </p>
</div>

<div class="row g-4">
    <!-- ── Parámetros ── -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2"></i>La estancia</div>
            <div class="card-body p-4">
                <form method="get">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alojamiento</label>
                        <select name="tipo" class="form-select">
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (int) $t['id'] === $tipoId ? 'selected' : '' ?>>
                                    <?= esc($t['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Llegada</label>
                        <input type="date" name="entrada" class="form-control" value="<?= esc($entrada) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Salida</label>
                        <input type="date" name="salida" class="form-control" value="<?= esc($salida) ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Adultos</label>
                            <input type="number" name="adultos" class="form-control" min="1" max="20" value="<?= esc($adultos) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Niños</label>
                            <input type="number" name="ninos" class="form-control" min="0" max="20" value="<?= esc($ninos) ?>">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-calculator me-1"></i>Calcular</button>
                </form>
            </div>
        </div>

        <div class="alert alert-light mt-3 small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            El simulador usa la ocupación real de esas fechas. Si entran reservas nuevas, el precio puede subir.
        </div>
    </div>

    <!-- ── Resultado ── -->
    <div class="col-lg-8">
        <?php if ($aviso !== null): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($aviso) ?></div>
        <?php elseif ($cotizacion === null || $cotizacion['tipo'] === null): ?>
            <div class="alert alert-warning">Ese tipo de alojamiento ya no existe.</div>
        <?php else: ?>
            <?php $c = $cotizacion; ?>

            <div class="card border-0 shadow-sm mb-4 tarjeta-total">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-muted small">Total de la estancia</div>
                            <div class="total">$<?= number_format($c['total'], 0, ',', '.') ?></div>
                            <div class="text-muted small mt-1">
                                <?= esc($c['tipo']['nombre']) ?> ·
                                <?= $c['noches_total'] ?> noche<?= $c['noches_total'] > 1 ? 's' : '' ?> ·
                                <?= $adultos + $ninos ?> persona<?= $adultos + $ninos > 1 ? 's' : '' ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Media por noche</div>
                            <div class="h4 mb-0 fuente-titulo">$<?= number_format($c['media_noche'], 0, ',', '.') ?></div>
                            <div class="text-muted small">
                                base $<?= number_format((float) $c['tipo']['tarifa_base'], 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-2"></i>Noche a noche</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Noche</th>
                                <th>Cómo se forma</th>
                                <th class="text-center d-none d-md-table-cell">Ocupación</th>
                                <th class="text-end">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($c['noches'] as $n): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= date('d/m/Y', strtotime($n['fecha'])) ?></div>
                                        <div class="text-muted small"><?= esc($n['dia']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-muted small mb-1">
                                            Base $<?= number_format($n['base'], 0, ',', '.') ?>
                                        </div>
                                        <?php if (empty($n['ajustes'])): ?>
                                            <span class="text-muted small">Sin ajustes</span>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($n['ajustes'] as $a): ?>
                                                    <div class="ajuste">
                                                        <span class="badge <?= $a['importe'] >= 0 ? 'text-bg-warning' : 'text-bg-success' ?>">
                                                            <?= $a['importe'] >= 0 ? '+' : '−' ?>$<?= number_format(abs($a['importe']), 0, ',', '.') ?>
                                                        </span>
                                                        <span class="small"><?= esc($a['concepto']) ?></span>
                                                        <span class="text-muted small">(<?= esc($a['texto']) ?>)</span>
                                                    </div>
                                                <?php endforeach ?>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php if ($n['ocupacion'] !== null): ?>
                                            <span class="text-muted small"><?= $n['ocupacion'] ?> %</span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        $<?= number_format($n['precio'], 0, ',', '.') ?>
                                        <?php if ($n['tope'] !== null): ?>
                                            <div><span class="badge text-bg-secondary">tope <?= $n['tope'] === 'minimo' ? 'mínimo' : 'máximo' ?></span></div>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="3" class="fw-semibold">Alojamiento</td>
                                <td class="text-end fw-semibold">$<?= number_format($c['alojamiento'], 0, ',', '.') ?></td>
                            </tr>
                            <?php foreach ($c['suplementos'] as $s): ?>
                                <tr>
                                    <td colspan="3">
                                        <?= esc($s['concepto']) ?>
                                        <span class="text-muted small">· <?= esc($s['detalle']) ?></span>
                                    </td>
                                    <td class="text-end">$<?= number_format($s['importe'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach ?>
                            <tr class="table-light">
                                <td colspan="3" class="fw-semibold">Total</td>
                                <td class="text-end fw-bold fs-5">$<?= number_format($c['total'], 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <?php if ((int) $c['tipo']['capacidad'] < $adultos + $ninos): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Ojo: <?= esc($c['tipo']['nombre']) ?> admite <?= (int) $c['tipo']['capacidad'] ?> personas y estás simulando
                    <?= $adultos + $ninos ?>. El motor de reservas no ofrecerá esta opción.
                </div>
            <?php endif ?>
        <?php endif ?>
    </div>
</div>

<style>
    .tarjeta-total { background: linear-gradient(135deg, #ffffff 0%, var(--panel-tenue) 100%); }
    .tarjeta-total .total { font-family: 'Fraunces', serif; font-weight: 600; font-size: 2.2rem; line-height: 1.1; color: var(--bosque); }
    .ajuste { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
</style>

<?= $this->endSection() ?>
