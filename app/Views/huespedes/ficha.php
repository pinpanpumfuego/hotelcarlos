<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$colorEstado = [
    'pendiente' => 'secondary', 'confirmada' => 'primary', 'checkin' => 'success',
    'checkout' => 'dark', 'cancelada' => 'danger', 'no_show' => 'warning',
];
$iconoPref = [
    'alergia' => 'exclamation-triangle-fill', 'dieta' => 'egg-fried',
    'accesibilidad' => 'universal-access', 'habitacion' => 'door-open',
    'interes' => 'binoculars', 'celebracion' => 'balloon', 'otro' => 'sticky',
];
$alergias = array_filter($preferencias, static fn (array $p): bool => $p['tipo'] === 'alergia');
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">
            <?= esc($huesped['nombre'] . ' ' . $huesped['apellidos']) ?>
            <?php if ($nivel !== null): ?>
                <span class="badge text-bg-<?= esc($nivel['color']) ?> align-middle"><?= esc($nivel['nombre']) ?></span>
            <?php endif ?>
            <?php if ((int) $huesped['vip'] === 1): ?>
                <span class="badge text-bg-warning align-middle"><i class="bi bi-star-fill me-1"></i>VIP</span>
            <?php endif ?>
        </h1>
        <p class="text-muted mb-0">
            <?= esc($huesped['tipo_documento']) ?> <?= esc($huesped['num_documento']) ?>
            <?php if ($huesped['origen']): ?>
                · Nos conoció por <?= esc($origenes[$huesped['origen']] ?? $huesped['origen']) ?>
            <?php endif ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (puede('huespedes.editar')): ?>
            <a href="<?= site_url('huespedes/editar/' . $huesped['id']) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Editar
            </a>
        <?php endif ?>
        <a href="<?= site_url('huespedes') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<?php // Lo primero de la pantalla, siempre. Una alergia enterrada tres cajas
      // más abajo es una alergia que alguien no va a ver. ?>
<?php if ($alergias !== []): ?>
    <div class="alert alert-danger d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        <div>
            <strong>Alergias</strong>
            <div>
                <?php foreach ($alergias as $a): ?>
                    <span class="badge text-bg-danger me-1"><?= esc($a['valor']) ?></span>
                <?php endforeach ?>
            </div>
            <div class="small mt-1">Avisa a cocina antes de servirle nada.</div>
        </div>
    </div>
<?php elseif (! $ver_sensibles): ?>
    <div class="alert alert-light border small">
        <i class="bi bi-shield-lock me-1"></i>
        Las alergias y los datos de salud no se muestran con tu perfil. Si necesitas verlos
        para hacer tu trabajo, pídeselo a gerencia.
    </div>
<?php endif ?>

