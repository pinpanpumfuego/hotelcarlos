<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\CuponModel;

[$color, $etiqueta] = CuponModel::estado($cupon);
$descontado = array_sum(array_map(static fn ($u) => (float) $u['descuento'], $usos));
?>

<div class="mb-4">
    <a href="<?= site_url('cupones') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Cupones
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1">
                <span class="codigo-cupon"><?= esc($cupon['codigo']) ?></span>
                <span class="badge text-bg-<?= $color ?> align-middle ms-2"><?= $etiqueta ?></span>
            </h1>
            <p class="text-muted mb-0">
                <?= esc(CuponModel::textoValor($cupon)) ?> ·
                <?= esc(CuponModel::AMBITOS[$cupon['ambito']]) ?>
                <?php if (trim((string) $cupon['descripcion']) !== ''): ?>
                    · <?= esc($cupon['descripcion']) ?>
                <?php endif ?>
            </p>
        </div>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCupon<?= $cupon['id'] ?>">
            <i class="bi bi-pencil me-1"></i>Editar
        </button>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Veces canjeado</div>
            <div class="valor h4 mb-0">
                <?= count($usos) ?><?= $cupon['limite_usos'] !== null ? ' / ' . (int) $cupon['limite_usos'] : '' ?>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Total descontado</div>
            <div class="valor h4 mb-0 text-success">−$<?= number_format($descontado, 0, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Vigencia</div>
            <div class="fw-semibold">
                <?php if ($cupon['desde'] === null && $cupon['hasta'] === null): ?>
                    Sin caducidad
                <?php else: ?>
                    <?= $cupon['desde'] !== null ? date('d/m/Y', strtotime($cupon['desde'])) : '…' ?><br>
                    <span class="text-muted small">hasta <?= $cupon['hasta'] !== null ? date('d/m/Y', strtotime($cupon['hasta'])) : '…' ?></span>
                <?php endif ?>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Canales</div>
            <div class="d-flex gap-1 flex-wrap mt-1">
                <?php foreach (['en_web' => 'Web', 'en_recepcion' => 'Recepción', 'en_tpv' => 'TPV'] as $campo => $texto): ?>
                    <span class="badge text-bg-<?= (int) $cupon[$campo] === 1 ? 'primary' : 'light' ?> <?= (int) $cupon[$campo] === 1 ? '' : 'text-muted' ?>">
                        <?= $texto ?>
                    </span>
                <?php endforeach ?>
            </div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2"></i>Canjes</div>
    <?php if ($usos === []): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
            Este cupón todavía no se ha canjeado.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Dónde</th>
                        <th class="d-none d-md-table-cell">Cliente</th>
                        <th class="text-end d-none d-lg-table-cell">Base</th>
                        <th class="text-end">Descuento</th>
                        <th class="d-none d-lg-table-cell">Registró</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usos as $u): ?>
                        <tr>
                            <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['reserva_codigo'] !== null): ?>
                                    <a href="<?= site_url('reservas/ver/' . $u['reserva_id']) ?>"><?= esc($u['reserva_codigo']) ?></a>
                                <?php elseif ($u['comanda_numero'] !== null): ?>
                                    <?= esc($u['comanda_numero']) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                                <div class="text-muted small"><?= esc(CuponModel::CANALES[$u['canal']] ?? $u['canal']) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?= $u['huesped_nombre'] !== null
                                    ? esc($u['huesped_nombre'] . ' ' . $u['huesped_apellidos'])
                                    : '<span class="text-muted">Cliente de paso</span>' ?>
                            </td>
                            <td class="text-end d-none d-lg-table-cell text-muted">$<?= number_format((float) $u['base'], 0, ',', '.') ?></td>
                            <td class="text-end fw-semibold text-success">−$<?= number_format((float) $u['descuento'], 0, ',', '.') ?></td>
                            <td class="d-none d-lg-table-cell text-muted small"><?= esc($u['usuario_nombre'] ?? 'Web') ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<?= view('cupones/_modal', [
    'id'     => 'modalCupon' . $cupon['id'],
    'accion' => site_url('cupones/actualizar/' . $cupon['id']),
    'cupon'  => $cupon,
]) ?>

<style>
    .codigo-cupon {
        font-family: 'Inter', monospace; font-weight: 700; letter-spacing: .04em;
        color: var(--bosque); background: var(--panel-tenue);
        border: 1px dashed var(--borde-fuerte); border-radius: var(--radio-sm);
        padding: .1rem .5rem; display: inline-block;
    }
</style>

<?= $this->endSection() ?>
