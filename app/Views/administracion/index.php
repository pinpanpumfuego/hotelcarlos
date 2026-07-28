<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-1">Administración</h1>
<p class="text-muted">Qué le falta al sistema para estar en marcha.</p>

<?= view('administracion/_nav', ['activa' => '']) ?>

<?= view('partes/errores') ?>

<?php
$colores = ['grave' => 'danger', 'aviso' => 'warning', 'suelto' => 'secondary'];
$iconos  = ['grave' => 'bi-exclamation-octagon', 'aviso' => 'bi-exclamation-triangle', 'suelto' => 'bi-info-circle'];
$graves  = array_filter($pendientes, static fn (array $a): bool => $a['nivel'] === 'grave');
?>

<?php if ($pendientes === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
            <h2 class="h4 mt-3 mb-1">No queda nada pendiente</h2>
            <p class="text-muted mb-0">
                El sistema está configurado. Los ajustes siguen en las pestañas de arriba.
            </p>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-center gap-4 flex-wrap">
            <div>
                <div class="fs-1 fw-semibold lh-1 text-<?= $graves !== [] ? 'danger' : 'warning' ?>">
                    <?= count($pendientes) ?>
                </div>
                <div class="text-muted small">
                    cosa<?= count($pendientes) === 1 ? '' : 's' ?> pendiente<?= count($pendientes) === 1 ? '' : 's' ?>
                </div>
            </div>
            <div class="flex-grow-1" style="min-width: 15rem;">
                <?php if ($graves !== []): ?>
                    <p class="mb-0">
                        <strong class="text-danger"><?= count($graves) ?> son graves</strong>: cosas
                        abiertas que no deberían estarlo, o dinero que el hotel no puede cobrar.
                        Empieza por esas.
                    </p>
                <?php else: ?>
                    <p class="mb-0">
                        Nada grave. Lo que queda se puede ir haciendo con el hotel ya funcionando.
                    </p>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column gap-3">
        <?php foreach ($pendientes as $p): ?>
            <div class="card border-0 shadow-sm border-start border-4 border-<?= $colores[$p['nivel']] ?>">
                <div class="card-body d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div style="max-width: 46rem;">
                        <h2 class="h6 mb-1">
                            <i class="bi <?= $iconos[$p['nivel']] ?> text-<?= $colores[$p['nivel']] ?> me-1"></i>
                            <?= esc($p['titulo']) ?>
                        </h2>
                        <p class="text-muted small mb-0"><?= esc($p['detalle']) ?></p>
                    </div>
                    <?php // Wompi, Siigo y el correo viven detrás del permiso de
                          // credenciales. A quien no lo tiene no se le ofrece un
                          // botón que le va a rebotar: se le dice a quién pedirlo. ?>
                    <?php $deCredenciales = str_starts_with((string) $p['enlace'], 'administracion/cobros'); ?>
                    <?php if ($p['enlace'] !== null && (! $deCredenciales || puede('administracion.integraciones'))): ?>
                        <a href="<?= site_url($p['enlace']) ?>" class="btn btn-sm btn-outline-<?= $colores[$p['nivel']] ?>">
                            Arreglar<i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    <?php elseif ($deCredenciales): ?>
                        <span class="badge text-bg-light text-muted align-self-center">Lo arregla gerencia</span>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <p class="text-muted small mt-4">
        Esta lista se calcula sola cada vez que entras: no hay nada que marcar como hecho.
        Cuando algo se arregla, desaparece.
    </p>
<?php endif ?>

<?= $this->endSection() ?>
