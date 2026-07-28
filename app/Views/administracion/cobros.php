<?= $this->extend("layouts/panel") ?>
<?= $this->section("contenido") ?>

<h1 class="h3 mb-1">Administración</h1>
<p class="text-muted">Llaves de terceros. Quien las tiene puede facturar en nombre del hotel y cobrar con su pasarela.</p>

<?= view("administracion/_nav", ["activa" => "cobros"]) ?>

<?= view("partes/errores") ?>

<div class="row g-4">
    <div class="col-lg-6" id="wompi">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-credit-card me-2 text-warning"></i>Pagos en línea (Wompi)</div>
            <div class="card-body">
                <p class="text-muted small">
                    Credenciales de tu cuenta Wompi (comercios.wompi.co → Desarrolladores).
                    Se guardan cifradas. <strong>Empieza siempre por el ambiente de pruebas</strong>: pegar una
                    llave de pruebas en producción significa que nadie cobra nada de verdad.
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
                    <div class="mb-3">
                        <label class="form-label">
                            Secreto de eventos
                            <?php if ($wompi['eventos_guardado']): ?>
                                <span class="badge text-bg-success ms-1">Guardado (cifrado)</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning ms-1">Falta</span>
                            <?php endif ?>
                        </label>
                        <input type="password" name="secreto_eventos" class="form-control" autocomplete="new-password"
                               placeholder="<?= $wompi['eventos_guardado'] ? 'En blanco = conservar el actual' : 'test_events_...' ?>">
                        <div class="form-text">
                            Es el que permite comprobar que un aviso de pago viene de verdad de Wompi.
                            <strong>Sin él, los avisos se rechazan todos</strong> — y son la red que recoge
                            los pagos que el huésped no llegó a confirmar en pantalla.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Anticipo al reservar por la web</label>
                        <div class="input-group">
                            <input type="number" name="anticipo_pct" class="form-control" min="0" max="100"
                                   value="<?= (int) $wompi['anticipo_pct'] ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">
                            Cuánto se cobra por adelantado. <strong>En 0 se cobra el total</strong> cuando
                            se manda un enlace de pago a mano.
                        </div>
                    </div>

                    <?php if ($wompi['revision'] !== []): ?>
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <?= esc(implode(' ', $wompi['revision'])) ?>
                        </div>
                    <?php endif ?>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="wompiActivo"
                               <?= $wompi['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="wompiActivo">
                            <strong>Cobrar en línea</strong>
                            <span class="d-block form-text">
                                Apagado, el motor de reservas sigue funcionando pero sin cobrar nada,
                                como hasta ahora.
                            </span>
                        </label>
                    </div>

                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar Wompi</button>
                </form>

                <form method="post" action="<?= site_url('administracion/wompi/probar') ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-plug me-1"></i>Probar la conexión
                    </button>
                </form>

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="bi bi-broadcast me-1"></i>
                    En el panel de Wompi, apunta esta dirección como <strong>URL de eventos</strong>:
                    <code class="d-block mt-1" style="word-break: break-all;"><?= site_url('pago/aviso') ?></code>
                </div>
            </div>
        </div>
    </div>

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
</div>

<?= $this->endSection() ?>
