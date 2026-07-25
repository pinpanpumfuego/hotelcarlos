<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4">Panel de control</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Unidades totales</div>
                <div class="fs-2 fw-semibold"><?= esc($totalUnidades) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Disponibles</div>
                <div class="fs-2 fw-semibold text-success"><?= esc($disponibles) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Ocupadas</div>
                <div class="fs-2 fw-semibold text-danger"><?= esc($ocupadas) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">En limpieza / bloqueadas</div>
                <div class="fs-2 fw-semibold text-warning"><?= esc($noVendibles) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Estado de las unidades</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Unidad</th>
                    <th>Tipo</th>
                    <th>Capacidad</th>
                    <th>Tarifa base</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unidades as $u): ?>
                    <tr>
                        <td><?= esc($u['nombre']) ?></td>
                        <td><?= esc($u['tipo_nombre']) ?></td>
                        <td><?= esc($u['capacidad']) ?> pers.</td>
                        <td>$<?= number_format((float) $u['tarifa_base'], 0, ',', '.') ?> COP</td>
                        <td>
                            <?php $colores = ['disponible' => 'success', 'ocupada' => 'danger', 'limpieza' => 'warning', 'bloqueada' => 'secondary']; ?>
                            <span class="badge text-bg-<?= $colores[$u['estado']] ?? 'secondary' ?>"><?= esc(ucfirst($u['estado'])) ?></span>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
