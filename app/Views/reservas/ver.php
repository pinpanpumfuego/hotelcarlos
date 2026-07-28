<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$coloresEstado = ['pendiente' => 'warning', 'confirmada' => 'primary', 'checkin' => 'success', 'checkout' => 'secondary', 'cancelada' => 'danger'];
$etiquetas     = ['pendiente' => 'Pendiente de confirmar', 'confirmada' => 'Confirmada', 'checkin' => 'Huésped alojado', 'checkout' => 'Finalizada', 'cancelada' => 'Cancelada'];
$noches        = (new DateTime($reserva['fecha_entrada']))->diff(new DateTime($reserva['fecha_salida']))->days;
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('reservas') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Reservas</a>
        <h1 class="h3 mb-0 mt-1">
            <?= esc($reserva['codigo']) ?>
            <span class="badge text-bg-<?= $coloresEstado[$reserva['estado']] ?> fs-6 align-middle ms-2"><?= $etiquetas[$reserva['estado']] ?></span>
        </h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($reserva['estado'] === 'pendiente'): ?>
            <form action="<?= site_url('reservas/confirmar/' . $reserva['id']) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Confirmar reserva</button>
            </form>
        <?php endif ?>
        <?php if (in_array($reserva['estado'], ['pendiente', 'confirmada'], true)): ?>
            <form action="<?= site_url('reservas/checkin/' . $reserva['id']) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-success"><i class="bi bi-box-arrow-in-right me-1"></i>Check-in</button>
            </form>
            <a href="<?= site_url('reservas/editar/' . $reserva['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
            <form action="<?= site_url('reservas/cancelar/' . $reserva['id']) ?>" method="post"
                  onsubmit="return confirm('¿Cancelar la reserva <?= esc($reserva['codigo'], 'js') ?>?');">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
            </form>
        <?php endif ?>
        <?php if ($reserva['estado'] === 'checkin'): ?>
            <form action="<?= site_url('reservas/checkout/' . $reserva['id']) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-secondary"><i class="bi bi-box-arrow-right me-1"></i>Check-out</button>
            </form>
        <?php endif ?>
        <?php if (in_array($reserva['estado'], ['checkin', 'checkout'], true)): ?>
            <a href="<?= site_url('facturas/reserva/' . $reserva['id']) ?>" class="btn btn-outline-success">
                <i class="bi bi-receipt-cutoff me-1"></i>Facturar
            </a>
        <?php endif ?>
    </div>
</div>

