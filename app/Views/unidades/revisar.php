<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$conCantidad = array_values(array_filter($inventario, static fn ($i) => (int) $i['cantidad'] > 0));
$porGrupo    = [];
foreach ($conCantidad as $i) {
    $porGrupo[$i['grupo']][] = $i;
}
?>

<div class="mb-4">
    <a href="<?= site_url('unidades/ver/' . $unidad['id']) ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i><?= esc($unidad['nombre']) ?>
    </a>
    <h1 class="h3 mb-0 mt-1">Revisar inventario</h1>
    <p class="text-muted mb-0 mt-1">
        Repasa la cabaña y marca solo lo que no esté bien. Todo lo demás se da por completo.
    </p>
</div>

<?= view('partes/errores') ?>

<?php if ($conCantidad === []): ?>
    <div class="alert alert-warning">
        Esta cabaña no tiene enseres asignados todavía.
        <a href="<?= site_url('unidades/ver/' . $unidad['id']) ?>#inventario">Configúralos primero</a>.
    </div>
<?php else: ?>

<form method="post" action="<?= site_url('unidades/revisar/' . $unidad['id']) ?>">
    <?= csrf_field() ?>
    <?php if ($reserva !== null): ?>
        <input type="hidden" name="reserva_id" value="<?= (int) $reserva['id'] ?>">
    <?php endif ?>

    <?php foreach ($porGrupo as $grupo => $lista): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= esc($grupo) ?></div>
            <div class="card-body p-0">
                <?php foreach ($lista as $i): ?>
                    <div class="linea-revision" data-item="<?= $i['id'] ?>">
                        <div class="datos">
                            <div class="fw-semibold"><?= esc($i['nombre']) ?></div>
                            <div class="text-muted small">
                                Debe haber <?= (int) $i['cantidad'] ?>
                                <?php if ((float) $i['valor_reposicion'] > 0): ?>
                                    · reponer cuesta $<?= number_format((float) $i['valor_reposicion'], 0, ',', '.') ?>
                                <?php endif ?>
                            </div>
                        </div>

                        <div class="opciones">
                            <input type="radio" class="btn-check" name="estado[<?= $i['id'] ?>]" value="ok"
                                   id="e<?= $i['id'] ?>ok" checked>
                            <label class="btn btn-sm btn-outline-success" for="e<?= $i['id'] ?>ok">
                                <i class="bi bi-check-lg"></i> Está
                            </label>

                            <input type="radio" class="btn-check" name="estado[<?= $i['id'] ?>]" value="falta"
                                   id="e<?= $i['id'] ?>falta">
                            <label class="btn btn-sm btn-outline-warning" for="e<?= $i['id'] ?>falta">
                                <i class="bi bi-dash-circle"></i> Falta
                            </label>

                            <input type="radio" class="btn-check" name="estado[<?= $i['id'] ?>]" value="danado"
                                   id="e<?= $i['id'] ?>danado">
                            <label class="btn btn-sm btn-outline-danger" for="e<?= $i['id'] ?>danado">
                                <i class="bi bi-exclamation-triangle"></i> Dañado
                            </label>
                        </div>

                        <div class="extra">
                            <div class="input-group input-group-sm" style="max-width: 150px;">
                                <span class="input-group-text">Quedan</span>
                                <input type="number" name="encontrada[<?= $i['id'] ?>]" class="form-control"
                                       min="0" max="<?= (int) $i['cantidad'] ?>" value="<?= max(0, (int) $i['cantidad'] - 1) ?>">
                            </div>
                            <input type="text" name="observacion[<?= $i['id'] ?>]" class="form-control form-control-sm"
                                   maxlength="200" placeholder="Nota (opcional)">
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endforeach ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <label class="form-label fw-semibold">Notas de la revisión</label>
            <textarea name="notas" class="form-control mb-3" rows="2"
                      placeholder="La cabaña quedó bien, solo faltó una toalla…"></textarea>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="crear_mantenimiento" id="crearMant" checked>
                <label class="form-check-label" for="crearMant">
                    Abrir aviso de <strong>mantenimiento</strong> con lo dañado
                </label>
            </div>

            <?php if ($reserva !== null): ?>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="cargar_folio" id="cargarFolio">
                    <label class="form-check-label" for="cargarFolio">
                        Cargar lo que falta al folio de <strong><?= esc($reserva['nombre']) ?> <?= esc($reserva['apellidos']) ?></strong>
                        (<?= esc($reserva['codigo']) ?>)
                    </label>
                    <div class="form-text">
                        Márcalo solo si el huésped se lo llevó. Se cobra al valor de reposición del catálogo.
                    </div>
                </div>
            <?php else: ?>
                <p class="form-text mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    No hay ninguna estancia reciente en esta cabaña, así que no se puede cargar nada a un folio.
                </p>
            <?php endif ?>
        </div>
        <div class="card-footer bg-white p-3 d-flex gap-2">
            <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Guardar revisión</button>
            <a href="<?= site_url('unidades/ver/' . $unidad['id']) ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
    </div>
</form>

<?php endif ?>

<style>
    .linea-revision {
        display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        padding: .75rem 1.1rem; border-bottom: 1px solid var(--borde);
    }
    .linea-revision:last-child { border-bottom: 0; }
    .linea-revision .datos { flex: 1 1 200px; min-width: 0; }
    .linea-revision .opciones { display: flex; gap: .25rem; flex-wrap: wrap; }
    .linea-revision .extra { display: none; flex: 1 1 100%; gap: .5rem; flex-wrap: wrap; }
    .linea-revision.con-incidencia { background: #fdf7ef; }
    .linea-revision.con-incidencia .extra { display: flex; }
    @media (max-width: 575px) {
        .linea-revision { padding: .7rem .8rem; }
        .linea-revision .opciones .btn { padding: .3rem .5rem; font-size: .8rem; }
    }
</style>

<script>
    // Los campos de detalle solo aparecen cuando hay algo que anotar
    document.querySelectorAll('.linea-revision').forEach(function (linea) {
        linea.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                linea.classList.toggle('con-incidencia', radio.value !== 'ok');
                if (radio.value === 'danado') {
                    // Un artículo dañado sigue estando: no se descuenta cantidad
                    const cant = linea.querySelector('input[type="number"]');
                    cant.value = cant.max;
                }
            });
        });
    });
</script>

<?= $this->endSection() ?>
