<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'solicitudes';
$titulo  = lang('Portal.minibar');

$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-1"><?= esc(lang('Portal.minibar')) ?></h1>
        <p class="mb-0 opacity-75 small"><?= esc(lang('Portal.minibarTexto')) ?></p>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php if ($productos === []): ?>
    <div class="card mt-3">
        <div class="card-body text-center py-5">
            <i class="bi bi-cup-straw fs-2 d-block mb-2 text-muted opacity-50"></i>
            <p class="text-muted mb-0"><?= esc(lang('Portal.pedidoVacio')) ?></p>
        </div>
    </div>
<?php else: ?>
    <form method="post" action="<?= site_url('estancia/' . $token . '/minibar') ?>">
        <?= csrf_field() ?>

        <div class="card mt-3">
            <div class="card-body py-2">
                <?php foreach ($productos as $p): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                        <div class="flex-grow-1">
                            <div class="small fw-semibold"><?= esc($p['nombre']) ?></div>
                            <div class="small text-muted"><?= $pesos((float) $p['precio']) ?></div>
                        </div>
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

        <div class="card mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-semibold"><?= esc(lang('Portal.importe')) ?></span>
                    <span class="titulo fs-5" id="total">$0</span>
                </div>
                <div class="d-grid">
                    <button class="btn btn-bosque btn-lg" id="enviar" disabled>
                        <?= esc(lang('Portal.enviar')) ?>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
    // El importe también se calcula en el servidor con los precios de la base.
    // Esto es solo para que el huésped vea lo que lleva antes de confirmar.
    (function () {
        const total  = document.getElementById('total');
        const enviar = document.getElementById('enviar');
        const pesos  = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 });

        function recalcular() {
            let suma = 0;
            document.querySelectorAll('.cantidad').forEach(function (s) {
                suma += parseFloat(s.dataset.precio) * parseInt(s.value, 10);
            });
            total.textContent = '$' + pesos.format(suma);
            enviar.disabled = suma === 0;
        }

        document.querySelectorAll('.cantidad').forEach(function (s) {
            s.addEventListener('change', recalcular);
        });
        recalcular();
    })();
    </script>
<?php endif ?>

<?php if ($consumido !== []): ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold small"><?= esc(lang('Portal.yaApuntado')) ?></div>
        <div class="card-body py-2">
            <?php foreach ($consumido as $c): ?>
                <div class="d-flex justify-content-between py-2 border-bottom small">
                    <span><?= esc(str_replace('Minibar: ', '', $c['concepto'])) ?></span>
                    <span class="text-nowrap"><?= $pesos((float) $c['valor']) ?></span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url('estancia/' . $token . '/minibar/reponer') ?>" class="mt-3">
    <?= csrf_field() ?>
    <input type="text" name="detalle" class="form-control form-control-sm mb-2" maxlength="500"
           placeholder="<?= esc(lang('Portal.detallePista')) ?>">
    <div class="d-grid">
        <button class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i><?= esc(lang('Portal.minibarReponer')) ?>
        </button>
    </div>
</form>

<?= $this->endSection() ?>
