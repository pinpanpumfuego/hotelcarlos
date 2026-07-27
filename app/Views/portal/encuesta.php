<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'encuesta';
$titulo  = lang('Portal.encuesta');

$esDurante = $momento === 'estancia';
$caras     = [1 => '😞', 2 => '🙁', 3 => '😐', 4 => '🙂', 5 => '😍'];
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-1">
            <?= esc($esDurante ? lang('Portal.encuestaEstancia') : lang('Portal.encuestaSalida')) ?>
        </h1>
        <p class="mb-0 opacity-75 small">
            <?= esc($esDurante ? lang('Portal.encuestaTextoEstancia') : lang('Portal.encuestaTextoSalida')) ?>
        </p>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php if ($previa !== null): ?>
    <div class="alert alert-light border small mt-3">
        <i class="bi bi-check-circle me-1"></i><?= esc(lang('Portal.yaRespondiste')) ?>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url('estancia/' . $token . '/encuesta') ?>" class="mt-3">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <label class="form-label fw-semibold"><?= esc(lang('Portal.enGeneral')) ?></label>
            <div class="row g-2 mb-4">
                <?php foreach ($caras as $n => $cara): ?>
                    <div class="col nota">
                        <input type="radio" name="general" value="<?= $n ?>" id="g<?= $n ?>"
                               <?= (int) ($previa['general'] ?? 0) === $n ? 'checked' : '' ?> required>
                        <label for="g<?= $n ?>" aria-label="<?= $n ?>"><?= $cara ?></label>
                    </div>
                <?php endforeach ?>
            </div>

            <?php foreach ($apartados as $clave => $nombre): ?>
                <label class="form-label small fw-semibold"><?= esc($nombre) ?></label>
                <div class="row g-2 mb-3">
                    <?php foreach ($caras as $n => $cara): ?>
                        <div class="col nota">
                            <input type="radio" name="<?= esc($clave) ?>" value="<?= $n ?>"
                                   id="<?= esc($clave) ?><?= $n ?>"
                                   <?= (int) ($previa[$clave] ?? 0) === $n ? 'checked' : '' ?>>
                            <label for="<?= esc($clave) ?><?= $n ?>" style="font-size:1.15rem;" aria-label="<?= $n ?>"><?= $cara ?></label>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>

            <label class="form-label fw-semibold small"><?= esc(lang('Portal.comentario')) ?></label>
            <textarea name="comentario" class="form-control mb-3" rows="4"><?= esc($previa['comentario'] ?? '') ?></textarea>

            <?php // Autorización explícita: sin ella, el comentario no se publica ni se cita. ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="publicable" value="1" id="publicable"
                       <?= ! empty($previa['publicable']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="publicable"><?= esc(lang('Portal.publicable')) ?></label>
            </div>

            <div class="d-grid">
                <button class="btn btn-bosque btn-lg"><?= esc(lang('Portal.enviar')) ?></button>
            </div>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
