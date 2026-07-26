<?php
/**
 * Apuntar a alguien en una experiencia.
 * Recibe: catalogo, fecha (por defecto), reserva (array|null si se abre desde una reserva).
 */
use App\Models\ExperienciaModel;

$reserva = $reserva ?? null;
$alojadosLista = $alojados ?? null;

if ($alojadosLista === null && $reserva === null) {
    // Huéspedes que están ahora en el hotel: son los que pueden apuntarse
    $alojadosLista = db_connect()->table('reservas')
        ->select('reservas.id, reservas.codigo, huespedes.nombre, huespedes.apellidos, unidades.nombre AS unidad')
        ->join('huespedes', 'huespedes.id = reservas.huesped_id')
        ->join('unidades', 'unidades.id = reservas.unidad_id')
        ->whereIn('reservas.estado', ['confirmada', 'checkin'])
        ->where('reservas.fecha_salida >=', date('Y-m-d'))
        ->orderBy('reservas.fecha_entrada')
        ->get()
        ->getResultArray();
}
?>
<div class="modal fade" id="modalVender" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= site_url('experiencias/reservar') ?>" id="form-vender">
                <?= csrf_field() ?>
                <?php if ($reserva !== null): ?>
                    <input type="hidden" name="reserva_id" value="<?= (int) $reserva['id'] ?>">
                <?php endif ?>

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-compass me-2"></i>Apuntar a una experiencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Experiencia <span class="text-danger">*</span></label>
                        <select name="experiencia_id" class="form-select" id="v-experiencia" required>
                            <option value="">Elige…</option>
                            <?php foreach ($catalogo as $e): ?>
                                <option value="<?= $e['id'] ?>"
                                        data-precio="<?= (float) $e['precio'] ?>"
                                        data-precio-nino="<?= $e['precio_nino'] !== null ? (float) $e['precio_nino'] : '' ?>"
                                        data-tipo="<?= esc($e['tipo_precio']) ?>"
                                        data-horarios="<?= esc(implode(',', ExperienciaModel::horariosDe($e))) ?>">
                                    <?= esc($e['nombre']) ?> —
                                    $<?= number_format((float) $e['precio'], 0, ',', '.') ?>
                                    <?= $e['tipo_precio'] === 'grupo' ? 'el grupo' : 'por persona' ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <?php if ($reserva === null): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">¿Quién va?</label>
                            <select name="reserva_id" class="form-select" id="v-reserva">
                                <option value="">Cliente de paso (no está alojado)</option>
                                <?php foreach ($alojadosLista as $a): ?>
                                    <option value="<?= $a['id'] ?>">
                                        <?= esc($a['nombre']) ?> <?= esc($a['apellidos']) ?> ·
                                        <?= esc($a['unidad']) ?> (<?= esc($a['codigo']) ?>)
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text">A un huésped alojado se le carga al folio al marcar la salida como realizada.</div>
                        </div>

                        <div class="row g-3 mb-3" id="v-cliente">
                            <div class="col-sm-7">
                                <label class="form-label fw-semibold">Nombre del cliente</label>
                                <input type="text" name="cliente_nombre" class="form-control" maxlength="150">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label fw-semibold">Teléfono</label>
                                <input type="text" name="cliente_telefono" class="form-control" maxlength="30">
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light py-2 small">
                            <i class="bi bi-person me-1"></i>
                            Para <strong><?= esc($reserva['h_nombre'] ?? '') ?> <?= esc($reserva['h_apellidos'] ?? '') ?></strong>
                            · <?= esc($reserva['codigo']) ?>. Se cargará a su folio.
                        </div>
                    <?php endif ?>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control" id="v-fecha" required
                                   value="<?= esc($fecha ?? date('Y-m-d')) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label fw-semibold">Salida</label>
                            <select name="hora" class="form-select" id="v-hora">
                                <option value="">Sin hora fija</option>
                            </select>
                            <div class="form-text" id="v-aviso"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Adultos</label>
                            <input type="number" name="adultos" class="form-control" id="v-adultos" min="1" max="50" value="2">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Niños</label>
                            <input type="number" name="ninos" class="form-control" id="v-ninos" min="0" max="50" value="0">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Total</label>
                            <div class="total-venta" id="v-total">$0</div>
                        </div>
                    </div>

                    <label class="form-label fw-semibold">Notas</label>
                    <input type="text" name="notas" class="form-control" maxlength="300"
                           placeholder="Uno de los niños no monta a caballo; celebran aniversario…">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Apuntar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .total-venta {
        font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.35rem;
        color: var(--bosque); padding: .35rem 0;
    }
    .plazas-libres { font-weight: 600; }
    .plazas-libres.pocas { color: var(--arena); }
    .plazas-libres.ninguna { color: #8f3237; }
</style>

<script>
(function () {
    const BASE = <?= json_encode(site_url('experiencias/disponibilidad')) ?>;
    const $ = (s) => document.querySelector(s);

    const sel = $('#v-experiencia');
    const fecha = $('#v-fecha');
    const hora = $('#v-hora');
    const aviso = $('#v-aviso');

    function pesos(n) { return '$' + Math.round(n).toLocaleString('es-CO'); }

    function calcularTotal() {
        const op = sel.selectedOptions[0];
        if (!op || !op.value) { $('#v-total').textContent = '$0'; return; }

        const precio = parseFloat(op.dataset.precio) || 0;
        if (op.dataset.tipo === 'grupo') { $('#v-total').textContent = pesos(precio); return; }

        const adultos = parseInt($('#v-adultos').value, 10) || 0;
        const ninos = parseInt($('#v-ninos').value, 10) || 0;
        const pn = op.dataset.precioNino !== '' ? parseFloat(op.dataset.precioNino) : precio;

        $('#v-total').textContent = pesos(adultos * precio + ninos * pn);
    }

    /**
     * Consulta plazas libres para pintar cada salida con lo que queda.
     *
     * Se numeran las peticiones: si se cambia rápido de experiencia o de fecha
     * se solapan dos consultas, y sin esto podría ganar la más lenta y enseñar
     * la disponibilidad de otro día.
     */
    let consulta = 0;

    async function refrescarSalidas() {
        const mia = ++consulta;

        hora.innerHTML = '<option value="">Sin hora fija</option>';
        aviso.textContent = '';

        if (!sel.value || !fecha.value) { return; }

        try {
            const r = await fetch(BASE + '?experiencia=' + sel.value + '&fecha=' + fecha.value);
            const d = await r.json();

            if (mia !== consulta) { return; }   // llegó tarde: ya no interesa
            if (!d.ok) { return; }

            if (!d.se_hace) {
                aviso.innerHTML = '<span class="text-danger">Esa experiencia no se hace ese día ('
                    + d.dias + ').</span>';
                return;
            }

            hora.innerHTML = '';
            d.salidas.forEach(function (s) {
                const o = document.createElement('option');
                o.value = s.hora || '';
                const etiqueta = s.hora ? s.hora.substring(0, 5) : 'Sin hora fija';
                o.textContent = etiqueta + ' — ' + (s.libres > 0 ? s.libres + ' plazas libres' : 'completa');
                if (s.libres === 0) { o.disabled = true; }
                hora.appendChild(o);
            });

            const total = d.salidas.reduce((a, s) => a + s.libres, 0);
            if (total === 0) {
                aviso.innerHTML = '<span class="text-danger">No quedan plazas ese día.</span>';
            } else {
                aviso.textContent = 'Cabe' + (d.capacidad === 1 ? '' : 'n') + ' ' + d.capacidad + ' por salida.';
            }
        } catch (e) { /* si falla, el servidor valida igual al guardar */ }
    }

    sel.addEventListener('change', function () { calcularTotal(); refrescarSalidas(); });
    fecha.addEventListener('change', refrescarSalidas);
    $('#v-adultos').addEventListener('input', calcularTotal);
    $('#v-ninos').addEventListener('input', calcularTotal);

    const selReserva = $('#v-reserva');
    if (selReserva) {
        selReserva.addEventListener('change', function () {
            $('#v-cliente').style.display = selReserva.value ? 'none' : 'flex';
        });
    }
})();
</script>
