<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Libraries\Planes;

$config = new \App\Models\ConfiguracionModel();
?>

<div class="mb-4">
    <h1 class="h3 mb-1">Planes incluidos</h1>
    <p class="text-muted mb-0">
        Qué lleva incluido el precio de la noche. Si la tarifa lleva desayuno y el
        sistema no lo sabe, o se cobra dos veces o alguien lo descuenta a mano
        cada mañana.
    </p>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($planes === []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-basket fs-2 d-block mb-2 text-muted opacity-50"></i>
                    <p class="text-muted mb-0">
                        Todavía no hay planes. Crea uno si alguna tarifa incluye algo:
                        desayuno, media pensión, una botella de bienvenida…
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($planes as $p): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="h5 mb-1">
                                    <?= esc($p['nombre']) ?>
                                    <?php if (! $p['activo']): ?>
                                        <span class="badge text-bg-secondary ms-1">Inactivo</span>
                                    <?php endif ?>
                                </h2>
                                <p class="small text-muted mb-2"><?= esc($p['descripcion'] ?? '') ?></p>
                                <div class="small text-muted">
                                    <span class="badge text-bg-light border"><?= (int) $p['lineas'] ?> cosa(s) incluidas</span>
                                    <span class="badge text-bg-light border"><?= (int) $p['tipos'] ?> tipo(s) de alojamiento</span>
                                    <?php if ((int) $p['reservas'] > 0): ?>
                                        <span class="badge text-bg-info"><?= (int) $p['reservas'] ?> estancia(s) en curso</span>
                                    <?php endif ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= site_url('planes/ver/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-sliders me-1"></i>Configurar
                                </a>
                                <?php if ((int) $p['reservas'] === 0 && (int) $p['tipos'] === 0): ?>
                                    <form method="post" action="<?= site_url('planes/eliminar/' . $p['id']) ?>"
                                          onsubmit="return confirm('¿Eliminar el plan «<?= esc($p['nombre'], 'js') ?>»?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-lg me-2"></i>Nuevo plan</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('planes/guardar') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="text" name="nombre" class="form-control" required placeholder="Con desayuno">
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="descripcion" class="form-control" placeholder="Incluye desayuno para todos los huéspedes">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary">Crear</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock me-2 text-primary"></i>Horarios de la carta
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    A qué hora es cada cosa. Sirve para dos cosas: que la carta enseñe
                    lo que toca a cada hora, y que <strong>un desayuno incluido no se pueda
                    gastar en la cena</strong>.
                </p>
                <form method="post" action="<?= site_url('planes/franjas') ?>">
                    <?= csrf_field() ?>
                    <?php foreach (Planes::FRANJAS as $clave => $nombre): ?>
                        <?php
                        $rango = (string) $config->obtener('carta_franja_' . $clave, '');
                        [$desde, $hasta] = str_contains($rango, '-') ? explode('-', $rango, 2) : ['', ''];
                        ?>
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-4"><label class="form-label mb-0 small fw-semibold"><?= esc($nombre) ?></label></div>
                            <div class="col-4">
                                <input type="time" name="desde_<?= $clave ?>" class="form-control form-control-sm"
                                       value="<?= esc(trim($desde)) ?>">
                            </div>
                            <div class="col-4">
                                <input type="time" name="hasta_<?= $clave ?>" class="form-control form-control-sm"
                                       value="<?= esc(trim($hasta)) ?>">
                            </div>
                        </div>
                    <?php endforeach ?>
                    <div class="form-text mb-3">
                        Deja las dos horas en blanco para desactivar una franja.
                    </div>
                    <button class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar horarios
                    </button>
                </form>
            </div>
        </div>

        <div class="alert alert-light border small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            El plan se cuelga del <a href="<?= site_url('tipos') ?>" class="text-decoration-none">tipo de alojamiento</a>,
            porque forma parte de la tarifa. En una reserva concreta se puede cambiar,
            para el día que se negocia algo distinto.
        </div>
    </div>
</div>

<?= $this->endSection() ?>
