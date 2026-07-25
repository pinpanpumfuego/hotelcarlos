<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$nombresDia = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$nombresMes = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoy        = date('Y-m-d');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Calendario de disponibilidad</h1>
    <div class="btn-group">
        <a href="<?= site_url('calendario') ?>?desde=<?= esc($anterior) ?>" class="btn btn-outline-secondary" title="Periodo anterior">
            <i class="bi bi-chevron-left"></i>
        </a>
        <a href="<?= site_url('calendario') ?>" class="btn btn-outline-secondary">Hoy</a>
        <a href="<?= site_url('calendario') ?>?desde=<?= esc($siguiente) ?>" class="btn btn-outline-secondary" title="Periodo siguiente">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>

<div class="d-flex flex-wrap gap-3 mb-3 small">
    <span><span class="badge" style="background:#4c8bf5">&nbsp;&nbsp;</span> Confirmada</span>
    <span><span class="badge" style="background:#f5b04c">&nbsp;&nbsp;</span> Pendiente</span>
    <span><span class="badge" style="background:#41a869">&nbsp;&nbsp;</span> Alojado (check-in)</span>
    <span class="text-muted">Haz clic en una casilla libre para crear una reserva en esa fecha.</span>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0" style="min-width: 1100px;">
            <thead>
                <tr class="table-light">
                    <th class="position-sticky start-0 bg-light" style="min-width: 180px; z-index: 2;">Unidad</th>
                    <?php foreach ($dias as $dia): ?>
                        <?php
                        $esHoy = $dia->format('Y-m-d') === $hoy;
                        $esFinde = in_array((int) $dia->format('w'), [0, 6], true);
                        ?>
                        <th class="text-center small <?= $esHoy ? 'table-primary' : ($esFinde ? 'table-secondary' : '') ?>" style="min-width: 44px;">
                            <div class="text-muted"><?= $nombresDia[(int) $dia->format('w')] ?></div>
                            <div class="fw-bold"><?= $dia->format('j') ?></div>
                            <?php if ($dia->format('j') === '1' || $dia === $dias[0]): ?>
                                <div class="text-muted" style="font-size:.65rem"><?= $nombresMes[(int) $dia->format('n')] ?></div>
                            <?php endif ?>
                        </th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unidades as $u): ?>
                    <tr>
                        <td class="position-sticky start-0 bg-white fw-semibold" style="z-index: 1;">
                            <?= esc($u['nombre']) ?>
                            <div class="text-muted small fw-normal"><?= esc($u['tipo_nombre']) ?></div>
                        </td>
                        <?php foreach ($dias as $dia): ?>
                            <?php
                            $clave   = $dia->format('Y-m-d');
                            $reserva = $ocupacion[$u['id']][$clave] ?? null;
                            ?>
                            <?php if ($reserva !== null): ?>
                                <?php
                                $colorFondo = ['confirmada' => '#4c8bf5', 'pendiente' => '#f5b04c', 'checkin' => '#41a869'][$reserva['estado']] ?? '#999';
                                $titulo = $reserva['codigo'] . ' · ' . $reserva['huesped_nombre'] . ' ' . $reserva['huesped_apellidos']
                                    . ' · ' . date('d/m', strtotime($reserva['fecha_entrada'])) . '–' . date('d/m', strtotime($reserva['fecha_salida']));
                                ?>
                                <td class="p-0 text-center" style="background: <?= $colorFondo ?>;" title="<?= esc($titulo) ?>">
                                    <a href="<?= site_url('reservas/editar/' . $reserva['id']) ?>" class="d-block text-white text-decoration-none small py-2"
                                       style="min-height: 100%;">
                                        <?= $clave === $reserva['fecha_entrada'] ? esc($reserva['codigo']) : '&nbsp;' ?>
                                    </a>
                                </td>
                            <?php else: ?>
                                <td class="p-0 text-center <?= $clave === $hoy ? 'table-primary' : '' ?>">
                                    <a href="<?= site_url('reservas/nueva') ?>?unidad=<?= esc($u['id']) ?>&entrada=<?= esc($clave) ?>"
                                       class="d-block text-decoration-none text-muted small py-2" title="Reservar <?= esc($u['nombre']) ?> desde el <?= $dia->format('d/m/Y') ?>">
                                        &nbsp;
                                    </a>
                                </td>
                            <?php endif ?>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
