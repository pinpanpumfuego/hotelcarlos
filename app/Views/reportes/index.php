<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Reportes gerenciales</h1>
    <a href="<?= site_url('reportes/csv') ?>?desde=<?= esc($desde) ?>&hasta=<?= esc($hasta) ?>" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar a Excel (CSV)
    </a>
</div>

<!-- ── Filtro de periodo ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= site_url('reportes') ?>" class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= esc($desde) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= esc($hasta) ?>">
            </div>
            <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Aplicar</button>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                <a class="btn btn-sm btn-outline-secondary align-self-end" href="<?= site_url('reportes') ?>?desde=<?= date('Y-m-01') ?>&hasta=<?= date('Y-m-d') ?>">Este mes</a>
                <a class="btn btn-sm btn-outline-secondary align-self-end" href="<?= site_url('reportes') ?>?desde=<?= date('Y-m-01', strtotime('first day of last month')) ?>&hasta=<?= date('Y-m-t', strtotime('last day of last month')) ?>">Mes pasado</a>
                <a class="btn btn-sm btn-outline-secondary align-self-end" href="<?= site_url('reportes') ?>?desde=<?= date('Y-m-d', strtotime('-29 days')) ?>&hasta=<?= date('Y-m-d') ?>">Últimos 30 días</a>
                <a class="btn btn-sm btn-outline-secondary align-self-end" href="<?= site_url('reportes') ?>?desde=<?= date('Y-m-d') ?>&hasta=<?= date('Y-m-d', strtotime('+30 days')) ?>">Próximos 30 días</a>
            </div>
        </form>
    </div>
</div>

