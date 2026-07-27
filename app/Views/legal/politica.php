<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><?= $politica === null ? 'Nueva versión' : 'Editar versión' ?></h1>
    <a href="<?= site_url('legal/politicas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($aceptada_por > 0): ?>
    <?php // Cambiar el texto de algo que ya firmó gente convierte su firma en
          // una firma sobre un documento distinto. ?>
    <div class="alert alert-warning">
        <strong><i class="bi bi-lock me-1"></i>Esta versión ya la aceptaron <?= $aceptada_por ?> huéspedes.</strong>
        Su texto no se puede cambiar: eso convertiría su firma en una firma sobre otro documento.
        Si hay que cambiar algo, <a href="<?= site_url('legal/politica') ?>">crea una versión nueva</a>.
    </div>
<?php endif ?>

<form method="post" action="<?= $politica === null
    ? site_url('legal/politica')
    : site_url('legal/politica/' . $politica['id']) ?>">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label">Documento</label>
                    <select name="tipo" class="form-select">
                        <?php foreach ($tipos as $t => $etiqueta): ?>
                            <option value="<?= $t ?>" <?= old('tipo', $politica['tipo'] ?? 'datos') === $t ? 'selected' : '' ?>>
                                <?= esc($etiqueta) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Versión</label>
                    <input type="text" name="version" class="form-control font-monospace" required maxlength="20"
                           value="<?= esc(old('version', $politica['version'] ?? '1.0')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Vigente desde</label>
                    <input type="date" name="vigente_desde" class="form-control"
                           value="<?= esc(old('vigente_desde', $politica['vigente_desde'] ?? date('Y-m-d'))) ?>">
                    <div class="form-text">Lo aceptado antes se rige por la versión anterior.</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" required maxlength="150"
                       value="<?= esc(old('titulo', $politica['titulo'] ?? '')) ?>"
                       placeholder="Política de tratamiento de datos personales">
            </div>

            <div class="mb-3">
                <label class="form-label">Texto</label>
                <textarea name="texto" class="form-control" rows="20" required
                          <?= $aceptada_por > 0 ? 'readonly' : '' ?>
                          style="font-size: .92rem; line-height: 1.55;"><?= esc(old('texto', $politica['texto'] ?? '')) ?></textarea>
                <div class="form-text">
                    Este texto lo redacta tu asesor jurídico, no el sistema. Aquí solo se guarda,
                    se versiona y se deja constancia de quién lo aceptó y cuándo.
                </div>
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="publicada" value="1" id="publicada"
                       <?= old('publicada', $politica['publicada'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="publicada">
                    <strong>Publicada</strong>
                    <span class="d-block form-text">
                        Es la que rige y la que se le enseña al huésped. Solo una por documento.
                    </span>
                </label>
            </div>

            <button class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
