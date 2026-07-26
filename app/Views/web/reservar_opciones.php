<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion" style="max-width: 860px;">
    <div class="text-center mb-4 reveal visible">
        <p class="etiqueta"><?= esc(lang('Web.paso2de3')) ?></p>
        <h1 class="titulo-seccion"><?= esc(lang('Web.disponibilidad')) ?></h1>
        <p class="text-muted mt-2">
            <i class="bi bi-calendar3 me-1"></i>
            <?= esc(lang('Web.del')) ?> <strong><?= esc(date('d/m/Y', strtotime($busqueda['entrada']))) ?></strong>
            <?= esc(lang('Web.al')) ?> <strong><?= esc(date('d/m/Y', strtotime($busqueda['salida']))) ?></strong>
            <?php // El plural se resuelve en cada idioma: en alemán «1 Nacht» y
                  // «2 Nächte» no se hacen añadiendo una «s» al final ?>
            · <?= esc(lang('Web.nochesN', [$noches])) ?>
            · <?= esc(lang('Web.adultosN', [(int) $busqueda['adultos']])) ?><?php
                if ((int) $busqueda['ninos'] > 0): ?> · <?= esc(lang('Web.ninosN', [(int) $busqueda['ninos']])) ?><?php endif ?>
            &nbsp;<a href="<?= url_web('reservar') ?>" class="small"><?= esc(lang('Web.cambiar')) ?></a>
        </p>
    </div>

    <?php if (empty($opciones)): ?>
        <div class="card tarjeta-tipo text-center">
            <div class="card-body p-5">
                <i class="bi bi-emoji-frown fs-1 text-muted"></i>
                <h2 class="h5 mt-3"><?= esc(lang('Web.sinDisponibilidad')) ?></h2>
                <p class="text-muted"><?= esc(lang('Web.pruebaOtrasFechas')) ?></p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?= url_web('reservar') ?>" class="btn btn-reserva"><?= esc(lang('Web.probarOtrasFechas')) ?></a>
                    <a href="https://wa.me/<?= esc($hotel->whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary rounded-pill">
                        <i class="bi bi-whatsapp me-1"></i>WhatsApp
                    </a>
                </div>
            </div>
        </div>
    <?php endif ?>

    <?php foreach ($opciones as $op): ?>
        <div class="card tarjeta-tipo mb-4">
            <div class="row g-0 align-items-stretch">
                <div class="col-md-5">
                    <?= view('web/_galeria_tipo', [
                        'medios'     => $op['galeria'] ?? [],
                        'variante'   => 'cabana',
                        'nombre'     => $op['tipo']['nombre'],
                        'idCarrusel' => 'r' . $op['tipo']['id'],
                    ]) ?>
                </div>
                <div class="col-md-7 d-flex align-items-center">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <h2 class="h4 mb-1"><?= esc($op['tipo']['nombre']) ?></h2>
                            <?php if ($op['libres'] <= 2): ?>
                                <span class="badge text-bg-warning"><?= esc(lang('Web.soloQuedanN', [(int) $op['libres']])) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-success"><?= esc(lang('Web.disponiblesN', [(int) $op['libres']])) ?></span>
                            <?php endif ?>
                        </div>
                        <p class="text-muted small mb-3"><?= esc($op['tipo']['descripcion'] ?? '') ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="chip">
                                <i class="bi bi-people me-1"></i><?= esc(lang('Web.hasta')) ?>
                                <?= esc($op['tipo']['capacidad']) ?> <?= esc(lang('Web.personas')) ?>
                            </span>
                            <span class="chip"><i class="bi bi-moon me-1"></i><?= esc(lang('Web.nochesN', [$noches])) ?></span>
                            <?php foreach (array_slice($op['servicios'] ?? [], 0, 6) as $s): ?>
                                <span class="chip"><i class="bi <?= esc($s['icono']) ?> me-1"></i><?= esc($s['nombre']) ?></span>
                            <?php endforeach ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <div class="precio-desde fs-4">$<?= number_format($op['total'], 0, ',', '.') ?> COP</div>
                                <div class="small text-muted">
                                    <?= esc(lang('Web.totalEstancia')) ?> ·
                                    $<?= number_format($op['porNoche'], 0, ',', '.') ?> <?= esc(lang('Web.porNocheMedia')) ?>
                                </div>
                                <?php if (! empty($op['cotizacion']['suplementos'])): ?>
                                    <div class="small text-muted"><?= esc(lang('Web.incluyeSuplemento')) ?></div>
                                <?php endif ?>
                                <a class="small" data-bs-toggle="collapse" href="#detalle<?= esc($op['tipo']['id']) ?>" role="button">
                                    <?= esc(lang('Web.verDetalleNoche')) ?>
                                </a>
                            </div>
                            <form method="post" action="<?= url_web('reservar/datos') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="entrada" value="<?= esc($busqueda['entrada']) ?>">
                                <input type="hidden" name="salida" value="<?= esc($busqueda['salida']) ?>">
                                <input type="hidden" name="adultos" value="<?= esc($busqueda['adultos']) ?>">
                                <input type="hidden" name="ninos" value="<?= esc($busqueda['ninos']) ?>">
                                <input type="hidden" name="tipo_id" value="<?= esc($op['tipo']['id']) ?>">
                                <button class="btn btn-reserva"><?= esc(lang('Web.elegir')) ?><i class="bi bi-arrow-right ms-1"></i></button>
                            </form>
                        </div>

                        <div class="collapse mt-3" id="detalle<?= esc($op['tipo']['id']) ?>">
                            <div class="detalle-precio">
                                <?php foreach ($op['cotizacion']['noches'] as $n): ?>
                                    <div class="linea">
                                        <span>
                                            <?= esc($n['dia']) ?> <?= date('d/m', strtotime($n['fecha'])) ?>
                                            <?php foreach ($n['ajustes'] as $a): ?>
                                                <?php if ($a['importe'] < 0): ?>
                                                    <span class="etiqueta-desc"><?= esc($a['concepto']) ?></span>
                                                <?php endif ?>
                                            <?php endforeach ?>
                                        </span>
                                        <span>$<?= number_format($n['precio'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endforeach ?>
                                <?php foreach ($op['cotizacion']['suplementos'] as $s): ?>
                                    <div class="linea">
                                        <span><?= esc($s['concepto']) ?> <span class="text-muted">· <?= esc($s['detalle']) ?></span></span>
                                        <span>$<?= number_format($s['importe'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endforeach ?>
                                <div class="linea total">
                                    <span><?= esc(lang('Web.total')) ?></span>
                                    <span>$<?= number_format($op['total'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <?php if (! empty($opciones)): ?>
        <p class="text-center text-muted small">
            <?= esc(lang('Web.notaPrecios')) ?>
        </p>
    <?php endif ?>
</div>

<style>
    .detalle-precio { border-top: 1px solid rgba(0,0,0,.08); padding-top: .6rem; font-size: .86rem; }
    .detalle-precio .linea { display: flex; justify-content: space-between; gap: 1rem; padding: .18rem 0; color: #5a6660; }
    .detalle-precio .linea.total { border-top: 1px solid rgba(0,0,0,.08); margin-top: .35rem;
                                   padding-top: .45rem; font-weight: 600; color: inherit; }
    .etiqueta-desc { display: inline-block; font-size: .72rem; padding: .05rem .4rem; border-radius: 99px;
                     background: #e9f4ee; color: #1c5c3c; margin-left: .25rem; }
</style>

<?= $this->endSection() ?>