<!-- ═══ Lo que vale ═══ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Estancias</div>
                <div class="fs-3 fw-semibold"><?= $valor['estancias'] ?></div>
                <div class="small text-muted"><?= $valor['noches'] ?> noches en total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Ha dejado</div>
                <div class="fs-3 fw-semibold"><?= $dinero($valor['gasto']) ?></div>
                <div class="small text-muted">alojamiento y extras</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Última vez</div>
                <div class="fs-5 fw-semibold">
                    <?= $valor['ultima'] !== null ? date('d/m/Y', strtotime($valor['ultima'])) : '—' ?>
                </div>
                <div class="small text-muted">
                    <?= $valor['dias_desde'] !== null ? 'hace ' . $valor['dias_desde'] . ' días' : 'nunca ha venido' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Cancelaciones</div>
                <div class="fs-3 fw-semibold <?= $valor['cancelaciones'] >= 2 ? 'text-warning' : '' ?>">
                    <?= $valor['cancelaciones'] ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($duplicados !== []): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-people me-2 text-warning"></i>Puede que sea la misma persona
        </div>
        <div class="card-body">
            <p class="small text-muted">
                Coinciden en documento, correo o teléfono. Al fusionarlos, las reservas, las
                preferencias y los consentimientos del otro pasan a esta ficha.
                <strong>El perfil viejo no se borra</strong>: se queda apuntando a este.
            </p>
            <?php foreach ($duplicados as $d): ?>
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap border-top py-2">
                    <div>
                        <a href="<?= site_url('huespedes/ver/' . $d['id']) ?>" class="fw-semibold text-decoration-none">
                            <?= esc($d['nombre'] . ' ' . $d['apellidos']) ?>
                        </a>
                        <div class="small text-muted">
                            <?= esc($d['num_documento']) ?>
                            <?= $d['email'] ? ' · ' . esc($d['email']) : '' ?>
                            <?= $d['telefono'] ? ' · ' . esc($d['telefono']) : '' ?>
                        </div>
                    </div>
                    <form method="post" action="<?= site_url('huespedes/fusionar/' . $huesped['id']) ?>"
                          onsubmit="return confirm('¿Fusionar ese perfil en este? No se puede deshacer desde el panel.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="perdedor_id" value="<?= $d['id'] ?>">
                        <button class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-arrow-left-right me-1"></i>Fusionarlo aquí
                        </button>
                    </form>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <!-- ═══ Historial ═══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-2"></i>Sus estancias
                <span class="badge text-bg-secondary ms-1"><?= count($reservas) ?></span>
            </div>
            <?php if ($reservas === []): ?>
                <div class="card-body text-muted small">Todavía no ha reservado nunca.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reserva</th>
                                <th>Fechas</th>
                                <th class="d-none d-md-table-cell">Cabaña</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $r): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('reservas/ver/' . $r['id']) ?>" class="font-monospace text-decoration-none">
                                            <?= esc($r['codigo']) ?>
                                        </a>
                                        <span class="badge text-bg-<?= $colorEstado[$r['estado']] ?? 'secondary' ?> ms-1">
                                            <?= esc(ucfirst($r['estado'])) ?>
                                        </span>
                                        <?php if ($r['canal']): ?>
                                            <div class="small text-muted"><?= esc($r['canal']) ?></div>
                                        <?php endif ?>
                                    </td>
                                    <td class="small">
                                        <?= date('d/m/y', strtotime($r['fecha_entrada'])) ?>
                                        → <?= date('d/m/y', strtotime($r['fecha_salida'])) ?>
                                    </td>
                                    <td class="small text-muted d-none d-md-table-cell"><?= esc($r['unidad_nombre']) ?: '—' ?></td>
                                    <td class="text-end"><?= $dinero((float) $r['total']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>

        <!-- ═══ Encuestas ═══ -->
        <?php if ($encuestas !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-star me-2"></i>Qué opinó</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($encuestas as $e): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?= $i <= (int) $e['general'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                                    <?php endfor ?>
                                    <span class="text-muted small ms-1 font-monospace"><?= esc($e['codigo']) ?></span>
                                    <?php if (trim((string) $e['comentario']) !== ''): ?>
                                        <div class="small mt-1">«<?= esc($e['comentario']) ?>»</div>
                                    <?php endif ?>
                                </div>
                                <span class="small text-muted"><?= date('d/m/Y', strtotime($e['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>

        <!-- ═══ Lo que pidió ═══ -->
        <?php if ($solicitudes !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-hand-index me-2"></i>Lo que ha pedido</div>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($solicitudes, 0, 10) as $s): ?>
                        <div class="list-group-item small d-flex justify-content-between gap-2 flex-wrap">
                            <div>
                                <strong><?= esc(\App\Models\SolicitudModel::TIPOS[$s['tipo']] ?? $s['tipo']) ?></strong>
                                <?= trim((string) $s['detalle']) !== '' ? '· ' . esc($s['detalle']) : '' ?>
                            </div>
                            <span class="text-muted"><?= date('d/m/Y', strtotime($s['created_at'])) ?></span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-5">
        <!-- ═══ Contacto ═══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-lines-fill me-2"></i>Contacto</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted fw-normal">Correo</dt>
                    <dd class="col-8">
                        <?= $huesped['email'] ? '<a href="mailto:' . esc($huesped['email']) . '">' . esc($huesped['email']) . '</a>' : '—' ?>
                    </dd>
                    <dt class="col-4 text-muted fw-normal">Teléfono</dt>
                    <dd class="col-8">
                        <?php if ($huesped['telefono']): ?>
                            <a href="tel:<?= esc($huesped['telefono']) ?>"><?= esc($huesped['telefono']) ?></a>
                            <a class="ms-1" href="https://wa.me/<?= esc(preg_replace('/\D/', '', $huesped['telefono'])) ?>"
                               target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a>
                        <?php else: ?>—<?php endif ?>
                    </dd>
                    <dt class="col-4 text-muted fw-normal">De</dt>
                    <dd class="col-8"><?= esc(trim(($huesped['ciudad'] ?? '') . ' ' . ($huesped['pais'] ?? $huesped['nacionalidad'] ?? ''))) ?: '—' ?></dd>
                    <?php if ($huesped['empresa']): ?>
                        <dt class="col-4 text-muted fw-normal">Empresa</dt>
                        <dd class="col-8"><?= esc($huesped['empresa']) ?></dd>
                    <?php endif ?>
                    <?php if (trim((string) $huesped['notas_internas']) !== ''): ?>
                        <dt class="col-12 text-muted fw-normal mt-2">
                            Notas internas
                            <span class="badge text-bg-secondary ms-1" title="El huésped no las ve">Solo personal</span>
                        </dt>
                        <dd class="col-12 mb-0"><?= nl2br(esc($huesped['notas_internas'])) ?></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <!-- ═══ Su nivel ═══ -->
        <?php if ($nivel !== null && ($beneficios !== [] || $siguiente !== null)): ?>
            <div class="card border-0 shadow-sm mb-4 border-top border-4 border-<?= esc($nivel['color']) ?>">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-award me-2"></i><?= esc($nivel['nombre']) ?>
                    <?php if ((float) $nivel['descuento_pct'] > 0): ?>
                        <span class="badge text-bg-<?= esc($nivel['color']) ?> ms-1">
                            <?= rtrim(rtrim(number_format((float) $nivel['descuento_pct'], 2, ',', '.'), '0'), ',') ?> %
                        </span>
                    <?php endif ?>
                </div>
                <div class="card-body">
                    <?php if ($beneficios !== []): ?>
                        <div class="small text-muted mb-2">Lo que le corresponde:</div>
                        <ul class="small ps-3 mb-0">
                            <?php foreach ($beneficios as $b): ?>
                                <li><?= esc($b) ?></li>
                            <?php endforeach ?>
                        </ul>
                        <?php if ((float) $nivel['descuento_pct'] > 0): ?>
                            <?php // El descuento NO se aplica solo. Un descuento
                                  // automático es dinero que se va sin que nadie
                                  // lo haya decidido esa vez. ?>
                            <div class="alert alert-light border small mt-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                El descuento <strong>no se aplica solo</strong>: hay que ponerlo a mano en
                                el folio de la reserva. Así queda claro quién lo dio y cuándo.
                            </div>
                        <?php endif ?>
                    <?php endif ?>

                    <?php if ($siguiente !== null): ?>
                        <?php // «Le falta una estancia para el siguiente nivel» es
                              // la frase que hace que alguien reserve otra vez. ?>
                        <div class="border-top mt-3 pt-3 small">
                            <span class="text-muted">Para llegar a</span>
                            <strong><?= esc($siguiente['nivel']['nombre']) ?></strong>:
                            <?php if ($siguiente['faltan_estancias'] !== null && $siguiente['faltan_estancias'] > 0): ?>
                                le falta<?= $siguiente['faltan_estancias'] > 1 ? 'n' : '' ?>
                                <strong><?= $siguiente['faltan_estancias'] ?></strong>
                                estancia<?= $siguiente['faltan_estancias'] > 1 ? 's' : '' ?>
                            <?php endif ?>
                            <?php if ($siguiente['falta_gasto'] !== null && $siguiente['falta_gasto'] > 0): ?>
                                <?= ($siguiente['faltan_estancias'] ?? 0) > 0 ? ' o ' : 'le faltan ' ?>
                                <strong><?= $dinero($siguiente['falta_gasto']) ?></strong>
                            <?php endif ?>.
                        </div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <!-- ═══ Su código de referido ═══ -->
        <?php if ($codigo_referido !== null): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-share me-2"></i>Su código para recomendar</div>
                <div class="card-body">
                    <div class="text-center border rounded py-3 mb-2" style="background: #fbfbfa;">
                        <div class="font-monospace fs-4 fw-semibold" style="letter-spacing: .15em;">
                            <?= esc($codigo_referido) ?>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">
                        Dáselo cuando se vaya contento: es el único momento en que alguien recomienda
                        algo. Los dos reciben su cupón <strong>cuando el recomendado se va</strong>,
                        no cuando reserva.
                    </p>

                    <?php if ($trajo !== []): ?>
                        <div class="border-top mt-3 pt-3">
                            <div class="small text-muted mb-2">Ha traído a:</div>
                            <?php foreach ($trajo as $t): ?>
                                <div class="d-flex justify-content-between small py-1">
                                    <span>
                                        <?= esc(trim(($t['referido_nombre'] ?? '') . ' ' . ($t['referido_apellidos'] ?? ''))) ?: 'alguien' ?>
                                        <?php if ($t['reserva_codigo']): ?>
                                            <span class="text-muted font-monospace">· <?= esc($t['reserva_codigo']) ?></span>
                                        <?php endif ?>
                                    </span>
                                    <span class="badge text-bg-<?= $t['estado'] === 'cumplido' ? 'success' : ($t['estado'] === 'anulado' ? 'secondary' : 'warning') ?>">
                                        <?= esc($estados_ref[$t['estado']]) ?>
                                    </span>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <!-- ═══ Preferencias ═══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-heart me-2"></i>Lo que sabemos de él</div>
            <?php if ($preferencias === []): ?>
                <div class="card-body text-muted small">
                    Nada apuntado. Lo que hace que alguien vuelva casi nunca es el precio:
                    es que le pongan la almohada que le gusta sin tener que pedirla.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($preferencias as $p): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <i class="bi bi-<?= $iconoPref[$p['tipo']] ?? 'sticky' ?> me-1 <?= in_array($p['tipo'], $sensibles, true) ? 'text-danger' : 'text-muted' ?>"></i>
                                <strong><?= esc($p['valor']) ?></strong>
                                <?php if ((int) $p['critica'] === 1): ?>
                                    <span class="badge text-bg-danger ms-1">Importante</span>
                                <?php endif ?>
                                <div class="small text-muted">
                                    <?= esc($tipos_pref[$p['tipo']]) ?>
                                    <?= $p['nota'] ? ' · ' . esc($p['nota']) : '' ?>
                                </div>
                            </div>
                            <?php if (puede('huespedes.editar')): ?>
                                <form method="post" action="<?= site_url('huespedes/preferencia/borrar/' . $p['id']) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                </form>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <?php if (puede('huespedes.editar')): ?>
                <div class="card-body border-top">
                    <form method="post" action="<?= site_url('huespedes/preferencia/' . $huesped['id']) ?>" class="d-flex gap-2 flex-wrap">
                        <?= csrf_field() ?>
                        <select name="tipo" class="form-select form-select-sm" style="flex: 1; min-width: 120px;">
                            <?php foreach ($tipos_pref as $t => $etiqueta): ?>
                                <?php if (in_array($t, $sensibles, true) && ! $ver_sensibles) { continue; } ?>
                                <option value="<?= $t ?>"><?= esc($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                        <input type="text" name="valor" class="form-control form-control-sm" required maxlength="150"
                               placeholder="Maní, almohada dura, aves…" style="flex: 2; min-width: 150px;">
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i></button>
                    </form>
                </div>
            <?php endif ?>
        </div>

        <!-- ═══ Consentimientos ═══ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-shield-check me-2"></i>Qué nos ha autorizado
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    <strong>Sin marcar significa que no.</strong> El silencio no es un sí, y
                    a quien no consta que autorizara nada no se le escribe.
                </p>

                <?php foreach ($consentimientos as $finalidad => $porCanal): ?>
                    <div class="border-top py-2">
                        <div class="fw-semibold small"><?= esc($finalidades[$finalidad]) ?></div>
                        <div class="d-flex gap-3 flex-wrap mt-1">
                            <?php foreach ($porCanal as $canal => $estado): ?>
                                <div>
                                    <span class="badge text-bg-<?= $estado['otorgado'] ? 'success' : 'secondary' ?>">
                                        <i class="bi bi-<?= $estado['otorgado'] ? 'check' : 'x' ?>-lg me-1"></i>
                                        <?= esc($canales[$canal]) ?>
                                    </span>
                                    <?php if (puede('consentimientos.gestionar')): ?>
                                        <form method="post" action="<?= site_url('huespedes/consentimiento/' . $huesped['id']) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="finalidad" value="<?= $finalidad ?>">
                                            <input type="hidden" name="canal" value="<?= $canal ?>">
                                            <input type="hidden" name="otorgar" value="<?= $estado['otorgado'] ? '0' : '1' ?>">
                                            <button class="btn btn-link btn-sm p-0 align-baseline text-decoration-none">
                                                <?= $estado['otorgado'] ? 'retirar' : 'autorizar' ?>
                                            </button>
                                        </form>
                                    <?php endif ?>
                                    <?php if ($estado['fila'] !== null): ?>
                                        <div class="text-muted" style="font-size: .75rem;">
                                            <?= date('d/m/Y', strtotime($estado['fila']['created_at'])) ?>
                                            · <?= esc($estado['fila']['origen']) ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>

            <?php if ($historial_consent !== []): ?>
                <div class="card-body border-top">
                    <details>
                        <summary class="small text-muted" style="cursor: pointer;">
                            Ver el rastro completo (<?= count($historial_consent) ?>)
                        </summary>
                        <div class="mt-2" style="font-size: .8rem;">
                            <?php foreach ($historial_consent as $h): ?>
                                <div class="border-top py-1">
                                    <span class="badge text-bg-<?= (int) $h['otorgado'] === 1 ? 'success' : 'secondary' ?>">
                                        <?= (int) $h['otorgado'] === 1 ? 'Autorizó' : 'Retiró' ?>
                                    </span>
                                    <?= esc($finalidades[$h['finalidad']] ?? $h['finalidad']) ?>
                                    · <?= esc($canales[$h['canal']] ?? $h['canal']) ?>
                                    <div class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                                        · <?= esc($h['origen']) ?>
                                        <?= $h['usuario_nombre'] ? ' · lo recogió ' . esc($h['usuario_nombre']) : '' ?>
                                        <?= $h['ip'] ? ' · ' . esc($h['ip']) : '' ?>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </details>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
