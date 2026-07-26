<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $editando = isset($tipo['id']); ?>

<div class="mb-4">
    <a href="<?= site_url('tipos') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Tipos de alojamiento
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $editando ? 'Editar ' . esc($tipo['nombre']) : 'Nuevo tipo de alojamiento' ?></h1>
    <p class="text-muted mb-0 mt-1">
        Un tipo agrupa alojamientos iguales: comparten capacidad, tarifa y descripción.
        Las cabañas concretas se crean después en <a href="<?= site_url('unidades') ?>">Cabañas</a>.
    </p>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('tipos/actualizar/' . $tipo['id']) : site_url('tipos/guardar') ?>">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm" style="max-width: 680px;">
        <div class="card-body p-4">
            <div class="mb-3">
                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control form-control-lg" required
                       value="<?= esc(old('nombre', $tipo['nombre'] ?? '')) ?>" placeholder="Cabaña, Glamping, Habitación estándar…">
                <div class="form-text">Así lo verá el huésped en la web y en el motor de reservas.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"
                          placeholder="Cabaña triangular con techo de paja a la orilla del lago…"><?= esc(old('descripcion', $tipo['descripcion'] ?? '')) ?></textarea>
                <div class="form-text">Aparece en la web pública, bajo el nombre del alojamiento.</div>
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Capacidad <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="capacidad" class="form-control" min="1" max="20" required
                               value="<?= esc(old('capacidad', $tipo['capacidad'] ?? 2)) ?>">
                        <span class="input-group-text">personas</span>
                    </div>
                    <div class="form-text">El motor de reservas no ofrecerá este tipo a grupos mayores.</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Tarifa por noche <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="tarifa_base" class="form-control" min="0" step="1000" required
                               value="<?= esc(old('tarifa_base', (int) ($tipo['tarifa_base'] ?? 0))) ?>">
                        <span class="input-group-text">COP</span>
                    </div>
                    <div class="form-text">Punto de partida: sobre ella se aplican las temporadas y las reglas.</div>
                </div>
            </div>
        </div>

        <div class="card-body border-top p-4">
            <h2 class="h6 fw-semibold mb-1"><i class="bi bi-shield-check me-2 text-muted"></i>Topes de precio</h2>
            <p class="text-muted small">
                Por dinámica que sea la tarifa, nunca saldrá de estos límites.
                Déjalos vacíos si no quieres poner freno.
            </p>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Nunca por debajo de</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="precio_minimo" class="form-control" min="0" step="1000"
                               value="<?= esc(old('precio_minimo', ($tipo['precio_minimo'] ?? null) !== null ? (int) $tipo['precio_minimo'] : '')) ?>"
                               placeholder="Sin mínimo">
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Nunca por encima de</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="precio_maximo" class="form-control" min="0" step="1000"
                               value="<?= esc(old('precio_maximo', ($tipo['precio_maximo'] ?? null) !== null ? (int) $tipo['precio_maximo'] : '')) ?>"
                               placeholder="Sin máximo">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body border-top p-4">
            <h2 class="h6 fw-semibold mb-1"><i class="bi bi-people me-2 text-muted"></i>Personas y suplementos</h2>
            <p class="text-muted small">
                La tarifa cubre a un número de personas; si llegan más, se cobra un suplemento por noche.
            </p>
            <div class="row g-3">
                <div class="col-sm-4">
                    <label class="form-label fw-semibold">La tarifa incluye</label>
                    <div class="input-group">
                        <input type="number" name="personas_incluidas" class="form-control" min="1" max="20"
                               value="<?= esc(old('personas_incluidas', $tipo['personas_incluidas'] ?? 2)) ?>">
                        <span class="input-group-text">pers.</span>
                    </div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-semibold">Adulto adicional</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="suplemento_adulto" class="form-control" min="0" step="1000"
                               value="<?= esc(old('suplemento_adulto', (int) ($tipo['suplemento_adulto'] ?? 0))) ?>">
                    </div>
                    <div class="form-text">Por noche.</div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-semibold">Niño adicional</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="suplemento_nino" class="form-control" min="0" step="1000"
                               value="<?= esc(old('suplemento_nino', (int) ($tipo['suplemento_nino'] ?? 0))) ?>">
                    </div>
                    <div class="form-text">Por noche. Déjalo en 0 si los niños no pagan.</div>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white d-flex gap-2 p-3">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            <a href="<?= site_url('tipos') ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
