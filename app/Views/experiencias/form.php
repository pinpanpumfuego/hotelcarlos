<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\ExperienciaModel;

$editando = isset($exp['id']);
$diasSel  = $editando ? ExperienciaModel::diasDe($exp) : [1, 2, 3, 4, 5, 6, 7];
?>

<div class="mb-4">
    <a href="<?= site_url('experiencias/catalogo') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Catálogo de experiencias
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $editando ? 'Editar ' . esc($exp['nombre']) : 'Nueva experiencia' ?></h1>
    <p class="text-muted mb-0 mt-1">
        Todo lo que pongas aquí se usa en tres sitios: la web, el motor de reservas y el mostrador.
    </p>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('experiencias/actualizar/' . $exp['id']) : site_url('experiencias/guardar') ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <!-- ── Qué es ── -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-compass me-2"></i>Qué es</div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-8">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control form-control-lg" required maxlength="120"
                                   value="<?= esc(old('nombre', $exp['nombre'] ?? '')) ?>"
                                   placeholder="Cabalgata al amanecer">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Categoría</label>
                            <select name="categoria" class="form-select">
                                <?php foreach (ExperienciaModel::CATEGORIAS as $cat => $icono): ?>
                                    <option value="<?= $cat ?>" <?= old('categoria', $exp['categoria'] ?? 'Naturaleza') === $cat ? 'selected' : '' ?>>
                                        <?= $cat ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>

                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea name="descripcion" class="form-control mb-3" rows="4"
                              placeholder="Salimos con las primeras luces bordeando el lago, entre la niebla…"><?= esc(old('descripcion', $exp['descripcion'] ?? '')) ?></textarea>
                    <div class="form-text mb-3">
                        Esto es lo que lee el huésped en la web. Cuenta lo que va a sentir, no solo lo que va a hacer.
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Incluye</label>
                            <input type="text" name="incluye" class="form-control" maxlength="300"
                                   value="<?= esc(old('incluye', $exp['incluye'] ?? '')) ?>"
                                   placeholder="Guía, caballo, casco, refrigerio">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">No incluye</label>
                            <input type="text" name="no_incluye" class="form-control" maxlength="300"
                                   value="<?= esc(old('no_incluye', $exp['no_incluye'] ?? '')) ?>"
                                   placeholder="Transporte, propinas">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Cuándo ── -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clock me-2"></i>Cuándo se hace</div>
                <div class="card-body p-4">
                    <label class="form-label fw-semibold">Días</label>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <?php foreach (ExperienciaModel::DIAS as $num => $nombre): ?>
                            <input type="checkbox" class="btn-check" name="dias[]" value="<?= $num ?>"
                                   id="d<?= $num ?>" <?= in_array($num, $diasSel, true) ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary btn-sm" for="d<?= $num ?>">
                                <?= mb_substr($nombre, 0, 3) ?>
                            </label>
                        <?php endforeach ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Horas de salida</label>
                            <input type="text" name="horarios" class="form-control"
                                   value="<?= esc(old('horarios', $exp['horarios'] ?? '')) ?>"
                                   placeholder="06:00, 15:30">
                            <div class="form-text">Separadas por comas. Déjalo vacío si la hora se acuerda con cada huésped.</div>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label fw-semibold">Duración</label>
                            <div class="input-group">
                                <input type="number" name="duracion_min" class="form-control" min="5" step="15"
                                       value="<?= esc(old('duracion_min', $exp['duracion_min'] ?? 90)) ?>">
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label fw-semibold">Avisar con</label>
                            <div class="input-group">
                                <input type="number" name="aviso_horas" class="form-control" min="0" max="168"
                                       value="<?= esc(old('aviso_horas', $exp['aviso_horas'] ?? 0)) ?>">
                                <span class="input-group-text">h</span>
                            </div>
                            <div class="form-text">De antelación.</div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Punto de encuentro</label>
                            <input type="text" name="punto_encuentro" class="form-control" maxlength="150"
                                   value="<?= esc(old('punto_encuentro', $exp['punto_encuentro'] ?? '')) ?>"
                                   placeholder="Recepción / muelle del lago">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Proveedor externo</label>
                            <input type="text" name="proveedor" class="form-control" maxlength="120"
                                   value="<?= esc(old('proveedor', $exp['proveedor'] ?? '')) ?>"
                                   placeholder="Vacío si la hacéis vosotros">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- ── Precio ── -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Precio</div>
                <div class="card-body p-4">
                    <label class="form-label fw-semibold">Cómo se cobra</label>
                    <select name="tipo_precio" class="form-select mb-3" id="tipoPrecio">
                        <option value="persona" <?= old('tipo_precio', $exp['tipo_precio'] ?? 'persona') === 'persona' ? 'selected' : '' ?>>
                            Por persona
                        </option>
                        <option value="grupo" <?= old('tipo_precio', $exp['tipo_precio'] ?? '') === 'grupo' ? 'selected' : '' ?>>
                            Precio cerrado por grupo
                        </option>
                    </select>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Precio <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio" class="form-control" min="0" step="1000" required
                                       id="precio" value="<?= esc(old('precio', (int) ($exp['precio'] ?? 0))) ?>">
                            </div>
                        </div>
                        <div class="col-6" id="cajaPrecioNino">
                            <label class="form-label fw-semibold">Precio niño</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio_nino" class="form-control" min="0" step="1000"
                                       value="<?= esc(old('precio_nino', ($exp['precio_nino'] ?? null) !== null ? (int) $exp['precio_nino'] : '')) ?>"
                                       placeholder="Igual">
                            </div>
                        </div>
                    </div>

                    <label class="form-label fw-semibold">Lo que te cuesta</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text">$</span>
                        <input type="number" name="coste" class="form-control" min="0" step="1000" id="coste"
                               value="<?= esc(old('coste', (int) ($exp['coste'] ?? 0))) ?>">
                    </div>
                    <div class="form-text mb-3">
                        Guía, caballo, combustible, lo que le pagas al proveedor… Sirve para saber si vale la pena.
                    </div>

                    <div class="alert alert-light mb-0" id="cajaMargen">
                        <div class="d-flex justify-content-between">
                            <span>Te queda</span>
                            <strong id="margen">—</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Cupo ── -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2"></i>Cupo</div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold">Caben</label>
                            <input type="number" name="capacidad" class="form-control" min="1" max="100" required
                                   value="<?= esc(old('capacidad', $exp['capacidad'] ?? 8)) ?>">
                            <div class="form-text">Por salida.</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Mínimo</label>
                            <input type="number" name="minimo" class="form-control" min="1" max="100"
                                   value="<?= esc(old('minimo', $exp['minimo'] ?? 1)) ?>">
                            <div class="form-text">Para que salga.</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Edad mín.</label>
                            <input type="number" name="edad_minima" class="form-control" min="0" max="99"
                                   value="<?= esc(old('edad_minima', $exp['edad_minima'] ?? '')) ?>"
                                   placeholder="Sin límite">
                            <div class="form-text">Años.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Visibilidad ── -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="activa" id="activa"
                               <?= (int) old('activa', $exp['activa'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activa">
                            <strong>Activa</strong>
                            <span class="d-block form-text">Se puede vender en el mostrador.</span>
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="publicada" id="publicada"
                               <?= (int) old('publicada', $exp['publicada'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="publicada">
                            <strong>Se anuncia en la web</strong>
                            <span class="d-block form-text">Y se ofrece al reservar en línea.</span>
                        </label>
                    </div>

                    <label class="form-label fw-semibold">Notas internas</label>
                    <input type="text" name="notas_internas" class="form-control mb-3" maxlength="300"
                           value="<?= esc(old('notas_internas', $exp['notas_internas'] ?? '')) ?>"
                           placeholder="Confirmar con Don Julio el día antes">

                    <label class="form-label fw-semibold">Orden</label>
                    <input type="number" name="orden" class="form-control" min="0" max="999"
                           value="<?= esc(old('orden', $exp['orden'] ?? 0)) ?>">
                </div>
                <div class="card-footer bg-white p-3 d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    <a href="<?= site_url('experiencias/catalogo') ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const precio = document.getElementById('precio');
    const coste = document.getElementById('coste');
    const margen = document.getElementById('margen');
    const tipo = document.getElementById('tipoPrecio');
    const cajaNino = document.getElementById('cajaPrecioNino');

    function pintar() {
        const p = parseFloat(precio.value) || 0;
        const c = parseFloat(coste.value) || 0;

        if (p <= 0) { margen.textContent = '—'; return; }

        const m = p - c;
        const pct = Math.round(m / p * 1000) / 10;
        margen.textContent = '$' + Math.round(m).toLocaleString('es-CO') + ' (' + pct + ' %)';
        margen.style.color = m <= 0 ? '#8f3237' : (pct < 30 ? '#7d5a1c' : '#1c5c3c');
    }

    function precioNino() {
        cajaNino.style.display = tipo.value === 'grupo' ? 'none' : 'block';
    }

    precio.addEventListener('input', pintar);
    coste.addEventListener('input', pintar);
    tipo.addEventListener('change', precioNino);
    pintar();
    precioNino();
})();
</script>

<?= $this->endSection() ?>
