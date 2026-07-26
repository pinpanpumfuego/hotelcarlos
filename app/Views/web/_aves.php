<?php

use App\Libraries\Aves;

/**
 * «Las aves de nuestro bosque».
 *
 * No es decoración: es contenido. Quien viaja a observar aves elige alojamiento
 * por la lista de especies, y esa es exactamente la gente que va a leer las
 * versiones en inglés y alemán de esta web.
 *
 * El nombre científico va siempre visible porque es el que usan los
 * observadores de todo el mundo y no depende del idioma.
 */
?>
<section class="seccion aves" id="aves">
    <?php // Dos aves cruzando el fondo de la sección, muy tenues ?>
    <div class="aves-fondo" aria-hidden="true">
        <span class="ave-fondo a1"><svg viewBox="0 0 100 30"><use href="#ave-planea"></use></svg></span>
        <span class="ave-fondo a2"><svg viewBox="0 0 100 30"><use href="#ave-planea"></use></svg></span>
    </div>

    <div class="container position-relative">
        <div class="text-center mb-5 reveal">
            <p class="etiqueta"><?= esc(lang('Web.avesNaturaleza')) ?></p>
            <h2 class="titulo-seccion"><?= esc(lang('Web.avesTitulo')) ?></h2>
            <p class="text-muted mt-2 mx-auto" style="max-width: 640px;">
                <?= esc(lang('Web.avesIntro')) ?>
            </p>
        </div>

        <div class="row g-3 g-md-4 justify-content-center">
            <?php foreach (Aves::ESPECIES as $ave): ?>
                <div class="col-6 col-md-4 col-lg-2 reveal">
                    <figure class="ficha-ave h-100" style="--tinte: <?= esc($ave['color']) ?>">
                        <div class="silueta">
                            <svg viewBox="0 0 120 120" role="img"
                                 aria-label="<?= esc(lang('Web.ave_' . $ave['clave'])) ?>">
                                <use href="#perfil-<?= esc($ave['perfil']) ?>"></use>
                            </svg>
                        </div>
                        <figcaption>
                            <span class="nombre"><?= esc(lang('Web.ave_' . $ave['clave'])) ?></span>
                            <span class="cientifico"><?= esc($ave['cientifico']) ?></span>
                            <?php if (! empty($ave['endemica'])): ?>
                                <span class="endemica"><?= esc(lang('Web.endemica')) ?></span>
                            <?php endif ?>
                        </figcaption>
                    </figure>
                </div>
            <?php endforeach ?>
        </div>

        <p class="text-center text-muted small mt-4 mb-0 mx-auto" style="max-width: 620px;">
            <?php // Se dice lo que hay, no lo que gustaría: prometer una especie
                  // que no aparece es la forma más rápida de perder a un birder ?>
            <i class="bi bi-binoculars me-1"></i><?= esc(lang('Web.avesAviso')) ?>
        </p>
    </div>
</section>
