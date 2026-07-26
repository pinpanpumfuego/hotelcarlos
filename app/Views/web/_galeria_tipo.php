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

// Los vídeos entran en el carrusel con su miniatura y un botón de reproducir:
// incrustar varios iframes haría la página muy pesada.
$piezas = [];
foreach ($medios as $m) {
    if ($m['tipo'] === 'foto') {
        $piezas[] = ['tipo' => 'foto', 'src' => MedioModel::urlPublica($m), 'alt' => $m['alt'] ?? $nombre];
    } elseif (MedioModel::embebido($m['url']) !== null) {
        $piezas[] = [
            'tipo'  => 'video',
            'src'   => MedioModel::miniaturaVideo($m['url']),
            'alt'   => $m['titulo'] ?? 'Vídeo de ' . $nombre,
            'enlace' => $m['url'],
        ];
    }
}
?>

<?php if ($piezas === []): ?>
    <?= view('web/_escena', ['variante' => $variante]) ?>
<?php elseif (count($piezas) === 1 && $piezas[0]['tipo'] === 'foto'): ?>
    <div class="marco-foto">
        <img src="<?= esc($piezas[0]['src']) ?>" alt="<?= esc($piezas[0]['alt']) ?>" loading="lazy">
    </div>
<?php else: ?>
    <div id="<?= esc($idCarrusel) ?>" class="carousel slide marco-foto" data-bs-ride="false">
        <div class="carousel-inner h-100">
            <?php foreach ($piezas as $i => $p): ?>
                <div class="carousel-item h-100 <?= $i === 0 ? 'active' : '' ?>">
                    <?php if ($p['tipo'] === 'video'): ?>
                        <a href="<?= esc($p['enlace']) ?>" target="_blank" rel="noopener" class="pieza-video">
                            <?php if ($p['src'] !== null): ?>
                                <img src="<?= esc($p['src']) ?>" alt="<?= esc($p['alt']) ?>" loading="lazy">
                            <?php endif ?>
                            <span class="boton-play"><i class="bi bi-play-fill"></i></span>
                            <span class="pie-video"><?= esc($p['alt']) ?></span>
                        </a>
                    <?php else: ?>
                        <img src="<?= esc($p['src']) ?>" alt="<?= esc($p['alt']) ?>"
                             loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                    <?php endif ?>
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
            <?php foreach ($piezas as $i => $p): ?>
                <button type="button" data-bs-target="#<?= esc($idCarrusel) ?>" data-bs-slide-to="<?= $i ?>"
                        class="<?= $i === 0 ? 'active' : '' ?>"
                        aria-label="<?= $p['tipo'] === 'video' ? 'Vídeo' : 'Foto' ?> <?= $i + 1 ?>"></button>
            <?php endforeach ?>
        </div>

        <span class="contador-galeria"><?= count($piezas) ?> <i class="bi bi-images"></i></span>
    </div>
<?php endif ?>

<style>
    .marco-foto { position: relative; height: 100%; min-height: 240px; overflow: hidden; background: #e9efe9; }
    .marco-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .marco-foto .carousel-inner, .marco-foto .carousel-item { height: 100%; min-height: 240px; }
    .pieza-video { position: relative; display: block; height: 100%; background: #1c2a23; }
    .pieza-video img { opacity: .82; }
    .boton-play {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 62px; height: 62px; border-radius: 50%; background: rgba(255,255,255,.92);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: #1f4d36; box-shadow: 0 4px 16px rgba(0,0,0,.3);
        transition: transform .18s ease;
    }
    .pieza-video:hover .boton-play { transform: translate(-50%, -50%) scale(1.08); }
    .pie-video {
        position: absolute; left: 0; right: 0; bottom: 0; padding: .6rem .9rem 1.6rem;
        color: #fff; font-size: .86rem;
        background: linear-gradient(transparent, rgba(0,0,0,.65));
    }
    .contador-galeria {
        position: absolute; top: 10px; right: 10px; background: rgba(28,42,35,.62);
        color: #fff; font-size: .74rem; padding: .15rem .55rem; border-radius: 99px;
    }
    @media (max-width: 767px) {
        .marco-foto, .marco-foto .carousel-inner, .marco-foto .carousel-item { min-height: 210px; }
    }
</style>
