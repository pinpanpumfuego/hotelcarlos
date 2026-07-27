<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $numero = static fn (?float $n): string => $n === null ? '—' : rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ','); ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= esc($medidor['nombre']) ?></h1>
        <p class="text-muted mb-0">
            <?= esc($tipos[$medidor['tipo']]) ?> · se mide en <?= esc($medidor['unidad_medida']) ?>
            <?= $medidor['ubicacion'] ? ' · ' . esc($medidor['ubicacion']) : '' ?>
        </p>
    </div>
    <a href="<?= site_url('medidores') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Consumo al día</div>
                <div class="fs-3 fw-semibold"><?= $numero($diario) ?></div>
                <div class="small text-muted">últimas lecturas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Este mes</div>
                <div class="fs-3 fw-semibold"><?= $numero($mes) ?></div>
                <div class="small text-muted"><?= esc($medidor['unidad_medida']) ?> desde el día 1</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Aviso configurado</div>
                <div class="fs-3 fw-semibold">
                    <?= $medidor['alerta_diaria'] !== null ? $numero((float) $medidor['alerta_diaria']) : '—' ?>
                </div>
                <div class="small text-muted">al día</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <?php if (puede('medidores.leer')): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square me-2"></i>Apuntar una lectura</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('medidores/leer/' . $medidor['id']) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Lo que marca el contador</label>
                            <div class="input-group input-group-lg">
                                <input type="number" name="lectura" class="form-control" step="any" required
                                       inputmode="decimal" autofocus>
                                <span class="input-group-text"><?= esc($medidor['unidad_medida']) ?></span>
                            </div>
                            <div class="form-text">
                                El número tal cual está en el contador. El consumo se calcula solo
                                restando la lectura anterior.
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Fecha</label>
                                <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nota</label>
                                <input type="text" name="nota" class="form-control" maxlength="200" placeholder="Opcional">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-lg me-1"></i>Apuntar</button>
                    </form>
                </div>
            </div>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2"></i>Ajustes</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('medidores/guardar/' . $medidor['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="120"
                               value="<?= esc($medidor['nombre']) ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Qué mide</label>
                            <select name="tipo" class="form-select">
                                <?php foreach ($tipos as $t => $etiqueta): ?>
                                    <option value="<?= $t ?>" <?= $medidor['tipo'] === $t ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Unidad</label>
                            <input type="text" name="unidad_medida" class="form-control" maxlength="20"
                                   value="<?= esc($medidor['unidad_medida']) ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Cabaña</label>
                            <select name="unidad_id" class="form-select">
                                <option value="">General</option>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (int) $medidor['unidad_id'] === (int) $u['id'] ? 'selected' : '' ?>>
                                        <?= esc($u['nombre']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Equipo</label>
                            <select name="activo_id" class="form-select">
                                <option value="">Ninguno</option>
                                <?php foreach ($activos as $a): ?>
                                    <option value="<?= $a['id'] ?>" <?= (int) $medidor['activo_id'] === (int) $a['id'] ? 'selected' : '' ?>>
                                        <?= esc($a['nombre']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" name="ubicacion" class="form-control" maxlength="120"
                               value="<?= esc($medidor['ubicacion']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Avisar si pasa de</label>
                        <div class="input-group">
                            <input type="number" name="alerta_diaria" class="form-control" min="0" step="any"
                                   value="<?= esc($medidor['alerta_diaria']) ?>">
                            <span class="input-group-text"><?= esc($medidor['unidad_medida']) ?>/día</span>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="acumulativo" value="1" id="acum"
                               <?= (int) $medidor['acumulativo'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="acum">
                            Siempre sube
                            <span class="d-block form-text">
                                Un contador de agua sube; un tanque de gasolina baja al gastarse.
                            </span>
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="activa" value="1" id="act"
                               <?= (int) $medidor['activa'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="act">En uso</label>
                    </div>
                    <button class="btn btn-outline-primary w-100"><i class="bi bi-check-lg me-1"></i>Guardar ajustes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-list-columns me-2"></i>Historial</div>
            <?php if ($historial === []): ?>
                <div class="card-body text-muted small">
                    Sin lecturas todavía. La primera solo sirve de punto de partida: el consumo
                    empieza a verse con la segunda.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-end">Lectura</th>
                                <th class="text-end">Consumo</th>
                                <th class="text-end d-none d-md-table-cell">Al día</th>
                                <th class="d-none d-lg-table-cell">Quién</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $h): ?>
                                <tr class="<?= (int) $h['sospechosa'] === 1 ? 'table-warning' : '' ?>">
                                    <td>
                                        <?= date('d/m/Y', strtotime($h['fecha'])) ?>
                                        <?php if ($h['nota']): ?>
                                            <div class="small text-muted"><?= esc($h['nota']) ?></div>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end font-monospace"><?= $numero((float) $h['lectura']) ?></td>
                                    <td class="text-end">
                                        <?php if ((int) $h['sospechosa'] === 1): ?>
                                            <span class="badge text-bg-warning" title="Marca menos que la anterior">Revisar</span>
                                        <?php else: ?>
                                            <?= $numero($h['consumo'] !== null ? (float) $h['consumo'] : null) ?>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end small text-muted d-none d-md-table-cell">
                                        <?= $h['consumo'] !== null && (int) $h['dias'] > 0
                                            ? $numero((float) $h['consumo'] / (int) $h['dias'])
                                            : '—' ?>
                                    </td>
                                    <td class="small text-muted d-none d-lg-table-cell"><?= esc($h['usuario_nombre']) ?: '—' ?></td>
                                    <td class="text-end">
                                        <?php if (puede('medidores.leer')): ?>
                                            <form method="post" action="<?= site_url('medidores/lectura/borrar/' . $h['id']) ?>"
                                                  onsubmit="return confirm('¿Borrarla? Se recalculan los consumos siguientes.')">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
