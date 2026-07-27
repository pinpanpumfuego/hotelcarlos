<?= $this->extend('portal/layout') ?>

<?php
$seccion = '';
$titulo  = lang('Portal.tuEstancia');

$dias = (int) (new DateTime(date('Y-m-d')))->diff(new DateTime($reserva['fecha_entrada']))->days;

// El registro solo se ofrece si aún tiene sentido rellenarlo.
$registroPendiente = $fase !== 'despues'
    && ($registro === null || in_array($registro['estado'], ['pendiente', 'rechazado'], true));
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-1"><?= esc(lang('Portal.bienvenida', [$reserva['huesped_nombre'] ?? ''])) ?></h1>
        <p class="mb-0 opacity-75 small">
            <?php if ($fase === 'antes'): ?>
                <?= esc(lang('Portal.faltan', [$dias])) ?>
            <?php elseif ($fase === 'durante'): ?>
                <?= esc(lang('Portal.estasAqui')) ?>
            <?php else: ?>
                <?= esc(lang('Portal.yaTeFuiste')) ?>
            <?php endif ?>
        </p>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php if ($registroPendiente): ?>
    <div class="card mt-3 border-warning">
        <div class="card-body">
            <div class="d-flex gap-2">
                <i class="bi bi-pencil-square text-warning fs-5"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= esc(lang('Portal.registroPendiente')) ?></div>
                    <p class="small text-muted mb-2"><?= esc(lang('Portal.registroTexto')) ?></p>
                    <?php if ($registro !== null): ?>
                        <a href="<?= site_url('registro/' . $registro['token']) ?>" class="btn btn-sm btn-bosque">
                            <?= esc(lang('Portal.registroBoton')) ?>
                        </a>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($registro !== null && $registro['estado'] === 'aprobado'): ?>
    <div class="alert alert-success d-flex gap-2 mt-3 mb-0 py-2 small">
        <i class="bi bi-check-circle-fill"></i>
        <div><?= esc(lang('Portal.registroHecho')) ?></div>
    </div>
<?php endif ?>

<div class="card mt-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6">
                <div class="dato"><?= esc(lang('Portal.entrada')) ?></div>
                <div class="fw-semibold"><?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></div>
            </div>
            <div class="col-6">
                <div class="dato"><?= esc(lang('Portal.salida')) ?></div>
                <div class="fw-semibold"><?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></div>
            </div>
            <div class="col-6">
                <div class="dato"><?= esc(lang('Portal.cabana')) ?></div>
                <div class="fw-semibold">
                    <?php if (! empty($reserva['unidad_nombre'])): ?>
                        <?= esc($reserva['unidad_nombre']) ?>
                    <?php else: ?>
                        <span class="text-muted fw-normal"><?= esc(lang('Portal.sinAsignar')) ?></span>
                    <?php endif ?>
                </div>
            </div>
            <div class="col-6">
                <div class="dato"><?= esc(lang('Portal.huespedes')) ?></div>
                <div class="fw-semibold">
                    <?= esc(lang('Portal.adultos', [(int) $reserva['adultos']])) ?><?php
                    if ((int) $reserva['ninos'] > 0) {
                        echo ' · ' . esc(lang('Portal.ninos', [(int) $reserva['ninos']]));
                    } ?>
                </div>
            </div>
        </div>

        <hr class="my-3">

        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="dato"><?= esc(lang('Portal.codigo')) ?></div>
                <div class="fw-semibold font-monospace"><?= esc($reserva['codigo']) ?></div>
            </div>
            <span class="badge text-bg-light border"><?= esc(lang('Portal.noches', [$noches])) ?></span>
        </div>
    </div>
</div>

<?php if ($acompanantes !== []): ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-people me-1"></i><?= esc(lang('Portal.acompanantes')) ?>
        </div>
        <div class="card-body py-2">
            <?php foreach ($acompanantes as $a): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span><?= esc(trim($a['nombre'] . ' ' . $a['apellidos'])) ?></span>
                    <?php if (! empty($a['es_menor'])): ?>
                        <span class="badge text-bg-light border"><?= esc(lang('Portal.menor')) ?></span>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?php if ($fase === 'durante'): ?>
    <div class="d-grid gap-2 mt-3">
        <a class="btn btn-bosque" href="<?= site_url('estancia/' . $token . '/carta') ?>">
            <i class="bi bi-cup-hot me-1"></i><?= esc(lang('Portal.pedirCarta')) ?>
        </a>
    </div>
<?php endif ?>

<?php if ($pendientes !== []): ?>
    <?php
    $etiquetas = [
        'pendiente' => ['warning', lang('Portal.pendiente')],
        'en_curso'  => ['info', lang('Portal.enCurso')],
        'resuelta'  => ['success', lang('Portal.resuelta')],
        'rechazada' => ['secondary', lang('Portal.rechazada')],
    ];
    ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small"><?= esc(lang('Portal.tusPeticiones')) ?></div>
        <div class="card-body py-2">
            <?php foreach (array_slice($pendientes, 0, 4) as $s): ?>
                <?php [$color, $texto] = $etiquetas[$s['estado']] ?? ['secondary', $s['estado']]; ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="small"><?= esc(\App\Models\SolicitudModel::TIPOS[$s['tipo']] ?? $s['tipo']) ?></span>
                    <span class="badge text-bg-<?= $color ?>"><?= esc($texto) ?></span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="d-grid gap-2 mt-3">
    <?php if (! empty($hotel->whatsapp)): ?>
        <a class="btn btn-outline-success"
           href="https://wa.me/<?= esc($hotel->whatsapp) ?>" target="_blank" rel="noopener">
            <i class="bi bi-whatsapp me-1"></i><?= esc(lang('Portal.escribir')) ?>
        </a>
    <?php endif ?>
    <?php if (! empty($hotel->telefono)): ?>
        <a class="btn btn-outline-secondary" href="tel:<?= esc(preg_replace('/\s+/', '', $hotel->telefono)) ?>">
            <i class="bi bi-telephone me-1"></i><?= esc(lang('Portal.llamar')) ?>
        </a>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
