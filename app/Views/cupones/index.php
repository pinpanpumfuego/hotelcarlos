<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php use App\Models\CuponModel; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Cupones de descuento</h1>
        <p class="text-muted mb-0">
            Códigos que rebajan la cuenta: en la reserva en línea, en recepción o en el TPV del restaurante.
        </p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCupon">
        <i class="bi bi-plus-lg me-1"></i>Nuevo cupón
    </button>
</div>

<?= view('partes/errores') ?>

<div class="card border-0 shadow-sm mb-4">
    <?php if ($cupones === []): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-ticket-perforated fs-3 d-block mb-2 opacity-50"></i>
            Todavía no hay cupones. Un ejemplo típico: <span class="fw-semibold">BIENVENIDA10</span>,
            10 % sobre el alojamiento, válido solo en la web.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descuento</th>
                        <th class="d-none d-md-table-cell">Ámbito</th>
                        <th class="d-none d-lg-table-cell">Vigencia</th>
                        <th class="text-center">Usos</th>
                        <th class="d-none d-lg-table-cell text-end">Descontado</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cupones as $c): ?>
                        <?php [$color, $etiqueta] = CuponModel::estado($c); ?>
                        <tr class="<?= (int) $c['activo'] !== 1 ? 'opacity-50' : '' ?>">
                            <td>
                                <a href="<?= site_url('cupones/ver/' . $c['id']) ?>" class="codigo-cupon text-decoration-none">
                                    <?= esc($c['codigo']) ?>
                                </a>
                                <?php if (trim((string) $c['descripcion']) !== ''): ?>
                                    <div class="text-muted small"><?= esc($c['descripcion']) ?></div>
                                <?php endif ?>
                                <div class="mt-1 d-flex gap-1 flex-wrap">
                                    <?php foreach (['en_web' => 'Web', 'en_recepcion' => 'Recepción', 'en_tpv' => 'TPV'] as $campo => $texto): ?>
                                        <?php if ((int) $c[$campo] === 1): ?>
                                            <span class="badge text-bg-light text-muted"><?= $texto ?></span>
                                        <?php endif ?>
                                    <?php endforeach ?>
                                </div>
                            </td>
                            <td class="fw-semibold"><?= esc(CuponModel::textoValor($c)) ?></td>
                            <td class="d-none d-md-table-cell text-muted small"><?= esc(CuponModel::AMBITOS[$c['ambito']]) ?></td>
                            <td class="d-none d-lg-table-cell text-muted small">
                                <?php if ($c['desde'] === null && $c['hasta'] === null): ?>
                                    Sin caducidad
                                <?php else: ?>
                                    <?= $c['desde'] !== null ? date('d/m/Y', strtotime($c['desde'])) : '…' ?>
                                    → <?= $c['hasta'] !== null ? date('d/m/Y', strtotime($c['hasta'])) : '…' ?>
                                <?php endif ?>
                            </td>
                            <td class="text-center">
                                <?= (int) $c['canjes'] ?><?= $c['limite_usos'] !== null ? ' / ' . (int) $c['limite_usos'] : '' ?>
                            </td>
                            <td class="d-none d-lg-table-cell text-end text-success">
                                <?= (float) $c['descontado'] > 0 ? '−$' . number_format((float) $c['descontado'], 0, ',', '.') : '—' ?>
                            </td>
                            <td><span class="badge text-bg-<?= $color ?>"><?= $etiqueta ?></span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('cupones/ver/' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver canjes">
                                        <i class="bi bi-list-check"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary" title="Editar"
                                            data-bs-toggle="modal" data-bs-target="#modalCupon<?= $c['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= site_url('cupones/estado/' . $c['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" title="<?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?>">
                                            <i class="bi <?= (int) $c['activo'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= site_url('cupones/eliminar/' . $c['id']) ?>"
                                          onsubmit="return confirm('¿Eliminar el cupón <?= esc($c['codigo'], 'js') ?>?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<div class="alert alert-light">
    <i class="bi bi-info-circle me-1"></i>
    Los cupones <strong>descuentan sobre la cuenta</strong>: no son dinero prepagado. Para eso están los
    <a href="<?= site_url('bonos') ?>">bonos regalo</a>. Un cupón ya canjeado no se borra: se desactiva,
    para no perder el historial de lo que se descontó.
</div>

<?= view('cupones/_modal', ['id' => 'modalCupon', 'accion' => site_url('cupones/guardar'), 'cupon' => null]) ?>
<?php foreach ($cupones as $c): ?>
    <?= view('cupones/_modal', [
        'id'     => 'modalCupon' . $c['id'],
        'accion' => site_url('cupones/actualizar/' . $c['id']),
        'cupon'  => $c,
    ]) ?>
<?php endforeach ?>

<style>
    .codigo-cupon {
        font-family: 'Inter', monospace; font-weight: 700; letter-spacing: .04em;
        color: var(--bosque); background: var(--panel-tenue);
        border: 1px dashed var(--borde-fuerte); border-radius: var(--radio-sm);
        padding: .15rem .5rem; display: inline-block;
    }
    .codigo-cupon:hover { background: #fff; border-color: var(--bosque-claro); }
</style>

<?= $this->endSection() ?>
