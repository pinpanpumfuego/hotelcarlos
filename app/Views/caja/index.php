<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$colorTipo = ['ingreso' => 'success', 'egreso' => 'danger', 'retiro' => 'warning', 'ajuste' => 'secondary'];
$nombreTipo = ['ingreso' => 'Ingreso', 'egreso' => 'Gasto', 'retiro' => 'Retiro', 'ajuste' => 'Ajuste'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Caja</h1>
        <p class="text-muted mb-0"><?= $punto !== null ? esc($punto['nombre']) : 'Sin punto de caja' ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (count($puntos) > 1): ?>
            <form method="get">
                <select name="punto" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($puntos as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $punto_id === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= esc($p['nombre']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </form>
        <?php endif ?>
        <?php if (puede('caja.puntos')): ?>
            <a href="<?= site_url('caja/configurar') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i>Configurar
            </a>
        <?php endif ?>
    </div>
</div>

<?= view('partes/errores') ?>

<?php // Con dos turnos vivos, un cobro apuntado en el punto equivocado
      // descuadra los dos. Verlo evita la mitad de esos errores. ?>
<?php if (count($abiertos) > 1): ?>
    <div class="alert alert-light border small">
        <i class="bi bi-cash-stack me-1"></i>
        Hay <?= count($abiertos) ?> cajas abiertas:
        <?php foreach ($abiertos as $i => $a): ?>
            <a href="<?= site_url('caja?punto=' . $a['punto_id']) ?>"><?= esc($a['punto_nombre']) ?></a>
            (<?= esc($a['usuario_nombre']) ?>)<?= $i < count($abiertos) - 1 ? ', ' : '' ?>
        <?php endforeach ?>.
        Comprueba que estás apuntando en la que toca.
    </div>
<?php endif ?>

<?php if ($turno === null): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-cash-stack fs-1 text-muted d-block mb-3"></i>
            <h2 class="h5">No hay ningún turno abierto<?= $punto !== null ? ' en ' . esc($punto['nombre']) : '' ?></h2>
            <p class="text-muted">Cuenta la base con la que empiezas antes de abrir.</p>

            <?php if (puede('caja.abrir') && $punto !== null): ?>
                <form method="post" action="<?= site_url('caja/abrir') ?>"
                      class="d-flex gap-2 justify-content-center flex-wrap mt-3" style="max-width: 420px; margin: 0 auto;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="punto_id" value="<?= $punto_id ?>">
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="base_inicial" class="form-control" min="0" step="any" required
                               value="<?= (float) $punto['base_sugerida'] ?>">
                    </div>
                    <button class="btn btn-primary text-nowrap"><i class="bi bi-play-fill me-1"></i>Abrir turno</button>
                </form>
            <?php endif ?>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Base</div>
                    <div class="fs-4 fw-semibold"><?= $dinero((float) $turno['base_inicial']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Efectivo cobrado</div>
                    <div class="fs-4 fw-semibold text-success"><?= $dinero($totales['ingresos']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Gastos y retiros</div>
                    <div class="fs-4 fw-semibold text-danger"><?= $dinero($totales['egresos'] + $totales['retiros']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body py-3">
                    <div class="text-muted small">Debería haber</div>
                    <div class="fs-4 fw-semibold"><?= $dinero($esperado) ?></div>
                    <div class="small text-muted">en el cajón</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($totales['otros_medios'] !== []): ?>
        <?php // Esto NO está en el cajón. Va aparte para poder cuadrarlo con el
              // datáfono y con el banco, que es donde de verdad aparece. ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-credit-card me-2"></i>Cobrado por otros medios
                <span class="text-muted small ms-1">— no está en el cajón</span>
            </div>
            <div class="card-body py-2">
                <div class="row g-2 small">
                    <?php foreach ($totales['otros_medios'] as $nombre => $valor): ?>
                        <div class="col-md-4 d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted"><?= esc($nombre) ?></span>
                            <strong><?= $dinero($valor) ?></strong>
                        </div>
                    <?php endforeach ?>
                </div>
                <div class="form-text mt-2">
                    Cuadra estas cifras con el cierre del datáfono y con el extracto del banco.
                    En el arqueo no se cuentan: ahí no hay un peso de esto.
                </div>
            </div>
        </div>
    <?php endif ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="bi bi-list-ul me-2"></i>Movimientos del turno</span>
                    <span class="text-muted small">
                        Abierto por <?= esc($turno['usuario_nombre']) ?>
                        el <?= date('d/m H:i', strtotime($turno['apertura'])) ?>
                    </span>
                </div>
                <?php if ($lista === []): ?>
                    <div class="card-body text-muted small">Todavía no hay movimientos.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Concepto</th>
                                    <th class="d-none d-md-table-cell">Medio</th>
                                    <th class="text-end">Valor</th>
                                    <th class="d-none d-lg-table-cell">Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista as $m): ?>
                                    <tr>
                                        <td>
                                            <span class="badge text-bg-<?= $colorTipo[$m['tipo']] ?>"><?= $nombreTipo[$m['tipo']] ?></span>
                                            <?= esc($m['concepto']) ?>
                                            <?php if ($m['referencia']): ?>
                                                <div class="small text-muted font-monospace"><?= esc($m['referencia']) ?></div>
                                            <?php endif ?>
                                        </td>
                                        <td class="small text-muted d-none d-md-table-cell">
                                            <?= esc($m['medio_nombre']) ?: '—' ?>
                                            <?php if ($m['medio_nombre'] !== null && (int) $m['afecta_caja'] === 0): ?>
                                                <div style="font-size: .72rem;">no entra en el cajón</div>
                                            <?php endif ?>
                                        </td>
                                        <td class="text-end fw-semibold text-<?= $m['tipo'] === 'ingreso' ? 'success' : 'danger' ?>">
                                            <?= $m['tipo'] === 'ingreso' ? '+' : '−' ?><?= $dinero((float) $m['valor']) ?>
                                        </td>
                                        <td class="small text-muted d-none d-lg-table-cell">
                                            <?= date('H:i', strtotime($m['created_at'])) ?>
                                            <?php if ($m['usuario_nombre']): ?>
                                                <div style="font-size: .72rem;"><?= esc($m['usuario_nombre']) ?></div>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>

                <?php if (puede('caja.movimiento')): ?>
                    <div class="card-body border-top">
                        <form method="post" action="<?= site_url('caja/movimiento') ?>" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <select name="tipo" class="form-select form-select-sm">
                                    <option value="ingreso">Entra plata</option>
                                    <option value="egreso">Sale plata (gasto)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="medio" class="form-select form-select-sm">
                                    <?php foreach ($medios as $m): ?>
                                        <option value="<?= esc($m['clave'], 'attr') ?>"><?= esc($m['nombre']) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="concepto" class="form-control form-control-sm" required
                                       maxlength="150" placeholder="De qué es">
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="referencia" class="form-control form-control-sm"
                                       maxlength="80" placeholder="Referencia o aprobación">
                            </div>
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="valor" class="form-control" min="1" step="any" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-plus-lg me-1"></i>Apuntar
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <div class="col-lg-5">
            <?php if (puede('caja.retirar')): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-safe me-2"></i>Retirar a la caja fuerte</div>
                    <div class="card-body">
                        <p class="small text-muted">
                            <strong>No es un gasto:</strong> la plata sale del cajón y sigue siendo del hotel.
                            Se descuenta de lo que hay que contar al cerrar.
                        </p>
                        <form method="post" action="<?= site_url('caja/retirar') ?>" class="d-flex gap-2 flex-wrap">
                            <?= csrf_field() ?>
                            <div class="input-group input-group-sm" style="flex: 1; min-width: 120px;">
                                <span class="input-group-text">$</span>
                                <input type="number" name="valor" class="form-control" min="1" step="any" required>
                            </div>
                            <input type="text" name="motivo" class="form-control form-control-sm"
                                   maxlength="150" placeholder="Motivo" style="flex: 2; min-width: 130px;">
                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-safe"></i></button>
                        </form>
                    </div>
                </div>
            <?php endif ?>

            <?php if (puede('caja.cerrar')): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-calculator me-2"></i>Arqueo y cierre</div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Cuenta el cajón <strong>billete a billete</strong>. El total lo calcula el
                            sistema: escribir la cifra de memoria y que cuadre es demasiado fácil.
                        </p>

                        <form method="post" action="<?= site_url('caja/cerrar') ?>">
                            <?= csrf_field() ?>
                            <table class="table table-sm align-middle mb-2">
                                <tbody>
                                    <?php foreach ($denominaciones as $d): ?>
                                        <tr>
                                            <td class="text-muted small" style="width: 38%;">
                                                $<?= number_format($d, 0, ',', '.') ?>
                                            </td>
                                            <td>
                                                <input type="number" name="d<?= $d ?>" class="form-control form-control-sm conteo"
                                                       min="0" value="0" data-valor="<?= $d ?>" inputmode="numeric">
                                            </td>
                                            <td class="text-end small text-muted subtotal" style="width: 32%;">$0</td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2">Contado</th>
                                        <th class="text-end" id="totalContado">$0</th>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-muted">Debería haber</td>
                                        <td class="text-end text-muted"><?= $dinero($esperado) ?></td>
                                    </tr>
                                    <tr>
                                        <th colspan="2">Diferencia</th>
                                        <th class="text-end" id="diferencia">—</th>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="mb-2" id="bloqueJustificar" style="display: none;">
                                <label class="form-label small text-muted mb-1">Qué pasó</label>
                                <textarea name="justificacion" class="form-control form-control-sm" rows="2"
                                          placeholder="Dentro de un mes nadie se acordará de por qué faltaba."></textarea>
                            </div>

                            <button class="btn btn-primary w-100">
                                <i class="bi bi-lock me-1"></i>Cerrar el turno
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>
<?php endif ?>

<?php if ($historial !== []): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Turnos cerrados</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Cerrado</th>
                        <th class="d-none d-md-table-cell">Quién</th>
                        <th class="text-end">Contado</th>
                        <th class="text-end">Diferencia</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                        <?php $dif = (float) $h['diferencia']; ?>
                        <tr>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($h['cierre'])) ?></td>
                            <td class="small text-muted d-none d-md-table-cell">
                                <?= esc($h['cerro_nombre'] ?? $h['usuario_nombre']) ?>
                            </td>
                            <td class="text-end"><?= $dinero((float) $h['efectivo_contado']) ?></td>
                            <td class="text-end fw-semibold <?= abs($dif) < 0.01 ? 'text-success' : ($dif < 0 ? 'text-danger' : 'text-warning') ?>">
                                <?= abs($dif) < 0.01 ? 'Cuadró' : ($dif < 0 ? '−' : '+') . $dinero(abs($dif)) ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('caja/turno/' . $h['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<?php if ($turno !== null && puede('caja.cerrar')): ?>
<script>
    // El total del arqueo se calcula aquí y no se puede escribir a mano.
    (function () {
        var esperado = <?= json_encode($esperado) ?>;
        var tolerancia = <?= json_encode((float) ($turno['tolerancia'] ?? 2000)) ?>;
        var campos = document.querySelectorAll('.conteo');
        var bloque = document.getElementById('bloqueJustificar');

        function pesos(n) {
            return '$' + Math.round(n).toLocaleString('es-CO');
        }

        function recalcular() {
            var total = 0;

            campos.forEach(function (c) {
                var valor = parseInt(c.dataset.valor, 10) * (parseInt(c.value, 10) || 0);
                total += valor;
                c.closest('tr').querySelector('.subtotal').textContent = pesos(valor);
            });

            document.getElementById('totalContado').textContent = pesos(total);

            var dif = total - esperado;
            var celda = document.getElementById('diferencia');
            celda.textContent = Math.abs(dif) < 1 ? 'Cuadra' : (dif < 0 ? '−' : '+') + pesos(Math.abs(dif));
            celda.className = 'text-end ' + (Math.abs(dif) < 1 ? 'text-success' : (dif < 0 ? 'text-danger' : 'text-warning'));

            // Por encima de la tolerancia hay que explicarlo. El servidor lo
            // vuelve a comprobar: esto solo evita el viaje de ida y vuelta.
            bloque.style.display = Math.abs(dif) > tolerancia ? '' : 'none';
        }

        campos.forEach(function (c) {
            c.addEventListener('input', recalcular);
            c.addEventListener('focus', function () { this.select(); });
        });

        recalcular();
    })();
</script>
<?php endif ?>

<?= $this->endSection() ?>
