<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <i class="bi bi-shield-lock text-primary" style="font-size: 2.5rem;"></i>
                </div>

                <h1 class="h4 mb-2">Confirma que eres tú</h1>
                <p class="text-muted">
                    Entraste con tu PIN, y esto que vas a hacer es de lo que mueve dinero o toca
                    datos personales. Escribe tu contraseña y podrás seguir durante
                    <?= (int) $minutos ?> minutos sin que se te vuelva a pedir.
                </p>

                <?= view('partes/errores') ?>

                <form method="post" action="<?= site_url('confirmar/clave') ?>" class="text-start mt-4">
                    <?= csrf_field() ?>
                    <label class="form-label">Tu contraseña</label>
                    <input type="password" name="clave" class="form-control form-control-lg mb-3"
                           autocomplete="current-password" autofocus required>
                    <button class="btn btn-primary w-100 py-2">
                        <i class="bi bi-unlock me-1"></i>Continuar
                    </button>
                </form>

                <a href="<?= site_url('panel') ?>" class="btn btn-link mt-3 text-muted">
                    Volver al panel
                </a>
            </div>
        </div>

        <p class="text-muted small text-center mt-3">
            Si no recuerdas tu contraseña, cierra la sesión y pídele a gerencia que te la cambie.
            El PIN por sí solo no abre esta parte, y es a propósito.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
