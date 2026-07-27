<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'solicitudes';
$titulo  = lang('Portal.pedir');

// Se puede llegar aquí con el tipo ya elegido (desde «algo no cuadra»)
$tipoPrevio = (string) (service('request')->getGet('tipo') ?? '');

$etiquetas = [
    'pendiente' => ['warning', lang('Portal.pendiente')],
    'en_curso'  => ['info', lang('Portal.enCurso')],
    'resuelta'  => ['success', lang('Portal.resuelta')],
    'rechazada' => ['secondary', lang('Portal.rechazada')],
];

$iconos = [
    'limpieza'      => 'bi-bucket',
    'toallas'       => 'bi-basket',
    'amenidades'    => 'bi-droplet',
    'mantenimiento' => 'bi-wrench',
    'cuna'          => 'bi-moon-stars',
    'decoracion'    => 'bi-balloon-heart',
    'salida_tarde'  => 'bi-clock-history',
    'transporte'    => 'bi-car-front',
    'otro'          => 'bi-three-dots',
];
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-0"><?= esc(lang('Portal.pedirTitulo')) ?></h1>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<form method="post" action="<?= site_url('estancia/' . $token . '/pedir') ?>" class="mt-3">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <label class="form-label fw-semibold"><?= esc(lang('Portal.queNecesitas')) ?></label>
            <div class="row g-2 mb-3">
                <?php foreach ($tipos as $valor => $texto): ?>
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="tipo" value="<?= esc($valor) ?>"
                               id="t<?= esc($valor) ?>" <?= $tipoPrevio === $valor ? 'checked' : '' ?> required>
                        <label class="btn btn-outline-secondary w-100 h-100 text-start p-2 small" for="t<?= esc($valor) ?>">
                            <i class="bi <?= $iconos[$valor] ?? 'bi-dot' ?> d-block fs-5 mb-1"></i>
                            <?= esc($texto) ?>
                        </label>
                    </div>
                <?php endforeach ?>
            </div>

            <label class="form-label fw-semibold small"><?= esc(lang('Portal.detalle')) ?></label>
            <textarea name="detalle" class="form-control mb-1" rows="3" maxlength="1000"></textarea>
            <div class="form-text mb-3"><?= esc(lang('Portal.detallePista')) ?></div>

            <label class="form-label fw-semibold small"><?= esc(lang('Portal.paraCuando')) ?></label>
            <input type="text" name="para_cuando" class="form-control mb-1" maxlength="100"
                   placeholder="<?= esc(lang('Portal.paraCuandoPista')) ?>">
            <div class="form-text mb-3"><?= esc(lang('Portal.paraCuandoPista')) ?></div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="urgente" value="1" id="urgente">
                <label class="form-check-label" for="urgente"><?= esc(lang('Portal.esUrgente')) ?></label>
            </div>

            <div class="d-grid">
                <button class="btn btn-bosque btn-lg"><?= esc(lang('Portal.enviar')) ?></button>
            </div>
        </div>
    </div>
</form>

<?php if ($previas !== []): ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small"><?= esc(lang('Portal.tusPeticiones')) ?></div>
        <div class="card-body py-2">
            <?php foreach ($previas as $s): ?>
                <?php [$color, $texto] = $etiquetas[$s['estado']] ?? ['secondary', $s['estado']]; ?>
                <div class="py-2 border-bottom">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="pe-2">
                            <div class="small fw-semibold">
                                <i class="bi <?= $iconos[$s['tipo']] ?? 'bi-dot' ?> me-1"></i>
                                <?= esc(\App\Models\SolicitudModel::TIPOS[$s['tipo']] ?? $s['tipo']) ?>
                            </div>
                            <?php if (! empty($s['detalle'])): ?>
                                <div class="small text-muted"><?= esc($s['detalle']) ?></div>
                            <?php endif ?>
                            <div class="small text-muted"><?= date('d/m H:i', strtotime($s['created_at'])) ?></div>
                        </div>
                        <span class="badge text-bg-<?= $color ?> text-nowrap"><?= esc($texto) ?></span>
                    </div>
                    <?php if (! empty($s['respuesta'])): ?>
                        <div class="alert alert-light border small mt-2 mb-0 py-2">
                            <i class="bi bi-reply me-1"></i><?= esc($s['respuesta']) ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
