<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion">
    <div class="text-center mb-5 reveal">
        <p class="etiqueta">Contacto</p>
        <h1 class="titulo-seccion">Estamos para ayudarte</h1>
        <p class="text-muted mt-2">Resolvemos tus dudas y te ayudamos a planear la visita.</p>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-md-5 reveal">
            <div class="card tarjeta-tipo h-100">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h5 mb-4"><i class="bi bi-chat-dots me-2" style="color:var(--arena);"></i>Habla con nosotros</h2>
                    <p class="mb-3">
                        <i class="bi bi-whatsapp me-2 text-success"></i>
                        <a href="https://wa.me/<?= esc($hotel->whatsapp) ?>" target="_blank" rel="noopener">WhatsApp: <?= esc($hotel->telefono) ?></a>
                        <br><span class="small text-muted ms-4">La forma más rápida de reservar</span>
                    </p>
                    <p class="mb-3"><i class="bi bi-telephone me-2" style="color:var(--bosque);"></i><a href="tel:<?= esc($hotel->telefono) ?>"><?= esc($hotel->telefono) ?></a></p>
                    <p class="mb-0"><i class="bi bi-envelope me-2" style="color:var(--bosque);"></i><a href="mailto:<?= esc($hotel->email) ?>"><?= esc($hotel->email) ?></a></p>
                </div>
            </div>
        </div>
        <div class="col-md-5 reveal">
            <div class="card tarjeta-tipo h-100">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h5 mb-4"><i class="bi bi-geo-alt me-2" style="color:var(--arena);"></i>Cómo llegar</h2>
                    <p><?= esc($hotel->direccion) ?></p>
                    <p class="small text-muted mb-0">
                        Pronto publicaremos aquí el mapa y las indicaciones detalladas de acceso
                        por carretera, incluyendo recomendaciones para época de lluvias.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
