<?php
/** Alta o edición de un servicio del catálogo. Recibe: id, accion, servicio (array|null), grupos. */
$s     = $servicio;
$nuevo = $s === null;

// Iconos habituales, para no tener que buscarlos en la documentación de Bootstrap
$iconos = [
    'bi-check-circle', 'bi-wifi', 'bi-droplet-half', 'bi-fire', 'bi-thermometer-sun',
    'bi-sun', 'bi-water', 'bi-tree', 'bi-cup-hot', 'bi-egg-fried', 'bi-box',
    'bi-house-door', 'bi-moisture', 'bi-wind', 'bi-flower1', 'bi-layers', 'bi-safe',
    'bi-p-square', 'bi-heart', 'bi-universal-access', 'bi-bucket', 'bi-tv', 'bi-music-note-beamed',
];
?>
<div class="modal fade" id="<?= esc($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= esc($accion) ?>">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title"><?= $nuevo ? 'Nuevo servicio' : 'Editar ' . esc($s['nombre']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="80"
                               value="<?= esc($s['nombre'] ?? '') ?>" placeholder="Jacuzzi, Kayak, Telescopio…">
                        <div class="form-text">Así lo verá el huésped en la web.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Grupo</label>
                            <input type="text" name="grupo" class="form-control" list="gruposServicios" maxlength="40"
                                   value="<?= esc($s['grupo'] ?? 'Comodidades') ?>">
                            <datalist id="gruposServicios">
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?= esc($g) ?>">
                                <?php endforeach ?>
                            </datalist>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Orden</label>
                            <input type="number" name="orden" class="form-control" min="0" value="<?= (int) ($s['orden'] ?? 0) ?>">
                        </div>
                    </div>

                    <label class="form-label fw-semibold">Icono</label>
                    <input type="text" name="icono" class="form-control mb-2" maxlength="40"
                           value="<?= esc($s['icono'] ?? 'bi-check-circle') ?>" id="<?= esc($id) ?>Icono">
                    <div class="d-flex gap-1 flex-wrap">
                        <?php foreach ($iconos as $icono): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary elegir-icono"
                                    data-destino="<?= esc($id) ?>Icono" data-icono="<?= $icono ?>" title="<?= $icono ?>">
                                <i class="bi <?= $icono ?>"></i>
                            </button>
                        <?php endforeach ?>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="activo" id="<?= esc($id) ?>Activo"
                               <?= (int) ($s['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= esc($id) ?>Activo">Activo</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary"><?= $nuevo ? 'Añadir' : 'Guardar' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.elegir-icono').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById(b.dataset.destino).value = b.dataset.icono;
        });
    });
</script>
