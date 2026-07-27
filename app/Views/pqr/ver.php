<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorTipo = [
    'peticion' => 'info', 'queja' => 'warning', 'reclamo' => 'danger',
    'sugerencia' => 'secondary', 'felicitacion' => 'success',
];
$colorEstado = [
    'recibida' => 'danger', 'en_gestion' => 'warning', 'respondida' => 'info',
    'cerrada' => 'success', 'reabierta' => 'danger',
];
$iconoNota = [
    'nota' => 'sticky', 'estado' => 'arrow-repeat', 'asignacion' => 'person-check',
    'contacto' => 'telephone', 'respuesta' => 'reply',
];
$abierta = in_array($p['estado'], ['recibida', 'en_gestion', 'reabierta'], true);
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="font-monospace text-muted small"><?= esc($p['codigo']) ?></div>
        <h1 class="h3 mb-1"><?= esc($p['asunto']) ?></h1>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge text-bg-<?= $colorTipo[$p['tipo']] ?>"><?= esc($tipos[$p['tipo']]) ?></span>
            <span class="badge text-bg-<?= $colorEstado[$p['estado']] ?>"><?= esc($estados[$p['estado']]) ?></span>
            <span class="text-muted small">
                <?= esc($canales[$p['canal_entrada']]) ?>
                · <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
            </span>
        </div>
    </div>
    <a href="<?= site_url('pqr') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($abierta && $dias_restantes !== null): ?>
    <?php if ($dias_restantes < 0): ?>
        <div class="alert alert-danger">
            <strong><i class="bi bi-exclamation-octagon me-1"></i>Fuera del plazo legal</strong>
            desde hace <?= abs($dias_restantes) ?> día(s) hábil(es).
            Contestar ahora no lo arregla, pero dejarla más tiempo lo empeora.
        </div>
    <?php elseif ($dias_restantes <= 3): ?>
        <div class="alert alert-warning">
            <strong><i class="bi bi-clock me-1"></i>Quedan <?= $dias_restantes ?> día(s) hábil(es)</strong>
            para contestar, hasta el <?= date('d/m/Y', strtotime($p['vence_en'])) ?>.
        </div>
    <?php else: ?>
        <div class="alert alert-light border small">
            <i class="bi bi-calendar-check me-1"></i>
            Hay que contestar antes del <strong><?= date('d/m/Y', strtotime($p['vence_en'])) ?></strong>
            — quedan <?= $dias_restantes ?> días hábiles.
        </div>
    <?php endif ?>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-left-text me-2"></i>Qué pasó</div>
            <div class="card-body">
                <div style="white-space: pre-wrap; line-height: 1.6;"><?= esc($p['detalle']) ?></div>
            </div>
        </div>

        <?php if ($p['respuesta']): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-reply me-2"></i>Lo que se le contestó</div>
                <div class="card-body">
                    <div style="white-space: pre-wrap; line-height: 1.6;"><?= esc($p['respuesta']) ?></div>
                    <div class="small text-muted mt-2">
                        <?= date('d/m/Y H:i', strtotime($p['respondida_en'])) ?>
                        <?php if ($p['causa']): ?>
                            · causa apuntada: <strong><?= esc($p['causa']) ?></strong>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <!-- ═══ Rastro ═══ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-ul me-2"></i>Qué se ha hecho
                <span class="badge text-bg-secondary ms-1"><?= count($notas) ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($notas as $n): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <div>
                                <i class="bi bi-<?= $iconoNota[$n['tipo']] ?? 'sticky' ?> me-1 text-muted"></i>
                                <?= esc($n['texto']) ?>
                            </div>
                            <div class="small text-muted text-nowrap">
                                <?= esc($n['usuario_nombre']) ?: 'sistema' ?>
                                · <?= date('d/m H:i', strtotime($n['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>

            <?php if (puede('pqr.gestionar')): ?>
                <div class="card-body border-top">
                    <form method="post" action="<?= site_url('pqr/nota/' . $p['id']) ?>" class="d-flex gap-2 flex-wrap">
                        <?= csrf_field() ?>
                        <select name="tipo" class="form-select form-select-sm" style="flex: 1; min-width: 130px;">
                            <?php foreach ($tipos_nota as $t => $etiqueta): ?>
                                <?php if ($t === 'respuesta') { continue; } ?>
                                <option value="<?= $t ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                        <input type="text" name="texto" class="form-control form-control-sm" required maxlength="500"
                               placeholder="Se llamó al huésped, quedó en…" style="flex: 3; min-width: 180px;">
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i></button>
                    </form>
                    <div class="form-text">
                        Esto no se puede borrar después. Es lo que vale el día que una queja
                        deja de serlo y se convierte en una reclamación.
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2"></i>Quién</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted fw-normal">Nombre</dt>
                    <dd class="col-8">
                        <?php if ($p['huesped_id']): ?>
                            <a href="<?= site_url('huespedes/ver/' . $p['huesped_id']) ?>">
                                <?= esc(trim(($p['huesped_nombre'] ?? '') . ' ' . ($p['huesped_apellidos'] ?? ''))) ?>
                            </a>
                        <?php else: ?>
                            <?= esc($p['nombre_contacto']) ?: '—' ?>
                        <?php endif ?>
                    </dd>
                    <dt class="col-4 text-muted fw-normal">Correo</dt>
                    <dd class="col-8"><?= esc($p['email_contacto']) ?: '—' ?></dd>
                    <dt class="col-4 text-muted fw-normal">Teléfono</dt>
                    <dd class="col-8"><?= esc($p['telefono_contacto']) ?: '—' ?></dd>
                    <?php if ($p['reserva_id']): ?>
                        <dt class="col-4 text-muted fw-normal">Reserva</dt>
                        <dd class="col-8">
                            <a href="<?= site_url('reservas/ver/' . $p['reserva_id']) ?>"><?= esc($p['reserva_codigo']) ?></a>
                        </dd>
                    <?php endif ?>
                    <dt class="col-4 text-muted fw-normal">A cargo</dt>
                    <dd class="col-8"><?= esc($p['responsable_nombre']) ?: '<span class="text-warning">sin repartir</span>' ?></dd>
                    <?php if ($p['satisfaccion'] !== null): ?>
                        <dt class="col-4 text-muted fw-normal">Le pareció</dt>
                        <dd class="col-8">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= (int) $p['satisfaccion'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                            <?php endfor ?>
                        </dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <?php if (puede('pqr.gestionar') && $abierta): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-check me-2"></i>Repartir</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('pqr/asignar/' . $p['id']) ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <select name="responsable_id" class="form-select form-select-sm">
                            <option value="">Sin repartir</option>
                            <?php foreach ($responsables as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= (int) $p['responsable_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                                    <?= esc($r['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i></button>
                    </form>
                </div>
            </div>
        <?php endif ?>

        <?php if (puede('pqr.responder')): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-reply-all me-2"></i>Cerrarla</div>
                <div class="card-body d-grid gap-3">
                    <?php if ($abierta): ?>
                        <form method="post" action="<?= site_url('pqr/responder/' . $p['id']) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label">Qué se le contestó</label>
                            <textarea name="respuesta" class="form-control mb-2" rows="4" required
                                      placeholder="La respuesta que se le dio, tal cual."></textarea>
                            <label class="form-label small text-muted">Qué lo causó</label>
                            <input type="text" name="causa" class="form-control form-control-sm mb-2" maxlength="60"
                                   placeholder="Calentador averiado, error de reserva, ruido…">
                            <div class="form-text mb-2">
                                Sirve para contar cuántas veces se repite lo mismo. Es lo único
                                que convierte las quejas en algo que se puede arreglar.
                            </div>
                            <button class="btn btn-success w-100"><i class="bi bi-send me-1"></i>Marcar respondida</button>
                        </form>
                    <?php elseif ($p['estado'] === 'respondida'): ?>
                        <form method="post" action="<?= site_url('pqr/cerrar/' . $p['id']) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label">¿Qué le pareció la respuesta?</label>
                            <select name="satisfaccion" class="form-select mb-2">
                                <option value="">No se sabe</option>
                                <?php foreach ([5 => 'Muy conforme', 4 => 'Conforme', 3 => 'Regular', 2 => 'Poco conforme', 1 => 'Nada conforme'] as $v => $e): ?>
                                    <option value="<?= $v ?>"><?= $e ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text mb-2">
                                Es lo único que dice si el problema se resolvió o solo se contestó.
                            </div>
                            <button class="btn btn-primary w-100"><i class="bi bi-check2-all me-1"></i>Cerrarla</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center text-success py-2">
                            <i class="bi bi-check2-all fs-2 d-block mb-1"></i>Cerrada.
                        </div>
                    <?php endif ?>

                    <?php if (in_array($p['estado'], ['respondida', 'cerrada'], true)): ?>
                        <form method="post" action="<?= site_url('pqr/reabrir/' . $p['id']) ?>" class="border-top pt-3">
                            <?= csrf_field() ?>
                            <label class="form-label small text-muted">Volvió a escribir por lo mismo</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <input type="text" name="motivo" class="form-control form-control-sm"
                                       placeholder="Por qué se reabre" style="flex: 2; min-width: 140px;">
                                <button class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                            <div class="form-text">Empieza un plazo nuevo desde hoy.</div>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
