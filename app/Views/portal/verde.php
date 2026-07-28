<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'solicitudes';
$titulo  = 'Gesto verde';

$colores = [
    'pedido'     => 'secondary',
    'confirmado' => 'success',
    'descartado' => 'warning',
    'canjeado'   => 'dark',
];
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-1">Gesto verde</h1>
        <p class="mb-0 opacity-75 small">Una lavada menos, algo de nuestra parte.</p>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php // Los vales ganados van arriba del todo: es lo que el huésped viene a
      // mirar cuando ya conoce el programa. ?>
<?php if ($vales !== []): ?>
    <div class="card mt-3 border-success">
        <div class="card-body text-center">
            <i class="bi bi-cup-hot text-success" style="font-size: 2rem;"></i>
            <h2 class="h5 mt-2 mb-1">
                Tienes <?= count($vales) ?> invitación<?= count($vales) === 1 ? '' : 'es' ?>
            </h2>
            <p class="text-muted small mb-0">
                Dilo en el restaurante o en recepción y elige lo que te apetezca de la carta
                de bebidas. Válidas hasta que te vayas.
            </p>
        </div>
    </div>
<?php endif ?>

<div class="card mt-3">
    <div class="card-body">
        <?php if (! empty($texto)): ?>
            <p class="mb-3"><?= esc($texto) ?></p>
        <?php endif ?>

        <?php if ($hoy !== null): ?>
            <?php // Ya lo pidió hoy: se le dice en qué punto está, sin prometer
                  // el premio hasta que alguien haya confirmado el ahorro. ?>
            <div class="alert alert-<?= $hoy['estado'] === 'descartado' ? 'warning' : 'success' ?> mb-0">
                <?php if ($hoy['estado'] === 'pedido'): ?>
                    <i class="bi bi-check-circle me-1"></i>
                    <strong>Apuntado para hoy.</strong>
                    No tocaremos tu ropa de cama ni tus toallas. Cuando el equipo pase por la
                    cabaña y lo confirme, tu invitación aparecerá aquí.
                <?php elseif ($hoy['estado'] === 'descartado'): ?>
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Hoy hubo que cambiarla igualmente.</strong>
                    <?= esc($hoy['motivo']) ?>
                    Lo intentamos mañana.
                <?php else: ?>
                    <i class="bi bi-check-circle me-1"></i>
                    <strong>Confirmado.</strong> Gracias: hoy nos has ahorrado una lavada entera.
                <?php endif ?>
            </div>
        <?php elseif ($disponible['ok']): ?>
            <form method="post" action="<?= site_url('estancia/' . $token . '/verde') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-success w-100 py-3">
                    <i class="bi bi-leaf me-1"></i>
                    Hoy no necesito toallas ni sábanas limpias
                </button>
                <div class="form-text text-center mt-2">
                    Se pide antes de las <?= esc($hora_tope) ?>, que es cuando el equipo
                    empieza a preparar las cabañas.
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-light border mb-0 small">
                <i class="bi bi-info-circle me-1"></i>
                <?= esc($disponible['motivo']) ?>
            </div>
        <?php endif ?>
    </div>
</div>

<?php // Honestidad sobre el programa: qué se ahorra y por qué hay un límite.
      // Un huésped que entiende el motivo no lo vive como una restricción. ?>
<div class="card mt-3">
    <div class="card-body small text-muted">
        <p class="mb-2">
            <strong class="text-body">Por qué lo hacemos.</strong>
            Lavar la ropa de una cabaña gasta agua, energía y detergente. En un lugar como este,
            junto a un lago y rodeado de bosque, esa cuenta se nota.
        </p>
        <p class="mb-0">
            <strong class="text-body">Por qué hay un límite.</strong>
            Cada cierto número de noches te cambiamos la ropa aunque no lo pidas. Es por higiene,
            y ahí no negociamos.
        </p>
    </div>
</div>

<?php if ($historial !== []): ?>
    <div class="card mt-3">
        <div class="card-header bg-white small fw-semibold">Tus noches</div>
        <div class="list-group list-group-flush">
            <?php foreach ($historial as $h): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <span class="small"><?= date('d/m/Y', strtotime($h['fecha'])) ?></span>
                    <span class="badge text-bg-<?= $colores[$h['estado']] ?>">
                        <?= esc($estados[$h['estado']]) ?>
                    </span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
