<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$coloresArea = [
    'recepcion' => 'primary', 'limpieza' => 'warning', 'cocina' => 'danger',
    'mantenimiento' => 'secondary', 'administracion' => 'info', 'otro' => 'light',
];
$activos = array_filter($empleados, static fn ($e) => (int) $e['activo'] === 1);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Personal</h1>
        <p class="text-muted mb-0">
            <?= count($activos) ?> persona<?= count($activos) === 1 ? '' : 's' ?> en plantilla
            <?php if (count($empleados) > count($activos)): ?>
                · <?= count($empleados) - count($activos) ?> dada<?= count($empleados) - count($activos) === 1 ? '' : 's' ?> de baja
            <?php endif ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('turnos') ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-week me-1"></i>Turnos</a>
        <a href="<?= site_url('ausencias') ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-x me-1"></i>Ausencias</a>
        <a href="<?= site_url('personal/nuevo') ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nuevo empleado</a>
    </div>
</div>

<?php if (empty($empleados)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-people fs-1 d-block mb-3 opacity-50"></i>
            <h2 class="h5">Todavía no hay personal registrado</h2>
            <p class="mb-3">
                Aquí llevarás las fichas del equipo: datos laborales, seguridad social,
                documentos, turnos y ausencias.
            </p>
            <a href="<?= site_url('personal/nuevo') ?>" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Registrar al primero
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($empleados as $e): ?>
            <?php
            $ausenteHoy = $ausentes[(int) $e['id']][date('Y-m-d')] ?? null;
            $inactivo   = (int) $e['activo'] !== 1;
            ?>
            <div class="col-md-6 col-xl-4">
                <a href="<?= site_url('personal/ver/' . $e['id']) ?>" class="text-decoration-none">
                    <div class="card h-100 <?= $inactivo ? 'opacity-75' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="kpi-icono bg-<?= $coloresArea[$e['area']] ?>-subtle text-<?= $coloresArea[$e['area']] ?>-emphasis">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div class="flex-fill min-w-0">
                                    <div class="fw-semibold text-body text-truncate">
                                        <?= esc($e['nombre'] . ' ' . $e['apellidos']) ?>
                                    </div>
                                    <div class="small text-muted text-truncate"><?= esc($e['cargo']) ?></div>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <span class="badge text-bg-light border"><?= esc($areas[$e['area']]) ?></span>
                                        <?php if ($inactivo): ?>
                                            <span class="badge text-bg-secondary">De baja</span>
                                        <?php elseif ($ausenteHoy): ?>
                                            <span class="badge text-bg-warning"><?= esc($tiposAusencia[$ausenteHoy]) ?> hoy</span>
                                        <?php endif ?>
                                        <?php if ($e['usuario_id']): ?>
                                            <span class="badge text-bg-light border" title="Tiene cuenta en el sistema">
                                                <i class="bi bi-person-check"></i>
                                            </span>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white small text-muted d-flex justify-content-between">
                            <span><?= esc($contratos[$e['tipo_contrato']]) ?></span>
                            <?php if ($e['telefono']): ?>
                                <span><i class="bi bi-telephone me-1"></i><?= esc($e['telefono']) ?></span>
                            <?php endif ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
