<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$editando = isset($unidad['id']);
$estados  = [
    'disponible' => ['Disponible', 'Lista para venderse', 'success', 'bi-check-circle'],
    'ocupada'    => ['Ocupada', 'Hay un huésped alojado', 'danger', 'bi-person-fill'],
    'limpieza'   => ['En limpieza', 'Pendiente de aseo tras la salida', 'warning', 'bi-bucket'],
    'bloqueada'  => ['Bloqueada', 'Fuera de servicio: no se vende', 'secondary', 'bi-slash-circle'],
];
$actual = old('estado', $unidad['estado'] ?? 'disponible');
?>

<div class="mb-4">
    <a href="<?= site_url('unidades') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Cabañas
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $editando ? 'Editar ' . esc($unidad['nombre']) : 'Nueva cabaña' ?></h1>
    <p class="text-muted mb-0 mt-1">
        Cada cabaña es un alojamiento físico que se vende por separado.
    </p>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('unidades/actualizar/' . $unidad['id']) : site_url('unidades/guardar') ?>">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm" style="max-width: 680px;">
        <div class="card-body p-4">
            <div class="row g-3 mb-3">
                <div class="col-sm-7">
                    <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control form-control-lg" required
                           value="<?= esc(old('nombre', $unidad['nombre'] ?? '')) ?>" placeholder="Cabaña 1, Cabaña El Roble…">
                </div>
                <div class="col-sm-5">
                    <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                    <select name="tipo_id" class="form-select form-select-lg" required>
                        <option value="">Elige…</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= esc($t['id']) ?>" <?= (string) old('tipo_id', $unidad['tipo_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= esc($t['nombre']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text">De él salen la capacidad y la tarifa.</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-8">
                    <label class="form-label fw-semibold">Dónde está</label>
                    <input type="text" name="ubicacion" class="form-control" maxlength="120"
                           value="<?= esc(old('ubicacion', $unidad['ubicacion'] ?? '')) ?>"
                           placeholder="Orilla del lago, junto al sendero, primera fila…">
                    <div class="form-text">Ayuda a recepción a asignar la cabaña adecuada a cada huésped.</div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-semibold">Orden</label>
                    <input type="number" name="orden" class="form-control" min="0" max="999"
                           value="<?= esc(old('orden', $unidad['orden'] ?? 0)) ?>">
                    <div class="form-text">En qué posición aparece en los listados.</div>
                </div>
            </div>

            <label class="form-label fw-semibold">Qué tiene de particular</label>
            <textarea name="descripcion" class="form-control mb-3" rows="2" maxlength="300"
                      placeholder="La más apartada, con la mejor vista al amanecer. Sin escalones."><?= esc(old('descripcion', $unidad['descripcion'] ?? '')) ?></textarea>

            <label class="form-label fw-semibold">Estado actual</label>
            <div class="row g-2 mb-3">
                <?php foreach ($estados as $valor => [$titulo, $detalle, $color, $icono]): ?>
                    <div class="col-sm-6">
                        <input type="radio" class="btn-check" name="estado" value="<?= $valor ?>"
                               id="e<?= $valor ?>" <?= $actual === $valor ? 'checked' : '' ?>>
                        <label class="btn btn-outline-<?= $color ?> w-100 text-start p-3" for="e<?= $valor ?>">
                            <i class="bi <?= $icono ?> me-1"></i><strong><?= $titulo ?></strong>
                            <span class="d-block small opacity-75"><?= $detalle ?></span>
                        </label>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="form-text mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Normalmente no hace falta tocarlo: el estado cambia solo con los check-in, check-out y la limpieza.
            </div>

            <label class="form-label fw-semibold">Notas internas</label>
            <textarea name="notas" class="form-control" rows="2"
                      placeholder="Detalles que solo ve el personal: acceso, particularidades…"><?= esc(old('notas', $unidad['notas'] ?? '')) ?></textarea>
        </div>

        <div class="card-footer bg-white d-flex gap-2 p-3">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            <a href="<?= site_url('unidades') ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
