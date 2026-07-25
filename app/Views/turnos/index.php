<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$nombresDia = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$meses      = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoy        = date('Y-m-d');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Turnos</h1>
        <p class="text-muted mb-0">
            Semana del <?= (int) date('j', strtotime($desde)) ?> al <?= (int) date('j', strtotime($hasta)) ?>
            de <?= $meses[(int) date('n', strtotime($hasta))] ?> de <?= date('Y', strtotime($hasta)) ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group">
            <a href="<?= site_url('turnos') ?>?semana=<?= esc($anterior) ?>" class="btn btn-outline-secondary" title="Semana anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a href="<?= site_url('turnos') ?>" class="btn btn-outline-secondary">Esta semana</a>
            <a href="<?= site_url('turnos') ?>?semana=<?= esc($siguiente) ?>" class="btn btn-outline-secondary" title="Semana siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <form method="post" action="<?= site_url('turnos/copiar') ?>"
              onsubmit="return confirm('¿Copiar todos los turnos de esta semana a la siguiente?');">
            <?= csrf_field() ?>
            <input type="hidden" name="semana" value="<?= esc($desde) ?>">
            <button class="btn btn-outline-primary"><i class="bi bi-copy me-1"></i>Copiar a la semana siguiente</button>
        </form>
        <a href="<?= site_url('ausencias') ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-x me-1"></i>Ausencias</a>
    </div>
</div>

<?php if (empty($empleados)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-people fs-1 d-block mb-3 opacity-50"></i>
            <h2 class="h5">No hay personal en plantilla</h2>
            <p class="mb-3">Registra primero al equipo para poder planificar sus turnos.</p>
            <a href="<?= site_url('personal/nuevo') ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Registrar empleado</a>
        </div>
    </div>
<?php else: ?>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0" style="min-width: 900px;">
            <thead>
                <tr>
                    <th class="position-sticky start-0" style="min-width:190px; z-index:2; background:var(--panel-tenue);">Persona</th>
                    <?php foreach ($dias as $i => $dia): ?>
                        <?php $esHoy = $dia->format('Y-m-d') === $hoy; ?>
                        <th class="text-center <?= $esHoy ? 'table-primary' : '' ?>" style="min-width:112px;">
                            <div class="text-muted small"><?= $nombresDia[$i] ?></div>
                            <div class="fw-bold"><?= $dia->format('j') ?></div>
                        </th>
                    <?php endforeach ?>
                    <th class="text-center d-none d-xl-table-cell" style="min-width:80px;">Horas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados as $e): ?>
                    <tr>
                        <td class="position-sticky start-0 bg-white" style="z-index:1;">
                            <a href="<?= site_url('personal/ver/' . $e['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= esc($e['nombre'] . ' ' . $e['apellidos']) ?>
                            </a>
                            <div class="small text-muted"><?= esc($e['cargo']) ?></div>
                        </td>

                        <?php foreach ($dias as $dia): ?>
                            <?php
                            $clave    = $dia->format('Y-m-d');
                            $delDia   = $porEmpleado[(int) $e['id']][$clave] ?? [];
                            $ausencia = $ausencias[(int) $e['id']][$clave] ?? null;
                            ?>
                            <td class="p-1 align-top <?= $clave === $hoy ? 'table-primary' : '' ?>">
                                <?php if ($ausencia !== null): ?>
                                    <div class="badge text-bg-<?= $coloresAusencia[$ausencia] ?> w-100 py-2">
                                        <?= esc($tiposAusencia[$ausencia]) ?>
                                    </div>
                                <?php endif ?>

                                <?php foreach ($delDia as $t): ?>
                                    <div class="d-flex align-items-center gap-1 mb-1 px-2 py-1 rounded-3"
                                         style="background:#e8f1eb; border:1px solid #cfe2d7;">
                                        <span class="small fw-semibold">
                                            <?= substr($t['hora_inicio'], 0, 5) ?>–<?= substr($t['hora_fin'], 0, 5) ?>
                                        </span>
                                        <form method="post" action="<?= site_url('turnos/eliminar/' . $t['id']) ?>" class="ms-auto">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-link text-danger p-0" style="line-height:1" title="Quitar turno">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <?php if ($t['puesto']): ?>
                                        <div class="small text-muted mb-1 px-1"><?= esc($t['puesto']) ?></div>
                                    <?php endif ?>
                                <?php endforeach ?>

                                <?php if ($ausencia === null): ?>
                                    <button class="btn btn-sm btn-outline-secondary w-100 py-1" data-bs-toggle="modal"
                                            data-bs-target="#modalTurno"
                                            data-empleado="<?= esc($e['id']) ?>"
                                            data-nombre="<?= esc($e['nombre'] . ' ' . $e['apellidos']) ?>"
                                            data-fecha="<?= $clave ?>"
                                            data-fecha-texto="<?= $dia->format('d/m/Y') ?>">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                <?php endif ?>
                            </td>
                        <?php endforeach ?>

                        <td class="text-center fw-semibold d-none d-xl-table-cell">
                            <?= isset($horas[(int) $e['id']]) ? $horas[(int) $e['id']] . ' h' : '—' ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted small d-xl-none">
    <i class="bi bi-arrow-left-right me-1"></i>Desliza la tabla para ver toda la semana.
