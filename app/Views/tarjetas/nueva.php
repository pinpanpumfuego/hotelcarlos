<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Nueva tarjeta</h1>
        <p class="text-muted mb-0">Se genera el código y el QR al guardarla.</p>
    </div>
    <a href="<?= site_url('tarjetas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($tipos === []): ?>
    <div class="alert alert-warning">
        No hay ninguna modalidad activa. Crea una primero en
        <a href="<?= site_url('tarjetas/tipos') ?>">Modalidades</a>.
    </div>
<?php else: ?>

<form method="post" action="<?= site_url('tarjetas/emitir') ?>">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-credit-card-2-front me-2"></i>La tarjeta
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Modalidad <span class="text-danger">*</span></label>
                        <select name="tipo_id" id="tipo_id" class="form-select" required>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t['id'] ?>"
                                        data-bonus="<?= (float) $t['bonus_pct'] ?>"
                                        data-descuento="<?= (float) $t['descuento_pct'] ?>"
                                        data-ambito="<?= esc($t['ambito']) ?>"
                                        data-acumula="<?= (int) $t['acumula'] ?>"
                                        data-recargable="<?= (int) $t['recargable'] ?>"
                                        data-caduca="<?= (int) $t['caduca_meses'] ?>"
                                        <?= old('tipo_id') == $t['id'] ? 'selected' : '' ?>>
                                    <?= esc($t['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text" id="resumen_tipo"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nombre en la tarjeta <span class="text-danger">*</span></label>
                        <input type="text" name="titular" class="form-control" maxlength="120"
                               value="<?= esc(old('titular')) ?>" placeholder="Como debe salir impreso">
                        <div class="form-text">Si eliges un huésped y lo dejas vacío, se pone el suyo.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Huésped</label>
                        <select name="huesped_id" class="form-select">
                            <option value="">— ninguno —</option>
                            <?php foreach ($huespedes as $h): ?>
                                <option value="<?= $h['id'] ?>" <?= old('huesped_id') == $h['id'] ? 'selected' : '' ?>>
                                    <?= esc(trim($h['apellidos'] . ', ' . $h['nombre'])) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text">Para que sus consumos y su historial queden juntos.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Empresa</label>
                        <select name="cuenta_id" class="form-select">
                            <option value="">— ninguna —</option>
                            <?php foreach ($cuentas as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= old('cuenta_id') == $c['id'] ? 'selected' : '' ?>>
                                    <?= esc($c['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text">Cuando la paga una empresa para sus empleados.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="2" maxlength="500"><?= esc(old('notas')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-cash-coin me-2"></i>Carga inicial
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Valor</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="carga_inicial" class="form-control" min="0" step="any"
                                   value="<?= esc(old('carga_inicial')) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">De dónde sale</label>
                        <select name="origen" class="form-select">
                            <?php foreach (['efectivo' => 'Efectivo', 'tarjeta' => 'Datáfono',
                                            'transferencia' => 'Transferencia', 'empresa' => 'La empresa',
                                            'cortesia' => 'De la casa'] as $o => $etiqueta): ?>
                                <option value="<?= $o ?>" <?= old('origen') === $o ? 'selected' : '' ?>>
                                    <?= $etiqueta ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="referencia" class="form-control" maxlength="80"
                               value="<?= esc(old('referencia')) ?>" placeholder="Recibo, comprobante…">
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Lo que se carga <strong>no es un ingreso todavía</strong>: es dinero que el hotel
                            debe hasta que se gaste. Sale así en los informes.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-shield-lock me-2"></i>Seguridad
                </div>
                <div class="card-body">
                    <label class="form-label">PIN</label>
                    <input type="text" name="pin" class="form-control" inputmode="numeric" maxlength="6"
                           autocomplete="off" placeholder="4 a 6 dígitos">
                    <div class="form-text">
                        Se pide al cobrar por encima del importe que marque la modalidad, y para que el
                        titular vea su saldo desde el QR. Se guarda cifrado: <strong>nadie podrá volver
                        a verlo</strong>, ni tú. Si se olvida, se pone uno nuevo.
                    </div>
                </div>
            </div>

            <?php if (puede('tarjetas.gestionar')): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-percent me-2"></i>Trato especial
                    </div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Descuento propio</label>
                            <div class="input-group">
                                <input type="number" name="descuento_pct" class="form-control" min="0" max="100"
                                       step="any" value="<?= esc(old('descuento_pct')) ?>" placeholder="—">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">
                                Vacío = el de su modalidad. Poner uno aquí es <strong>crear un precio
                                para esta persona</strong>: queda escrito en cada consumo cuánto se le
                                descontó.
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Recarga mensual automática</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="recarga_mensual" class="form-control" min="0" step="any"
                                       value="<?= esc(old('recarga_mensual')) ?>">
                            </div>
                            <div class="form-text">
                                Para tarjetas de personal o de empresa. No se recarga sola: aparece en la
                                lista y alguien pulsa «Recargarlas todas».
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif ?>

            <button class="btn btn-primary btn-lg w-100">
                <i class="bi bi-check-lg me-1"></i>Emitir tarjeta
            </button>
        </div>
    </div>
</form>

<script>
(function () {
    const select  = document.getElementById('tipo_id');
    const resumen = document.getElementById('resumen_tipo');
    if (!select || !resumen) return;

    const ambitos = {
        todo: 'vale en todo el hotel',
        alojamiento: 'solo alojamiento',
        restaurante: 'solo restaurante'
    };

    function pintar() {
        const o = select.selectedOptions[0];
        if (!o) return;
        const partes = [ambitos[o.dataset.ambito] || o.dataset.ambito];
        if (parseFloat(o.dataset.descuento) > 0) partes.push(o.dataset.descuento + ' % de descuento');
        if (parseFloat(o.dataset.bonus) > 0)     partes.push(o.dataset.bonus + ' % de regalo al recargar');
        if (o.dataset.recargable !== '1')        partes.push('no se puede recargar');
        if (o.dataset.acumula !== '1')           partes.push('cada carga sustituye al saldo anterior');
        if (parseInt(o.dataset.caduca, 10) > 0)  partes.push('caduca a los ' + o.dataset.caduca + ' meses');
        resumen.textContent = partes.join(' · ');
    }

    select.addEventListener('change', pintar);
    pintar();
})();
</script>

<?php endif ?>

<?= $this->endSection() ?>
