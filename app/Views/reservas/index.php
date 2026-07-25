<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colores   = ['pendiente' => 'warning', 'confirmada' => 'primary', 'checkin' => 'success', 'checkout' => 'secondary', 'cancelada' => 'danger'];
$etiquetas = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'checkin' => 'Alojado', 'checkout' => 'Finalizada', 'cancelada' => 'Cancelada'];
$filtrosEstado = [
    ''           => 'Todas',
    'activas'    => 'Activas',
    'pendiente'  => 'Por confirmar',
    'confirmada' => 'Confirmadas',
    'checkin'    => 'Alojados',
    'checkout'   => 'Finalizadas',
    'cancelada'  => 'Canceladas',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Reservas</h1>
    <a href="<?= site_url('reservas/nueva') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva reserva
    </a>
</div>

<!-- ── Filtros ── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="get" action="<?= site_url('reservas') ?>" class="d-flex gap-2 flex-wrap align-items-center">
            <div class="input-group" style="max-width: 320px;">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="Código, nombre o documento"
                       value="<?= esc($filtros['buscar']) ?>">
            </div>
            <select name="estado" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
                <?php foreach ($filtrosEstado as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php endforeach ?>
            </select>
            <button class="btn btn-outline-primary">Filtrar</button>
            <?php if ($filtros['buscar'] !== '' || $filtros['estado'] !== ''): ?>
                <a href="<?= site_url('reservas') ?>" class="btn btn-link text-decoration-none">Limpiar</a>
            <?php endif ?>
            <span class="ms-auto text-muted small"><?= esc($totalActual) ?> reserva<?= $totalActual === 1 ? '' : 's' ?></span>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Huésped</th>
                    <th>Cabaña</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th class="d-none d-lg-table-cell">Personas</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservas)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
                        <?= ($filtros['buscar'] !== '' || $filtros['estado'] !== '')
                            ? 'Ninguna reserva coincide con el filtro.'
                            : 'Todavía no hay reservas registradas.' ?>
                    </td></tr>
                <?php endif ?>
                <?php foreach ($reservas as $r): ?>
                    <tr>
                        <td><a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($r['codigo']) ?></a></td>
                        <td>
                            <?= esc($r['huesped_apellidos']) ?>, <?= esc($r['huesped_nombre']) ?>
                            <div class="small text-muted d-lg-none"><?= esc($r['unidad_nombre']) ?></div>
                        </td>
                        <td class="d-none d-lg-table-cell"><?= esc($r['unidad_nombre']) ?></td>
                        <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($r['fecha_entrada']))) ?></td>
                        <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($r['fecha_salida']))) ?></td>
                        <td class="d-none d-lg-table-cell"><?= esc($r['adultos']) ?> ad.<?= $r['ninos'] > 0 ? ' + ' . esc($r['ninos']) . ' niñ.' : '' ?></td>
                        <td class="text-nowrap">$<?= number_format((float) $r['total'], 0, ',', '.') ?></td>
                        <td><span class="badge text-bg-<?= $colores[$r['estado']] ?? 'secondary' ?>"><?= esc($etiquetas[$r['estado']] ?? $r['estado']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver ficha y folio">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($r['estado'] === 'pendiente'): ?>
                                <form action="<?= site_url('reservas/confirmar/' . $r['id']) ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-primary" title="Confirmar reserva"><i class="bi bi-check2-circle"></i></button>
                                </form>
                            <?php endif ?>
                            <?php if (in_array($r['estado'], ['pendiente', 'confirmada'], true)): ?>
                                <form action="<?= site_url('reservas/checkin/' . $r['id']) ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-success" title="Check-in"><i class="bi bi-box-arrow-in-right"></i></button>
                                </form>
                            <?php endif ?>
                            <?php if ($r['estado'] === 'checkin'): ?>
                                <form action="<?= site_url('reservas/checkout/' . $r['id']) ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-secondary" title="Check-out"><i class="bi bi-box-arrow-right"></i></button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginador->getPageCount() > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-center pt-3">
            <?= $paginador->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
