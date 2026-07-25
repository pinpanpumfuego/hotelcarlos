<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $coloresEstado = ['solicitada' => 'warning', 'aprobada' => 'success', 'rechazada' => 'danger']; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Ausencias</h1>
        <p class="text-muted mb-0">Vacaciones, incapacidades y permisos del equipo.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('turnos') ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-week me-1"></i>Turnos</a>
        <a href="<?= site_url('personal') ?>" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i>Personal</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <!-- ═══ Pendientes ═══ -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pendientes de aprobar</span>
                <?php if (! empty($solicitadas)): ?>
                    <span class="badge text-bg-warning"><?= count($solicitadas) ?></span>
                <?php endif ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($solicitadas)): ?>
                    <div class="list-group-item text-center text-muted py-5">
                        <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success opacity-75"></i>
                        No hay solicitudes pendientes.
                    </div>
                <?php endif ?>
                <?php foreach ($solicitadas as $a): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <span class="fw-semibold"><?= esc($a['nombre'] . ' ' . $a['apellidos']) ?></span>
                                <span class="badge text-bg-<?= $colores[$a['tipo']] ?> ms-1"><?= esc($tipos[$a['tipo']]) ?></span>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?= date('d/m/Y', strtotime($a['desde'])) ?> → <?= date('d/m/Y', strtotime($a['hasta'])) ?>
                                    · <?= \App\Models\AusenciaModel::dias($a) ?> día<?= \App\Models\AusenciaModel::dias($a) > 1 ? 's' : '' ?>
                                    · <?= esc($a['cargo']) ?>
                                </div>
                                <?php if ($a['motivo']): ?>
                                    <div class="small mt-1"><?= esc($a['motivo']) ?></div>
                                <?php endif ?>
                            </div>
                            <div class="d-flex gap-1">
                                <form method="post" action="<?= site_url('ausencias/resolver/' . $a['id']) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="aprobar">
                                    <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Aprobar</button>
                                </form>
                                <form method="post" action="<?= site_url('ausencias/resolver/' . $a['id']) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="rechazar">
                                    <button class="btn btn-sm btn-outline-danger">Rechazar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <!-- ═══ Historial ═══ -->
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Historial</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Persona</th>
                            <th>Tipo</th>
                            <th class="d-none d-md-table-cell">Fechas</th>
                            <th>Días</th>
                            <th>Estado</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historial)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Sin ausencias registradas.</td></tr>
                        <?php endif ?>
                        <?php foreach ($historial as $a): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= esc($a['nombre'] . ' ' . $a['apellidos']) ?></span>
                                    <div class="small text-muted d-md-none">
                                        <?= date('d/m/y', strtotime($a['desde'])) ?>–<?= date('d/m/y', strtotime($a['hasta'])) ?>
                                    </div>
                                </td>
                                <td><span class="badge text-bg-<?= $colores[$a['tipo']] ?>"><?= esc($tipos[$a['tipo']]) ?></span></td>
                                <td class="d-none d-md-table-cell text-nowrap">
                                    <?= date('d/m/Y', strtotime($a['desde'])) ?> → <?= date('d/m/Y', strtotime($a['hasta'])) ?>
                                </td>
                                <td><?= \App\Models\AusenciaModel::dias($a) ?></td>
                                <td><span class="badge text-bg-<?= $coloresEstado[$a['estado']] ?>"><?= ucfirst($a['estado']) ?></span></td>
                                <td class="text-end">
                                    <form method="post" action="<?= site_url('ausencias/eliminar/' . $a['id']) ?>"
                                          onsubmit="return confirm('¿Eliminar este registro de ausencia?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══ Registrar ═══ -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Registrar ausencia</div>
            <div class="card-body">
                <?php if (empty($empleados)): ?>
                    <p class="text-muted mb-0">
                        No hay personal en plantilla.
                        <a href="<?= site_url('personal/nuevo') ?>">Registra al equipo primero</a>.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= site_url('ausencias/guardar') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label">Persona</label>
                        <select name="empleado_id" class="form-select mb-3" required>
                            <option value="">Elige…</option>
                            <?php foreach ($empleados as $e): ?>
                                <option value="<?= esc($e['id']) ?>"><?= esc($e['apellidos']) ?>, <?= esc($e['nombre']) ?> — <?= esc($e['cargo']) ?></option>
                            <?php endforeach ?>
                        </select>

                        <label class="form-label">Tipo</label>
                        <div class="row g-2 mb-3">
                            <?php foreach ($tipos as $valor => $etiqueta): ?>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="tipo" value="<?= $valor ?>"
                                           id="t<?= $valor ?>" <?= $valor === 'permiso' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-<?= $colores[$valor] ?> w-100 py-2 small" for="t<?= $valor ?>">
                                        <?= $etiqueta ?>
                                    </label>
                                </div>
                            <?php endforeach ?>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Desde</label>
                                <input type="date" name="desde" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Hasta</label>
                                <input type="date" name="hasta" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <label class="form-label">Motivo <span class="text-muted">(opcional)</span></label>
                        <input type="text" name="motivo" class="form-control mb-3" placeholder="Cita médica, asunto familiar…">

                        <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                        <p class="form-text mt-2 mb-0">
                            Queda pendiente de aprobación. Una vez aprobada, aparece marcada
                            en el cuadrante de turnos y no se podrá asignar trabajo esos días.
                        </p>
                    </form>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
