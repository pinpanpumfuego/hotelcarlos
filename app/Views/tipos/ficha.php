<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php use App\Models\MedioModel; ?>

<div class="mb-4">
    <a href="<?= site_url('tipos') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Tipos de alojamiento
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1"><?= esc($tipo['nombre']) ?></h1>
            <p class="text-muted mb-0">
                Esto es lo que <strong>ve el huésped</strong> en la web y al reservar:
                fotos, descripción y servicios. <?= count($unidades) ?> cabaña<?= count($unidades) === 1 ? '' : 's' ?> de este tipo.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= site_url('alojamientos') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right me-1"></i>Ver en la web
            </a>
            <a href="<?= site_url('tipos/editar/' . $tipo['id']) ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Precios y capacidad
            </a>
        </div>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <!-- ══ Galería ══ -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" id="galeria">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-images me-2"></i>Galería</span>
                <span class="text-muted small"><?= count($medios) ?> elemento<?= count($medios) === 1 ? '' : 's' ?></span>
            </div>

            <div class="card-body">
                <?php if ($medios === []): ?>
                    <div class="vacio-galeria">
                        <i class="bi bi-camera fs-1 opacity-50"></i>
                        <p class="mb-1 fw-semibold">Aún no hay fotos</p>
                        <p class="text-muted small mb-0">
                            Mientras tanto la web sigue mostrando las ilustraciones.
                            En cuanto subas la primera foto, la web pasa a usarlas.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="galeria">
                        <?php foreach ($medios as $i => $m): ?>
                            <figure class="pieza <?= (int) $m['portada'] === 1 ? 'es-portada' : '' ?>">
                                <?php if ($m['tipo'] === 'video'): ?>
                                    <div class="marco-video">
                                        <i class="bi bi-play-circle"></i>
                                        <span class="small text-truncate"><?= esc($m['titulo'] ?? 'Vídeo') ?></span>
                                    </div>
                                <?php else: ?>
                                    <img src="<?= esc(MedioModel::urlMiniatura($m)) ?>"
                                         alt="<?= esc($m['alt'] ?? $tipo['nombre']) ?>" loading="lazy">
                                <?php endif ?>

                                <?php if ((int) $m['portada'] === 1): ?>
                                    <span class="etiqueta-portada"><i class="bi bi-star-fill me-1"></i>Portada</span>
                                <?php endif ?>

                                <figcaption>
                                    <span class="text-truncate small text-muted"><?= esc($m['alt'] ?? $m['titulo'] ?? '') ?></span>
                                    <div class="acciones">
                                        <?php if ($i > 0): ?>
                                            <form method="post" action="<?= site_url('tipos/foto/mover/' . $m['id']) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="direccion" value="arriba">
                                                <button class="btn btn-sm btn-link p-0" title="Mover antes"><i class="bi bi-arrow-left"></i></button>
                                            </form>
                                        <?php endif ?>
                                        <?php if ($i < count($medios) - 1): ?>
                                            <form method="post" action="<?= site_url('tipos/foto/mover/' . $m['id']) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="direccion" value="abajo">
                                                <button class="btn btn-sm btn-link p-0" title="Mover después"><i class="bi bi-arrow-right"></i></button>
                                            </form>
                                        <?php endif ?>
                                        <?php if ($m['tipo'] === 'foto' && (int) $m['portada'] !== 1): ?>
                                            <form method="post" action="<?= site_url('tipos/foto/portada/' . $m['id']) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-link p-0" title="Poner de portada"><i class="bi bi-star"></i></button>
                                            </form>
                                        <?php endif ?>
                                        <form method="post" action="<?= site_url('tipos/foto/eliminar/' . $m['id']) ?>"
                                              onsubmit="return confirm('¿Eliminar de la galería?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-link text-danger p-0" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </figcaption>
                            </figure>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>

            <div class="card-body border-top">
                <form method="post" action="<?= site_url('tipos/foto/' . $tipo['id']) ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Subir foto</label>
                        <input type="file" name="foto" class="form-control form-control-sm" accept="image/*" required>
                        <div class="form-text">JPG, PNG o WEBP, hasta 12 MB. Se reducen solas para que la web cargue rápido.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Descripción de la foto</label>
                        <input type="text" name="alt" class="form-control form-control-sm" maxlength="200"
                               placeholder="Cabaña al atardecer con vista al lago">
                        <div class="form-text">La leen Google y quien no puede ver la imagen.</div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100"><i class="bi bi-upload me-1"></i>Subir</button>
                    </div>
                </form>

                <form method="post" action="<?= site_url('tipos/video/' . $tipo['id']) ?>" class="row g-2 align-items-end mt-2 pt-3 border-top">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Añadir vídeo</label>
                        <input type="url" name="url" class="form-control form-control-sm" placeholder="https://youtu.be/…" required>
                        <div class="form-text">De YouTube o Vimeo. El vídeo no se sube al servidor: pesaría demasiado.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-sm" maxlength="150"
                               placeholder="Un día en San Antonio de los Lagos">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-play-btn me-1"></i>Añadir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ══ Servicios ══ -->
    <div class="col-lg-5">
        <form method="post" action="<?= site_url('tipos/servicios/' . $tipo['id']) ?>">
            <?= csrf_field() ?>
            <div class="card border-0 shadow-sm" id="servicios">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-stars me-2"></i>Servicios y equipamiento
                </div>
                <div class="card-body">
                    <p class="form-text mb-3">
                        Lo que marques aquí sale en la web como etiquetas y lo heredan todas las cabañas
                        de este tipo. Si una cabaña concreta tiene algo distinto, se marca en
                        <a href="<?= site_url('unidades') ?>">su propia ficha</a>.
                    </p>

                    <?php foreach ($catalogo as $grupo => $lista): ?>
                        <div class="mb-3">
                            <div class="fw-semibold small mb-1"><?= esc($grupo) ?></div>
                            <?php foreach ($lista as $s): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="servicio[]"
                                           value="<?= $s['id'] ?>" id="ts<?= $s['id'] ?>"
                                           <?= in_array((int) $s['id'], array_map('intval', $marcados), true) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="ts<?= $s['id'] ?>">
                                        <i class="bi <?= esc($s['icono']) ?> me-1 text-muted"></i><?= esc($s['nombre']) ?>
                                    </label>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endforeach ?>
                </div>
                <div class="card-footer bg-white p-3 d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar servicios</button>
                    <a href="<?= site_url('servicios') ?>" class="btn btn-outline-secondary">Catálogo</a>
                </div>
            </div>
        </form>

        <?php if ($unidades !== []): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-door-open me-2"></i>Cabañas de este tipo</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($unidades as $u): ?>
                        <a href="<?= site_url('unidades/ver/' . $u['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><?= esc($u['nombre']) ?></span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<style>
    .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .7rem; }
    .pieza { position: relative; margin: 0; border: 1px solid var(--borde);
             border-radius: var(--radio-sm); overflow: hidden; background: var(--panel-tenue); }
    .pieza.es-portada { border-color: var(--arena-clara); box-shadow: 0 0 0 2px rgba(185,135,63,.18); }
    .pieza img { width: 100%; height: 118px; object-fit: cover; display: block; }
    .marco-video { height: 118px; display: flex; flex-direction: column; align-items: center;
                   justify-content: center; gap: .2rem; color: var(--tinta-media); background: #e9efe9; }
    .marco-video i { font-size: 1.7rem; color: var(--bosque); }
    .etiqueta-portada { position: absolute; top: 6px; left: 6px; background: var(--arena);
                        color: #fff; font-size: .66rem; font-weight: 600;
                        padding: .1rem .45rem; border-radius: 99px; }
    .pieza figcaption { display: flex; justify-content: space-between; align-items: center;
                        gap: .3rem; padding: .3rem .5rem; }
    .pieza .acciones { display: flex; gap: .35rem; flex-shrink: 0; }
    .pieza .acciones form { display: inline; }
    .vacio-galeria { text-align: center; padding: 2.2rem 1rem; color: var(--tinta-suave);
                     border: 2px dashed var(--borde-fuerte); border-radius: var(--radio); }
</style>

<?= $this->endSection() ?>
