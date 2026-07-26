<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\ReglaPrecioModel;
use App\Models\TemporadaModel;

$hoy       = date('Y-m-d');
$anio      = (int) date('Y');
$porTipo   = [];
foreach ($reglas as $r) {
    $porTipo[$r['tipo']][] = $r;
}
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Tarifas</h1>
        <p class="text-muted mb-0">
            El precio de cada noche se calcula solo, a partir de la tarifa base de cada alojamiento.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('tarifas/agente') ?>" class="btn btn-primary">
            <i class="bi bi-magic me-1"></i>Agente de tarifas
        </a>
        <a href="<?= site_url('tarifas/calendario') ?>" class="btn btn-outline-primary">
            <i class="bi bi-calendar3 me-1"></i>Calendario
        </a>
        <a href="<?= site_url('tarifas/simulador') ?>" class="btn btn-outline-primary">
            <i class="bi bi-calculator me-1"></i>Simulador
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<!-- ── Cómo se calcula ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3"><i class="bi bi-diagram-3 me-2 text-muted"></i>Cómo se calcula el precio de una noche</h2>
        <div class="escalera">
            <div class="paso">
                <span class="num">1</span>
                <div>
                    <div class="fw-semibold">Tarifa base</div>
                    <div class="text-muted small">La del tipo de alojamiento. Se edita en <a href="<?= site_url('tipos') ?>">Tipos de alojamiento</a>.</div>
                </div>
            </div>
            <div class="paso">
                <span class="num">2</span>
                <div>
                    <div class="fw-semibold">Temporada</div>
                    <div class="text-muted small">Si la fecha cae dentro de una temporada, se aplica su precio o su ajuste.</div>
                </div>
            </div>
            <div class="paso">
                <span class="num">3</span>
                <div>
                    <div class="fw-semibold">Reglas</div>
                    <div class="text-muted small">Fin de semana, ocupación alta, reserva anticipada, estancia larga… se apilan por prioridad.</div>
                </div>
            </div>
            <div class="paso">
                <span class="num">4</span>
                <div>
                    <div class="fw-semibold">Topes</div>
                    <div class="text-muted small">El precio nunca sale del mínimo y el máximo del alojamiento.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ Temporadas ═══════════ -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h5 mb-0">Temporadas</h2>
        <p class="text-muted small mb-0">Rangos de fechas con su propio precio: alta, baja, puentes, fin de año…</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTemporada">
        <i class="bi bi-plus-lg me-1"></i>Nueva temporada
    </button>
</div>

