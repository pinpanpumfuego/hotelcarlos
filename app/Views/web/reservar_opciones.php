<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion" style="max-width: 860px;">
    <div class="text-center mb-4 reveal visible">
        <p class="etiqueta">Paso 2 de 3</p>
        <h1 class="titulo-seccion">Disponibilidad</h1>
        <p class="text-muted mt-2">
            <i class="bi bi-calendar3 me-1"></i>
            Del <strong><?= esc(date('d/m/Y', strtotime($busqueda['entrada']))) ?></strong>
            al <strong><?= esc(date('d/m/Y', strtotime($busqueda['salida']))) ?></strong>
            · <?= esc($noches) ?> noche<?= $noches > 1 ? 's' : '' ?>
            · <?= esc($busqueda['adultos']) ?> adulto<?= $busqueda['adultos'] > 1 ? 's' : '' ?><?= $busqueda['ninos'] > 0 ? ' y ' . esc($busqueda['ninos']) . ' niño' . ($busqueda['ninos'] > 1 ? 's' : '') : '' ?>
            &nbsp;<a href="<?= site_url('reservar') ?>" class="small">Cambiar</a>
        </p>
    </div>

    <?php if (empty($opciones)): ?>
        <div class="card tarjeta-tipo text-center">
            <div class="card-body p-5">
                <i class="bi bi-emoji-frown fs-1 text-muted"></i>
                <h2 class="h5 mt-3">No hay cabañas disponibles en esas fechas</h2>
                <p class="text-muted">Prueba con otras fechas, o escríbenos y buscamos una alternativa contigo.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?= site_url('reservar') ?>" class="btn btn-reserva">Probar otras fechas</a>
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
                    <?= view('web/_escena', ['variante' => 'cabana']) ?>
                </div>
                <div class="col-md-7 d-flex align-items-center">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <h2 class="h4 mb-1"><?= esc($op['tipo']['nombre']) ?></h2>
                            <?php if ($op['libres'] <= 2): ?>
                                <span class="badge text-bg-warning">¡Solo <?= esc($op['libres']) ?> disponible<?= $op['libres'] > 1 ? 's' : '' ?>!</span>
                            <?php else: ?>
                                <span class="badge text-bg-success"><?= esc($op['libres']) ?> disponibles</span>
                            <?php endif ?>
                        </div>
                        <p class="text-muted small mb-3"><?= esc($op['tipo']['descripcion'] ?? '') ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="chip"><i class="bi bi-people me-1"></i>Hasta <?= esc($op['tipo']['capacidad']) ?> personas</span>
                            <span class="chip"><i class="bi bi-moon me-1"></i><?= esc($noches) ?> noche<?= $noches > 1 ? 's' : '' ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <div class="precio-desde fs-4">$<?= number_format($op['total'], 0, ',', '.') ?> COP</div>
                                <div class="small text-muted">
                                    total de la estancia · $<?= number_format($op['porNoche'], 0, ',', '.') ?> por noche de media
                                </div>
                                <?php if (! empty($op['cotizacion']['suplementos'])): ?>
                                    <div class="small text-muted">incluye suplemento por personas adicionales</div>
                                <?php endif ?>
                                <a class="small" data-bs-toggle="collapse" href="#detalle<?= esc($op['tipo']['id']) ?>" role="button">
                                    Ver el detalle por noche
                                </a>
                            </div>
                            <form method="post" action="<?= site_url('reservar/datos') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="entrada" value="<?= esc($busqueda['entrada']) ?>">
                                <input type="hidden" name="salida" value="<?= esc($busqueda['salida']) ?>">
                                <input type="hidden" name="adultos" value="<?= esc($busqueda['adultos']) ?>">
                                <input type="hidden" name="ninos" value="<?= esc($busqueda['ninos']) ?>">
                                <input type="hidden" name="tipo_id" value="<?= esc($op['tipo']['id']) ?>">
                                <button class="btn btn-reserva">Elegir<i class="bi bi-arrow-right ms-1"></i></button>
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
                                    <span>Total</span>
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
            Precios en pesos colombianos, impuestos incluidos. El precio de cada noche varía según la temporada,
            el día de la semana y la ocupación; se congela en cuanto envías la reserva.
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
