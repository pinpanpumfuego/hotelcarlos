<?php
/**
 * Galería de un tipo de alojamiento en la web pública.
 *
 * Mientras no haya fotos reales se sigue mostrando la ilustración: es mejor
 * un dibujo cuidado que un hueco gris o una foto de catálogo que no es del hotel.
 *
 * Recibe: medios (list), variante (ilustración de respaldo), nombre, idCarrusel.
 */
use App\Models\MedioModel;

$fotos = array_values(array_filter($medios, static fn ($m) => $m['tipo'] === 'foto'));
$video = null;
foreach ($medios as $m) {
    if ($m['tipo'] === 'video') {
        $video = $m;
        break;
    }
}
?>

<?php if ($fotos === []): ?>
    <?= view('web/_escena', ['variante' => $variante]) ?>
    <?php if ($video !== null && MedioModel::embebido($video['url']) !== null): ?>
        <a class="enlace-video" href="<?= esc($video['url']) ?>" target="_blank" rel="noopener">
            <i class="bi bi-play-circle me-1"></i><?= esc($video['titulo'] ?? 'Ver el vídeo') ?>
        </a>
    <?php endif ?>
<?php elseif (count($fotos) === 1): ?>
    <div class="marco-foto">
        <img src="<?= esc(MedioModel::urlPublica($fotos[0])) ?>"
             alt="<?= esc($fotos[0]['alt'] ?? $nombre) ?>" loading="lazy">
    </div>
<?php else: ?>
    <div id="<?= esc($idCarrusel) ?>" class="carousel slide marco-foto" data-bs-ride="false">
        <div class="carousel-inner h-100">
            <?php foreach ($fotos as $i => $f): ?>
                <div class="carousel-item h-100 <?= $i === 0 ? 'active' : '' ?>">
                    <img src="<?= esc(MedioModel::urlPublica($f)) ?>"
                         alt="<?= esc($f['alt'] ?? $nombre) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                </div>
            <?php endforeach ?>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#<?= esc($idCarrusel) ?>" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#<?= esc($idCarrusel) ?>" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
        </button>

        <div class="carousel-indicators">
            <?php foreach ($fotos as $i => $f): ?>
                <button type="button" data-bs-target="#<?= esc($idCarrusel) ?>" data-bs-slide-to="<?= $i ?>"
                        class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Foto <?= $i + 1 ?>"></button>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<style>
    .marco-foto { position: relative; height: 100%; min-height: 240px; overflow: hidden; background: #e9efe9; }
    .marco-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .marco-foto .carousel-inner, .marco-foto .carousel-item { height: 100%; min-height: 240px; }
    .enlace-video {
        display: inline-flex; align-items: center; margin: .6rem 0 0 1rem;
        font-size: .9rem; text-decoration: none;
    }
    @media (max-width: 767px) {
        .marco-foto, .marco-foto .carousel-inner, .marco-foto .carousel-item { min-height: 210px; }
    }
</style>
