<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-qr-code fs-1 text-muted d-block mb-3"></i>
        <h1 class="h4">No hay ningún equipo con el código <span class="font-monospace"><?= esc($codigo) ?></span></h1>
        <p class="text-muted">
            O la etiqueta es de otro sitio, o el equipo se dio de baja y alguien no la despegó.
        </p>
        <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
            <a href="<?= site_url('activos') ?>" class="btn btn-primary">
                <i class="bi bi-list me-1"></i>Ver todos los equipos
            </a>
            <?php if (puede('mantenimiento.crear')): ?>
                <a href="<?= site_url('mantenimiento') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-exclamation-triangle me-1"></i>Reportar una avería sin equipo
                </a>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
