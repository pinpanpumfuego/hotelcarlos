<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="mb-4">
    <a href="<?= site_url('limpieza') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Limpieza
    </a>
    <h1 class="h3 mb-1 mt-1">Checklist de limpieza</h1>
    <p class="text-muted mb-0">
        Qué hay que repasar en cada cabaña. Se configura <strong>por tipo de
        alojamiento</strong>: si hay siete cabañas iguales, escribir el mismo
        checklist siete veces es la forma más segura de que acaben siendo distintos.
    </p>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($puntos === []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-list-check fs-2 d-block mb-2 text-muted opacity-50"></i>
                    <p class="text-muted mb-0">
                        Todavía no hay puntos. Sin checklist, la limpieza se puede terminar
                        directamente; con él, queda constancia de lo que se repasó.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($puntos as $grupo => $lista): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><?= esc($grupo) ?></div>
                    <div class="card-body py-2">
                        <?php foreach ($lista as $p): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <span class="<?= $p['activo'] ? '' : 'text-muted text-decoration-line-through' ?>">
                                        <?= esc($p['texto']) ?>
                                    </span>
                                    <?php if ($p['exige_foto']): ?>
                                        <span class="badge text-bg-warning ms-1">
                                            <i class="bi bi-camera me-1"></i>Foto obligatoria
                                        </span>
                                    <?php endif ?>
                                    <div class="small text-muted">
                                        <?php if (! empty($p['zona'])): ?><?= esc($p['zona']) ?> · <?php endif ?>
                                        <?php if (empty($p['tipos'])): ?>
                                            en todas las limpiezas
                                        <?php else: ?>
                                            solo en:
                                            <?php foreach (explode(',', (string) $p['tipos']) as $t): ?>
                                                <?= esc($tiposLimpieza[trim($t)] ?? $t) ?>
                                            <?php endforeach ?>
                                        <?php endif ?>
                                    </div>
                                </div>
                                <form method="post" action="<?= site_url('limpieza/checklist/eliminar/' . $p['id']) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endforeach ?>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Añadir un punto</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('limpieza/checklist/guardar') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Qué hay que repasar</label>
                            <input type="text" name="texto" class="form-control" required maxlength="200"
                                   placeholder="Revisar que el termo tiene agua caliente">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Zona</label>
                            <input type="text" name="zona" class="form-control" maxlength="60" placeholder="Baño">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Para qué cabañas</label>
                            <select name="tipo_unidad_id" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($tipos as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>"><?= esc($t['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">En qué limpiezas</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($tiposLimpieza as $clave => $nombre): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tipos[]"
                                               value="<?= esc($clave) ?>" id="tl<?= esc($clave) ?>">
                                        <label class="form-check-label small" for="tl<?= esc($clave) ?>"><?= esc($nombre) ?></label>
                                    </div>
                                <?php endforeach ?>
                            </div>
                            <div class="form-text">Sin marcar nada, vale para todas.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="exige_foto" value="1" id="exigeFoto">
                                <label class="form-check-label" for="exigeFoto">
                                    <strong>Exige foto</strong>
                                    <span class="d-block form-text">
                                        No se podrá terminar la limpieza sin ella. Es lo que convierte
                                        un «ya está» en algo comprobable el día que alguien reclama.
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3"><i class="bi bi-plus-lg me-1"></i>Añadir</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Política de inspección</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('limpieza/politica') ?>">
                    <?= csrf_field() ?>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="inspeccion" value="1" id="insp"
                               <?= $exigeInspeccion ? 'checked' : '' ?>>
                        <label class="form-check-label" for="insp">
                            <strong>Hay que inspeccionar antes de entregar</strong>
                        </label>
                    </div>
                    <p class="small text-muted">
                        Con una sola persona que limpia y revisa, pedirle que se inspeccione
                        a sí misma es un clic sin sentido. Con tres, es lo único que
                        garantiza que la cabaña esté como debe.
                    </p>
                    <button class="btn btn-primary btn-sm">Guardar</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Cómo va el mes</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="small text-muted text-uppercase">Tareas</div>
                        <div class="fs-4 fw-semibold"><?= (int) $rendimiento['tareas'] ?></div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted text-uppercase">Minutos de media</div>
                        <div class="fs-4 fw-semibold">
                            <?= $rendimiento['minutos_medios'] === null ? '—' : $rendimiento['minutos_medios'] ?>
                        </div>
                    </div>
                </div>

                <?php if ($rendimiento['tasa'] !== null): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-top">
                        <span class="small">Hay que rehacer</span>
                        <span class="fw-semibold text-<?= $rendimiento['tasa'] > 15 ? 'danger' : 'success' ?>">
                            <?= $rendimiento['tasa'] ?>%
                        </span>
                    </div>
                <?php endif ?>

                <?php if ($porPersona !== []): ?>
                    <div class="mt-3">
                        <div class="small text-muted text-uppercase mb-1">Por persona</div>
                        <?php foreach ($porPersona as $p): ?>
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span><?= esc($p['quien']) ?></span>
                                <span class="text-muted">
                                    <?= (int) $p['tareas'] ?> tareas
                                    <?php if ($p['minutos'] !== null): ?>
                                        · <?= round((float) $p['minutos']) ?> min
                                    <?php endif ?>
                                    <?php if ((int) $p['reprocesos'] > 0): ?>
                                        · <?= (int) $p['reprocesos'] ?> rehechas
                                    <?php endif ?>
                                </span>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Los reprocesos sirven para <strong>formar, no para castigar</strong>: si
                    alguien repite siempre el mismo punto, lo más probable es que ese punto
                    no esté bien explicado.
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
