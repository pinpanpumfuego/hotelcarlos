<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$colorEstado = ['activa' => 'success', 'bloqueada' => 'danger', 'cerrada' => 'secondary'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Cartera</h1>
        <p class="text-muted mb-0">
            Ordenada por lo vencido, no por el saldo: lo que corre no es quién debe más,
            es quién lleva más tiempo debiendo.
        </p>
    </div>
    <?php if (puede('cartera.gestionar')): ?>
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formCuenta">
            <i class="bi bi-plus-lg me-1"></i>Nueva cuenta
        </button>
    <?php endif ?>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Total por cobrar</div>
                <div class="fs-3 fw-semibold"><?= $dinero($deuda) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 <?= $vencido > 0 ? 'border-start border-4 border-danger' : '' ?>">
            <div class="card-body py-3">
                <div class="text-muted small">Ya vencido</div>
                <div class="fs-3 fw-semibold <?= $vencido > 0 ? 'text-danger' : '' ?>"><?= $dinero($vencido) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Cuentas</div>
                <div class="fs-3 fw-semibold"><?= count($cuentas) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (puede('cartera.gestionar')): ?>
    <div class="collapse mb-4" id="formCuenta">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= site_url('cartera/guardar') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="200">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <?php foreach ($tipos as $t => $etiqueta): ?>
                                <option value="<?= $t ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">NIT</label>
                        <input type="text" name="nit" class="form-control font-monospace" maxlength="40">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cupo</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="cupo" class="form-control" min="0" step="any" value="0">
                        </div>
                        <div class="form-text"><strong>En 0 no hay límite.</strong> Que sea una decisión, no un descuido.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Plazo de pago</label>
                        <div class="input-group">
                            <input type="number" name="plazo_dias" class="form-control" min="0" max="365" value="30">
                            <span class="input-group-text">días</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descuento pactado</label>
                        <div class="input-group">
                            <input type="number" name="descuento_pct" class="form-control" min="0" max="50" step="any" value="0">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Se enseña al cargar; no se aplica solo.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contacto</label>
                        <input type="text" name="contacto" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Correo</label>
                        <input type="email" name="email" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control" maxlength="30">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif ?>

<?php if ($cuentas === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-briefcase fs-1 d-block mb-3"></i>
            Ninguna cuenta todavía. Sirven para cuando una empresa manda gente y paga a fin de mes
            en vez de pagar cada estancia.
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Cuenta</th>
                        <th class="text-end">Debe</th>
                        <th class="text-end">Vencido</th>
                        <th class="text-end d-none d-md-table-cell">Cupo libre</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cuentas as $c): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('cartera/ver/' . $c['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($c['nombre']) ?>
                                </a>
                                <div class="small text-muted">
                                    <span class="font-monospace"><?= esc($c['codigo']) ?></span>
                                    · <?= esc($tipos[$c['tipo']]) ?>
                                    · paga a <?= (int) $c['plazo_dias'] ?> días
                                </div>
                            </td>
                            <td class="text-end fw-semibold"><?= $dinero((float) $c['saldo']) ?></td>
                            <td class="text-end">
                                <?php if ((float) $c['vencido'] > 0): ?>
                                    <span class="text-danger fw-semibold"><?= $dinero((float) $c['vencido']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end d-none d-md-table-cell">
                                <?php if ($c['disponible'] === null): ?>
                                    <span class="text-muted">sin límite</span>
                                <?php else: ?>
                                    <span class="<?= $c['disponible'] <= 0 ? 'text-danger' : '' ?>">
                                        <?= $dinero((float) $c['disponible']) ?>
                                    </span>
                                <?php endif ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= $colorEstado[$c['estado']] ?>">
                                    <?= esc($estados[$c['estado']]) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
