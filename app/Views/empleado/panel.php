<?= $this->extend('layouts/empleado') ?>
<?= $this->section('contenido') ?>

<?php
use App\Models\FichajeModel;

$etiquetas = ['dentro' => 'Estás dentro', 'fuera' => 'Estás fuera', 'pausa' => 'En pausa'];
$iconos    = ['dentro' => 'bi-check-circle-fill', 'fuera' => 'bi-circle', 'pausa' => 'bi-pause-circle-fill'];
$dias      = ['Mon' => 'lunes', 'Tue' => 'martes', 'Wed' => 'miércoles', 'Thu' => 'jueves',
    'Fri' => 'viernes', 'Sat' => 'sábado', 'Sun' => 'domingo'];
?>

<div class="cabecera">
    <div class="barra">
        <div>
            <div class="saludo">Hola,</div>
            <h1><?= esc($empleado['nombre']) ?></h1>
        </div>
        <a href="<?= site_url('empleado/salir') ?>" class="salir" title="Salir"
           onclick="event.preventDefault(); document.getElementById('form-salir').submit();">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>

<form method="post" action="<?= site_url('empleado/salir') ?>" id="form-salir" hidden>
    <?= csrf_field() ?>
</form>

<main>
    <div id="aviso" class="aviso" style="display:none"></div>

    <!-- ── Comandero: solo para quien atiende mesas ── -->
    <?php if (in_array($empleado['rol_tpv'] ?? '', \App\Filters\Comandero::ROLES, true)): ?>
        <a href="<?= site_url('comandero') ?>" class="tarjeta"
           style="display:flex; align-items:center; gap:14px; text-decoration:none; color:inherit">
            <span style="width:46px; height:46px; border-radius:14px; background:#1f4d36; color:#fff;
                         display:grid; place-items:center; font-size:1.3rem; flex:none">
                <i class="bi bi-journal-text"></i>
            </span>
            <span style="flex:1">
                <strong style="display:block">Tomar comandas</strong>
                <span style="font-size:.82rem; color:#7b8a81">Funciona aunque te quedes sin señal</span>
            </span>
            <i class="bi bi-chevron-right" style="color:#7b8a81"></i>
        </a>
    <?php endif ?>

    <!-- ── Estado y botones ── -->
    <div class="tarjeta">
        <div class="estado-grande">
            <span class="etiqueta <?= $estado ?>" id="etiqueta-estado">
                <i class="bi <?= $iconos[$estado] ?>"></i><?= $etiquetas[$estado] ?>
            </span>
            <div class="reloj" id="reloj">--:--</div>
            <div class="detalle">
                <?php if ($ultimo !== null): ?>
                    Última marca: <?= esc(FichajeModel::TIPOS[$ultimo['tipo']]) ?>
                    <?= date('Y-m-d', strtotime($ultimo['marcado_en'])) === date('Y-m-d')
                        ? 'hoy' : 'el ' . date('d/m', strtotime($ultimo['marcado_en'])) ?>
                    a las <?= date('H:i', strtotime($ultimo['marcado_en'])) ?>
                <?php else: ?>
                    Todavía no has fichado nunca.
                <?php endif ?>
            </div>
        </div>

        <?php if ($puedeMovil): ?>
            <div id="botones">
                <?php foreach ($acciones as $tipo): ?>
                    <button class="btn <?= $tipo ?>" data-tipo="<?= $tipo ?>">
                        <i class="bi <?= FichajeModel::ICONOS[$tipo] ?>"></i>
                        <?= esc(FichajeModel::TIPOS[$tipo]) ?>
                    </button>
                <?php endforeach ?>
            </div>
            <p class="detalle" style="text-align:center; margin:12px 0 0; font-size:.78rem; color:var(--tinta-suave)">
                <i class="bi bi-geo-alt"></i>
                Se guarda tu ubicación al fichar<?= $radio > 0 ? ', y si estás a más de ' . number_format($radio, 0, ',', '.') . ' m del hotel queda anotado' : '' ?>.
            </p>
        <?php else: ?>
            <div class="aviso info" style="margin:0">
                <i class="bi bi-info-circle"></i>
                Tu ficha está configurada para marcar solo en el <strong>terminal del hotel</strong>.
                Aquí puedes consultar tus horas.
            </div>
        <?php endif ?>
    </div>

    <!-- ── Semana ── -->
    <div class="tarjeta">
        <h2>Esta semana</h2>
        <div class="estado-grande" style="padding:0 0 10px">
            <div class="reloj" style="font-size:2rem"><?= esc(FichajeModel::horas($minutos)) ?></div>
            <div class="detalle">
                del <?= date('d/m', strtotime($desde)) ?> al <?= date('d/m', strtotime($hasta)) ?>
            </div>
        </div>

        <?php if ($jornadas === []): ?>
            <p class="detalle" style="text-align:center; color:var(--tinta-suave)">Sin fichajes esta semana.</p>
        <?php else: ?>
            <ul class="lista">
                <?php foreach ($jornadas as $j): ?>
                    <li>
                        <div>
                            <div class="dia"><?= $dias[date('D', strtotime($j['fecha']))] ?> <?= date('d/m', strtotime($j['fecha'])) ?></div>
                            <div class="detalle">
                                <?= date('H:i', strtotime($j['primera'])) ?>
                                <?php if ($j['abierta']): ?>
                                    → en curso
                                <?php elseif ($j['ultima'] !== $j['primera']): ?>
                                    → <?= date('H:i', strtotime($j['ultima'])) ?>
                                <?php endif ?>
                                <?php if ($j['pausa'] > 0): ?>
                                    · pausa <?= $j['pausa'] ?> min
                                <?php endif ?>
                            </div>
                        </div>
                        <span class="horas"><?= esc(FichajeModel::horas($j['minutos'])) ?></span>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>

        <a href="<?= site_url('empleado/historial') ?>" class="btn suave" style="margin-top:14px">
            <i class="bi bi-calendar3"></i> Ver todas mis horas
        </a>
    </div>

    <!-- ── Turnos ── -->
    <?php if ($turnos !== []): ?>
        <div class="tarjeta">
            <h2>Mis próximos turnos</h2>
            <ul class="lista">
                <?php foreach ($turnos as $t): ?>
                    <li>
                        <div>
                            <div class="dia"><?= $dias[date('D', strtotime($t['fecha']))] ?> <?= date('d/m', strtotime($t['fecha'])) ?></div>
                            <?php if (trim((string) $t['puesto']) !== ''): ?>
                                <div class="detalle"><?= esc($t['puesto']) ?></div>
                            <?php endif ?>
                        </div>
                        <span class="horas" style="font-size:.95rem">
                            <?= substr($t['hora_inicio'], 0, 5) ?>–<?= substr($t['hora_fin'], 0, 5) ?>
                        </span>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>
