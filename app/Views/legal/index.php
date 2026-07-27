<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$color = ['bien' => 'success', 'atencion' => 'warning', 'mal' => 'danger', 'sin_dato' => 'secondary'];
$icono = ['bien' => 'check-circle-fill', 'atencion' => 'exclamation-triangle-fill',
          'mal' => 'x-circle-fill', 'sin_dato' => 'dash-circle'];
$nombre = ['bien' => 'En orden', 'atencion' => 'Hay que mirarlo', 'mal' => 'Falta', 'sin_dato' => 'Sin dato'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Cumplimiento</h1>
        <p class="text-muted mb-0">Qué falta, qué vence y qué lleva demasiado tiempo sin hacerse.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('legal/derechos') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-person-lock me-1"></i>Derechos del titular
        </a>
        <?php if (puede('cumplimiento.gestionar')): ?>
            <a href="<?= site_url('legal/politicas') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-file-text me-1"></i>Políticas
            </a>
            <a href="<?= site_url('legal/datos') ?>" class="btn btn-primary">
                <i class="bi bi-building me-1"></i>Datos legales
            </a>
        <?php endif ?>
    </div>
</div>

<?= view('partes/errores') ?>

<?php // Esto va arriba del todo y en primera persona: es lo que este panel NO
      // hace, y confundirlo con un certificado sería el peor resultado posible. ?>
<div class="alert alert-light border">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Esto no certifica nada.</strong> No tramita el RNT, no decide si alguien es menor de
    edad, no clasifica impuestos y no sustituye a un abogado ni a un contador. Mira los datos que
    ya están en el sistema y te dice qué falta. La responsabilidad sigue siendo tuya; esto solo
    sirve para que nadie pueda decir «no me acordaba».
</div>

<div class="row g-3 mb-4">
    <?php foreach (['mal', 'atencion', 'sin_dato', 'bien'] as $e): ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small"><?= $nombre[$e] ?></div>
                    <div class="fs-3 fw-semibold text-<?= $color[$e] ?>"><?= (int) $resumen[$e] ?></div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php if ($derechos !== []): ?>
    <?php $vencidas = array_filter($derechos, static fn (array $d): bool => $d['vence_en'] !== null && $d['vence_en'] < date('Y-m-d')); ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-<?= $vencidas !== [] ? 'danger' : 'info' ?>">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-person-lock me-2"></i>Gente pidiendo sus datos
            <span class="badge text-bg-<?= $vencidas !== [] ? 'danger' : 'info' ?> ms-1"><?= count($derechos) ?></span>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($derechos as $d): ?>
                <a href="<?= site_url('legal/derecho/' . $d['id']) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <span class="font-monospace small"><?= esc($d['codigo']) ?></span>
                        <strong class="ms-1"><?= esc($d['nombre']) ?></strong>
                        <div class="small text-muted"><?= esc($tipos[$d['tipo']]) ?></div>
                    </div>
                    <div class="text-end small">
                        <?php if ($d['vence_en'] !== null && $d['vence_en'] < date('Y-m-d')): ?>
                            <span class="badge text-bg-danger">Fuera de plazo</span>
                        <?php else: ?>
                            <span class="text-muted">
                                Antes del <?= $d['vence_en'] ? date('d/m/Y', strtotime($d['vence_en'])) : '—' ?>
                            </span>
                        <?php endif ?>
                    </div>
                </a>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?php foreach ($revision as $clave => $grupo): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><?= esc($grupo['titulo']) ?></div>
        <div class="list-group list-group-flush">
            <?php foreach ($grupo['puntos'] as $p): ?>
                <div class="list-group-item">
                    <div class="d-flex gap-3">
                        <i class="bi bi-<?= $icono[$p['estado']] ?> text-<?= $color[$p['estado']] ?> fs-5"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <strong><?= esc($p['titulo']) ?></strong>
                                <span class="badge text-bg-<?= $color[$p['estado']] ?>"><?= $nombre[$p['estado']] ?></span>
                            </div>
                            <div class="small"><?= esc($p['detalle']) ?></div>
                            <?php // El «por qué» va siempre, también cuando está
                                  // bien: dentro de seis meses nadie recordará
                                  // para qué servía esta línea. ?>
                            <div class="small text-muted mt-1"><?= esc($p['porque']) ?></div>

                            <?php if ($clave === 'seguridad' && $p['titulo'] === 'Copia de seguridad' && puede('cumplimiento.gestionar')): ?>
                                <form method="post" action="<?= site_url('legal/copia') ?>" class="mt-2">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-check-lg me-1"></i>Hoy la comprobé
                                    </button>
                                </form>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endforeach ?>

<?= $this->endSection() ?>
