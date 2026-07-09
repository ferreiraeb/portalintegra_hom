<?php
/** @var \Support\ListTable $lt @var array $itens @var \Models\TipoItem $tipo @var string $listaUrl @var int $total @var int $from @var int $to @var bool $canCreate */
$statusLabels = [
    'disponivel' => 'Disponível',
    'em_uso'     => 'Em uso',
    'reservado'  => 'Reservado',
    'bloqueado'  => 'Bloqueado',
    'baixado'    => 'Baixado',
    'extraviado' => 'Extraviado',
];
$statusClasses = [
    'disponivel' => 'success',
    'em_uso'     => 'primary',
    'reservado'  => 'warning',
    'bloqueado'  => 'danger',
    'baixado'    => 'secondary',
    'extraviado' => 'dark',
];
$colCount = count($lt->cols());
?>
<section class="content pt-3">
  <div class="container-fluid">
    <form method="get" action="<?= base_url($listaUrl) ?>" id="frmItensFilter">
      <input type="hidden" name="sort"     value="<?= e($lt->getSort()) ?>">
      <input type="hidden" name="dir"      value="<?= e($lt->getDir()) ?>">
      <input type="hidden" name="per_page" value="<?= $lt->getPerPage() ?>">
      <input type="hidden" name="page"     value="1">

      <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap">
          <h3 class="card-title mb-0 mr-auto"><?= e($tipo->nome) ?></h3>

          <button type="submit" class="btn btn-sm btn-secondary ml-2">
            <i class="fas fa-search mr-1"></i>Filtrar
          </button>
          <?php if ($lt->hasFilters()): ?>
            <a href="<?= base_url($listaUrl) ?>" class="btn btn-sm btn-outline-secondary ml-2" title="Limpar filtros">
              <i class="fas fa-times mr-1"></i>Limpar
            </a>
          <?php endif; ?>

          <?php if ($canCreate ?? false): ?>
          <a href="<?= base_url('itens/criar?tipo_item_id='.(int)$tipo->id) ?>" class="btn btn-sm btn-primary ml-2">
            <i class="fas fa-plus mr-1"></i>Novo Item
          </a>
          <?php endif; ?>
        </div>

        <div class="card-body p-0" id="itensTableWrap">
          <div class="table-scroll-top" id="tblItensScrollTop" aria-hidden="true">
            <div id="tblItensScrollSpacer"></div>
          </div>
          <div class="table-responsive table-scroll-body p-0" id="tblItensScrollBody">
          <table class="table table-hover table-sm mb-0" id="tblItens">
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
            <?php if (!empty($itens)): ?>
              <?php foreach ($itens as $i): ?>
              <tr>
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <?php if ($key === '_acoes'): ?>
                <td class="text-right text-nowrap">
                  <a href="<?= base_url('itens/'.(int)$i['id']) ?>" class="btn btn-xs btn-outline-secondary">Ver</a>
                  <?php if (($i['nivel_usuario'] ?? 0) >= 2): ?>
                  <a href="<?= base_url('itens/'.(int)$i['id'].'/editar') ?>" class="btn btn-xs btn-outline-primary">Editar</a>
                  <?php
                    $emprestavel = !in_array($i['status'], ['bloqueado', 'baixado', 'extraviado', 'reservado'], true)
                        && (int)($i['quantidade_em_uso'] ?? 0) < (int)$i['quantidade_total'];
                  ?>
                  <?php if ($emprestavel): ?>
                  <a href="<?= base_url('emprestimos/criar?item_id='.(int)$i['id']) ?>" class="btn btn-xs btn-outline-success">Emprestar</a>
                  <?php endif; ?>
                  <?php endif; ?>
                </td>
                  <?php elseif ($key === 'status'): ?>
                <td>
                  <span class="badge badge-<?= $statusClasses[$i['status']] ?? 'secondary' ?>">
                    <?= e($statusLabels[$i['status']] ?? $i['status']) ?>
                  </span>
                </td>
                  <?php elseif ($key === 'descricao'): ?>
                <td class="<?= e($col['th_class'] ?? '') ?>"><?= e($i['descricao']) ?></td>
                  <?php elseif ($key === 'quantidade_total'): ?>
                <td><?= (int)$i['quantidade_total'] ?></td>
                  <?php else: ?>
                <td><?= format_item_list_cell($key, $i[$key] ?? null) ?></td>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="<?= $colCount ?>" class="text-center text-muted py-4">Nenhum item encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          </div><!-- /.table-scroll-body -->
        </div><!-- /#itensTableWrap -->

        <?= $lt->paginationFooter($total, $from, $to, 'itens', $lt->hasFilters()) ?>
      </div>
    </form>
  </div>
</section>
<style>
#itensTableWrap .table-scroll-top {
  overflow-x: auto;
  overflow-y: hidden;
  border-bottom: 1px solid #dee2e6;
}
#itensTableWrap .table-scroll-top > div {
  height: 1px;
}
#itensTableWrap #tblItens th,
#itensTableWrap #tblItens td {
  white-space: nowrap;
}
#itensTableWrap #tblItens th.col-itens-descricao,
#itensTableWrap #tblItens td.col-itens-descricao {
  min-width: 200px;
}
</style>
<script>
(function(){
  var f=document.getElementById('frmItensFilter');
  if(f){
    f.addEventListener('submit',function(){
      Array.prototype.forEach.call(f.elements,function(el){
        if(el.tagName==='SELECT') return;
        if((el.tagName==='INPUT')&&el.value===''&&el.type!=='hidden')el.disabled=true;
      });
    });
  }

  var top=document.getElementById('tblItensScrollTop');
  var body=document.getElementById('tblItensScrollBody');
  var spacer=document.getElementById('tblItensScrollSpacer');
  var table=document.getElementById('tblItens');
  if(!top||!body||!spacer||!table)return;

  var syncing=false;
  function syncWidth(){
    spacer.style.width=table.scrollWidth+'px';
  }
  function linkScroll(from,to){
    from.addEventListener('scroll',function(){
      if(syncing)return;
      syncing=true;
      to.scrollLeft=from.scrollLeft;
      syncing=false;
    });
  }
  linkScroll(top,body);
  linkScroll(body,top);
  syncWidth();
  window.addEventListener('resize',syncWidth);
  if(window.ResizeObserver){
    new ResizeObserver(syncWidth).observe(table);
  }
})();
</script>
