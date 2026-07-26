<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\ExperienciaModel;
use App\Models\ExperienciaReservaModel;

$dias = ['Mon' => 'Lunes', 'Tue' => 'Martes', 'Wed' => 'Miércoles', 'Thu' => 'Jueves',
    'Fri' => 'Viernes', 'Sat' => 'Sábado', 'Sun' => 'Domingo'];

$esHoy   = $fecha === date('Y-m-d');
$ayer    = date('Y-m-d', strtotime($fecha . ' -1 day'));
$manana  = date('Y-m-d', strtotime($fecha . ' +1 day'));
$personas = 0;
foreach ($salidas as $s) {
    $personas += $s['personas'];
}
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Experiencias</h1>
        <p class="text-muted mb-0">Qué sale hoy, quién va y cuántas plazas quedan.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVender">
            <i class="bi bi-plus-lg me-1"></i>Apuntar a alguien
        </button>
        <a href="<?= site_url('experiencias/catalogo') ?>" class="btn btn-outline-primary">
            <i class="bi bi-list-stars me-1"></i>Catálogo
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if ($catalogo === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-compass fs-1 text-muted opacity-50 d-block mb-3"></i>
            <h2 class="h5">Todavía no hay experiencias</h2>
            <p class="text-muted">
                Cabalgata, paseo en lancha, avistamiento de aves, caminata al amanecer…<br>
                Es lo que más margen deja y lo que hace que el huésped recuerde el sitio.
            </p>
            <a href="<?= site_url('experiencias/nueva') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Crear la primera
            </a>
        </div>
    </div>
<?php else: ?>

<?php if ($porConfirmar !== []): ?>
    <div class="alert alert-warning">
        <i class="bi bi-hourglass-split me-1"></i>
        <strong><?= count($porConfirmar) ?> solicitud<?= count($porConfirmar) > 1 ? 'es' : '' ?> sin confirmar.</strong>
        <?php foreach (array_slice($porConfirmar, 0, 3) as $p): ?>
            <?= esc(ExperienciaReservaModel::quien($p)) ?> · <?= esc($p['experiencia']) ?>
            (<?= date('d/m', strtotime($p['fecha'])) ?>)<?= $p !== end($porConfirmar) ? ' · ' : '' ?>
        <?php endforeach ?>
    </div>
<?php endif ?>

<!-- ── Semana ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= site_url('experiencias?fecha=' . $ayer) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div class="tira-semana flex-fill">
                <?php foreach ($semana as $dia => $datos): ?>
                    <a href="<?= site_url('experiencias?fecha=' . $dia) ?>"
                       class="dia-semana <?= $dia === $fecha ? 'activo' : '' ?> <?= $dia === date('Y-m-d') ? 'hoy' : '' ?>">
                        <span class="nombre"><?= mb_substr($dias[date('D', strtotime($dia))], 0, 3) ?></span>
                        <span class="numero"><?= date('d', strtotime($dia)) ?></span>
                        <?php if ($datos['salidas'] > 0): ?>
                            <span class="punto" title="<?= $datos['personas'] ?> personas"></span>
                        <?php endif ?>
                    </a>
                <?php endforeach ?>
            </div>
            <a href="<?= site_url('experiencias?fecha=' . $manana) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php if (! $esHoy): ?>
                <a href="<?= site_url('experiencias') ?>" class="btn btn-outline-primary btn-sm">Hoy</a>
            <?php endif ?>
        </div>
    </div>
</div>

<!-- ── Salidas del día ── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="h5 mb-0">
        <?= $dias[date('D', strtotime($fecha))] ?> <?= date('d/m/Y', strtotime($fecha)) ?>
        <?php if ($esHoy): ?><span class="badge text-bg-primary ms-1">Hoy</span><?php endif ?>
    </h2>
    <?php if ($personas > 0): ?>
        <span class="text-muted small"><?= $personas ?> persona<?= $personas > 1 ? 's' : '' ?> en <?= count($salidas) ?> salida<?= count($salidas) > 1 ? 's' : '' ?></span>
    <?php endif ?>
</div>

<?php if ($salidas === []): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
            No hay ninguna salida apuntada este día.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($salidas as $s): ?>
        <?php $libres = max(0, $s['capacidad'] - $s['personas']); ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="fw-semibold">
                        <?php if ($s['hora'] !== null): ?>
                            <span class="hora-salida"><?= substr($s['hora'], 0, 5) ?></span>
                        <?php endif ?>
                        <?= esc($s['experiencia']) ?>
                    </span>
                    <?php if (trim((string) $s['punto']) !== ''): ?>
                        <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= esc($s['punto']) ?></div>
                    <?php endif ?>
                </div>
                <span class="badge text-bg-<?= $libres === 0 ? 'danger' : ($libres <= 2 ? 'warning' : 'light') ?> <?= $libres > 2 ? 'text-muted' : '' ?>">
                    <?= $s['personas'] ?> / <?= $s['capacidad'] ?> plazas
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <?php foreach ($s['reservas'] as $r): ?>
                            <tr class="<?= in_array($r['estado'], ['cancelada', 'no_show'], true) ? 'opacity-50' : '' ?>">
                                <td>
                                    <span class="fw-semibold"><?= esc(ExperienciaReservaModel::quien($r)) ?></span>
                                    <div class="text-muted small">
                                        <?php if ($r['unidad_nombre'] !== null): ?>
                                            <i class="bi bi-house-heart me-1"></i><?= esc($r['unidad_nombre']) ?>
                                            · <?= esc($r['reserva_codigo']) ?>
                                        <?php else: ?>
                                            <i class="bi bi-person me-1"></i>Cliente de paso
                                            <?= $r['cliente_telefono'] !== null ? '· ' . esc($r['cliente_telefono']) : '' ?>
                                        <?php endif ?>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <?= (int) $r['adultos'] ?> adulto<?= (int) $r['adultos'] > 1 ? 's' : '' ?>
                                    <?= (int) $r['ninos'] > 0 ? ' + ' . (int) $r['ninos'] . ' niño' . ((int) $r['ninos'] > 1 ? 's' : '') : '' ?>
                                    <?php if (trim((string) $r['notas']) !== ''): ?>
                                        <div class="text-muted small"><i class="bi bi-sticky me-1"></i><?= esc($r['notas']) ?></div>
                                    <?php endif ?>
                                </td>
                                <td class="text-end fw-semibold text-nowrap">
                                    $<?= number_format((float) $r['total'], 0, ',', '.') ?>
                                    <?php if ($r['folio_movimiento_id'] !== null): ?>
                                        <div><span class="badge text-bg-success">Al folio</span></div>
                                    <?php endif ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= ExperienciaReservaModel::COLORES[$r['estado']] ?>">
                                        <?= ExperienciaReservaModel::ESTADOS[$r['estado']] ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if (in_array($r['estado'], ['solicitada', 'confirmada'], true)): ?>
                                        <form method="post" action="<?= site_url('experiencias/estado/' . $r['id']) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="estado" value="realizada">
                                            <button class="btn btn-sm btn-success" title="Marcar como realizada y cargar al folio">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <?php if ($r['estado'] === 'solicitada'): ?>
                                            <form method="post" action="<?= site_url('experiencias/estado/' . $r['id']) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="estado" value="confirmada">
                                                <button class="btn btn-sm btn-outline-primary" title="Confirmar"><i class="bi bi-check2"></i></button>
                                            </form>
                                        <?php endif ?>
                                        <form method="post" action="<?= site_url('experiencias/estado/' . $r['id']) ?>" class="d-inline"
                                              onsubmit="return confirm('¿Cancelar esta salida?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="estado" value="cancelada">
                                            <button class="btn btn-sm btn-outline-danger" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?php endif ?>

<!-- ═══ Modal de venta ═══ -->
<?= view('experiencias/_modal_vender', ['catalogo' => $catalogo, 'fecha' => $fecha, 'alojados' => null]) ?>

<style>
    .tira-semana { display: grid; grid-template-columns: repeat(7, 1fr); gap: .3rem; }
    .dia-semana {
        display: flex; flex-direction: column; align-items: center; gap: 1px;
        padding: .45rem .2rem; border-radius: var(--radio-sm); text-decoration: none;
        border: 1px solid var(--borde); background: var(--panel-tenue); color: var(--tinta-media);
        position: relative; transition: all .15s ease;
    }
    .dia-semana:hover { background: #fff; border-color: var(--borde-fuerte); color: var(--tinta); }
    .dia-semana.activo { background: var(--bosque); border-color: var(--bosque); color: #fff; }
    .dia-semana.hoy:not(.activo) { border-color: var(--bosque-claro); }
    .dia-semana .nombre { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; }
    .dia-semana .numero { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.05rem; line-height: 1; }
    .dia-semana .punto { width: 5px; height: 5px; border-radius: 50%; background: var(--arena); margin-top: 2px; }
    .dia-semana.activo .punto { background: #e3c9a4; }
    .hora-salida {
        font-family: 'Fraunces', serif; font-weight: 600; color: var(--bosque);
        background: #e9f4ee; border-radius: var(--radio-sm); padding: .1rem .45rem; margin-right: .35rem;
    }
</style>

<?= $this->endSection() ?>
