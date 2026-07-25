<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Huéspedes</h1>
    <div class="d-flex gap-2">
        <form method="get" action="<?= site_url('huespedes') ?>" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o documento"
                       value="<?= esc($buscar ?? '') ?>">
            </div>
            <button class="btn btn-outline-primary">Buscar</button>
            <?php if (($buscar ?? '') !== ''): ?>
                <a href="<?= site_url('huespedes') ?>" class="btn btn-link text-decoration-none text-nowrap">Limpiar</a>
            <?php endif ?>
        </form>
        <a href="<?= site_url('huespedes/nuevo') ?>" class="btn btn-primary text-nowrap">
            <i class="bi bi-plus-lg me-1"></i>Nuevo huésped
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>Nacionalidad</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($huespedes)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-people fs-3 d-block mb-2 opacity-50"></i>
                        No hay huéspedes registrados<?= ($buscar ?? '') !== '' ? ' que coincidan con la búsqueda' : ' todavía' ?>.
                    </td></tr>
                <?php endif ?>
                <?php foreach ($huespedes as $h): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($h['apellidos']) ?>, <?= esc($h['nombre']) ?></td>
                        <td><?= esc($h['tipo_documento']) ?> <?= esc($h['num_documento']) ?></td>
                        <td><?= esc($h['nacionalidad']) ?></td>
                        <td><?= esc($h['telefono'] ?? '') ?></td>
                        <td><?= esc($h['email'] ?? '') ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('huespedes/editar/' . $h['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="<?= site_url('huespedes/eliminar/' . $h['id']) ?>" method="post" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar al huésped <?= esc($h['nombre'] . ' ' . $h['apellidos'], 'js') ?>?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginador->getPageCount() > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3">
            <span class="text-muted small"><?= esc($totalActual) ?> huésped<?= $totalActual === 1 ? '' : 'es' ?></span>
            <?= $paginador->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
