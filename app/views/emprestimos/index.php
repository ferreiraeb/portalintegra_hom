<?php
/** @var \Support\ListTable $lt @var array $emprestimos @var int $total @var int $from @var int $to @var bool $canCreate */
$statusLabels = [
    'ativo'       => 'Ativo',
    'reservado'   => 'Reservado',
    'devolvido'   => 'Devolvido',
    'extraviado'  => 'Extraviado',
    'transferido' => 'Transferido',
    'cancelado'   => 'Cancelado',
];
$statusClasses = [
    'ativo'       => 'primary',
    'reservado'   => 'warning',
    'devolvido'   => 'success',
    'extraviado'  => 'dark',
    'transferido' => 'info',
    'cancelado'   => 'secondary',
];
$colCount = count($lt->cols());
?>
<section class="content pt-3">
  <div class="container-fluid">
    <form method="get" action="<?= base_url('emprestimos') ?>" id="frmEmprestimosFilter">
      <input type="hidden" name="sort"     value="<?= e($lt->getSort()) ?>">
      <input type="hidden" name="dir"      value="<?= e($lt->getDir()) ?>">
      <input type="hidden" name="per_page" value="<?= $lt->getPerPage() ?>">
      <input type="hidden" name="page"     value="1">

      <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap">
          <h3 class="card-title mb-0 mr-auto">Empréstimos</h3>

          <button type="submit" class="btn btn-sm btn-secondary ml-2">
            <i class="fas fa-search mr-1"></i>Filtrar
          </button>
          <?php
            $exportParams = array_filter($lt->getFilterValues());
            $exportParams['sort']   = $lt->getSort();
            $exportParams['dir']    = $lt->getDir();
            $exportParams['export'] = '1';
          ?>
          <a href="<?= e(base_url('emprestimos') . '?' . http_build_query($exportParams)) ?>"
             class="btn btn-sm btn-outline-secondary ml-2" title="Exportar CSV">
            <i class="fas fa-file-export mr-1"></i>Exportar CSV
          </a>
          <?php if ($lt->hasFilters()): ?>
            <a href="<?= base_url('emprestimos') ?>" class="btn btn-sm btn-outline-secondary ml-2" title="Limpar filtros">
              <i class="fas fa-times mr-1"></i>Limpar
            </a>
          <?php endif; ?>

          <?php if ($canCreate ?? false): ?>
          <a href="<?= base_url('emprestimos/criar') ?>" class="btn btn-sm btn-primary ml-2">
            <i class="fas fa-plus mr-1"></i>Novo Empréstimo
          </a>
          <?php endif; ?>
        </div>

        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <th class="<?= e($col['th_class'] ?? '') ?>"><?= $lt->sortLink($key) ?></th>
                <?php endforeach; ?>
              </tr>
              <tr class="thead-filter">
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <th class="p-1 <?= e($col['th_class'] ?? '') ?>"><?= $lt->filterInput($key) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
            <?php if (!empty($emprestimos)): ?>
              <?php foreach ($emprestimos as $emp): ?>
              <tr>
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <?php if ($key === '_acoes'): ?>
                <td class="text-right text-nowrap">
                  <a href="<?= base_url('emprestimos/'.(int)$emp['id']) ?>"
                     class="btn btn-xs btn-outline-secondary">Ver</a>
                </td>
                  <?php elseif ($key === 'item_descricao'): ?>
                <td>
                  <a href="<?= base_url('itens/'.(int)$emp['item_id']) ?>"><?= e($emp['item_descricao']) ?></a>
                </td>
                  <?php elseif ($key === 'tipo_nome'): ?>
                <td><?= e($emp['tipo_nome']) ?></td>
                  <?php elseif ($key === 'colaborador_nome'): ?>
                <td>
                  <a href="<?= base_url('hr/colaboradores/' . urlencode((string)$emp['colaborador_codpessoa'])) ?>">
                    <?= e($emp['colaborador_nome']) ?>
                  </a>
                  <small class="text-muted d-block"><?= e($emp['colaborador_codpessoa']) ?></small>
                </td>
                  <?php elseif ($key === 'quantidade'): ?>
                <td><?= (int)$emp['quantidade'] ?></td>
                  <?php elseif ($key === 'data_entrega'): ?>
                <td><?= format_date_br($emp['data_entrega'] ?? null) ?></td>
                  <?php elseif ($key === 'data_prevista_devolucao'): ?>
                <td><?= format_date_br($emp['data_prevista_devolucao'] ?? null) ?></td>
                  <?php elseif ($key === 'data_devolucao'): ?>
                <td><?= format_date_br($emp['data_devolucao'] ?? null) ?></td>
                  <?php elseif ($key === 'status'): ?>
                <td>
                  <span class="badge badge-<?= $statusClasses[$emp['status']] ?? 'secondary' ?>">
                    <?= e($statusLabels[$emp['status']] ?? $emp['status']) ?>
                  </span>
                </td>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="<?= $colCount ?>" class="text-center text-muted py-4">Nenhum empréstimo encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?= $lt->paginationFooter($total, $from, $to, 'empréstimos', $lt->hasFilters()) ?>
      </div>
    </form>
  </div>
</section>
<script>
(function () {
  var f = document.getElementById('frmEmprestimosFilter');
  if (!f) return;
  f.addEventListener('submit', function () {
    Array.prototype.forEach.call(f.elements, function (el) {
      if (el.tagName === 'SELECT') return;
      if (el.tagName === 'INPUT' && el.value === '' && el.type !== 'hidden') el.disabled = true;
    });
  });
})();
</script>
