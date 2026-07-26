<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\CanalConexionModel;

$conectadas = 0;
foreach ($conexiones as $lista) {
    foreach ($lista as $c) {
        if ((int) $c['activa'] === 1 && trim((string) $c['url_importar']) !== '') {
            $conectadas++;
        }
    }
}

$desfase = $ultimaSync !== null ? (time() - strtotime($ultimaSync)) / 3600 : null;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Portales y canales</h1>
        <p class="text-muted mb-0">
            Sincroniza las fechas ocupadas con Booking, Airbnb y demás para no vender dos veces la misma noche.
        </p>
    </div>
    <?php if ($conectadas > 0): ?>
        <form method="post" action="<?= site_url('canales/sincronizar') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Sincronizar ahora</button>
        </form>
    <?php endif ?>
</div>

<?= view('partes/errores') ?>

<!-- ── Sobreventa: lo primero que hay que ver ── -->
<?php if ($conflictos !== []): ?>
    <div class="alert alert-danger">
        <h2 class="h6 mb-2">
            <i class="bi bi-exclamation-octagon me-1"></i>
            <?= count($conflictos) ?> posible sobreventa
        </h2>
        <p class="small mb-2">
            Estas noches están vendidas <strong>en los dos sitios</strong>. Resuélvelo cuanto antes:
            cancela en uno o mueve al huésped de cabaña.
        </p>
        <ul class="mb-0 small">
            <?php foreach ($conflictos as $c): ?>
                <li>
                    <strong><?= esc($c['unidad_nombre']) ?></strong>:
                    <a href="<?= site_url('reservas/ver/' . $c['reserva_id']) ?>"><?= esc($c['codigo']) ?></a>
                    (<?= esc($c['nombre']) ?> <?= esc($c['apellidos']) ?>,
                    <?= date('d/m', strtotime($c['fecha_entrada'])) ?>–<?= date('d/m', strtotime($c['fecha_salida'])) ?>)
                    contra <?= esc(CanalConexionModel::nombreCanal($c['canal'])) ?>
                    (<?= date('d/m', strtotime($c['b_entrada'])) ?>–<?= date('d/m', strtotime($c['b_salida'])) ?>)
                </li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<?php if ($conectadas === 0): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h6 mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Cómo funciona esto</h2>
            <div class="escalera">
                <div class="paso">
                    <span class="num">1</span>
                    <div>
                        <div class="fw-semibold">Das tu calendario</div>
                        <div class="text-muted small">Copias la dirección de cada cabaña y la pegas en Booking o Airbnb. Ellos bloquean solos lo que tú ya vendiste.</div>
                    </div>
                </div>
                <div class="paso">
                    <span class="num">2</span>
                    <div>
                        <div class="fw-semibold">Traes el suyo</div>
                        <div class="text-muted small">Pegas aquí la dirección que ellos te dan. Sus reservas aparecen como fechas bloqueadas.</div>
                    </div>
                </div>
                <div class="paso">
                    <span class="num">3</span>
                    <div>
                        <div class="fw-semibold">Tu motor lo respeta</div>
                        <div class="text-muted small">Una cabaña bloqueada deja de ofrecerse en tu web automáticamente.</div>
                    </div>
                </div>
            </div>
            <p class="form-text mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Esto no es instantáneo.</strong> Las plataformas leen el calendario cada 2–4 horas.
                Si en esa ventana entran dos reservas para la misma noche, aquí arriba te avisará.
                Solo llegan fechas: ni el nombre del huésped ni el importe.
            </p>
        </div>
    </div>
<?php elseif ($desfase !== null && $desfase > 12): ?>
    <div class="alert alert-warning">
        <i class="bi bi-clock-history me-1"></i>
        Hace <?= (int) $desfase ?> horas que no se sincroniza. Pulsa «Sincronizar ahora» o revisa la tarea programada.
    </div>
<?php endif ?>

