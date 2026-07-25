<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$activo   = (int) $empleado['activo'] === 1;
$colores  = ['solicitada' => 'warning', 'aprobada' => 'success', 'rechazada' => 'danger'];
$coloresA = \App\Models\AusenciaModel::COLORES;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <a href="<?= site_url('personal') ?>" class="text-decoration-none small text-muted">
            <i class="bi bi-arrow-left me-1"></i>Personal
        </a>
        <h1 class="h3 mb-0 mt-1">
            <?= esc($empleado['nombre'] . ' ' . $empleado['apellidos']) ?>
            <?php if (! $activo): ?>
                <span class="badge text-bg-secondary fs-6 align-middle ms-2">De baja</span>
            <?php endif ?>
        </h1>
        <p class="text-muted mb-0 mt-1">
            <?= esc($empleado['cargo']) ?> · <?= esc($areas[$empleado['area']]) ?>
            <?php if ($antiguedad): ?> · <?= esc($antiguedad) ?> en el equipo<?php endif ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('personal/editar/' . $empleado['id']) ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <form method="post" action="<?= site_url('personal/estado/' . $empleado['id']) ?>"
              onsubmit="return confirm('<?= $activo ? '¿Dar de baja a esta persona?' : '¿Reincorporar a esta persona?' ?>');">
            <?= csrf_field() ?>
            <button class="btn btn-outline-<?= $activo ? 'danger' : 'success' ?>">
                <i class="bi bi-<?= $activo ? 'person-dash' : 'person-check' ?> me-1"></i>
                <?= $activo ? 'Dar de baja' : 'Reincorporar' ?>
            </button>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- ═══ Datos ═══ -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Datos personales</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:45%">Documento</td><td><?= esc($empleado['tipo_documento']) ?> <?= esc($empleado['num_documento']) ?></td></tr>
                    <tr><td class="text-muted">Nacimiento</td><td><?= $empleado['fecha_nacimiento'] ? date('d/m/Y', strtotime($empleado['fecha_nacimiento'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Teléfono</td><td>
                        <?php if ($empleado['telefono']): ?>
                            <a href="https://wa.me/<?= esc(preg_replace('/\D/', '', $empleado['telefono'])) ?>" target="_blank" rel="noopener"><?= esc($empleado['telefono']) ?></a>
                        <?php else: ?>—<?php endif ?>
                    </td></tr>
                    <tr><td class="text-muted">Correo</td><td><?= esc($empleado['email'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Dirección</td><td><?= esc(trim(($empleado['direccion'] ?? '') . ' ' . ($empleado['ciudad'] ?? ''))) ?: '—' ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-briefcase me-2"></i>Contrato</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:45%">Tipo</td><td><?= esc($contratos[$empleado['tipo_contrato']]) ?></td></tr>
                    <tr><td class="text-muted">Jornada</td><td><?= esc($empleado['jornada'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Ingreso</td><td><?= $empleado['fecha_ingreso'] ? date('d/m/Y', strtotime($empleado['fecha_ingreso'])) : '—' ?></td></tr>
                    <?php if ($empleado['fecha_salida']): ?>
                        <tr><td class="text-muted">Salida</td><td><?= date('d/m/Y', strtotime($empleado['fecha_salida'])) ?></td></tr>
                    <?php endif ?>
                    <tr><td class="text-muted">Salario</td><td><?= (float) $empleado['salario'] > 0 ? '$' . number_format((float) $empleado['salario'], 0, ',', '.') . ' COP' : '—' ?></td></tr>
                    <tr><td class="text-muted">Cuenta del sistema</td><td>
                        <?php if ($empleado['usuario_id']): ?>
                            <?= esc($empleado['usuario_nombre']) ?>
                            <span class="badge text-bg-light border"><?= esc(\App\Models\UsuarioModel::ROLES[$empleado['usuario_rol']] ?? '') ?></span>
                        <?php else: ?>
                            <span class="text-muted">Sin acceso</span>
                        <?php endif ?>
                    </td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-plus me-2"></i>Seguridad social</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:45%">EPS</td><td><?= esc($empleado['eps'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">ARL</td><td><?= esc($empleado['arl'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Pensiones</td><td><?= esc($empleado['fondo_pension'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Caja</td><td><?= esc($empleado['caja_compensacion'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Pago</td><td>
                        <?= esc(trim(($empleado['banco'] ?? '') . ' ' . ($empleado['cuenta_bancaria'] ?? ''))) ?: '—' ?>
                    </td></tr>
                </table>
            </div>
        </div>

        <?php if ($empleado['emergencia_nombre']): ?>
            <div class="card border-start border-4 border-danger">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone-plus me-2 text-danger"></i>En caso de emergencia</div>
                <div class="card-body">
                    <div class="fw-semibold"><?= esc($empleado['emergencia_nombre']) ?></div>
                    <div class="text-muted small mb-2"><?= esc($empleado['emergencia_parentesco'] ?: 'Contacto') ?></div>
                    <?php if ($empleado['emergencia_telefono']): ?>
                        <a href="tel:<?= esc($empleado['emergencia_telefono']) ?>" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-telephone me-1"></i><?= esc($empleado['emergencia_telefono']) ?>
                        </a>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <!-- ═══ Actividad ═══ -->
    <div class="col-lg-7">
        <?php if ($empleado['notas']): ?>
            <div class="alert alert-light border mb-4">
                <i class="bi bi-sticky me-1"></i><?= esc($empleado['notas']) ?>
            </div>
        <?php endif ?>

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-week me-2"></i>Turnos recientes</span>
                <a href="<?= site_url('turnos') ?>" class="small text-decoration-none">Ir al cuadrante</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Fecha</th><th>Horario</th><th class="d-none d-md-table-cell">Puesto</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($turnos)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Sin turnos en las últimas dos semanas.</td></tr>
                        <?php endif ?>
                        <?php foreach ($turnos as $t): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y', strtotime($t['fecha'])) ?></td>
                                <td><?= substr($t['hora_inicio'], 0, 5) ?> – <?= substr($t['hora_fin'], 0, 5) ?></td>
                                <td class="text-muted d-none d-md-table-cell"><?= esc($t['puesto'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-x me-2"></i>Ausencias</span>
                <a href="<?= site_url('ausencias') ?>" class="small text-decoration-none">Gestionar</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($ausencias)): ?>
                    <div class="list-group-item text-center text-muted py-4">Sin ausencias registradas.</div>
                <?php endif ?>
                <?php foreach ($ausencias as $a): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div>
                            <span class="badge text-bg-<?= $coloresA[$a['tipo']] ?>"><?= esc($tiposAusencia[$a['tipo']]) ?></span>
                            <span class="ms-1"><?= date('d/m/Y', strtotime($a['desde'])) ?> → <?= date('d/m/Y', strtotime($a['hasta'])) ?></span>
                            <span class="text-muted small">· <?= \App\Models\AusenciaModel::dias($a) ?> día<?= \App\Models\AusenciaModel::dias($a) > 1 ? 's' : '' ?></span>
                            <?php if ($a['motivo']): ?>
                                <div class="small text-muted"><?= esc($a['motivo']) ?></div>
                            <?php endif ?>
                        </div>
                        <span class="badge text-bg-<?= $colores[$a['estado']] ?>"><?= ucfirst($a['estado']) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <!-- ═══ Documentos ═══ -->
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-folder2-open me-2"></i>Documentos laborales</div>
            <div class="card-body">
                <?php if (empty($documentos)): ?>
                    <p class="text-muted text-center py-3 mb-0">Sin documentos. Sube el contrato, la hoja de vida o las afiliaciones.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach ($documentos as $d): ?>
                            <div class="d-flex align-items-center gap-2 p-2 border rounded-3">
                                <i class="bi bi-<?= str_contains((string) $d['mime'], 'pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-primary' ?> fs-5"></i>
                                <div class="flex-fill min-w-0">
                                    <div class="fw-semibold small"><?= esc($tiposDocumento[$d['tipo']] ?? 'Documento') ?></div>
                                    <div class="text-muted small text-truncate">
                                        <?= esc($d['nombre_original']) ?> · <?= round($d['tamano'] / 1024) ?> KB
                                        · <?= date('d/m/Y', strtotime($d['created_at'])) ?>
                                    </div>
                                </div>
                                <a href="<?= site_url('personal/documento/' . $d['id']) ?>" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <form method="post" action="<?= site_url('personal/documento/eliminar/' . $d['id']) ?>"
                                      onsubmit="return confirm('¿Eliminar este documento?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <form method="post" action="<?= site_url('personal/documento/' . $empleado['id']) ?>"
                      enctype="multipart/form-data" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-sm-5">
                        <label class="form-label small mb-1">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm">
                            <?php foreach ($tiposDocumento as $valor => $etiqueta): ?>
                                <option value="<?= $valor ?>"><?= $etiqueta ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label small mb-1">Archivo</label>
                        <input type="file" name="documento" class="form-control form-control-sm" accept="image/*,application/pdf" required>
                    </div>
                    <div class="col-sm-2">
                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i></button>
                    </div>
                </form>
                <p class="small text-muted mt-2 mb-0">
                    <i class="bi bi-lock me-1"></i>
                    Guardados fuera de internet: solo se ven desde aquí y cada consulta queda registrada.
                </p>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
