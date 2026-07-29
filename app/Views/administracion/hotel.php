<?= $this->extend("layouts/panel") ?>
<?= $this->section("contenido") ?>

<h1 class="h3 mb-1">Administración</h1>
<p class="text-muted">Lo que ve el p\xc3\xbablico: los datos del hotel y los textos que lee quien se aloja.</p>

<?= view("administracion/_nav", ["activa" => "hotel"]) ?>

<?= view("partes/errores") ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-tree me-2 text-success"></i>Datos del hotel</div>
            <div class="card-body">
                <p class="text-muted small">Estos datos se muestran en la web pública, el panel y los documentos.</p>
                <form method="post" action="<?= site_url('administracion/hotel') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del hotel</label>
                            <input type="text" name="nombre" class="form-control" required value="<?= esc($hotel['nombre']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Eslogan</label>
                            <input type="text" name="eslogan" class="form-control" value="<?= esc($hotel['eslogan']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teléfono (como se muestra)</label>
                            <input type="text" name="telefono" class="form-control" value="<?= esc($hotel['telefono']) ?>" placeholder="+57 300 000 0000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">WhatsApp (solo dígitos con país)</label>
                            <input type="text" name="whatsapp" class="form-control" value="<?= esc($hotel['whatsapp']) ?>" placeholder="573000000000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Correo público</label>
                            <input type="email" name="email" class="form-control" value="<?= esc($hotel['email']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Dirección / ubicación</label>
                            <input type="text" name="direccion" class="form-control" value="<?= esc($hotel['direccion']) ?>" placeholder="Vereda, municipio, departamento, Colombia">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Guardar datos del hotel</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4 <?= $obras['activo'] ? 'border-start border-4 border-warning' : '' ?>" id="obras">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-cone-striped me-2 text-warning"></i>Cartel de «todavía no abrimos»
                <?php if ($obras['activo']): ?>
                    <span class="badge text-bg-warning ms-1">La web está tapada</span>
                <?php endif ?>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Tapa la web pública y el motor de reservas. <strong>No toca nada de lo que ya
                    funciona</strong>: el portal del huésped, los enlaces de registro ya enviados,
                    el calendario que lee Booking, el fichaje, este panel ni el aviso de la pasarela
                    de pagos.
                </p>

                <form method="post" action="<?= site_url('administracion/obras') ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-md-8">
                        <label class="form-label">Titular</label>
                        <input type="text" name="titulo" class="form-control" maxlength="120"
                               value="<?= esc($obras['titulo']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Cuándo abrís</label>
                        <input type="text" name="fecha" class="form-control" maxlength="60"
                               value="<?= esc($obras['fecha']) ?>" placeholder="Ej.: Abrimos en marzo">
                        <div class="form-text">Déjalo vacío si aún no hay fecha.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Texto</label>
                        <textarea name="texto" class="form-control" rows="3" maxlength="500"><?= esc($obras['texto']) ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Clave para poder entrar</label>
                        <input type="text" name="clave" class="form-control" autocomplete="off"
                               placeholder="<?= $obras['clave_guardada'] ? 'Puesta — escribe otra para cambiarla' : 'Sin poner' ?>">
                        <div class="form-text">
                            En blanco se queda la que hay. Es una cortina para que nadie vea el hotel
                            a medio configurar, no una cerradura: detrás no hay datos de nadie.
                        </div>
                    </div>

                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="activo" id="obras_activo"
                                   <?= $obras['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="obras_activo">
                                Tapar la web pública
                                <span class="form-text d-block">
                                    Quítalo el día que abráis y la web vuelve sola.
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                        <?php if ($obras['activo']): ?>
                            <a href="<?= site_url('obras/salir') ?>" class="btn btn-link" target="_blank" rel="noopener">
                                Ver cómo lo ve un visitante
                            </a>
                        <?php endif ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm" id="portal">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-phone me-2 text-success"></i>Portal del huésped
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Lo que lee el huésped en su móvil durante la estancia. <strong>Lo que dejes
                    en blanco no se le enseña</strong>, así que puedes rellenarlo poco a poco.
                </p>
                <form method="post" action="<?= site_url('administracion/portal') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Horarios</label>
                            <textarea name="horarios" class="form-control" rows="4"
                                      placeholder="Recepción: 7:00 a 21:00&#10;Desayuno: 7:30 a 10:00&#10;Restaurante: 12:30 a 15:00 y 19:00 a 21:30"><?= esc($portal['horarios']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Normas de la casa</label>
                            <textarea name="normas" class="form-control" rows="4"
                                      placeholder="Entrada desde las 15:00, salida hasta las 12:00&#10;No se puede fumar dentro de las cabañas&#10;Silencio a partir de las 22:00"><?= esc($portal['normas']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rutas y senderos</label>
                            <textarea name="rutas" class="form-control" rows="4"
                                      placeholder="Sendero del lago · 40 min · fácil&#10;Mirador de los Farallones · 2 h · exige calzado cerrado&#10;No salgas del sendero marcado ni después del anochecer"><?= esc($portal['rutas']) ?></textarea>
                            <div class="form-text">
                                Si hay senderos, di también lo que <strong>no</strong> hay que hacer.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recomendaciones de la zona</label>
                            <textarea name="recomendaciones" class="form-control" rows="4"
                                      placeholder="Dónde comer, qué visitar, cómo moverse…"><?= esc($portal['recomendaciones']) ?></textarea>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Condiciones de cancelación</label>
                            <textarea name="politica" class="form-control" rows="3"
                                      placeholder="Cancelación gratuita hasta 7 días antes de la llegada…"><?= esc($portal['politica']) ?></textarea>
                            <div class="form-text">
                                Es lo que el huésped busca cuando le cambian los planes.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tope de cargo a la cabaña</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="tope_cargo" class="form-control" min="0" step="10000"
                                       value="<?= (int) $portal['tope_cargo'] ?>">
                            </div>
                            <div class="form-text">
                                Lo máximo que puede pedir al restaurante sin que nadie lo apruebe.
                                <strong>En 0 se apagan los pedidos desde el portal.</strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr class="my-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="minibar" value="1" id="minibarOn"
                                       <?= $portal['minibar'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="minibarOn">
                                    <strong>Hay minibar</strong>
                                    <span class="d-block form-text">
                                        El huésped apunta lo que se ha tomado y se le carga a la cuenta en el acto,
                                        sin sorpresas al salir. <strong>Apagado, no aparece por ninguna parte.</strong>
                                    </span>
                                </label>
                            </div>
                            <?php if ($portal['minibar']): ?>
                                <div class="alert alert-light border small mt-2 mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Hace falta además marcar <strong>qué cabañas lo tienen</strong> —no todas tienen
                                    por qué— y <strong>qué productos hay dentro</strong>.
                                    <div class="mt-2">
                                        <a href="<?= site_url('unidades') ?>" class="badge text-decoration-none
                                           <?= $portal['cabanas_minibar'] > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                                            <?= (int) $portal['cabanas_minibar'] ?> cabaña(s) con minibar
                                        </a>
                                        <a href="<?= site_url('carta') ?>" class="badge text-decoration-none ms-1
                                           <?= $portal['productos_minibar'] > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                                            <?= (int) $portal['productos_minibar'] ?> producto(s) en el minibar
                                        </a>
                                    </div>
                                    <?php if ($portal['cabanas_minibar'] === 0 || $portal['productos_minibar'] === 0): ?>
                                        <div class="mt-2 text-warning-emphasis">
                                            Mientras falte una de las dos cosas, el minibar no se le enseña a nadie.
                                        </div>
                                    <?php endif ?>
                                </div>
                            <?php endif ?>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3">
                        <i class="bi bi-check-lg me-1"></i>Guardar textos del portal
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
