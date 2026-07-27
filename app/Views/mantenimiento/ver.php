<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = [
    'abierta' => 'danger', 'en_proceso' => 'warning', 'pausada' => 'secondary',
    'resuelta' => 'info', 'verificada' => 'success', 'anulada' => 'secondary',
];
$nombreEstado = [
    'abierta' => 'Abierta', 'en_proceso' => 'En proceso', 'pausada' => 'En espera',
    'resuelta' => 'Resuelta, falta el visto bueno', 'verificada' => 'Cerrada', 'anulada' => 'Anulada',
];
$colorPrio = ['urgente' => 'danger', 'alta' => 'warning', 'media' => 'info', 'baja' => 'secondary'];

$viva      = in_array($orden['estado'], ['abierta', 'en_proceso', 'pausada'], true);
$fueraPlazo = $viva && $orden['vence_en'] !== null && $orden['vence_en'] < date('Y-m-d H:i:s');
$costoTotal = (float) $orden['costo_materiales'] + (float) $orden['costo_mano_obra'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="text-muted small">
            Orden #<?= $orden['id'] ?>
            · <?= $orden['tipo'] === 'preventiva' ? 'Preventiva' : 'Correctiva' ?>
            · abierta el <?= date('d/m/Y H:i', strtotime($orden['created_at'])) ?>
        </div>
        <h1 class="h3 mb-1"><?= esc($orden['titulo']) ?></h1>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge text-bg-<?= $colorEstado[$orden['estado']] ?>"><?= esc($nombreEstado[$orden['estado']]) ?></span>
            <span class="badge text-bg-<?= $colorPrio[$orden['prioridad']] ?>"><?= esc($prioridades[$orden['prioridad']]) ?></span>
            <?php if ($fueraPlazo): ?>
                <span class="badge text-bg-danger">
                    <i class="bi bi-clock me-1"></i>Fuera de plazo desde el <?= date('d/m H:i', strtotime($orden['vence_en'])) ?>
                </span>
            <?php elseif ($viva && $orden['vence_en'] !== null): ?>
                <span class="text-muted small">Debería estar antes del <?= date('d/m H:i', strtotime($orden['vence_en'])) ?></span>
            <?php endif ?>
        </div>
    </div>
    <a href="<?= site_url('mantenimiento') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Al tablero
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($orden['estado'] === 'resuelta' && $exige_visto): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-clipboard-check fs-5"></i>
        <div>
            <strong>Falta que otra persona la dé por buena.</strong>
            <?php if ((int) $orden['bloqueo_unidad'] === 1): ?>
                Hasta entonces la cabaña sigue bloqueada.
            <?php endif ?>
            Quien la arregló no puede firmarse su propio visto bueno.
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <!-- Qué pasa y dónde -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 col-sm-3 text-muted fw-normal">Dónde</dt>
                    <dd class="col-8 col-sm-9">
                        <?= esc($orden['unidad_nombre'] ?? $orden['ubicacion'] ?? 'Sin ubicación') ?>
                        <?php if ((int) $orden['bloqueo_unidad'] === 1 && $viva): ?>
                            <span class="badge text-bg-danger ms-1">Cabaña bloqueada</span>
                        <?php endif ?>
                    </dd>

                    <?php if ($orden['activo_nombre'] !== null): ?>
                        <dt class="col-4 col-sm-3 text-muted fw-normal">Equipo</dt>
                        <dd class="col-8 col-sm-9">
                            <a href="<?= site_url('activos/ver/' . $orden['activo_id']) ?>">
                                <?= esc($orden['activo_nombre']) ?>
                            </a>
                            <span class="font-monospace text-muted">· <?= esc($orden['activo_codigo']) ?></span>
                            <?php if ($orden['garantia_hasta'] !== null && $orden['garantia_hasta'] >= date('Y-m-d')): ?>
                                <div class="alert alert-warning py-1 px-2 mt-1 mb-0 small">
                                    <i class="bi bi-shield-check me-1"></i>
                                    <strong>En garantía hasta el <?= date('d/m/Y', strtotime($orden['garantia_hasta'])) ?>.</strong>
                                    Llama al proveedor antes de tocarlo: abrirlo puede anularla.
                                </div>
                            <?php endif ?>
                        </dd>
                    <?php endif ?>

                    <dt class="col-4 col-sm-3 text-muted fw-normal">Reportó</dt>
                    <dd class="col-8 col-sm-9">
                        <?= esc($orden['reporto_nombre'] ?? 'El huésped') ?>
                        <span class="text-muted">· <?= esc($origenes[$orden['origen']]) ?></span>
                    </dd>

                    <dt class="col-4 col-sm-3 text-muted fw-normal">A cargo de</dt>
                    <dd class="col-8 col-sm-9"><?= esc($orden['asignado_nombre']) ?: '<span class="text-muted">Sin repartir</span>' ?></dd>

                    <?php if ($orden['descripcion']): ?>
                        <dt class="col-12 text-muted fw-normal mt-2">Detalle</dt>
                        <dd class="col-12 mb-0"><?= nl2br(esc($orden['descripcion'])) ?></dd>
                    <?php endif ?>

                    <?php if ($orden['solucion']): ?>
                        <dt class="col-12 text-muted fw-normal mt-2">Qué se hizo</dt>
                        <dd class="col-12 mb-0">
                            <?= nl2br(esc($orden['solucion'])) ?>
                            <div class="text-muted mt-1">
                                <?= esc($orden['resolvio_nombre']) ?>
                                <?php if ($orden['resuelta_en']): ?>
                                    · <?= date('d/m/Y H:i', strtotime($orden['resuelta_en'])) ?>
                                <?php endif ?>
                                <?php if ($orden['minutos'] !== null): ?>
                                    · <?= (int) $orden['minutos'] ?> minutos
                                <?php endif ?>
                            </div>
                        </dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <!-- Fotos -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-camera me-2"></i>Fotos</div>
            <div class="card-body">
                <?php if ($fotos === []): ?>
                    <p class="text-muted small mb-3">
                        Ninguna todavía. Una foto de cómo estaba y otra de cómo quedó es lo que permite
                        dar por buena una reparación sin ir hasta la cabaña.
                    </p>
                <?php else: ?>
                    <div class="row g-2 mb-3">
                        <?php foreach ($fotos as $f): ?>
                            <div class="col-6 col-md-3">
                                <a href="<?= site_url('mantenimiento/foto/' . $f['id']) ?>" target="_blank" rel="noopener">
                                    <img src="<?= site_url('mantenimiento/foto/' . $f['id']) ?>" class="img-fluid rounded border"
                                         alt="<?= esc($momentos[$f['momento']]) ?>" style="aspect-ratio: 4/3; object-fit: cover;">
                                </a>
                                <div class="small text-muted mt-1">
                                    <?= esc($momentos[$f['momento']]) ?>
                                    <?php if ($f['usuario_nombre']): ?>
                                        · <?= esc($f['usuario_nombre']) ?>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <?php if (puede('mantenimiento.trabajar') && $orden['estado'] !== 'anulada'): ?>
                    <form method="post" action="<?= site_url('mantenimiento/foto/subir/' . $orden['id']) ?>"
                          enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                        <?= csrf_field() ?>
                        <select name="momento" class="form-select form-select-sm" style="flex: 1; min-width: 130px;">
                            <?php foreach ($momentos as $m => $etiqueta): ?>
                                <option value="<?= $m ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                        <input type="file" name="foto" class="form-control form-control-sm" required
                               accept="image/*" capture="environment" style="flex: 2; min-width: 160px;">
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i></button>
                    </form>
                <?php endif ?>
            </div>
        </div>

        <!-- Materiales -->
        <?php if ($ver_costos): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="bi bi-wrench me-2"></i>Materiales</span>
                    <?php if ($costoTotal > 0): ?>
                        <span class="badge text-bg-secondary fs-6">
                            Total: $<?= number_format($costoTotal, 0, ',', '.') ?>
                        </span>
                    <?php endif ?>
                </div>

                <?php if ($materiales !== []): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Qué</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Coste</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materiales as $m): ?>
                                    <tr>
                                        <td>
                                            <?= esc($m['descripcion']) ?>
                                            <?php if ($m['bodega_nombre']): ?>
                                                <div class="small text-muted">Del almacén: <?= esc($m['bodega_nombre']) ?></div>
                                            <?php else: ?>
                                                <div class="small text-muted">Comprado fuera</div>
                                            <?php endif ?>
                                        </td>
                                        <td class="text-end">
                                            <?= rtrim(rtrim(number_format((float) $m['cantidad'], 3, ',', '.'), '0'), ',') ?>
                                            <?= esc($m['insumo_unidad'] ?? '') ?>
                                        </td>
                                        <td class="text-end">$<?= number_format((float) $m['costo_total'], 0, ',', '.') ?></td>
                                        <td class="text-end">
                                            <?php if (puede('mantenimiento.trabajar')): ?>
                                                <form method="post" action="<?= site_url('mantenimiento/material/quitar/' . $m['id']) ?>"
                                                      onsubmit="return confirm('¿Quitarlo y devolverlo al almacén?')">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                            <?php if ((float) $orden['costo_mano_obra'] > 0): ?>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="text-muted">Mano de obra (<?= (int) $orden['minutos'] ?> min)</td>
                                        <td class="text-end">$<?= number_format((float) $orden['costo_mano_obra'], 0, ',', '.') ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            <?php endif ?>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card-body text-muted small">
                        Nada apuntado. Lo que sale del almacén sin apuntarse falta el día del conteo
                        y nadie sabe por qué.
                    </div>
                <?php endif ?>

                <?php if (puede('mantenimiento.trabajar') && $orden['estado'] !== 'anulada'): ?>
                    <div class="card-body border-top">
                        <form method="post" action="<?= site_url('mantenimiento/material/' . $orden['id']) ?>" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Del almacén</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <select name="insumo_id" class="form-select form-select-sm" style="flex: 2; min-width: 150px;">
                                        <option value="">No sale del almacén</option>
                                        <?php foreach ($insumos as $i): ?>
                                            <option value="<?= $i['id'] ?>"><?= esc($i['nombre']) ?> (<?= esc($i['unidad']) ?>)</option>
                                        <?php endforeach ?>
                                    </select>
                                    <select name="bodega_id" class="form-select form-select-sm" style="flex: 1; min-width: 130px;">
                                        <option value="">Almacén…</option>
                                        <?php foreach ($bodegas as $b): ?>
                                            <option value="<?= $b['id'] ?>" <?= $b['tipo'] === 'mantenimiento' ? 'selected' : '' ?>>
                                                <?= esc($b['nombre']) ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">O comprado fuera</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <input type="text" name="descripcion" class="form-control form-control-sm"
                                           placeholder="Qué se gastó" style="flex: 2; min-width: 150px;">
                                    <input type="number" name="cantidad" class="form-control form-control-sm" min="0.001" step="any"
                                           value="1" required style="flex: 1; min-width: 80px;" title="Cantidad">
                                    <input type="number" name="costo_unitario" class="form-control form-control-sm" min="0" step="any"
                                           placeholder="Precio" style="flex: 1; min-width: 90px;">
                                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i></button>
                                </div>
                                <div class="form-text">
                                    Si eliges un insumo del almacén, el precio se coge solo del coste medio.
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif ?>
            </div>
        <?php endif ?>
    </div>

    <!-- ═══ Acciones ═══ -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-lightning me-2"></i>Qué hacer ahora</div>
            <div class="card-body d-grid gap-3">

                <?php if (puede('mantenimiento.trabajar') && in_array($orden['estado'], ['abierta', 'pausada'], true)): ?>
                    <form method="post" action="<?= site_url('mantenimiento/iniciar/' . $orden['id']) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary w-100">
                            <i class="bi bi-play-fill me-1"></i><?= $orden['estado'] === 'pausada' ? 'Retomarla' : 'Empezar' ?>
                        </button>
                    </form>
                <?php endif ?>

                <?php if (puede('mantenimiento.trabajar') && $orden['estado'] === 'en_proceso'): ?>
                    <form method="post" action="<?= site_url('mantenimiento/resolver/' . $orden['id']) ?>">
                        <?= csrf_field() ?>
                        <label class="form-label">Qué se hizo</label>
                        <textarea name="solucion" class="form-control mb-2" rows="3" required
                                  placeholder="Dentro de un año, esto es lo único que quedará."></textarea>
                        <button class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Darla por resuelta</button>
                    </form>

                    <form method="post" action="<?= site_url('mantenimiento/pausar/' . $orden['id']) ?>" class="border-top pt-3">
                        <?= csrf_field() ?>
                        <label class="form-label small text-muted">¿A qué está esperando?</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="text" name="motivo" class="form-control form-control-sm" required
                                   placeholder="Falta el repuesto, viene el técnico el jueves…" style="flex: 2; min-width: 150px;">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pause-fill"></i></button>
                        </div>
                        <div class="form-text">Deja de aparecer como pendiente de que alguien la coja, pero el plazo sigue corriendo.</div>
                    </form>
                <?php endif ?>

                <?php if (puede('mantenimiento.verificar') && $orden['estado'] === 'resuelta'): ?>
                    <form method="post" action="<?= site_url('mantenimiento/verificar/' . $orden['id']) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-success w-100">
                            <i class="bi bi-patch-check me-1"></i>Darla por buena y cerrarla
                        </button>
                    </form>

                    <form method="post" action="<?= site_url('mantenimiento/rechazar/' . $orden['id']) ?>" class="border-top pt-3">
                        <?= csrf_field() ?>
                        <label class="form-label small text-muted">O devolverla porque falta algo</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="text" name="motivo" class="form-control form-control-sm" required
                                   placeholder="Qué falta" style="flex: 2; min-width: 150px;">
                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </form>
                <?php endif ?>

                <?php if ($orden['estado'] === 'verificada'): ?>
                    <div class="text-center text-success py-2">
                        <i class="bi bi-patch-check-fill fs-2 d-block mb-1"></i>
                        Cerrada y dada por buena.
                    </div>
                <?php elseif ($orden['estado'] === 'anulada'): ?>
                    <div class="text-center text-muted py-2">
                        <i class="bi bi-slash-circle fs-2 d-block mb-1"></i>
                        Anulada.
                    </div>
                <?php elseif ($orden['estado'] === 'resuelta' && ! $exige_visto): ?>
                    <div class="text-center text-success py-2">
                        <i class="bi bi-check-circle-fill fs-2 d-block mb-1"></i>
                        Resuelta.
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php if ($viva): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2"></i>Organizarla</div>
                <div class="card-body d-grid gap-3">
                    <?php if (puede('mantenimiento.asignar')): ?>
                        <form method="post" action="<?= site_url('mantenimiento/asignar/' . $orden['id']) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label small text-muted mb-1">A cargo de</label>
                            <div class="d-flex gap-2">
                                <select name="asignado_a" class="form-select form-select-sm">
                                    <option value="">Sin repartir</option>
                                    <?php foreach ($tecnicos as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= (int) $orden['asignado_a'] === (int) $t['id'] ? 'selected' : '' ?>>
                                            <?= esc($t['nombre']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i></button>
                            </div>
                        </form>
                    <?php endif ?>

                    <?php if (puede('mantenimiento.prioridad')): ?>
                        <form method="post" action="<?= site_url('mantenimiento/prioridad/' . $orden['id']) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label small text-muted mb-1">Cuánto corre</label>
                            <div class="d-flex gap-2">
                                <select name="prioridad" class="form-select form-select-sm">
                                    <?php foreach ($prioridades as $p => $etiqueta): ?>
                                        <option value="<?= $p ?>" <?= $orden['prioridad'] === $p ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i></button>
                            </div>
                            <div class="form-text">El plazo se recalcula desde que se abrió, no desde ahora.</div>
                        </form>
                    <?php endif ?>

                    <?php if (puede('mantenimiento.prioridad')): ?>
                        <form method="post" action="<?= site_url('mantenimiento/anular/' . $orden['id']) ?>" class="border-top pt-3"
                              onsubmit="return confirm('¿Anular esta orden?')">
                            <?= csrf_field() ?>
                            <label class="form-label small text-muted mb-1">No era una avería</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <input type="text" name="motivo" class="form-control form-control-sm" required
                                       placeholder="Por qué se anula" style="flex: 2; min-width: 140px;">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle"></i></button>
                            </div>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
