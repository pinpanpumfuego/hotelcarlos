<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $colores = ['pendiente' => 'secondary', 'emitida' => 'success', 'error' => 'danger', 'anulada' => 'dark']; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <a href="<?= site_url('facturas') ?>" class="text-decoration-none small text-muted">
            <i class="bi bi-arrow-left me-1"></i>Facturas
        </a>
        <h1 class="h3 mb-0 mt-1">
            <?= esc($factura['numero'] ?: 'Factura #' . $factura['id']) ?>
            <span class="badge text-bg-<?= $colores[$factura['estado']] ?> fs-6 align-middle ms-2">
                <?= esc($estados[$factura['estado']]) ?>
            </span>
        </h1>
    </div>
    <?php if ($factura['url_publica']): ?>
        <a href="<?= esc($factura['url_publica']) ?>" target="_blank" rel="noopener" class="btn btn-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i>Ver factura en Siigo
        </a>
    <?php endif ?>
</div>

<?php if ($factura['estado'] === 'error'): ?>
    <div class="alert alert-danger d-flex gap-2">
        <i class="bi bi-x-octagon-fill mt-1"></i>
        <div>
            <strong>Siigo rechazó la factura.</strong>
            <div class="mt-1"><?= esc($factura['error']) ?></div>
            <div class="small mt-2">
                Corrige lo que indique el mensaje (normalmente el tercero, el código de producto
                o la resolución de facturación) y vuelve a emitirla desde su origen.
            </div>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Cliente</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:40%">Nombre</td><td><?= esc($factura['cliente_nombre']) ?></td></tr>
                    <tr><td class="text-muted">Documento</td><td><?= esc($factura['cliente_documento']) ?></td></tr>
                    <tr><td class="text-muted">Correo</td><td><?= esc($factura['cliente_email'] ?: '—') ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-file-earmark-check me-2"></i>Datos fiscales</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:40%">Número</td><td><?= esc($factura['numero'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Emitida</td><td><?= $factura['emitida_en'] ? date('d/m/Y H:i', strtotime($factura['emitida_en'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Identificador Siigo</td><td><code class="small"><?= esc($factura['siigo_id'] ?: '—') ?></code></td></tr>
                    <tr>
                        <td class="text-muted">CUFE</td>
                        <td>
                            <?php if ($factura['cufe']): ?>
                                <code class="small" style="word-break:break-all"><?= esc($factura['cufe']) ?></code>
                            <?php else: ?>—<?php endif ?>
                        </td>
                    </tr>
                </table>
                <?php if ($factura['cufe']): ?>
                    <p class="small text-muted mt-2 mb-0">
                        <i class="bi bi-shield-check me-1"></i>
                        El CUFE es el código único que identifica la factura ante la DIAN.
                    </p>
                <?php endif ?>
            </div>
        </div>

        <?php if ($factura['estado'] === 'emitida'): ?>
            <div class="card">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-envelope me-2"></i>Reenviar al cliente</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('facturas/reenviar/' . $factura['id']) ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="email" name="correo" class="form-control"
                               value="<?= esc($factura['cliente_email']) ?>" placeholder="correo@cliente.com">
                        <button class="btn btn-outline-primary text-nowrap">Enviar</button>
                    </form>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-2"></i>Detalle</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Concepto</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineas as $l): ?>
                            <tr>
                                <td><code class="small"><?= esc($l['codigo']) ?></code></td>
                                <td><?= esc($l['descripcion']) ?></td>
                                <td class="text-end"><?= (int) $l['cantidad'] ?></td>
                                <td class="text-end">$<?= number_format((float) $l['precio'], 0, ',', '.') ?></td>
                                <td class="text-end">$<?= number_format((float) $l['precio'] * (int) $l['cantidad'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="4">Total</td>
                            <td class="text-end">$<?= number_format((float) $factura['total'], 0, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if ($factura['respuesta']): ?>
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <button class="btn btn-sm btn-link text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#respuesta">
                        <i class="bi bi-code-slash me-1"></i>Respuesta técnica de Siigo
                    </button>
                </div>
                <div class="collapse" id="respuesta">
                    <div class="card-body">
                        <pre class="small mb-0" style="white-space:pre-wrap; word-break:break-all; max-height:300px; overflow:auto;"><?= esc($factura['respuesta']) ?></pre>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
