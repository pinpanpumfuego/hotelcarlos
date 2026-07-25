<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$coloresPrioridad = ['baja' => 'secondary', 'media' => 'info', 'alta' => 'warning', 'urgente' => 'danger'];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Mantenimiento</h1>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formNueva">
        <i class="bi bi-plus-lg me-1"></i>Reportar incidencia
    </button>
</div>

<!-- ═══ Formulario de reporte (plegable) ═══ -->
<div class="collapse mb-4" id="formNueva">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="<?= site_url('mantenimiento/guardar') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">¿Qué pasó?</label>
                        <input type="text" name="titulo" class="form-control" required
                               placeholder="Ej.: gotea el calentador, bombillo fundido...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cabaña (o deja en blanco)</label>
                        <select name="unidad_id" class="form-select">
                            <option value="">Zonas comunes / otra</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= esc($u['id']) ?>"><?= esc($u['nombre']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Prioridad</label>
                        <select name="prioridad" class="form-select">
                            <?php foreach ($prioridades as $valor => $etiqueta): ?>
                                <option value="<?= $valor ?>" <?= $valor === 'media' ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Detalles (opcional)</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Más contexto para quien lo repare">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Si es zona común, ¿dónde?</label>
                        <input type="text" name="ubicacion" class="form-control" placeholder="Muelle, sendero, restaurante...">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="bloquear" value="1" id="chkBloquear">
                            <label class="form-check-label" for="chkBloquear">
                                Bloquear la cabaña (deja de venderse)
                            </label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary mt-3"><i class="bi bi-send me-1"></i>Reportar</button>
            </form>
        </div>
    </div>
</div>

<!-- ═══ Incidencias abiertas ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-tools me-2 text-warning"></i>Incidencias abiertas</div>
    <div class="list-group list-group-flush">
        <?php if (empty($abiertas)): ?>
            <div class="list-group-item text-muted text-center py-4">No hay incidencias abiertas. Todo funcionando.</div>
        <?php endif ?>
        <?php foreach ($abiertas as $i): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <span class="badge text-bg-<?= $coloresPrioridad[$i['prioridad']] ?>"><?= esc($prioridades[$i['prioridad']]) ?></span>
                        <?php if ($i['estado'] === 'en_proceso'): ?>
                            <span class="badge text-bg-primary">En proceso</span>
                        <?php endif ?>
                        <span class="fw-semibold ms-1"><?= esc($i['titulo']) ?></span>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-geo-alt me-1"></i><?= esc($i['unidad_nombre'] ?? $i['ubicacion'] ?? 'Sin ubicación') ?>
                            · reportó <?= esc($i['reporto_nombre']) ?> el <?= date('d/m H:i', strtotime($i['created_at'])) ?>
                            <?= $i['descripcion'] ? '· ' . esc($i['descripcion']) : '' ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <?php if ($i['estado'] === 'abierta'): ?>
                            <form action="<?= site_url('mantenimiento/iniciar/' . $i['id']) ?>" method="post">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-play-fill me-1"></i>Atender</button>
                            </form>
                        <?php endif ?>
                        <form action="<?= site_url('mantenimiento/resolver/' . $i['id']) ?>" method="post" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="text" name="solucion" class="form-control form-control-sm" placeholder="¿Qué se hizo?" required style="min-width: 170px;">
                            <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-check-lg me-1"></i>Resolver</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<!-- ═══ Resueltas recientes ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-check2-all me-2 text-success"></i>Resueltas recientemente</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Incidencia</th>
                    <th>Dónde</th>
                    <th class="d-none d-md-table-cell">Resolvió</th>
                    <th class="d-none d-md-table-cell">Cuándo</th>
                    <th class="d-none d-lg-table-cell">Solución</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($resueltas)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Aún no hay incidencias resueltas.</td></tr>
                <?php endif ?>
                <?php foreach ($resueltas as $i): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($i['titulo']) ?></td>
                        <td><?= esc($i['unidad_nombre'] ?? $i['ubicacion'] ?? '—') ?></td>
                        <td class="d-none d-md-table-cell"><?= esc($i['resolvio_nombre'] ?? '—') ?></td>
                        <td class="text-muted small d-none d-md-table-cell"><?= date('d/m H:i', strtotime($i['resuelta_en'])) ?></td>
                        <td class="text-muted small d-none d-lg-table-cell"><?= esc($i['solucion'] ?? '—') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
