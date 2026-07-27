<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['activo' => 'success', 'averiado' => 'danger', 'reparacion' => 'warning', 'baja' => 'secondary'];
$colorInc    = [
    'abierta' => 'danger', 'en_proceso' => 'warning', 'pausada' => 'secondary',
    'resuelta' => 'success', 'verificada' => 'success', 'anulada' => 'secondary',
];
$colorPrio = ['urgente' => 'danger', 'alta' => 'warning', 'media' => 'info', 'baja' => 'secondary'];
?>

<?php if ($desde_qr): ?>
    <div class="alert alert-primary d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-qr-code-scan fs-4"></i>
        <div>Has escaneado la etiqueta de este equipo.</div>
    </div>
<?php endif ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="text-muted font-monospace small"><?= esc($activo['codigo']) ?></div>
        <h1 class="h3 mb-1">
            <?= esc($activo['nombre']) ?>
            <?php if ((int) $activo['critico'] === 1): ?>
                <span class="badge text-bg-danger align-middle">Crítico</span>
            <?php endif ?>
        </h1>
        <p class="text-muted mb-0">
            <?= esc($categorias[$activo['categoria']]) ?>
            · <?= esc($activo['unidad_nombre'] ?? $activo['ubicacion'] ?? 'Sin ubicar') ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (puede('activos.gestionar')): ?>
            <a href="<?= site_url('activos/editar/' . $activo['id']) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Editar
            </a>
        <?php endif ?>
        <a href="<?= site_url('activos') ?>" class="btn btn-outline-secondary d-none d-md-inline-block">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<?php // Lo primero de la pantalla es el botón de reportar: quien llega aquí
      // desde el QR casi siempre viene porque algo no funciona. ?>
