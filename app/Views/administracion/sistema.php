<?= $this->extend("layouts/panel") ?>
<?= $this->section("contenido") ?>

<h1 class="h3 mb-1">Administración</h1>
<p class="text-muted">Lo que se mira cuando algo va mal: tareas del servidor y correos enviados.</p>

<?= view("administracion/_nav", ["activa" => "sistema"]) ?>

<?= view("partes/errores") ?>

<div class="row g-4">
    <div class="col-12">
    <div class="card border-0 shadow-sm mb-4" id="tareas">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold"><i class="bi bi-clock-history me-2 text-success"></i>Tareas automáticas del servidor</span>
            <?php if ($tareas['caducado']): ?>
                <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>Requiere atención</span>
            <?php else: ?>
                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Al día</span>
            <?php endif ?>
        </div>
        <div class="card-body">
    
            <!-- Tipo de cambio -->
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 pb-3 mb-3 border-bottom">
                <div>
                    <div class="fw-semibold">Tipo de cambio de las divisas</div>
                    <?php if ($tareas['ultimo'] === null): ?>
                        <div class="small text-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Nunca se ha traído. La web solo enseña pesos, sin conversión.
                        </div>
                    <?php else: ?>
                        <div class="small <?= $tareas['caducado'] ? 'text-danger' : 'text-muted' ?>">
                            Actualizado <?= esc($tareas['antiguedad']) ?>
                            <?= $tareas['caducado'] ? ' · demasiado viejo, la tarea puede no estar corriendo' : '' ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <?php foreach ($tareas['cambios'] as $c): ?>
                                <span class="me-3">
                                    1 <?= esc($c['moneda']) ?> =
                                    $<?= number_format((float) $c['pesos'], 0, ',', '.') ?>
                                    <?= $c['origen'] === 'manual' ? '<em>(a mano)</em>' : '' ?>
                                </span>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
                <form method="post" action="<?= site_url('administracion/cambio') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-repeat me-1"></i>Actualizar ahora
                    </button>
                </form>
            </div>
    
            <!-- Cómo programarlas -->
            <p class="small text-muted mb-2">
                Estas órdenes tienen que correr solas. Añádelas en el panel de tu hosting,
                en <strong>Tareas programadas</strong>. Son las rutas reales de este servidor:
            </p>
    
            <?php foreach ($tareas['ordenes'] as $o): ?>
                <div class="mb-2">
                    <div class="small">
                        <strong><?= esc($o['que']) ?></strong>
                        <span class="text-muted">· <?= esc($o['cuando']) ?></span>
                    </div>
                    <code class="d-block bg-light border rounded p-2 small mt-1"
                          style="word-break: break-all;"><?= esc($o['orden']) ?></code>
                </div>
            <?php endforeach ?>
    
            <div class="alert alert-light border small mb-0 mt-3">
                <i class="bi bi-info-circle me-1"></i>
                El botón <strong>Actualizar ahora</strong> sirve para dos cosas: salir de un apuro si
                la tarea falló, y <strong>comprobar que la orden funciona en este servidor</strong>
                antes de programarla.
            </div>
        </div>
    </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-send me-2 text-primary"></i>Correos enviados
                <span class="text-muted small fw-normal">— últimos 20</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Destinatario</th>
                            <th class="d-none d-lg-table-cell">Asunto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($correosLog)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                Todavía no se ha enviado ningún correo.
                            </td></tr>
                        <?php endif ?>
                        <?php foreach ($correosLog as $c): ?>
                            <tr>
                                <td class="text-muted small text-nowrap"><?= date('d/m H:i', strtotime($c['created_at'])) ?></td>
                                <td><?= esc($tiposCorreo[$c['tipo']] ?? $c['tipo']) ?></td>
                                <td class="small"><?= esc($c['destinatario']) ?></td>
                                <td class="small text-muted d-none d-lg-table-cell"><?= esc($c['asunto']) ?></td>
                                <td>
                                    <?php if ($c['estado'] === 'enviado'): ?>
                                        <span class="badge text-bg-success">Enviado</span>
                                    <?php elseif ($c['estado'] === 'sin_configurar'): ?>
                                        <span class="badge text-bg-warning" title="<?= esc($c['error'] ?? '') ?>">Sin configurar</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger" title="<?= esc(mb_substr($c['error'] ?? '', 0, 300)) ?>">Falló</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Pasa el ratón por encima de un envío fallido para ver el motivo.
                Los correos nunca interrumpen la operación del hotel: si fallan, quedan anotados aquí.
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
