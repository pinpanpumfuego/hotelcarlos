<?= $this->extend('layouts/empleado') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\FichajeModel;

$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$dias = ['Mon' => 'lunes', 'Tue' => 'martes', 'Wed' => 'miércoles', 'Thu' => 'jueves',
    'Fri' => 'viernes', 'Sat' => 'sábado', 'Sun' => 'domingo'];

$anterior = date('Y-n', strtotime(sprintf('%04d-%02d-01 -1 month', $anio, $mes)));
$siguiente = date('Y-n', strtotime(sprintf('%04d-%02d-01 +1 month', $anio, $mes)));
[$aAnio, $aMes] = explode('-', $anterior);
[$sAnio, $sMes] = explode('-', $siguiente);
?>

<div class="cabecera">
    <div class="barra">
        <div>
            <div class="saludo"><a href="<?= site_url('empleado') ?>" style="color:#9dbcaa; text-decoration:none">
                <i class="bi bi-arrow-left"></i> Mi jornada</a></div>
            <h1>Mis horas</h1>
        </div>
    </div>
</div>

<main>
    <div class="tarjeta">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px">
            <a href="<?= site_url('empleado/historial?mes=' . (int) $aMes . '&anio=' . (int) $aAnio) ?>"
               class="btn suave" style="width:auto; padding:10px 14px"><i class="bi bi-chevron-left"></i></a>
            <div style="text-align:center">
                <div style="font-weight:600; text-transform:capitalize"><?= $meses[$mes] ?> <?= $anio ?></div>
                <div class="detalle" style="color:var(--tinta-suave); font-size:.82rem">
                    <?= count($jornadas) ?> día<?= count($jornadas) === 1 ? '' : 's' ?> con fichaje
                </div>
            </div>
            <a href="<?= site_url('empleado/historial?mes=' . (int) $sMes . '&anio=' . (int) $sAnio) ?>"
               class="btn suave" style="width:auto; padding:10px 14px"><i class="bi bi-chevron-right"></i></a>
        </div>

        <div class="estado-grande" style="padding-bottom:0">
            <div class="reloj"><?= esc(FichajeModel::horas($minutos)) ?></div>
            <div class="detalle">trabajadas en el mes</div>
        </div>
    </div>

    <?php if ($jornadas === []): ?>
        <div class="tarjeta">
            <p class="detalle" style="text-align:center; color:var(--tinta-suave); margin:16px 0">
                <i class="bi bi-calendar-x" style="font-size:1.6rem; display:block; margin-bottom:8px; opacity:.5"></i>
                No hay fichajes en este mes.
            </p>
        </div>
    <?php else: ?>
        <div class="tarjeta">
            <h2>Día a día</h2>
            <ul class="lista">
                <?php foreach ($jornadas as $j): ?>
                    <li style="align-items:flex-start">
                        <div style="flex:1">
                            <div class="dia"><?= $dias[date('D', strtotime($j['fecha']))] ?> <?= date('d/m', strtotime($j['fecha'])) ?></div>
                            <div class="detalle">
                                <?php foreach ($j['marcas'] as $m): ?>
                                    <span style="white-space:nowrap">
                                        <?= date('H:i', strtotime($m['marcado_en'])) ?>
                                        <?= $m['tipo'] === 'entrada' ? '↓' : ($m['tipo'] === 'salida' ? '↑' : '·') ?>
                                    </span>
                                <?php endforeach ?>
                                <?php if ($j['abierta']): ?>
                                    <span style="color:#7d5a1c">· sin salida</span>
                                <?php endif ?>
                            </div>
                        </div>
                        <span class="horas"><?= esc(FichajeModel::horas($j['minutos'])) ?></span>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>

    <div class="aviso info">
        <i class="bi bi-shield-check"></i>
        Este es tu registro de jornada. Si ves algo que no cuadra, díselo a gerencia:
        las correcciones quedan anotadas con quién las hizo y por qué.
    </div>
</main>

<p class="pie"><?= esc($hotel->nombre) ?></p>

<?= $this->endSection() ?>
