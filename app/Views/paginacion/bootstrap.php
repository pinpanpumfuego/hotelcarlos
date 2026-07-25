<?php
/** Paginación con estilo Bootstrap 5, en español. */
$pager->setSurroundCount(2);
?>
<nav aria-label="Paginación">
    <ul class="pagination mb-0">
        <?php if ($pager->hasPrevious()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="Primera página">&laquo;</a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getPrevious() ?>" aria-label="Anterior">Anterior</a>
            </li>
        <?php endif ?>

        <?php foreach ($pager->links() as $enlace): ?>
            <li class="page-item <?= $enlace['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $enlace['uri'] ?>"><?= esc($enlace['title']) ?></a>
            </li>
        <?php endforeach ?>

        <?php if ($pager->hasNext()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getNext() ?>" aria-label="Siguiente">Siguiente</a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getLast() ?>" aria-label="Última página">&raquo;</a>
            </li>
        <?php endif ?>
    </ul>
</nav>
