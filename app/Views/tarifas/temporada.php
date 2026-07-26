<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\TemporadaModel;

$noches = (new DateTime($temporada['desde']))->diff(new DateTime($temporada['hasta']))->days + 1;
?>

<div class="mb-4">
    <a href="<?= site_url('tarifas') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Tarifas
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1 d-flex align-items-center gap-2">
                <span class="punto-color" style="background: <?= esc($temporada['color']) ?>"></span>
                <?= esc($temporada['nombre']) ?>
            </h1>
            <p class="text-muted mb-0">
                Del <?= date('d/m/Y', strtotime($temporada['desde'])) ?>
                al <?= date('d/m/Y', strtotime($temporada['hasta'])) ?>
                · <?= $noches ?> noche<?= $noches > 1 ? 's' : '' ?>
                · Ajuste general <span class="fw-semibold"><?= esc(TemporadaModel::textoAjuste($temporada)) ?></span>
            </p>
        </div>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTemporada<?= $temporada['id'] ?>">
            <i class="bi bi-pencil me-1"></i>Editar temporada
        </button>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" action="<?= site_url('tarifas/temporada/precios/' . $temporada['id']) ?>">
            <?= csrf_field() ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-cash-stack me-2"></i>Precio cerrado por tipo de alojamiento
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small">
                        Si pones un precio aquí, esa cifra <strong>sustituye</strong> a la tarifa base y al ajuste general
                        durante esta temporada. Déjalo vacío para que se use el ajuste
                        (<?= esc(TemporadaModel::textoAjuste($temporada)) ?>).
                    </p>

                    <?php if ($tipos === []): ?>
                        <div class="alert alert-warning mb-0">
                            No hay tipos de alojamiento. Créalos en <a href="<?= site_url('tipos') ?>">Tipos de alojamiento</a>.
                        </div>
                    <?php else: ?>
                        <?php foreach ($tipos as $tipo): ?>
                            <?php $valor = $precios[$tipo['id']] ?? null; ?>
                            <div class="row g-3 align-items-center py-2 border-bottom">
                                <div class="col-sm-6">
                                    <div class="fw-semibold"><?= esc($tipo['nombre']) ?></div>
                                    <div class="text-muted small">
                                        Tarifa base $<?= number_format((float) $tipo['tarifa_base'], 0, ',', '.') ?>
                                        · <?= (int) $tipo['capacidad'] ?> personas
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="precio[<?= $tipo['id'] ?>]" class="form-control"
                                               min="0" step="1000"
                                               value="<?= $valor !== null ? (int) $valor : '' ?>"
                                               placeholder="Usar el ajuste general">
                                        <span class="input-group-text">/ noche</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
                <?php if ($tipos !== []): ?>
                    <div class="card-footer bg-white p-3">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar precios</button>
                    </div>
                <?php endif ?>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-lightbulb me-2"></i>Cómo se combina</div>
            <div class="card-body">
                <ol class="small text-muted mb-3 ps-3">
                    <li class="mb-2">Si hay <strong>precio cerrado</strong> para el tipo, se parte de él.</li>
                    <li class="mb-2">Si no, se aplica el <strong>ajuste general</strong> de la temporada sobre la tarifa base.</li>
                    <li class="mb-2">Después se apilan las <strong>reglas</strong> (fin de semana, ocupación…).</li>
                    <li>Y al final mandan los <strong>topes</strong> mínimo y máximo del alojamiento.</li>
                </ol>
                <a href="<?= site_url('tarifas/calendario?anio=' . date('Y', strtotime($temporada['desde'])) . '&mes=' . date('n', strtotime($temporada['desde']))) ?>"
                   class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-calendar3 me-1"></i>Ver el resultado en el calendario
                </a>
            </div>
        </div>
    </div>
</div>

<?= view('tarifas/_modal_temporada', [
    'id'        => 'modalTemporada' . $temporada['id'],
    'accion'    => site_url('tarifas/temporada/actualizar/' . $temporada['id']),
    'temporada' => $temporada,
    'anio'      => (int) date('Y'),
]) ?>

<style>
    .punto-color { width: 12px; height: 12px; border-radius: 50%; display: inline-block;
                   box-shadow: 0 0 0 3px rgba(0,0,0,.05); }
    .border-bottom:last-of-type { border-bottom: 0 !important; }
</style>

<?= $this->endSection() ?>
