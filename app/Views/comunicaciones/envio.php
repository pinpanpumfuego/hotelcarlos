<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = [
    'pendiente' => 'secondary', 'enviado' => 'success', 'fallido' => 'danger',
    'cancelado' => 'dark', 'rebotado' => 'warning',
];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Mensaje #<?= $envio['id'] ?></h1>
        <p class="text-muted mb-0">
            <span class="badge text-bg-<?= $colorEstado[$envio['estado']] ?>"><?= esc($estados[$envio['estado']]) ?></span>
            · <?= esc($envio['canal']) ?>
            · <?= $envio['finalidad'] === 'operativo' ? 'aviso de su reserva' : 'necesitaba su permiso' ?>
        </p>
    </div>
    <a href="<?= site_url('comunicaciones') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($envio['error']): ?>
    <div class="alert alert-danger">
        <strong>Qué pasó:</strong> <?= esc($envio['error']) ?>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="small text-muted">Para <?= esc($envio['destino']) ?></div>
                <div class="fw-semibold"><?= esc($envio['asunto']) ?: '(sin asunto)' ?></div>
            </div>
            <div class="card-body">
                <?php // El texto guardado, tal y como salió. Si mañana se cambia
                      // la plantilla, esto tiene que seguir siendo lo que se
                      // mandó: es la única forma de contestar «yo no recibí eso». ?>
                <div style="white-space: pre-wrap; line-height: 1.6;"><?= esc($envio['cuerpo']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Ficha</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Plantilla</dt>
                    <dd class="col-7 font-monospace"><?= esc($envio['plantilla_clave']) ?></dd>

                    <dt class="col-5 text-muted fw-normal">Programado</dt>
                    <dd class="col-7"><?= date('d/m/Y H:i', strtotime($envio['programado_para'])) ?></dd>

                    <dt class="col-5 text-muted fw-normal">Enviado</dt>
                    <dd class="col-7">
                        <?= $envio['enviado_en'] ? date('d/m/Y H:i', strtotime($envio['enviado_en'])) : '—' ?>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Intentos</dt>
                    <dd class="col-7"><?= (int) $envio['intentos'] ?></dd>

                    <dt class="col-5 text-muted fw-normal">Lo abrió</dt>
                    <dd class="col-7">
                        <?php if ($envio['abierto_en']): ?>
                            <?= date('d/m/Y H:i', strtotime($envio['abierto_en'])) ?>
                        <?php else: ?>
                            <span class="text-muted">No consta</span>
                            <div class="text-muted" style="font-size: .75rem;">
                                Solo se apunta si autorizó el perfilado. Sin eso, no se mira.
                            </div>
                        <?php endif ?>
                    </dd>

                    <?php if ($envio['reserva_id']): ?>
                        <dt class="col-5 text-muted fw-normal">Reserva</dt>
                        <dd class="col-7">
                            <a href="<?= site_url('reservas/ver/' . $envio['reserva_id']) ?>">Ver la reserva</a>
                        </dd>
                    <?php endif ?>
                    <?php if ($envio['huesped_id']): ?>
                        <dt class="col-5 text-muted fw-normal">Huésped</dt>
                        <dd class="col-7">
                            <a href="<?= site_url('huespedes/ver/' . $envio['huesped_id']) ?>">Ver su ficha</a>
                        </dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <?php if (puede('comunicaciones.enviar') && $envio['estado'] !== 'enviado'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body d-grid gap-2">
                    <form method="post" action="<?= site_url('comunicaciones/reintentar/' . $envio['id']) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Mandarlo ahora</button>
                    </form>
                    <?php if ($envio['estado'] !== 'cancelado'): ?>
                        <form method="post" action="<?= site_url('comunicaciones/cancelar/' . $envio['id']) ?>"
                              onsubmit="return confirm('¿Cancelarlo? No se mandará.')">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-circle me-1"></i>Cancelarlo
                            </button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
