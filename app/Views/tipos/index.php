<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Tipos de alojamiento</h1>
    <a href="<?= site_url('tipos/nuevo') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo tipo
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Capacidad</th>
                    <th>Tarifa base</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tipos)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay tipos registrados todavía.</td></tr>
                <?php endif ?>
                <?php foreach ($tipos as $t): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($t['nombre']) ?></td>
                        <td class="text-muted"><?= esc($t['descripcion'] ?? '') ?></td>
                        <td><?= esc($t['capacidad']) ?> pers.</td>
                        <td>$<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?> COP</td>
                        <td class="text-end">
                            <a href="<?= site_url('tipos/editar/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="<?= site_url('tipos/eliminar/' . $t['id']) ?>" method="post" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar el tipo <?= esc($t['nombre'], 'js') ?>? Solo es posible si no tiene unidades asociadas.');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
