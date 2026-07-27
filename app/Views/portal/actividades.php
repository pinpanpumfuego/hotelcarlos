<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'solicitudes';
$titulo  = lang('Portal.actividades');

$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-0"><?= esc(lang('Portal.actividades')) ?></h1>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php if ($experiencias === []): ?>
    <div class="card mt-3">
        <div class="card-body text-center py-5">
            <i class="bi bi-binoculars fs-2 d-block mb-2 text-muted opacity-50"></i>
            <p class="text-muted mb-0"><?= esc(lang('Portal.sinActividades')) ?></p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($experiencias as $e): ?>
        <div class="card mt-3">
            <div class="card-body">
                <h2 class="h6 mb-1"><?= esc($e['nombre']) ?></h2>

                <div class="small text-muted mb-2">
                    <?= $pesos((float) $e['precio']) ?>
                    <?= esc($e['tipo_precio'] === 'grupo' ? lang('Portal.porGrupo') : lang('Portal.porPersona')) ?>
                    <?php if (! empty($e['duracion_min'])): ?>
                        · <?= esc(lang('Portal.duracion')) ?>: <?= esc(lang('Portal.minutos', [(int) $e['duracion_min']])) ?>
                    <?php endif ?>
                </div>

                <?php if (! empty($e['descripcion'])): ?>
                    <p class="small mb-2"><?= esc(mb_substr($e['descripcion'], 0, 300)) ?></p>
                <?php endif ?>

                <?php if (! empty($e['horarios'])): ?>
                    <div class="small text-muted mb-2">
                        <i class="bi bi-clock me-1"></i><?= esc($e['horarios']) ?>
                    </div>
                <?php endif ?>

                <form method="post" action="<?= site_url('estancia/' . $token . '/apuntarse') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="experiencia_id" value="<?= (int) $e['id'] ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-4">
                            <label class="form-label small mb-1"><?= esc(lang('Portal.huespedes')) ?></label>
                            <input type="number" name="personas" class="form-control form-control-sm"
                                   min="1" max="20" value="<?= (int) $reserva['adultos'] ?>">
                        </div>
                        <div class="col-8">
                            <label class="form-label small mb-1"><?= esc(lang('Portal.paraCuando')) ?></label>
                            <input type="text" name="cuando" class="form-control form-control-sm" maxlength="100">
                        </div>
                    </div>
                    <div class="d-grid mt-2">
                        <button class="btn btn-sm btn-bosque"><?= esc(lang('Portal.reservarActividad')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach ?>

    <?php // Se dice claramente que no queda confirmado: el aforo y el guía los
          // sabe recepción, no este formulario. ?>
    <div class="alert alert-light border small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i><?= esc(lang('Portal.actividadPedida')) ?>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
