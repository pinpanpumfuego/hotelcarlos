<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'info';
$titulo  = lang('Portal.info');

$mapa = trim((string) ($hotel->direccion ?? ''));

// Los servicios se agrupan por su grupo para no soltar una lista de treinta
// cosas seguidas, que nadie lee.
$porGrupo = [];
foreach ($servicios as $s) {
    $porGrupo[$s['grupo'] ?: '—'][] = $s;
}

/** Bloque de texto libre escrito por gerencia. Si está vacío, ni se pinta. */
$bloque = static function (string $titulo, string $texto, string $icono): void {
    if (trim($texto) === '') {
        return;
    }
    ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small">
            <i class="bi <?= $icono ?> me-1"></i><?= esc($titulo) ?>
        </div>
        <div class="card-body small" style="white-space: pre-line;"><?= esc($texto) ?></div>
    </div>
    <?php
};
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-0"><?= esc(lang('Portal.info')) ?></h1>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<div class="card mt-3">
    <div class="card-header bg-white fw-semibold small">
        <i class="bi bi-telephone me-1"></i><?= esc(lang('Portal.contacto')) ?>
    </div>
    <div class="card-body">
        <div class="d-grid gap-2">
            <?php if (! empty($hotel->whatsapp)): ?>
                <a class="btn btn-outline-success" href="https://wa.me/<?= esc($hotel->whatsapp) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp me-1"></i><?= esc(lang('Portal.escribir')) ?>
                </a>
            <?php endif ?>
            <?php if (! empty($hotel->telefono)): ?>
                <a class="btn btn-outline-secondary" href="tel:<?= esc(preg_replace('/\s+/', '', $hotel->telefono)) ?>">
                    <i class="bi bi-telephone me-1"></i><?= esc($hotel->telefono) ?>
                </a>
            <?php endif ?>
            <?php if (! empty($hotel->email)): ?>
                <a class="btn btn-outline-secondary" href="mailto:<?= esc($hotel->email) ?>">
                    <i class="bi bi-envelope me-1"></i><?= esc($hotel->email) ?>
                </a>
            <?php endif ?>
        </div>
    </div>
</div>

<?php $bloque(lang('Portal.horarios'), (string) $horarios, 'bi-clock'); ?>

<?php if ($porGrupo !== []): ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-stars me-1"></i><?= esc(lang('Portal.queOfrecemos')) ?>
        </div>
        <div class="card-body py-2">
            <?php foreach ($porGrupo as $grupo => $lista): ?>
                <?php if ($grupo !== '—'): ?>
                    <div class="dato mt-2 mb-1"><?= esc($grupo) ?></div>
                <?php endif ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php foreach ($lista as $s): ?>
                        <span class="badge text-bg-light border fw-normal">
                            <?php if (! empty($s['icono'])): ?>
                                <i class="bi <?= esc($s['icono']) ?> me-1"></i>
                            <?php endif ?>
                            <?= esc($s['nombre']) ?>
                        </span>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?php if ($mapa !== ''): ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-geo-alt me-1"></i><?= esc(lang('Portal.comoLlegar')) ?>
        </div>
        <div class="card-body">
            <p class="mb-2"><?= esc($mapa) ?></p>
            <?php // Enlace a la app de mapas del móvil: funciona en Android y en
                  // iPhone sin cargar un mapa incrustado, que con mala cobertura
                  // no llega a pintarse. ?>
            <a class="btn btn-outline-secondary btn-sm"
               href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($hotel->nombre . ' ' . $mapa) ?>"
               target="_blank" rel="noopener">
                <i class="bi bi-map me-1"></i><?= esc(lang('Portal.comoLlegar')) ?>
            </a>
        </div>
    </div>
<?php endif ?>

<?php
$bloque(lang('Portal.rutas'), (string) $rutas, 'bi-signpost-split');
$bloque(lang('Portal.recomendaciones'), (string) $recomendaciones, 'bi-compass');
$bloque(lang('Portal.normas'), (string) $normas, 'bi-journal-text');
$bloque(lang('Portal.politicaTitulo'), (string) $politica, 'bi-file-text');
?>

<div class="card mt-3 border-danger">
    <div class="card-body small">
        <div class="fw-semibold text-danger mb-1">
            <i class="bi bi-exclamation-octagon me-1"></i><?= esc(lang('Portal.emergencias')) ?>
        </div>
        <p class="mb-2"><?= esc(lang('Portal.emergenciasTexto')) ?></p>
        <a class="btn btn-danger btn-sm" href="tel:123">
            <i class="bi bi-telephone-fill me-1"></i>123
        </a>
    </div>
</div>

<div class="d-grid mt-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('estancia/' . $token . '/preferencias') ?>">
        <i class="bi bi-sliders me-1"></i><?= esc(lang('Portal.misDatos')) ?>
    </a>
</div>

<?= $this->endSection() ?>
