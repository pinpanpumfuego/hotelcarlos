<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['recibida' => 'danger', 'en_tramite' => 'warning', 'atendida' => 'success', 'rechazada' => 'secondary'];
$abierta = in_array($s['estado'], ['recibida', 'en_tramite'], true);
$vencida = $s['vence_en'] !== null && $s['vence_en'] < date('Y-m-d') && $abierta;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="font-monospace text-muted small"><?= esc($s['codigo']) ?></div>
        <h1 class="h3 mb-1"><?= esc($tipos[$s['tipo']]) ?></h1>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge text-bg-<?= $colorEstado[$s['estado']] ?>"><?= esc($estados[$s['estado']]) ?></span>
            <span class="text-muted small">
                Recibida el <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                <?php if ($s['vence_en']): ?>
                    · plazo hasta el <?= date('d/m/Y', strtotime($s['vence_en'])) ?>
                <?php endif ?>
            </span>
        </div>
    </div>
    <a href="<?= site_url('legal/derechos') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($vencida): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-octagon me-1"></i>Fuera del plazo legal.</strong>
        Contestar ahora no lo arregla, pero dejarlo más lo empeora.
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2"></i>Quién lo pide</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted fw-normal">Nombre</dt>
                    <dd class="col-8"><?= esc($s['nombre']) ?></dd>
                    <dt class="col-4 text-muted fw-normal">Documento</dt>
                    <dd class="col-8 font-monospace"><?= esc($s['documento']) ?></dd>
                    <dt class="col-4 text-muted fw-normal">Correo</dt>
                    <dd class="col-8"><?= esc($s['email']) ?: '—' ?></dd>
                    <dt class="col-4 text-muted fw-normal">Teléfono</dt>
                    <dd class="col-8"><?= esc($s['telefono']) ?: '—' ?></dd>
                    <dt class="col-4 text-muted fw-normal">Su perfil</dt>
                    <dd class="col-8">
                        <?php if ($s['huesped_id']): ?>
                            <a href="<?= site_url('huespedes/ver/' . $s['huesped_id']) ?>">
                                <?= esc(trim(($s['huesped_nombre'] ?? '') . ' ' . ($s['huesped_apellidos'] ?? ''))) ?>
                            </a>
                        <?php elseif ($probable !== null): ?>
                            <span class="text-warning">Sin enlazar.</span>
                            Podría ser
                            <a href="<?= site_url('huespedes/ver/' . $probable['id']) ?>">
                                <?= esc(trim($probable['nombre'] . ' ' . $probable['apellidos'])) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">No hay ningún perfil con ese documento.</span>
                        <?php endif ?>
                    </dd>
                    <?php if ($s['detalle']): ?>
                        <dt class="col-12 text-muted fw-normal mt-2">Qué pide</dt>
                        <dd class="col-12 mb-0" style="white-space: pre-wrap;"><?= esc($s['detalle']) ?></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <?php if ($s['respuesta']): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-success">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-reply me-2"></i>Lo que se le contestó</div>
                <div class="card-body">
                    <div style="white-space: pre-wrap;"><?= esc($s['respuesta']) ?></div>
                    <div class="small text-muted mt-2">
                        <?= date('d/m/Y H:i', strtotime($s['atendida_en'])) ?>
                        <?php if ($s['atendio_nombre']): ?>· <?= esc($s['atendio_nombre']) ?><?php endif ?>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <?php if ($s['motivo_rechazo']): ?>
            <div class="card border-0 shadow-sm border-start border-4 border-secondary">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-x-circle me-2"></i>Por qué se rechazó</div>
                <div class="card-body"><?= esc($s['motivo_rechazo']) ?></div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-5">
        <!-- ═══ Identificación ═══ -->
        <div class="card border-0 shadow-sm mb-4 <?= (int) $s['identificado'] === 1 ? '' : 'border-start border-4 border-warning' ?>">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-patch-check me-2"></i>Comprobar quién es
            </div>
            <div class="card-body">
                <?php if ((int) $s['identificado'] === 1): ?>
                    <div class="d-flex gap-2 align-items-start">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <div>
                            <strong>Identidad confirmada.</strong>
                            <div class="small text-muted"><?= esc($s['como_identifico']) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="small text-muted">
                        <strong>Este paso no se puede saltar.</strong> Entregarle los datos de alguien a
                        quien no es esa persona es la misma infracción que se intenta evitar, hecha por
                        escrito y con acuse de recibo.
                    </p>
                    <?php if (puede('datos.derechos')): ?>
                        <form method="post" action="<?= site_url('legal/derecho/identificar/' . $s['id']) ?>">
                            <?= csrf_field() ?>
                            <?php if ($s['huesped_id'] === null && $probable !== null): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="huesped_id"
                                           value="<?= $probable['id'] ?>" id="enlazar" checked>
                                    <label class="form-check-label small" for="enlazar">
                                        Enlazar con <?= esc(trim($probable['nombre'] . ' ' . $probable['apellidos'])) ?>
                                    </label>
                                </div>
                            <?php endif ?>
                            <label class="form-label small text-muted mb-1">Cómo lo comprobaste</label>
                            <input type="text" name="como_identifico" class="form-control form-control-sm mb-2"
                                   required maxlength="200" placeholder="Vino con su cédula, la coteje con el registro…">
                            <button class="btn btn-warning w-100 btn-sm">
                                <i class="bi bi-patch-check me-1"></i>Confirmo que es esa persona
                            </button>
                        </form>
                    <?php endif ?>
                <?php endif ?>
            </div>
        </div>

        <!-- ═══ Entregar ═══ -->
        <?php if (puede('datos.derechos')): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-send me-2"></i>Atenderla</div>
                <div class="card-body d-grid gap-3">
                    <?php if ((int) $s['identificado'] === 1 && $s['huesped_id'] !== null): ?>
                        <a href="<?= site_url('legal/derecho/expediente/' . $s['id']) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-download me-1"></i>Bajar todo lo que tenemos de él
                        </a>
                        <div class="form-text mt-0">
                            Sus datos, sus estancias, sus preferencias y el rastro completo de qué
                            autorizó y cuándo. <strong>Las fotos del documento y la firma no van
                            dentro</strong>: se dicen, pero se entregan en mano.
                        </div>
                    <?php endif ?>

                    <?php if ($abierta): ?>
                        <form method="post" action="<?= site_url('legal/derecho/atender/' . $s['id']) ?>" class="border-top pt-3">
                            <?= csrf_field() ?>
                            <label class="form-label">Qué se le contestó</label>
                            <textarea name="respuesta" class="form-control mb-2" rows="4" required
                                      placeholder="Qué se le entregó, qué se corrigió o qué se borró."></textarea>
                            <button class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Darla por atendida</button>
                        </form>

                        <form method="post" action="<?= site_url('legal/derecho/rechazar/' . $s['id']) ?>" class="border-top pt-3">
                            <?= csrf_field() ?>
                            <label class="form-label small text-muted">O rechazarla, con motivo</label>
                            <input type="text" name="motivo_rechazo" class="form-control form-control-sm mb-2"
                                   required maxlength="300" placeholder="Por qué no procede">
                            <button class="btn btn-outline-danger w-100 btn-sm">
                                <i class="bi bi-x-circle me-1"></i>Rechazarla
                            </button>
                            <div class="form-text">
                                Hay peticiones que no proceden, pero el motivo tiene que poder explicarse.
                            </div>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
