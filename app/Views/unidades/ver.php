<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\MantenimientoModel;

$rol      = session()->get('usuario_rol');
$esGestor = in_array($rol, ['gerencia', 'recepcion'], true);

$colores   = ['disponible' => 'success', 'ocupada' => 'danger', 'limpieza' => 'warning', 'bloqueada' => 'secondary'];
$etiquetas = ['disponible' => 'Disponible', 'ocupada' => 'Ocupada', 'limpieza' => 'En limpieza', 'bloqueada' => 'Bloqueada'];

$ultimaRevision = $revisiones[0] ?? null;

// Servicios agrupados para pintarlos ordenados
$porGrupo = [];
foreach ($servicios as $s) {
    $porGrupo[$s['grupo']][] = $s;
}

// Inventario solo con lo que debe haber
$conCantidad = array_values(array_filter($inventario, static fn ($i) => (int) $i['cantidad'] > 0));
$totalPiezas = array_sum(array_map(static fn ($i) => (int) $i['cantidad'], $conCantidad));
?>

<div class="mb-4">
    <a href="<?= site_url('unidades') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Cabañas
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1">
                <?= esc($unidad['nombre']) ?>
                <span class="badge text-bg-<?= $colores[$unidad['estado']] ?? 'secondary' ?> fs-6 align-middle ms-1">
                    <?= esc($etiquetas[$unidad['estado']] ?? $unidad['estado']) ?>
                </span>
            </h1>
            <p class="text-muted mb-0">
                <?= esc($unidad['tipo_nombre']) ?> · hasta <?= (int) $unidad['capacidad'] ?> personas
                <?php if (trim((string) $unidad['ubicacion']) !== ''): ?>
                    · <i class="bi bi-geo-alt"></i> <?= esc($unidad['ubicacion']) ?>
                <?php endif ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= site_url('unidades/revisar/' . $unidad['id']) ?>" class="btn btn-primary">
                <i class="bi bi-clipboard-check me-1"></i>Revisar inventario
            </a>
            <?php if ($esGestor): ?>
                <a href="<?= site_url('unidades/editar/' . $unidad['id']) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
            <?php endif ?>
        </div>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if (! empty($mantenimientos)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-wrench-adjustable me-1"></i>
        <strong><?= count($mantenimientos) ?> incidencia<?= count($mantenimientos) > 1 ? 's abiertas' : ' abierta' ?></strong>
        en esta cabaña:
        <?= esc(implode(' · ', array_column($mantenimientos, 'titulo'))) ?>.
        <a href="<?= site_url('mantenimiento') ?>">Ver mantenimiento</a>
    </div>
<?php endif ?>

<div class="row g-4">
    <!-- ══ Columna izquierda ══ -->
    <div class="col-lg-5">
        <!-- Estado y datos -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-house-heart me-2"></i>La cabaña</div>
            <div class="card-body">
                <?php if (trim((string) $unidad['descripcion']) !== ''): ?>
                    <p class="mb-3"><?= esc($unidad['descripcion']) ?></p>
                <?php endif ?>

                <div class="row text-center g-2 mb-3">
                    <div class="col-4">
                        <div class="text-muted small">Enseres</div>
                        <div class="fs-5 fw-semibold"><?= $totalPiezas ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Servicios</div>
                        <div class="fs-5 fw-semibold"><?= count($servicios) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Reservas</div>
                        <div class="fs-5 fw-semibold"><?= count($reservas) ?></div>
                    </div>
                </div>

                <?php if ($ultimaRevision !== null): ?>
                    <div class="alert alert-<?= $ultimaRevision['estado'] === 'ok' ? 'success' : 'warning' ?> mb-0 py-2 small">
                        <i class="bi bi-clipboard-check me-1"></i>
                        Última revisión el <?= date('d/m/Y', strtotime($ultimaRevision['created_at'])) ?>:
                        <?= $ultimaRevision['estado'] === 'ok'
                            ? 'todo completo.'
                            : (int) $ultimaRevision['faltantes'] . ' faltante(s) y ' . (int) $ultimaRevision['danados'] . ' dañado(s).' ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light mb-0 py-2 small">
                        <i class="bi bi-info-circle me-1"></i>Esta cabaña nunca se ha revisado.
                    </div>
                <?php endif ?>

                <?php if (trim((string) $unidad['notas']) !== ''): ?>
                    <hr>
                    <p class="small text-muted mb-0"><i class="bi bi-sticky me-1"></i><?= esc($unidad['notas']) ?></p>
                <?php endif ?>
            </div>
        </div>

        <!-- Próximas reservas -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-check me-2"></i>Próximas reservas</div>
            <?php if ($reservas === []): ?>
                <div class="card-body text-muted small text-center py-4">Sin reservas próximas.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($reservas as $r): ?>
                        <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <span class="fw-semibold"><?= esc($r['nombre']) ?> <?= esc($r['apellidos']) ?></span>
                                    <div class="text-muted small"><?= esc($r['codigo']) ?></div>
                                </div>
                                <div class="text-end text-muted small">
                                    <?= date('d/m', strtotime($r['fecha_entrada'])) ?> → <?= date('d/m', strtotime($r['fecha_salida'])) ?>
                                    <div><span class="badge text-bg-light text-muted"><?= esc($r['estado']) ?></span></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <!-- Últimas limpiezas -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bucket me-2"></i>Últimas limpiezas</div>
            <?php if ($limpiezas === []): ?>
                <div class="card-body text-muted small text-center py-4">Todavía no hay aseos registrados.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($limpiezas as $l): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <span class="small">
                                <?= date('d/m/Y H:i', strtotime($l['inicio'])) ?>
                                <span class="text-muted">· <?= esc($l['usuario_nombre'] ?? 'Sin asignar') ?></span>
                            </span>
                            <span class="badge text-bg-<?= $l['fin'] !== null ? 'success' : 'warning' ?>">
                                <?= $l['fin'] !== null ? 'Terminada' : 'En curso' ?>
                            </span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>

    <!-- ══ Columna derecha ══ -->
    <div class="col-lg-7">
        <!-- Servicios -->
        <div class="card border-0 shadow-sm mb-4" id="servicios">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-stars me-2"></i>Servicios y equipamiento</span>
                <?php if ($esGestor): ?>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editarServicios">
                        <i class="bi bi-pencil me-1"></i>Cambiar
                    </button>
                <?php endif ?>
            </div>
            <div class="card-body">
                <?php if ($servicios === []): ?>
                    <p class="text-muted small mb-0">
                        Esta cabaña no tiene servicios marcados. Se definen en
                        <a href="<?= site_url('tipos/ficha/' . $unidad['tipo_id']) ?>">su tipo de alojamiento</a>
                        y aquí solo se anotan las diferencias.
                    </p>
                <?php else: ?>
                    <?php foreach ($porGrupo as $grupo => $lista): ?>
                        <div class="mb-2">
                            <div class="text-muted" style="font-size: .72rem; text-transform: uppercase; letter-spacing: .07em;">
                                <?= esc($grupo) ?>
                            </div>
                            <div class="d-flex gap-2 flex-wrap mt-1">
                                <?php foreach ($lista as $s): ?>
                                    <span class="chip-servicio">
                                        <i class="bi <?= esc($s['icono']) ?>"></i><?= esc($s['nombre']) ?>
                                    </span>
                                <?php endforeach ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>

                <?php if ($esGestor): ?>
                    <div class="collapse mt-3" id="editarServicios">
                        <form method="post" action="<?= site_url('unidades/servicios/' . $unidad['id']) ?>" class="border-top pt-3">
                            <?= csrf_field() ?>
                            <p class="form-text mb-3">
                                Lo marcado por defecto es lo que trae su tipo. Solo se guarda lo que cambies:
                                si mañana añades un servicio al tipo, esta cabaña lo hereda sola.
                            </p>
                            <?php foreach ($catalogo as $grupo => $lista): ?>
                                <div class="mb-3">
                                    <div class="fw-semibold small mb-1"><?= esc($grupo) ?></div>
                                    <div class="row g-1">
                                        <?php foreach ($lista as $s): ?>
                                            <?php
                                            $enTipo    = in_array((int) $s['id'], array_map('intval', $delTipo), true);
                                            $excepcion = $excepciones[$s['id']] ?? null;
                                            $marcado   = $excepcion === 'si' || ($excepcion === null && $enTipo);
                                            ?>
                                            <div class="col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="servicio[]"
                                                           value="<?= $s['id'] ?>" id="sv<?= $s['id'] ?>" <?= $marcado ? 'checked' : '' ?>>
                                                    <label class="form-check-label small" for="sv<?= $s['id'] ?>">
                                                        <i class="bi <?= esc($s['icono']) ?> me-1 text-muted"></i><?= esc($s['nombre']) ?>
                                                        <?php if ($excepcion !== null): ?>
                                                            <span class="badge text-bg-warning ms-1">distinto</span>
                                                        <?php endif ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach ?>
                                    </div>
                                </div>
                            <?php endforeach ?>
                            <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar servicios</button>
                        </form>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <!-- Inventario -->
        <div class="card border-0 shadow-sm mb-4" id="inventario">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold"><i class="bi bi-box-seam me-2"></i>Inventario de enseres</span>
                <div class="d-flex gap-2">
                    <a href="<?= site_url('unidades/revisar/' . $unidad['id']) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-clipboard-check me-1"></i>Revisar
                    </a>
                    <?php if ($esGestor): ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editarInventario">
                            <i class="bi bi-pencil me-1"></i>Cantidades
                        </button>
                    <?php endif ?>
                </div>
            </div>

            <?php if ($conCantidad === []): ?>
                <div class="card-body text-muted small text-center py-4">
                    No hay enseres asignados. Márcalos con el botón «Cantidades», o crea artículos nuevos en
                    <a href="<?= site_url('inventario-cabanas') ?>">el catálogo</a>.
                </div>
            <?php else: ?>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($conCantidad as $i): ?>
                            <span class="chip-enser">
                                <strong><?= (int) $i['cantidad'] ?></strong> <?= esc($i['nombre']) ?>
                            </span>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endif ?>

            <?php if ($esGestor): ?>
                <div class="collapse" id="editarInventario">
                    <form method="post" action="<?= site_url('unidades/inventario/' . $unidad['id']) ?>" class="card-body border-top">
                        <?= csrf_field() ?>
                        <p class="form-text mb-3">Pon 0 en lo que no haya en esta cabaña.</p>
                        <div class="row g-2">
                            <?php foreach ($inventario as $i): ?>
                                <div class="col-sm-6 col-xl-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text flex-fill justify-content-start" style="min-width: 0;">
                                            <span class="text-truncate"><?= esc($i['nombre']) ?></span>
                                        </span>
                                        <input type="number" name="cantidad[<?= $i['id'] ?>]" class="form-control"
                                               min="0" max="99" style="max-width: 74px;" value="<?= (int) $i['cantidad'] ?>">
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar cantidades</button>
                            <a href="<?= site_url('inventario-cabanas') ?>" class="btn btn-outline-secondary btn-sm">Catálogo de artículos</a>
                        </div>
                    </form>
                    <form method="post" action="<?= site_url('unidades/inventario/copiar/' . $unidad['id']) ?>"
                          class="card-body border-top pt-3"
                          onsubmit="return confirm('¿Copiar este inventario a todas las demás cabañas del mismo tipo? Se reemplaza el suyo.');">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-copy me-1"></i>Copiar este inventario a las demás cabañas
                        </button>
                    </form>
                </div>
            <?php endif ?>

            <?php if ($revisiones !== []): ?>
                <div class="card-body border-top">
                    <h3 class="h6 text-muted mb-2">Revisiones recientes</h3>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <?php foreach (array_slice($revisiones, 0, 6) as $rev): ?>
                                    <tr>
                                        <td class="ps-0 small text-muted"><?= date('d/m/Y H:i', strtotime($rev['created_at'])) ?></td>
                                        <td class="small"><?= esc($rev['usuario_nombre'] ?? '—') ?></td>
                                        <td class="small">
                                            <?php if ($rev['estado'] === 'ok'): ?>
                                                <span class="badge text-bg-success">Todo completo</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-warning">
                                                    <?= (int) $rev['faltantes'] ?> faltan · <?= (int) $rev['danados'] ?> dañados
                                                </span>
                                            <?php endif ?>
                                        </td>
                                        <td class="pe-0 small text-muted text-end"><?= esc($rev['reserva_codigo'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <!-- Fotos internas -->
        <div class="card border-0 shadow-sm" id="fotos">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-camera me-2"></i>Fotos internas
            </div>
            <div class="card-body">
                <p class="form-text mb-3">
                    <i class="bi bi-shield-lock me-1"></i>
                    Estas fotos <strong>no salen en la web</strong>: son de uso interno (cómo debe quedar la cabaña,
                    desperfectos…). Solo las ve el personal con sesión iniciada.
                    Las fotos que se anuncian están en <a href="<?= site_url('tipos/ficha/' . $unidad['tipo_id']) ?>">el tipo de alojamiento</a>.
                </p>

                <?php if ($fotos === []): ?>
                    <p class="text-muted small">Todavía no hay fotos de esta cabaña.</p>
                <?php else: ?>
                    <div class="galeria-interna mb-3">
                        <?php foreach ($fotos as $f): ?>
                            <figure class="foto">
                                <a href="<?= site_url('unidades/foto/' . $f['id']) ?>" target="_blank" rel="noopener">
                                    <img src="<?= site_url('unidades/foto/' . $f['id']) ?>" alt="<?= esc($f['alt'] ?? 'Foto de ' . $unidad['nombre']) ?>" loading="lazy">
                                </a>
                                <figcaption>
                                    <span class="text-truncate"><?= esc($f['alt'] ?? '') ?></span>
                                    <form method="post" action="<?= site_url('unidades/foto/eliminar/' . $f['id']) ?>"
                                          onsubmit="return confirm('¿Eliminar esta foto?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-link text-danger p-0" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </figcaption>
                            </figure>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <form method="post" action="<?= site_url('unidades/foto/' . $unidad['id']) ?>" enctype="multipart/form-data"
                      class="row g-2 align-items-end border-top pt-3">
                    <?= csrf_field() ?>
                    <div class="col-sm-5">
                        <label class="form-label small mb-1">Foto</label>
                        <input type="file" name="foto" class="form-control form-control-sm" accept="image/*" capture="environment" required>
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label small mb-1">Qué se ve</label>
                        <input type="text" name="alt" class="form-control form-control-sm" maxlength="200"
                               placeholder="Terraza tras el aseo, gotera en el techo…">
                    </div>
                    <div class="col-sm-2">
                        <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-upload"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .chip-servicio {
        display: inline-flex; align-items: center; gap: .35rem;
        background: var(--panel-tenue); border: 1px solid var(--borde);
        border-radius: 99px; padding: .25rem .7rem; font-size: .84rem; color: var(--tinta-media);
    }
    .chip-servicio i { color: var(--bosque-claro); }
    .chip-enser {
        display: inline-flex; align-items: center; gap: .3rem;
        background: #fff; border: 1px solid var(--borde); border-radius: var(--radio-sm);
        padding: .2rem .6rem; font-size: .84rem; color: var(--tinta-media);
    }
    .chip-enser strong { color: var(--bosque); }
    .galeria-interna { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .6rem; }
    .galeria-interna .foto { margin: 0; border: 1px solid var(--borde); border-radius: var(--radio-sm); overflow: hidden; background: var(--panel-tenue); }
    .galeria-interna img { width: 100%; height: 105px; object-fit: cover; display: block; }
    .galeria-interna figcaption {
        display: flex; justify-content: space-between; align-items: center; gap: .3rem;
        padding: .3rem .5rem; font-size: .74rem; color: var(--tinta-suave);
    }
</style>

<?= $this->endSection() ?>
