<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\ExperienciaModel;
use App\Models\MedioModel;
?>

<div class="container seccion">
    <div class="text-center mb-5 reveal">
        <p class="etiqueta">Experiencias</p>
        <h1 class="titulo-seccion">Algo más que dormir junto al lago</h1>
        <p class="text-muted mt-2" style="max-width: 620px; margin: 0 auto;">
            Actividades para hacer durante tu estancia. Se reservan al llegar o escribiéndonos antes,
            porque los cupos son pequeños a propósito.
        </p>
    </div>

    <?php if ($experiencias === []): ?>
        <p class="text-center text-muted reveal">Muy pronto publicaremos nuestras experiencias.</p>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($experiencias as $e): ?>
                <?php
                $galeria = $galerias[$e['id']] ?? [];
                $portada = null;
                foreach ($galeria as $m) {
                    if ($m['tipo'] === 'foto') {
                        $portada = $m;
                        break;
                    }
                }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card tarjeta-tipo h-100 reveal">
                        <div class="foto-exp">
                            <?php if ($portada !== null): ?>
                                <img src="<?= esc(MedioModel::urlPublica($portada)) ?>"
                                     alt="<?= esc($portada['alt'] ?? $e['nombre']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="sin-foto-exp">
                                    <i class="bi <?= ExperienciaModel::CATEGORIAS[$e['categoria']] ?? 'bi-compass' ?>"></i>
                                </div>
                            <?php endif ?>
                            <span class="etiqueta-cat"><?= esc($e['categoria']) ?></span>
                        </div>

                        <div class="card-body p-4 d-flex flex-column">
                            <h2 class="h5 mb-2"><?= esc($e['nombre']) ?></h2>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="chip"><i class="bi bi-clock me-1"></i><?= esc(ExperienciaModel::duracion((int) $e['duracion_min'])) ?></span>
                                <span class="chip"><i class="bi bi-people me-1"></i>Máx. <?= (int) $e['capacidad'] ?></span>
                                <?php if ($e['edad_minima'] !== null): ?>
                                    <span class="chip"><i class="bi bi-person-check me-1"></i>Desde <?= (int) $e['edad_minima'] ?> años</span>
                                <?php endif ?>
                            </div>

                            <?php if (trim((string) $e['descripcion']) !== ''): ?>
                                <p class="text-muted small flex-grow-1"><?= esc($e['descripcion']) ?></p>
                            <?php endif ?>

                            <?php if (trim((string) $e['incluye']) !== ''): ?>
                                <p class="small mb-3">
                                    <i class="bi bi-check-circle text-success me-1"></i>
                                    <span class="text-muted">Incluye: <?= esc($e['incluye']) ?></span>
                                </p>
                            <?php endif ?>

                            <div class="d-flex justify-content-between align-items-end mt-auto pt-2 border-top">
                                <div>
                                    <div class="precio-desde fs-5">$<?= number_format((float) $e['precio'], 0, ',', '.') ?></div>
                                    <div class="small text-muted">
                                        <?= $e['tipo_precio'] === 'grupo' ? 'el grupo' : 'por persona' ?>
                                        · <?= esc(ExperienciaModel::textoDias($e)) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <div class="text-center mt-5 reveal">
            <p class="text-muted">
                Todas se reservan con tu estancia. Si ya tienes fecha, dínoslo al reservar
                y te guardamos el cupo.
            </p>
            <a href="<?= site_url('reservar') ?>" class="btn btn-reserva">
                <i class="bi bi-calendar-check me-1"></i>Reservar mi estancia
            </a>
        </div>
    <?php endif ?>
</div>

<style>
    .foto-exp { position: relative; height: 210px; overflow: hidden;
                border-radius: 18px 18px 0 0; background: #e9efe9; }
    .foto-exp img { width: 100%; height: 100%; object-fit: cover; display: block;
                    transition: transform .5s ease; }
    .tarjeta-tipo:hover .foto-exp img { transform: scale(1.04); }
    .sin-foto-exp { height: 100%; display: flex; align-items: center; justify-content: center;
                    color: #b9cec1; font-size: 3rem; }
    .etiqueta-cat { position: absolute; top: 12px; left: 12px; background: rgba(28,42,35,.7);
                    color: #fff; font-size: .72rem; padding: .15rem .65rem; border-radius: 99px; }
</style>

<?= $this->endSection() ?>
