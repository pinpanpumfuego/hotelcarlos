<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['pendiente' => 'warning', 'en_curso' => 'info', 'resuelta' => 'success', 'rechazada' => 'secondary'];
$textoEstado = ['pendiente' => 'Pendiente', 'en_curso' => 'En curso', 'resuelta' => 'Resuelta', 'rechazada' => 'No se pudo'];
$caras       = [1 => '😞', 2 => '🙁', 3 => '😐', 4 => '🙂', 5 => '😍'];
?>

<div class="mb-4">
    <h1 class="h3 mb-1">Peticiones de huéspedes</h1>
    <p class="text-muted mb-0">
        Lo que piden desde su móvil durante la estancia. Cuando cambias el estado,
        lo ven al momento en su portal.
    </p>
</div>

<?= view('partes/errores') ?>

<?php if ($encuestas !== []): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-chat-heart me-2 text-danger"></i>Encuestas que piden una respuesta
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-2">
                Notas de <?= (int) $umbral ?> o menos, y comentarios sin leer. Las de
                <strong>durante la estancia</strong> van primero: a esas todavía se les puede dar la vuelta.
            </p>
            <?php foreach ($encuestas as $e): ?>
                <div class="d-flex justify-content-between align-items-start py-2 border-bottom gap-3">
                    <div>
                        <span class="fs-5 me-1"><?= $caras[(int) $e['general']] ?? '' ?></span>
                        <strong><?= esc(trim(($e['nombre'] ?? '') . ' ' . ($e['apellidos'] ?? ''))) ?: 'Huésped' ?></strong>
                        <span class="badge text-bg-<?= $e['momento'] === 'estancia' ? 'warning' : 'light' ?> border ms-1">
                            <?= $e['momento'] === 'estancia' ? 'Está aquí ahora' : 'Ya se fue' ?>
                        </span>
                        <?php if (! empty($e['comentario'])): ?>
                            <div class="small text-muted mt-1">«<?= esc($e['comentario']) ?>»</div>
                        <?php endif ?>
                        <div class="small text-muted">
                            <?= esc($e['codigo']) ?>
                            <?php if (! empty($e['telefono'])): ?>
                                · <a href="tel:<?= esc($e['telefono']) ?>" class="text-decoration-none"><?= esc($e['telefono']) ?></a>
                            <?php endif ?>
                        </div>
                    </div>
                    <form method="post" action="<?= site_url('solicitudes/encuesta/' . $e['id']) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-secondary text-nowrap">Marcar leída</button>
                    </form>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Sin atender</option>
                    <?php foreach ($textoEstado as $v => $t): ?>
                        <option value="<?= $v ?>" <?= ($filtros['estado'] ?? '') === $v ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $v => $t): ?>
                        <option value="<?= $v ?>" <?= ($filtros['tipo'] ?? '') === $v ? 'selected' : '' ?>><?= esc($t) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= esc($filtros['desde'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= esc($filtros['hasta'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                <a href="<?= site_url('solicitudes') ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if ($solicitudes === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success opacity-50"></i>
            <p class="text-muted mb-0">
                <?= $sinFiltrar ? 'No hay nada pendiente. Todo atendido.' : 'No hay peticiones con esos filtros.' ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($solicitudes as $s): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100 <?= $s['prioridad'] === 'urgente' ? 'border-start border-danger border-4' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="fw-semibold"><?= esc($tipos[$s['tipo']] ?? $s['tipo']) ?></span>
                                <?php if ($s['prioridad'] === 'urgente'): ?>
                                    <span class="badge text-bg-danger ms-1">Urgente</span>
                                <?php endif ?>
                            </div>
                            <span class="badge text-bg-<?= $colorEstado[$s['estado']] ?? 'secondary' ?>">
                                <?= $textoEstado[$s['estado']] ?? $s['estado'] ?>
                            </span>
                        </div>

                        <div class="small text-muted mb-2">
                            <i class="bi bi-door-open me-1"></i><?= esc($s['cabana'] ?? 'sin cabaña') ?>
                            · <?= esc(trim(($s['huesped_nombre'] ?? '') . ' ' . ($s['huesped_apellidos'] ?? ''))) ?: 'Huésped' ?>
                            · <?= esc($s['reserva_codigo'] ?? '') ?>
                        </div>

                        <?php if (! empty($s['detalle'])): ?>
                            <p class="mb-2 small">«<?= esc($s['detalle']) ?>»</p>
                        <?php endif ?>

                        <div class="small text-muted mb-3">
                            <i class="bi bi-clock me-1"></i><?= date('d/m H:i', strtotime($s['created_at'])) ?>
                            <?php if (! empty($s['para_cuando'])): ?>
                                · para <strong><?= esc($s['para_cuando']) ?></strong>
                            <?php endif ?>
                        </div>

                        <?php if (in_array($s['estado'], ['pendiente', 'en_curso'], true)): ?>
                            <form method="post" action="<?= site_url('solicitudes/atender/' . $s['id']) ?>">
                                <?= csrf_field() ?>
                                <input type="text" name="respuesta" class="form-control form-control-sm mb-2"
                                       maxlength="400" placeholder="Respuesta para el huésped (opcional)">
                                <div class="d-flex gap-2">
                                    <?php if ($s['estado'] === 'pendiente'): ?>
                                        <button name="estado" value="en_curso" class="btn btn-sm btn-outline-info flex-grow-1">
                                            <i class="bi bi-arrow-right me-1"></i>En camino
                                        </button>
                                    <?php endif ?>
                                    <button name="estado" value="resuelta" class="btn btn-sm btn-success flex-grow-1">
                                        <i class="bi bi-check-lg me-1"></i>Hecho
                                    </button>
                                    <button name="estado" value="rechazada" class="btn btn-sm btn-outline-secondary">
                                        No se puede
                                    </button>
                                </div>
                            </form>
                        <?php elseif (! empty($s['respuesta'])): ?>
                            <div class="alert alert-light border small mb-0 py-2">
                                <i class="bi bi-reply me-1"></i><?= esc($s['respuesta']) ?>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
