<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colores = ['guardado' => 'warning', 'avisado' => 'info', 'devuelto' => 'success', 'desechado' => 'secondary'];
?>

<div class="mb-4">
    <a href="<?= site_url('limpieza') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Limpieza
    </a>
    <h1 class="h3 mb-1 mt-1">Objetos olvidados</h1>
    <p class="text-muted mb-0">
        Lo que aparece en las cabañas. Se guarda, se avisa al huésped, y acaba
        devuelto o desechado.
    </p>
</div>

<?= view('partes/errores') ?>

<?php if ($plazo !== []): ?>
    <div class="alert alert-warning d-flex gap-2">
        <i class="bi bi-hourglass-bottom mt-1"></i>
        <div>
            <strong><?= count($plazo) ?> objeto(s) llevan guardados más del plazo.</strong>
            <div class="small mt-1">
                No se tiran solos: toca decidir qué hacer con ellos. Tirar la cámara de
                alguien por un vencimiento automático es la clase de error que no se arregla.
            </div>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <form method="get" class="d-flex gap-2 align-items-end">
                    <div>
                        <label class="form-label small mb-1">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($estados as $clave => $texto): ?>
                                <option value="<?= esc($clave) ?>" <?= ($filtro ?? '') === $clave ? 'selected' : '' ?>>
                                    <?= esc($texto) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="<?= site_url('limpieza/objetos') ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                </form>
            </div>
        </div>

        <?php if ($objetos === []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bag-check fs-2 d-block mb-2 text-muted opacity-50"></i>
                    <p class="text-muted mb-0">No hay objetos con ese filtro.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($objetos as $o): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <span class="fw-semibold"><?= esc($o['descripcion']) ?></span>
                                <div class="small text-muted">
                                    <?= esc($o['cabana'] ?? 'sin cabaña') ?>
                                    <?php if (! empty($o['donde'])): ?> · <?= esc($o['donde']) ?><?php endif ?>
                                    · encontrado el <?= date('d/m/Y', strtotime($o['encontrado_el'])) ?>
                                </div>
                                <?php if (! empty($o['nombre'])): ?>
                                    <div class="small mt-1">
                                        <i class="bi bi-person me-1"></i>
                                        <?= esc(trim($o['nombre'] . ' ' . ($o['apellidos'] ?? ''))) ?>
                                        <?php if (! empty($o['reserva_codigo'])): ?>
                                            <span class="text-muted">· <?= esc($o['reserva_codigo']) ?></span>
                                        <?php endif ?>
                                        <?php if (! empty($o['telefono'])): ?>
                                            · <a href="tel:<?= esc($o['telefono']) ?>" class="text-decoration-none"><?= esc($o['telefono']) ?></a>
                                        <?php endif ?>
                                        <?php if (! empty($o['email'])): ?>
                                            · <a href="mailto:<?= esc($o['email']) ?>" class="text-decoration-none"><?= esc($o['email']) ?></a>
                                        <?php endif ?>
                                    </div>
                                <?php endif ?>
                                <?php if (! empty($o['notas'])): ?>
                                    <div class="small text-muted fst-italic mt-1"><?= esc($o['notas']) ?></div>
                                <?php endif ?>
                            </div>
                            <span class="badge text-bg-<?= $colores[$o['estado']] ?? 'secondary' ?>">
                                <?= esc($estados[$o['estado']] ?? $o['estado']) ?>
                            </span>
                        </div>

                        <?php if (in_array($o['estado'], ['guardado', 'avisado'], true)): ?>
                            <form method="post" action="<?= site_url('limpieza/objeto/estado/' . $o['id']) ?>"
                                  class="d-flex gap-2 mt-3 flex-wrap">
                                <?= csrf_field() ?>
                                <input type="text" name="notas" class="form-control form-control-sm flex-grow-1"
                                       maxlength="300" placeholder="Nota (opcional)">
                                <?php if ($o['estado'] === 'guardado'): ?>
                                    <button name="estado" value="avisado" class="btn btn-sm btn-outline-info">Avisado</button>
                                <?php endif ?>
                                <button name="estado" value="devuelto" class="btn btn-sm btn-success">Devuelto</button>
                                <button name="estado" value="desechado" class="btn btn-sm btn-outline-secondary">Desechado</button>
                            </form>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        <?php endif ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Apuntar uno</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('limpieza/objeto') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label class="form-label small">Cabaña</label>
                    <select name="unidad_id" class="form-select form-select-sm mb-2">
                        <option value="">— sin especificar —</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= esc($u['nombre']) ?></option>
                        <?php endforeach ?>
                    </select>

                    <label class="form-label small">Qué es</label>
                    <input type="text" name="descripcion" class="form-control form-control-sm mb-2"
                           maxlength="300" required>

                    <label class="form-label small">Dónde estaba</label>
                    <input type="text" name="donde" class="form-control form-control-sm mb-2" maxlength="150">

                    <label class="form-label small">Foto</label>
                    <input type="file" name="foto" accept="image/*" capture="environment"
                           class="form-control form-control-sm mb-3">

                    <div class="d-grid">
                        <button class="btn btn-primary btn-sm">Apuntar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
