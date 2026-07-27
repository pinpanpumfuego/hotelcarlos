<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'solicitudes';
$titulo  = lang('Portal.carta');

$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-1"><?= esc(lang('Portal.carta')) ?></h1>
        <p class="mb-0 opacity-75 small"><?= esc(lang('Portal.seCargaAlFolio')) ?></p>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php if ($porCategoria === []): ?>
    <div class="card mt-3">
        <div class="card-body text-center py-5">
            <i class="bi bi-cup fs-2 d-block mb-2 text-muted opacity-50"></i>
            <p class="text-muted mb-0"><?= esc(lang('Portal.pedidoVacio')) ?></p>
        </div>
    </div>
<?php else: ?>
<form method="post" action="<?= site_url('estancia/' . $token . '/pedido') ?>" id="pedido">
    <?= csrf_field() ?>

    <?php foreach ($porCategoria as $categoria => $productos): ?>
        <div class="card mt-3">
            <div class="card-header bg-white fw-semibold small"><?= esc($categoria) ?></div>
            <div class="card-body py-2">
                <?php foreach ($productos as $p): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                        <div class="flex-grow-1">
                            <div class="small fw-semibold"><?= esc($p['nombre']) ?></div>
                            <div class="small text-muted"><?= $pesos((float) $p['precio']) ?></div>
                        </div>
                        <?php // Un selector en vez de un contador con botones: menos
                              // código, y en un móvil se toca igual de bien. ?>
                        <select name="cantidad[<?= (int) $p['id'] ?>]"
                                class="form-select form-select-sm cantidad"
                                data-precio="<?= (float) $p['precio'] ?>"
                                style="width: 4.4rem;">
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor ?>
                        </select>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endforeach ?>

    <div class="card mt-3">
        <div class="card-body">
            <label class="form-label fw-semibold small"><?= esc(lang('Portal.donde')) ?></label>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <input type="radio" class="btn-check" name="donde" value="cabana" id="d1" checked>
                    <label class="btn btn-outline-secondary w-100 small p-2" for="d1">
                        <i class="bi bi-house d-block fs-5 mb-1"></i><?= esc(lang('Portal.enLaCabana')) ?>
                    </label>
                </div>
                <div class="col-6">
                    <input type="radio" class="btn-check" name="donde" value="restaurante" id="d2">
                    <label class="btn btn-outline-secondary w-100 small p-2" for="d2">
                        <i class="bi bi-cup-hot d-block fs-5 mb-1"></i><?= esc(lang('Portal.enElRestaurante')) ?>
                    </label>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold"><?= esc(lang('Portal.tuPedido')) ?></span>
                <span class="titulo fs-5" id="total">$0</span>
            </div>

            <?php if ($tope > 0): ?>
                <p class="small text-muted" id="avisoTope" style="display:none;">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= esc(lang('Portal.pedidoTope')) ?>
                </p>
            <?php endif ?>

            <div class="d-grid">
                <button class="btn btn-bosque btn-lg" id="enviar"><?= esc(lang('Portal.enviarPedido')) ?></button>
            </div>
        </div>
    </div>
</form>

<script>
// El total se calcula también en el servidor, con los precios de la base de
// datos. Esto es solo para que el huésped vea lo que lleva antes de enviar.
(function () {
    const tope    = <?= (float) $tope ?>;
    const total   = document.getElementById('total');
    const aviso   = document.getElementById('avisoTope');
    const enviar  = document.getElementById('enviar');
    const pesos   = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 });

    function recalcular() {
        let suma = 0;
        document.querySelectorAll('.cantidad').forEach(function (s) {
            suma += parseFloat(s.dataset.precio) * parseInt(s.value, 10);
        });
        total.textContent = '$' + pesos.format(suma);

        const pasa = tope > 0 && suma > tope;
        if (aviso) { aviso.style.display = pasa ? 'block' : 'none'; }
        enviar.disabled = pasa || suma === 0;
    }

    document.querySelectorAll('.cantidad').forEach(function (s) {
        s.addEventListener('change', recalcular);
    });
    recalcular();
})();
</script>
<?php endif ?>

<?= $this->endSection() ?>
