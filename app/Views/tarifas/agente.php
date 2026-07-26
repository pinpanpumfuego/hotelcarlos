<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$nuevas    = array_filter($propuestas, static fn ($p) => ! $p['existe']);
$conHistorico = array_filter($propuestas, static fn ($p) => $p['historico'] !== null);
?>

<div class="mb-4">
    <a href="<?= site_url('tarifas') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Tarifas
    </a>
    <h1 class="h3 mb-0 mt-1">Agente de tarifas</h1>
    <p class="text-muted mb-0 mt-1">
        Calcula los festivos de Colombia de <?= $anio ?> y propone el calendario comercial del año.
        <strong>Tú decides qué se aplica</strong>: nada se guarda hasta que lo marques.
    </p>
</div>

<?= view('partes/errores') ?>

<!-- ── Año ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label mb-1">Año a preparar</label>
                <select name="anio" class="form-select" onchange="this.form.submit()" style="min-width: 140px;">
                    <?php for ($a = (int) date('Y'); $a <= (int) date('Y') + 3; $a++): ?>
                        <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor ?>
                </select>
            </div>
            <div class="text-muted small ms-auto">
                <?= count($festivos) ?> festivos nacionales ·
                <?= count($propuestas) ?> temporadas propuestas ·
                <?= count($nuevas) ?> por crear
            </div>
        </form>
    </div>
</div>

<?php if ($conHistorico === []): ?>
    <div class="alert alert-light">
        <i class="bi bi-lightbulb me-1"></i>
        Todavía no hay histórico de ocupación, así que los porcentajes son los de partida
        recomendados para un ecolodge rural. Cuando lleves un año vendiendo, el agente
        <strong>los ajustará solo</strong> mirando cómo se llenaron esas mismas fechas.
    </div>
<?php endif ?>

<form method="post" action="<?= site_url('tarifas/agente/aplicar') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="anio" value="<?= $anio ?>">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold"><i class="bi bi-magic me-2"></i>Temporadas propuestas para <?= $anio ?></span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-nuevas">Marcar solo las nuevas</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-todas">Marcar todas</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="desmarcar">Ninguna</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 42px;"></th>
                        <th>Temporada</th>
                        <th class="d-none d-md-table-cell">Fechas</th>
                        <th style="width: 150px;">Recargo</th>
                        <th class="d-none d-lg-table-cell">Por qué</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($propuestas as $p): ?>
                        <tr>
                            <td>
                                <input class="form-check-input casilla" type="checkbox" name="clave[]"
                                       value="<?= esc($p['clave']) ?>" data-nueva="<?= $p['existe'] ? '0' : '1' ?>"
                                       <?= $p['existe'] ? '' : 'checked' ?>>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="punto-color" style="background: <?= esc($p['color']) ?>"></span>
                                    <div>
                                        <span class="fw-semibold"><?= esc($p['nombre']) ?></span>
                                        <?php if ($p['existe']): ?>
                                            <span class="badge text-bg-light text-muted ms-1">Ya creada</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success ms-1">Nueva</span>
                                        <?php endif ?>
                                        <div class="text-muted small d-md-none">
                                            <?= date('d/m/Y', strtotime($p['desde'])) ?> → <?= date('d/m/Y', strtotime($p['hasta'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell text-muted small">
                                <?= date('d/m/Y', strtotime($p['desde'])) ?> → <?= date('d/m/Y', strtotime($p['hasta'])) ?>
                                <div><?= $p['noches'] ?> día<?= $p['noches'] > 1 ? 's' : '' ?></div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="ajuste[<?= esc($p['clave']) ?>]" class="form-control"
                                           step="any" value="<?= esc($p['ajuste']) ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <?php if ($p['historico'] !== null): ?>
                                    <div class="text-muted" style="font-size: .74rem;">
                                        histórico <?= $p['historico'] ?> %
                                    </div>
                                <?php endif ?>
                            </td>
                            <td class="d-none d-lg-table-cell text-muted small">
                                <?= esc($p['motivo']) ?>
                                <?php if ($p['nota'] !== null): ?>
                                    <div class="text-success mt-1"><i class="bi bi-graph-up me-1"></i><?= esc($p['nota']) ?></div>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white p-3 d-flex gap-2 flex-wrap align-items-center">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear las temporadas marcadas</button>
            <a href="<?= site_url('tarifas/calendario') ?>" class="btn btn-outline-secondary">Ver el calendario</a>
            <span class="text-muted small ms-auto">
                Las que ya existen solo se refrescan si las creó el agente: si las tocaste a mano, se respetan.
            </span>
        </div>
    </div>
</form>

<!-- ── Festivos del año ── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-calendar-event me-2"></i>Festivos nacionales de <?= $anio ?>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small">
            Se calculan con las reglas de ley: los de fecha fija, los que la
            <strong>Ley Emiliani</strong> traslada al lunes siguiente, y los que dependen de la Pascua
            (Domingo de Resurrección: <?= date('d/m/Y', strtotime(\App\Libraries\FestivosColombia::pascua($anio))) ?>).
            No dependen de internet ni de ningún servicio externo.
        </p>
        <div class="rejilla-festivos">
            <?php
            $diasSemana = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
            foreach ($festivos as $fecha => $nombre): ?>
                <?php $n = (int) date('N', strtotime($fecha)); ?>
                <div class="festivo <?= $n === 1 ? 'lunes' : '' ?>">
                    <div class="dia"><?= date('d/m', strtotime($fecha)) ?></div>
                    <div class="nombre"><?= esc($nombre) ?></div>
                    <div class="semana"><?= $diasSemana[$n] ?><?= $n === 1 ? ' · puente' : '' ?></div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<style>
    .punto-color { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
                   box-shadow: 0 0 0 3px rgba(0,0,0,.05); }
    .rejilla-festivos { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: .6rem; }
    .festivo { border: 1px solid var(--borde); border-radius: var(--radio-sm);
               padding: .6rem .7rem; background: var(--panel-tenue); }
    .festivo.lunes { border-color: var(--arena-clara); background: #fdf7ef; }
    .festivo .dia { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.05rem; }
    .festivo .nombre { font-size: .84rem; line-height: 1.3; }
    .festivo .semana { font-size: .74rem; color: var(--tinta-suave); text-transform: capitalize; }
</style>

<script>
    const casillas = document.querySelectorAll('.casilla');
    document.getElementById('marcar-todas').onclick = () => casillas.forEach((c) => c.checked = true);
    document.getElementById('desmarcar').onclick = () => casillas.forEach((c) => c.checked = false);
    document.getElementById('marcar-nuevas').onclick = () =>
        casillas.forEach((c) => c.checked = c.dataset.nueva === '1');
</script>

<?= $this->endSection() ?>
