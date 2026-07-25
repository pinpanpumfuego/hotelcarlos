<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4">Administración</h1>

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
