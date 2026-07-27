<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'info';
$titulo  = lang('Portal.info');

$mapa = trim((string) ($hotel->direccion ?? ''));
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

<?php if ($mapa !== ''): ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-geo-alt me-1"></i><?= esc(lang('Portal.comoLlegar')) ?>
        </div>
        <div class="card-body">
            <p class="mb-2"><?= esc($mapa) ?></p>
            <?php // Enlace a la app de mapas del móvil: funciona en Android y en iPhone
                  // sin cargar un mapa incrustado, que en mala cobertura no llega. ?>
            <a class="btn btn-outline-secondary btn-sm"
               href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($hotel->nombre . ' ' . $mapa) ?>"
               target="_blank" rel="noopener">
                <i class="bi bi-map me-1"></i><?= esc(lang('Portal.comoLlegar')) ?>
            </a>
        </div>
    </div>
<?php endif ?>

<div class="card mt-3">
    <div class="card-header bg-white fw-semibold small">
        <i class="bi bi-journal-text me-1"></i><?= esc(lang('Portal.normas')) ?>
    </div>
    <div class="card-body small">
        <?php
        // Estas se sacarán de Administración cuando gerencia las escriba. Hasta
        // entonces, las mínimas de un alojamiento rural: mejor algo correcto y
        // corto que un texto inventado y largo.
        $normas = [
            lang('Portal.entrada') . ': 15:00 · ' . lang('Portal.salida') . ': 12:00',
            'No se puede fumar dentro de las cabañas.',
            'A partir de las 22:00, silencio: hay fauna alrededor y otros huéspedes.',
            'No alimentes a los animales, por su bien y por el tuyo.',
            'Lleva calzado cerrado si vas a los senderos.',
        ];
        ?>
        <ul class="mb-0 ps-3">
            <?php foreach ($normas as $n): ?>
                <li class="mb-1"><?= esc($n) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
</div>

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

<?= $this->endSection() ?>
