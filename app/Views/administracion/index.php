<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4">Administración</h1>

<?= view('partes/errores') ?>

<!-- ═══ Tareas del servidor ═══ -->
<?php // Va lo primero a propósito: una tarea programada que deja de correr no
      // avisa. Los datos se quedan viejos en silencio y nadie se entera hasta
      // que un huésped reserva una noche ya vendida en Booking. ?>
<div class="card border-0 shadow-sm mb-4" id="tareas">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-clock-history me-2 text-success"></i>Tareas automáticas del servidor</span>
        <?php if ($tareas['caducado']): ?>
            <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>Requiere atención</span>
        <?php else: ?>
            <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Al día</span>
        <?php endif ?>
    </div>
    <div class="card-body">

        <!-- Tipo de cambio -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 pb-3 mb-3 border-bottom">
            <div>
                <div class="fw-semibold">Tipo de cambio de las divisas</div>
                <?php if ($tareas['ultimo'] === null): ?>
                    <div class="small text-danger">
                        <i class="bi bi-x-circle me-1"></i>
                        Nunca se ha traído. La web solo enseña pesos, sin conversión.
                    </div>
                <?php else: ?>
                    <div class="small <?= $tareas['caducado'] ? 'text-danger' : 'text-muted' ?>">
                        Actualizado <?= esc($tareas['antiguedad']) ?>
                        <?= $tareas['caducado'] ? ' · demasiado viejo, la tarea puede no estar corriendo' : '' ?>
                    </div>
                    <div class="small text-muted mt-1">
                        <?php foreach ($tareas['cambios'] as $c): ?>
                            <span class="me-3">
                                1 <?= esc($c['moneda']) ?> =
                                $<?= number_format((float) $c['pesos'], 0, ',', '.') ?>
                                <?= $c['origen'] === 'manual' ? '<em>(a mano)</em>' : '' ?>
                            </span>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
            <form method="post" action="<?= site_url('administracion/cambio') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i>Actualizar ahora
                </button>
            </form>
        </div>

        <!-- Cómo programarlas -->
        <p class="small text-muted mb-2">
            Estas órdenes tienen que correr solas. Añádelas en el panel de tu hosting,
            en <strong>Tareas programadas</strong>. Son las rutas reales de este servidor:
        </p>

        <?php foreach ($tareas['ordenes'] as $o): ?>
            <div class="mb-2">
                <div class="small">
                    <strong><?= esc($o['que']) ?></strong>
                    <span class="text-muted">· <?= esc($o['cuando']) ?></span>
                </div>
                <code class="d-block bg-light border rounded p-2 small mt-1"
                      style="word-break: break-all;"><?= esc($o['orden']) ?></code>
            </div>
        <?php endforeach ?>

        <div class="alert alert-light border small mb-0 mt-3">
            <i class="bi bi-info-circle me-1"></i>
            El botón <strong>Actualizar ahora</strong> sirve para dos cosas: salir de un apuro si
            la tarea falló, y <strong>comprobar que la orden funciona en este servidor</strong>
            antes de programarla.
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ═══ Datos del hotel ═══ -->
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

    <!-- ═══ TPV compartido ═══ -->
    <div class="col-lg-6" id="tpv">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2 text-primary"></i>TPV compartido
            </div>
            <form method="post" action="<?= site_url('administracion/tpv') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="compartido" id="tpvCompartido"
                               <?= $tpv['compartido'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tpvCompartido">
                            <strong>Cada camarero se identifica</strong>
                            <span class="d-block form-text">
                                La pantalla se bloquea y cada uno abre con su PIN de fichaje o su tarjeta.
                                Todo lo que haga queda a su nombre. Apágalo si solo lo usa una persona.
                            </span>
                        </label>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Se bloquea tras</label>
                            <div class="input-group">
                                <input type="number" name="bloqueo_seg" class="form-control" min="15" max="3600" step="15"
                                       value="<?= (int) $tpv['bloqueo_seg'] ?>">
                                <span class="input-group-text">seg</span>
                            </div>
                            <div class="form-text">Sin tocar la pantalla. También se bloquea al cobrar.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Descuento sin permiso</label>
                            <div class="input-group">
                                <input type="number" name="descuento_libre" class="form-control" min="0" max="100" step="1"
                                       value="<?= (float) $tpv['descuento_libre'] ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Por encima lo autoriza un encargado.</div>
                        </div>
                    </div>

                    <p class="form-text mb-3">
                        <strong>Anular una comanda siempre</strong> pide el PIN de un encargado, y queda
                        anotado quién lo autorizó.
                    </p>

                    <hr>
                    <label class="form-label fw-semibold">Propina sugerida</label>
                    <div class="input-group mb-2" style="max-width: 180px;">
                        <input type="number" name="propina_sugerida" class="form-control" min="0" max="100" step="1"
                               value="<?= (float) $tpv['propina_sugerida'] ?>">
                        <span class="input-group-text">%</span>
                    </div>
                    <p class="form-text mb-0">
                        El TPV ofrecerá <strong>Sin propina</strong>, la mitad y este porcentaje.
                        En Colombia la propina es <strong>voluntaria</strong> (Ley 1935 de 2018):
                        el cliente puede rechazarla o cambiarla, hay que decírselo por escrito
                        —el ticket ya lo hace— y el 100 % es de los trabajadores.
                        Pon 0 para no sugerir ninguna.
                    </p>

                    <hr>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cocina_voz"
                               name="cocina_voz" <?= $tpv['cocina_voz'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="cocina_voz">
                            Leer las comandas en voz alta en cocina
                        </label>
                    </div>
                    <p class="form-text mb-0">
                        Al entrar una comanda, la pantalla de cocina la canta:
                        <em>«Nueva comanda. Mesa 3: dos de trucha al ajillo, sancocho de gallina.»</em>
                        Usa la voz del propio navegador, así que <strong>no cuesta nada y funciona
                        sin internet</strong>. Si nadie la marca como recibida, la repite al minuto
                        y medio. Cada pantalla puede silenciarla por su cuenta con el botón del
                        altavoz; este interruptor la apaga en todas.
                    </p>

                    <div class="form-check form-switch mt-3 mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cocina_escucha"
                               name="cocina_escucha" <?= $tpv['cocina_escucha'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="cocina_escucha">
                            Que el cocinero pueda decir «oído cocina»
                        </label>
                    </div>
                    <div class="alert alert-warning py-2 small mb-0">
                        <p class="mb-2">
                            Con esto encendido, el cocinero marca la comanda como recibida
                            <strong>sin tocar la pantalla</strong>: basta con decir
                            <em>«oído cocina»</em> (también valen «recibido», «marcha» o «anotado»).
                            Con <em>«oído todo»</em> marca todas las que estén esperando.
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-wifi me-1"></i>
                            <strong>Esto sí necesita internet</strong>, al revés que la lectura en voz
                            alta: Chrome envía el audio a Google para entenderlo. Sin conexión, el
                            botón «Recibido» sigue funcionando igual.
                        </p>
                        <p class="mb-0">
                            <i class="bi bi-mic me-1"></i>
                            <strong>El micrófono queda encendido durante el turno.</strong> Adviértelo
                            al personal antes de activarlo. Cada pantalla tiene además su propio
                            botón para apagarlo, y hay que dar permiso en el navegador la primera vez.
                        </p>
                    </div>

                    <?php if ($tpv['con_rol'] === []): ?>
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Nadie tiene acceso al TPV todavía.</strong> Si enciendes el modo compartido,
                            la pantalla quedará bloqueada sin que nadie pueda abrirla. Asigna el rol y el PIN
                            en <a href="<?= site_url('personal') ?>">las fichas del personal</a>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light py-2 small mb-0">
                            <i class="bi bi-person-check me-1"></i>
                            Con acceso:
                            <?php foreach ($tpv['con_rol'] as $i => $e): ?>
                                <?= esc($e['nombre']) ?><?= $e['rol_tpv'] === 'encargado' ? ' <span class="badge text-bg-primary">encargado</span>' : '' ?><?= $i < count($tpv['con_rol']) - 1 ? ', ' : '' ?>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
                <div class="card-footer bg-white p-3">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ Portal del huésped ═══ -->
    <div class="col-12">
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
                    </div>
                    <button class="btn btn-primary mt-3">
                        <i class="bi bi-check-lg me-1"></i>Guardar textos del portal
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ Control de jornada ═══ -->
    <div class="col-lg-6" id="fichaje">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-2 text-primary"></i>Control de jornada
            </div>
            <form method="post" action="<?= site_url('administracion/fichaje') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="terminal" id="fTerminal"
                               <?= $fichaje['terminal'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fTerminal">
                            <strong>Terminal encendido</strong>
                            <span class="d-block form-text">
                                La pantalla de <a href="<?= site_url('fichar') ?>" target="_blank" rel="noopener">fichar</a>
                                que se deja en recepción. Apágalo si no lo usas.
                            </span>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="foto" id="fFoto"
                               <?= $fichaje['foto'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fFoto">
                            <strong>Tomar foto al fichar en el terminal</strong>
                            <span class="d-block form-text">
                                Disuade de fichar por un compañero. Si la cámara falla, el fichaje se registra
                                igual pero queda señalado. Avísale al personal: es un dato personal suyo.
                            </span>
                        </label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="movil" id="fMovil"
                               <?= $fichaje['movil'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fMovil">
                            <strong>Permitir fichar desde el móvil</strong>
                            <span class="d-block form-text">
                                Con el portal del empleado. Puedes quitárselo a alguien en concreto desde su ficha.
                            </span>
                        </label>
                    </div>

                    <hr>
                    <p class="form-text mb-2">
                        <strong>Ubicación del hotel.</strong> Si la pones, cada fichaje desde el móvil anota
                        a cuántos metros se hizo. Sácala de Google Maps: pulsa largo sobre el punto y copia las
                        dos cifras que aparecen.
                    </p>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small mb-1">Latitud</label>
                            <input type="text" name="latitud" class="form-control form-control-sm"
                                   value="<?= esc($fichaje['latitud']) ?>" placeholder="6.1234567">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Longitud</label>
                            <input type="text" name="longitud" class="form-control form-control-sm"
                                   value="<?= esc($fichaje['longitud']) ?>" placeholder="-75.1234567">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Radio</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="radio" class="form-control" min="0" step="50"
                                       value="<?= (int) $fichaje['radio'] ?>">
                                <span class="input-group-text">m</span>
                            </div>
                        </div>
                    </div>
                    <p class="form-text mb-0">
                        Fichar fuera del radio <strong>no se bloquea</strong>: se anota y lo ves en el
                        <a href="<?= site_url('fichajes') ?>">control de jornada</a>. Puede haber mala señal,
                        o alguien haciendo de verdad un recado del hotel.
                    </p>
                </div>
                <div class="card-footer bg-white p-3">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ Correo saliente ═══ -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-envelope-at me-2 text-primary"></i>Correo saliente (SMTP)</div>
            <div class="card-body">
                <p class="text-muted small">
                    Para enviar confirmaciones de reserva y avisos. Con Gmail: servidor <code>smtp.gmail.com</code>,
                    puerto <code>587</code>, cifrado TLS y una <strong>contraseña de aplicación</strong>
                    (se crea en la cuenta de Google, no es la contraseña normal).
                </p>
                <form method="post" action="<?= site_url('administracion/correo') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label">Servidor SMTP</label>
                            <input type="text" name="host" class="form-control" value="<?= esc($correo['host']) ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Puerto</label>
                            <input type="number" name="puerto" class="form-control" value="<?= esc($correo['puerto']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario (correo)</label>
                            <input type="text" name="usuario" class="form-control" value="<?= esc($correo['usuario']) ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                Contraseña
                                <?php if ($correo['clave_guardada']): ?>
                                    <span class="badge text-bg-success ms-1">Guardada</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary ms-1">Sin configurar</span>
                                <?php endif ?>
                            </label>
                            <input type="password" name="clave" class="form-control" autocomplete="new-password"
                                   placeholder="<?= $correo['clave_guardada'] ? 'En blanco = conservar la actual' : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cifrado</label>
                            <select name="cifrado" class="form-select">
                                <option value="tls" <?= $correo['cifrado'] === 'tls' ? 'selected' : '' ?>>TLS (recomendado)</option>
                                <option value="ssl" <?= $correo['cifrado'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre del remitente</label>
                            <input type="text" name="remitente_nombre" class="form-control" value="<?= esc($correo['remitente_nombre']) ?>">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Guardar correo</button>
                </form>
                <hr>
                <form method="post" action="<?= site_url('administracion/correo/probar') ?>" class="d-flex gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="email" name="destino" class="form-control" style="max-width: 280px;" required
                           placeholder="tu@correo.com" value="<?= esc(session()->get('usuario_email') ?? '') ?>">
                    <button class="btn btn-outline-primary"><i class="bi bi-send me-1"></i>Enviar correo de prueba</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ Facturación electrónica (Siigo) ═══ -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-receipt-cutoff me-2 text-success"></i>Facturación electrónica · Siigo
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Siigo es proveedor tecnológico autorizado por la DIAN: el sistema le envía la factura
                    y Siigo la timbra y la reporta. Genera tus credenciales en Siigo Nube, en
                    <strong>Configuración › Alianzas e integraciones › Credenciales de integración</strong>,
                    y pégalas aquí. Se guardan cifradas.
                </p>

                <form method="post" action="<?= site_url('administracion/siigo') ?>">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">
                                Usuario API
                                <?php if ($siigo['usuario_guardado']): ?>
                                    <span class="badge text-bg-success ms-1">Guardado</span>
                                <?php endif ?>
                            </label>
                            <input type="text" name="usuario" class="form-control" autocomplete="off"
                                   placeholder="<?= $siigo['usuario_guardado'] ? 'En blanco = conservar' : 'correo@empresa.com' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">
                                Access Key
                                <?php if ($siigo['clave_guardada']): ?>
                                    <span class="badge text-bg-success ms-1">Guardada</span>
                                <?php endif ?>
                            </label>
                            <input type="password" name="access_key" class="form-control" autocomplete="new-password"
                                   placeholder="<?= $siigo['clave_guardada'] ? 'En blanco = conservar' : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">
                                Partner-Id
                                <?php if ($siigo['partner_guardado']): ?>
                                    <span class="badge text-bg-success ms-1">Guardado</span>
                                <?php endif ?>
                            </label>
                            <input type="password" name="partner_id" class="form-control" autocomplete="new-password"
                                   placeholder="<?= $siigo['partner_guardado'] ? 'En blanco = conservar' : '' ?>">
                        </div>
                    </div>

                    <h3 class="h6 text-muted">Parámetros de la factura</h3>
                    <p class="text-muted small">
                        Estos identificadores salen de tu propia cuenta de Siigo. Pulsa
                        <strong>Probar conexión</strong> abajo y te los mostraré para que los copies.
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Tipo de documento <span class="text-danger">*</span></label>
                            <input type="text" name="documento_id" class="form-control" value="<?= esc($siigo['documento_id']) ?>"
                                   placeholder="Ej.: 24446">
                            <div class="form-text">Tu resolución de facturación.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Vendedor <span class="text-danger">*</span></label>
                            <input type="text" name="vendedor_id" class="form-control" value="<?= esc($siigo['vendedor_id']) ?>">
                            <div class="form-text">Usuario de Siigo que firma.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Forma de pago <span class="text-danger">*</span></label>
                            <input type="text" name="pago_id" class="form-control" value="<?= esc($siigo['pago_id']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Impuesto</label>
                            <input type="text" name="impuesto_id" class="form-control" value="<?= esc($siigo['impuesto_id']) ?>">
                            <div class="form-text">Déjalo vacío si no aplicas IVA.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Código: alojamiento</label>
                            <input type="text" name="codigo_alojamiento" class="form-control" value="<?= esc($siigo['codigo_alojamiento']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Código: restaurante</label>
                            <input type="text" name="codigo_restaurante" class="form-control" value="<?= esc($siigo['codigo_restaurante']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Código: otros</label>
                            <input type="text" name="codigo_otros" class="form-control" value="<?= esc($siigo['codigo_otros']) ?>">
                        </div>
                        <div class="col-md-3 d-flex flex-column justify-content-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enviar_dian" value="1" id="sDian" <?= $siigo['enviar_dian'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="sDian">Timbrar ante la DIAN</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enviar_correo" value="1" id="sMail" <?= $siigo['enviar_correo'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="sMail">Enviar al cliente por correo</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mb-3">
                        Los códigos deben existir como productos en tu catálogo de Siigo.
                    </div>

                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar facturación</button>
                </form>

                <hr>
                <form method="post" action="<?= site_url('administracion/siigo/probar') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-primary"><i class="bi bi-plug me-1"></i>Probar conexión y ver mis catálogos</button>
                </form>

                <?php if (! empty($catalogos)): ?>
                    <div class="row g-3 mt-2">
                        <?php
                        $bloques = [
                            'documentos' => ['Tipos de documento', 'documento_id'],
                            'vendedores' => ['Vendedores', 'vendedor_id'],
                            'pagos'      => ['Formas de pago', 'pago_id'],
                            'impuestos'  => ['Impuestos', 'impuesto_id'],
                        ];
                        ?>
                        <?php foreach ($bloques as $clave => [$titulo, $campo]): ?>
                            <div class="col-md-6 col-xl-3">
                                <div class="card h-100">
                                    <div class="card-header bg-white small fw-semibold"><?= $titulo ?></div>
                                    <div class="card-body p-2" style="max-height:220px; overflow-y:auto">
                                        <?php if (empty($catalogos[$clave])): ?>
                                            <p class="text-muted small mb-0">Sin datos.</p>
                                        <?php endif ?>
                                        <?php foreach ((array) ($catalogos[$clave] ?? []) as $item): ?>
                                            <div class="small border-bottom py-1">
                                                <code><?= esc($item['id'] ?? '') ?></code>
                                                · <?= esc($item['name'] ?? $item['username'] ?? $item['description'] ?? '') ?>
                                                <?php if (isset($item['percentage'])): ?>
                                                    <span class="text-muted">(<?= esc($item['percentage']) ?>%)</span>
                                                <?php endif ?>
                                            </div>
                                        <?php endforeach ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        Copia el número de la izquierda en el campo correspondiente de arriba y guarda.
                    </p>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- ═══ Correos enviados ═══ -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-send me-2 text-primary"></i>Correos enviados
                <span class="text-muted small fw-normal">— últimos 20</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Destinatario</th>
                            <th class="d-none d-lg-table-cell">Asunto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($correosLog)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                Todavía no se ha enviado ningún correo.
                            </td></tr>
                        <?php endif ?>
                        <?php foreach ($correosLog as $c): ?>
                            <tr>
                                <td class="text-muted small text-nowrap"><?= date('d/m H:i', strtotime($c['created_at'])) ?></td>
                                <td><?= esc($tiposCorreo[$c['tipo']] ?? $c['tipo']) ?></td>
                                <td class="small"><?= esc($c['destinatario']) ?></td>
                                <td class="small text-muted d-none d-lg-table-cell"><?= esc($c['asunto']) ?></td>
                                <td>
                                    <?php if ($c['estado'] === 'enviado'): ?>
                                        <span class="badge text-bg-success">Enviado</span>
                                    <?php elseif ($c['estado'] === 'sin_configurar'): ?>
                                        <span class="badge text-bg-warning" title="<?= esc($c['error'] ?? '') ?>">Sin configurar</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger" title="<?= esc(mb_substr($c['error'] ?? '', 0, 300)) ?>">Falló</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Pasa el ratón por encima de un envío fallido para ver el motivo.
                Los correos nunca interrumpen la operación del hotel: si fallan, quedan anotados aquí.
            </div>
        </div>
    </div>

    <!-- ═══ Wompi ═══ -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-credit-card me-2 text-warning"></i>Pagos en línea (Wompi)</div>
            <div class="card-body">
                <p class="text-muted small">
                    Credenciales de tu cuenta Wompi (comercios.wompi.co → Desarrolladores).
                    Se guardan cifradas y se usarán cuando activemos el pago online del motor de reservas.
                    Empieza con el ambiente de <strong>pruebas</strong>.
                </p>
                <form method="post" action="<?= site_url('administracion/wompi') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Ambiente</label>
                        <select name="ambiente" class="form-select">
                            <option value="pruebas" <?= $wompi['ambiente'] === 'pruebas' ? 'selected' : '' ?>>Pruebas (sandbox)</option>
                            <option value="produccion" <?= $wompi['ambiente'] === 'produccion' ? 'selected' : '' ?>>Producción (cobros reales)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Llave pública</label>
                        <input type="text" name="llave_publica" class="form-control" value="<?= esc($wompi['llave_publica']) ?>"
                               placeholder="pub_test_..." autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Llave privada
                            <?php if ($wompi['privada_guardada']): ?>
                                <span class="badge text-bg-success ms-1">Guardada (cifrada)</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary ms-1">Sin configurar</span>
                            <?php endif ?>
                        </label>
                        <input type="password" name="llave_privada" class="form-control" autocomplete="new-password"
                               placeholder="<?= $wompi['privada_guardada'] ? 'En blanco = conservar la actual' : 'prv_test_...' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Secreto de integridad
                            <?php if ($wompi['integridad_guardada']): ?>
                                <span class="badge text-bg-success ms-1">Guardado (cifrado)</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary ms-1">Sin configurar</span>
                            <?php endif ?>
                        </label>
                        <input type="password" name="secreto_integridad" class="form-control" autocomplete="new-password"
                               placeholder="<?= $wompi['integridad_guardada'] ? 'En blanco = conservar el actual' : 'test_integrity_...' ?>">
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar Wompi</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
