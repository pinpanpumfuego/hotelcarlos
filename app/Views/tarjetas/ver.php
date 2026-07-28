<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$colorEstado = ['activa' => 'success', 'congelada' => 'warning', 'anulada' => 'secondary'];
$colorMov = ['carga' => 'success', 'bonus' => 'info', 'consumo' => 'danger',
             'devolucion' => 'success', 'ajuste' => 'warning', 'caducidad' => 'secondary'];
$descuento = $t['descuento_pct'] !== null ? (float) $t['descuento_pct'] : (float) $t['tipo_descuento'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="font-monospace text-muted small"><?= esc($t['codigo']) ?></div>
        <h1 class="h3 mb-1"><?= esc($t['titular']) ?></h1>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge text-bg-<?= esc($t['color']) ?>"><?= esc($t['tipo_nombre']) ?></span>
            <span class="badge text-bg-<?= $colorEstado[$t['estado']] ?>"><?= esc($estados[$t['estado']]) ?></span>
            <?php if ($descuento > 0): ?>
                <span class="badge text-bg-dark">
                    <?= rtrim(rtrim(number_format($descuento, 2, ',', '.'), '0'), ',') ?> % de descuento
                </span>
            <?php endif ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('tarjetas/imprimir/' . $t['id']) ?>" target="_blank" rel="noopener"
           class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimir
        </a>
        <a href="<?= site_url('tarjetas') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if ($t['estado'] !== 'activa'): ?>
    <div class="alert alert-<?= $t['estado'] === 'congelada' ? 'warning' : 'secondary' ?>">
        <strong><?= esc($estados[$t['estado']]) ?>.</strong>
        <?= esc($t['motivo_estado']) ?>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4 border-top border-4 border-<?= esc($t['color']) ?>">
            <div class="card-body text-center">
                <div class="text-muted small">Saldo</div>
                <div class="fs-1 fw-semibold"><?= $dinero((float) $t['saldo']) ?></div>

                <?php if ($t['caduca'] !== null): ?>
                    <div class="small text-muted mt-1">
                        Caduca el <?= date('d/m/Y', strtotime($t['caduca'])) ?>
                    </div>
                <?php endif ?>

                <hr>
                <img src="<?= site_url('tarjetas/qr/' . $t['id']) ?>" alt="QR de <?= esc($t['codigo']) ?>"
                     class="img-fluid" style="max-width: 160px;">
                <div class="font-monospace fw-semibold mt-2" style="letter-spacing: .1em;">
                    <?= esc($t['codigo']) ?>
                </div>
                <div class="small text-muted"><?= esc($enlace) ?></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Ficha</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Dónde vale</dt>
                    <dd class="col-7"><?= esc(ucfirst($t['ambito'])) ?></dd>

                    <dt class="col-5 text-muted fw-normal">PIN</dt>
                    <dd class="col-7">
                        <?php if ($t['pin_hash'] !== null): ?>
                            Puesto, desde <?= $dinero((float) $t['pin_desde']) ?>
                        <?php else: ?>
                            <span class="text-warning">Sin PIN</span>
                        <?php endif ?>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Regalo al recargar</dt>
                    <dd class="col-7">
                        <?= (float) $t['bonus_pct'] > 0
                            ? rtrim(rtrim(number_format((float) $t['bonus_pct'], 2, ',', '.'), '0'), ',') . ' %'
                            : '—' ?>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Recarga mensual</dt>
                    <dd class="col-7">
                        <?= (float) $t['recarga_mensual'] > 0 ? $dinero((float) $t['recarga_mensual']) : '—' ?>
                        <?php if ($t['ultima_recarga']): ?>
                            <div class="text-muted">última: <?= date('d/m/Y', strtotime($t['ultima_recarga'])) ?></div>
                        <?php endif ?>
                    </dd>

                    <?php if ($t['huesped_id']): ?>
                        <dt class="col-5 text-muted fw-normal">Huésped</dt>
                        <dd class="col-7">
                            <a href="<?= site_url('huespedes/ver/' . $t['huesped_id']) ?>">
                                <?= esc(trim(($t['huesped_nombre'] ?? '') . ' ' . ($t['huesped_apellidos'] ?? ''))) ?>
                            </a>
                        </dd>
                    <?php endif ?>
                    <?php if ($t['cuenta_id']): ?>
                        <dt class="col-5 text-muted fw-normal">Empresa</dt>
                        <dd class="col-7">
                            <a href="<?= site_url('cartera/ver/' . $t['cuenta_id']) ?>"><?= esc($t['cuenta_nombre']) ?></a>
                        </dd>
                    <?php endif ?>
                    <?php if ($t['notas']): ?>
                        <dt class="col-12 text-muted fw-normal mt-2">Notas</dt>
                        <dd class="col-12 mb-0"><?= esc($t['notas']) ?></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if (puede('tarjetas.cargar') && $t['estado'] === 'activa'): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Cargar saldo</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('tarjetas/cargar/' . $t['id']) ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="valor" class="form-control" min="1" step="any" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="origen" class="form-select">
                                <?php foreach (['efectivo' => 'Efectivo', 'tarjeta' => 'Datáfono',
                                                'transferencia' => 'Transferencia', 'empresa' => 'La empresa',
                                                'cortesia' => 'De la casa'] as $o => $etiqueta): ?>
                                    <option value="<?= $o ?>"><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="referencia" class="form-control" maxlength="80"
                                   placeholder="Referencia">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-success">
                                <i class="bi bi-plus-lg me-1"></i>Cargar
                            </button>
                            <?php if ((float) $t['bonus_pct'] > 0): ?>
                                <span class="form-text ms-2">
                                    Se le sumará un
                                    <?= rtrim(rtrim(number_format((float) $t['bonus_pct'], 2, ',', '.'), '0'), ',') ?> %
                                    de regalo.
                                </span>
                            <?php endif ?>
                        </div>
                    </form>

                    <div class="form-text mt-2">
                        El titular también puede recargarla él mismo escaneando el QR, si los pagos
                        en línea están activos.
                    </div>
                </div>
            </div>
        <?php endif ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-ul me-2"></i>Movimientos
                <span class="badge text-bg-secondary ms-1"><?= count($movimientos) ?></span>
            </div>
            <?php if ($movimientos === []): ?>
                <div class="card-body text-muted small">Sin movimientos todavía.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cuándo</th>
                                <th>Qué</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end d-none d-md-table-cell">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $m): ?>
                                <?php $resta = in_array($m['tipo'], ['consumo', 'caducidad'], true)
                                    || ($m['tipo'] === 'ajuste' && (float) $m['valor'] < 0); ?>
                                <tr>
                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $colorMov[$m['tipo']] ?>">
                                            <?= esc($tipos_mov[$m['tipo']]) ?>
                                        </span>
                                        <?= esc($m['concepto']) ?>
                                        <div class="small text-muted">
                                            <?php if ($m['origen']): ?><?= esc($origenes[$m['origen']] ?? $m['origen']) ?><?php endif ?>
                                            <?php if ($m['reserva_codigo']): ?> · <?= esc($m['reserva_codigo']) ?><?php endif ?>
                                            <?php if ($m['comanda_numero']): ?> · comanda <?= esc($m['comanda_numero']) ?><?php endif ?>
                                            <?php if ($m['usuario_nombre']): ?> · <?= esc($m['usuario_nombre']) ?><?php endif ?>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold text-<?= $resta ? 'danger' : 'success' ?>">
                                        <?= ($resta ? '−' : '+') . $dinero(abs((float) $m['valor'])) ?>
                                    </td>
                                    <td class="text-end text-muted d-none d-md-table-cell">
                                        <?= $dinero((float) $m['saldo_despues']) ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>

        <?php if (puede('tarjetas.gestionar')): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold text-muted">
                    <i class="bi bi-shield-lock me-2"></i>Seguridad y estado
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <form method="post" action="<?= site_url('tarjetas/estado/' . $t['id']) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label small text-muted mb-1">Estado</label>
                            <select name="estado" class="form-select form-select-sm mb-2">
                                <?php foreach ($estados as $e => $etiqueta): ?>
                                    <option value="<?= $e ?>" <?= $t['estado'] === $e ? 'selected' : '' ?>>
                                        <?= esc($etiqueta) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <input type="text" name="motivo" class="form-control form-control-sm mb-2"
                                   maxlength="200" placeholder="Motivo, si no queda activa">
                            <button class="btn btn-outline-secondary btn-sm w-100">Cambiar estado</button>
                            <div class="form-text">
                                Congelar deja el saldo intacto. <strong>Anular lo retira</strong>, y queda apuntado.
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?= site_url('tarjetas/pin/' . $t['id']) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label small text-muted mb-1">PIN nuevo</label>
                            <input type="text" name="pin" class="form-control form-control-sm mb-2"
                                   inputmode="numeric" maxlength="6" placeholder="4 a 6 dígitos"
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary btn-sm w-100">Cambiar PIN</button>
                            <div class="form-text">
                                En blanco lo quita, y entonces la tarjeta se puede gastar solo con el
                                código. Con saldo alto, no conviene.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
