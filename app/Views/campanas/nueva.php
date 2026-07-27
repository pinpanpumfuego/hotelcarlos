<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Nueva campaña</h1>
        <p class="text-muted mb-0">Elige a quién, mira quiénes son, y solo entonces guarda.</p>
    </div>
    <a href="<?= site_url('campanas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <!-- ═══ Filtros ═══ -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-funnel me-2"></i>A quién</div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Cómo nos conoció</label>
                        <select name="origen" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <?php foreach ($valores['origenes'] as $c => $n): ?>
                                <option value="<?= $c ?>" <?= ($filtros['origen'] ?? '') === $c ? 'selected' : '' ?>><?= esc($n) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-6">
                        <label class="form-label">País</label>
                        <select name="pais" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <?php foreach ($valores['paises'] as $v): ?>
                                <option value="<?= esc($v, 'attr') ?>" <?= ($filtros['pais'] ?? '') === $v ? 'selected' : '' ?>><?= esc($v) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Ciudad</label>
                        <select name="ciudad" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <?php foreach ($valores['ciudades'] as $v): ?>
                                <option value="<?= esc($v, 'attr') ?>" <?= ($filtros['ciudad'] ?? '') === $v ? 'selected' : '' ?>><?= esc($v) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-6">
                        <label class="form-label">Reservó por</label>
                        <select name="canal" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <?php foreach ($valores['canales'] as $v): ?>
                                <option value="<?= esc($v, 'attr') ?>" <?= ($filtros['canal'] ?? '') === $v ? 'selected' : '' ?>><?= esc($v) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Idioma</label>
                        <select name="idioma" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <?php foreach (['es' => 'Español', 'en' => 'Inglés', 'fr' => 'Francés', 'de' => 'Alemán'] as $c => $n): ?>
                                <option value="<?= $c ?>" <?= ($filtros['idioma'] ?? '') === $c ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-6">
                        <label class="form-label">Ha venido al menos</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="estancias_min" class="form-control" min="0"
                                   value="<?= esc($filtros['estancias_min'] ?? '') ?>">
                            <span class="input-group-text">veces</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Ha dejado más de</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="gasto_min" class="form-control" min="0" step="any"
                                   value="<?= esc($filtros['gasto_min'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-6">
                        <label class="form-label">Sin venir desde hace</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="sin_venir_min" class="form-control" min="0"
                                   value="<?= esc($filtros['sin_venir_min'] ?? '') ?>">
                            <span class="input-group-text">días</span>
                        </div>
                        <div class="form-text">Solo cuenta a quien ya vino alguna vez.</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Y no más de</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="sin_venir_max" class="form-control" min="0"
                                   value="<?= esc($filtros['sin_venir_max'] ?? '') ?>">
                            <span class="input-group-text">días</span>
                        </div>
                    </div>

                    <div class="col-6">
                        <label class="form-label">Cumple años en</label>
                        <select name="cumple_mes" class="form-select form-select-sm">
                            <option value="">Cualquier mes</option>
                            <?php foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                                            'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $i => $m): ?>
                                <option value="<?= $i + 1 ?>" <?= (int) ($filtros['cumple_mes'] ?? 0) === $i + 1 ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Le interesa</label>
                        <input type="text" name="interes" class="form-control form-control-sm" maxlength="60"
                               value="<?= esc($filtros['interes'] ?? '') ?>" placeholder="Aves, senderismo…">
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="empresa" value="1" id="fEmpresa"
                                   <?= ! empty($filtros['empresa']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fEmpresa">Solo los que vienen de empresa</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="vip" value="1" id="fVip"
                                   <?= ! empty($filtros['vip']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fVip">Solo los marcados VIP</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-outline-primary flex-fill">
                            <i class="bi bi-search me-1"></i>Ver a cuántos
                        </button>
                        <a href="<?= site_url('campanas/nueva') ?>" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ Resultado ═══ -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fs-2 fw-semibold"><?= $cuantos ?></div>
                        <div class="text-muted small">huéspedes cumplen</div>
                    </div>
                    <div class="text-end small text-muted" style="max-width: 60%;">
                        <?= esc($descripcion) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($cuantos > 0): ?>
            <form method="post" action="<?= site_url('campanas/guardar') ?>">
                <?= csrf_field() ?>
                <?php // Los filtros viajan escondidos para que la campaña guarde
                      // exactamente lo que se está viendo en pantalla. ?>
                <?php foreach ($filtros as $clave => $valor): ?>
                    <input type="hidden" name="<?= esc($clave, 'attr') ?>" value="<?= esc((string) $valor, 'attr') ?>">
                <?php endforeach ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-envelope me-2"></i>Qué mandarles</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre de la campaña</label>
                            <input type="text" name="nombre" class="form-control" required maxlength="120"
                                   placeholder="Recuperación de clientes de 2025">
                            <div class="form-text">Solo para reconocerla después.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Plantilla</label>
                            <select name="plantilla_clave" class="form-select" required>
                                <option value="">Elige una…</option>
                                <?php foreach ($plantillas as $p): ?>
                                    <option value="<?= esc($p['clave'], 'attr') ?>"><?= esc($p['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text">
                                Solo salen las que necesitan permiso. Los avisos de reserva no se
                                mandan en campaña: se mandan solos cuando toca.
                            </div>
                        </div>
                        <button class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Guardar como borrador
                        </button>
                        <div class="form-text mt-2">
                            No se manda nada todavía. Se guarda y se revisa antes.
                        </div>
                    </div>
                </div>
            </form>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2"></i>Quiénes son
                <?php if ($cuantos > count($muestra)): ?>
                    <span class="text-muted small">(los primeros <?= count($muestra) ?>)</span>
                <?php endif ?>
            </div>
            <?php if ($muestra === []): ?>
                <div class="card-body text-muted small">
                    Nadie cumple esos filtros. Prueba a quitar alguno.
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quién</th>
                                <th class="text-end">Estancias</th>
                                <th class="text-end d-none d-md-table-cell">Ha dejado</th>
                                <th class="d-none d-lg-table-cell">Última vez</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($muestra as $h): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('huespedes/ver/' . $h['id']) ?>" class="text-decoration-none">
                                            <?= esc(trim($h['nombre'] . ' ' . $h['apellidos'])) ?>
                                        </a>
                                        <div class="small text-muted"><?= esc($h['email']) ?: 'sin correo' ?></div>
                                    </td>
                                    <td class="text-end"><?= (int) $h['estancias'] ?></td>
                                    <td class="text-end d-none d-md-table-cell">
                                        $<?= number_format((float) $h['gasto'], 0, ',', '.') ?>
                                    </td>
                                    <td class="small text-muted d-none d-lg-table-cell">
                                        <?= $h['ultima'] !== null ? date('d/m/Y', strtotime($h['ultima'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
