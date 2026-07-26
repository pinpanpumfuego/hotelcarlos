<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $grupos = ['Comodidades', 'Baño', 'Exterior', 'Cocina', 'Servicios']; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Catálogo de servicios</h1>
        <p class="text-muted mb-0">
            Lo que se puede anunciar de un alojamiento. Después marcas cuáles tiene cada tipo
            en <a href="<?= site_url('tipos') ?>">Tipos de alojamiento</a>.
        </p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalServicio">
        <i class="bi bi-plus-lg me-1"></i>Nuevo servicio
    </button>
</div>

<?= view('partes/errores') ?>

<?php foreach ($catalogo as $grupo => $lista): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= esc($grupo) ?></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <tbody>
                    <?php foreach ($lista as $s): ?>
                        <tr class="<?= (int) $s['activo'] !== 1 ? 'opacity-50' : '' ?>">
                            <td style="width: 46px;"><i class="bi <?= esc($s['icono']) ?> fs-5 text-muted"></i></td>
                            <td>
                                <span class="fw-semibold"><?= esc($s['nombre']) ?></span>
                                <?php if ((int) $s['activo'] !== 1): ?>
                                    <span class="badge text-bg-secondary ms-1">Desactivado</span>
                                <?php endif ?>
                            </td>
                            <td class="text-muted small">
                                <?php $uso = $enUso[$s['id']] ?? 0; ?>
                                <?= $uso > 0 ? 'En ' . $uso . ' alojamiento' . ($uso > 1 ? 's' : '') : 'Sin usar' ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalServicio<?= $s['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= site_url('servicios/eliminar/' . $s['id']) ?>"
                                          onsubmit="return confirm('¿Quitar <?= esc($s['nombre'], 'js') ?> del catálogo?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach ?>

<?= view('catalogos/_modal_servicio', ['id' => 'modalServicio', 'accion' => site_url('servicios/guardar'), 'servicio' => null, 'grupos' => $grupos]) ?>
<?php foreach ($catalogo as $lista): ?>
    <?php foreach ($lista as $s): ?>
        <?= view('catalogos/_modal_servicio', [
            'id'       => 'modalServicio' . $s['id'],
            'accion'   => site_url('servicios/actualizar/' . $s['id']),
            'servicio' => $s,
            'grupos'   => $grupos,
        ]) ?>
    <?php endforeach ?>
<?php endforeach ?>

<?= $this->endSection() ?>
