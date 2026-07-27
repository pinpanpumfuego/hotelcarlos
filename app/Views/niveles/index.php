<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.'); ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Niveles y referidos</h1>
        <p class="text-muted mb-0">Qué se le reconoce a quien vuelve, y qué se le da a quien trae gente.</p>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>El nivel de cada persona no se guarda: se calcula</strong> con sus estancias y su gasto.
    Si mañana cambias un umbral, todo el mundo se recoloca solo. Basta con cumplir
    <strong>uno</strong> de los dos criterios: quien vino dos veces gastando mucho y quien vino seis
    gastando poco son los dos buenos clientes, por razones distintas.
</div>

<!-- ═══ Los niveles ═══ -->
<div class="row g-3 mb-4">
    <?php foreach ($niveles as $n): ?>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-<?= esc($n['color']) ?> <?= (int) $n['activo'] === 0 ? 'opacity-50' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h2 class="h5 mb-0"><?= esc($n['nombre']) ?></h2>
                        <span class="badge text-bg-<?= esc($n['color']) ?>"><?= (int) $n['cuantos'] ?></span>
                    </div>
                    <div class="small text-muted mb-2">
                        <?php if ((int) $n['estancias_min'] === 0 && (float) $n['gasto_min'] <= 0): ?>
                            Todo el mundo empieza aquí
                        <?php else: ?>
                            <?= (int) $n['estancias_min'] ?> estancias
                            <?php if ((float) $n['gasto_min'] > 0): ?>
                                o <?= $dinero((float) $n['gasto_min']) ?>
                            <?php endif ?>
                        <?php endif ?>
                        <?php if ((float) $n['descuento_pct'] > 0): ?>
                            · <?= rtrim(rtrim(number_format((float) $n['descuento_pct'], 2, ',', '.'), '0'), ',') ?> % de descuento
                        <?php endif ?>
                    </div>

                    <ul class="small text-muted ps-3 mb-0">
                        <?php foreach (array_filter(array_map('trim', explode("\n", (string) $n['beneficios']))) as $b): ?>
                            <li><?= esc($b) ?></li>
                        <?php endforeach ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php if ($total === 0): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-people me-1"></i>
        Todavía no hay ningún huésped con estancias cumplidas, así que todos los contadores están
        en cero. Los números de arriba dicen a cuánta gente le tocaría <strong>hoy</strong> cada
        nivel: es lo que hay que mirar antes de ajustar un umbral, para no poner uno que no
        alcance nadie.
    </div>
<?php endif ?>

<!-- ═══ Ajustes ═══ -->
<?php if (puede('niveles.gestionar')): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2"></i>Ajustar los niveles</div>
        <div class="card-body py-2">
            <p class="small text-muted mb-0">
                Los beneficios se escriben uno por línea. <strong>Son texto a propósito</strong>:
                «salida tardía si hay hueco» o «una botella en la primera noche» las hace una
                persona, no un programa. Fingir que el sistema las aplica sería peor que decir
                claramente que hay que mirarlas.
            </p>
        </div>

        <?php foreach ($niveles as $n): ?>
            <div class="card-body border-top">
                <form method="post" action="<?= site_url('niveles/guardar/' . $n['id']) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Nombre</label>
                        <input type="text" name="nombre" class="form-control form-control-sm" required maxlength="60"
                               value="<?= esc($n['nombre']) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Estancias</label>
                        <input type="number" name="estancias_min" class="form-control form-control-sm" min="0"
                               value="<?= (int) $n['estancias_min'] ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">O gasto</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="gasto_min" class="form-control" min="0" step="any"
                                   value="<?= (float) $n['gasto_min'] ?>">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Descuento</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="descuento_pct" class="form-control" min="0" max="50" step="any"
                                   value="<?= (float) $n['descuento_pct'] ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-2 d-flex align-items-center pb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" value="1"
                                   id="nivelActivo<?= $n['id'] ?>" <?= (int) $n['activo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="nivelActivo<?= $n['id'] ?>">Activo</label>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-check-lg"></i></button>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">Beneficios, uno por línea</label>
                        <textarea name="beneficios" class="form-control form-control-sm" rows="3"><?= esc($n['beneficios']) ?></textarea>
                    </div>
                </form>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<!-- ═══ Referidos ═══ -->
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-share me-2"></i>Programa de referidos
                <?php if ($ref['activo']): ?>
                    <span class="badge text-bg-success ms-1">En marcha</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary ms-1">Apagado</span>
                <?php endif ?>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Cuando alguien recomienda el alojamiento y su recomendado <strong>se va después
                    de haber venido</strong>, los dos reciben un cupón. Se entrega al salir y no al
                    reservar: si se diera al reservar, una cancelación dejaría dos cupones regalados
                    por una estancia que no ocurrió.
                </p>

                <?php if (puede('niveles.gestionar')): ?>
                    <form method="post" action="<?= site_url('niveles/referidos') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Cupón para cada uno</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="premio_pct" class="form-control" min="0" max="50" step="any"
                                           value="<?= $ref['pct'] ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Válido durante</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="premio_dias" class="form-control" min="30"
                                           value="<?= $ref['dias'] ?>">
                                    <span class="input-group-text">días</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="activo" value="1" id="refActivo"
                                   <?= $ref['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="refActivo">
                                <strong>Programa en marcha</strong>
                                <span class="d-block form-text">
                                    Apagado, los códigos siguen existiendo pero no se reparte nada.
                                </span>
                            </label>
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    </form>
                <?php endif ?>

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="bi bi-ticket-perforated me-1"></i>
                    Los cupones que se emiten son de <strong>un solo uso y una sola persona</strong>.
                    Un cupón de referido que empiece a circular por WhatsApp deja de ser un premio y
                    pasa a ser tu tarifa.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2"></i>Quién ha traído a quién</div>
            <?php if ($referidos === []): ?>
                <div class="card-body text-muted small">
                    Nadie todavía. El código de cada huésped sale en su ficha, para poder dárselo
                    cuando se va contento — que es el único momento en que alguien recomienda algo.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recomendó</th>
                                <th>Vino</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referidos as $r): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('huespedes/ver/' . $r['referidor_id']) ?>" class="text-decoration-none">
                                            <?= esc(trim($r['referidor_nombre'] . ' ' . $r['referidor_apellidos'])) ?>
                                        </a>
                                        <div class="small text-muted font-monospace"><?= esc($r['codigo_usado']) ?></div>
                                    </td>
                                    <td class="small">
                                        <?php if ($r['referido_id']): ?>
                                            <a href="<?= site_url('huespedes/ver/' . $r['referido_id']) ?>" class="text-decoration-none">
                                                <?= esc(trim(($r['referido_nombre'] ?? '') . ' ' . ($r['referido_apellidos'] ?? ''))) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif ?>
                                        <?php if ($r['reserva_codigo']): ?>
                                            <div class="text-muted font-monospace" style="font-size: .75rem;">
                                                <?= esc($r['reserva_codigo']) ?>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?= $r['estado'] === 'cumplido' ? 'success' : ($r['estado'] === 'anulado' ? 'secondary' : 'warning') ?>">
                                            <?= esc($estados_ref[$r['estado']]) ?>
                                        </span>
                                        <?php if ($r['cupon_referidor']): ?>
                                            <div class="small text-muted font-monospace"><?= esc($r['cupon_referidor']) ?></div>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
