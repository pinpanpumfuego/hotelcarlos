<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Libraries\Permisos\Catalogo;

$creando  = $rol === null;
$esTotal  = ! $creando && $rol['clave'] === Catalogo::ROL_TOTAL;
$marcados = array_flip($marcados);
$accion   = $creando ? site_url('roles/guardar') : site_url('roles/actualizar/' . $rol['id']);
?>

<div class="mb-4">
    <a href="<?= site_url('roles') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Perfiles
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $creando ? 'Nuevo perfil' : esc($rol['nombre']) ?></h1>
    <p class="text-muted mb-0 mt-1">
        Marca lo que este puesto necesita hacer. Lo que no marques, ni siquiera
        aparecerá en su menú.
    </p>
</div>

<?= view('partes/errores') ?>

<?php if ($esTotal): ?>
    <div class="alert alert-dark d-flex gap-2">
        <i class="bi bi-shield-lock-fill mt-1"></i>
        <div>
            <strong>Gerencia tiene todos los permisos y no se puede modificar.</strong>
            <div class="small mt-1">
                Es la salida de emergencia: si un día otro perfil se configura mal,
                esta es la cuenta que sigue pudiendo entrar a arreglarlo. Se enseña
                aquí para consulta.
            </div>
        </div>
    </div>
<?php endif ?>

<?php if (! $creando && ! empty($usuarios)): ?>
    <div class="alert alert-info d-flex gap-2">
        <i class="bi bi-people-fill mt-1"></i>
        <div>
            <strong>Lo que cambies aquí afecta ya a <?= count($usuarios) ?> persona<?= count($usuarios) === 1 ? '' : 's' ?>:</strong>
            <div class="small mt-1">
                <?php foreach ($usuarios as $u): ?>
                    <span class="badge text-bg-light border me-1">
                        <?= esc($u['nombre']) ?><?= (int) $u['activo'] === 0 ? ' (inactiva)' : '' ?>
                    </span>
                <?php endforeach ?>
            </div>
            <div class="small mt-2 text-muted">
                No hace falta que vuelvan a entrar: el cambio surte efecto en su siguiente clic.
            </div>
        </div>
    </div>
<?php endif ?>

<form method="post" action="<?= $accion ?>">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Nombre del perfil <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required
                           <?= $esTotal ? 'disabled' : '' ?>
                           placeholder="Recepción de fin de semana"
                           value="<?= esc(old('nombre', $rol['nombre'] ?? '')) ?>">
                </div>
                <div class="col-md-7">
                    <label class="form-label fw-semibold">Para qué es</label>
                    <input type="text" name="descripcion" class="form-control"
                           <?= $esTotal ? 'disabled' : '' ?>
                           placeholder="Cubre el mostrador sábados y domingos"
                           value="<?= esc(old('descripcion', $rol['descripcion'] ?? '')) ?>">
                </div>
            </div>

            <?php if ($creando): ?>
                <hr class="my-4">
                <label class="form-label fw-semibold">Partir de un perfil que ya existe</label>
                <select name="plantilla" class="form-select" style="max-width: 420px;">
                    <option value="">Empezar en blanco</option>
                    <?php foreach ($plantillas as $clave => $def): ?>
                        <?php if ($clave === Catalogo::ROL_TOTAL) { continue; } ?>
                        <option value="<?= esc($clave) ?>"><?= esc($def['nombre']) ?></option>
                    <?php endforeach ?>
                </select>
                <div class="form-text">
                    Copia sus permisos como punto de partida. Después los ajustas a mano.
                </div>
            <?php endif ?>
        </div>
    </div>

    <?php if (! $creando): ?>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="h5 mb-0">Qué puede hacer</h2>
            <?php if (! $esTotal): ?>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" data-marcar="todo">Marcar todo</button>
                    <button type="button" class="btn btn-outline-secondary" data-marcar="nada">Desmarcar todo</button>
                </div>
            <?php endif ?>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ($modulos as $modulo => $permisos): ?>
                <?php if ($permisos === []) { continue; } ?>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                            <span><?= esc(Catalogo::MODULOS[$modulo]) ?></span>
                            <?php if (! $esTotal): ?>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0"
                                        data-modulo="<?= esc($modulo) ?>">Todo el bloque</button>
                            <?php endif ?>
                        </div>
                        <div class="card-body py-2">
                            <?php foreach ($permisos as $clave => $datos): ?>
                                <div class="form-check py-1">
                                    <input class="form-check-input perm perm-<?= esc($modulo) ?>" type="checkbox"
                                           name="permisos[]" value="<?= esc($clave) ?>"
                                           id="p<?= esc(str_replace('.', '_', $clave)) ?>"
                                           <?= isset($marcados[$clave]) ? 'checked' : '' ?>
                                           <?= $esTotal ? 'disabled' : '' ?>>
                                    <label class="form-check-label d-flex align-items-start gap-2"
                                           for="p<?= esc(str_replace('.', '_', $clave)) ?>">
                                        <span>
                                            <?= esc($datos['nombre']) ?>
                                            <?php if ($datos['sensible']): ?>
                                                <i class="bi bi-exclamation-triangle-fill text-warning ms-1"
                                                   title="Mueve dinero, toca datos personales o cambia la configuración"></i>
                                            <?php endif ?>
                                            <code class="d-block small text-muted"><?= esc($clave) ?></code>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <div class="alert alert-light border small">
            <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
            Los permisos marcados con el triángulo mueven dinero, tocan datos
            personales o cambian la configuración. Dáselos al menor número de
            personas posible.
        </div>
    <?php endif ?>

    <div class="d-flex gap-2">
        <?php if (! $esTotal): ?>
            <button class="btn btn-primary btn-lg">
                <i class="bi bi-check-lg me-1"></i><?= $creando ? 'Crear perfil' : 'Guardar permisos' ?>
            </button>
        <?php endif ?>
        <a href="<?= site_url('roles') ?>" class="btn btn-outline-secondary btn-lg">
            <?= $esTotal ? 'Volver' : 'Cancelar' ?>
        </a>
    </div>
</form>

<?php if (! $esTotal && ! $creando): ?>
<script>
// Marcar y desmarcar en bloque. Es puro ahorro de clics: con 82 casillas,
// configurar un perfil a mano desde cero desanima a cualquiera.
document.querySelectorAll('[data-marcar]').forEach(function (boton) {
    boton.addEventListener('click', function () {
        const valor = boton.dataset.marcar === 'todo';
        document.querySelectorAll('.perm').forEach(function (c) { c.checked = valor; });
    });
});

document.querySelectorAll('[data-modulo]').forEach(function (boton) {
    boton.addEventListener('click', function () {
        const casillas = document.querySelectorAll('.perm-' + boton.dataset.modulo);
        // Si ya estaban todas marcadas, el botón desmarca: así el mismo botón
        // sirve para las dos cosas y no hacen falta dos.
        const todas = Array.from(casillas).every(function (c) { return c.checked; });
        casillas.forEach(function (c) { c.checked = ! todas; });
    });
});
</script>
<?php endif ?>

<?= $this->endSection() ?>