<div class="row g-4">
    <!-- Columna izquierda: detalles -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2"></i>Huésped</div>
            <div class="card-body">
                <p class="fs-5 fw-semibold mb-1"><?= esc($reserva['h_nombre']) ?> <?= esc($reserva['h_apellidos']) ?></p>
                <p class="text-muted mb-2"><?= esc($reserva['tipo_documento']) ?> <?= esc($reserva['num_documento']) ?></p>
                <?php if ($reserva['h_telefono']): ?>
                    <p class="mb-1"><i class="bi bi-telephone me-2 text-muted"></i><a href="tel:<?= esc($reserva['h_telefono']) ?>"><?= esc($reserva['h_telefono']) ?></a>
                        <a class="ms-2 small" href="https://wa.me/<?= esc(preg_replace('/\D/', '', $reserva['h_telefono'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </p>
                <?php endif ?>
                <?php if ($reserva['h_email']): ?>
                    <p class="mb-0"><i class="bi bi-envelope me-2 text-muted"></i><a href="mailto:<?= esc($reserva['h_email']) ?>"><?= esc($reserva['h_email']) ?></a></p>
                <?php endif ?>
            </div>
        </div>

        <!-- Registro en línea del huésped -->
        <?php if (! in_array($reserva['estado'], ['cancelada', 'checkout'], true)): ?>
            <?php
            $colorReg = ['pendiente' => 'secondary', 'enviado' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger'];
            ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Registro de llegada</div>
                <div class="card-body">
                    <?php if ($registro === null): ?>
                        <p class="text-muted small">
                            Genera un enlace para que el huésped complete su registro desde el móvil antes de llegar:
                            datos, acompañantes, documento y firma.
                        </p>
                        <form action="<?= site_url('registros/generar/' . $reserva['id']) ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary"><i class="bi bi-link-45deg me-1"></i>Generar enlace de registro</button>
                        </form>
                    <?php else: ?>
                        <p class="mb-2">
                            Estado:
                            <span class="badge text-bg-<?= $colorReg[$registro['estado']] ?>">
                                <?= esc(\App\Models\RegistroModel::ESTADOS[$registro['estado']]) ?>
                            </span>
                        </p>
                        <a href="<?= site_url('registros/ver/' . $registro['id']) ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Ver el registro
                        </a>
                        <a href="<?= site_url('registro/' . $registro['token']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Abrir como el huésped
                        </a>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-house-heart me-2"></i>Estancia</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-5">
                        <div class="text-muted small">Llegada</div>
                        <div class="fs-5 fw-semibold"><?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></div>
                    </div>
                    <div class="col-2 align-self-center text-muted"><i class="bi bi-arrow-right"></i></div>
                    <div class="col-5">
                        <div class="text-muted small">Salida</div>
                        <div class="fs-5 fw-semibold"><?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></div>
                    </div>
                </div>
                <p class="mb-1">
                    <i class="bi bi-door-open me-2 text-muted"></i>
                    <?php if ($reserva['unidad_nombre'] === null): ?>
                        <span class="badge text-bg-warning">Sin cabaña asignada</span>
                    <?php else: ?>
                        <?= esc($reserva['unidad_nombre']) ?> · <?= esc($reserva['tipo_nombre']) ?>
                    <?php endif ?>
                </p>
                <p class="mb-1"><i class="bi bi-moon me-2 text-muted"></i><?= $noches ?> noche<?= $noches > 1 ? 's' : '' ?></p>
                <p class="mb-1"><i class="bi bi-people me-2 text-muted"></i><?= esc($reserva['adultos']) ?> adulto<?= $reserva['adultos'] > 1 ? 's' : '' ?><?= $reserva['ninos'] > 0 ? ' y ' . esc($reserva['ninos']) . ' niño' . ($reserva['ninos'] > 1 ? 's' : '') : '' ?></p>
                <?php if (trim((string) $reserva['notas']) !== ''): ?>
                    <hr>
                    <p class="small text-muted mb-0"><i class="bi bi-sticky me-2"></i><?= esc($reserva['notas']) ?></p>
                <?php endif ?>
            </div>

            <?php $desglose = \App\Libraries\MotorTarifas::leerResumen($reserva['desglose_precio'] ?? null); ?>
            <div class="card-footer bg-white p-0">
                <button class="btn btn-link w-100 text-decoration-none text-start px-3 py-2 small collapsed"
                        data-bs-toggle="collapse" data-bs-target="#desglosePrecio">
                    <i class="bi bi-receipt me-2"></i>Cómo se calculó el precio
                    <span class="fw-semibold ms-1">$<?= number_format((float) $reserva['total'], 0, ',', '.') ?></span>
                </button>
                <div class="collapse" id="desglosePrecio">
                    <div class="px-3 pb-3">
                        <?php if ($desglose === null): ?>
                            <p class="small text-muted mb-0">
                                Esta reserva se creó antes del motor de tarifas, o su precio se puso a mano.
                                El total guardado es el que manda.
                            </p>
                        <?php else: ?>
                            <table class="table table-sm align-middle mb-2">
                                <tbody>
                                    <?php foreach ($desglose['noches'] as $n): ?>
                                        <tr>
                                            <td class="ps-0">
                                                <div class="small fw-semibold"><?= date('d/m', strtotime($n['f'])) ?></div>
                                                <?php foreach ($n['a'] as $a): ?>
                                                    <div class="text-muted" style="font-size: .76rem;">
                                                        <?= esc($a[0]) ?> <span class="opacity-75">(<?= esc($a[1]) ?>)</span>
                                                        <?= $a[2] >= 0 ? '+' : '−' ?>$<?= number_format(abs((float) $a[2]), 0, ',', '.') ?>
                                                    </div>
                                                <?php endforeach ?>
                                            </td>
                                            <td class="text-end pe-0 small">$<?= number_format((float) $n['p'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php foreach ($desglose['suplementos'] as $s): ?>
                                        <tr>
                                            <td class="ps-0 small"><?= esc($s['concepto']) ?></td>
                                            <td class="text-end pe-0 small">$<?= number_format((float) $s['importe'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <tr class="border-top">
                                        <td class="ps-0 fw-semibold small">Total alojamiento</td>
                                        <td class="text-end pe-0 fw-semibold small">$<?= number_format((float) $desglose['total'], 0, ',', '.') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="text-muted mb-0" style="font-size: .76rem;">
                                Precio congelado el <?= date('d/m/Y \a \l\a\s H:i', strtotime($desglose['calculado'])) ?>.
                            </p>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna derecha: folio -->
    <div class="col-lg-7">
        <!-- ── Experiencias contratadas ── -->
        <?php if ($experiencias !== [] && ! in_array($reserva['estado'], ['cancelada'], true)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="bi bi-compass me-2"></i>Experiencias</span>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalVender">
                        <i class="bi bi-plus-lg me-1"></i>Apuntar
                    </button>
                </div>
                <?php if ($actividades === []): ?>
                    <div class="card-body text-muted small text-center py-3">
                        Todavía no ha contratado ninguna actividad.
                        <span class="d-block">Es el momento de ofrecerle la cabalgata o el paseo en lancha.</span>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($actividades as $a): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-2 <?= in_array($a['estado'], ['cancelada', 'no_show'], true) ? 'opacity-50' : '' ?>">
                                <div>
                                    <span class="fw-semibold"><?= esc($a['experiencia']) ?></span>
                                    <div class="text-muted small">
                                        <?= date('d/m/Y', strtotime($a['fecha'])) ?>
                                        <?= $a['hora'] !== null ? ' a las ' . substr($a['hora'], 0, 5) : '' ?>
                                        · <?= (int) $a['adultos'] + (int) $a['ninos'] ?> pers.
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold">$<?= number_format((float) $a['total'], 0, ',', '.') ?></div>
                                    <span class="badge text-bg-<?= \App\Models\ExperienciaReservaModel::COLORES[$a['estado']] ?>">
                                        <?= \App\Models\ExperienciaReservaModel::ESTADOS[$a['estado']] ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>

            <?= view('experiencias/_modal_vender', [
                'catalogo' => $experiencias,
                'fecha'    => max(date('Y-m-d'), $reserva['fecha_entrada']),
                'reserva'  => $reserva,
                'alojados' => [],
            ]) ?>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-receipt me-2"></i>Folio de la cuenta</span>
                <?php if (abs($saldo) < 0.01): ?>
                    <span class="badge text-bg-success fs-6">Saldo en cero</span>
                <?php elseif ($saldo > 0): ?>
                    <span class="badge text-bg-danger fs-6">Pendiente: $<?= number_format($saldo, 0, ',', '.') ?></span>
                <?php else: ?>
                    <span class="badge text-bg-info fs-6">A favor del huésped: $<?= number_format(abs($saldo), 0, ',', '.') ?></span>
                <?php endif ?>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th><th>Concepto</th>
                            <th class="text-end">Cargo</th>
                            <th class="text-end">Descuento</th>
                            <th class="text-end">Pago</th>
                            <th class="d-none d-lg-table-cell">Registró</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Sin movimientos todavía.</td></tr>
                        <?php endif ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td class="text-muted small"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
                                <td>
                                    <?php if ($m['tipo'] === 'descuento'): ?>
                                        <i class="bi bi-ticket-perforated me-1 text-success"></i>
                                    <?php endif ?>
                                    <?= esc($m['concepto']) ?>
                                </td>
                                <td class="text-end"><?= $m['tipo'] === 'cargo' ? '$' . number_format((float) $m['valor'], 0, ',', '.') : '' ?></td>
                                <td class="text-end text-success"><?= $m['tipo'] === 'descuento' ? '−$' . number_format((float) $m['valor'], 0, ',', '.') : '' ?></td>
                                <td class="text-end text-success"><?= $m['tipo'] === 'pago' ? '$' . number_format((float) $m['valor'], 0, ',', '.') : '' ?></td>
                                <td class="text-muted small d-none d-lg-table-cell"><?= esc($m['usuario_nombre'] ?? 'Web') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>

            <?php if (! in_array($reserva['estado'], ['cancelada', 'checkout'], true)): ?>
                <div class="card-body border-top">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h3 class="h6 text-muted mb-3">Cupón de descuento</h3>
                            <form action="<?= site_url('reservas/folio/cupon/' . $reserva['id']) ?>" method="post" class="d-flex gap-2 flex-wrap">
                                <?= csrf_field() ?>
                                <input type="text" name="codigo" class="form-control text-uppercase" placeholder="CÓDIGO DEL CUPÓN"
                                       autocomplete="off" required style="flex: 2; min-width: 150px;">
                                <button class="btn btn-outline-success"><i class="bi bi-ticket-perforated"></i></button>
                            </form>
                            <div class="form-text">Rebaja los cargos del folio.</div>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6 text-muted mb-3">Bono regalo</h3>
                            <form action="<?= site_url('reservas/folio/bono/' . $reserva['id']) ?>" method="post" class="d-flex gap-2 flex-wrap">
                                <?= csrf_field() ?>
                                <input type="text" name="codigo" class="form-control text-uppercase" placeholder="BR-XXXX-XXXX"
                                       autocomplete="off" required style="flex: 2; min-width: 150px;">
                                <button class="btn btn-outline-primary"><i class="bi bi-gift"></i></button>
                            </form>
                            <div class="form-text">Paga con el saldo del bono; no es un descuento.</div>
                        </div>
                        <?php // Tarjeta de saldo: cobra todo lo pendiente de golpe. Si la
                              // tarjeta tiene descuento, se apunta aparte como descuento
                              // para que se vea cuánto se está regalando. ?>
                        <?php if ($saldo > 0 && puede('tarjetas.cobrar')): ?>
                            <div class="col-md-6">
                                <h3 class="h6 text-muted mb-3">Tarjeta de saldo</h3>
                                <form action="<?= site_url('tarjetas/cobrar-folio/' . $reserva['id']) ?>" method="post" class="d-flex gap-2 flex-wrap">
                                    <?= csrf_field() ?>
                                    <input type="text" name="codigo" class="form-control text-uppercase" placeholder="TJ-XXXXXX"
                                           autocomplete="off" required style="flex: 2; min-width: 130px;">
                                    <input type="password" name="pin" class="form-control" placeholder="PIN"
                                           inputmode="numeric" maxlength="6" autocomplete="off"
                                           style="flex: 1; min-width: 70px;">
                                    <button class="btn btn-outline-primary"><i class="bi bi-credit-card-2-front"></i></button>
                                </form>
                                <div class="form-text">
                                    Cobra hasta $<?= number_format($saldo, 0, ',', '.') ?>. Si no llega, el resto
                                    se cobra por otro medio. El PIN solo hace falta a partir del importe
                                    que marque su modalidad.
                                </div>
                            </div>
                        <?php endif ?>
                        <div class="col-md-6">
                            <h3 class="h6 text-muted mb-3">Añadir cargo</h3>
                            <form action="<?= site_url('reservas/folio/cargo/' . $reserva['id']) ?>" method="post" class="d-flex gap-2 flex-wrap">
                                <?= csrf_field() ?>
                                <input type="text" name="concepto" class="form-control" placeholder="Concepto (cena, minibar...)" required style="min-width: 150px; flex: 2;">
                                <input type="number" name="valor" class="form-control" placeholder="Valor" min="1" step="any" required style="flex: 1; min-width: 90px;">
                                <button class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i></button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6 text-muted mb-3">Registrar pago</h3>
                            <form action="<?= site_url('reservas/folio/pago/' . $reserva['id']) ?>" method="post" class="d-flex gap-2 flex-wrap">
                                <?= csrf_field() ?>
                                <input type="number" name="valor" class="form-control" placeholder="Valor" min="1" step="any" required
                                       value="<?= $saldo > 0 ? esc($saldo) : '' ?>" style="flex: 1; min-width: 90px;">
                                <select name="metodo" class="form-select" style="flex: 1; min-width: 120px;">
                                    <?php foreach ($metodos as $clave => $etiqueta): ?>
                                        <option value="<?= $clave ?>"><?= $etiqueta ?></option>
                                    <?php endforeach ?>
                                </select>
                                <button class="btn btn-success"><i class="bi bi-cash-coin"></i></button>
                            </form>
                        </div>
                    </div>

                    <?php // Cargar a una empresa: el folio queda saldado y la
                          // deuda pasa a la cuenta, con su fecha de vencimiento. ?>
                    <?php if ($yaEnCartera !== null): ?>
                        <hr>
                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-briefcase me-1"></i>
                            Esta reserva se cargó a una cuenta de empresa el
                            <?= date('d/m/Y', strtotime($yaEnCartera['fecha'])) ?>.
                            <a href="<?= site_url('cartera/ver/' . $yaEnCartera['cuenta_id']) ?>">Ver la cuenta</a>.
                        </div>
                    <?php elseif ($cuentas !== [] && $saldo > 0 && puede('cartera.gestionar')): ?>
                        <hr>
                        <h3 class="h6 text-muted mb-2"><i class="bi bi-briefcase me-1"></i>Cargar a una empresa</h3>
                        <p class="small text-muted">
                            El folio queda saldado y la deuda pasa a la cuenta de la empresa, con su
                            plazo de pago. Se comprueba el cupo antes de cargar.
                        </p>
                        <form method="post" action="<?= site_url('cartera/cargar') ?>" class="d-flex gap-2 flex-wrap">
                            <?= csrf_field() ?>
                            <input type="hidden" name="reserva_id" value="<?= $reserva['id'] ?>">
                            <select name="cuenta_id" class="form-select form-select-sm" required
                                    style="flex: 2; min-width: 180px;">
                                <option value="">Elige la cuenta…</option>
                                <?php foreach ($cuentas as $cta): ?>
                                    <option value="<?= $cta['id'] ?>"><?= esc($cta['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-arrow-right me-1"></i>Cargar $<?= number_format($saldo, 0, ',', '.') ?>
                            </button>
                        </form>
                    <?php endif ?>

                    <?php if ($cobroOnline && $saldo > 0): ?>
                        <?php $enlacePago = site_url('pago/reserva/' . $reserva['codigo'] . '/total'); ?>
                        <hr>
                        <h3 class="h6 text-muted mb-2"><i class="bi bi-credit-card me-1"></i>Cobrar en línea</h3>
                        <p class="small text-muted">
                            Mándale este enlace al huésped para que pague con tarjeta, PSE o Nequi.
                            El pago entra solo en el folio; no hay que anotarlo a mano.
                        </p>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control font-monospace small" id="enlacePago"
                                   value="<?= esc($enlacePago) ?>" readonly onfocus="this.select()">
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('enlacePago').value);
                                             this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copiado';">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($reserva['h_telefono']): ?>
                                <a class="btn btn-success btn-sm"
                                   href="https://wa.me/<?= esc(preg_replace('/\D/', '', $reserva['h_telefono'])) ?>?text=<?= rawurlencode('Hola ' . $reserva['h_nombre'] . ', aquí puedes pagar tu reserva ' . $reserva['codigo'] . ': ' . $enlacePago) ?>"
                                   target="_blank" rel="noopener">
                                    <i class="bi bi-whatsapp me-1"></i>Enviar por WhatsApp
                                </a>
                            <?php endif ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= esc($enlacePago) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Abrirlo como el huésped
                            </a>
                        </div>
                    <?php endif ?>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
