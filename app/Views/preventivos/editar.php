<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><?= $plan === null ? 'Nuevo plan preventivo' : 'Editar plan' ?></h1>
    <a href="<?= site_url('preventivos') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $plan === null ? site_url('preventivos/guardar') : site_url('preventivos/guardar/' . $plan['id']) ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-check me-2"></i>Qué hay que hacer</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del plan</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="150"
                               value="<?= esc(old('nombre', $plan['nombre'] ?? '')) ?>"
                               placeholder="Revisión anual de extintores">
                        <div class="form-text">Es lo que se leerá en el tablero cuando la orden se abra sola.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Instrucciones</label>
                        <textarea name="instrucciones" class="form-control" rows="5"
                                  placeholder="Paso a paso. Presión, precinto, fecha de recarga…"><?= esc(old('instrucciones', $plan['instrucciones'] ?? '')) ?></textarea>
                        <div class="form-text">
                            Van dentro de la orden. Quien la atienda dentro de seis meses no tiene
                            por qué acordarse de nada.
                        </div>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="obligatorio" value="1" id="obligatorio"
                               <?= old('obligatorio', $plan['obligatorio'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="obligatorio">
                            <strong>Lo exige la normativa</strong>
                            <span class="d-block form-text">
                                Extintores, piscina, planta eléctrica. La orden recuerda guardar el
                                certificado, y el plan no se puede borrar sin quitar antes esta marca.
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam me-2"></i>Sobre qué</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Un equipo concreto</label>
                        <select name="activo_id" class="form-select" id="activoSel">
                            <option value="">Ninguno en particular</option>
                            <?php foreach ($activos as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= (int) old('activo_id', $plan['activo_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                                    <?= esc($a['codigo']) ?> · <?= esc($a['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-0" id="categoriaBloque">
                        <label class="form-label">O toda una categoría</label>
                        <select name="categoria" class="form-select">
                            <option value="">Ninguna: es una tarea general del alojamiento</option>
                            <?php foreach ($categorias as $c => $etiqueta): ?>
                                <option value="<?= $c ?>" <?= old('categoria', $plan['categoria'] ?? '') === $c ? 'selected' : '' ?>>
                                    <?= esc($etiqueta) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text">
                            Se abre <strong>una orden por cada equipo</strong> de esa categoría. Así, cuando
                            compres otro extintor, entra en el plan solo y nadie tiene que acordarse.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-week me-2"></i>Cada cuánto</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Cada</label>
                            <input type="number" name="cada" class="form-control" min="1" required
                                   value="<?= esc(old('cada', $plan['cada'] ?? 3)) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">&nbsp;</label>
                            <select name="periodo" class="form-select">
                                <?php foreach ($periodos as $p => $etiqueta): ?>
                                    <option value="<?= $p ?>" <?= old('periodo', $plan['periodo'] ?? 'meses') === $p ? 'selected' : '' ?>>
                                        <?= esc($etiqueta) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">La primera vez toca el</label>
                            <input type="date" name="proxima_fecha" class="form-control" required
                                   value="<?= esc(old('proxima_fecha', $plan['proxima_fecha'] ?? date('Y-m-d', strtotime('+7 days')))) ?>">
                            <div class="form-text">
                                Las siguientes se cuentan <strong>desde esta fecha</strong>, no desde el día
                                en que se haga. Si una revisión anual se atrasa dos semanas, la del año que
                                viene sigue cayendo en su mes.
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Avisar con antelación</label>
                            <div class="input-group">
                                <input type="number" name="aviso_dias" class="form-control" min="0" max="60"
                                       value="<?= esc(old('aviso_dias', $plan['aviso_dias'] ?? 7)) ?>">
                                <span class="input-group-text">días antes</span>
                            </div>
                            <div class="form-text">
                                Si la orden aparece el mismo día que vence, no da tiempo a llamar a nadie.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-gear me-2"></i>Cómo se abre</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Prioridad</label>
                            <select name="prioridad" class="form-select">
                                <?php foreach ($prioridades as $p => $etiqueta): ?>
                                    <option value="<?= $p ?>" <?= old('prioridad', $plan['prioridad'] ?? 'media') === $p ? 'selected' : '' ?>>
                                        <?= esc($etiqueta) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Dura unos</label>
                            <div class="input-group">
                                <input type="number" name="duracion_min" class="form-control" min="0"
                                       value="<?= esc(old('duracion_min', $plan['duracion_min'] ?? '')) ?>">
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">A cargo de</label>
                            <select name="asignado_a" class="form-select">
                                <option value="">Sin repartir</option>
                                <?php foreach ($tecnicos as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= (int) old('asignado_a', $plan['asignado_a'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                                        <?= esc($t['nombre']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="planActivo"
                               <?= old('activo', $plan['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="planActivo">
                            <strong>Plan en marcha</strong>
                            <span class="d-block form-text">Apagado, deja de generar órdenes pero no se pierde.</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button class="btn btn-primary btn-lg">
            <i class="bi bi-check-lg me-1"></i><?= $plan === null ? 'Crear el plan' : 'Guardar cambios' ?>
        </button>
    </div>
</form>

<script>
    // Un plan apunta a un equipo o a una categoría, nunca a los dos: si no, no
    // se sabría cuántas órdenes tiene que generar.
    (function () {
        var sel = document.getElementById('activoSel');
        var cat = document.getElementById('categoriaBloque');

        function ajustar() {
            cat.style.display = sel.value === '' ? '' : 'none';
        }

        sel.addEventListener('change', ajustar);
        ajustar();
    })();
</script>

<?= $this->endSection() ?>