<!-- ── KPIs ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Ocupación del periodo</div>
                <div class="fs-3 fw-bold"><?= esc($ocupacionPct) ?>%</div>
                <div class="small text-muted"><?= esc($nochesVendidas) ?> de <?= esc($nochesPosibles) ?> noches posibles</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Cobrado (pagos)</div>
                <div class="fs-4 fw-bold">$<?= number_format($pagos, 0, ',', '.') ?></div>
                <div class="small text-muted">facturado en cargos: $<?= number_format($cargos, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Tarifa media (ADR)</div>
                <div class="fs-4 fw-bold">$<?= number_format($adr, 0, ',', '.') ?></div>
                <div class="small text-muted">cargos de alojamiento ÷ noches vendidas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">RevPAR</div>
                <div class="fs-4 fw-bold">$<?= number_format($revpar, 0, ',', '.') ?></div>
                <div class="small text-muted">ingreso alojamiento ÷ noches disponibles</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up me-2"></i>Ocupación por día (cabañas ocupadas de <?= esc($totalUnidades) ?>)</div>
            <div class="card-body"><canvas id="graficaOcupacion" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Pagos por método</div>
            <div class="card-body">
                <?php if (empty($pagosPorMetodo)): ?>
                    <p class="text-muted text-center py-4 mb-0">Sin pagos en el periodo.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <?php foreach ($pagosPorMetodo as $p): ?>
                            <tr>
                                <td><?= esc(\App\Models\FolioModel::METODOS[$p['metodo']] ?? $p['metodo'] ?? 'Sin método') ?></td>
                                <td class="text-end fw-semibold">$<?= number_format((float) $p['total'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </table>
                    <?php if (in_array('bono', array_column($pagosPorMetodo, 'metodo'), true)): ?>
                        <p class="form-text mt-2 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Lo cobrado con <strong>bono regalo</strong> no es dinero que entre hoy:
                            se cobró el día que se vendió el bono.
                        </p>
                    <?php endif ?>
                <?php endif ?>
                <hr>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Reservas creadas en el periodo: <strong><?= esc($creadas) ?></strong></span>
                    <span>Canceladas: <strong><?= esc($canceladas) ?></strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Restaurante: quién vendió qué ═══ -->
<?php if (! empty($porCamarero)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-people me-2"></i>Restaurante por camarero
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Camarero</th>
                        <th class="text-center">Comandas</th>
                        <th class="text-center d-none d-md-table-cell">Comensales</th>
                        <th class="text-end">Ventas</th>
                        <th class="text-end d-none d-lg-table-cell">Ticket medio</th>
                        <th class="text-end">Propinas</th>
                        <th class="text-center">Anuladas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($porCamarero as $c): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold"><?= esc($c['nombre']) ?> <?= esc($c['apellidos']) ?></span>
                                <?php if ($c['rol_tpv'] === 'encargado'): ?>
                                    <span class="badge text-bg-primary ms-1">Encargado</span>
                                <?php endif ?>
                            </td>
                            <td class="text-center"><?= (int) $c['comandas'] ?></td>
                            <td class="text-center d-none d-md-table-cell text-muted"><?= (int) $c['comensales'] ?></td>
                            <td class="text-end fw-semibold">$<?= number_format((float) $c['ventas'], 0, ',', '.') ?></td>
                            <td class="text-end d-none d-lg-table-cell text-muted">
                                $<?= number_format((float) $c['ticket_medio'], 0, ',', '.') ?>
                            </td>
                            <td class="text-end text-success">
                                <?= (float) $c['propinas'] > 0 ? '$' . number_format((float) $c['propinas'], 0, ',', '.') : '—' ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int) $c['anuladas'] > 0): ?>
                                    <span class="badge text-bg-warning"><?= (int) $c['anuladas'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            <p class="form-text mb-0">
                Se cuenta por quien <strong>abrió</strong> la comanda, que es quien atendió la mesa.
                Las anuladas conviene mirarlas: son ventas que desaparecieron.
            </p>
        </div>
    </div>
<?php endif ?>

<?php if (! empty($autorizaciones)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-shield-check me-2"></i>Anulaciones y descuentos autorizados
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Cuándo</th>
                        <th>Comanda</th>
                        <th>Camarero</th>
                        <th>Autorizó</th>
                        <th>Qué pasó</th>
                        <th class="text-end">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($autorizaciones as $a): ?>
                        <tr>
                            <td class="text-muted small"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
                            <td class="fw-semibold"><?= esc($a['numero']) ?></td>
                            <td><?= esc($a['camarero'] ?? '—') ?></td>
                            <td>
                                <span class="badge text-bg-primary"><?= esc($a['autorizo']) ?></span>
                            </td>
                            <td class="small">
                                <?php if ($a['estado'] === 'anulada'): ?>
                                    <span class="text-danger"><?= esc($a['notas'] ?? 'Anulada') ?></span>
                                <?php else: ?>
                                    <?= esc($a['motivo_descuento'] ?? 'Descuento') ?>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <?= $a['estado'] === 'anulada'
                                    ? '$' . number_format((float) $a['total'], 0, ',', '.')
                                    : '−$' . number_format((float) $a['descuento'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart me-2"></i>Ingresos cobrados por día (COP)</div>
    <div class="card-body"><canvas id="graficaIngresos" height="80"></canvas></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const dias      = <?= json_encode(array_map(fn ($d) => date('d/m', strtotime($d)), array_keys($ocupacionDia))) ?>;
    const ocupacion = <?= json_encode(array_values($ocupacionDia)) ?>;
    const ingresos  = <?= json_encode(array_values($ingresosDia)) ?>;

    new Chart(document.getElementById('graficaOcupacion'), {
        type: 'line',
        data: { labels: dias, datasets: [{
            label: 'Cabañas ocupadas', data: ocupacion, fill: true, tension: .3,
            borderColor: '#1f4d36', backgroundColor: 'rgba(31,77,54,.12)', pointRadius: 2,
        }]},
        options: {
            scales: { y: { beginAtZero: true, max: <?= max(1, $totalUnidades) ?>, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } },
        }
    });

    new Chart(document.getElementById('graficaIngresos'), {
        type: 'bar',
        data: { labels: dias, datasets: [{
            label: 'Cobrado', data: ingresos, backgroundColor: '#b98a4a', borderRadius: 4,
        }]},
        options: {
            scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString('es-CO') } } },
            plugins: { legend: { display: false } },
        }
    });
</script>

<?= $this->endSection() ?>
