<?php /** @var \Support\ListTable $lt @var array $categorias @var int $total @var int $from @var int $to */ ?>
<section class="content pt-3">
  <div class="container-fluid">
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-warning alert-dismissible">
        <?= e($_SESSION['flash_error']) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success alert-dismissible">
        <?= e($_SESSION['flash_success']) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <form method="get" action="<?= base_url('categorias') ?>" id="frmCategoriasFilter">
      <input type="hidden" name="sort"     value="<?= e($lt->getSort()) ?>">
      <input type="hidden" name="dir"      value="<?= e($lt->getDir()) ?>">
      <input type="hidden" name="per_page" value="<?= $lt->getPerPage() ?>">
      <input type="hidden" name="page"     value="1">

      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title mb-0 mr-auto">Categorias</h3>

          <button type="submit" class="btn btn-sm btn-secondary ml-2">
            <i class="fas fa-search mr-1"></i>Filtrar
          </button>
          <?php if ($lt->hasFilters()): ?>
            <a href="<?= base_url('categorias') ?>" class="btn btn-sm btn-outline-secondary ml-2" title="Limpar filtros">
              <i class="fas fa-times mr-1"></i>Limpar
            </a>
          <?php endif; ?>

          <a href="<?= base_url('categorias/criar') ?>" class="btn btn-sm btn-primary ml-2">
            <i class="fas fa-plus mr-1"></i>Nova Categoria
          </a>
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
            <?php if (!empty($categorias)): ?>
              <?php foreach ($categorias as $cat): ?>
              <tr class="<?= $cat['ativo'] ? '' : 'text-muted' ?>">
                <td><?= (int)$cat['id'] ?></td>
                <td><?= e($cat['nome']) ?></td>
                <td><?= e($cat['descricao'] ?? '—') ?></td>
                <td>
                  <?php if ($cat['ativo']): ?>
                    <span class="badge badge-success">Ativa</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Inativa</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <a href="<?= base_url('categorias/'.(int)$cat['id'].'/editar') ?>"
                     class="btn btn-xs btn-outline-primary">Editar</a>
                  <button type="submit"
                          form="frmToggleCat<?= (int)$cat['id'] ?>"
                          class="btn btn-xs <?= $cat['ativo'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                          onclick="return confirm('Alterar status desta categoria?')">
                    <?= $cat['ativo'] ? 'Desativar' : 'Ativar' ?>
                  </button>
                  <button type="submit"
                          form="frmDeleteCat<?= (int)$cat['id'] ?>"
                          class="btn btn-xs btn-outline-danger"
                          onclick="return confirm('Excluir permanentemente esta categoria? Esta ação não pode ser desfeita.')">
                    Excluir
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="<?= count($lt->cols()) ?>" class="text-center text-muted py-4">Nenhuma categoria encontrada.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?= $lt->paginationFooter($total, $from, $to, 'categorias', $lt->hasFilters()) ?>
      </div>
    </form>

    <?php foreach ($categorias as $cat): ?>
      <form id="frmToggleCat<?= (int)$cat['id'] ?>"
            method="post"
            action="<?= base_url('categorias/'.(int)$cat['id'].'/toggle') ?>"
            style="display:none">
        <?php csrf_field(); ?>
      </form>
      <form id="frmDeleteCat<?= (int)$cat['id'] ?>"
            method="post"
            action="<?= base_url('categorias/'.(int)$cat['id'].'/excluir') ?>"
            style="display:none">
        <?php csrf_field(); ?>
      </form>
    <?php endforeach; ?>
  </div>
</section>
<script>
(function(){
  var f=document.getElementById('frmCategoriasFilter');
  if(!f)return;
  f.addEventListener('submit',function(){
    Array.prototype.forEach.call(f.elements,function(el){
      if(el.tagName==='SELECT') return;
      if((el.tagName==='INPUT')&&el.value===''&&el.type!=='hidden')el.disabled=true;
    });
  });
})();
</script>
