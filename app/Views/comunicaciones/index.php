<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = [
    'pendiente' => 'secondary', 'enviado' => 'success', 'fallido' => 'danger',
    'cancelado' => 'dark', 'rebotado' => 'warning',
];
$colorFinalidad = ['operativo' => 'primary', 'encuestas' => 'info', 'marketing' => 'warning'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Comunicaciones</h1>
        <p class="text-muted mb-0">Lo que se le manda al huésped, y si de verdad salió.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('comunicaciones/plantillas') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-text me-1"></i>Plantillas
        </a>
        <?php if (puede('comunicaciones.enviar')): ?>
            <form method="post" action="<?= site_url('comunicaciones/encolar') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-primary"><i class="bi bi-calendar-plus me-1"></i>Encolar lo de hoy</button>
            </form>
            <form method="post" action="<?= site_url('comunicaciones/procesar') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Mandar la cola</button>
            </form>
        <?php endif ?>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if (! $correo_ok): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-envelope-exclamation fs-5"></i>
        <div>
            <strong>No hay servidor de correo configurado.</strong>
            Los mensajes se encolan igual, pero no sale ninguno. Configúralo en
            <a href="<?= site_url('administracion') ?>">Administración</a> y se mandarán solos.
        </div>
    </div>
<?php endif ?>

<div class="row g-3 mb-4">
    <?php foreach ($estados as $e => $etiqueta): ?>
        <div class="col-6 col-lg">
            <a href="<?= site_url('comunicaciones?estado=' . $e) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= $filtro === $e ? 'border-start border-4 border-' . $colorEstado[$e] : '' ?>">
                    <div class="card-body py-3">
                        <div class="text-muted small"><?= esc($etiqueta) ?></div>
                        <div class="fs-3 fw-semibold text-<?= $colorEstado[$e] ?>"><?= (int) $conteo[$e] ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<!-- ═══ Automatizaciones ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-lightning-charge me-2 text-warning"></i>Cuándo se manda qué
    </div>
    <div class="card-body py-2">
        <p class="small text-muted mb-0">
            La columna de la derecha dice <strong>a cuántos les tocaría hoy</strong>. Míralo antes
            de encender nada: encender una campaña a ciegas y descubrir después a quién le llegó
            es la peor forma de estrenar esto.
        </p>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Regla</th>
                    <th class="d-none d-md-table-cell">Cuándo</th>
                    <th>Días</th>
                    <th>Hora</th>
                    <th class="text-center">Hoy</th>
                    <th class="text-center">Activa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reglas as $i => $r): ?>
                    <?php $f = 'regla-' . $r['id']; ?>
                    <tr class="<?= (int) $r['activa'] === 0 ? 'opacity-50' : '' ?>">
                        <td>
                            <span class="fw-semibold"><?= esc($r['nombre']) ?></span>
                            <?php if ($r['finalidad'] ?? null): ?>
                                <span class="badge text-bg-<?= $colorFinalidad[$r['finalidad']] ?? 'secondary' ?> ms-1">
                                    <?= $r['finalidad'] === 'operativo' ? 'No pide permiso' : 'Pide permiso' ?>
                                </span>
                            <?php endif ?>
                            <div class="small text-muted"><?= esc($r['plantilla_nombre'] ?? $r['plantilla_clave']) ?></div>
                        </td>
                        <td class="small text-muted d-none d-md-table-cell"><?= esc($eventos[$r['evento']]) ?></td>
                        <td style="width: 90px;">
                            <input type="number" name="dias" form="<?= $f ?>" class="form-control form-control-sm"
                                   value="<?= (int) $r['dias'] ?>" <?= puede('comunicaciones.plantillas') ? '' : 'disabled' ?>>
                        </td>
                        <td style="width: 110px;">
                            <input type="time" name="hora" form="<?= $f ?>" class="form-control form-control-sm"
                                   value="<?= esc(substr((string) $r['hora'], 0, 5)) ?>" <?= puede('comunicaciones.plantillas') ? '' : 'disabled' ?>>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-<?= ($ensayo[$i]['cuantos'] ?? 0) > 0 ? 'primary' : 'light text-dark' ?>">
                                <?= $ensayo[$i]['cuantos'] ?? 0 ?>
                            </span>
                        </td>
                        <td class="text-center" style="width: 110px;">
                            <?php if (puede('comunicaciones.plantillas')): ?>
                                <div class="form-check form-switch d-inline-block align-middle">
                                    <input class="form-check-input" type="checkbox" name="activa" value="1" form="<?= $f ?>"
                                           <?= (int) $r['activa'] === 1 ? 'checked' : '' ?>>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" form="<?= $f ?>"><i class="bi bi-check-lg"></i></button>
                            <?php else: ?>
                                <i class="bi bi-<?= (int) $r['activa'] === 1 ? 'check-circle text-success' : 'x-circle text-muted' ?>"></i>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php // Los formularios van fuera de la tabla a propósito. Un <form> entre
          // <tr> y <td> es HTML inválido: el navegador lo saca de la tabla y los
          // campos dejan de enviarse. El atributo `form` los reconecta. ?>
    <?php foreach ($reglas as $r): ?>
        <form method="post" action="<?= site_url('comunicaciones/regla/' . $r['id']) ?>" id="regla-<?= $r['id'] ?>">
            <?= csrf_field() ?>
        </form>
    <?php endforeach ?>
</div>

<!-- ═══ La cola ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-envelope me-2"></i>Últimos mensajes</span>
        <?php if ($filtro !== ''): ?>
            <a href="<?= site_url('comunicaciones') ?>" class="btn btn-sm btn-outline-secondary">Ver todos</a>
        <?php endif ?>
    </div>

    <?php if ($envios === []): ?>
        <div class="card-body text-muted small">
            Nada todavía. Los mensajes aparecerán aquí en cuanto haya reservas que los disparen.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Para</th>
                        <th class="d-none d-md-table-cell">Asunto</th>
                        <th>Estado</th>
                        <th class="d-none d-lg-table-cell">Cuándo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($envios as $e): ?>
                        <tr>
                            <td>
                                <?php if ($e['huesped_id']): ?>
                                    <a href="<?= site_url('huespedes/ver/' . $e['huesped_id']) ?>" class="text-decoration-none fw-semibold">
                                        <?= esc(trim(($e['huesped_nombre'] ?? '') . ' ' . ($e['huesped_apellidos'] ?? ''))) ?: esc($e['destino']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= esc($e['destino']) ?>
                                <?php endif ?>
                                <div class="small text-muted">
                                    <?= esc($e['destino']) ?>
                                    <?php if ($e['reserva_codigo']): ?>
                                        · <?= esc($e['reserva_codigo']) ?>
                                    <?php endif ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell small"><?= esc($e['asunto']) ?></td>
                            <td>
                                <span class="badge text-bg-<?= $colorEstado[$e['estado']] ?>"><?= esc($estados[$e['estado']]) ?></span>
                                <?php if ($e['abierto_en'] !== null): ?>
                                    <span class="badge text-bg-light text-dark ms-1" title="Lo abrió">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                <?php endif ?>
                                <?php if ((int) $e['intentos'] > 1): ?>
                                    <div class="small text-muted"><?= (int) $e['intentos'] ?> intentos</div>
                                <?php endif ?>
                            </td>
                            <td class="small text-muted d-none d-lg-table-cell">
                                <?= date('d/m H:i', strtotime($e['enviado_en'] ?? $e['programado_para'])) ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('comunicaciones/ver/' . $e['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
