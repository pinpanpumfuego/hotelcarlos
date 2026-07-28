<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$c = $e['cuenta'];
$colorEstado = ['activa' => 'success', 'bloqueada' => 'danger', 'cerrada' => 'secondary'];
$colorMov = ['cargo' => 'danger', 'abono' => 'success', 'nota_credito' => 'success', 'ajuste' => 'warning'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="font-monospace text-muted small"><?= esc($c['codigo']) ?></div>
        <h1 class="h3 mb-1"><?= esc($c['nombre']) ?></h1>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge text-bg-<?= $colorEstado[$c['estado']] ?>"><?= esc($estados[$c['estado']]) ?></span>
            <span class="text-muted small">
                <?= esc($tipos[$c['tipo']]) ?>
                <?= $c['nit'] ? ' · NIT ' . esc($c['nit']) : '' ?>
                · paga a <?= (int) $c['plazo_dias'] ?> días
            </span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('cartera/estado/' . $c['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Estado de cuenta
        </a>
        <a href="<?= site_url('cartera') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if ($c['estado'] === 'bloqueada'): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-lock me-1"></i>Cuenta bloqueada.</strong>
        No admite cargos nuevos.
        <?php if ($c['motivo_bloqueo']): ?>
            <div class="mt-1"><?= esc($c['motivo_bloqueo']) ?></div>
        <?php endif ?>
    </div>
<?php endif ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Saldo</div>
                <div class="fs-3 fw-semibold <?= $e['saldo'] < 0 ? 'text-success' : '' ?>">
                    <?= $dinero(abs($e['saldo'])) ?>
                </div>
                <div class="small text-muted"><?= $e['saldo'] < 0 ? 'a su favor' : 'nos debe' ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Cupo libre</div>
                <div class="fs-3 fw-semibold">
                    <?= $e['disponible'] === null ? '∞' : $dinero((float) $e['disponible']) ?>
                </div>
                <div class="small text-muted">
                    <?= $e['disponible'] === null ? 'sin límite' : 'de ' . $dinero((float) $c['cupo']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold py-2">
                <i class="bi bi-hourglass-split me-2"></i>Antigüedad de la deuda
            </div>
            <div class="card-body py-2">
                <div class="row g-2 small text-center">
                    <?php
                    $tramos = [
                        'corriente' => ['Sin vencer', 'success'],
                        'd30'       => ['1-30 días', 'warning'],
                        'd60'       => ['31-60', 'warning'],
                        'd90'       => ['61-90', 'danger'],
                        'mas90'     => ['+90 días', 'danger'],
                    ];
                    ?>
                    <?php foreach ($tramos as $clave => [$etiqueta, $color]): ?>
                        <div class="col">
                            <div class="text-muted" style="font-size: .72rem;"><?= $etiqueta ?></div>
                            <div class="fw-semibold <?= $e['antiguedad'][$clave] > 0 ? 'text-' . $color : 'text-muted' ?>">
                                <?= $e['antiguedad'][$clave] > 0 ? $dinero((float) $e['antiguedad'][$clave]) : '—' ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ((float) $e['antiguedad']['mas90'] > 0): ?>
    <div class="alert alert-danger">
        <strong>Hay <?= $dinero((float) $e['antiguedad']['mas90']) ?> con más de 90 días.</strong>
        Conviene decidir si se sigue dando crédito a esta cuenta antes de que la deuda crezca más.
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($vencidos !== []): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-danger">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-calendar-x me-2 text-danger"></i>Cargos vencidos
                </div>
                <div class="card-body py-2">
                    <p class="small text-muted mb-0">
                        Para poder decir por teléfono «la de septiembre» en vez de «tienen un saldo».
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($vencidos as $v): ?>
                                <tr>
                                    <td>
                                        <?= esc($v['concepto']) ?>
                                        <?php if ($v['reserva_codigo']): ?>
                                            <span class="font-monospace text-muted small">· <?= esc($v['reserva_codigo']) ?></span>
                                        <?php endif ?>
                                    </td>
                                    <td class="small text-danger">
                                        venció el <?= date('d/m/Y', strtotime($v['vence_en'])) ?>
                                    </td>
                                    <td class="text-end"><?= $dinero((float) $v['valor']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-2"></i>Movimientos</div>
            <?php if ($e['movimientos'] === []): ?>
                <div class="card-body text-muted small">Sin movimientos todavía.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th class="d-none d-md-table-cell">Vence</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($e['movimientos'] as $m): ?>
                                <?php $suma = in_array($m['tipo'], ['cargo', 'ajuste'], true); ?>
                                <tr>
                                    <td class="small"><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $colorMov[$m['tipo']] ?>">
                                            <?= esc($tipos_mov[$m['tipo']]) ?>
                                        </span>
                                        <?= esc($m['concepto']) ?>
                                        <?php if ($m['referencia']): ?>
                                            <div class="small text-muted font-monospace"><?= esc($m['referencia']) ?></div>
                                        <?php endif ?>
                                    </td>
                                    <td class="small text-muted d-none d-md-table-cell">
                                        <?php if ($m['vence_en']): ?>
                                            <span class="<?= $m['vence_en'] < date('Y-m-d') ? 'text-danger' : '' ?>">
                                                <?= date('d/m/Y', strtotime($m['vence_en'])) ?>
                                            </span>
                                        <?php else: ?>—<?php endif ?>
                                    </td>
                                    <td class="text-end fw-semibold text-<?= $suma ? 'danger' : 'success' ?>">
                                        <?= ($suma ? '+' : '−') . $dinero((float) $m['valor']) ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if (puede('cartera.cobrar')): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Apuntar un abono</div>
                <div class="card-body">
                    <p class="small text-muted">
                        Un abono va contra <strong>la cuenta</strong>, no contra una factura concreta.
                        Un pago grande puede saldar varias reservas y dejar saldo a favor.
                    </p>
                    <form method="post" action="<?= site_url('cartera/abonar/' . $c['id']) ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="valor" class="form-control" min="1" step="any" required>
                                </div>
                            </div>
                            <div class="col-5">
                                <select name="medio" class="form-select form-select-sm">
                                    <?php foreach ($medios as $m): ?>
                                        <option value="<?= esc($m['clave'], 'attr') ?>"
                                                <?= $m['clave'] === 'transferencia' ? 'selected' : '' ?>>
                                            <?= esc($m['nombre']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <input type="text" name="referencia" class="form-control form-control-sm"
                                       maxlength="80" placeholder="Número de transferencia o comprobante">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-check-lg me-1"></i>Apuntar el abono
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif ?>

        <?php if (puede('cartera.gestionar')): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2"></i>La cuenta</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('cartera/guardar/' . $c['id']) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Nombre</label>
                            <input type="text" name="nombre" class="form-control form-control-sm" required
                                   maxlength="200" value="<?= esc($c['nombre']) ?>">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Tipo</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <?php foreach ($tipos as $t => $etiqueta): ?>
                                        <option value="<?= $t ?>" <?= $c['tipo'] === $t ? 'selected' : '' ?>>
                                            <?= esc($etiqueta) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">NIT</label>
                                <input type="text" name="nit" class="form-control form-control-sm font-monospace"
                                       maxlength="40" value="<?= esc($c['nit']) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Cupo</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="cupo" class="form-control" min="0" step="any"
                                           value="<?= (float) $c['cupo'] ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Plazo</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="plazo_dias" class="form-control" min="0" max="365"
                                           value="<?= (int) $c['plazo_dias'] ?>">
                                    <span class="input-group-text">días</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Descuento</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="descuento_pct" class="form-control" min="0" max="50"
                                           step="any" value="<?= (float) $c['descuento_pct'] ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Contacto</label>
                                <input type="text" name="contacto" class="form-control form-control-sm"
                                       maxlength="150" value="<?= esc($c['contacto']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Correo</label>
                                <input type="email" name="email" class="form-control form-control-sm"
                                       maxlength="150" value="<?= esc($c['email']) ?>">
                            </div>
                        </div>
                        <button class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-check-lg me-1"></i>Guardar
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold text-muted">
                    <i class="bi bi-exclamation-triangle me-2"></i>Bloquear y ajustar
                </div>
                <div class="card-body d-grid gap-3">
                    <form method="post" action="<?= site_url('cartera/estado/' . $c['id']) ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small text-muted mb-1">Estado de la cuenta</label>
                        <select name="estado" class="form-select form-select-sm mb-2">
                            <?php foreach ($estados as $s => $etiqueta): ?>
                                <option value="<?= $s ?>" <?= $c['estado'] === $s ? 'selected' : '' ?>>
                                    <?= esc($etiqueta) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <input type="text" name="motivo_bloqueo" class="form-control form-control-sm mb-2"
                               maxlength="200" placeholder="Motivo, si se bloquea"
                               value="<?= esc($c['motivo_bloqueo']) ?>">
                        <button class="btn btn-outline-secondary btn-sm w-100">Cambiar estado</button>
                    </form>

                    <form method="post" action="<?= site_url('cartera/ajustar/' . $c['id']) ?>" class="border-top pt-3">
                        <?= csrf_field() ?>
                        <label class="form-label small text-muted mb-1">Ajuste a mano</label>
                        <div class="row g-2">
                            <div class="col-5">
                                <select name="sentido" class="form-select form-select-sm">
                                    <option value="baja">Bajar deuda</option>
                                    <option value="sube">Subir deuda</option>
                                </select>
                            </div>
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="valor" class="form-control" min="1" step="any" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <input type="text" name="motivo" class="form-control form-control-sm" required
                                       maxlength="200" placeholder="Por qué (obligatorio)">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-warning btn-sm w-100">Apuntar ajuste</button>
                            </div>
                        </div>
                        <div class="form-text">
                            Sin motivo escrito, dentro de seis meses nadie sabrá qué fue esto.
                        </div>
                    </form>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
