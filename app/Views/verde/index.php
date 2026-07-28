<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.'); ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Gesto verde</h1>
        <p class="text-muted mb-0">Una lavada menos a cambio de una consumición.</p>
    </div>
    <?php if (puede('administracion.ver')): ?>
        <a href="<?= site_url('administracion') ?>#verde" class="btn btn-outline-secondary">
            <i class="bi bi-sliders me-1"></i>Configurar
        </a>
    <?php endif ?>
</div>

<?= view('partes/errores') ?>

<?php if (! $activo): ?>
    <div class="alert alert-warning">
        <strong>El programa está apagado.</strong>
        Se enciende en Administración, y hace falta elegir de qué categoría de la carta puede
        escoger el huésped. Mientras tanto, ni el portal ni recepción lo ofrecen.
    </div>
<?php endif ?>

<?php // El balance va arriba: es el número que decide si esto sigue el año que
      // viene, y abajo del todo no lo miraría nadie. ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-graph-up-arrow me-2"></i>¿Sale a cuenta?</span>
        <form method="get" class="d-flex gap-2 align-items-center">
            <input type="date" name="desde" value="<?= esc($desde) ?>" class="form-control form-control-sm">
            <input type="date" name="hasta" value="<?= esc($hasta) ?>" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-primary">Ver</button>
        </form>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-6 col-lg-3">
                <div class="text-muted small">Lavadas ahorradas</div>
                <div class="fs-2 fw-semibold"><?= (int) $balance['lavadas'] ?></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="text-muted small">Lo que costaban</div>
                <div class="fs-2 fw-semibold text-success">
                    <?= $balance['sabemos_costes'] ? $dinero((float) $balance['ahorro']) : '—' ?>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="text-muted small">Coste de lo regalado</div>
                <div class="fs-2 fw-semibold text-danger"><?= $dinero((float) $balance['coste_premios']) ?></div>
                <div class="small text-muted">
                    <?= (int) $balance['canjeados'] ?> consumición<?= (int) $balance['canjeados'] === 1 ? '' : 'es' ?>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="text-muted small">Diferencia</div>
                <div class="fs-2 fw-semibold <?= (float) $balance['neto'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $balance['sabemos_costes'] ? $dinero((float) $balance['neto']) : '—' ?>
                </div>
            </div>
        </div>

        <?php if (! $balance['sabemos_costes']): ?>
            <div class="alert alert-warning small mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Falta cuánto cuesta una lavada.</strong>
                Sin ese número no se puede decir si el programa sale a cuenta, y no lo vamos a
                estimar: el agua, la energía, el detergente y el rato de quien lava son de este
                hotel. Se pone en Administración.
            </div>
        <?php endif ?>

        <div class="alert alert-light border small mt-3 mb-0">
            <strong>Cómo se cuenta.</strong>
            Una lavada se da por ahorrada cuando housekeeping confirma que no cambió nada, se
            haya tomado la bebida o no: el ahorro ya ocurrió. Y lo regalado se cuenta por
            <strong>lo que costó producirlo</strong>, no por su precio de venta —quien se toma
            una limonada de la casa no siempre la habría comprado.
            <?php if ((float) $balance['pvp_regalado'] > 0): ?>
                A precio de carta habrían sido <?= $dinero((float) $balance['pvp_regalado']) ?>.
            <?php endif ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-check2-square me-2"></i>Por confirmar hoy
                <span class="badge text-bg-secondary ms-1"><?= count($pendientes) ?></span>
            </div>

            <?php if ($pendientes === []): ?>
                <div class="card-body text-muted small">
                    Nadie ha pedido gesto verde hoy, o ya está todo confirmado.
                </div>
            <?php else: ?>
                <div class="card-body pb-0">
                    <p class="small text-muted">
                        Confirma solo si de verdad no se cambió nada. Si hubo que cambiar la ropa
                        —una mancha, una sábana rota, el huésped cambió de idea— usa «Hubo que
                        cambiarla»: sin ahorro no hay premio, y así queda escrito por qué.
                    </p>
                </div>

                <div class="list-group list-group-flush">
                    <?php foreach ($pendientes as $p): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold"><?= esc($p['unidad'] ?? 'Sin cabaña') ?></div>
                                    <div class="small text-muted">
                                        <?= esc($p['reserva_codigo']) ?> ·
                                        pedido desde <?= $p['origen'] === 'portal' ? 'el portal' : 'recepción' ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="post" action="<?= site_url('verde/confirmar/' . $p['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-success">
                                            <i class="bi bi-check-lg me-1"></i>No se cambió
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-warning" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#descartar-<?= $p['id'] ?>">
                                        Hubo que cambiarla
                                    </button>
                                </div>
                            </div>

                            <div class="collapse mt-2" id="descartar-<?= $p['id'] ?>">
                                <form method="post" action="<?= site_url('verde/descartar/' . $p['id']) ?>"
                                      class="d-flex gap-2">
                                    <?= csrf_field() ?>
                                    <input type="text" name="motivo" class="form-control form-control-sm"
                                           maxlength="200" required
                                           placeholder="¿Por qué? (mancha, sábana rota, lo pidió el huésped…)">
                                    <button class="btn btn-sm btn-outline-warning">Anotar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-raised-hand me-2"></i>Apuntar por el huésped
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Para quien lo dice en recepción o deja el colgante puesto. No todo el mundo
                    abre el portal, y aquí la cobertura no siempre acompaña.
                </p>

                <?php if (! $activo): ?>
                    <p class="text-muted small mb-0">El programa está apagado.</p>
                <?php elseif ($alojados === []): ?>
                    <p class="text-muted small mb-0">
                        Nadie puede acogerse hoy: o ya lo pidieron, o llegan o se van hoy, o les
                        toca ropa limpia por el tope de <?= (int) $max ?> noches.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= site_url('verde/pedir') ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <select name="reserva_id" class="form-select form-select-sm" required>
                            <option value="">Elige la cabaña…</option>
                            <?php foreach ($alojados as $a): ?>
                                <option value="<?= $a['id'] ?>">
                                    <?= esc($a['unidad'] ?? 'Sin cabaña') ?> ·
                                    <?= esc(trim(($a['huesped'] ?? '') . ' ' . ($a['apellidos'] ?? ''))) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <button class="btn btn-sm btn-success">Apuntar</button>
                    </form>
                <?php endif ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold text-muted">
                <i class="bi bi-info-circle me-2"></i>Las reglas
            </div>
            <div class="card-body small text-muted">
                <p class="mb-2">
                    <strong class="text-body">Ni el día de llegada ni el de salida.</strong>
                    El día que se va, la ropa se lava igual: premiar esa noche sería pagar una
                    bebida por un ahorro que no existe.
                </p>
                <p class="mb-2">
                    <strong class="text-body">Máximo <?= (int) $max ?> noches seguidas.</strong>
                    Después toca ropa limpia aunque no quiera. Es por higiene, no por gasto.
                </p>
                <p class="mb-0">
                    <strong class="text-body">Se pide antes de las <?= esc($hora_tope) ?>.</strong>
                    Después, el equipo ya está preparando las cabañas y el aviso llega tarde.
                </p>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
