<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php use App\Models\MedioModel; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Tipos de alojamiento</h1>
        <p class="text-muted mb-0">
            Un tipo es lo que se vende: sus fotos, su descripción y sus servicios son los que ve el huésped en la web.
        </p>
    </div>
    <a href="<?= site_url('tipos/nuevo') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo tipo
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th colspan="2">Nombre</th>
                    <th class="d-none d-lg-table-cell">Descripción</th>
                    <th class="d-none d-md-table-cell">Capacidad</th>
                    <th>Tarifa base</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tipos)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay tipos registrados todavía.</td></tr>
                <?php endif ?>
                <?php foreach ($tipos as $t): ?>
                    <?php $r = $resumen[$t['id']] ?? ['fotos' => 0, 'videos' => 0, 'servicios' => 0, 'portada' => null]; ?>
                    <tr>
                        <td style="width: 84px;">
                            <a href="<?= site_url('tipos/ficha/' . $t['id']) ?>" class="d-block miniatura-tipo">
                                <?php if ($r['portada'] !== null && $r['portada']['tipo'] === 'foto'): ?>
                                    <img src="<?= esc(MedioModel::urlMiniatura($r['portada'])) ?>" alt="<?= esc($t['nombre']) ?>">
                                <?php else: ?>
                                    <span class="sin-foto"><i class="bi bi-camera"></i></span>
                                <?php endif ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?= site_url('tipos/ficha/' . $t['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= esc($t['nombre']) ?>
                            </a>
                            <div class="mt-1 d-flex gap-1 flex-wrap">
                                <?php if ($r['fotos'] === 0): ?>
                                    <span class="badge text-bg-warning">Sin fotos</span>
                                <?php else: ?>
                                    <span class="badge text-bg-light text-muted">
                                        <i class="bi bi-image me-1"></i><?= $r['fotos'] ?> foto<?= $r['fotos'] > 1 ? 's' : '' ?>
                                    </span>
                                <?php endif ?>
                                <?php if ($r['videos'] > 0): ?>
                                    <span class="badge text-bg-light text-muted"><i class="bi bi-play-btn me-1"></i><?= $r['videos'] ?></span>
                                <?php endif ?>
                                <span class="badge text-bg-light text-muted">
                                    <i class="bi bi-stars me-1"></i><?= $r['servicios'] ?> servicio<?= $r['servicios'] === 1 ? '' : 's' ?>
                                </span>
                            </div>
                        </td>
                        <td class="text-muted d-none d-lg-table-cell small"><?= esc($t['descripcion'] ?? '') ?></td>
                        <td class="d-none d-md-table-cell"><?= esc($t['capacidad']) ?> pers.</td>
                        <td>$<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?> COP</td>
                        <td class="text-end text-nowrap">
                            <a href="<?= site_url('tipos/ficha/' . $t['id']) ?>" class="btn btn-sm btn-primary" title="Fotos y servicios">
                                <i class="bi bi-images me-1"></i>Fotos y servicios
                            </a>
                            <a href="<?= site_url('tipos/editar/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary" title="Precios y capacidad">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="<?= site_url('tipos/eliminar/' . $t['id']) ?>" method="post" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar el tipo <?= esc($t['nombre'], 'js') ?>? Solo es posible si no tiene unidades asociadas.');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-light mt-3">
    <i class="bi bi-info-circle me-1"></i>
    Para <strong>subir fotos</strong>, entra en «Fotos y servicios». Las fotos de un tipo se ven en la web
    y en el motor de reservas. Las fotos internas de una cabaña concreta (desperfectos, cómo debe quedar
    tras el aseo) van en <a href="<?= site_url('unidades') ?>">su propia ficha</a> y no se publican.
</div>

<style>
    .miniatura-tipo { width: 68px; height: 50px; border-radius: var(--radio-sm);
                      overflow: hidden; background: var(--panel-tenue); border: 1px solid var(--borde); }
    .miniatura-tipo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .miniatura-tipo .sin-foto { width: 100%; height: 100%; display: flex; align-items: center;
                                justify-content: center; color: var(--borde-fuerte); }
</style>

<?= $this->endSection() ?>
