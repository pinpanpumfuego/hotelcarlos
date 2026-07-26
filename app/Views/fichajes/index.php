<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\FichajeModel;

$origenes = ['terminal' => ['Terminal', 'bi-display'], 'movil' => ['Móvil', 'bi-phone'], 'manual' => ['A mano', 'bi-pencil']];
$radio    = \App\Libraries\Fichaje::radioPermitido();
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Control de jornada</h1>
        <p class="text-muted mb-0">Quién está trabajando, cuántas horas lleva cada uno y qué marcas hay.</p>
    </div>
    <a href="<?= site_url('fichar') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
        <i class="bi bi-display me-1"></i>Abrir el terminal
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($sinPin !== []): ?>
    <div class="alert alert-warning">
        <i class="bi bi-key me-1"></i>
        <strong><?= count($sinPin) ?> persona<?= count($sinPin) > 1 ? 's' : '' ?> sin PIN</strong>, así que no
        puede<?= count($sinPin) > 1 ? 'n' : '' ?> fichar:
        <?php foreach ($sinPin as $i => $e): ?>
            <a href="<?= site_url('personal/ver/' . $e['id']) ?>"><?= esc($e['nombre'] . ' ' . $e['apellidos']) ?></a><?= $i < count($sinPin) - 1 ? ', ' : '' ?>
        <?php endforeach ?>.
    </div>
<?php endif ?>

<!-- ── Quién está ahora ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-people me-2"></i>Ahora mismo en el hotel
    </div>
    <div class="card-body">
        <?php if ($presentes === []): ?>
            <p class="text-muted small mb-0 text-center py-2">No hay nadie fichado en este momento.</p>
        <?php else: ?>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($presentes as $p): ?>
                    <a href="<?= site_url('fichajes/empleado/' . $p['empleado']['id']) ?>"
                       class="chip-presente <?= $p['estado'] === 'pausa' ? 'pausa' : '' ?>">
                        <i class="bi <?= $p['estado'] === 'pausa' ? 'bi-pause-circle-fill' : 'bi-check-circle-fill' ?>"></i>
                        <span>
                            <strong><?= esc($p['empleado']['nombre']) ?> <?= esc($p['empleado']['apellidos']) ?></strong>
                            <span class="d-block small opacity-75">
                                <?= $p['estado'] === 'pausa' ? 'En pausa' : 'Desde' ?>
                                <?= $p['desde'] !== null ? date('H:i', strtotime($p['desde'])) : '' ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>

