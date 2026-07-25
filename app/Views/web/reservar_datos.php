<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion" style="max-width: 820px;">
    <div class="text-center mb-4 reveal visible">
        <p class="etiqueta">Paso 3 de 3</p>
        <h1 class="titulo-seccion">Tus datos</h1>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card tarjeta-tipo">
                <div class="card-body p-4">
                    <form method="post" action="<?= site_url('reservar/confirmar') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entrada" value="<?= esc($busqueda['entrada']) ?>">
                        <input type="hidden" name="salida" value="<?= esc($busqueda['salida']) ?>">
                        <input type="hidden" name="adultos" value="<?= esc($busqueda['adultos']) ?>">
                        <input type="hidden" name="ninos" value="<?= esc($busqueda['ninos']) ?>">
                        <input type="hidden" name="tipo_id" value="<?= esc($tipo['id']) ?>">
                        <!-- Campo señuelo antispam: los humanos no lo ven -->
                        <input type="text" name="sitioweb" value="" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" class="form-control" required value="<?= esc(old('nombre', '')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Apellidos</label>
                                <input type="text" name="apellidos" class="form-control" required value="<?= esc(old('apellidos', '')) ?>">
                            </div>
                            <div class="col-5">
                                <label class="form-label">Tipo de documento</label>
                                <select name="tipo_documento" class="form-select">
                                    <option value="CC">Cédula</option>
                                    <option value="CE">Cédula de extranjería</option>
                                    <option value="PASAPORTE">Pasaporte</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>
                            <div class="col-7">
                                <label class="form-label">Número de documento</label>
                                <input type="text" name="num_documento" class="form-control" required value="<?= esc(old('num_documento', '')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Teléfono / WhatsApp</label>
                                <input type="tel" name="telefono" class="form-control" required placeholder="+57 300 000 0000" value="<?= esc(old('telefono', '')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Correo electrónico</label>
                                <input type="email" name="email" class="form-control" required value="<?= esc(old('email', '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Comentarios (opcional)</label>
                                <textarea name="comentarios" class="form-control" rows="2" placeholder="Hora estimada de llegada, alergias, ocasión especial..."><?= esc(old('comentarios', '')) ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="acepta" value="1" id="chkAcepta" required>
                                    <label class="form-check-label small" for="chkAcepta">
                                        Acepto el tratamiento de mis datos para gestionar la reserva y las políticas del ecolodge.
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-reserva btn-lg w-100 mt-4">
                            <i class="bi bi-check2-circle me-1"></i>Confirmar reserva
                        </button>
                        <p class="small text-muted text-center mt-2 mb-0">
                            No se te cobrará nada ahora: te contactaremos para coordinar el pago.
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card tarjeta-tipo" style="background: var(--bosque); color: #eef3ee;">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Resumen de tu reserva</h2>
                    <p class="mb-2"><i class="bi bi-house-heart me-2"></i><?= esc($tipo['nombre']) ?></p>
                    <p class="mb-2"><i class="bi bi-calendar3 me-2"></i><?= esc(date('d/m/Y', strtotime($busqueda['entrada']))) ?> → <?= esc(date('d/m/Y', strtotime($busqueda['salida']))) ?></p>
                    <p class="mb-2"><i class="bi bi-moon me-2"></i><?= esc($noches) ?> noche<?= $noches > 1 ? 's' : '' ?></p>
                    <p class="mb-3"><i class="bi bi-people me-2"></i><?= esc($busqueda['adultos']) ?> adulto<?= $busqueda['adultos'] > 1 ? 's' : '' ?><?= $busqueda['ninos'] > 0 ? ' y ' . esc($busqueda['ninos']) . ' niño' . ($busqueda['ninos'] > 1 ? 's' : '') : '' ?></p>
                    <hr class="border-light opacity-25">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Total</span>
                        <span class="fs-4 fw-bold">$<?= number_format($total, 0, ',', '.') ?> COP</span>
                    </div>
                    <div class="small opacity-75 text-end">$<?= number_format((float) $tipo['tarifa_base'], 0, ',', '.') ?> / noche</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
