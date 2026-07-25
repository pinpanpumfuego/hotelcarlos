<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $colores = ['pendiente' => 'secondary', 'emitida' => 'success', 'error' => 'danger', 'anulada' => 'dark']; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Facturación electrónica</h1>
        <p class="text-muted mb-0">Facturas emitidas a través de Siigo.</p>
    </div>
    <a href="<?= site_url('administracion') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-gear me-1"></i>Configuración
    </a>
</div>

<?php if (! $listoParaFacturar['ok']): ?>
    <div class="alert alert-warning d-flex gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Aún no se puede facturar.</strong>
            Falta configurar <?= esc(implode(', ', $listoParaFacturar['faltan'])) ?>.
            <a href="<?= site_url('administracion') ?>" class="alert-link">Ir a Administración</a>.
        </div>
    </div>
<?php endif ?>

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="<?= site_url('facturas') ?>" class="btn btn-sm btn-<?= $filtro === '' ? 'primary' : 'outline-secondary' ?>">Todas</a>
            <?php foreach ($estados as $clave => $etiqueta): ?>
                <a href="<?= site_url('facturas') ?>?estado=<?= $clave ?>"
                   class="btn btn-sm btn-<?= $filtro === $clave ? 'primary' : 'outline-secondary' ?>"><?= $etiqueta ?></a>
            <?php endforeach ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th class="d-none d-md-table-cell">Origen</th>
                    <th class="d-none d-lg-table-cell">Fecha</th>
                    <th class="text-end">Total</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($facturas)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-receipt fs-3 d-block mb-2 opacity-50"></i>
                        Todavía no se ha emitido ninguna factura.
                        <div class="small mt-2">
                            Las facturas se generan desde la ficha de una reserva o desde una comanda del restaurante.
                        </div>
                    </td></tr>
                <?php endif ?>
                <?php foreach ($facturas as $f): ?>
                    <tr>
                        <td>
                            <a href="<?= site_url('facturas/ver/' . $f['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= esc($f['numero'] ?: '#' . $f['id']) ?>
                            </a>
                        </td>
                        <td>
                            <?= esc($f['cliente_nombre']) ?>
                            <div class="small text-muted"><?= esc($f['cliente_documento']) ?></div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if ($f['reserva_codigo']): ?>
                                <span class="badge text-bg-light border">Reserva <?= esc($f['reserva_codigo']) ?></span>
                            <?php elseif ($f['comanda_numero']): ?>
                                <span class="badge text-bg-light border">Comanda <?= esc($f['comanda_numero']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">Manual</span>
                            <?php endif ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-muted small">
                            <?= $f['emitida_en'] ? date('d/m/Y H:i', strtotime($f['emitida_en'])) : date('d/m/Y', strtotime($f['created_at'])) ?>
                        </td>
                        <td class="text-end">$<?= number_format((float) $f['total'], 0, ',', '.') ?></td>
                        <td>
                            <span class="badge text-bg-<?= $colores[$f['estado']] ?>"><?= esc($estados[$f['estado']]) ?></span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= site_url('facturas/ver/' . $f['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($f['url_publica']): ?>
                                <a href="<?= esc($f['url_publica']) ?>" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-primary" title="Ver en Siigo">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
