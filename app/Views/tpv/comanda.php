<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $abierta = $comanda['estado'] === 'abierta'; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('tpv') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Restaurante</a>
        <h1 class="h3 mb-0 mt-1">
            Comanda <?= esc($comanda['numero']) ?>
            <?php if (! $abierta): ?>
                <span class="badge text-bg-secondary fs-6 align-middle ms-2"><?= esc(ucfirst($comanda['estado'])) ?></span>
            <?php endif ?>
        </h1>
        <div class="text-muted small mt-1">
            <?php if ($comanda['reserva_id'] !== null): ?>
                <i class="bi bi-house-heart me-1"></i><?= esc($comanda['unidad_nombre']) ?> ·
                <?= esc($comanda['h_nombre']) ?> <?= esc($comanda['h_apellidos']) ?>
                (<a href="<?= site_url('reservas/ver/' . $comanda['reserva_id']) ?>"><?= esc($comanda['reserva_codigo']) ?></a>)
            <?php else: ?>
                <i class="bi bi-shop me-1"></i><?= esc($comanda['mesa'] ?? 'Sin mesa') ?>
            <?php endif ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ═══ Carta ═══ -->
    <div class="col-lg-7">
        <?php if (! $hayCarta): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-journal-x fs-3 d-block mb-2 text-muted opacity-50"></i>
                    <p class="text-muted mb-3">La carta está vacía. Añade categorías y productos para poder vender.</p>
                    <?php if (puede('carta.gestionar')): ?>
                        <a href="<?= site_url('carta') ?>" class="btn btn-primary">Configurar la carta</a>
                    <?php endif ?>
                </div>
            </div>
        <?php elseif ($abierta): ?>
            <ul class="nav nav-pills mb-3 flex-wrap gap-1" role="tablist">
                <?php $i = 0; foreach (array_keys($porCategoria) as $categoria): ?>
                    <li class="nav-item">
                        <button class="nav-link <?= $i === 0 ? 'active' : '' ?>" data-bs-toggle="pill"
                                data-bs-target="#cat<?= $i ?>" type="button"><?= esc($categoria) ?></button>
                    </li>
                <?php $i++; endforeach ?>
            </ul>
            <div class="tab-content">
                <?php $i = 0; foreach ($porCategoria as $productos): ?>
                    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="cat<?= $i ?>">
                        <div class="row g-2">
                            <?php foreach ($productos as $p): ?>
                                <div class="col-6 col-md-4">
                                    <form method="post" action="<?= site_url('tpv/comanda/' . $comanda['id'] . '/anadir') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="producto_id" value="<?= esc($p['id']) ?>">
                                        <button class="btn btn-light border w-100 h-100 text-start p-3 shadow-sm">
                                            <span class="d-block fw-semibold small"><?= esc($p['nombre']) ?></span>
                                            <span class="d-block text-success fw-bold">$<?= number_format((float) $p['precio'], 0, ',', '.') ?></span>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php $i++; endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <!-- ═══ Cuenta ═══ -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2"></i>Cuenta</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <?php if (empty($lineas)): ?>
                            <tr><td class="text-center text-muted py-4">Sin productos todavía.</td></tr>
                        <?php endif ?>
                        <?php foreach ($lineas as $l): ?>
                            <tr>
                                <td>
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <div>
                                            <span class="fw-semibold"><?= esc($l['nombre_producto']) ?></span>
                                            <?php if ($l['entregado']): ?>
                                                <i class="bi bi-check2 text-success ms-1" title="Entregado"></i>
                                            <?php endif ?>
                                            <div class="small text-muted">
                                                <?= esc($l['cantidad']) ?> × $<?= number_format((float) $l['precio_unitario'], 0, ',', '.') ?>
                                            </div>
                                        </div>
                                        <div class="text-end text-nowrap">
                                            <span class="fw-semibold">$<?= number_format((float) $l['precio_unitario'] * $l['cantidad'], 0, ',', '.') ?></span>
                                            <?php if ($abierta): ?>
                                                <div class="btn-group btn-group-sm mt-1">
                                                    <form method="post" action="<?= site_url('tpv/linea/' . $l['id'] . '/cantidad') ?>" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="cantidad" value="<?= $l['cantidad'] - 1 ?>">
                                                        <button class="btn btn-outline-secondary btn-sm" title="Quitar uno"><i class="bi bi-dash"></i></button>
                                                    </form>
                                                    <form method="post" action="<?= site_url('tpv/linea/' . $l['id'] . '/cantidad') ?>" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="cantidad" value="<?= $l['cantidad'] + 1 ?>">
                                                        <button class="btn btn-outline-secondary btn-sm" title="Añadir uno"><i class="bi bi-plus"></i></button>
                                                    </form>
                                                </div>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fs-5">Total</span>
                    <span class="fs-3 fw-bold">$<?= number_format((float) $comanda['total'], 0, ',', '.') ?></span>
                </div>

                <?php if ($abierta): ?>
                    <form method="post" action="<?= site_url('tpv/comanda/' . $comanda['id'] . '/cobrar') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label">Forma de pago</label>
                        <select name="forma_pago" class="form-select mb-3">
                            <?php foreach ($formasPago as $valor => $etiqueta): ?>
                                <?php if ($valor === 'habitacion' && $comanda['reserva_id'] === null) continue; ?>
                                <option value="<?= $valor ?>"><?= $etiqueta ?></option>
                            <?php endforeach ?>
                        </select>
                        <?php // El bono y la tarjeta de saldo necesitan su código: sin él
                              // la comanda se cerraría sin descontarle nada a nadie. ?>
                        <div id="cobro-codigo" class="mb-3 d-none">
                            <label class="form-label" id="cobro-codigo-etiqueta">Código</label>
                            <input type="text" name="codigo" class="form-control text-uppercase"
                                   autocomplete="off" placeholder="BR-XXXX-XXXX">
                            <input type="password" name="pin" class="form-control mt-2 d-none" id="cobro-pin"
                                   inputmode="numeric" maxlength="6" autocomplete="off"
                                   placeholder="PIN del titular, si lo pide">
                        </div>
                        <button class="btn btn-success w-100 py-2"><i class="bi bi-cash-coin me-1"></i>Cobrar comanda</button>
                    </form>

                    <form method="post" action="<?= site_url('tpv/comanda/' . $comanda['id'] . '/anular') ?>" class="mt-3"
                          onsubmit="return confirm('¿Anular esta comanda? No se cobrará nada.');">
                        <?= csrf_field() ?>
                        <div class="input-group input-group-sm">
                            <input type="text" name="motivo" class="form-control" placeholder="Motivo de la anulación">
                            <button class="btn btn-outline-danger">Anular</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Comanda <?= esc($comanda['estado']) ?>
                        <?= $comanda['forma_pago'] ? ' · ' . esc($formasPago[$comanda['forma_pago']]) : '' ?>
                        <?= $comanda['cerrada_en'] ? ' el ' . date('d/m/Y H:i', strtotime($comanda['cerrada_en'])) : '' ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php if ($abierta && ! empty($lineas)): ?>
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white fw-semibold small"><i class="bi bi-fire me-2"></i>Marcar entregado</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($lineas as $l): ?>
                        <form method="post" action="<?= site_url('tpv/linea/' . $l['id'] . '/entregar') ?>"
                              class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <?= csrf_field() ?>
                            <span class="small <?= $l['entregado'] ? 'text-muted text-decoration-line-through' : '' ?>">
                                <?= esc($l['cantidad']) ?>× <?= esc($l['nombre_producto']) ?>
                            </span>
                            <button class="btn btn-sm <?= $l['entregado'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                <i class="bi <?= $l['entregado'] ? 'bi-arrow-counterclockwise' : 'bi-check-lg' ?>"></i>
                            </button>
                        </form>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<script>
(function () {
    const select = document.querySelector('select[name="forma_pago"]');
    const caja   = document.getElementById('cobro-codigo');
    if (!select || !caja) return;

    const campo = caja.querySelector('input[name="codigo"]');
    const pin   = document.getElementById('cobro-pin');
    const eti   = document.getElementById('cobro-codigo-etiqueta');

    function pintar() {
        const bono    = select.value === 'bono';
        const tarjeta = select.value === 'tarjeta_saldo';
        caja.classList.toggle('d-none', !bono && !tarjeta);
        pin.classList.toggle('d-none', !tarjeta);
        campo.required = bono || tarjeta;
        if (bono)    { eti.textContent = 'Código del bono';    campo.placeholder = 'BR-XXXX-XXXX'; }
        if (tarjeta) { eti.textContent = 'Código de la tarjeta'; campo.placeholder = 'TJ-XXXXXX'; }
    }

    select.addEventListener('change', pintar);
    pintar();
})();
</script>

<?= $this->endSection() ?>
