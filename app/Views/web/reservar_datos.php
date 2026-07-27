<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<?php
/** «Viernes 14 de agosto», sin depender de la configuración regional del servidor. */
if (! function_exists('fechaLarga')) {
    function fechaLarga(string $fecha): string
    {
        $dias  = ['Mon' => 'lunes', 'Tue' => 'martes', 'Wed' => 'miércoles', 'Thu' => 'jueves',
            'Fri' => 'viernes', 'Sat' => 'sábado', 'Sun' => 'domingo'];
        $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        $t = strtotime($fecha);

        return $dias[date('D', $t)] . ' ' . (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)];
    }
}
?>

<div class="container seccion" style="max-width: 820px;">
    <div class="text-center mb-4 reveal visible">
        <p class="etiqueta"><?= esc(lang('Web.paso3de3')) ?></p>
        <h1 class="titulo-seccion"><?= esc(lang('Web.tusDatos')) ?></h1>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card tarjeta-tipo">
                <div class="card-body p-4">
                    <form method="post" action="<?= url_web('reservar/confirmar') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entrada" value="<?= esc($busqueda['entrada']) ?>">
                        <input type="hidden" name="salida" value="<?= esc($busqueda['salida']) ?>">
                        <input type="hidden" name="adultos" value="<?= esc($busqueda['adultos']) ?>">
                        <input type="hidden" name="ninos" value="<?= esc($busqueda['ninos']) ?>">
                        <input type="hidden" name="tipo_id" value="<?= esc($tipo['id']) ?>">
                        <input type="hidden" name="cupon" value="<?= esc($cupon ?? '') ?>">
                        <?php // Campo de referido: solo aparece si el programa está
                              // en marcha. Enseñarlo apagado sería pedirle a alguien
                              // un código que no le va a servir de nada. ?>
                        <?php if (! empty($referidosActivos)): ?>
                            <div class="mb-3">
                                <label class="form-label small mb-1"><?= esc(lang('Web.teRecomendaron')) ?></label>
                                <input type="text" name="referido" class="form-control text-uppercase"
                                       maxlength="12" autocomplete="off"
                                       placeholder="<?= esc(lang('Web.codigoOpcional')) ?>">
                                <div class="form-text"><?= esc(lang('Web.referidoTexto')) ?></div>
                            </div>
                        <?php endif ?>
                        <?php if (! empty($experiencias)): ?>
                            <!-- ── Venta cruzada: se ofrece antes de pedir los datos ── -->
                            <div class="bloque-experiencias">
                                <p class="etiqueta mb-1"><?= esc(lang('Web.anadeEstancia')) ?></p>
                                <p class="text-muted small mb-3">
                                    Opcional. No se cobra ahora: confirmamos el cupo y lo pagas en el hotel.
                                </p>

                                <?php foreach ($experiencias as $x): ?>
                                    <?php
                                    $e  = $x['experiencia'];
                                    $id = (int) $e['id'];
                                    ?>
                                    <div class="extra" data-extra="<?= $id ?>">
                                        <label class="cabecera" for="exp<?= $id ?>">
                                            <input type="checkbox" name="experiencia[]" value="<?= $id ?>"
                                                   id="exp<?= $id ?>" class="marca-extra">

                                            <?php if ($x['foto'] !== null): ?>
                                                <img src="<?= esc(\App\Models\MedioModel::urlMiniatura($x['foto'])) ?>"
                                                     alt="<?= esc($e['nombre']) ?>" loading="lazy">
                                            <?php else: ?>
                                                <span class="icono">
                                                    <i class="bi <?= \App\Models\ExperienciaModel::CATEGORIAS[$e['categoria']] ?? 'bi-compass' ?>"></i>
                                                </span>
                                            <?php endif ?>

                                            <span class="texto">
                                                <span class="nombre"><?= esc($e['nombre']) ?></span>
                                                <span class="detalle">
                                                    <?= esc(\App\Models\ExperienciaModel::duracion((int) $e['duracion_min'])) ?>
                                                    <?php if ($e['edad_minima'] !== null): ?>
                                                        · desde <?= (int) $e['edad_minima'] ?> años
                                                    <?php endif ?>
                                                </span>
                                            </span>

                                            <span class="precio">
                                                $<?= number_format($x['total'], 0, ',', '.') ?>
                                                <span class="d-block">
                                                    <?= $e['tipo_precio'] === 'grupo' ? 'el grupo' : 'en total' ?>
                                                </span>
                                            </span>
                                        </label>

                                        <div class="cuando">
                                            <label class="form-label small mb-1" for="cuando<?= $id ?>"><?= esc(lang('Web.queDia')) ?></label>
                                            <select name="experiencia_fecha[<?= $id ?>]" class="form-select form-select-sm" id="cuando<?= $id ?>">
                                                <?php foreach ($x['fechas'] as $f): ?>
                                                    <option value="<?= esc($f['fecha'] . '|' . ($f['hora'] ?? '')) ?>"><?= ucfirst(fechaLarga($f['fecha'])) . ($f['hora'] !== null ? ' · ' . substr($f['hora'], 0, 5) : '') ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>

                        <!-- Campo señuelo antispam: los humanos no lo ven -->
                        <input type="text" name="sitioweb" value="" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label"><?= esc(lang('Web.nombre')) ?></label>
                                <input type="text" name="nombre" class="form-control" required value="<?= esc(old('nombre', '')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?= esc(lang('Web.apellidos')) ?></label>
                                <input type="text" name="apellidos" class="form-control" required value="<?= esc(old('apellidos', '')) ?>">
                            </div>
                            <div class="col-5">
                                <label class="form-label"><?= esc(lang('Web.tipoDocumento')) ?></label>
                                <select name="tipo_documento" class="form-select">
                                    <option value="CC"><?= esc(lang('Web.docCC')) ?></option>
                                    <option value="CE"><?= esc(lang('Web.docCE')) ?></option>
                                    <option value="PASAPORTE"><?= esc(lang('Web.docPasaporte')) ?></option>
                                    <option value="OTRO"><?= esc(lang('Web.docOtro')) ?></option>
                                </select>
                            </div>
                            <div class="col-7">
                                <label class="form-label"><?= esc(lang('Web.numDocumento')) ?></label>
                                <input type="text" name="num_documento" class="form-control" required value="<?= esc(old('num_documento', '')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?= esc(lang('Web.telefonoWsp')) ?></label>
                                <input type="tel" name="telefono" class="form-control" required placeholder="+57 300 000 0000" value="<?= esc(old('telefono', '')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?= esc(lang('Web.correo')) ?></label>
                                <input type="email" name="email" class="form-control" required value="<?= esc(old('email', '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= esc(lang('Web.comentariosOpc')) ?></label>
                                <textarea name="comentarios" class="form-control" rows="2" placeholder="<?= esc(lang('Web.comentariosPista')) ?>"><?= esc(old('comentarios', '')) ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="acepta" value="1" id="chkAcepta" required>
                                    <label class="form-check-label small" for="chkAcepta">
                                        <?= esc(lang('Web.aceptoDatos')) ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-reserva btn-lg w-100 mt-4">
                            <i class="bi bi-check2-circle me-1"></i><?= esc(lang('Web.confirmarReserva')) ?>
                        </button>
                        <p class="small text-muted text-center mt-2 mb-0">
                            <?= esc(lang('Web.noSeCobraAhora')) ?>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card tarjeta-tipo" style="background: var(--bosque); color: #eef3ee;">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3"><?= esc(lang('Web.resumenReserva')) ?></h2>
                    <p class="mb-2"><i class="bi bi-house-heart me-2"></i><?= esc($tipo['nombre']) ?></p>
                    <p class="mb-2"><i class="bi bi-calendar3 me-2"></i><?= esc(date('d/m/Y', strtotime($busqueda['entrada']))) ?> → <?= esc(date('d/m/Y', strtotime($busqueda['salida']))) ?></p>
                    <p class="mb-2"><i class="bi bi-moon me-2"></i><?= esc($noches) ?> noche<?= $noches > 1 ? 's' : '' ?></p>
                    <p class="mb-3"><i class="bi bi-people me-2"></i><?= esc($busqueda['adultos']) ?> adulto<?= $busqueda['adultos'] > 1 ? 's' : '' ?><?= $busqueda['ninos'] > 0 ? ' y ' . esc($busqueda['ninos']) . ' niño' . ($busqueda['ninos'] > 1 ? 's' : '') : '' ?></p>
                    <hr class="border-light opacity-25">

                    <?php if (isset($cotizacion)): ?>
                        <div class="small opacity-75 mb-2">
                            <?php foreach ($cotizacion['noches'] as $n): ?>
                                <div class="d-flex justify-content-between">
                                    <span><?= esc($n['dia']) ?> <?= date('d/m', strtotime($n['fecha'])) ?></span>
                                    <span>$<?= number_format($n['precio'], 0, ',', '.') ?></span>
                                </div>
                            <?php endforeach ?>
                            <?php foreach ($cotizacion['suplementos'] as $s): ?>
                                <div class="d-flex justify-content-between">
                                    <span><?= esc($s['concepto']) ?></span>
                                    <span>$<?= number_format($s['importe'], 0, ',', '.') ?></span>
                                </div>
                            <?php endforeach ?>
                        </div>
                        <hr class="border-light opacity-25">
                    <?php endif ?>

                    <?php $descuentoCupon = (float) ($descuento ?? 0); ?>
                    <?php if ($descuentoCupon > 0): ?>
                        <div class="d-flex justify-content-between small">
                            <span><?= esc(lang('Web.alojamiento')) ?></span>
                            <span>$<?= number_format($total, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span><i class="bi bi-ticket-perforated me-1"></i><?= esc(lang('Web.cupon')) ?> <?= esc($cupon ?? '') ?></span>
                            <span>−$<?= number_format($descuentoCupon, 0, ',', '.') ?></span>
                        </div>
                        <hr class="border-light opacity-25">
                    <?php endif ?>

                    <!-- Lo que se marca abajo aparece aquí al instante -->
                    <div id="resumen-extras" style="display:none">
                        <div class="small opacity-75 mb-2" id="lista-extras"></div>
                        <hr class="border-light opacity-25">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <span><?= esc(lang('Web.total')) ?></span>
                        <span class="fs-4 fw-bold" id="total-general"
                              data-base="<?= (int) ($total - $descuentoCupon) ?>">$<?= number_format($total - $descuentoCupon, 0, ',', '.') ?> COP</span>
                    </div>
                    <div class="small opacity-75 text-end" id="nota-extras" style="display:none">
                        incluye las experiencias, que se pagan en el hotel
                    </div>
                    <div class="small opacity-75 text-end">
                        $<?= number_format(isset($cotizacion) ? $cotizacion['media_noche'] : (float) $tipo['tarifa_base'], 0, ',', '.') ?> / noche de media
                    </div>
                </div>
            </div>

            <!-- Cupón: formulario aparte, para poder recalcular el resumen sin perder los datos -->
            <div class="card tarjeta-tipo mt-3">
                <div class="card-body p-3">
                    <?php if ($descuentoCupon > 0): ?>
                        <div class="d-flex align-items-center gap-2 text-success">
                            <i class="bi bi-check-circle-fill"></i>
                            <div class="small">
                                Cupón <strong><?= esc($cupon ?? '') ?></strong> aplicado.
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?= url_web('reservar/datos') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entrada" value="<?= esc($busqueda['entrada']) ?>">
                            <input type="hidden" name="salida" value="<?= esc($busqueda['salida']) ?>">
                            <input type="hidden" name="adultos" value="<?= esc($busqueda['adultos']) ?>">
                            <input type="hidden" name="ninos" value="<?= esc($busqueda['ninos']) ?>">
                            <input type="hidden" name="tipo_id" value="<?= esc($tipo['id']) ?>">
                            <label class="form-label small mb-1"><?= esc(lang('Web.tienesCupon')) ?></label>
                            <div class="d-flex gap-2">
                                <input type="text" name="cupon" class="form-control text-uppercase" placeholder="<?= esc(lang('Web.codigo')) ?>" autocomplete="off">
                                <button class="btn btn-outline-secondary rounded-pill px-3"><?= esc(lang('Web.aplicar')) ?></button>
                            </div>
                            <?php if (! empty($cuponError)): ?>
                                <div class="small text-danger mt-2"><i class="bi bi-x-circle me-1"></i><?= esc($cuponError) ?></div>
                            <?php endif ?>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bloque-experiencias {
        border: 1px solid rgba(0,0,0,.09); border-radius: 16px;
        padding: 1.1rem; margin: 1.4rem 0; background: #fbfdfb;
    }
    .extra { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff;
             margin-bottom: .6rem; overflow: hidden; transition: border-color .15s ease, box-shadow .15s ease; }
    .extra:last-child { margin-bottom: 0; }
    .extra.elegido { border-color: #2a5f44; box-shadow: 0 0 0 2px rgba(31,77,54,.1); }
    .extra .cabecera { display: flex; align-items: center; gap: .7rem; padding: .7rem .8rem;
                       cursor: pointer; margin: 0; }
    .extra .cabecera input { width: 20px; height: 20px; flex-shrink: 0; accent-color: #1f4d36; }
    .extra .cabecera img { width: 56px; height: 46px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
    .extra .icono { width: 56px; height: 46px; border-radius: 8px; background: #eef4ef; flex-shrink: 0;
                    display: flex; align-items: center; justify-content: center; color: #2a5f44; font-size: 1.3rem; }
    .extra .texto { flex: 1; min-width: 0; }
    .extra .nombre { display: block; font-weight: 600; font-size: .95rem; }
    .extra .detalle { display: block; font-size: .78rem; color: #7b8a81; }
    .extra .precio { text-align: right; font-weight: 700; color: #1f4d36; white-space: nowrap; font-size: .95rem; }
    .extra .precio span { font-size: .7rem; font-weight: 400; color: #7b8a81; }
    .extra .cuando { display: none; padding: 0 .8rem .8rem calc(20px + 56px + 1.4rem); }
    .extra.elegido .cuando { display: block; }
    @media (max-width: 480px) {
        .extra .cuando { padding-left: .8rem; }
    }
</style>

<script>
(function () {
    // El resumen de la derecha se actualiza al marcar: el huésped ve
    // lo que va a pagar antes de dar sus datos.
    const total = document.getElementById('total-general');
    if (!total) { return; }

    const base = parseInt(total.dataset.base, 10) || 0;
    const caja = document.getElementById('resumen-extras');
    const lista = document.getElementById('lista-extras');
    const nota = document.getElementById('nota-extras');

    function pesos(n) { return '$' + Math.round(n).toLocaleString('es-CO'); }

    function refrescar() {
        let suma = 0;
        const filas = [];

        document.querySelectorAll('.marca-extra').forEach(function (c) {
            const bloque = c.closest('.extra');
            bloque.classList.toggle('elegido', c.checked);
            if (!c.checked) { return; }

            const precio = parseInt(
                bloque.querySelector('.precio').textContent.replace(/[^\d]/g, ''), 10
            ) || 0;
            suma += precio;
            filas.push(
                '<div class="d-flex justify-content-between"><span>'
                + bloque.querySelector('.nombre').textContent
                + '</span><span>' + pesos(precio) + '</span></div>'
            );
        });

        lista.innerHTML = filas.join('');
        caja.style.display = filas.length ? 'block' : 'none';
        nota.style.display = filas.length ? 'block' : 'none';
        total.textContent = pesos(base + suma) + ' COP';
    }

    document.querySelectorAll('.marca-extra').forEach(function (c) {
        c.addEventListener('change', refrescar);
    });
    refrescar();
})();
</script>

<?= $this->endSection() ?>
