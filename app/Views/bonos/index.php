<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\BonoModel;

$emitidos = array_sum(array_map(static fn ($b) => (float) $b['importe_inicial'], $bonos));
$usados   = $emitidos - array_sum(array_map(static fn ($b) => (float) $b['saldo'], $bonos));
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Bonos regalo</h1>
        <p class="text-muted mb-0">
            Saldo prepagado: alguien paga hoy y lo canjea después, en alojamiento o en el restaurante.
        </p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBono">
        <i class="bi bi-plus-lg me-1"></i>Emitir bono
    </button>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Saldo pendiente de canjear</div>
            <div class="valor h4 mb-0 text-danger">$<?= number_format($saldoVivo, 0, ',', '.') ?></div>
            <div class="text-muted small">servicio que el hotel aún debe</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Emitido (en esta lista)</div>
            <div class="valor h4 mb-0">$<?= number_format($emitidos, 0, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Ya consumido</div>
            <div class="valor h4 mb-0 text-success">$<?= number_format(max(0, $usados), 0, ',', '.') ?></div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="buscar" class="form-control" value="<?= esc($filtros['buscar']) ?>"
                       placeholder="Código, comprador o beneficiario">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="con_saldo" <?= $filtros['estado'] === 'con_saldo' ? 'selected' : '' ?>>Con saldo</option>
                    <option value="agotados" <?= $filtros['estado'] === 'agotados' ? 'selected' : '' ?>>Agotados</option>
                    <option value="anulados" <?= $filtros['estado'] === 'anulados' ? 'selected' : '' ?>>Anulados</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="<?= site_url('bonos') ?>" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <?php if ($bonos === []): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-gift fs-3 d-block mb-2 opacity-50"></i>
            No hay bonos con esos criterios.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th class="d-none d-md-table-cell">Comprador</th>
                        <th class="text-end">Importe</th>
                        <th class="text-end">Saldo</th>
                        <th class="d-none d-lg-table-cell">Caduca</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bonos as $b): ?>
                        <?php [$color, $etiqueta] = BonoModel::estado($b); ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('bonos/ver/' . $b['id']) ?>" class="codigo-bono text-decoration-none">
                                    <?= esc($b['codigo']) ?>
                                </a>
                                <div class="text-muted small d-md-none"><?= esc($b['comprador_nombre']) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?= esc($b['comprador_nombre']) ?>
                                <?php if ($b['beneficiario'] !== null): ?>
                                    <div class="text-muted small">para <?= esc($b['beneficiario']) ?></div>
                                <?php endif ?>
                            </td>
                            <td class="text-end">$<?= number_format((float) $b['importe_inicial'], 0, ',', '.') ?></td>
                            <td class="text-end fw-semibold <?= (float) $b['saldo'] > 0 ? '' : 'text-muted' ?>">
                                $<?= number_format((float) $b['saldo'], 0, ',', '.') ?>
                            </td>
                            <td class="d-none d-lg-table-cell text-muted small">
                                <?= $b['caduca'] !== null ? date('d/m/Y', strtotime($b['caduca'])) : 'Sin caducidad' ?>
                            </td>
                            <td><span class="badge text-bg-<?= $color ?>"><?= $etiqueta ?></span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('bonos/ver/' . $b['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= site_url('bonos/imprimir/' . $b['id']) ?>" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-outline-secondary" title="Imprimir para entregar">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<div class="alert alert-light mt-4">
    <i class="bi bi-info-circle me-1"></i>
    Un bono vendido es <strong>dinero cobrado por adelantado</strong>: el saldo pendiente de canjear es
    servicio que el hotel todavía debe. Por eso al canjearlo aparece como <strong>forma de pago</strong>
    y no como descuento (para eso están los <a href="<?= site_url('cupones') ?>">cupones</a>).
</div>

<!-- ═══ Modal de emisión ═══ -->
<div class="modal fade" id="modalBono" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= site_url('bonos/guardar') ?>">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gift me-2"></i>Emitir bono regalo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Importe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="importe_inicial" class="form-control" min="1000" step="1000" required
                                       value="<?= esc(old('importe_inicial', 300000)) ?>">
                                <span class="input-group-text">COP</span>
                            </div>
                            <div class="d-flex gap-1 mt-2 flex-wrap">
                                <?php foreach ([200000, 300000, 500000, 1000000] as $sugerido): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-importe="<?= $sugerido ?>">
                                        $<?= number_format($sugerido, 0, ',', '.') ?>
                                    </button>
                                <?php endforeach ?>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Cómo lo paga</label>
                            <select name="forma_pago" class="form-select">
                                <?php foreach ($formasPago as $clave => $etiqueta): ?>
                                    <option value="<?= $clave ?>"><?= esc($etiqueta) ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text">Si es efectivo, entra en la caja del turno abierto.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Quién lo compra <span class="text-danger">*</span></label>
                            <input type="text" name="comprador_nombre" class="form-control" required maxlength="150"
                                   value="<?= esc(old('comprador_nombre', '')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Para quién es</label>
                            <input type="text" name="beneficiario" class="form-control" maxlength="150"
                                   value="<?= esc(old('beneficiario', '')) ?>" placeholder="Nombre del agasajado">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Correo del comprador</label>
                            <input type="email" name="comprador_email" class="form-control" maxlength="150"
                                   value="<?= esc(old('comprador_email', '')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" name="comprador_telefono" class="form-control" maxlength="30"
                                   value="<?= esc(old('comprador_telefono', '')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dedicatoria</label>
                        <input type="text" name="mensaje" class="form-control" maxlength="300"
                               placeholder="Feliz cumpleaños, disfruta del lago…">
                        <div class="form-text">Sale impresa en el bono.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Caduca el</label>
                            <input type="date" name="caduca" class="form-control"
                                   value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                            <div class="form-text">Vacío = sin caducidad.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Notas internas</label>
                            <input type="text" name="notas" class="form-control" maxlength="300">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Emitir bono</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .codigo-bono {
        font-family: 'Inter', monospace; font-weight: 700; letter-spacing: .06em;
        color: var(--bosque); background: var(--panel-tenue);
        border: 1px dashed var(--borde-fuerte); border-radius: var(--radio-sm);
        padding: .15rem .5rem; display: inline-block; white-space: nowrap;
    }
    .codigo-bono:hover { background: #fff; border-color: var(--bosque-claro); }
</style>

<script>
    document.querySelectorAll('[data-importe]').forEach(function (b) {
        b.addEventListener('click', function () {
            b.closest('form').querySelector('[name="importe_inicial"]').value = b.dataset.importe;
        });
    });
</script>

<?= $this->endSection() ?>
