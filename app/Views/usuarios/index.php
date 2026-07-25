<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Usuarios del sistema</h1>
    <a href="<?= site_url('usuarios/nuevo') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= esc($u['nombre']) ?>
                            <?php if ((int) $u['id'] === (int) session()->get('usuario_id')): ?>
                                <span class="badge text-bg-info ms-1">Tú</span>
                            <?php endif ?>
                        </td>
                        <td><?= esc($u['email']) ?></td>
                        <td><?= esc(\App\Models\UsuarioModel::ROLES[$u['rol']] ?? $u['rol']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= $u['activo'] ? 'success' : 'secondary' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span>
                        </td>
                        <td class="text-muted"><?= $u['ultimo_acceso'] ? esc(date('d/m/Y H:i', strtotime($u['ultimo_acceso']))) : 'Nunca' ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('usuarios/editar/' . $u['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ((int) $u['id'] !== (int) session()->get('usuario_id')): ?>
                                <form action="<?= site_url('usuarios/eliminar/' . $u['id']) ?>" method="post" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar al usuario <?= esc($u['nombre'], 'js') ?>?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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
