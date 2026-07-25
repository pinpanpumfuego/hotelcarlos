<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Restaurante</h1>
    <a href="<?= site_url('cocina') ?>" class="btn btn-outline-primary">
        <i class="bi bi-fire me-1"></i>Pantalla de cocina
    </a>
</div>

<!-- ── Abrir comanda ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Abrir comanda</div>
    <div class="card-body">
        <form method="post" action="<?= site_url('tpv/abrir') ?>" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label">Mesa o referencia</label>
                <input type="text" name="mesa" class="form-control" placeholder="Mesa 3, Terraza, Muelle...">
            </div>
            <div class="col-md-5">
                <label class="form-label">…o cargar a un huésped alojado</label>
                <select name="reserva_id" class="form-select">
                    <option value="">Sin huésped (cliente de paso)</option>
                    <?php foreach ($alojados as $a): ?>
                        <option value="<?= esc($a['id']) ?>">
                            <?= esc($a['unidad_nombre']) ?> · <?= esc($a['nombre']) ?> <?= esc($a['apellidos']) ?> (<?= esc($a['codigo']) ?>)
                        </option>
                    <?php endforeach ?>
                </select>
                <?php if (empty($alojados)): ?>
                    <div class="form-text">No hay huéspedes alojados ahora mismo.</div>
                <?php endif ?>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100"><i class="bi bi-receipt me-1"></i>Abrir comanda</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Comandas abiertas ── -->
<h2 class="h5 mb-3">Comandas abiertas</h2>
<div class="row g-3 mb-4">
    <?php if (empty($abiertas)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-cup-hot fs-3 d-block mb-2 opacity-50"></i>
                    No hay comandas abiertas.
                </div>
            </div>
        </div>
    <?php endif ?>
    <?php foreach ($abiertas as $c): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= site_url('tpv/comanda/' . $c['id']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="fw-semibold text-dark"><?= esc($c['numero']) ?></span>
                            <span class="fs-5 fw-bold text-dark">$<?= number_format((float) $c['total'], 0, ',', '.') ?></span>
                        </div>
                        <div class="text-muted small mt-1">
                            <?php if ($c['reserva_id'] !== null): ?>
                                <i class="bi bi-house-heart me-1"></i><?= esc($c['unidad_nombre']) ?> ·
                                <?= esc($c['h_nombre']) ?> <?= esc($c['h_apellidos']) ?>
                            <?php else: ?>
                                <i class="bi bi-shop me-1"></i><?= esc($c['mesa'] ?? 'Sin mesa') ?>
                            <?php endif ?>
                            <br><i class="bi bi-clock me-1"></i>Abierta a las <?= date('H:i', strtotime($c['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<!-- ── Historial ── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Comandas cerradas</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Número</th>
                    <th class="d-none d-md-table-cell">Cerrada</th>
                    <th class="d-none d-lg-table-cell">Atendió</th>
                    <th class="d-none d-md-table-cell">Forma de pago</th>
                    <th class="text-end">Total</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Aún no hay comandas cerradas.</td></tr>
                <?php endif ?>
                <?php foreach ($historial as $c): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($c['numero']) ?><?= $c['reserva_codigo'] ? ' <span class="text-muted small">· ' . esc($c['reserva_codigo']) . '</span>' : '' ?></td>
                        <td class="text-muted small d-none d-md-table-cell"><?= $c['cerrada_en'] ? date('d/m H:i', strtotime($c['cerrada_en'])) : '—' ?></td>
                        <td class="d-none d-lg-table-cell"><?= esc($c['usuario_nombre']) ?></td>
                        <td class="d-none d-md-table-cell"><?= esc(\App\Models\ComandaModel::FORMAS_PAGO[$c['forma_pago']] ?? '—') ?></td>
                        <td class="text-end">$<?= number_format((float) $c['total'], 0, ',', '.') ?></td>
                        <td>
                            <span class="badge text-bg-<?= $c['estado'] === 'cobrada' ? 'success' : 'danger' ?>">
                                <?= $c['estado'] === 'cobrada' ? 'Cobrada' : 'Anulada' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
