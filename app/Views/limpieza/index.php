<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = [
    'pendiente' => 'secondary', 'en_curso' => 'info',
    'hecha' => 'primary', 'inspeccionada' => 'success', 'rechazada' => 'danger',
];
$estadoLimpieza = [
    'limpia'        => ['success', 'Limpia'],
    'sucia'         => ['warning', 'Sucia'],
    'en_limpieza'   => ['info', 'En limpieza'],
    'inspeccionada' => ['success', 'Inspeccionada'],
    'no_molestar'   => ['dark', 'No molestar'],
];
$motivos = [
    'dano' => 'Daño', 'mantenimiento' => 'Mantenimiento',
    'bioseguridad' => 'Bioseguridad', 'uso_propio' => 'Uso propio', 'otro' => 'Otro',
];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Limpieza</h1>
        <p class="text-muted mb-0">Lo urgente primero, después las cabañas con llegada hoy.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('limpieza/objetos') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-bag me-1"></i>Objetos olvidados
            <?php if ($objetos > 0): ?>
                <span class="badge text-bg-warning ms-1"><?= (int) $objetos ?></span>
            <?php endif ?>
        </a>
        <a href="<?= site_url('limpieza/checklist') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list-check me-1"></i>Checklist
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if ($exigeInspeccion && $inspeccion !== []): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-clipboard-check me-2 text-primary"></i>Esperando inspección
        </div>
        <div class="card-body py-2">
            <?php foreach ($inspeccion as $t): ?>
                <div class="row align-items-center py-2 border-bottom g-2">
                    <div class="col-md-6">
                        <span class="fw-semibold"><?= esc($t['cabana']) ?></span>
                        <div class="text-muted small">
                            <?= esc($tipos[$t['tipo']] ?? $t['tipo']) ?>
                            · la hizo <?= esc($t['quien'] ?? '—') ?>
                            <?php if ($t['inicio'] !== null && $t['fin'] !== null): ?>
                                en <?= (int) ((strtotime($t['fin']) - strtotime($t['inicio'])) / 60) ?> min
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex gap-2 justify-content-md-end">
                        <a href="<?= site_url('limpieza/tarea/' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
                        <form method="post" action="<?= site_url('limpieza/aprobar/' . $t['id']) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Aprobar</button>
                        </form>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse"
                                data-bs-target="#rech<?= (int) $t['id'] ?>">Rechazar</button>
                    </div>
                    <div class="collapse" id="rech<?= (int) $t['id'] ?>">
                        <form method="post" action="<?= site_url('limpieza/rechazar/' . $t['id']) ?>" class="d-flex gap-2 mt-2">
                            <?= csrf_field() ?>
                            <input type="text" name="motivo" class="form-control form-control-sm" required
                                   maxlength="300" placeholder="Qué hay que rehacer (sin esto no ayuda a nadie)">
                            <button class="btn btn-sm btn-danger text-nowrap">Devolver</button>
                        </form>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Tareas</div>
            <?php if ($tareas === []): ?>
                <div class="card-body text-center py-5">
                    <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success opacity-50"></i>
                    <p class="text-muted mb-0">No hay nada pendiente. Todo limpio.</p>
                </div>
            <?php else: ?>
                <div class="card-body py-2">
                    <?php foreach ($tareas as $t): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom flex-wrap gap-2">
                            <div>
                                <a href="<?= site_url('limpieza/tarea/' . $t['id']) ?>" class="text-decoration-none fw-semibold">
                                    <?= esc($t['cabana'] ?? '—') ?>
                                </a>
                                <?php if ($t['prioridad'] === 'urgente'): ?>
                                    <span class="badge text-bg-danger ms-1">Urgente</span>
                                <?php endif ?>
                                <?php if ($t['llega_hoy']): ?>
                                    <span class="badge text-bg-warning ms-1">Llega hoy</span>
                                <?php endif ?>
                                <?php // Sin esta marca el programa entero no existe: quien limpia
                                      // entra y cambia las toallas como cada mañana. ?>
                                <?php if (! empty($t['sin_lenceria'])): ?>
                                    <span class="badge text-bg-success ms-1">
                                        <i class="bi bi-leaf me-1"></i>Sin cambio de lencería
                                    </span>
                                <?php endif ?>
                                <div class="small text-muted">
                                    <?= esc($tipos[$t['tipo']] ?? $t['tipo']) ?>
                                    <?php if (! empty($t['quien'])): ?>· <?= esc($t['quien']) ?><?php endif ?>
                                    <?php if ($t['minutos'] !== null): ?>· lleva <?= (int) $t['minutos'] ?> min<?php endif ?>
                                </div>
                                <?php if (! empty($t['motivo_rechazo'])): ?>
                                    <div class="small text-danger">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i><?= esc($t['motivo_rechazo']) ?>
                                    </div>
                                <?php endif ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge text-bg-<?= $colorEstado[$t['estado']] ?? 'secondary' ?>">
                                    <?= esc($estados[$t['estado']] ?? $t['estado']) ?>
                                </span>
                                <?php if (in_array($t['estado'], ['pendiente', 'rechazada'], true)): ?>
                                    <form method="post" action="<?= site_url('limpieza/empezar/' . $t['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-primary">Empezar</button>
                                    </form>
                                <?php elseif ($t['estado'] === 'en_curso'): ?>
                                    <a href="<?= site_url('limpieza/tarea/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Seguir</a>
                                <?php endif ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Estado de las cabañas</div>
            <div class="card-body py-2">
                <?php foreach ($unidades as $u): ?>
                    <?php
                    $el = $u['estado_limpieza'] ?? 'limpia';
                    [$color, $texto] = $estadoLimpieza[$el] ?? ['secondary', $el];
                    $bloqueada = $u['estado'] === 'bloqueada';
                    ?>
                    <div class="py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-semibold"><?= esc($u['nombre']) ?></span>
                                <?php if ($bloqueada): ?>
                                    <div class="small text-danger">
                                        <i class="bi bi-slash-circle me-1"></i>Bloqueada<?php
                                        if (! empty($u['motivo_bloqueo'])) {
                                            echo ' · ' . esc($motivos[$u['motivo_bloqueo']] ?? $u['motivo_bloqueo']);
                                        }
                                        if (! empty($u['bloqueada_hasta'])) {
                                            echo ' hasta el ' . date('d/m', strtotime($u['bloqueada_hasta']));
                                        } ?>
                                    </div>
                                <?php endif ?>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge text-bg-<?= $color ?>"><?= esc($texto) ?></span>
                                <button class="btn btn-sm btn-link text-decoration-none p-0 ms-1"
                                        data-bs-toggle="collapse" data-bs-target="#acc<?= (int) $u['id'] ?>">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                            </div>
                        </div>

                        <div class="collapse" id="acc<?= (int) $u['id'] ?>">
                            <div class="bg-light rounded p-2 mt-2 small">
                                <form method="post" action="<?= site_url('limpieza/abrir/' . $u['id']) ?>" class="d-flex gap-1 mb-2">
                                    <?= csrf_field() ?>
                                    <select name="tipo" class="form-select form-select-sm">
                                        <?php foreach ($tipos as $clave => $nombre): ?>
                                            <option value="<?= esc($clave) ?>"><?= esc($nombre) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <select name="prioridad" class="form-select form-select-sm" style="width: 7rem;">
                                        <option value="normal">Normal</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                    <button class="btn btn-sm btn-primary text-nowrap">Abrir</button>
                                </form>

                                <form method="post" action="<?= site_url('limpieza/no-molestar/' . $u['id']) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="activar" value="<?= $el === 'no_molestar' ? '0' : '1' ?>">
                                    <button class="btn btn-sm btn-outline-dark">
                                        <?= $el === 'no_molestar' ? 'Quitar «no molestar»' : 'No molestar' ?>
                                    </button>
                                </form>

                                <?php if ($bloqueada): ?>
                                    <form method="post" action="<?= site_url('limpieza/desbloquear/' . $u['id']) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-success">Desbloquear</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= site_url('limpieza/bloquear/' . $u['id']) ?>" class="mt-2">
                                        <?= csrf_field() ?>
                                        <div class="d-flex gap-1">
                                            <select name="motivo" class="form-select form-select-sm">
                                                <?php foreach ($motivos as $clave => $nombre): ?>
                                                    <option value="<?= esc($clave) ?>"><?= esc($nombre) ?></option>
                                                <?php endforeach ?>
                                            </select>
                                            <input type="date" name="hasta" class="form-control form-control-sm">
                                            <button class="btn btn-sm btn-outline-danger text-nowrap">Bloquear</button>
                                        </div>
                                        <input type="text" name="nota" class="form-control form-control-sm mt-1"
                                               maxlength="300" placeholder="Qué pasa (opcional)">
                                    </form>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <?php if ($historial !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Últimas terminadas</div>
                <div class="card-body py-2">
                    <?php foreach ($historial as $h): ?>
                        <div class="d-flex justify-content-between py-1 border-bottom small">
                            <span>
                                <?= esc($h['unidad_nombre'] ?? '—') ?>
                                <span class="text-muted">· <?= esc($h['usuario_nombre'] ?? '—') ?></span>
                            </span>
                            <span class="text-muted text-nowrap">
                                <?php if ($h['inicio'] !== null && $h['fin'] !== null): ?>
                                    <?= (int) ((strtotime($h['fin']) - strtotime($h['inicio'])) / 60) ?> min ·
                                <?php endif ?>
                                <?= $h['fin'] !== null ? date('d/m H:i', strtotime($h['fin'])) : '' ?>
                            </span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
