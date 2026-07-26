<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\FichajeModel;

$dias = ['Mon' => 'Lunes', 'Tue' => 'Martes', 'Wed' => 'Miércoles', 'Thu' => 'Jueves',
    'Fri' => 'Viernes', 'Sat' => 'Sábado', 'Sun' => 'Domingo'];

$total = array_sum(array_column($jornadas, 'minutos'));
$sinCerrar = count(array_filter($jornadas, static fn ($j) => $j['abierta']));
?>

<div class="mb-4">
    <a href="<?= site_url('fichajes') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Control de jornada
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1"><?= esc($empleado['nombre']) ?> <?= esc($empleado['apellidos']) ?></h1>
            <p class="text-muted mb-0">
                <?= esc($empleado['cargo']) ?> ·
                del <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?>
            </p>
        </div>
        <a href="<?= site_url('personal/ver/' . $empleado['id']) ?>" class="btn btn-outline-primary">
            <i class="bi bi-person-badge me-1"></i>Su ficha
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Total trabajado</div>
            <div class="valor h4 mb-0"><?= esc(FichajeModel::horas($total)) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Días con fichaje</div>
            <div class="valor h4 mb-0"><?= count($jornadas) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Media por día</div>
            <div class="valor h4 mb-0">
                <?= count($jornadas) > 0 ? esc(FichajeModel::horas((int) round($total / count($jornadas)))) : '—' ?>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Días sin cerrar</div>
            <div class="valor h4 mb-0 <?= $sinCerrar > 0 ? 'text-warning' : '' ?>"><?= $sinCerrar ?></div>
            <?php if ($sinCerrar > 0): ?>
                <div class="text-muted small">falta la salida</div>
            <?php endif ?>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3 me-2"></i>Día a día</div>

    <?php if ($jornadas === []): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
            Sin fichajes en este periodo.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Marcas</th>
                        <th class="text-center d-none d-md-table-cell">Pausa</th>
                        <th class="text-end">Trabajado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jornadas as $j): ?>
                        <tr class="<?= $j['abierta'] ? 'table-warning' : '' ?>">
                            <td>
                                <div class="fw-semibold"><?= $dias[date('D', strtotime($j['fecha']))] ?></div>
                                <div class="text-muted small"><?= date('d/m/Y', strtotime($j['fecha'])) ?></div>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ($j['marcas'] as $m): ?>
                                        <span class="marca-hora <?= $m['tipo'] ?>" title="<?= FichajeModel::TIPOS[$m['tipo']] ?> · <?= ucfirst($m['origen']) ?>">
                                            <i class="bi <?= FichajeModel::ICONOS[$m['tipo']] ?>"></i>
                                            <?= date('H:i', strtotime($m['marcado_en'])) ?>
                                            <?php if ($m['origen'] === 'manual'): ?>
                                                <i class="bi bi-pencil-fill ms-1" style="font-size:.6rem" title="Puesta a mano"></i>
                                            <?php elseif ($m['origen'] === 'movil'): ?>
                                                <i class="bi bi-phone ms-1" style="font-size:.65rem" title="Desde el móvil"></i>
                                            <?php endif ?>
                                            <?php if ($m['foto'] !== null): ?>
                                                <a href="<?= site_url('fichajes/foto/' . $m['id']) ?>" target="_blank" rel="noopener"
                                                   class="ms-1" title="Ver la foto"><i class="bi bi-camera" style="font-size:.7rem"></i></a>
                                            <?php endif ?>
                                        </span>
                                    <?php endforeach ?>
                                </div>
                                <?php if ($j['abierta']): ?>
                                    <div class="small text-warning-emphasis mt-1">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Falta la marca de salida
                                    </div>
                                <?php endif ?>
                            </td>
                            <td class="text-center text-muted small d-none d-md-table-cell">
                                <?= $j['pausa'] > 0 ? $j['pausa'] . ' min' : '—' ?>
                            </td>
                            <td class="text-end fw-semibold"><?= esc(FichajeModel::horas($j['minutos'])) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="3" class="fw-semibold">Total del periodo</td>
                        <td class="text-end fw-bold fs-5"><?= esc(FichajeModel::horas($total)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif ?>
</div>

<style>
    .marca-hora {
        display: inline-flex; align-items: center; gap: .25rem; font-size: .84rem;
        padding: .18rem .55rem; border-radius: 99px; border: 1px solid var(--borde);
        background: var(--panel-tenue); white-space: nowrap;
    }
    .marca-hora.entrada { background: #e9f4ee; border-color: #c6e2d2; color: #1c5c3c; }
    .marca-hora.salida { background: #f4f0e8; border-color: #e6dcc8; color: #7d5a1c; }
    .marca-hora a { color: inherit; }
</style>

<?= $this->endSection() ?>
