<?php
/**
 * @var \Support\ListTable $lt
 */
$erro = $erro ?? null;
?>
<section class="content pt-3">
  <div class="container-fluid">

    <?php if ($erro): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="fas fa-exclamation-triangle mr-1"></i>Erro ao conectar no Oracle:</strong>
        <?= e($erro) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
    <?php endif; ?>

    <form method="get" action="<?= base_url('hr/colaboradores') ?>" id="frmColabFilter">
      <input type="hidden" name="sort"     value="<?= e($lt->getSort()) ?>">
      <input type="hidden" name="dir"      value="<?= e($lt->getDir()) ?>">
      <input type="hidden" name="per_page" value="<?= $lt->getPerPage() ?>">
      <input type="hidden" name="page"     value="1">

      <div class="card">

        <!-- Cabeçalho -->
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title mb-0 mr-auto">Colaboradores</h3>
          <button type="submit" class="btn btn-sm btn-secondary ml-2">
            <i class="fas fa-search mr-1"></i>Filtrar
          </button>
          <?php
            $exportParams = array_filter($lt->getFilterValues());
            $exportParams['sort']   = $lt->getSort();
            $exportParams['dir']    = $lt->getDir();
            $exportParams['export'] = '1';
          ?>
          <a href="<?= e(base_url('hr/colaboradores') . '?' . http_build_query($exportParams)) ?>"
             class="btn btn-sm btn-outline-secondary ml-2" title="Exportar CSV">
            <i class="fas fa-file-export mr-1"></i>Exportar CSV
          </a>
          <?php if ($lt->hasFilters()): ?>
            <a href="<?= base_url('hr/colaboradores') ?>" class="btn btn-sm btn-outline-secondary ml-2" title="Limpar filtros">
              <i class="fas fa-times mr-1"></i>Limpar
            </a>
          <?php endif; ?>
        </div>

        <!-- Tabela -->
        <div class="card-body table-responsive p-0">

          <table class="table table-hover table-sm mb-0" id="tblColaboradores">
            <thead>
              <!-- Linha 1: cabeçalhos ordenáveis -->
              <tr>
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <th><?= $lt->sortLink($key) ?></th>
                <?php endforeach; ?>
              </tr>
              <!-- Linha 2: filtros por coluna -->
              <tr class="thead-filter">
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <th class="p-1"><?= $lt->filterInput($key) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!$erro && !empty($colaboradores)): ?>
                <?php foreach ($colaboradores as $row): ?>
                  <tr>
                    <?php foreach ($lt->cols() as $colKey => $col): ?>
                      <td><?php
                        $cellHtml = isset($col['render'])
                            ? ($col['render'])($row[$colKey] ?? '')
                            : e((string)($row[$colKey] ?? ''));
                        if ($colKey === 'NOMECOMPLETO' && !empty($row['CODPESSOA'])) {
                            echo '<a href="' . base_url('hr/colaboradores/' . urlencode((string)$row['CODPESSOA'])) . '">'
                                . $cellHtml . '</a>';
                        } else {
                            echo $cellHtml;
                        }
                      ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="<?= count($lt->cols()) ?>" class="text-center text-muted py-4">
                    Nenhum registro encontrado.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (!$erro && $total > 0): ?>
          <?= $lt->paginationFooter($total, $from, $to, 'colaboradores', $lt->hasFilters()) ?>
        <?php endif; ?>

      </div><!-- /.card -->
    </form><!-- /#frmColabFilter -->
  </div>
</section>
<script>
(function(){
  var f=document.getElementById('frmColabFilter');
  if(!f)return;
  f.addEventListener('submit',function(){
    Array.prototype.forEach.call(f.elements,function(el){
      if(el.tagName==='INPUT'&&el.value===''&&el.type!=='hidden')el.disabled=true;
    });
  });
})();
</script>


