<?= $this->extend('layouts/empleado') ?>
<?= $this->section('contenido') ?>

<div class="cabecera">
    <div class="saludo"><?= esc($hotel->nombre) ?></div>
    <h1>Mi jornada</h1>
</div>

<main>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="aviso error"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>
    <?php if (session()->getFlashdata('ok')): ?>
        <div class="aviso ok"><?= esc(session()->getFlashdata('ok')) ?></div>
    <?php endif ?>

    <form method="post" action="<?= site_url('empleado/entrar') ?>" class="tarjeta">
        <?= csrf_field() ?>
        <h2>Entra con tus datos</h2>

        <div class="campo">
            <label for="documento">Número de documento</label>
            <input type="text" id="documento" name="documento" inputmode="numeric" autocomplete="username"
                   required maxlength="50" placeholder="1023456789">
        </div>

        <div class="campo">
            <label for="pin">PIN de fichaje</label>
            <input type="password" id="pin" name="pin" inputmode="numeric" autocomplete="current-password"
                   required maxlength="<?= \App\Models\EmpleadoModel::LONGITUD_PIN ?>"
                   pattern="\d{<?= \App\Models\EmpleadoModel::LONGITUD_PIN ?>}"
                   placeholder="••••">
            <div class="pista">El mismo que usas en el terminal del hotel.</div>
        </div>

        <button class="btn"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
    </form>

    <div class="aviso info">
        <i class="bi bi-key me-1"></i>
        ¿No tienes PIN o lo olvidaste? Pídeselo a gerencia: por seguridad no se puede recuperar,
        se genera uno nuevo.
    </div>
</main>

<p class="pie">
    <?= esc($hotel->nombre) ?> · Control de jornada
</p>

<?= $this->endSection() ?>
