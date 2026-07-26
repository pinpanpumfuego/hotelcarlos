<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\BonoModel;
use App\Models\BonoMovimientoModel;

[$color, $etiqueta] = BonoModel::estado($bono);
$consumido = (float) $bono['importe_inicial'] - (float) $bono['saldo'];
$progreso  = (float) $bono['importe_inicial'] > 0 ? $consumido / (float) $bono['importe_inicial'] * 100 : 0;
?>

<div class="mb-4">
    <a href="<?= site_url('bonos') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Bonos regalo
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1">
                <span class="codigo-bono"><?= esc($bono['codigo']) ?></span>
                <span class="badge text-bg-<?= $color ?> align-middle ms-2"><?= $etiqueta ?></span>
            </h1>
            <p class="text-muted mb-0">
                Emitido el <?= date('d/m/Y', strtotime($bono['created_at'])) ?>
                por $<?= number_format((float) $bono['importe_inicial'], 0, ',', '.') ?>
                · pagado con <?= esc(BonoModel::FORMAS_PAGO[$bono['forma_pago']] ?? $bono['forma_pago']) ?>
            </p>
        </div>
        <a href="<?= site_url('bonos/imprimir/' . $bono['id']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
            <i class="bi bi-printer me-1"></i>Imprimir
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 text-center">
                <div class="text-muted small">Saldo disponible</div>
                <div class="saldo-grande">$<?= number_format((float) $bono['saldo'], 0, ',', '.') ?></div>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?= min(100, max(0, $progreso)) ?>%"></div>
                </div>
                <div class="text-muted small mt-2">
                    Consumido $<?= number_format($consumido, 0, ',', '.') ?>
                    de $<?= number_format((float) $bono['importe_inicial'], 0, ',', '.') ?>
                </div>
                <?php if ($bono['caduca'] !== null): ?>
                    <div class="small mt-2 <?= date('Y-m-d') > $bono['caduca'] ? 'text-danger' : 'text-muted' ?>">
                        <i class="bi bi-calendar-x me-1"></i>Caduca el <?= date('d/m/Y', strtotime($bono['caduca'])) ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <form method="post" action="<?= site_url('bonos/actualizar/' . $bono['id']) ?>">
            <?= csrf_field() ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2"></i>Datos del bono</div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comprador</label>
                        <input type="text" name="comprador_nombre" class="form-control" required maxlength="150"
                               value="<?= esc($bono['comprador_nombre']) ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Correo</label>
                            <input type="email" name="comprador_email" class="form-control" maxlength="150"
                                   value="<?= esc($bono['comprador_email'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" name="comprador_telefono" class="form-control" maxlength="30"
                                   value="<?= esc($bono['comprador_telefono'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Para quién es</label>
                        <input type="text" name="beneficiario" class="form-control" maxlength="150"
                               value="<?= esc($bono['beneficiario'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dedicatoria</label>
                        <input type="text" name="mensaje" class="form-control" maxlength="300"
                               value="<?= esc($bono['mensaje'] ?? '') ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Caduca el</label>
                            <input type="date" name="caduca" class="form-control" value="<?= esc($bono['caduca'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Notas internas</label>
                            <input type="text" name="notas" class="form-control" maxlength="300"
                                   value="<?= esc($bono['notas'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white p-3 d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    <?php if ($bono['estado'] !== 'anulado'): ?>
                        <button type="button" class="btn btn-outline-danger ms-auto"
                                data-bs-toggle="modal" data-bs-target="#modalAnular">
                            <i class="bi bi-x-octagon me-1"></i>Anular bono
                        </button>
                    <?php endif ?>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Historial del saldo</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Movimiento</th>
                            <th class="text-end">Importe</th>
                            <th class="text-end d-none d-md-table-cell">Saldo</th>
                            <th class="d-none d-lg-table-cell">Registró</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                                <td>
                                    <span class="fw-semibold"><?= esc(BonoMovimientoModel::TIPOS[$m['tipo']] ?? $m['tipo']) ?></span>
                                    <?php if ($m['reserva_codigo'] !== null): ?>
                                        · <a href="<?= site_url('reservas/ver/' . $m['reserva_id']) ?>"><?= esc($m['reserva_codigo']) ?></a>
                                    <?php elseif ($m['comanda_numero'] !== null): ?>
                                        · <?= esc($m['comanda_numero']) ?>
                                    <?php endif ?>
                                    <?php if (trim((string) $m['concepto']) !== ''): ?>
                                        <div class="text-muted small"><?= esc($m['concepto']) ?></div>
                                    <?php endif ?>
                                </td>
                                <td class="text-end fw-semibold <?= in_array($m['tipo'], ['emision', 'devolucion'], true) ? 'text-success' : '' ?>">
                                    <?= in_array($m['tipo'], ['emision', 'devolucion'], true) ? '+' : '−' ?>$<?= number_format((float) $m['valor'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end text-muted d-none d-md-table-cell">$<?= number_format((float) $m['saldo_despues'], 0, ',', '.') ?></td>
                                <td class="text-muted small d-none d-lg-table-cell"><?= esc($m['usuario_nombre'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-light mt-3 small">
            <i class="bi bi-info-circle me-1"></i>
            Para canjearlo: en la <strong>ficha de una reserva</strong> (recuadro «Bono regalo» del folio)
            o en el <strong>TPV</strong>, eligiendo «Bono regalo» al cobrar. Si sobra saldo, se guarda para otra vez.
        </div>
    </div>
</div>

<?php if ($bono['estado'] !== 'anulado'): ?>
    <div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="<?= site_url('bonos/anular/' . $bono['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Anular el bono <?= esc($bono['codigo']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted">
                            El bono dejará de poder canjearse y su saldo de
                            <strong>$<?= number_format((float) $bono['saldo'], 0, ',', '.') ?></strong> se pondrá a cero.
                            El historial se conserva.
                        </p>
                        <label class="form-label fw-semibold">Motivo <span class="text-danger">*</span></label>
                        <input type="text" name="motivo" class="form-control" required maxlength="200"
                               placeholder="Devolución del importe al comprador, error al emitirlo…">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-danger">Anular bono</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif ?>

<style>
    .codigo-bono {
        font-family: 'Inter', monospace; font-weight: 700; letter-spacing: .06em;
        color: var(--bosque); background: var(--panel-tenue);
        border: 1px dashed var(--borde-fuerte); border-radius: var(--radio-sm);
        padding: .1rem .5rem; display: inline-block;
    }
    .saldo-grande { font-family: 'Fraunces', serif; font-weight: 600; font-size: 2.4rem;
                    line-height: 1.1; color: var(--bosque); }
</style>

<?= $this->endSection() ?>
