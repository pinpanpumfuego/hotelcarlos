<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion" style="max-width: 760px;">
    <div class="text-center mb-4 reveal visible">
        <p class="etiqueta"><?= esc(lang('Web.reservaEnLinea')) ?></p>
        <h1 class="titulo-seccion"><?= esc(lang('Web.cuandoVenir')) ?></h1>
        <p class="text-muted mt-2"><?= esc(lang('Web.reservaIntro')) ?></p>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>

    <div class="card tarjeta-tipo">
        <div class="card-body p-4 p-lg-5">
            <form method="post" action="<?= url_web('reservar/disponibilidad') ?>">
                <?= csrf_field() ?>
                <?php // Calendario propio: el nativo del navegador no deja
                      // pintar precios ni tachar los días ocupados ?>
                <?= view('web/_calendario') ?>

                <div class="row g-3 mt-1">
                    <div class="col-6">
                        <label class="form-label fw-semibold"><?= esc(lang('Web.adultos')) ?></label>
                        <select name="adultos" class="form-select form-select-lg">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>" <?= (string) old('adultos', '2') === (string) $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold"><?= esc(lang('Web.ninos')) ?></label>
                        <select name="ninos" class="form-select form-select-lg">
                            <?php for ($i = 0; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>" <?= (string) old('ninos', '0') === (string) $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor ?>
                        </select>
                    </div>
                </div>
                <button class="btn btn-reserva btn-lg w-100 mt-4">
                    <i class="bi bi-search me-1"></i><?= esc(lang('Web.verDisponibilidad')) ?>
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-muted small mt-4 mb-0">
        <?= esc(lang('Web.prefieresHablar')) ?>
        <a href="https://wa.me/<?= esc($hotel->whatsapp) ?>" target="_blank" rel="noopener"><?= esc(lang('Web.escribenosWsp')) ?></a>.
    </p>
</div>

<?= $this->endSection() ?>
