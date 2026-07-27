<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['borrador' => 'secondary', 'enviada' => 'success', 'cancelada' => 'dark'];
$sinPermiso  = max(0, $cuantos - $con_permiso);
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= esc($campana['nombre']) ?></h1>
        <p class="text-muted mb-0">
            <span class="badge text-bg-<?= $colorEstado[$campana['estado']] ?>"><?= ucfirst($campana['estado']) ?></span>
            · <?= esc($descripcion) ?>
        </p>
    </div>
    <a href="<?= site_url('campanas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($plantilla === null): ?>
    <div class="alert alert-danger">
        La plantilla <code><?= esc($campana['plantilla_clave']) ?></code> ya no existe o está apagada.
        Esta campaña no puede mandarse hasta que se arregle.
    </div>
<?php endif ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Cumplen los filtros</div>
                <div class="fs-3 fw-semibold"><?= $cuantos ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Y lo han autorizado</div>
                <div class="fs-3 fw-semibold text-success"><?= $con_permiso ?></div>
                <div class="small text-muted">a estos les llegará</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">No lo han autorizado</div>
                <div class="fs-3 fw-semibold text-secondary"><?= $sinPermiso ?></div>
                <div class="small text-muted">se saltan</div>
            </div>
        </div>
    </div>
</div>

<?php if ($campana['estado'] === 'borrador' && $sinPermiso > 0): ?>
    <?php // Este aviso está aquí para que nadie se lleve la sorpresa después.
          // Un segmento de 300 personas puede ser una campaña de 40, y eso no
          // es un fallo del sistema: es lo que autorizó la gente. ?>
    <div class="alert alert-light border">
        <i class="bi bi-shield-check me-1"></i>
        De los <?= $cuantos ?> que cumplen los filtros, <strong>solo <?= $con_permiso ?></strong> han
        autorizado que se les escriba para esto. A los otros <?= $sinPermiso ?> no se les manda nada,
        y no hay forma de saltárselo: es lo que dijeron.
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-6">
        <?php if ($plantilla !== null): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-envelope me-2"></i>Lo que van a recibir
                </div>
                <div class="card-body">
                    <div class="fw-semibold mb-2"><?= esc($plantilla['asunto']) ?></div>
                    <div class="small" style="white-space: pre-wrap; line-height: 1.6;"><?= esc($plantilla['cuerpo']) ?></div>
                    <div class="form-text mt-2">
                        Las variables se rellenan con los datos de cada uno al salir.
                        <?php if (puede('comunicaciones.plantillas')): ?>
                            <a href="<?= site_url('comunicaciones/plantilla/' . $plantilla['id']) ?>">Editar la plantilla</a>.
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <?php if ($campana['estado'] === 'borrador' && $plantilla !== null && $con_permiso > 0): ?>
            <div class="card border-0 shadow-sm border-start border-4 border-danger">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-send me-2"></i>Mandarla</div>
                <div class="card-body">
                    <p class="small text-muted">
                        Se pondrán <strong><?= $con_permiso ?></strong> mensajes en cola. Saldrán con la
                        próxima pasada, y cada uno vuelve a comprobar el permiso justo antes de salir.
                        <strong>Un correo mandado no se puede recoger.</strong>
                    </p>
                    <form method="post" action="<?= site_url('campanas/enviar/' . $campana['id']) ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small">Para confirmar, escribe <strong>ENVIAR</strong></label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="text" name="confirmar" class="form-control" required
                                   autocomplete="off" placeholder="ENVIAR" style="flex: 2; min-width: 140px;">
                            <button class="btn btn-danger">
                                <i class="bi bi-send me-1"></i>Mandarla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($campana['estado'] === 'enviada'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                    <div class="fw-semibold">Mandada el <?= date('d/m/Y H:i', strtotime($campana['enviada_en'])) ?></div>
                    <p class="text-muted small mb-0 mt-2">
                        <?= (int) $campana['encolados'] ?> en cola,
                        <?= (int) $campana['saltados'] ?> saltados por no tener permiso o correo.
                    </p>
                    <a href="<?= site_url('comunicaciones') ?>" class="btn btn-outline-secondary btn-sm mt-3">
                        <i class="bi bi-list me-1"></i>Ver cómo va la cola
                    </a>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2"></i>Quiénes cumplen hoy
            </div>
            <div class="card-body py-2">
                <p class="small text-muted mb-0">
                    Se recalcula cada vez que se abre esta pantalla. La campaña guarda los filtros,
                    no la lista: así, quien se dé de baja mañana ya no estará el día que se mande.
                </p>
            </div>
            <?php if ($muestra === []): ?>
                <div class="card-body text-muted small border-top">Hoy no cumple nadie.</div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 460px;">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($muestra as $h): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('huespedes/ver/' . $h['id']) ?>" class="text-decoration-none">
                                            <?= esc(trim($h['nombre'] . ' ' . $h['apellidos'])) ?>
                                        </a>
                                        <div class="small text-muted"><?= esc($h['email']) ?: 'sin correo' ?></div>
                                    </td>
                                    <td class="text-end small text-muted">
                                        <?= (int) $h['estancias'] ?> estancia(s)
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