<!-- ── Conexiones por cabaña ── -->
<div class="row g-3 mb-4">
    <?php foreach ($unidades as $u): ?>
        <?php
        $suyas = $conexiones[(int) $u['id']] ?? [];
        $porCanal = [];
        foreach ($suyas as $c) {
            $porCanal[$c['canal']] = $c;
        }
        $direccion = site_url('calendario-ical/' . $u['token_ical']);
        ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-house-door me-2"></i><?= esc($u['nombre']) ?></span>
                    <span class="text-muted small"><?= count($porCanal) ?> <?= count($porCanal) === 1 ? 'conexión' : 'conexiones' ?></span>
                </div>

                <div class="card-body">
                    <!-- Exportar -->
                    <label class="form-label fw-semibold small mb-1">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Tu calendario (pégalo en el portal)
                    </label>
                    <div class="input-group input-group-sm mb-1">
                        <input type="text" class="form-control direccion-ical" readonly value="<?= esc($direccion) ?>">
                        <button class="btn btn-outline-secondary copiar" type="button" data-valor="<?= esc($direccion, 'attr') ?>">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <a class="btn btn-outline-secondary" href="<?= esc($direccion) ?>" target="_blank" rel="noopener" title="Ver">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                    <div class="form-text mb-3">
                        Es secreta. Si se filtra,
                        <form method="post" action="<?= site_url('canales/token/' . $u['id']) ?>" class="d-inline"
                              onsubmit="return confirm('¿Cambiar la dirección? Tendrás que volver a pegarla en todos los portales.');">
                            <?= csrf_field() ?>
                            <button class="btn btn-link btn-sm p-0 align-baseline">cámbiala</button>
                        </form>.
                    </div>

                    <!-- Importar -->
                    <label class="form-label fw-semibold small mb-2">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Calendarios que traes
                    </label>

                    <?php foreach (CanalConexionModel::sincronizables() as $clave => [$nombre, $icono, $color, $comision]): ?>
                        <?php $c = $porCanal[$clave] ?? null; ?>
                        <form method="post" action="<?= site_url('canales/guardar') ?>" class="fila-canal">
                            <?= csrf_field() ?>
                            <input type="hidden" name="unidad_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="canal" value="<?= $clave ?>">
                            <input type="hidden" name="activa" value="1">

                            <span class="marca-canal" style="background: <?= $color ?>" title="<?= esc($nombre) ?>">
                                <i class="bi <?= $icono ?>"></i>
                            </span>

                            <div class="flex-fill">
                                <input type="url" name="url_importar" class="form-control form-control-sm"
                                       value="<?= esc($c['url_importar'] ?? '') ?>"
                                       placeholder="<?= esc($nombre) ?> — pega aquí su enlace .ics">
                                <?php if ($c !== null && $c['ultima_sync'] !== null): ?>
                                    <div class="form-text">
                                        <?php if ($c['ultimo_error'] !== null): ?>
                                            <span class="text-danger">
                                                <i class="bi bi-x-circle me-1"></i><?= esc($c['ultimo_error']) ?>
                                            </span>
                                        <?php else: ?>
                                            <i class="bi bi-check-circle text-success me-1"></i>
                                            <?= (int) $c['eventos'] ?> fecha<?= (int) $c['eventos'] === 1 ? '' : 's' ?> ·
                                            <?= date('d/m H:i', strtotime($c['ultima_sync'])) ?>
                                        <?php endif ?>
                                    </div>
                                <?php endif ?>
                            </div>

                            <button class="btn btn-sm btn-outline-primary" title="Guardar y leer ahora">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </form>

                        <?php if ($c !== null): ?>
                            <form method="post" action="<?= site_url('canales/eliminar/' . $c['id']) ?>" class="text-end mb-2"
                                  onsubmit="return confirm('¿Quitar la conexión con <?= esc($nombre, 'js') ?>? Sus fechas volverán a estar libres.');">
                                <?= csrf_field() ?>
                                <button class="btn btn-link btn-sm text-danger p-0">Quitar <?= esc($nombre) ?></button>
                            </form>
                        <?php endif ?>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<!-- ── Fechas bloqueadas ── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-calendar-x me-2"></i>Fechas bloqueadas</span>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalBloquear">
            <i class="bi bi-plus-lg me-1"></i>Bloquear a mano
        </button>
    </div>

    <?php if ($bloqueos === []): ?>
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-calendar-check fs-3 d-block mb-2 opacity-50"></i>
            Ninguna fecha bloqueada en los próximos seis meses.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Cabaña</th>
                        <th>Fechas</th>
                        <th>Origen</th>
                        <th class="d-none d-md-table-cell">Detalle</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bloqueos as $b): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($b['unidad_nombre']) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($b['fecha_entrada'])) ?> →
                                <?= date('d/m/Y', strtotime($b['fecha_salida'])) ?>
                                <div class="text-muted small">
                                    <?= (int) ((strtotime($b['fecha_salida']) - strtotime($b['fecha_entrada'])) / 86400) ?> noches
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= CanalConexionModel::colorCanal($b['canal']) ?>">
                                    <?= $b['origen'] === 'manual' ? 'A mano' : esc(CanalConexionModel::nombreCanal($b['canal'])) ?>
                                </span>
                            </td>
                            <td class="text-muted small d-none d-md-table-cell">
                                <?= esc($b['resumen'] ?? '') ?>
                                <?php if (trim((string) $b['notas']) !== ''): ?>
                                    <div><?= esc($b['notas']) ?></div>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <?php if ($b['origen'] === 'manual'): ?>
                                    <form method="post" action="<?= site_url('canales/desbloquear/' . $b['id']) ?>"
                                          onsubmit="return confirm('¿Liberar estas fechas?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-unlock"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small" title="Viene de fuera: cancélalo allí">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<div class="alert alert-light mt-4">
    <i class="bi bi-lightbulb me-1"></i>
    <strong>Qué llega y qué no.</strong> Por iCal solo viajan fechas: cuando alguien reserve por Booking,
    aquí verás que la cabaña está ocupada, pero <strong>no su nombre ni el importe</strong>. Si quieres su
    folio y su factura, crea la reserva a mano marcando el canal. Para que entre sola hace falta un
    channel manager de pago, y el sistema ya está preparado para conectarse a uno.
