<?php /** @var \Support\ListTable $lt @var array $tipos @var int $total @var int $from @var int $to */ ?>
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

    <form method="get" action="<?= base_url('tipos-item') ?>" id="frmTiposItemFilter">
      <input type="hidden" name="sort"     value="<?= e($lt->getSort()) ?>">
      <input type="hidden" name="dir"      value="<?= e($lt->getDir()) ?>">
      <input type="hidden" name="per_page" value="<?= $lt->getPerPage() ?>">
      <input type="hidden" name="page"     value="1">

      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title mb-0 mr-auto">Tipos de Item</h3>

          <button type="submit" class="btn btn-sm btn-secondary ml-2">
            <i class="fas fa-search mr-1"></i>Filtrar
          </button>
          <?php if ($lt->hasFilters()): ?>
            <a href="<?= base_url('tipos-item') ?>" class="btn btn-sm btn-outline-secondary ml-2" title="Limpar filtros">
              <i class="fas fa-times mr-1"></i>Limpar
            </a>
          <?php endif; ?>

          <a href="<?= base_url('tipos-item/criar') ?>" class="btn btn-sm btn-primary ml-2">
            <i class="fas fa-plus mr-1"></i>Novo Tipo
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
            <?php if (!empty($tipos)): ?>
              <?php foreach ($tipos as $t): ?>
              <tr class="<?= $t['ativo'] ? '' : 'text-muted' ?>">
                <td><?= (int)$t['id'] ?></td>
                <td><?= e($t['nome']) ?></td>
                <td><?= e($t['nome_categoria']) ?></td>
                <td>
                  <?php if ($t['is_determinado']): ?>
                    <span class="badge badge-info">Sim</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Não</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($t['ativo']): ?>
                    <span class="badge badge-success">Ativo</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Inativo</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <a href="<?= base_url('tipos-item/'.(int)$t['id'].'/editar') ?>"
                     class="btn btn-xs btn-outline-primary">Editar</a>
                  <button type="submit"
                          form="frmToggleTipo<?= (int)$t['id'] ?>"
                          class="btn btn-xs <?= $t['ativo'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                          onclick="return confirm('Alterar status?')">
                    <?= $t['ativo'] ? 'Desativar' : 'Ativar' ?>
                  </button>
                  <button type="submit"
                          form="frmDeleteTipo<?= (int)$t['id'] ?>"
                          class="btn btn-xs btn-outline-danger"
                          onclick="return confirm('Excluir permanentemente este tipo de item? Esta ação não pode ser desfeita.')">
                    Excluir
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="<?= count($lt->cols()) ?>" class="text-center text-muted py-4">Nenhum tipo encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?= $lt->paginationFooter($total, $from, $to, 'tipos de item', $lt->hasFilters()) ?>
      </div>
    </form>

    <?php foreach ($tipos as $t): ?>
      <form id="frmToggleTipo<?= (int)$t['id'] ?>"
            method="post"
            action="<?= base_url('tipos-item/'.(int)$t['id'].'/toggle') ?>"
            style="display:none">
        <?php csrf_field(); ?>
      </form>
      <form id="frmDeleteTipo<?= (int)$t['id'] ?>"
            method="post"
            action="<?= base_url('tipos-item/'.(int)$t['id'].'/excluir') ?>"
            style="display:none">
        <?php csrf_field(); ?>
      </form>
    <?php endforeach; ?>
  </div>
</section>
<script>
(function(){
  var f=document.getElementById('frmTiposItemFilter');
  if(!f)return;
  f.addEventListener('submit',function(){
    Array.prototype.forEach.call(f.elements,function(el){
      if(el.tagName==='SELECT') return;
      if((el.tagName==='INPUT')&&el.value===''&&el.type!=='hidden')el.disabled=true;
    });
  });
})();
</script>
