<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\ExperienciaModel;
use App\Models\ExperienciaReservaModel;
use App\Models\MedioModel;

$margen   = ExperienciaModel::margen($exp);
$horarios = ExperienciaModel::horariosDe($exp);
?>

<div class="mb-4">
    <a href="<?= site_url('experiencias/catalogo') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Catálogo
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1">
                <?= esc($exp['nombre']) ?>
                <?php if ((int) $exp['activa'] !== 1): ?>
                    <span class="badge text-bg-secondary fs-6 align-middle">Desactivada</span>
                <?php elseif ((int) $exp['publicada'] !== 1): ?>
                    <span class="badge text-bg-warning fs-6 align-middle">No sale en la web</span>
                <?php endif ?>
            </h1>
            <p class="text-muted mb-0">
                <?= esc($exp['categoria']) ?> ·
                <?= esc(ExperienciaModel::duracion((int) $exp['duracion_min'])) ?> ·
                <?= esc(ExperienciaModel::textoDias($exp)) ?>
                <?php if ($horarios !== []): ?> · <?= esc(implode(' y ', $horarios)) ?><?php endif ?>
            </p>
        </div>
        <a href="<?= site_url('experiencias/editar/' . $exp['id']) ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Precio</div>
            <div class="valor h4 mb-0">$<?= number_format((float) $exp['precio'], 0, ',', '.') ?></div>
            <div class="text-muted small"><?= $exp['tipo_precio'] === 'grupo' ? 'el grupo' : 'por persona' ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Margen</div>
            <div class="valor h4 mb-0 <?= $margen['valor'] <= 0 ? 'text-danger' : 'text-success' ?>">
                $<?= number_format($margen['valor'], 0, ',', '.') ?>
            </div>
            <div class="text-muted small"><?= $margen['porcentaje'] ?> % sobre el precio</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Realizadas</div>
            <div class="valor h4 mb-0"><?= (int) $realizadas['realizadas'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Ha dejado</div>
            <div class="valor h4 mb-0">$<?= number_format($realizadas['ingresos'], 0, ',', '.') ?></div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <!-- ── Galería ── -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4" id="galeria">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-images me-2"></i>Galería</span>
                <span class="text-muted small"><?= count($medios) ?> elemento<?= count($medios) === 1 ? '' : 's' ?></span>
            </div>
            <div class="card-body">
                <?php if ($medios === []): ?>
                    <div class="vacio-galeria">
                        <i class="bi bi-camera fs-1 opacity-50 d-block mb-2"></i>
                        <p class="mb-0">
                            Sin fotos. Una experiencia sin foto no se vende:
                            es lo primero que mira el huésped.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="galeria">
                        <?php foreach ($medios as $m): ?>
                            <?php $mini = MedioModel::miniaturaVideo($m['url'] ?? null); ?>
                            <figure class="pieza <?= (int) $m['portada'] === 1 ? 'es-portada' : '' ?>">
                                <?php if ($m['tipo'] === 'video'): ?>
                                    <div class="marco-video <?= $mini !== null ? 'con-imagen' : '' ?>"
                                         <?= $mini !== null ? 'style="background-image:url(\'' . esc($mini, 'attr') . '\')"' : '' ?>>
                                        <i class="bi bi-play-circle-fill"></i>
                                    </div>
                                <?php else: ?>
                                    <img src="<?= esc(MedioModel::urlMiniatura($m)) ?>"
                                         alt="<?= esc($m['alt'] ?? $exp['nombre']) ?>" loading="lazy">
                                <?php endif ?>

                                <?php if ((int) $m['portada'] === 1): ?>
                                    <span class="etiqueta-portada"><i class="bi bi-star-fill me-1"></i>Portada</span>
                                <?php endif ?>

                                <figcaption>
                                    <span class="text-truncate small text-muted"><?= esc($m['alt'] ?? $m['titulo'] ?? '') ?></span>
                                    <div class="acciones">
                                        <?php if ($m['tipo'] === 'foto' && (int) $m['portada'] !== 1): ?>
                                            <form method="post" action="<?= site_url('experiencias/foto/portada/' . $m['id']) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-link p-0" title="Poner de portada"><i class="bi bi-star"></i></button>
                                            </form>
                                        <?php endif ?>
                                        <form method="post" action="<?= site_url('experiencias/foto/eliminar/' . $m['id']) ?>"
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
                <form method="post" action="<?= site_url('experiencias/foto/' . $exp['id']) ?>" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Subir foto</label>
                        <input type="file" name="foto" class="form-control form-control-sm" accept="image/*" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Qué se ve</label>
                        <input type="text" name="alt" class="form-control form-control-sm" maxlength="200"
                               placeholder="Cabalgata bordeando el lago al amanecer">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100"><i class="bi bi-upload"></i></button>
                    </div>
                </form>

                <form method="post" action="<?= site_url('experiencias/video/' . $exp['id']) ?>"
                      class="row g-2 align-items-end mt-2 pt-3 border-top">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Añadir vídeo</label>
                        <input type="url" name="url" class="form-control form-control-sm" placeholder="https://youtu.be/…" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1 fw-semibold">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-sm" maxlength="150">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-play-btn"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Detalles y próximas salidas ── -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Detalles</div>
            <div class="card-body">
                <?php if (trim((string) $exp['descripcion']) !== ''): ?>
                    <p class="mb-3"><?= nl2br(esc($exp['descripcion'])) ?></p>
                <?php endif ?>

                <ul class="list-unstyled small mb-0">
                    <?php if (trim((string) $exp['incluye']) !== ''): ?>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i><strong>Incluye:</strong> <?= esc($exp['incluye']) ?></li>
                    <?php endif ?>
                    <?php if (trim((string) $exp['no_incluye']) !== ''): ?>
                        <li class="mb-2"><i class="bi bi-x-circle text-muted me-2"></i><strong>No incluye:</strong> <?= esc($exp['no_incluye']) ?></li>
                    <?php endif ?>
                    <?php if (trim((string) $exp['punto_encuentro']) !== ''): ?>
                        <li class="mb-2"><i class="bi bi-geo-alt me-2 text-muted"></i><?= esc($exp['punto_encuentro']) ?></li>
                    <?php endif ?>
                    <li class="mb-2">
                        <i class="bi bi-people me-2 text-muted"></i>
                        Caben <?= (int) $exp['capacidad'] ?> por salida
                        <?= (int) $exp['minimo'] > 1 ? ' · mínimo ' . (int) $exp['minimo'] : '' ?>
                    </li>
                    <?php if ($exp['edad_minima'] !== null): ?>
                        <li class="mb-2"><i class="bi bi-person-check me-2 text-muted"></i>Desde <?= (int) $exp['edad_minima'] ?> años</li>
                    <?php endif ?>
                    <?php if ((int) $exp['aviso_horas'] > 0): ?>
                        <li class="mb-2"><i class="bi bi-alarm me-2 text-muted"></i>Avisar con <?= (int) $exp['aviso_horas'] ?> h de antelación</li>
                    <?php endif ?>
                    <?php if (trim((string) $exp['proveedor']) !== ''): ?>
                        <li class="mb-2"><i class="bi bi-truck me-2 text-muted"></i>Proveedor: <?= esc($exp['proveedor']) ?></li>
                    <?php endif ?>
                </ul>

                <?php if (trim((string) $exp['notas_internas']) !== ''): ?>
                    <div class="alert alert-light mt-3 mb-0 small">
                        <i class="bi bi-sticky me-1"></i><?= esc($exp['notas_internas']) ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-check me-2"></i>Próximas salidas</div>
            <?php if ($proximas === []): ?>
                <div class="card-body text-muted small text-center py-4">
                    Nadie apuntado en los próximos 60 días.
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach (array_slice($proximas, 0, 12) as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <span class="fw-semibold"><?= date('d/m', strtotime($p['fecha'])) ?></span>
                                <?= $p['hora'] !== null ? '<span class="text-muted">' . substr($p['hora'], 0, 5) . '</span>' : '' ?>
                                <div class="text-muted small">
                                    <?= esc(ExperienciaReservaModel::quien($p)) ?> ·
                                    <?= (int) $p['adultos'] + (int) $p['ninos'] ?> pers.
                                </div>
                            </div>
                            <span class="badge text-bg-<?= ExperienciaReservaModel::COLORES[$p['estado']] ?>">
                                <?= ExperienciaReservaModel::ESTADOS[$p['estado']] ?>
                            </span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
</div>

<style>
    .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .7rem; }
    .pieza { position: relative; margin: 0; border: 1px solid var(--borde);
             border-radius: var(--radio-sm); overflow: hidden; background: var(--panel-tenue); }
    .pieza.es-portada { border-color: var(--arena-clara); box-shadow: 0 0 0 2px rgba(185,135,63,.18); }
    .pieza img { width: 100%; height: 118px; object-fit: cover; display: block; }
    .marco-video { height: 118px; display: flex; align-items: center; justify-content: center;
                   color: var(--bosque); background: #e9efe9; font-size: 1.8rem; }
    .marco-video.con-imagen { background-size: cover; background-position: center; }
    .marco-video.con-imagen i { color: #fff; font-size: 2.2rem; text-shadow: 0 2px 8px rgba(0,0,0,.5); }
    .etiqueta-portada { position: absolute; top: 6px; left: 6px; background: var(--arena);
                        color: #fff; font-size: .66rem; font-weight: 600;
                        padding: .1rem .45rem; border-radius: 99px; }
    .pieza figcaption { display: flex; justify-content: space-between; align-items: center;
                        gap: .3rem; padding: .3rem .5rem; }
    .pieza .acciones { display: flex; gap: .35rem; flex-shrink: 0; }
    .pieza .acciones form { display: inline; }
    .vacio-galeria { text-align: center; padding: 2rem 1rem; color: var(--tinta-suave);
                     border: 2px dashed var(--borde-fuerte); border-radius: var(--radio); }
</style>

<?= $this->endSection() ?>