</main>

<p class="pie">
    <?= esc($empleado['cargo']) ?> · <?= esc($hotel->nombre) ?><br>
    ¿Una marca equivocada? Avísale a gerencia: se corrige dejando constancia.
</p>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';

    const BASE = <?= json_encode(site_url('empleado/fichar')) ?>;
    let token = <?= json_encode(csrf_hash()) ?>;
    const NOMBRE_TOKEN = <?= json_encode(csrf_token()) ?>;

    const $ = (s) => document.querySelector(s);

    function reloj() {
        $('#reloj').textContent = new Date().toLocaleTimeString('es-CO',
            { hour: '2-digit', minute: '2-digit', hour12: false });
    }
    reloj();
    setInterval(reloj, 15000);

    function avisar(texto, esError) {
        const a = $('#aviso');
        a.textContent = texto;
        a.className = 'aviso ' + (esError ? 'error' : 'ok');
        a.style.display = 'block';
        a.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /** Pide la ubicación sin bloquear: si tarda o se deniega, se ficha igual. */
    function ubicacion() {
        return new Promise(function (resolver) {
            if (!navigator.geolocation) { return resolver({}); }

            const corte = setTimeout(() => resolver({}), 6000);

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    clearTimeout(corte);
                    resolver({
                        latitud: +pos.coords.latitude.toFixed(7),
                        longitud: +pos.coords.longitude.toFixed(7),
                        precision_m: Math.round(pos.coords.accuracy || 0),
                    });
                },
                function () { clearTimeout(corte); resolver({}); },
                { enableHighAccuracy: true, timeout: 5500, maximumAge: 30000 }
            );
        });
    }

    document.querySelectorAll('#botones [data-tipo]').forEach(function (boton) {
        boton.addEventListener('click', async function () {
            const botones = document.querySelectorAll('#botones button');
            botones.forEach((b) => b.disabled = true);
            const original = boton.innerHTML;
            boton.innerHTML = '<i class="bi bi-geo-alt"></i> Buscando tu ubicación…';

            const pos = await ubicacion();
            boton.innerHTML = '<i class="bi bi-hourglass-split"></i> Registrando…';

            try {
                const r = await fetch(BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(Object.assign({ tipo: boton.dataset.tipo, [NOMBRE_TOKEN]: token }, pos)),
                });
                const nuevo = r.headers.get('X-CSRF-TOKEN');
                if (nuevo) { token = nuevo; }

                const datos = await r.json();

                if (!r.ok || !datos.ok) {
                    avisar(datos.error || 'No se pudo registrar. Inténtalo otra vez.', true);
                    botones.forEach((b) => b.disabled = false);
                    boton.innerHTML = original;
                    return;
                }

                avisar(datos.mensaje, false);
                setTimeout(() => location.reload(), 1600);
            } catch (e) {
                avisar('Sin conexión. Ficha en el terminal del hotel y avisa a gerencia.', true);
                botones.forEach((b) => b.disabled = false);
                boton.innerHTML = original;
            }
        });
    });
})();
</script>
<?= $this->endSection() ?>
