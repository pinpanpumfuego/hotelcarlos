<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4">Tablero de limpieza</h1>

<div class="row g-3 mb-4">
    <?php
    $configEstado = [
        'limpieza'   => ['color' => 'warning', 'icono' => 'bi-bucket', 'texto' => 'Pendiente de limpieza'],
        'ocupada'    => ['color' => 'danger', 'icono' => 'bi-person-fill', 'texto' => 'Ocupada'],
        'disponible' => ['color' => 'success', 'icono' => 'bi-check-circle', 'texto' => 'Limpia y disponible'],
        'bloqueada'  => ['color' => 'secondary', 'icono' => 'bi-slash-circle', 'texto' => 'Bloqueada'],
    ];
    ?>
    <?php foreach ($unidades as $u): ?>
        <?php
        $cfg     = $configEstado[$u['estado']] ?? $configEstado['bloqueada'];
        $abierta = $enCurso[$u['id']] ?? null;
        ?>
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-<?= $cfg['color'] ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h2 class="h6 mb-1"><?= esc($u['nombre']) ?></h2>
                        <i class="bi <?= $cfg['icono'] ?> text-<?= $cfg['color'] ?> fs-5"></i>
                    </div>
                    <?php if ($abierta !== null): ?>
                        <p class="small mb-2">
                            <span class="badge text-bg-info">En limpieza</span><br>
                            <span class="text-muted"><?= esc($abierta['usuario_nombre']) ?> · desde las <?= date('H:i', strtotime($abierta['inicio'])) ?></span>
                        </p>
                        <form action="<?= site_url('limpieza/finalizar/' . $u['id']) ?>" method="post">
                            <?= csrf_field() ?>
                            <textarea name="notas" class="form-control form-control-sm mb-2" rows="2"
                                      placeholder="Novedades: daños, objetos olvidados..."></textarea>
                            <button class="btn btn-sm btn-success w-100"><i class="bi bi-check-lg me-1"></i>Terminar: quedó lista</button>
                        </form>
                    <?php elseif ($u['estado'] === 'limpieza'): ?>
                        <p class="small text-muted mb-2"><?= $cfg['texto'] ?></p>
                        <form action="<?= site_url('limpieza/iniciar/' . $u['id']) ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-warning w-100"><i class="bi bi-play-fill me-1"></i>Empezar a limpiar</button>
                        </form>
                    <?php elseif ($u['estado'] === 'disponible'): ?>
                        <p class="small text-muted mb-2"><?= $cfg['texto'] ?></p>
                        <form action="<?= site_url('limpieza/reportar/' . $u['id']) ?>" method="post"
                              onsubmit="return confirm('¿Enviar <?= esc($u['nombre'], 'js') ?> a limpieza?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-warning w-100 text-dark"><i class="bi bi-bucket me-1"></i>Enviar a limpieza</button>
                        </form>
                    <?php else: ?>
                        <p class="small text-muted mb-0"><?= $cfg['texto'] ?><?= $u['estado'] === 'ocupada' ? ' — se limpiará a la salida del huésped' : '' ?></p>
                    <?php endif ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Últimas limpiezas</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Cabaña</th><th>Quién</th><th>Inicio</th><th>Duración</th><th>Novedades</th></tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Aún no hay limpiezas registradas.</td></tr>
                <?php endif ?>
                <?php foreach ($historial as $h): ?>
                    <?php $minutos = (int) ((strtotime($h['fin']) - strtotime($h['inicio'])) / 60); ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($h['unidad_nombre']) ?></td>
                        <td><?= esc($h['usuario_nombre']) ?></td>
                        <td class="text-muted"><?= date('d/m H:i', strtotime($h['inicio'])) ?></td>
                        <td><?= $minutos ?> min</td>
                        <td class="text-muted small"><?= esc($h['notas'] ?? '—') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