<div class="card border-0 shadow-sm mb-5">
    <?php if ($temporadas === []): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-range fs-3 d-block mb-2 opacity-50"></i>
            Todavía no hay temporadas. Sin ellas se cobra siempre la tarifa base.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Temporada</th>
                        <th class="d-none d-md-table-cell">Fechas</th>
                        <th>Ajuste</th>
                        <th class="d-none d-lg-table-cell text-center">Prioridad</th>
                        <th class="d-none d-lg-table-cell">Precios cerrados</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($temporadas as $t): ?>
                        <?php
                        $vigente  = $hoy >= $t['desde'] && $hoy <= $t['hasta'];
                        $pasada   = $hoy > $t['hasta'];
                        $inactiva = (int) $t['activa'] !== 1;
                        ?>
                        <tr class="<?= $inactiva ? 'opacity-50' : '' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="punto-color" style="background: <?= esc($t['color']) ?>"></span>
                                    <div>
                                        <a href="<?= site_url('tarifas/temporada/' . $t['id']) ?>" class="fw-semibold text-decoration-none">
                                            <?= esc($t['nombre']) ?>
                                        </a>
                                        <?php if ($vigente && ! $inactiva): ?>
                                            <span class="badge text-bg-success ms-1">En curso</span>
                                        <?php elseif ($pasada): ?>
                                            <span class="badge text-bg-light text-muted ms-1">Terminada</span>
                                        <?php endif ?>
                                        <?php if ($inactiva): ?>
                                            <span class="badge text-bg-secondary ms-1">Desactivada</span>
                                        <?php endif ?>
                                        <div class="text-muted small d-md-none">
                                            <?= date('d/m/Y', strtotime($t['desde'])) ?> → <?= date('d/m/Y', strtotime($t['hasta'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell text-muted small">
                                <?= date('d/m/Y', strtotime($t['desde'])) ?> → <?= date('d/m/Y', strtotime($t['hasta'])) ?>
                                <div><?= (new DateTime($t['desde']))->diff(new DateTime($t['hasta']))->days + 1 ?> noches</div>
                            </td>
                            <td>
                                <span class="fw-semibold <?= (float) $t['ajuste'] < 0 ? 'text-success' : '' ?>">
                                    <?= esc(TemporadaModel::textoAjuste($t)) ?>
                                </span>
                            </td>
                            <td class="d-none d-lg-table-cell text-center text-muted"><?= (int) $t['prioridad'] ?></td>
                            <td class="d-none d-lg-table-cell">
                                <?php if ((int) $t['precios'] > 0): ?>
                                    <span class="badge text-bg-primary"><?= (int) $t['precios'] ?> tipo<?= (int) $t['precios'] > 1 ? 's' : '' ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('tarifas/temporada/' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Precios por tipo">
                                        <i class="bi bi-cash-stack"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary" title="Editar"
                                            data-bs-toggle="modal" data-bs-target="#modalTemporada<?= $t['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= site_url('tarifas/temporada/estado/' . $t['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" title="<?= $inactiva ? 'Activar' : 'Desactivar' ?>">
                                            <i class="bi <?= $inactiva ? 'bi-toggle-off' : 'bi-toggle-on' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= site_url('tarifas/temporada/eliminar/' . $t['id']) ?>"
                                          onsubmit="return confirm('¿Eliminar la temporada <?= esc($t['nombre'], 'js') ?>? Las reservas ya hechas conservan su precio.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<!-- ═══════════ Reglas ═══════════ -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h5 mb-0">Reglas de precio</h2>
        <p class="text-muted small mb-0">Se aplican sobre el precio ya ajustado por la temporada, de mayor a menor prioridad.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegla">
        <i class="bi bi-plus-lg me-1"></i>Nueva regla
    </button>
</div>

<?php if ($reglas === []): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-sliders fs-3 d-block mb-2 opacity-50"></i>
            Aún no hay reglas. Un buen punto de partida: <span class="fw-semibold">viernes y sábado +20 %</span>
            y <span class="fw-semibold">ocupación desde el 70 % +15 %</span>.
        </div>
    </div>
<?php endif ?>

<?php foreach (ReglaPrecioModel::TIPOS as $clave => $etiqueta): ?>
    <?php if (empty($porTipo[$clave])) {
        continue;
    } ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi <?= ['dia_semana' => 'bi-calendar-week', 'ocupacion' => 'bi-bar-chart-line', 'anticipacion' => 'bi-hourglass-split', 'duracion' => 'bi-moon-stars'][$clave] ?> me-2"></i>
            <?= esc($etiqueta) ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Regla</th>
                        <th>Cuándo se aplica</th>
                        <th class="d-none d-md-table-cell">Alojamiento</th>
                        <th>Ajuste</th>
                        <th class="d-none d-lg-table-cell text-center">Prioridad</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($porTipo[$clave] as $r): ?>
                        <?php $inactiva = (int) $r['activa'] !== 1; ?>
                        <tr class="<?= $inactiva ? 'opacity-50' : '' ?>">
                            <td class="fw-semibold">
                                <?= esc($r['nombre']) ?>
                                <?php if ($inactiva): ?>
                                    <span class="badge text-bg-secondary ms-1">Desactivada</span>
                                <?php endif ?>
                            </td>
                            <td class="text-muted small"><?= esc(ReglaPrecioModel::textoCondicion($r)) ?></td>
                            <td class="d-none d-md-table-cell text-muted small">
                                <?= $r['tipo_unidad_nombre'] !== null ? esc($r['tipo_unidad_nombre']) : 'Todos' ?>
                            </td>
                            <td>
                                <span class="fw-semibold <?= (float) $r['ajuste'] < 0 ? 'text-success' : '' ?>">
                                    <?= esc(ReglaPrecioModel::textoAjuste($r)) ?>
                                </span>
                            </td>
                            <td class="d-none d-lg-table-cell text-center text-muted"><?= (int) $r['prioridad'] ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary" title="Editar"
                                            data-bs-toggle="modal" data-bs-target="#modalRegla<?= $r['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= site_url('tarifas/regla/estado/' . $r['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" title="<?= $inactiva ? 'Activar' : 'Desactivar' ?>">
                                            <i class="bi <?= $inactiva ? 'bi-toggle-off' : 'bi-toggle-on' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= site_url('tarifas/regla/eliminar/' . $r['id']) ?>"
                                          onsubmit="return confirm('¿Eliminar la regla <?= esc($r['nombre'], 'js') ?>?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach ?>

<!-- ═══════════ Modales de temporada ═══════════ -->
<?= view('tarifas/_modal_temporada', ['id' => 'modalTemporada', 'accion' => site_url('tarifas/temporada/guardar'), 'temporada' => null, 'anio' => $anio]) ?>
<?php foreach ($temporadas as $t): ?>
    <?= view('tarifas/_modal_temporada', [
        'id'        => 'modalTemporada' . $t['id'],
        'accion'    => site_url('tarifas/temporada/actualizar/' . $t['id']),
        'temporada' => $t,
        'anio'      => $anio,
    ]) ?>
<?php endforeach ?>

<!-- ═══════════ Modales de regla ═══════════ -->
<?= view('tarifas/_modal_regla', ['id' => 'modalRegla', 'accion' => site_url('tarifas/regla/guardar'), 'regla' => null, 'tipos' => $tipos]) ?>
<?php foreach ($reglas as $r): ?>
    <?= view('tarifas/_modal_regla', [
        'id'     => 'modalRegla' . $r['id'],
        'accion' => site_url('tarifas/regla/actualizar/' . $r['id']),
        'regla'  => $r,
        'tipos'  => $tipos,
    ]) ?>
<?php endforeach ?>

<style>
    .escalera { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; }
    .paso { display: flex; gap: .7rem; align-items: flex-start;
            background: var(--panel-tenue); border: 1px solid var(--borde);
            border-radius: var(--radio-sm); padding: .8rem .9rem; }
    .paso .num { flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
                 background: var(--bosque); color: #fff; font-size: .8rem; font-weight: 700;
                 display: flex; align-items: center; justify-content: center; }
    .punto-color { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
                   box-shadow: 0 0 0 3px rgba(0,0,0,.05); }
    .campo-regla { display: none; }
    .campo-regla.visible { display: block; }
</style>

<script>
    // Los campos de una regla dependen de su tipo: mostramos solo los que aplican
    document.querySelectorAll('.form-regla').forEach(function (form) {
        var selector = form.querySelector('[name="tipo"]');
        function refrescar() {
            var tipo = selector.value;
            form.querySelectorAll('.campo-regla').forEach(function (campo) {
                campo.classList.toggle('visible', campo.dataset.para.split(' ').indexOf(tipo) !== -1);
            });
            var pista = form.querySelector('.pista-tipo');
            if (pista) { pista.textContent = pista.dataset['pista' + tipo] || ''; }
        }
        selector.addEventListener('change', refrescar);
        refrescar();
    });

    // Atajos para rellenar fechas de temporada
    document.querySelectorAll('[data-preset]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var form = boton.closest('form');
            var datos = boton.dataset;
            form.querySelector('[name="nombre"]').value = datos.nombre;
            form.querySelector('[name="desde"]').value = datos.desde;
            form.querySelector('[name="hasta"]').value = datos.hasta;
            form.querySelector('[name="ajuste"]').value = datos.ajuste;
        });
    });
</script>

<?= $this->endSection() ?>
