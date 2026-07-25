<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion" style="max-width: 760px;">
    <div class="text-center mb-4 reveal visible">
        <p class="etiqueta">Reserva en línea</p>
        <h1 class="titulo-seccion">¿Cuándo quieres venir?</h1>
        <p class="text-muted mt-2">Consulta la disponibilidad real de nuestras cabañas y reserva al momento.</p>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>

    <div class="card tarjeta-tipo">
        <div class="card-body p-4 p-lg-5">
            <form method="post" action="<?= site_url('reservar/disponibilidad') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Llegada</label>
                        <input type="date" name="entrada" class="form-control form-control-lg" required
                               min="<?= date('Y-m-d') ?>" value="<?= esc(old('entrada', '')) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Salida</label>
                        <input type="date" name="salida" class="form-control form-control-lg" required
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= esc(old('salida', '')) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Adultos</label>
                        <select name="adultos" class="form-select form-select-lg">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>" <?= (string) old('adultos', '2') === (string) $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Niños</label>
                        <select name="ninos" class="form-select form-select-lg">
                            <?php for ($i = 0; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>" <?= (string) old('ninos', '0') === (string) $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor ?>
                        </select>
                    </div>
                </div>
                <button class="btn btn-reserva btn-lg w-100 mt-4">
                    <i class="bi bi-search me-1"></i>Ver disponibilidad
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-muted small mt-4 mb-0">
        ¿Prefieres hablar con una persona?
        <a href="https://wa.me/<?= esc($hotel->whatsapp) ?>" target="_blank" rel="noopener">Escríbenos por WhatsApp</a>.
    </p>
</div>

<?= $this->endSection() ?>
