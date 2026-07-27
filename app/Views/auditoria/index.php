<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorResultado = ['ok' => 'success', 'error' => 'warning', 'denegado' => 'danger'];
$etiqueta       = ['ok' => 'Hecho', 'error' => 'No se pudo', 'denegado' => 'Sin permiso'];
?>

<div class="mb-4">
    <h1 class="h3 mb-1">Auditoría</h1>
    <p class="text-muted mb-0">
        Quién hizo cada cosa de las que importan: lo que mueve dinero, toca datos
        personales o cambia la configuración. No se puede editar ni borrar.
    </p>
</div>

<?php if ($denegados !== []): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-shield-exclamation me-2 text-danger"></i>Intentos sin permiso · últimos 7 días
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-2">
                Casi siempre es un enlace viejo o un menú mal configurado. Si se repite
                mucho con la misma persona, quizá le falte un permiso que sí necesita.
            </p>
            <?php foreach ($denegados as $d): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-1 small">
                    <span>
                        <strong><?= esc($d['usuario_nombre']) ?></strong>
                        <span class="text-muted">intentó</span>
                        <code><?= esc($d['permiso']) ?></code>
                    </span>
                    <span class="text-muted">
                        <?= (int) $d['veces'] ?> vez<?= (int) $d['veces'] === 1 ? '' : 'es' ?>
                        · <?= date('d/m H:i', strtotime($d['ultima'])) ?>
                    </span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= esc($filtros['desde'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= esc($filtros['hasta'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Quién</label>
                <select name="usuario_id" class="form-select form-select-sm">
                    <option value="">Cualquiera</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int) $u['usuario_id'] ?>" <?= (string) ($filtros['usuario_id'] ?? '') === (string) $u['usuario_id'] ? 'selected' : '' ?>>
                            <?= esc($u['usuario_nombre']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Área</label>
                <select name="modulo" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($modulos as $clave => $nombre): ?>
                        <option value="<?= esc($clave) ?>" <?= ($filtros['modulo'] ?? '') === $clave ? 'selected' : '' ?>>
                            <?= esc($nombre) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Resultado</label>
                <select name="resultado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($etiqueta as $valor => $texto): ?>
                        <option value="<?= $valor ?>" <?= ($filtros['resultado'] ?? '') === $valor ? 'selected' : '' ?>>
                            <?= $texto ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="<?= site_url('auditoria') ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if ($registros === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-clipboard-check fs-2 d-block mb-2 text-muted opacity-50"></i>
            <p class="text-muted mb-0">No hay nada registrado con esos filtros.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Cuándo</th>
                        <th>Quién</th>
                        <th>Qué hizo</th>
                        <th class="d-none d-lg-table-cell">Sobre</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                        <tr>
                            <td class="text-nowrap text-muted">
                                <?= date('d/m/Y', strtotime($r['created_at'])) ?>
                                <span class="d-block"><?= date('H:i:s', strtotime($r['created_at'])) ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= esc($r['usuario_nombre']) ?></span>
                                <?php if (! empty($r['perfil'])): ?>
                                    <span class="d-block text-muted"><?= esc($r['perfil']) ?></span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?= esc($sensibles[$r['permiso']] ?? $r['permiso']) ?>
                                <code class="d-block text-muted"><?= esc($r['metodo']) ?> /<?= esc($r['ruta']) ?></code>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <?php if (! empty($r['referencia'])): ?>
                                    <a href="<?= site_url('auditoria/referencia/' . urlencode($r['referencia'])) ?>"
                                       class="text-decoration-none">
                                        <?= esc($r['referencia']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= $colorResultado[$r['resultado']] ?? 'secondary' ?>">
                                    <?= $etiqueta[$r['resultado']] ?? $r['resultado'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3"><?= $paginador->links() ?></div>
<?php endif ?>

<div class="alert alert-light border small mt-4 mb-0">
    <i class="bi bi-clock-history me-1"></i>
    Se conserva <strong><?= (int) (\App\Models\AuditoriaModel::DIAS_CONSERVACION / 365) ?> años</strong>
    y después se borra solo. Es tiempo de sobra para una revisión contable, y no
    guardar de más también es parte de tratar bien los datos de las personas.
</div>

<?= $this->endSection() ?>
