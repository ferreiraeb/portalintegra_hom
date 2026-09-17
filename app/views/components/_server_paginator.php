<?php
$pc = pageControls($meta, $absBase);
?>
<nav aria-label="Paginação" class="mt-2">
  <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= ((int)$meta['p'] === 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $pc['first'] ?>">&laquo; Primeiro</a>
      </li>
      <li class="page-item <?= ((int)$meta['p'] === 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $pc['prev'] ?>">&lsaquo; Anterior</a>
      </li>
      <li class="page-item disabled">
        <span class="page-link">Página <?= (int)$meta['p'] ?> / <?= (int)$pc['pages'] ?></span>
      </li>
      <li class="page-item <?= ((int)$meta['p'] >= $pc['pages']) ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $pc['next'] ?>">Próxima &rsaquo;</a>
      </li>
      <li class="page-item <?= ((int)$meta['p'] >= $pc['pages']) ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $pc['last'] ?>">Última &raquo;</a>
      </li>
    </ul>
    <?php if ($pc['pages'] > 1): ?>
    <div class="d-inline-flex align-items-center" style="gap:4px;">
      <input type="number" min="1" max="<?= (int)$pc['pages'] ?>"
             class="form-control form-control-sm massey-goto-input" style="width:70px;"
             placeholder="Pág."
             data-qs="<?= htmlspecialchars($meta['qsPattern']) ?>"
             data-base="<?= htmlspecialchars($absBase) ?>"
             data-max="<?= (int)$pc['pages'] ?>">
      <button type="button" class="btn btn-sm btn-outline-secondary massey-goto-btn">Ir</button>
    </div>
    <?php endif; ?>
  </div>
</nav>

