<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Puntos de caja y medios de pago</h1>
        <p class="text-muted mb-0">Dónde se cobra y con qué se puede pagar.</p>
    </div>
    <a href="<?= site_url('caja') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<!-- ═══ Medios de pago ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2 me-2"></i>Medios de pago</div>
    <div class="card-body py-2">
        <p class="small text-muted mb-0">
            <strong>«Entra en el cajón» es la casilla que importa.</strong> Un cobro con tarjeta es
            un ingreso, pero no hay un peso más dentro del cajón. Si se marcara, la caja
            «faltaría» todos los días exactamente lo cobrado con tarjeta, nadie se fiaría del
            arqueo, y a partir de ahí un robo pequeño no se nota nunca.
        </p>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Medio</th>
                    <th class="text-center">Entra en<br>el cajón</th>
                    <th class="text-center">Pide<br>referencia</th>
                    <th class="text-center d-none d-lg-table-cell">Comisión</th>
                    <th class="text-center d-none d-lg-table-cell">Cuenta<br>contable</th>
                    <th class="text-center">Dónde</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medios as $m): ?>
                    <?php $f = 'medio-' . $m['id']; ?>
                    <tr class="<?= (int) $m['activo'] === 0 ? 'opacity-50' : '' ?>">
                        <td>
                            <input type="text" name="nombre" form="<?= $f ?>" class="form-control form-control-sm"
                                   value="<?= esc($m['nombre']) ?>" maxlength="60"
                                   <?= puede('medios.gestionar') ? '' : 'disabled' ?>>
                            <div class="small text-muted font-monospace mt-1">
                                <?= esc($m['clave']) ?> · <?= esc($tipos_medio[$m['tipo']]) ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <input class="form-check-input" type="checkbox" name="afecta_caja" value="1" form="<?= $f ?>"
                                   <?= (int) $m['afecta_caja'] === 1 ? 'checked' : '' ?>
                                   <?= puede('medios.gestionar') ? '' : 'disabled' ?>>
                        </td>
                        <td class="text-center">
                            <input class="form-check-input" type="checkbox" name="requiere_referencia" value="1" form="<?= $f ?>"
                                   <?= (int) $m['requiere_referencia'] === 1 ? 'checked' : '' ?>
                                   <?= puede('medios.gestionar') ? '' : 'disabled' ?>>
                        </td>
                        <td class="d-none d-lg-table-cell" style="width: 100px;">
                            <div class="input-group input-group-sm">
                                <input type="number" name="comision_pct" form="<?= $f ?>" class="form-control"
                                       min="0" max="20" step="any" value="<?= (float) $m['comision_pct'] ?>"
                                       <?= puede('medios.gestionar') ? '' : 'disabled' ?>>
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell" style="width: 110px;">
                            <input type="text" name="cuenta_contable" form="<?= $f ?>" class="form-control form-control-sm font-monospace"
                                   maxlength="30" value="<?= esc($m['cuenta_contable']) ?>" placeholder="—"
                                   <?= puede('medios.gestionar') ? '' : 'disabled' ?>>
                        </td>
                        <td class="text-center small" style="width: 140px;">
                            <?php foreach (['en_recepcion' => 'Recep.', 'en_tpv' => 'TPV', 'en_web' => 'Web'] as $campo => $etiqueta): ?>
                                <div class="form-check form-check-inline me-1">
                                    <input class="form-check-input" type="checkbox" name="<?= $campo ?>" value="1" form="<?= $f ?>"
                                           id="<?= $f ?>-<?= $campo ?>" <?= (int) $m[$campo] === 1 ? 'checked' : '' ?>
                                           <?= puede('medios.gestionar') ? '' : 'disabled' ?>>
                                    <label class="form-check-label" for="<?= $f ?>-<?= $campo ?>" style="font-size: .72rem;">
                                        <?= $etiqueta ?>
                                    </label>
                                </div>
                            <?php endforeach ?>
                        </td>
                        <td class="text-end" style="width: 110px;">
                            <?php if (puede('medios.gestionar')): ?>
                                <div class="form-check form-switch d-inline-block align-middle me-1">
                                    <input class="form-check-input" type="checkbox" name="activo" value="1" form="<?= $f ?>"
                                           <?= (int) $m['activo'] === 1 ? 'checked' : '' ?>>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" form="<?= $f ?>"><i class="bi bi-check-lg"></i></button>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php // Los formularios van fuera de la tabla: un <form> entre <tr> y <td>
          // es HTML inválido y el navegador lo saca de la tabla. ?>
    <?php if (puede('medios.gestionar')): ?>
        <?php foreach ($medios as $m): ?>
            <form method="post" action="<?= site_url('caja/medio/' . $m['id']) ?>" id="medio-<?= $m['id'] ?>">
                <?= csrf_field() ?>
            </form>
        <?php endforeach ?>
    <?php endif ?>

    <div class="card-body border-top">
        <div class="alert alert-light border small mb-0">
            <i class="bi bi-calculator me-1"></i>
            La <strong>cuenta contable</strong> se deja en blanco a propósito. La rellena tu contador
            con su plan de cuentas: un número que ponga yo acaba en un balance.
        </div>
    </div>
