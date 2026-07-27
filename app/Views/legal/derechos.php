<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['recibida' => 'danger', 'en_tramite' => 'warning', 'atendida' => 'success', 'rechazada' => 'secondary'];
$hoy = date('Y-m-d');
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Derechos del titular</h1>
        <p class="text-muted mb-0">Gente pidiendo ver, corregir o borrar sus datos.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (puede('datos.derechos')): ?>
            <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formNueva">
                <i class="bi bi-plus-lg me-1"></i>Registrar una
            </button>
        <?php endif ?>
        <a href="<?= site_url('legal') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="alert alert-light border small">
    <i class="bi bi-shield-lock me-1"></i>
    Cualquiera puede pedir que le digamos qué datos suyos tenemos, que los corrijamos o que los
    borremos. <strong>Antes de entregar nada hay que estar seguro de quién pide</strong>: darle los
    datos de alguien a quien no es esa persona es la misma infracción que se intenta evitar, hecha
    por escrito.
</div>

<?php if (puede('datos.derechos')): ?>
    <div class="collapse mb-4" id="formNueva">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= site_url('legal/derecho') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-4">
                        <label class="form-label">Qué pide</label>
                        <select name="tipo" class="form-select">
                            <?php foreach ($tipos as $t => $etiqueta): ?>
                                <option value="<?= $t ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombre de quien pide</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Su documento</label>
                        <input type="text" name="documento" class="form-control" required maxlength="50">
                        <div class="form-text">Con esto se busca su perfil.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Correo</label>
                        <input type="email" name="email" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control" maxlength="30">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Detalle</label>
                        <textarea name="detalle" class="form-control" rows="3"
                                  placeholder="Qué pide exactamente, con sus palabras."></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
            <label class="small text-muted mb-0">Ver</label>
            <select name="estado" class="form-select form-select-sm" style="max-width: 200px;"
                    onchange="this.form.submit()">
                <option value="abiertas" <?= $estado === 'abiertas' ? 'selected' : '' ?>>Sin atender</option>
                <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todas</option>
                <?php foreach ($estados as $e => $etiqueta): ?>
                    <option value="<?= $e ?>" <?= $estado === $e ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                <?php endforeach ?>
            </select>
        </form>
    </div>
</div>

<?php if ($lista === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-person-lock fs-1 d-block mb-3"></i>
            Ninguna solicitud. Que no las haya no significa que la gente no tenga derecho:
            significa que todavía nadie lo ha usado.
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Quién y qué pide</th>
                        <th class="d-none d-md-table-cell">Identificado</th>
                        <th>Estado</th>
                        <th>Plazo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $s): ?>
                        <?php $vencida = $s['vence_en'] !== null && $s['vence_en'] < $hoy
                            && in_array($s['estado'], ['recibida', 'en_tramite'], true); ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('legal/derecho/' . $s['id']) ?>" class="font-monospace text-decoration-none">
                                    <?= esc($s['codigo']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= site_url('legal/derecho/' . $s['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($s['nombre']) ?>
                                </a>
                                <div class="small text-muted"><?= esc($tipos[$s['tipo']]) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?php if ((int) $s['identificado'] === 1): ?>
                                    <i class="bi bi-check-circle text-success"></i>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">Falta comprobar</span>
                                <?php endif ?>
                            </td>
                            <td><span class="badge text-bg-<?= $colorEstado[$s['estado']] ?>"><?= esc($estados[$s['estado']]) ?></span></td>
                            <td class="small">
                                <?php if ($s['vence_en'] === null): ?>
                                    —
                                <?php else: ?>
                                    <span class="<?= $vencida ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                        <?= date('d/m/Y', strtotime($s['vence_en'])) ?>
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

<?= $this->endSection() ?>
