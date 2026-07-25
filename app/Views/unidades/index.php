<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Unidades de alojamiento</h1>
    <a href="<?= site_url('unidades/nueva') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva unidad
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Notas</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($unidades)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay unidades registradas todavía.</td></tr>
                <?php endif ?>
                <?php foreach ($unidades as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($u['nombre']) ?></td>
                        <td><?= esc($u['tipo_nombre']) ?></td>
                        <td>
                            <?php $colores = ['disponible' => 'success', 'ocupada' => 'danger', 'limpieza' => 'warning', 'bloqueada' => 'secondary']; ?>
                            <span class="badge text-bg-<?= $colores[$u['estado']] ?? 'secondary' ?>"><?= esc(ucfirst($u['estado'])) ?></span>
                        </td>
                        <td class="text-muted"><?= esc($u['notas'] ?? '') ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('unidades/editar/' . $u['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="<?= site_url('unidades/eliminar/' . $u['id']) ?>" method="post" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar la unidad <?= esc($u['nombre'], 'js') ?>?');">
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