<!-- ── Periodo ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label mb-1">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= esc($desde) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= esc($hasta) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Empleado</label>
                <select name="empleado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($empleados as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= (int) $e['id'] === (int) $empleadoId ? 'selected' : '' ?>>
                            <?= esc($e['apellidos']) ?>, <?= esc($e['nombre']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Ver</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Horas por persona ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Horas del periodo</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th class="d-none d-md-table-cell">Cargo</th>
                    <th class="text-center">Días</th>
                    <th class="text-center d-none d-lg-table-cell">Pausas</th>
                    <th class="text-end">Horas</th>
                    <th>Ahora</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumen as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($r['empleado']['nombre']) ?> <?= esc($r['empleado']['apellidos']) ?></td>
                        <td class="text-muted small d-none d-md-table-cell"><?= esc($r['empleado']['cargo']) ?></td>
                        <td class="text-center"><?= $r['dias'] ?></td>
                        <td class="text-center text-muted small d-none d-lg-table-cell">
                            <?= $r['pausas'] > 0 ? $r['pausas'] . ' min' : '—' ?>
                        </td>
                        <td class="text-end fw-semibold">
                            <?= esc(FichajeModel::horas($r['minutos'])) ?>
                            <?php if ($r['abiertas'] > 0): ?>
                                <div><span class="badge text-bg-warning"><?= $r['abiertas'] ?> sin cerrar</span></div>
                            <?php endif ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= ['dentro' => 'success', 'pausa' => 'warning', 'fuera' => 'light'][$r['estado']] ?> <?= $r['estado'] === 'fuera' ? 'text-muted' : '' ?>">
                                <?= ['dentro' => 'Dentro', 'pausa' => 'En pausa', 'fuera' => 'Fuera'][$r['estado']] ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= site_url('fichajes/empleado/' . $r['empleado']['id'] . '?desde=' . $desde . '&hasta=' . $hasta) ?>"
                               class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar3"></i></a>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($resumen === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay empleados activos.</td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Marcas ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-list-ul me-2"></i>Marcas del periodo</span>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalAnadir">
            <i class="bi bi-plus-lg me-1"></i>Añadir a mano
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Cuándo</th>
                    <th>Persona</th>
                    <th>Marca</th>
                    <th class="d-none d-md-table-cell">Origen</th>
                    <th class="d-none d-lg-table-cell">Ubicación</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($marcas === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Sin marcas en este periodo.</td></tr>
                <?php endif ?>
                <?php foreach ($marcas as $m): ?>
                    <?php $anulado = (int) $m['anulado'] === 1; ?>
                    <tr class="<?= $anulado ? 'opacity-50' : '' ?>">
                        <td>
                            <div class="fw-semibold"><?= date('H:i', strtotime($m['marcado_en'])) ?></div>
                            <div class="text-muted small"><?= date('d/m/Y', strtotime($m['marcado_en'])) ?></div>
                        </td>
                        <td>
                            <?= esc($m['nombre']) ?> <?= esc($m['apellidos']) ?>
                            <div class="text-muted small d-md-none"><?= esc($m['cargo']) ?></div>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= $m['tipo'] === 'entrada' ? 'success' : ($m['tipo'] === 'salida' ? 'secondary' : 'info') ?>">
                                <i class="bi <?= FichajeModel::ICONOS[$m['tipo']] ?> me-1"></i><?= FichajeModel::TIPOS[$m['tipo']] ?>
                            </span>
                            <?php if ($anulado): ?>
                                <div><span class="badge text-bg-danger">Anulada</span></div>
                                <div class="text-muted small"><?= esc($m['motivo']) ?></div>
                            <?php elseif ($m['origen'] === 'manual'): ?>
                                <div class="text-muted small"><?= esc($m['motivo']) ?></div>
                            <?php endif ?>
                        </td>
                        <td class="d-none d-md-table-cell text-muted small">
                            <i class="bi <?= $origenes[$m['origen']][1] ?> me-1"></i><?= $origenes[$m['origen']][0] ?>
                            <?php if ($m['editado_por'] !== null && $m['origen'] === 'manual'): ?>
                                <div><?= esc($m['editor'] ?? '') ?></div>
                            <?php endif ?>
                        </td>
                        <td class="d-none d-lg-table-cell small">
                            <?php if ($m['distancia_m'] !== null): ?>
                                <span class="<?= $radio > 0 && (int) $m['distancia_m'] > $radio ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <i class="bi bi-geo-alt me-1"></i><?= number_format((int) $m['distancia_m'], 0, ',', '.') ?> m
                                </span>
                            <?php elseif ($m['latitud'] !== null): ?>
                                <span class="text-muted"><i class="bi bi-geo-alt me-1"></i>sin referencia</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if ($m['foto'] !== null): ?>
                                    <button class="btn btn-sm btn-outline-secondary" title="Ver la foto"
                                            data-bs-toggle="modal" data-bs-target="#modalFoto"
                                            data-foto="<?= site_url('fichajes/foto/' . $m['id']) ?>"
                                            data-quien="<?= esc($m['nombre'] . ' ' . $m['apellidos'], 'attr') ?>"
                                            data-cuando="<?= date('d/m/Y H:i', strtotime($m['marcado_en'])) ?>">
                                        <i class="bi bi-camera"></i>
                                    </button>
                                <?php endif ?>
                                <?php if (! $anulado): ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Anular"
                                            data-bs-toggle="modal" data-bs-target="#modalAnular"
                                            data-accion="<?= site_url('fichajes/anular/' . $m['id']) ?>"
                                            data-detalle="<?= esc(FichajeModel::TIPOS[$m['tipo']] . ' de ' . $m['nombre'] . ' el ' . date('d/m H:i', strtotime($m['marcado_en'])), 'attr') ?>">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-light">
    <i class="bi bi-shield-check me-1"></i>
    Las marcas <strong>no se borran</strong>: si hay un error se anulan con su motivo y queda quién lo hizo.
    Las fotos son un dato personal del trabajador: se guardan fuera de internet y solo se ven desde aquí,
    dejando registro de cada consulta.
</div>

<!-- ═══ Modales ═══ -->
<div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="foto-titulo">Foto del fichaje</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="foto-img" src="" alt="Foto del fichaje" class="w-100" style="border-radius: 0 0 var(--radio) var(--radio);">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="form-anular">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Anular la marca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted" id="anular-detalle"></p>
                    <label class="form-label fw-semibold">Motivo <span class="text-danger">*</span></label>
                    <input type="text" name="motivo" class="form-control" required maxlength="200"
                           placeholder="Fichó por error, marca duplicada…">
                    <div class="form-text">La marca no se borra: queda anulada con este motivo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger">Anular</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAnadir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= site_url('fichajes/anadir') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Añadir una marca a mano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="form-text mb-3">
                        Para cuando alguien se olvidó de fichar. Queda señalada como puesta a mano y con tu nombre.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Persona</label>
                        <select name="empleado_id" class="form-select" required>
                            <option value="">Elige…</option>
                            <?php foreach ($empleados as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= esc($e['apellidos']) ?>, <?= esc($e['nombre']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-5">
                            <label class="form-label fw-semibold">Fecha</label>
                            <input type="date" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-3">
                            <label class="form-label fw-semibold">Hora</label>
                            <input type="time" name="hora" class="form-control" required value="<?= date('H:i') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Marca</label>
                            <select name="tipo" class="form-select">
                                <?php foreach (FichajeModel::TIPOS as $clave => $etiqueta): ?>
                                    <option value="<?= $clave ?>"><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <label class="form-label fw-semibold">Motivo <span class="text-danger">*</span></label>
                    <input type="text" name="motivo" class="form-control" required maxlength="200"
                           placeholder="Se le olvidó marcar la salida; lo confirmó el jefe de turno">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Añadir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .chip-presente {
        display: inline-flex; align-items: center; gap: .5rem; text-decoration: none;
        background: #e9f4ee; color: #1c5c3c; border-radius: var(--radio-sm);
        padding: .5rem .8rem; font-size: .88rem; border: 1px solid #c6e2d2;
        transition: transform .15s ease;
    }
    .chip-presente:hover { transform: translateY(-1px); color: #1c5c3c; }
    .chip-presente.pausa { background: #fdf5e7; color: #7d5a1c; border-color: #f2e0bd; }
</style>

<script>
    document.getElementById('modalFoto').addEventListener('show.bs.modal', function (e) {
        const b = e.relatedTarget;
        document.getElementById('foto-img').src = b.dataset.foto;
        document.getElementById('foto-titulo').textContent = b.dataset.quien + ' · ' + b.dataset.cuando;
    });

    document.getElementById('modalAnular').addEventListener('show.bs.modal', function (e) {
        const b = e.relatedTarget;
        document.getElementById('form-anular').action = b.dataset.accion;
        document.getElementById('anular-detalle').textContent = b.dataset.detalle;
    });
</script>

<?= $this->endSection() ?>
