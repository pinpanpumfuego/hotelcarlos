<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= esc($plantilla['nombre']) ?></h1>
        <p class="text-muted mb-0 font-monospace small">
            <?= esc($plantilla['clave']) ?> · <?= esc($plantilla['idioma']) ?> · <?= esc($plantilla['canal']) ?>
            · <?= $plantilla['finalidad'] === 'operativo' ? 'no pide permiso' : 'pide permiso' ?>
        </p>
    </div>
    <a href="<?= site_url('comunicaciones/plantillas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" action="<?= site_url('comunicaciones/plantilla/' . $plantilla['id']) ?>">
            <?= csrf_field() ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre (solo para ti)</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="120"
                               value="<?= esc(old('nombre', $plantilla['nombre'])) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Asunto</label>
                        <input type="text" name="asunto" class="form-control" maxlength="200"
                               value="<?= esc(old('asunto', $plantilla['asunto'])) ?>">
                        <div class="form-text">
                            Es lo único que se ve en la bandeja de entrada. Que diga algo, no
                            «Comunicación importante».
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Texto</label>
                        <textarea name="cuerpo" class="form-control font-monospace" rows="14" required
                                  style="font-size: .9rem;"><?= esc(old('cuerpo', $plantilla['cuerpo'])) ?></textarea>
                        <div class="form-text">
                            Texto normal, con saltos de línea. Las direcciones que empiecen por
                            <code>http</code> se convierten solas en enlaces. No hace falta HTML —
                            y si lo pegas, se verá tal cual en vez de aplicarse.
                        </div>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activa" value="1" id="activa"
                               <?= (int) $plantilla['activa'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activa">
                            <strong>Activa</strong>
                            <span class="d-block form-text">
                                Apagada, lo que dependa de ella deja de mandarse.
                            </span>
                        </label>
                    </div>

                    <button class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-eye me-2"></i>Cómo queda
            </div>
            <div class="card-body">
                <p class="small text-muted">Con datos de ejemplo, tal y como lo verá el huésped.</p>
                <div class="border rounded p-3" style="background: #fbfbfa;">
                    <div class="fw-semibold mb-2"><?= esc($vista['asunto']) ?: '(sin asunto)' ?></div>
                    <div class="small" style="line-height: 1.6; white-space: pre-wrap;"><?= esc($vista['cuerpo']) ?></div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-braces me-2"></i>Variables</div>
            <div class="card-body">
                <div class="small">
                    <?php foreach ($variables as $v => $que): ?>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <code>{{<?= $v ?>}}</code>
                            <span class="text-muted text-end"><?= esc($que) ?></span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
