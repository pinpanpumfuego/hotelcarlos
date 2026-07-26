<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\ExperienciaModel;
use App\Models\MedioModel;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('experiencias') ?>" class="text-decoration-none small text-muted">
            <i class="bi bi-arrow-left me-1"></i>Agenda
        </a>
        <h1 class="h3 mb-1 mt-1">Catálogo de experiencias</h1>
        <p class="text-muted mb-0">Lo que puedes vender: precio, cupo y margen de cada una.</p>
    </div>
    <a href="<?= site_url('experiencias/nueva') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva experiencia
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($lista === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-compass fs-3 d-block mb-2 opacity-50"></i>
            Todavía no hay ninguna experiencia.
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($lista as $e): ?>
            <?php
            $margen  = ExperienciaModel::margen($e);
            $g       = $portadas[$e['id']] ?? ['portada' => null, 'total' => 0];
            $inactiva = (int) $e['activa'] !== 1;
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 <?= $inactiva ? 'opacity-75' : '' ?>">
                    <a href="<?= site_url('experiencias/ficha/' . $e['id']) ?>" class="text-decoration-none">
                        <div class="portada-exp">
                            <?php if ($g['portada'] !== null && $g['portada']['tipo'] === 'foto'): ?>
                                <img src="<?= esc(MedioModel::urlMiniatura($g['portada'])) ?>" alt="<?= esc($e['nombre']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="sin-foto">
                                    <i class="bi <?= ExperienciaModel::CATEGORIAS[$e['categoria']] ?? 'bi-compass' ?>"></i>
                                </div>
                            <?php endif ?>
                            <span class="cat"><?= esc($e['categoria']) ?></span>
                            <?php if ($inactiva): ?>
                                <span class="apagada">Desactivada</span>
                            <?php elseif ((int) $e['publicada'] !== 1): ?>
                                <span class="apagada">No sale en la web</span>
                            <?php endif ?>
                        </div>
                    </a>

                    <div class="card-body">
                        <a href="<?= site_url('experiencias/ficha/' . $e['id']) ?>" class="text-decoration-none">
                            <h2 class="h6 mb-1 text-dark"><?= esc($e['nombre']) ?></h2>
                        </a>
                        <div class="text-muted small mb-2">
                            <?= esc(ExperienciaModel::duracion((int) $e['duracion_min'])) ?> ·
                            hasta <?= (int) $e['capacidad'] ?> pers. ·
                            <?= esc(ExperienciaModel::textoDias($e)) ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-end">
                            <div>
                                <div class="precio-exp">$<?= number_format((float) $e['precio'], 0, ',', '.') ?></div>
                                <div class="text-muted small">
                                    <?= $e['tipo_precio'] === 'grupo' ? 'el grupo' : 'por persona' ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if ((float) $e['coste'] > 0): ?>
                                    <div class="small text-muted">margen</div>
                                    <div class="fw-semibold <?= $margen['porcentaje'] < 30 ? 'text-warning-emphasis' : 'text-success' ?>">
                                        <?= $margen['porcentaje'] ?> %
                                    </div>
                                <?php else: ?>
                                    <div class="small text-muted">sin coste puesto</div>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-white d-flex gap-1 justify-content-between align-items-center">
                        <span class="text-muted small">
                            <i class="bi bi-images me-1"></i><?= $g['total'] ?>
                        </span>
                        <div class="d-flex gap-1">
                            <a href="<?= site_url('experiencias/ficha/' . $e['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= site_url('experiencias/editar/' . $e['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" action="<?= site_url('experiencias/eliminar/' . $e['id']) ?>"
                                  onsubmit="return confirm('¿Eliminar <?= esc($e['nombre'], 'js') ?>?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<style>
    .portada-exp { position: relative; height: 150px; overflow: hidden;
                   border-radius: var(--radio) var(--radio) 0 0; background: var(--panel-tenue); }
    .portada-exp img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .portada-exp .sin-foto { height: 100%; display: flex; align-items: center; justify-content: center;
                             color: var(--borde-fuerte); font-size: 2.6rem; }
    .portada-exp .cat { position: absolute; top: 8px; left: 8px; background: rgba(28,42,35,.68);
                        color: #fff; font-size: .7rem; padding: .1rem .55rem; border-radius: 99px; }
    .portada-exp .apagada { position: absolute; top: 8px; right: 8px; background: var(--arena);
                            color: #fff; font-size: .68rem; padding: .1rem .5rem; border-radius: 99px; }
    .precio-exp { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.3rem; color: var(--bosque); }
</style>

<?= $this->endSection() ?>
