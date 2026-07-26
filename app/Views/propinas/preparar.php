<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="mb-4">
    <a href="<?= site_url('propinas') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Propinas
    </a>
    <h1 class="h3 mb-0 mt-1">Liquidar propinas</h1>
    <p class="text-muted mb-0 mt-1">
        Elige el periodo y cómo repartir. Puedes ajustar las cifras a mano antes de cerrar.
    </p>
</div>

<?= view('partes/errores') ?>

<!-- ── Periodo y criterio ── -->
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
                <label class="form-label mb-1">Cómo repartir</label>
                <select name="criterio" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($criterios as $clave => $etiqueta): ?>
                        <option value="<?= $clave ?>" <?= $criterio === $clave ? 'selected' : '' ?>>
                            <?= esc($etiqueta) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-repeat me-1"></i>Calcular</button>
            </div>
        </form>
    </div>
</div>

<?php if ($propuesta['recaudado'] <= 0): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-cash-stack fs-3 d-block mb-2 opacity-50"></i>
            No hay propinas sin liquidar en ese periodo.
        </div>
    </div>
<?php else: ?>

<?php if ($propuesta['sin_camarero'] > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>$<?= number_format($propuesta['sin_camarero'], 0, ',', '.') ?> de propina sin camarero asignado.</strong>
        Son comandas hechas sin el TPV compartido encendido, así que no se sabe quién atendió.
        Ese dinero está en el total y hay que repartirlo igualmente: ajústalo a mano.
    </div>
<?php endif ?>

<form method="post" action="<?= site_url('propinas/guardar') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="desde" value="<?= esc($desde) ?>">
    <input type="hidden" name="hasta" value="<?= esc($hasta) ?>">
    <input type="hidden" name="criterio" value="<?= esc($propuesta['criterio']) ?>">

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
                <div class="text-muted small">Recaudado</div>
                <div class="valor h4 mb-0">$<?= number_format($propuesta['recaudado'], 0, ',', '.') ?></div>
                <div class="text-muted small"><?= (int) $propuesta['comandas'] ?> comandas</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
                <div class="text-muted small">Repartido</div>
                <div class="valor h4 mb-0" id="kpi-repartido">$0</div>
            </div></div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
                <div class="text-muted small">Sin repartir</div>
                <div class="valor h4 mb-0" id="kpi-diferencia">$0</div>
                <div class="text-muted small" id="kpi-aviso">Tiene que quedar en cero: la propina es de los trabajadores.</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold"><i class="bi bi-people me-2"></i>Reparto propuesto</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-cuadrar">
                <i class="bi bi-magic me-1"></i>Cuadrar el resto
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th class="text-center d-none d-md-table-cell">Comandas</th>
                        <th class="text-end d-none d-md-table-cell">Generó</th>
                        <th class="text-end" style="width: 190px;">Le corresponde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($propuesta['lineas'] as $l): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($l['nombre']) ?></td>
                            <td class="text-center d-none d-md-table-cell text-muted"><?= (int) $l['comandas'] ?></td>
                            <td class="text-end d-none d-md-table-cell text-muted">
                                $<?= number_format($l['generado'], 0, ',', '.') ?>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="importe[<?= (int) $l['empleado_id'] ?>]"
                                           class="form-control text-end importe" min="0" step="100"
                                           value="<?= (int) $l['importe'] ?>">
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <label class="form-label fw-semibold">Notas</label>
            <input type="text" name="notas" class="form-control" maxlength="300"
                   placeholder="Quincena del 1 al 15; se incluye a cocina por acuerdo del equipo">
        </div>
        <div class="card-footer bg-white p-3 d-flex gap-2 flex-wrap align-items-center">
            <button class="btn btn-primary" id="btn-cerrar"><i class="bi bi-lock me-1"></i>Cerrar liquidación</button>
            <a href="<?= site_url('propinas') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <span class="text-muted small ms-auto">
                Una vez cerrada no se puede deshacer: esas comandas quedan marcadas como repartidas.
            </span>
        </div>
    </div>
</form>

<?php endif ?>

<script>
(function () {
    const recaudado = <?= (float) $propuesta['recaudado'] ?>;
    const campos = [...document.querySelectorAll('.importe')];
    if (!campos.length) { return; }

    const pesos = (n) => '$' + Math.round(n).toLocaleString('es-CO');

    function refrescar() {
        const repartido = campos.reduce((a, c) => a + (parseFloat(c.value) || 0), 0);
        const resto = Math.round(recaudado - repartido);

        document.getElementById('kpi-repartido').textContent = pesos(repartido);

        const dif = document.getElementById('kpi-diferencia');
        const aviso = document.getElementById('kpi-aviso');
        dif.textContent = pesos(resto);

        if (Math.abs(resto) < 1) {
            dif.className = 'valor h4 mb-0 text-success';
            aviso.textContent = 'Cuadra. Ya puedes cerrarla.';
        } else if (resto > 0) {
            dif.className = 'valor h4 mb-0 text-danger';
            aviso.textContent = 'Faltan por repartir. Ese dinero no puede quedarse en el hotel.';
        } else {
            dif.className = 'valor h4 mb-0 text-danger';
            aviso.textContent = 'Estás repartiendo más de lo que se recaudó.';
        }

        document.getElementById('btn-cerrar').disabled = Math.abs(resto) >= 1;
    }

    // Los pesos sueltos del redondeo se le dan al primero
    document.getElementById('btn-cuadrar').onclick = function () {
        const repartido = campos.reduce((a, c) => a + (parseFloat(c.value) || 0), 0);
        const resto = Math.round(recaudado - repartido);
        campos[0].value = (parseFloat(campos[0].value) || 0) + resto;
        refrescar();
    };

    campos.forEach((c) => c.addEventListener('input', refrescar));
    refrescar();
})();
</script>

<?= $this->endSection() ?>
