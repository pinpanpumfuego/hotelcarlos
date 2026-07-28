<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * Un formulario por modalidad, en tarjetas y no en una tabla: un <form> metido
 * entre <tr> y <td> es HTML inválido y el navegador lo saca de ahí, con lo que
 * el formulario deja de enviar la mitad de los campos. Ya pasó una vez.
 */
$campos = static function (array $t, array $ambitos, string $sufijo): void {
    ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="nombre" class="form-control" maxlength="60" required
                   value="<?= esc($t['nombre'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Dónde vale</label>
            <select name="ambito" class="form-select">
                <?php foreach ($ambitos as $a => $etiqueta): ?>
                    <option value="<?= $a ?>" <?= ($t['ambito'] ?? 'todo') === $a ? 'selected' : '' ?>>
                        <?= esc($etiqueta) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Descuento</label>
            <div class="input-group">
                <input type="number" name="descuento_pct" class="form-control" min="0" max="100" step="any"
                       value="<?= esc($t['descuento_pct'] ?? 0) ?>">
                <span class="input-group-text">%</span>
            </div>
            <div class="form-text">Sobre lo que consuma.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Regalo al recargar</label>
            <div class="input-group">
                <input type="number" name="bonus_pct" class="form-control" min="0" max="100" step="any"
                       value="<?= esc($t['bonus_pct'] ?? 0) ?>">
                <span class="input-group-text">%</span>
            </div>
            <div class="form-text">Carga 100 y le entran 110.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Pedir PIN desde</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="pin_desde" class="form-control" min="0" step="any"
                       value="<?= esc($t['pin_desde'] ?? 0) ?>">
            </div>
            <div class="form-text">0 = siempre.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Caduca a los</label>
            <div class="input-group">
                <input type="number" name="caduca_meses" class="form-control" min="0" max="120"
                       value="<?= esc($t['caduca_meses'] ?? '') ?>">
                <span class="input-group-text">meses</span>
            </div>
            <div class="form-text">Vacío = no caduca.</div>
        </div>

        <div class="col-12 d-flex gap-4 flex-wrap">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="recargable"
                       id="recargable<?= $sufijo ?>" <?= ($t['recargable'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="recargable<?= $sufijo ?>">
                    Se puede recargar
                    <span class="form-text d-block">Una tarjeta regalo normalmente no.</span>
                </label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="acumula"
                       id="acumula<?= $sufijo ?>" <?= ($t['acumula'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="acumula<?= $sufijo ?>">
                    El saldo se acumula
                    <span class="form-text d-block">Sin esto, cada carga sustituye a la anterior.</span>
                </label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="activo"
                       id="activo<?= $sufijo ?>" <?= ($t['activo'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo<?= $sufijo ?>">
                    Se puede emitir
                    <span class="form-text d-block">Apagarla no toca las tarjetas ya emitidas.</span>
                </label>
            </div>
        </div>
    </div>
    <?php
};
?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Modalidades de tarjeta</h1>
        <p class="text-muted mb-0">Regalo, fidelización, personal… todas son la misma tarjeta con otra configuración.</p>
    </div>
    <a href="<?= site_url('tarjetas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<div class="alert alert-light border small">
    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
    Cambiar el descuento de una modalidad <strong>cambia el precio de todas las tarjetas
    que la usan</strong>, también de las ya entregadas. Si es para una sola persona, ponle
    un descuento propio en su ficha.
</div>

<div class="row g-4">
    <?php foreach ($tipos as $t): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm <?= $t['activo'] ? '' : 'opacity-75' ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <span class="badge text-bg-<?= esc($t['color']) ?> me-1">&nbsp;</span>
                        <?= esc($t['nombre']) ?>
                    </span>
                    <?php if (! $t['activo']): ?>
                        <span class="badge text-bg-secondary">No se emite</span>
                    <?php endif ?>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('tarjetas/tipos/guardar/' . $t['id']) ?>">
                        <?= csrf_field() ?>
                        <?php $campos($t, $ambitos, (string) $t['id']) ?>
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <div class="col-12">
        <div class="card border-0 shadow-sm border-start border-4 border-primary">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-plus-lg me-2"></i>Nueva modalidad
            </div>
            <div class="card-body">
                <form method="post" action="<?= site_url('tarjetas/tipos/guardar') ?>">
                    <?= csrf_field() ?>
                    <?php $campos([], $ambitos, 'Nueva') ?>
                    <div class="mt-3">
                        <button class="btn btn-primary btn-sm">Crear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
