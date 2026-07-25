<?php
/**
 * Errores de validación de un formulario.
 * Uso: <?= view('partes/errores') ?>
 */
$errores = session()->getFlashdata('errores');
?>
<?php if (! empty($errores)): ?>
    <div class="alert alert-danger d-flex gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Revisa estos puntos:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ((array) $errores as $e): ?>
                    <li><?= esc($e) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>