</p>

<!-- ═══ Modal de asignación ═══ -->
<div class="modal fade" id="modalTurno" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= site_url('turnos/guardar') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="empleado_id" id="turnoEmpleado">
            <input type="hidden" name="fecha" id="turnoFecha">
            <input type="hidden" name="semana" value="<?= esc($desde) ?>">

            <div class="modal-header">
                <div>
                    <h2 class="h5 mb-0">Asignar turno</h2>
                    <div class="text-muted small" id="turnoResumen"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <label class="form-label">Turno habitual</label>
                <div class="row g-2 mb-3">
                    <?php foreach ($plantillas as $clave => $p): ?>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="plantilla" value="<?= $clave ?>" id="p<?= $clave ?>">
                            <label class="btn btn-outline-primary w-100 py-2" for="p<?= $clave ?>">
                                <strong><?= $p['etiqueta'] ?></strong>
                                <span class="d-block small opacity-75"><?= $p['inicio'] ?> – <?= $p['fin'] ?></span>
                            </label>
                        </div>
                    <?php endforeach ?>
                    <div class="col-12">
                        <input type="radio" class="btn-check" name="plantilla" value="" id="pLibre" checked>
                        <label class="btn btn-outline-secondary w-100 py-2" for="pLibre">Horario a medida</label>
                    </div>
                </div>

                <div class="row g-2 mb-3" id="horasLibres">
                    <div class="col-6">
                        <label class="form-label">Desde</label>
                        <input type="time" name="hora_inicio" class="form-control" value="08:00">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Hasta</label>
                        <input type="time" name="hora_fin" class="form-control" value="17:00">
                    </div>
                </div>

                <label class="form-label">Puesto o tarea <span class="text-muted">(opcional)</span></label>
                <input type="text" name="puesto" class="form-control mb-3" placeholder="Recepción, cocina, cabañas 1-4…">

                <label class="form-label">Notas <span class="text-muted">(opcional)</span></label>
                <input type="text" name="notas" class="form-control">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Asignar turno</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('modalTurno');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', (evento) => {
        const boton = evento.relatedTarget;
        if (!boton) return;
        document.getElementById('turnoEmpleado').value = boton.dataset.empleado;
        document.getElementById('turnoFecha').value = boton.dataset.fecha;
        document.getElementById('turnoResumen').textContent =
            boton.dataset.nombre + ' · ' + boton.dataset.fechaTexto;
        document.getElementById('pLibre').checked = true;
        document.getElementById('horasLibres').style.display = 'flex';
    });

    // Las horas a medida solo se piden si no se eligió un turno habitual
    modal.querySelectorAll('input[name=plantilla]').forEach((r) => {
        r.addEventListener('change', () => {
            document.getElementById('horasLibres').style.display = r.value === '' ? 'flex' : 'none';
        });
    });
}());
</script>

<?php endif ?>

<?= $this->endSection() ?>
