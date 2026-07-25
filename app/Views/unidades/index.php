<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$rol       = session()->get('usuario_rol');
$esGestor  = in_array($rol, ['gerencia', 'recepcion'], true);
$colores   = ['disponible' => 'success', 'ocupada' => 'danger', 'limpieza' => 'warning', 'bloqueada' => 'secondary'];
$etiquetas = ['disponible' => 'Disponible', 'ocupada' => 'Ocupada', 'limpieza' => 'En limpieza', 'bloqueada' => 'Bloqueada'];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Cabañas</h1>
    <?php if ($esGestor): ?>
        <a href="<?= site_url('unidades/nueva') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva cabaña
        </a>
    <?php endif ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th class="d-none d-md-table-cell">Notas</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($unidades)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">
                        <i class="bi bi-door-open fs-3 d-block mb-2 opacity-50"></i>
                        No hay cabañas registradas todavía.
                    </td></tr>
                <?php endif ?>
                <?php foreach ($unidades as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($u['nombre']) ?></td>
                        <td><?= esc($u['tipo_nombre']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= $colores[$u['estado']] ?? 'secondary' ?>"><?= esc($etiquetas[$u['estado']] ?? $u['estado']) ?></span>
                        </td>
                        <td class="text-muted d-none d-md-table-cell"><?= esc($u['notas'] ?? '') ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= site_url('unidades/editar/' . $u['id']) ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($rol === 'gerencia'): ?>
                                <form action="<?= site_url('unidades/eliminar/' . $u['id']) ?>" method="post" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar <?= esc($u['nombre'], 'js') ?>? Solo es posible si no tiene reservas.');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted small mt-3">
    <i class="bi bi-info-circle me-1"></i>
    El estado cambia solo con la operación: al hacer check-in pasa a ocupada, al check-out a limpieza,
    y vuelve a disponible cuando el equipo termina el aseo desde el <a href="<?= site_url('limpieza') ?>">tablero de limpieza</a>.
</p>

<?= $this->endSection() ?>
