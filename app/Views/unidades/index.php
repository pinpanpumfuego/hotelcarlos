<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\MedioModel;

$rol       = session()->get('usuario_rol');
$esGestor  = in_array($rol, ['gerencia', 'recepcion'], true);
$colores   = ['disponible' => 'success', 'ocupada' => 'danger', 'limpieza' => 'warning', 'bloqueada' => 'secondary'];
$etiquetas = ['disponible' => 'Disponible', 'ocupada' => 'Ocupada', 'limpieza' => 'En limpieza', 'bloqueada' => 'Bloqueada'];
$iconos    = ['disponible' => 'bi-check-circle', 'ocupada' => 'bi-person-fill', 'limpieza' => 'bi-bucket', 'bloqueada' => 'bi-slash-circle'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Cabañas</h1>
        <p class="text-muted mb-0">Entra en una cabaña para ver su inventario, sus servicios y su historial.</p>
    </div>
    <?php if ($esGestor): ?>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= site_url('inventario-cabanas') ?>" class="btn btn-outline-primary">
                <i class="bi bi-box-seam me-1"></i>Enseres
            </a>
            <a href="<?= site_url('unidades/nueva') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nueva cabaña
            </a>
        </div>
    <?php endif ?>
</div>

<?php if (empty($unidades)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-door-open fs-3 d-block mb-2 opacity-50"></i>
            No hay cabañas registradas todavía.
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($unidades as $u): ?>
            <?php
            // Manda la foto propia de la cabaña; si no tiene, la de su tipo
            $propia   = $propias[(int) $u['id']] ?? null;
            $portada  = $propia ?? ($portadas[(int) $u['tipo_id']] ?? null);
            $revision = $revisiones[(int) $u['id']] ?? null;
            $enGaleria = $galerias[(int) $u['id']] ?? 0;
            ?>
            <div class="col-sm-6 col-xl-4">
                <a href="<?= site_url('unidades/ver/' . $u['id']) ?>" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100 tarjeta-cabana">
                        <div class="portada">
                            <?php if ($portada !== null): ?>
                                <img src="<?= esc(MedioModel::urlMiniatura($portada)) ?>"
                                     alt="<?= esc($portada['alt'] ?? $u['nombre']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="sin-foto"><i class="bi bi-camera"></i></div>
                            <?php endif ?>
                            <span class="estado badge text-bg-<?= $colores[$u['estado']] ?? 'secondary' ?>">
                                <i class="bi <?= $iconos[$u['estado']] ?? 'bi-circle' ?> me-1"></i>
                                <?= esc($etiquetas[$u['estado']] ?? $u['estado']) ?>
                            </span>
                            <?php if ($enGaleria > 0): ?>
                                <span class="conteo-fotos"><i class="bi bi-images me-1"></i><?= $enGaleria ?></span>
                            <?php else: ?>
                                <span class="conteo-fotos vacio"><i class="bi bi-camera me-1"></i>Sin fotos propias</span>
                            <?php endif ?>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <h2 class="h6 mb-0 text-dark"><?= esc($u['nombre']) ?></h2>
                                    <div class="text-muted small">
                                        <?= esc($u['tipo_nombre']) ?> · <?= (int) $u['capacidad'] ?> pers.
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </div>

                            <?php if (trim((string) ($u['ubicacion'] ?? '')) !== ''): ?>
                                <div class="text-muted small mt-2">
                                    <i class="bi bi-geo-alt me-1"></i><?= esc($u['ubicacion']) ?>
                                </div>
                            <?php endif ?>

                            <div class="mt-2">
                                <?php if ($revision === null): ?>
                                    <span class="badge text-bg-light text-muted">Sin revisar nunca</span>
                                <?php elseif ($revision['estado'] === 'ok'): ?>
                                    <span class="badge text-bg-success">
                                        <i class="bi bi-clipboard-check me-1"></i>Revisada <?= date('d/m', strtotime($revision['created_at'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        <?= (int) $revision['faltantes'] ?> faltan · <?= (int) $revision['danados'] ?> dañados
                                    </span>
                                <?php endif ?>
                            </div>

                            <?php if (trim((string) ($u['notas'] ?? '')) !== ''): ?>
                                <p class="text-muted small mb-0 mt-2 text-truncate"><?= esc($u['notas']) ?></p>
                            <?php endif ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<p class="text-muted small mt-3">
    <i class="bi bi-info-circle me-1"></i>
    El estado cambia solo con la operación: al hacer check-in pasa a ocupada, al check-out a limpieza,
    y vuelve a disponible cuando el equipo termina el aseo desde el <a href="<?= site_url('limpieza') ?>">tablero de limpieza</a>.
    Las fotos que se ven aquí son las de <a href="<?= site_url('tipos') ?>">su tipo de alojamiento</a>.
</p>

<style>
    .tarjeta-cabana .portada { position: relative; height: 132px; overflow: hidden;
                               border-radius: var(--radio) var(--radio) 0 0; background: var(--panel-tenue); }
    .tarjeta-cabana .portada img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .tarjeta-cabana .sin-foto { height: 100%; display: flex; align-items: center; justify-content: center;
                                color: var(--borde-fuerte); font-size: 2rem;
                                background: repeating-linear-gradient(45deg, var(--panel-tenue), var(--panel-tenue) 10px, #eef2ee 10px, #eef2ee 20px); }
    .tarjeta-cabana .estado { position: absolute; top: 8px; left: 8px; }
    .tarjeta-cabana .conteo-fotos {
        position: absolute; bottom: 8px; right: 8px; background: rgba(28,42,35,.65);
        color: #fff; font-size: .7rem; padding: .1rem .5rem; border-radius: 99px;
    }
    .tarjeta-cabana .conteo-fotos.vacio { background: rgba(28,42,35,.4); }
</style>

<?= $this->endSection() ?>
