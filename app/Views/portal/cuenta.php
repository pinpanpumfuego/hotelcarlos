<?= $this->extend('portal/layout') ?>

<?php
$seccion = 'cuenta';
$titulo  = lang('Portal.cuenta');

$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');

$cargos = 0.0;
foreach ($movimientos as $m) {
    if ($m['tipo'] === 'cargo') {
        $cargos += (float) $m['valor'];
    }
}
?>

<?= $this->section('cabecera') ?>
<header class="cabecera">
    <div class="container px-1" style="max-width: 640px;">
        <div class="marca"><?= esc($hotel->nombre) ?></div>
        <h1 class="h4 mt-2 mb-1"><?= esc(lang('Portal.cuentaTitulo')) ?></h1>
        <p class="mb-0 opacity-75 small"><?= esc($reserva['codigo']) ?></p>
    </div>
</header>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php if ($movimientos === []): ?>
    <div class="card mt-3">
        <div class="card-body text-center py-5">
            <i class="bi bi-receipt fs-2 d-block mb-2 text-muted opacity-50"></i>
            <p class="text-muted mb-0"><?= esc(lang('Portal.cuentaVacia')) ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="card mt-3">
        <div class="card-body py-2">
            <?php foreach ($movimientos as $m): ?>
                <?php
                $esPago      = $m['tipo'] === 'pago';
                $esDescuento = $m['tipo'] === 'descuento';
                ?>
                <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                    <div class="pe-2">
                        <div><?= esc($m['concepto']) ?></div>
                        <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></div>
                    </div>
                    <div class="text-end fw-semibold text-nowrap <?= $esPago || $esDescuento ? 'text-success' : '' ?>">
                        <?= $esPago || $esDescuento ? '−' : '' ?><?= $pesos((float) $m['valor']) ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted"><?= esc(lang('Portal.totalCargos')) ?></span>
                <span><?= $pesos($cargos) ?></span>
            </div>
            <?php if ($descuentos > 0): ?>
                <div class="d-flex justify-content-between py-1 text-success">
                    <span><?= esc(lang('Portal.descuentos')) ?></span>
                    <span>−<?= $pesos((float) $descuentos) ?></span>
                </div>
            <?php endif ?>
            <div class="d-flex justify-content-between py-1 text-success">
                <span><?= esc(lang('Portal.totalPagado')) ?></span>
                <span>−<?= $pesos((float) $pagado) ?></span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= esc(lang('Portal.saldo')) ?></span>
                <span class="titulo fs-4"><?= $pesos(max(0, (float) $saldo)) ?></span>
            </div>
            <?php if ((float) $saldo <= 0): ?>
                <p class="small text-success mb-0 mt-1">
                    <i class="bi bi-check-circle me-1"></i><?= esc(lang('Portal.saldoCero')) ?>
                </p>
            <?php endif ?>
        </div>
    </div>
<?php endif ?>

<div class="alert alert-light border small mt-3">
    <i class="bi bi-info-circle me-1"></i><?= esc(lang('Portal.cuentaAviso')) ?>
</div>

<?php // El «no cuadra» va a solicitudes, que es donde alguien lo va a leer. ?>
<div class="d-grid">
    <a class="btn btn-outline-secondary btn-sm"
       href="<?= site_url('estancia/' . $token . '/solicitudes?tipo=otro') ?>">
        <i class="bi bi-question-circle me-1"></i><?= esc(lang('Portal.algoNoCuadra')) ?>
    </a>
</div>

<?= $this->endSection() ?>