<?php if (puede('mantenimiento.crear') && $activo['estado'] !== 'baja'): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-danger">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-exclamation-triangle me-2 text-danger"></i>¿Algo no funciona?
        </div>
        <div class="card-body">
            <form method="post" action="<?= site_url('mantenimiento/guardar') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="activo_id" value="<?= $activo['id'] ?>">
                <input type="hidden" name="unidad_id" value="<?= (int) $activo['unidad_id'] ?>">
                <input type="hidden" name="volver" value="activos/ver/<?= $activo['id'] ?>">

                <div class="mb-3">
                    <label class="form-label">Qué le pasa</label>
                    <input type="text" name="titulo" class="form-control form-control-lg" required
                           placeholder="Gotea por abajo, no calienta, hace ruido…">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cuánto corre</label>
                        <select name="prioridad" class="form-select">
                            <?php foreach ($prioridades as $p => $etiqueta): ?>
                                <option value="<?= $p ?>" <?= $p === ((int) $activo['critico'] === 1 ? 'alta' : 'media') ? 'selected' : '' ?>>
                                    <?= esc($etiqueta) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Detalle (opcional)</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Desde cuándo, qué se ha probado…">
                    </div>
                </div>

                <?php if ($activo['unidad_id'] !== null): ?>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="bloquear" value="1" id="bloquear"
                               <?= (int) $activo['critico'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="bloquear">
                            Bloquear <strong><?= esc($activo['unidad_nombre']) ?></strong> hasta arreglarlo
                            <span class="d-block form-text">Deja de venderse al instante.</span>
                        </label>
                    </div>
                <?php endif ?>

                <button class="btn btn-danger mt-3">
                    <i class="bi bi-send me-1"></i>Reportar la avería
                </button>
            </form>
        </div>
    </div>
<?php endif ?>

<?php if ($abiertas !== []): ?>
    <div class="alert alert-warning">
        <strong>Este equipo tiene <?= count($abiertas) ?> avería<?= count($abiertas) > 1 ? 's' : '' ?> sin cerrar.</strong>
        Antes de reportar otra, mira si es la misma.
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <!-- Ficha -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-card-list me-2"></i>Ficha</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 col-sm-4 text-muted fw-normal">Estado</dt>
                    <dd class="col-7 col-sm-8">
                        <span class="badge text-bg-<?= $colorEstado[$activo['estado']] ?>"><?= esc($estados[$activo['estado']]) ?></span>
                        <?php if ($activo['estado'] === 'baja' && $activo['baja_en']): ?>
                            <span class="text-muted ms-1">
                                el <?= date('d/m/Y', strtotime($activo['baja_en'])) ?> · <?= esc($activo['baja_motivo']) ?>
                            </span>
                        <?php endif ?>
                    </dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Marca y modelo</dt>
                    <dd class="col-7 col-sm-8"><?= esc(trim(($activo['marca'] ?? '') . ' ' . ($activo['modelo'] ?? ''))) ?: '—' ?></dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Número de serie</dt>
                    <dd class="col-7 col-sm-8 font-monospace"><?= esc($activo['serie']) ?: '—' ?></dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Proveedor</dt>
                    <dd class="col-7 col-sm-8"><?= esc($activo['proveedor_nombre']) ?: '—' ?></dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Comprado</dt>
                    <dd class="col-7 col-sm-8">
                        <?= $activo['fecha_compra'] ? date('d/m/Y', strtotime($activo['fecha_compra'])) : '—' ?>
                        <?php if ($activo['valor_compra'] !== null): ?>
                            · $<?= number_format((float) $activo['valor_compra'], 0, ',', '.') ?>
                        <?php endif ?>
                    </dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Garantía</dt>
                    <dd class="col-7 col-sm-8">
                        <?php if ($activo['garantia_hasta'] === null): ?>
                            —
                        <?php elseif ($en_garantia): ?>
                            <span class="badge text-bg-success">En garantía</span>
                            hasta el <?= date('d/m/Y', strtotime($activo['garantia_hasta'])) ?>
                            <div class="text-muted">Antes de tocarlo, llama al proveedor: abrirlo puede anularla.</div>
                        <?php else: ?>
                            <span class="text-muted">Venció el <?= date('d/m/Y', strtotime($activo['garantia_hasta'])) ?></span>
                        <?php endif ?>
                    </dd>

                    <?php if ($activo['notas']): ?>
                        <dt class="col-12 text-muted fw-normal mt-2">Notas</dt>
                        <dd class="col-12 mb-0"><?= nl2br(esc($activo['notas'])) ?></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <!-- Historial -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-2"></i>Historial de averías
                <span class="badge text-bg-secondary ms-1"><?= count($incidencias) ?></span>
            </div>
            <?php if ($incidencias === []): ?>
                <div class="card-body text-muted small">
                    Todavía no ha dado ningún problema. Cuando dé el primero, aquí se irá viendo
                    si es siempre lo mismo — que es lo que dice si toca cambiarlo en vez de arreglarlo.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($incidencias as $i): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <span class="badge text-bg-<?= $colorPrio[$i['prioridad']] ?> me-1"><?= esc($prioridades[$i['prioridad']]) ?></span>
                                    <strong><?= esc($i['titulo']) ?></strong>
                                    <?php if ($i['descripcion']): ?>
                                        <div class="small text-muted"><?= esc($i['descripcion']) ?></div>
                                    <?php endif ?>
                                    <?php if ($i['solucion']): ?>
                                        <div class="small mt-1">
                                            <i class="bi bi-check2 text-success me-1"></i><?= esc($i['solucion']) ?>
                                            <?php if ($i['resolvio_nombre']): ?>
                                                <span class="text-muted">— <?= esc($i['resolvio_nombre']) ?></span>
                                            <?php endif ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                                <div class="text-end small">
                                    <span class="badge text-bg-<?= $colorInc[$i['estado']] ?>"><?= esc(ucfirst(str_replace('_', ' ', $i['estado']))) ?></span>
                                    <div class="text-muted mt-1"><?= date('d/m/Y', strtotime($i['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Etiqueta -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-qr-code me-2"></i>Su etiqueta</div>
            <div class="card-body text-center">
                <img src="<?= site_url('activos/qr/' . $activo['id']) ?>" alt="Código QR de <?= esc($activo['codigo']) ?>"
                     class="img-fluid" style="max-width: 180px;">
                <div class="font-monospace fw-semibold mt-2"><?= esc($activo['codigo']) ?></div>
                <div class="small text-muted"><?= esc($enlace) ?></div>
                <?php if (puede('activos.gestionar')): ?>
                    <a href="<?= site_url('activos/etiquetas?id[]=' . $activo['id']) ?>" target="_blank" rel="noopener"
                       class="btn btn-outline-secondary btn-sm mt-3">
                        <i class="bi bi-printer me-1"></i>Imprimir esta etiqueta
                    </a>
                <?php endif ?>
            </div>
        </div>

        <!-- Documentos -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-folder2-open me-2"></i>Documentos</div>
            <?php if ($documentos === []): ?>
                <div class="card-body text-muted small">
                    Nada guardado todavía. La factura y el manual aquí valen más que en un cajón:
                    el día de la avería están a mano, y desde el móvil.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($documentos as $d): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="text-truncate">
                                <i class="bi bi-file-earmark me-1 text-muted"></i>
                                <a href="<?= site_url('activos/documento/' . $d['id']) ?>"><?= esc($d['nombre_original']) ?></a>
                                <div class="small text-muted"><?= esc($tipos_doc[$d['tipo']]) ?></div>
                            </div>
                            <?php if (puede('activos.gestionar')): ?>
                                <form method="post" action="<?= site_url('activos/documento/borrar/' . $d['id']) ?>"
                                      onsubmit="return confirm('¿Borrar este documento?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <?php if (puede('activos.gestionar')): ?>
                <div class="card-body border-top">
                    <form method="post" action="<?= site_url('activos/documento/subir/' . $activo['id']) ?>"
                          enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                        <?= csrf_field() ?>
                        <select name="tipo" class="form-select form-select-sm" style="flex: 1; min-width: 130px;">
                            <?php foreach ($tipos_doc as $t => $etiqueta): ?>
                                <option value="<?= $t ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                        <input type="file" name="documento" class="form-control form-control-sm" required
                               accept=".pdf,image/jpeg,image/png,image/webp" style="flex: 2; min-width: 160px;">
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i></button>
                    </form>
                    <div class="form-text">PDF o imagen, hasta 8 MB.</div>
                </div>
            <?php endif ?>
        </div>

        <?php if (puede('activos.gestionar') && $activo['estado'] !== 'baja'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold text-muted"><i class="bi bi-box-arrow-down me-2"></i>Dar de baja</div>
                <div class="card-body">
                    <p class="small text-muted">
                        No se borra nada: su historial de averías y costos es justo lo que hace falta
                        para decidir de qué marca se compra el siguiente.
                    </p>
                    <form method="post" action="<?= site_url('activos/baja/' . $activo['id']) ?>"
                          onsubmit="return confirm('¿Dar de baja este equipo?')" class="d-flex gap-2 flex-wrap">
                        <?= csrf_field() ?>
                        <input type="text" name="baja_motivo" class="form-control form-control-sm" required
                               placeholder="Por qué (se cambió, se vendió, no compensa…)" style="flex: 2; min-width: 170px;">
                        <button class="btn btn-sm btn-outline-danger">Dar de baja</button>
                    </form>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
