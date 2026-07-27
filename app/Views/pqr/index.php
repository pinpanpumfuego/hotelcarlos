<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorTipo = [
    'peticion' => 'info', 'queja' => 'warning', 'reclamo' => 'danger',
    'sugerencia' => 'secondary', 'felicitacion' => 'success',
];
$colorEstado = [
    'recibida' => 'danger', 'en_gestion' => 'warning', 'respondida' => 'info',
    'cerrada' => 'success', 'reabierta' => 'danger',
];
$hoy = date('Y-m-d');
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Quejas y peticiones</h1>
        <p class="text-muted mb-0">Ordenadas por plazo, no por fecha: primero lo que se queda sin tiempo.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('pqr/informe') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-graph-up me-1"></i>Informe
        </a>
        <?php if (puede('pqr.gestionar')): ?>
            <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formPqr">
                <i class="bi bi-plus-lg me-1"></i>Registrar una
            </button>
        <?php endif ?>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if ($riesgo !== []): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-clock-history me-1"></i>Se están quedando sin plazo</strong>
        <div class="small mt-1">
            La Ley 1755/2015 da quince días hábiles para contestar una queja o un reclamo.
            Pasado el plazo no se arregla contestando después.
        </div>
        <ul class="mb-0 mt-2 small">
            <?php foreach ($riesgo as $r): ?>
                <li>
                    <a href="<?= site_url('pqr/ver/' . $r['id']) ?>" class="fw-semibold">
                        <?= esc($r['codigo']) ?>
                    </a>
                    · <?= esc($r['asunto']) ?>
                    · vence el <?= date('d/m/Y', strtotime($r['vence_en'])) ?>
                    <?php if ($r['vence_en'] < $hoy): ?>
                        <span class="badge text-bg-dark ms-1">Ya vencida</span>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<!-- ═══ Registrar ═══ -->
<?php if (puede('pqr.gestionar')): ?>
    <div class="collapse mb-4" id="formPqr">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= site_url('pqr/guardar') ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-md-3">
                        <label class="form-label">Qué es</label>
                        <select name="tipo" class="form-select" id="tipoPqr">
                            <?php foreach ($tipos as $t => $etiqueta): ?>
                                <option value="<?= $t ?>" data-plazo="<?= $plazos[$t] ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text" id="plazoAviso"></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Por dónde entró</label>
                        <select name="canal_entrada" class="form-select">
                            <?php foreach ($canales as $c => $etiqueta): ?>
                                <option value="<?= $c ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text">Si vino por Booking, hay que contestar también allí.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Prioridad</label>
                        <select name="prioridad" class="form-select">
                            <?php foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'urgente' => 'Urgente'] as $p => $e): ?>
                                <option value="<?= $p ?>" <?= $p === 'media' ? 'selected' : '' ?>><?= $e ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">A cargo de</label>
                        <select name="responsable_id" class="form-select">
                            <option value="">Sin repartir</option>
                            <?php foreach ($responsables as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= esc($r['nombre']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Resumen</label>
                        <input type="text" name="asunto" class="form-control" required maxlength="200"
                               placeholder="La ducha no calentaba en toda la estancia">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Qué pasó</label>
                        <textarea name="detalle" class="form-control" rows="4" required
                                  placeholder="Con las palabras del huésped si puede ser. Dentro de un mes nadie se acordará del tono."></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Nombre de quien la pone</label>
                        <input type="text" name="nombre_contacto" class="form-control" maxlength="150">
                        <div class="form-text">Si no es un huésped registrado.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Su correo</label>
                        <input type="email" name="email_contacto" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Su teléfono</label>
                        <input type="tel" name="telefono_contacto" class="form-control" maxlength="30">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif ?>

<!-- ═══ Filtros ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="estado" class="form-select">
                    <option value="abiertas" <?= $filtros['estado'] === 'abiertas' ? 'selected' : '' ?>>Sin cerrar</option>
                    <option value="">Todas</option>
                    <?php foreach ($estados as $e => $etiqueta): ?>
                        <option value="<?= $e ?>" <?= $filtros['estado'] === $e ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $t => $etiqueta): ?>
                        <option value="<?= $t ?>" <?= $filtros['tipo'] === $t ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">A cargo de</label>
                <select name="responsable_id" class="form-select">
                    <option value="">Cualquiera</option>
                    <?php foreach ($responsables as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $filtros['responsable_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                            <?= esc($r['nombre']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($lista === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-chat-square-heart fs-1 d-block mb-3"></i>
            Nada por aquí. Que no haya quejas registradas no siempre significa que no las haya:
            a veces significa que no se están recogiendo.
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Asunto</th>
                        <th class="d-none d-md-table-cell">Quién</th>
                        <th>Estado</th>
                        <th>Plazo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $p): ?>
                        <?php $vencida = $p['vence_en'] !== null && $p['vence_en'] < $hoy
                            && in_array($p['estado'], ['recibida', 'en_gestion', 'reabierta'], true); ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('pqr/ver/' . $p['id']) ?>" class="font-monospace text-decoration-none">
                                    <?= esc($p['codigo']) ?>
                                </a>
                                <div>
                                    <span class="badge text-bg-<?= $colorTipo[$p['tipo']] ?>"><?= esc($tipos[$p['tipo']]) ?></span>
                                </div>
                            </td>
                            <td>
                                <a href="<?= site_url('pqr/ver/' . $p['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($p['asunto']) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= esc($canales[$p['canal_entrada']]) ?>
                                    <?php if ($p['responsable_nombre']): ?>
                                        · <?= esc($p['responsable_nombre']) ?>
                                    <?php else: ?>
                                        · <span class="text-warning">sin repartir</span>
                                    <?php endif ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell small">
                                <?php if ($p['huesped_id']): ?>
                                    <a href="<?= site_url('huespedes/ver/' . $p['huesped_id']) ?>" class="text-decoration-none">
                                        <?= esc(trim(($p['huesped_nombre'] ?? '') . ' ' . ($p['huesped_apellidos'] ?? ''))) ?>
                                    </a>
                                <?php else: ?>
                                    <?= esc($p['nombre_contacto']) ?: '—' ?>
                                <?php endif ?>
                                <?php if ($p['reserva_codigo']): ?>
                                    <div class="text-muted font-monospace" style="font-size: .75rem;"><?= esc($p['reserva_codigo']) ?></div>
                                <?php endif ?>
                            </td>
                            <td><span class="badge text-bg-<?= $colorEstado[$p['estado']] ?>"><?= esc($estados[$p['estado']]) ?></span></td>
                            <td class="small">
                                <?php if ($p['vence_en'] === null): ?>
                                    —
                                <?php else: ?>
                                    <span class="<?= $vencida ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                        <?= date('d/m/Y', strtotime($p['vence_en'])) ?>
                                    </span>
                                    <?php if ($vencida): ?>
                                        <div><span class="badge text-bg-danger">Fuera de plazo</span></div>
                                    <?php endif ?>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<script>
    // Enseñar el plazo legal al elegir el tipo: es lo que hace que quien la
    // registra entienda que esto no es una nota, es un reloj que empieza.
    (function () {
        var sel = document.getElementById('tipoPqr');
        var avi = document.getElementById('plazoAviso');
        if (!sel || !avi) { return; }

        function pintar() {
            var d = sel.options[sel.selectedIndex].dataset.plazo;
            avi.textContent = d + ' días hábiles para contestar.';
        }

        sel.addEventListener('change', pintar);
        pintar();
    })();
</script>

<?= $this->endSection() ?>