</div>

<!-- ═══ Modal de bloqueo manual ═══ -->
<div class="modal fade" id="modalBloquear" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= site_url('canales/bloquear') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Bloquear fechas a mano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="form-text mb-3">
                        Para obras, uso de la familia o cualquier motivo por el que una cabaña no se pueda vender.
                        Se envía también a los portales conectados.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cabaña</label>
                        <select name="unidad_id" class="form-select" required>
                            <option value="">Elige…</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= esc($u['nombre']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Desde</label>
                            <input type="date" name="desde" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Hasta</label>
                            <input type="date" name="hasta" class="form-control" required>
                            <div class="form-text">Esa noche ya queda libre.</div>
                        </div>
                    </div>
                    <label class="form-label fw-semibold">Motivo</label>
                    <input type="text" name="resumen" class="form-control mb-3" maxlength="200"
                           placeholder="Pintura de la terraza, uso familiar…">
                    <label class="form-label fw-semibold">Notas internas</label>
                    <input type="text" name="notas" class="form-control" maxlength="300">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Bloquear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .escalera { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
    .paso { display: flex; gap: .7rem; align-items: flex-start;
            background: var(--panel-tenue); border: 1px solid var(--borde);
            border-radius: var(--radio-sm); padding: .8rem .9rem; }
    .paso .num { flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
                 background: var(--bosque); color: #fff; font-size: .8rem; font-weight: 700;
                 display: flex; align-items: center; justify-content: center; }
    .direccion-ical { font-size: .76rem; font-family: monospace; }
    .fila-canal { display: flex; align-items: flex-start; gap: .5rem; margin-bottom: .5rem; }
    .marca-canal { width: 30px; height: 30px; border-radius: var(--radio-sm); flex-shrink: 0;
                   color: #fff; display: flex; align-items: center; justify-content: center;
                   font-size: .9rem; margin-top: 1px; }
</style>

<script>
    document.querySelectorAll('.copiar').forEach(function (b) {
        b.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(b.dataset.valor);
                const antes = b.innerHTML;
                b.innerHTML = '<i class="bi bi-check-lg"></i>';
                setTimeout(() => { b.innerHTML = antes; }, 1600);
            } catch (e) {
                // Sin permiso para el portapapeles: se selecciona para copiar a mano
                const campo = b.closest('.input-group').querySelector('input');
                campo.select();
            }
        });
    });
</script>

<?= $this->endSection() ?>
