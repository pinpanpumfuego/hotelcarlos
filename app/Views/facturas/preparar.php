<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="mb-4">
    <a href="<?= $origen === 'reserva' ? site_url('reservas/ver/' . $registro['id']) : site_url('tpv') ?>"
       class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
    <h1 class="h3 mb-0 mt-1">Emitir factura electrónica</h1>
    <p class="text-muted mb-0 mt-1">
        <?= $origen === 'reserva'
            ? 'Reserva ' . esc($registro['codigo']) . ' · ' . esc($registro['unidad_nombre'])
            : 'Comanda ' . esc($registro['numero']) ?>
    </p>
</div>

<?php if (! $listo['ok']): ?>
    <div class="alert alert-warning d-flex gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>No se puede emitir todavía.</strong>
            Falta configurar <?= esc(implode(', ', $listo['faltan'])) ?>.
            <a href="<?= site_url('administracion') ?>" class="alert-link">Ir a Administración</a>.
        </div>
    </div>
<?php endif ?>

<div class="alert alert-info d-flex gap-2">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div>
        <strong>Revisa antes de emitir.</strong>
        Una factura electrónica se reporta a la DIAN y <strong>no se puede editar</strong>:
        corregirla exige emitir una nota crédito desde Siigo. Comprueba el nombre, el documento
        del cliente y los importes.
    </div>
</div>

<form method="post" action="<?= site_url('facturas/emitir') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="origen" value="<?= esc($origen) ?>">
    <input type="hidden" name="referencia_id" value="<?= esc($registro['id']) ?>">

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Cliente</div>
                <div class="card-body">
                    <label class="form-label">Nombre o razón social <span class="text-danger">*</span></label>
                    <input type="text" name="cliente_nombre" class="form-control mb-3" required
                           value="<?= esc(old('cliente_nombre', $cliente['nombre'])) ?>">

                    <label class="form-label">Número de documento (NIT o cédula) <span class="text-danger">*</span></label>
                    <input type="text" name="cliente_documento" class="form-control mb-1" required
                           value="<?= esc(old('cliente_documento', $cliente['documento'])) ?>">
                    <div class="form-text mb-3">
                        Debe existir como tercero en Siigo, o Siigo lo rechazará.
                    </div>

                    <label class="form-label">Correo del cliente</label>
                    <input type="email" name="cliente_email" class="form-control mb-1"
                           value="<?= esc(old('cliente_email', $cliente['email'])) ?>">
                    <div class="form-text mb-3">Si lo pones, Siigo le enviará la factura.</div>

                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2"
                              placeholder="Aparecerán en la factura"><?= $origen === 'reserva'
                        ? 'Alojamiento del ' . date('d/m/Y', strtotime($registro['fecha_entrada'])) . ' al ' . date('d/m/Y', strtotime($registro['fecha_salida']))
                        : '' ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-2"></i>Líneas de la factura</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:110px">Código</th>
                                <th>Concepto</th>
                                <th style="width:80px">Cant.</th>
                                <th style="width:140px" class="text-end">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineas as $l): ?>
                                <tr>
                                    <td>
                                        <input type="text" name="linea_codigo[]" class="form-control form-control-sm"
                                               value="<?= esc($l['codigo']) ?>">
                                    </td>
                                    <td>
                                        <input type="text" name="linea_descripcion[]" class="form-control form-control-sm"
                                               value="<?= esc($l['descripcion']) ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="linea_cantidad[]" class="form-control form-control-sm"
                                               min="1" value="<?= esc($l['cantidad']) ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="linea_precio[]" class="form-control form-control-sm text-end"
                                               min="0" step="0.01" value="<?= esc($l['precio']) ?>">
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="3">Total a facturar</td>
                                <td class="text-end">$<?= number_format($total, 0, ',', '.') ?></td>
                            </tr>
                            <?php if (! empty($propina)): ?>
                                <tr class="text-muted">
                                    <td colspan="3" class="fw-normal">
                                        Propina voluntaria — <strong>no se factura</strong>
                                    </td>
                                    <td class="text-end fw-normal">$<?= number_format($propina, 0, ',', '.') ?></td>
                                </tr>
                            <?php endif ?>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    Los códigos deben existir como productos en tu catálogo de Siigo.
                    Se configuran en <a href="<?= site_url('administracion') ?>">Administración</a>.
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary btn-lg" <?= $listo['ok'] ? '' : 'disabled' ?>
                onclick="return confirm('¿Emitir la factura electrónica? Se reportará a la DIAN y no podrá editarse.');">
            <i class="bi bi-send-check me-1"></i>Emitir factura
        </button>
        <a href="<?= $origen === 'reserva' ? site_url('reservas/ver/' . $registro['id']) : site_url('tpv') ?>"
           class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>
