<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['pendiente' => 'secondary', 'enviado' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger'];
$etiqueta    = \App\Models\RegistroModel::ESTADOS[$registro['estado']];
$enviado     = $registro['estado'] === 'enviado';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('registros') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Registros</a>
        <h1 class="h3 mb-0 mt-1">
            <?= esc($registro['nombre']) ?> <?= esc($registro['apellidos']) ?>
            <span class="badge text-bg-<?= $colorEstado[$registro['estado']] ?> fs-6 align-middle ms-2"><?= esc($etiqueta) ?></span>
        </h1>
        <div class="text-muted small mt-1">
            Reserva <a href="<?= site_url('reservas/ver/' . $registro['reserva_id']) ?>"><?= esc($registro['codigo']) ?></a>
            · <?= esc($registro['unidad_nombre']) ?>
            · <?= date('d/m/Y', strtotime($registro['fecha_entrada'])) ?> → <?= date('d/m/Y', strtotime($registro['fecha_salida'])) ?>
        </div>
    </div>
    <?php if ($enviado): ?>
        <div class="d-flex gap-2">
            <form method="post" action="<?= site_url('registros/aprobar/' . $registro['id']) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-success"><i class="bi bi-patch-check me-1"></i>Aprobar registro</button>
            </form>
            <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#devolver">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Devolver
            </button>
        </div>
    <?php endif ?>
</div>

<?php if ($enviado): ?>
    <div class="collapse mb-4" id="devolver">
        <div class="card border-0 shadow-sm border-start border-4 border-danger">
            <div class="card-body">
                <h2 class="h6">Devolver al huésped para que corrija</h2>
                <p class="text-muted small">Verá tu mensaje al abrir su enlace y podrá volver a enviarlo.</p>
                <form method="post" action="<?= site_url('registros/rechazar/' . $registro['id']) ?>" class="d-flex gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="text" name="motivo" class="form-control" style="min-width:260px; flex:1"
                           placeholder="Ej.: la foto del documento está borrosa, no se lee el número" required>
                    <button class="btn btn-danger">Devolver</button>
                </form>
            </div>
        </div>
    </div>
<?php endif ?>

<?php if ($registro['hay_menores'] || $registro['hay_extranjeros']): ?>
    <div class="alert alert-warning">
        <?php if ($registro['hay_menores']): ?>
            <div><i class="bi bi-shield-exclamation me-1"></i>
                <strong>Viajan menores de edad.</strong> Verifica el documento del menor y, si no viaja con
                ambos padres, la autorización de viaje. Aplica el protocolo de protección del establecimiento.
            </div>
        <?php endif ?>
        <?php if ($registro['hay_extranjeros']): ?>
            <div class="<?= $registro['hay_menores'] ? 'mt-2' : '' ?>"><i class="bi bi-globe2 me-1"></i>
                <strong>Hay huéspedes extranjeros.</strong> Recuerda el reporte a Migración Colombia (SIRE)
                dentro del plazo establecido.
            </div>
        <?php endif ?>
    </div>
<?php endif ?>

<div class="row g-4">
    <!-- ═══ Datos ═══ -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Datos del titular</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:45%">Documento</td><td><?= esc($registro['tipo_documento']) ?> <?= esc($registro['num_documento']) ?></td></tr>
                    <tr><td class="text-muted">Fecha de nacimiento</td><td><?= $registro['fecha_nacimiento'] ? date('d/m/Y', strtotime($registro['fecha_nacimiento'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Nacionalidad</td><td><?= esc($registro['nacionalidad'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Residencia</td><td><?= esc(trim(($registro['ciudad_residencia'] ?? '') . ', ' . ($registro['pais_residencia'] ?? ''), ', ')) ?: '—' ?></td></tr>
                    <tr><td class="text-muted">Dirección</td><td><?= esc($registro['direccion'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Motivo del viaje</td><td><?= esc($motivos[$registro['motivo_viaje']] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Ocupación</td><td><?= esc($registro['ocupacion'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Teléfono</td><td>
                        <?php if ($registro['telefono']): ?>
                            <a href="https://wa.me/<?= esc(preg_replace('/\D/', '', $registro['telefono'])) ?>" target="_blank" rel="noopener"><?= esc($registro['telefono']) ?></a>
                        <?php else: ?>—<?php endif ?>
                    </td></tr>
                    <tr><td class="text-muted">Correo</td><td><?= esc($registro['email'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Llegada estimada</td><td><?= esc($registro['hora_llegada'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Vehículo</td><td><?= esc($registro['placa_vehiculo'] ?: '—') ?></td></tr>
                </table>
                <?php if ($registro['observaciones']): ?>
                    <div class="alert alert-info mt-3 mb-0 py-2 small">
                        <i class="bi bi-chat-left-text me-1"></i><?= esc($registro['observaciones']) ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <!-- ═══ Acompañantes ═══ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2"></i>Acompañantes
                <span class="badge text-bg-light border ms-1"><?= count($acompanantes) ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($acompanantes)): ?>
                    <div class="list-group-item text-muted text-center py-4">Viaja solo.</div>
                <?php endif ?>
                <?php foreach ($acompanantes as $a): ?>
                    <div class="list-group-item">
                        <div class="fw-semibold">
                            <?= esc($a['nombre'] . ' ' . $a['apellidos']) ?>
                            <?php if ($a['es_menor']): ?>
                                <span class="badge text-bg-danger">Menor de edad</span>
                            <?php endif ?>
                        </div>
                        <div class="small text-muted">
                            <?= esc($a['tipo_documento']) ?> <?= esc($a['num_documento'] ?: 'sin documento') ?>
                            · <?= esc($a['nacionalidad']) ?>
                            <?= $a['fecha_nacimiento'] ? ' · nacido el ' . date('d/m/Y', strtotime($a['fecha_nacimiento'])) : '' ?>
                            <?= $a['parentesco'] ? ' · ' . esc($a['parentesco']) : '' ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>

    <!-- ═══ Documentos y consentimientos ═══ -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-file-earmark-image me-2"></i>Documentos aportados</div>
            <div class="card-body">
                <?php if (empty($documentos)): ?>
                    <p class="text-muted mb-0 text-center py-3">Sin documentos.</p>
                <?php endif ?>
                <div class="row g-3">
                    <?php foreach ($documentos as $d): ?>
                        <div class="col-6">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="small fw-semibold mb-2"><?= esc(\App\Models\DocumentoModel::TIPOS[$d['tipo']] ?? 'Documento') ?></div>
                                <?php if (str_contains((string) $d['mime'], 'image')): ?>
                                    <a href="<?= site_url('registros/documento/' . $d['id']) ?>" target="_blank" rel="noopener">
                                        <img src="<?= site_url('registros/documento/' . $d['id']) ?>" alt="Documento"
                                             class="img-fluid rounded" style="max-height:190px; width:100%; object-fit:cover">
                                    </a>
                                <?php else: ?>
                                    <a href="<?= site_url('registros/documento/' . $d['id']) ?>" target="_blank" rel="noopener"
                                       class="btn btn-outline-primary btn-sm w-100">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Abrir PDF
                                    </a>
                                <?php endif ?>
                                <div class="small text-muted mt-2"><?= round($d['tamano'] / 1024) ?> KB</div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
                <?php if (! empty($documentos)): ?>
                    <p class="small text-muted mt-3 mb-0">
                        <i class="bi bi-lock me-1"></i>
                        Los documentos no son accesibles desde internet: solo se muestran aquí, al personal con sesión iniciada,
                        y cada consulta queda registrada.
                    </p>
                <?php endif ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-vector-pen me-2"></i>Firma y consentimientos</div>
            <div class="card-body">
                <?php if ($registro['firma_archivo']): ?>
                    <div class="border rounded-3 p-2 mb-3 text-center bg-light">
                        <img src="<?= site_url('registros/firma/' . $registro['id']) ?>" alt="Firma del huésped"
                             class="img-fluid" style="max-height:130px">
                    </div>
                <?php endif ?>
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted">Tratamiento de datos</td>
                        <td><?= $registro['acepta_datos'] ? '<span class="badge text-bg-success">Autorizado</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Reglamento</td>
                        <td><?= $registro['acepta_reglamento'] ? '<span class="badge text-bg-success">Aceptado</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Aviso de protección de menores</td>
                        <td><?= $registro['acepta_escnna'] ? '<span class="badge text-bg-success">Leído</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Comunicaciones comerciales</td>
                        <td><?= $registro['acepta_marketing'] ? '<span class="badge text-bg-info">Sí</span>' : '<span class="text-muted small">No autorizadas</span>' ?></td>
                    </tr>
                    <tr><td class="text-muted">Versión de la política</td><td><?= esc($registro['version_politica'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted">Firmado</td><td><?= $registro['firmado_en'] ? date('d/m/Y H:i', strtotime($registro['firmado_en'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Desde la IP</td><td><code class="small"><?= esc($registro['firma_ip'] ?: '—') ?></code></td></tr>
                </table>
            </div>
        </div>

        <!-- ═══ Reportes a autoridades ═══ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-check me-2"></i>Reportes obligatorios</div>
            <div class="card-body">
                <p class="small text-muted">
                    Marca aquí cuando hayas hecho el reporte en el sistema oficial correspondiente.
                    El sistema no transmite por ti: te ayuda a llevar el control.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="post" action="<?= site_url('registros/reporte/' . $registro['id']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="cual" value="tra">
                        <button class="btn btn-sm btn-<?= $registro['reportado_tra'] ? 'success' : 'outline-secondary' ?>">
                            <i class="bi bi-<?= $registro['reportado_tra'] ? 'check-circle-fill' : 'circle' ?> me-1"></i>
                            TRA · Tarjeta de Registro de Alojamiento
                        </button>
                    </form>
                    <?php if ($registro['hay_extranjeros']): ?>
                        <form method="post" action="<?= site_url('registros/reporte/' . $registro['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cual" value="sire">
                            <button class="btn btn-sm btn-<?= $registro['reportado_sire'] ? 'success' : 'outline-secondary' ?>">
                                <i class="bi bi-<?= $registro['reportado_sire'] ? 'check-circle-fill' : 'circle' ?> me-1"></i>
                                SIRE · Migración Colombia
                            </button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h2 class="h6 text-muted mb-2">Enlace del huésped</h2>
        <div class="input-group">
            <input type="text" class="form-control font-monospace small" value="<?= esc($enlace) ?>" readonly id="enlaceRegistro">
            <button class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('enlaceRegistro').value); this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copiado';">
                <i class="bi bi-clipboard"></i> Copiar
            </button>
            <?php if ($registro['telefono']): ?>
                <a class="btn btn-success" target="_blank" rel="noopener"
                   href="https://wa.me/<?= esc(preg_replace('/\D/', '', $registro['telefono'])) ?>?text=<?= rawurlencode('Hola ' . $registro['nombre'] . ', para agilizar tu llegada a ' . config('Hotel')->nombre . ' completa tu registro aquí: ' . $enlace) ?>">
                    <i class="bi bi-whatsapp"></i> Enviar por WhatsApp
                </a>
            <?php endif ?>
        </div>
        <?php if ($registro['email']): ?>
            <form method="post" action="<?= site_url('registros/enviar-enlace/' . $registro['id']) ?>" class="mt-2">
                <?= csrf_field() ?>
                <button class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-envelope me-1"></i>Enviar por correo a <?= esc($registro['email']) ?>
                </button>
            </form>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