</div>

<!-- ═══ Puntos de caja ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-shop me-2"></i>Puntos de caja</div>
    <div class="card-body py-2">
        <p class="small text-muted mb-0">
            La caja de recepción y la del bar no son la misma plata ni la cuenta la misma persona.
            Con una caja única, un descuadre en el bar se le apunta a quien estaba en recepción — y
            eso no es un problema de contabilidad, es un problema entre dos personas que trabajan
            juntas mañana.
        </p>
    </div>

    <?php foreach ($puntos as $p): ?>
        <div class="card-body border-top">
            <form method="post" action="<?= site_url('caja/punto/' . $p['id']) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Nombre</label>
                    <input type="text" name="nombre" class="form-control form-control-sm" required maxlength="60"
                           value="<?= esc($p['nombre']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <?php foreach ($tipos_punto as $t => $etiqueta): ?>
                            <option value="<?= $t ?>" <?= $p['tipo'] === $t ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Base sugerida</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" name="base_sugerida" class="form-control" min="0" step="any"
                               value="<?= (float) $p['base_sugerida'] ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Tolerancia</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" name="tolerancia" class="form-control" min="0" step="any"
                               value="<?= (float) $p['tolerancia'] ?>">
                    </div>
                    <div class="form-text" style="font-size: .72rem;">Por encima hay que explicarlo.</div>
                </div>
                <div class="col-md-2 d-flex align-items-center pb-1">
                    <div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="exige_denominaciones" value="1"
                                   id="den<?= $p['id'] ?>" <?= (int) $p['exige_denominaciones'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="den<?= $p['id'] ?>">Contar billetes</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" value="1"
                                   id="act<?= $p['id'] ?>" <?= (int) $p['activo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="act<?= $p['id'] ?>">Activo</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-check-lg"></i></button>
                </div>
            </form>
        </div>
    <?php endforeach ?>

    <div class="card-body border-top">
        <form method="post" action="<?= site_url('caja/punto') ?>" class="d-flex gap-2 flex-wrap">
            <?= csrf_field() ?>
            <input type="text" name="nombre" class="form-control form-control-sm" required maxlength="60"
                   placeholder="Nombre del punto nuevo" style="flex: 2; min-width: 160px;">
            <select name="tipo" class="form-select form-select-sm" style="flex: 1; min-width: 120px;">
                <?php foreach ($tipos_punto as $t => $etiqueta): ?>
                    <option value="<?= $t ?>"><?= esc($etiqueta) ?></option>
                <?php endforeach ?>
            </select>
            <input type="hidden" name="exige_denominaciones" value="1">
            <input type="hidden" name="activo" value="1">
            <input type="hidden" name="tolerancia" value="2000">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Añadir</button>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
