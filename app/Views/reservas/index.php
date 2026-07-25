<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Reservas</h1>
    <a href="<?= site_url('reservas/nueva') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva reserva
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Huésped</th>
                    <th>Unidad</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Personas</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservas)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay reservas todavía.</td></tr>
                <?php endif ?>
                <?php foreach ($reservas as $r): ?>
                    <tr>
                        <td><a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($r['codigo']) ?></a></td>
                        <td><?= esc($r['huesped_apellidos']) ?>, <?= esc($r['huesped_nombre']) ?></td>
                        <td><?= esc($r['unidad_nombre']) ?></td>
                        <td><?= esc(date('d/m/Y', strtotime($r['fecha_entrada']))) ?></td>
                        <td><?= esc(date('d/m/Y', strtotime($r['fecha_salida']))) ?></td>
                        <td><?= esc($r['adultos']) ?> ad.<?= $r['ninos'] > 0 ? ' + ' . esc($r['ninos']) . ' niñ.' : '' ?></td>
                        <td>$<?= number_format((float) $r['total'], 0, ',', '.') ?></td>
                        <td>
                            <?php $colores = ['pendiente' => 'warning', 'confirmada' => 'primary', 'checkin' => 'success', 'checkout' => 'secondary', 'cancelada' => 'danger']; ?>
                            <span class="badge text-bg-<?= $colores[$r['estado']] ?? 'secondary' ?>"><?= esc(ucfirst($r['estado'])) ?></span>
                        </td>
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
                            <?php if (in_array($r['estado'], ['pendiente', 'confirmada'], true)): ?>
                                <a href="<?= site_url('reservas/editar/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="<?= site_url('reservas/cancelar/' . $r['id']) ?>" method="post" class="d-inline"
                                      onsubmit="return confirm('¿Cancelar la reserva <?= esc($r['codigo'], 'js') ?>?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
