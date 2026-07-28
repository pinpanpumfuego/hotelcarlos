<?php
/**
 * Navegación de Administración.
 *
 * Agrupado por **quién lo toca y cada cuánto**, no por módulo: los datos del
 * hotel se ponen una vez y no se vuelven a mirar, las credenciales de terceros
 * las toca solo gerencia, y el estado del sistema se mira cuando algo falla.
 * En una sola página los tres pesaban lo mismo, y por eso no se encontraba nada.
 *
 * @var string $activa
 */
$paginas = [
    ''           => ['Estado',    'bi-clipboard-check'],
    'hotel'      => ['El hotel',  'bi-house-heart'],
    'cobros'     => ['Cobros',    'bi-credit-card'],
    'operacion'  => ['Operación', 'bi-sliders'],
    'sistema'    => ['Sistema',   'bi-hdd-network'],
];
?>

<ul class="nav nav-pills mb-4 flex-wrap gap-1">
    <?php foreach ($paginas as $ruta => [$nombre, $icono]): ?>
        <?php // Cobros guarda llaves de Wompi y de Siigo: quien las tiene puede
              // facturar en nombre del hotel. Si no se puede entrar, tampoco se
              // enseña la puerta. ?>
        <?php if ($ruta === 'cobros' && ! puede('administracion.integraciones')) { continue; } ?>
        <li class="nav-item">
            <a class="nav-link <?= ($activa ?? '') === $ruta ? 'active' : '' ?>"
               href="<?= site_url('administracion' . ($ruta !== '' ? '/' . $ruta : '')) ?>">
                <i class="bi <?= $icono ?> me-1"></i><?= $nombre ?>
            </a>
        </li>
    <?php endforeach ?>
</ul>
