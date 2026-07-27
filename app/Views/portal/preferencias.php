<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'info';
$titulo  = lang('Portal.misDatos');
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-0"><?= esc(lang('Portal.misDatos')) ?></h1>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<form method="post" action="<?= site_url('estancia/' . $token . '/preferencias') ?>" class="mt-3">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body">
            <?php // Se puede quitar con un clic, igual que se dio. Un
                  // consentimiento que cuesta más retirar que conceder no es un
                  // consentimiento (Ley 1581/2012). ?>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="campanas" value="1" id="campanas"
                       <?= ! empty($registro['acepta_marketing']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="campanas">
                    <?= esc(lang('Portal.campanas')) ?>
                </label>
            </div>
            <p class="small text-muted"><?= esc(lang('Portal.campanasTexto')) ?></p>

            <div class="d-grid">
                <button class="btn btn-bosque"><?= esc(lang('Portal.guardar')) ?></button>
            </div>
        </div>
    </div>
</form>

<div class="d-grid mt-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('estancia/' . $token . '/comprobante') ?>">
        <i class="bi bi-download me-1"></i><?= esc(lang('Portal.comprobante')) ?>
    </a>
</div>

<?= $this->endSection() ?>
