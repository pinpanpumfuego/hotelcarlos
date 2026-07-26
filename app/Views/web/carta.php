<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion">
    <div class="text-center mb-5 reveal visible">
        <p class="etiqueta">Restaurante</p>
        <h1 class="titulo-seccion">Nuestra carta</h1>
        <p class="text-muted mt-2">Cocina local con productos de la región, junto al lago.</p>
    </div>

    <?php if (empty($porCategoria)): ?>
        <p class="text-center text-muted">Muy pronto publicaremos nuestra carta.</p>
    <?php endif ?>

    <?php foreach ($porCategoria as $categoria => $productos): ?>
        <div class="mb-5 reveal">
            <h2 class="h4 mb-4 pb-2" style="border-bottom: 2px solid var(--arena);"><?= esc($categoria) ?></h2>
            <?php foreach ($productos as $p): ?>
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3 pb-3" style="border-bottom: 1px dashed #ddd7cc;">
                    <div>
                        <h3 class="h6 mb-1">
                            <?= esc($p['nombre']) ?>
                            <?php foreach ($dietas as $campo => $info): ?>
                                <?php if (! empty($p[$campo])): ?>
                                    <span class="badge rounded-pill text-bg-success ms-1" style="font-weight:500">
                                        <i class="bi <?= $info['icono'] ?>"></i> <?= esc($info['etiqueta']) ?>
                                    </span>
                                <?php endif ?>
                            <?php endforeach ?>
                            <?php if ((int) ($p['picante'] ?? 0) > 0): ?>
                                <span title="Picante"><?= str_repeat('🌶', (int) $p['picante']) ?></span>
                            <?php endif ?>
                        </h3>
                        <?php if ($p['descripcion']): ?>
                            <p class="text-muted small mb-1"><?= esc($p['descripcion']) ?></p>
                        <?php endif ?>
                        <?php if (! empty($p['alergenos_lista'])): ?>
                            <p class="small mb-0" style="color:#9a3b40;">
                                <i class="bi bi-exclamation-triangle"></i>
                                Contiene: <?= esc(implode(', ', array_map(static fn ($a) => $alergenos[$a], $p['alergenos_lista']))) ?>
                            </p>
                        <?php endif ?>
                    </div>
                    <div class="text-nowrap fw-bold" style="color: var(--bosque);">
                        $<?= number_format((float) $p['precio'], 0, ',', '.') ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>

    <div class="alert alert-light border small mt-4">
        <i class="bi bi-info-circle me-1"></i>
        Si tienes alguna alergia o intolerancia, avísanos al reservar o al llegar: adaptamos los platos siempre que sea posible.
        Los precios están en pesos colombianos e incluyen impuestos.
    </div>

    <?php if (! empty($propinaSugerida)): ?>
        <!-- La Ley 1935 de 2018 obliga a informarlo también en la carta -->
        <div class="alert alert-light border small">
            <i class="bi bi-cash-coin me-1"></i>
            <strong>Propina.</strong> Sugerimos un
            <?= rtrim(rtrim(number_format($propinaSugerida, 1, ',', '.'), '0'), ',') ?> % de propina,
            que es <strong>voluntaria</strong>: puedes aceptarla, rechazarla o cambiar su valor, y te lo
            preguntaremos antes de cobrar. Se reparte íntegramente entre quienes te atienden
            (Ley 1935 de 2018).
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
