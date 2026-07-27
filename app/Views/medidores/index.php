<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$iconos = [
    'agua' => 'droplet', 'energia' => 'lightning-charge', 'gas' => 'fire',
    'combustible' => 'fuel-pump', 'horas' => 'stopwatch', 'otro' => 'speedometer2',
];
$numero = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Medidores y consumos</h1>
        <p class="text-muted mb-0">Agua, luz, gas y combustible.</p>
    </div>
    <?php if (puede('medidores.ver')): ?>
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formMedidor">
            <i class="bi bi-plus-lg me-1"></i>Nuevo medidor
        </button>
    <?php endif ?>
</div>

<?= view('partes/errores') ?>

<div class="collapse mb-4" id="formMedidor">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="<?= site_url('medidores/guardar') ?>" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-5">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required maxlength="120"
                           placeholder="Contador general de agua">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Qué mide</label>
                    <select name="tipo" class="form-select">
                        <?php foreach ($tipos as $t => $etiqueta): ?>
                            <option value="<?= $t ?>"><?= esc($etiqueta) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unidad</label>
                    <input type="text" name="unidad_medida" class="form-control" maxlength="20" placeholder="m³">
                    <div class="form-text">En blanco, la del tipo.</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cabaña</label>
                    <select name="unidad_id" class="form-select">
                        <option value="">General</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= esc($u['nombre']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Dónde está</label>
                    <input type="text" name="ubicacion" class="form-control" maxlength="120"
                           placeholder="Cuarto de máquinas, entrada…">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Avisar si pasa de</label>
                    <div class="input-group">
                        <input type="number" name="alerta_diaria" class="form-control" min="0" step="any">
                        <span class="input-group-text">al día</span>
                    </div>
                    <div class="form-text">
                        Una fuga enterrada no se ve: solo se nota porque el contador sube igual
                        con las cabañas vacías.
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="acumulativo" value="1" id="acum" checked>
                            <label class="form-check-label" for="acum">Es un contador que siempre sube</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activa" value="1" id="act" checked>
                            <label class="form-check-label" for="act">En uso</label>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear medidor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($medidores === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-speedometer2 fs-1 text-muted d-block mb-3"></i>
            <p class="text-muted mb-0">
                Ningún medidor todavía.<br>
                Con apuntar el contador general de agua y el de la luz una vez por semana ya se ve
                si algo se está yendo.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($medidores as $m): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 <?= $m['dispara_alerta'] ? 'border-start border-4 border-danger' : '' ?> <?= (int) $m['activa'] === 0 ? 'opacity-50' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <a href="<?= site_url('medidores/ver/' . $m['id']) ?>" class="fw-semibold text-decoration-none">
                                    <i class="bi bi-<?= $iconos[$m['tipo']] ?> me-1 text-muted"></i><?= esc($m['nombre']) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= esc($tipos[$m['tipo']]) ?>
                                    <?php if ($m['unidad_nombre'] ?? null): ?>
                                        · <?= esc($m['unidad_nombre']) ?>
                                    <?php elseif ($m['ubicacion']): ?>
                                        · <?= esc($m['ubicacion']) ?>
                                    <?php endif ?>
                                </div>
                            </div>
                            <?php if ((int) $m['activa'] === 0): ?>
                                <span class="badge text-bg-secondary">Retirado</span>
                            <?php endif ?>
                        </div>

                        <hr class="my-2">

                        <?php if ($m['ultima'] === null): ?>
                            <p class="small text-muted mb-0">
                                Sin lecturas. Apunta la de hoy para empezar a contar.
                            </p>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-end">
                                <div>
                                    <div class="small text-muted">Última lectura</div>
                                    <div class="fs-5 fw-semibold">
                                        <?= $numero((float) $m['ultima']['lectura']) ?>
                                        <span class="fs-6 text-muted"><?= esc($m['unidad_medida']) ?></span>
                                    </div>
                                    <div class="small text-muted"><?= date('d/m/Y', strtotime($m['ultima']['fecha'])) ?></div>
                                </div>
                                <?php if ($m['consumo_diario'] !== null): ?>
                                    <div class="text-end">
                                        <div class="small text-muted">Al día</div>
                                        <div class="fw-semibold <?= $m['dispara_alerta'] ? 'text-danger' : '' ?>">
                                            <?= $numero($m['consumo_diario']) ?>
                                        </div>
                                    </div>
                                <?php endif ?>
                            </div>

                            <?php if ($m['dispara_alerta']): ?>
                                <div class="alert alert-danger py-1 px-2 small mt-2 mb-0">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    Por encima de lo normal. Merece la pena mirar si hay una fuga.
                                </div>
                            <?php endif ?>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
