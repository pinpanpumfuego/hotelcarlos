<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['pendiente' => 'secondary', 'enviado' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger'];
?>

<h1 class="h3 mb-4">Registros de llegada</h1>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    El huésped completa su registro desde el móvil antes de llegar: datos, acompañantes, documento y firma.
    Aquí lo revisas y lo apruebas, de modo que a su llegada solo haya que entregarle la llave.
    El enlace se genera desde la ficha de cada reserva.
</div>

<!-- ═══ Por revisar ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-inbox me-2 text-warning"></i>Esperando tu revisión</span>
        <?php if (! empty($porRevisar)): ?>
            <span class="badge text-bg-warning"><?= count($porRevisar) ?></span>
        <?php endif ?>
    </div>
    <div class="list-group list-group-flush">
        <?php if (empty($porRevisar)): ?>
            <div class="list-group-item text-center text-muted py-5">
                <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success opacity-75"></i>
                No hay registros pendientes de revisar.
            </div>
        <?php endif ?>
        <?php foreach ($porRevisar as $r): ?>
            <a href="<?= site_url('registros/ver/' . $r['id']) ?>" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <span class="fw-semibold"><?= esc($r['nombre']) ?> <?= esc($r['apellidos']) ?></span>
                        <span class="text-muted">· <?= esc($r['codigo']) ?></span>
                        <div class="small text-muted">
                            <i class="bi bi-calendar3 me-1"></i>Llega el <?= date('d/m/Y', strtotime($r['fecha_entrada'])) ?>
                            · <?= esc($r['unidad_nombre']) ?>
                            · enviado <?= $r['enviado_en'] ? date('d/m H:i', strtotime($r['enviado_en'])) : '—' ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1 align-items-center">
                        <?php if ($r['hay_menores']): ?>
                            <span class="badge text-bg-danger"><i class="bi bi-shield-exclamation"></i> Menores</span>
                        <?php endif ?>
                        <?php if ($r['hay_extranjeros']): ?>
                            <span class="badge text-bg-info"><i class="bi bi-globe2"></i> Extranjeros</span>
                        <?php endif ?>
                        <span class="btn btn-sm btn-primary">Revisar</span>
                    </div>
                </div>
            </a>
        <?php endforeach ?>
    </div>
</div>

<!-- ═══ Resto ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Otros registros</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Huésped</th>
                    <th>Reserva</th>
                    <th class="d-none d-md-table-cell">Llegada</th>
                    <th>Estado</th>
                    <th>Avisos</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($otros)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Aún no hay registros.</td></tr>
                <?php endif ?>
                <?php foreach ($otros as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($r['nombre']) ?> <?= esc($r['apellidos']) ?></td>
                        <td><?= esc($r['codigo']) ?> <span class="text-muted small d-none d-lg-inline">· <?= esc($r['unidad_nombre']) ?></span></td>
                        <td class="d-none d-md-table-cell"><?= date('d/m/Y', strtotime($r['fecha_entrada'])) ?></td>
                        <td><span class="badge text-bg-<?= $colorEstado[$r['estado']] ?>"><?= esc($estados[$r['estado']]) ?></span></td>
                        <td class="text-nowrap">
                            <?php if ($r['hay_menores']): ?><i class="bi bi-shield-exclamation text-danger" title="Viaja con menores"></i><?php endif ?>
                            <?php if ($r['hay_extranjeros']): ?><i class="bi bi-globe2 text-info" title="Huéspedes extranjeros"></i><?php endif ?>
                            <?php if ($r['reportado_tra']): ?><span class="badge text-bg-light border">TRA</span><?php endif ?>
                            <?php if ($r['reportado_sire']): ?><span class="badge text-bg-light border">SIRE</span><?php endif ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= site_url('registros/ver/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
