<?php
/** Variáveis esperadas do controller:
 * @var string|null $erro
 * @var array $rows
 * @var array $soSistema
 * @var array $soPlanilha
 * @var array $matchMeta ['total','p','pp','qsPattern','tabActive']
 * @var array $sysMeta   ['total','p','pp','qsPattern','tabActive']
 * @var array $planMeta  ['total','p','pp','qsPattern','tabActive']
 * @var array $filters   ['f_emp','f_cod','f_ref','f_desc','f_ncm','f_ncmid']
 * @var string $uiOver
 * @var string $uiIndice
 * @var string $marcaNome
 * @var string|null $base
 */

$flash = $_SESSION['massey_flash'] ?? null;
unset($_SESSION['massey_flash']);

$reqBase = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?'));
$base    = isset($base) && is_string($base) && $base !== '' ? $base : ($reqBase ?: '#');

// Monta base absoluta para os fetch/links
$scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME']
         : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http');
$host   = $_SERVER['HTTP_HOST'] ?? '';
$path   = $base;
if (strpos($path, 'http://') !== 0 && strpos($path, 'https://') !== 0) {
  if ($path === '' || $path[0] !== '/') $path = '/'.$path;
  $absBase = $scheme.'://'.$host.rtrim($path, '/');
} else {
  $absBase = $base;
}

// GUID atual (se houver)
$currentOp = isset($_GET['op']) ? preg_replace('/[^0-9a-fA-F\-]/','', $_GET['op']) : '';

// Links (com ou sem OP)
$exportHref = $absBase . '?action=export' . ($currentOp ? '&op=' . urlencode($currentOp) : '');
$updateHref = $absBase . '?action=update' . ($currentOp ? '&op=' . urlencode($currentOp) : '');

// Helpers de paginação
function totalPages($total,$pp){ $pp=max(1,(int)$pp); return max(1,(int)ceil($total/$pp)); }
function buildPageUrl($pattern,$page,$absBase){ $qs = sprintf($pattern,$page); return htmlspecialchars($absBase.'?'.$qs); }
function pageControls($meta,$absBase){
  $total = (int)$meta['total']; $p=(int)$meta['p']; $pp=(int)$meta['pp'];
  $pages = totalPages($total,$pp);
  $first = buildPageUrl($meta['qsPattern'], 1, $absBase);
  $prev  = buildPageUrl($meta['qsPattern'], max(1,$p-1), $absBase);
  $next  = buildPageUrl($meta['qsPattern'], min($pages,$p+1), $absBase);
  $last  = buildPageUrl($meta['qsPattern'], $pages, $absBase);
  return [
    'first'=>$first,'prev'=>$prev,'next'=>$next,'last'=>$last,
    'pages'=>$pages,'p'=>$p
  ];
}

// Meta locais
$match = $matchMeta ?? ['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>true];
$sys   = $sysMeta   ?? ['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>false];
$plan  = $planMeta  ?? ['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>false];
$filters = $filters ?? ['f_emp'=>'','f_cod'=>'','f_ref'=>'','f_desc'=>'','f_ncm'=>'','f_ncmid'=>''];

// select options (page size)
function ppOptions($current){
  $sizes=[25,100,1000,5000]; $out='';
  foreach($sizes as $s){ $sel=($s==(int)$current)?' selected':''; $out.="<option value=\"$s\"$sel>$s</option>"; }
  return $out;
}

// preserva filtros como inputs hidden
function filterHiddenInputs($filters){
  $out='';
  foreach($filters as $k=>$v){
    $v=htmlspecialchars($v);
    $out.="<input type=\"hidden\" name=\"$k\" value=\"$v\">";
  }
  return $out;
}
?>
<section class="content pt-3">
  <div class="container-fluid">

    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Agro — Tabela Massey Ferguson</h3>
        <span class="text-muted small">
          Marca: <strong><?= htmlspecialchars($marcaNome ?? 'Massey Ferguson') ?></strong>
        </span>
      </div>

      <div class="card-body">

        <?php if ($flash): ?>
          <div class="alert alert-success" role="alert">
            <pre class="mb-0" style="white-space:pre-wrap;"><?= htmlspecialchars($flash) ?></pre>
          </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger" role="alert">
            <pre class="mb-0" style="white-space:pre-wrap;"><?= htmlspecialchars($erro) ?></pre>
          </div>
        <?php endif; ?>

        <!-- UPLOAD / PROCESS -->


        <form id="mf-form" class="mb-3" enctype="multipart/form-data">
		<div class="form-row">
          <div class="form-group col-md-8">
			<label>Clique no botão abaixo e selecione o arquivo com a lista de preços</label>
			<input type="file" name="arquivo" id="arquivo" accept=".txt,.lp%," class="form-control-file" required>
			<small class="form-text text-muted">
                O arquivo anexo deve ser o enviado pelo fabricante no forma "lp%", sem manipulações. 
            </small>
          </div>
		</div>
		
		<div class="form-row">
		
          <div class="col-12 col-md-6">
            <label class="form-label">Overprice</label>
            <input type="text" name="overprice" id="overprice" value="<?= htmlspecialchars($uiOver ?? '15%') ?>" class="form-control" placeholder="15% ou 0,15">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Índice p/ Custo</label>
            <input type="text" name="indice_custo" id="indice_custo" value="<?= htmlspecialchars($uiIndice ?? '70%') ?>" class="form-control" placeholder="70% ou 0,70">
          </div>
		  
		</div>
		
		<!--
		<div class="form-row">
          <div class="col-12 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-cog me-1"></i> Processar (staging + cruzamento)
            </button>

            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportHref) ?>">
              <i class="fas fa-file-export me-1"></i> Exportar CSV (MATCH)
            </a>
          </div>
		</div>
		-->
		
		<div class="form-row">
			<div class="form-group col-md-8">
			  <!-- Marca ID oculto (fixo) -->
			  <input type="hidden" name="marca_id" value="96">
			
			  
				<button type="submit" class="btn btn-primary">
				  <i class="fas fa-cog me-1"></i> Processar
				</button>
				<a class="btn btn-outline-secondary" href="<?= htmlspecialchars($exportHref) ?>">
				  <i class="fas fa-file-export me-1"></i> Exportar CSV
				</a>
			 </div>
		 </div>
		

        </form>

        <!-- Modal de progresso -->
        <div class="modal fade" id="mfProgressModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-spinner me-2"></i>Processando...</h5>
                <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <div id="mf-step" class="mb-2 fw-bold small text-uppercase">Iniciando</div>
                <div class="progress" style="height: 18px;">
                  <div id="mf-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                       style="width: 0%;">0%</div>
                </div>
                <div id="mf-msg" class="small text-muted mt-2"></div>
              </div>
              <div class="modal-footer">
                <!-- ✅ BOTÃO VER RESULTADOS PRESENTE -->
                <a id="mf-see-results" class="btn btn-success d-none" href="#">
                  <i class="fas fa-check me-1"></i> Ver Resultados
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Fechar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- TABS -->
        <?php
          // Constrói QS de filtros para os tabs
          $qsFiltros = http_build_query($filters);
          $matchTabHref = $absBase.'?action=results&op='.$currentOp.'&tab=match&ppM='.$match['pp'].'&pM='.$match['p'].($qsFiltros?('&'.$qsFiltros):'');
          $sysTabHref   = $absBase.'?action=results&op='.$currentOp.'&tab=sys&ppS='.$sys['pp'].'&pS='.$sys['p'].($qsFiltros?('&'.$qsFiltros):'');
          $planTabHref  = $absBase.'?action=results&op='.$currentOp.'&tab=plan&ppP='.$plan['pp'].'&pP='.$plan['p'].($qsFiltros?('&'.$qsFiltros):'');
        ?>
        <ul class="nav nav-tabs" id="mfTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <a class="nav-link <?= $match['tabActive']?'active':'' ?>" href="<?= htmlspecialchars($matchTabHref) ?>">Relação de Itens</a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link <?= $sys['tabActive']?'active':'' ?>" href="<?= htmlspecialchars($sysTabHref) ?>">Itens Apenas DealerNet</a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link <?= $plan['tabActive']?'active':'' ?>" href="<?= htmlspecialchars($planTabHref) ?>">Itens Apenas Planilha</a>
          </li>
        </ul>
		


        <div class="tab-content mt-3" id="mfTabsContent">

          <!-- RELAÇÃO (MATCH) -->
          <div class="tab-pane fade <?= $match['tabActive']?'show active':'' ?>" id="pane-relacao" role="tabpanel">
            <!-- Filtros + page size -->
            <form method="get" class="row g-2 align-items-end mb-2">
              <?= filterHiddenInputs(['action'=>'results','op'=>$currentOp,'tab'=>'match','pM'=>1,'ppS'=>$sys['pp'],'pS'=>$sys['p'],'ppP'=>$plan['pp'],'pP'=>$plan['p']]) ?>
              <input type="hidden" name="ppM" value="<?= (int)$match['pp'] ?>">
              <div class="col-12 col-md-2">
                <label class="form-label">Empresa</label>
                <input type="text" name="f_emp" value="<?= htmlspecialchars($filters['f_emp']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Código</label>
                <input type="text" name="f_cod" value="<?= htmlspecialchars($filters['f_cod']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Referência</label>
                <input type="text" name="f_ref" value="<?= htmlspecialchars($filters['f_ref']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Descrição</label>
                <input type="text" name="f_desc" value="<?= htmlspecialchars($filters['f_desc']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-1">
                <label class="form-label">NCM</label>
                <input type="text" name="f_ncm" value="<?= htmlspecialchars($filters['f_ncm']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">NCM Ident.</label>
                <input type="text" name="f_ncmid" value="<?= htmlspecialchars($filters['f_ncmid']) ?>" class="form-control" placeholder="contém...">
              </div>

              <div class="col-6 col-md-2">
                <label class="form-label">Itens/página</label>
                <select class="form-select form-control" name="ppM" onchange="this.form.submit()">
                  <?= ppOptions($match['pp']) ?>
                </select>
              </div>
              <div class="col-6 col-md-2 d-flex gap-2">
                <button class="btn btn-secondary w-100" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
              </div>
            </form>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="text-muted">
                Total filtrado: <strong><?= number_format($match['total'],0,',','.') ?></strong>
              </div>
              <div>
                <button type="button"
                        id="btn-update-valores"
                        class="btn btn-success<?= $currentOp ? '' : ' disabled' ?>"
                        data-op="<?= htmlspecialchars($currentOp) ?>"
                        <?= $currentOp ? '' : 'disabled' ?>>
                  <i class="fas fa-sync me-1"></i> Atualizar Valores
                </button>
              </div>
            </div>

            <?php if (empty($rows)): ?>
              <div class="text-muted">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Empresa</th><th>Código</th><th>Referência</th><th>Descrição</th><th>NCM</th>
                      <th>ValorPlanilha (R$)</th><th>Overprice</th><th>Índice Custo</th>
                      <th>PP</th><th>PS</th><th>GA</th><th>RP</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['Empresa'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoCodigo'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoReferencia'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoDescricao'] ?? '') ?></td>
                      <td><?= htmlspecialchars(trim(($r['NCMIdentificador'] ?? '').' '.($r['NCMCodigo'] ?? ''))) ?></td>
                      <td><?= number_format((float)($r['ValorPlanilha'] ?? 0), 2, ',', '.') ?></td>
                      <td><?= number_format((float)($r['Overprice'] ?? 0) * 100, 2, ',', '.') ?>%</td>
                      <td><?= number_format((float)($r['IndiceParaCusto'] ?? 0) * 100, 2, ',', '.') ?>%</td>
                      <td><?= number_format((float)($r['PrecoPublico'] ?? 0), 2, ',', '.') ?></td>
                      <td><?= number_format((float)($r['PrecoSugerido'] ?? 0), 2, ',', '.') ?></td>
                      <td><?= number_format((float)($r['PrecoGarantia'] ?? 0), 2, ',', '.') ?></td>
                      <td><?= number_format((float)($r['PrecoReposicao'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <?php $pc = pageControls($match,$absBase); ?>
              <nav aria-label="Paginação MATCH" class="mt-2">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?= ($match['p']==1)?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['first'] ?>">&laquo; Primeiro</a>
                  </li>
                  <li class="page-item <?= ($match['p']==1)?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['prev'] ?>">&lsaquo; Anterior</a>
                  </li>
                  <li class="page-item disabled"><span class="page-link">Página <?= (int)$match['p'] ?> / <?= (int)$pc['pages'] ?></span></li>
                  <li class="page-item <?= ($match['p']>=$pc['pages'])?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['next'] ?>">Próxima &rsaquo;</a>
                  </li>
                  <li class="page-item <?= ($match['p']>=$pc['pages'])?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['last'] ?>">Última &raquo;</a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>

          <!-- APENAS DEALERNET (ONLY_SYS) -->
          <div class="tab-pane fade <?= $sys['tabActive']?'show active':'' ?>" id="pane-sistema" role="tabpanel">
            <form method="get" class="row g-2 align-items-end mb-2">
              <?= filterHiddenInputs(['action'=>'results','op'=>$currentOp,'tab'=>'sys','pS'=>1,'ppM'=>$match['pp'],'pM'=>$match['p'],'ppP'=>$plan['pp'],'pP'=>$plan['p']]) ?>
              <input type="hidden" name="ppS" value="<?= (int)$sys['pp'] ?>">
              <div class="col-12 col-md-2">
                <label class="form-label">Empresa</label>
                <input type="text" name="f_emp" value="<?= htmlspecialchars($filters['f_emp']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Código</label>
                <input type="text" name="f_cod" value="<?= htmlspecialchars($filters['f_cod']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Referência</label>
                <input type="text" name="f_ref" value="<?= htmlspecialchars($filters['f_ref']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Descrição</label>
                <input type="text" name="f_desc" value="<?= htmlspecialchars($filters['f_desc']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-1">
                <label class="form-label">NCM</label>
                <input type="text" name="f_ncm" value="<?= htmlspecialchars($filters['f_ncm']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">NCM Ident.</label>
                <input type="text" name="f_ncmid" value="<?= htmlspecialchars($filters['f_ncmid']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Itens/página</label>
                <select class="form-select form-control" name="ppS" onchange="this.form.submit()">
                  <?= ppOptions($sys['pp']) ?>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <button class="btn btn-secondary w-100" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
              </div>
            </form>

            <div class="text-muted mb-2">
              Total filtrado: <strong><?= number_format($sys['total'],0,',','.') ?></strong>
            </div>

            <?php if (empty($soSistema)): ?>
              <div class="text-muted">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                  <thead class="table-light">
                    <tr><th>ProdutoCódigo</th><th>ProdutoReferencia</th><th>ProdutoDescricao</th><th>NCM</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($soSistema as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['ProdutoCodigo'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoReferencia'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoDescricao'] ?? '') ?></td>
                      <td><?= htmlspecialchars(trim(($r['NCMIdentificador'] ?? '').' '.($r['NCMCodigo'] ?? ''))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <?php $pc = pageControls($sys,$absBase); ?>
              <nav aria-label="Paginação SYS" class="mt-2">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?= ($sys['p']==1)?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['first'] ?>">&laquo; Primeiro</a>
                  </li>
                  <li class="page-item <?= ($sys['p']==1)?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['prev'] ?>">&lsaquo; Anterior</a>
                  </li>
                  <li class="page-item disabled"><span class="page-link">Página <?= (int)$sys['p'] ?> / <?= (int)$pc['pages'] ?></span></li>
                  <li class="page-item <?= ($sys['p']>=$pc['pages'])?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['next'] ?>">Próxima &rsaquo;</a>
                  </li>
                  <li class="page-item <?= ($sys['p']>=$pc['pages'])?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['last'] ?>">Última &raquo;</a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>

          <!-- APENAS PLANILHA (ONLY_PLAN) -->
          <div class="tab-pane fade <?= $plan['tabActive']?'show active':'' ?>" id="pane-planilha" role="tabpanel">
            <form method="get" class="row g-2 align-items-end mb-2">
              <?= filterHiddenInputs(['action'=>'results','op'=>$currentOp,'tab'=>'plan','pP'=>1,'ppM'=>$match['pp'],'pM'=>$match['p'],'ppS'=>$sys['pp'],'pS'=>$sys['p']]) ?>
              <input type="hidden" name="ppP" value="<?= (int)$plan['pp'] ?>">
              <div class="col-12 col-md-2">
                <label class="form-label">Empresa</label>
                <input type="text" name="f_emp" value="<?= htmlspecialchars($filters['f_emp']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Código</label>
                <input type="text" name="f_cod" value="<?= htmlspecialchars($filters['f_cod']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Referência</label>
                <input type="text" name="f_ref" value="<?= htmlspecialchars($filters['f_ref']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Descrição</label>
                <input type="text" name="f_desc" value="<?= htmlspecialchars($filters['f_desc']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-1">
                <label class="form-label">NCM</label>
                <input type="text" name="f_ncm" value="<?= htmlspecialchars($filters['f_ncm']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">NCM Ident.</label>
                <input type="text" name="f_ncmid" value="<?= htmlspecialchars($filters['f_ncmid']) ?>" class="form-control" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label">Itens/página</label>
                <select class="form-select form-control" name="ppP" onchange="this.form.submit()">
                  <?= ppOptions($plan['pp']) ?>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <button class="btn btn-secondary w-100" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
              </div>
            </form>

            <div class="text-muted mb-2">
              Total filtrado: <strong><?= number_format($plan['total'],0,',','.') ?></strong>
            </div>

            <?php if (empty($soPlanilha)): ?>
              <div class="text-muted">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                  <thead class="table-light">
                    <tr><th>ProdutoReferencia</th><th>PartDescription</th><th>ValorPlanilha (R$)</th><th>NCM</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($soPlanilha as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['ProdutoReferencia'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoDescricao'] ?? '') ?></td>
                      <td><?= number_format((float)($r['ValorPlanilha'] ?? 0), 2, ',', '.') ?></td>
                      <td><?= htmlspecialchars(trim(($r['NCMIdentificador'] ?? '').' '.($r['NCMCodigo'] ?? ''))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <?php $pc = pageControls($plan,$absBase); ?>
              <nav aria-label="Paginação PLAN" class="mt-2">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?= ($plan['p']==1)?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['first'] ?>">&laquo; Primeiro</a>
                  </li>
                  <li class="page-item <?= ($plan['p']==1)?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['prev'] ?>">&lsaquo; Anterior</a>
                  </li>
                  <li class="page-item disabled"><span class="page-link">Página <?= (int)$plan['p'] ?> / <?= (int)$pc['pages'] ?></span></li>
                  <li class="page-item <?= ($plan['p']>=$pc['pages'])?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['next'] ?>">Próxima &rsaquo;</a>
                  </li>
                  <li class="page-item <?= ($plan['p']>=$pc['pages'])?'disabled':'' ?>">
                    <a class="page-link" href="<?= $pc['last'] ?>">Última &raquo;</a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>

        </div><!-- /tab-content -->
      </div><!-- /card-body -->
    </div><!-- /card -->
  </div><!-- /container-fluid -->
</section>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// Massey Ferguson — Import chunked + Atualizar Valores chunked
// Cada requisição processa um pedaço pequeno e atualiza a barra em tempo real.
// O usuário é avisado para não fechar a aba durante o processamento.
// ─────────────────────────────────────────────────────────────────────────────
(function () {
  'use strict';

  const form       = document.getElementById('mf-form');
  const modalEl    = document.getElementById('mfProgressModal');
  const elStep     = document.getElementById('mf-step');
  const elBar      = document.getElementById('mf-bar');
  const elMsg      = document.getElementById('mf-msg');
  const btnSee     = document.getElementById('mf-see-results');
  const elTitle    = modalEl ? modalEl.querySelector('.modal-title') : null;

  const ABS_BASE = "<?= addslashes($absBase) ?>";

  let isProcessing = false;

  /* ── helpers ────────────────────────────────────────────────────────────── */

  function urlWith(qs) {
    const u = new URL(ABS_BASE);
    new URLSearchParams(qs).forEach((v, k) => u.searchParams.set(k, v));
    return u.toString();
  }

  const isBS5 = !!(window.bootstrap && window.bootstrap.Modal);
  let bsModal = null;
  function showModal() {
    if (isBS5) {
      if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
      bsModal.show();
    } else if (window.jQuery && typeof jQuery.fn.modal === 'function') {
      jQuery(modalEl).modal('show');
    }
  }

  function setProgress(step, pct, msg) {
    if (elStep) elStep.textContent = step || '';
    const p = Math.max(0, Math.min(100, pct | 0));
    if (elBar) { elBar.style.width = p + '%'; elBar.textContent = p + '%'; }
    if (elMsg) elMsg.textContent = msg || '';
  }

  function setModalTitle(html) {
    if (elTitle) elTitle.innerHTML = html;
  }

  function showError(title, detail) {
    setProgress(title || 'Erro', 100, detail || 'Falha inesperada.');
    if (elBar) { elBar.classList.remove('progress-bar-animated'); elBar.classList.add('bg-danger'); }
    console.error(title, detail);
  }

  function resetBar() {
    if (elBar) { elBar.classList.remove('bg-danger'); elBar.classList.add('progress-bar-animated'); }
    if (btnSee) { btnSee.classList.add('d-none'); btnSee.removeAttribute('href'); }
  }

  function setProcessing(v) {
    isProcessing = v;
    window.onbeforeunload = v
      ? () => 'O processamento está em andamento. Tem certeza que deseja sair?'
      : null;
  }

  function goToResults(op) {
    window.location.href = urlWith('action=results&op=' + encodeURIComponent(op) + '&tab=match');
  }

  /* ── IMPORTAÇÃO (form submit) ───────────────────────────────────────────── */

  if (form) {
    form.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      if (isProcessing) return;

      // 1. Upload do arquivo
      const fd = new FormData(form);
      let uploadResp;
      try {
        const r = await fetch(urlWith('action=upload'), { method: 'POST', body: fd });
        if (!r.ok) { const txt = await r.text().catch(() => ''); throw new Error('Upload HTTP ' + r.status + '. ' + txt); }
        uploadResp = await r.json();
      } catch (err) {
        showModal(); showError('Upload com erro', err.message || String(err));
        alert(err.message || err);
        return;
      }
      if (!uploadResp.ok || !uploadResp.op) {
        showModal(); showError('Upload falhou', uploadResp.msg || 'Sem OP retornado.');
        return;
      }

      const op = uploadResp.op;
      setModalTitle('<i class="fas fa-spinner fa-spin me-2"></i>Importando arquivo...');
      showModal();
      resetBar();
      setProgress('UPLOAD', 3, 'Arquivo enviado. Iniciando importação em etapas...');
      setProcessing(true);

      try {
        // 2. Loop de import por chunks
        let byteOffset = 0;
        let done       = 0;
        let total      = 0;

        while (true) {
          const r = await fetch(
            urlWith('action=process&phase=import' +
              '&op='          + encodeURIComponent(op) +
              '&byte_offset=' + byteOffset +
              '&done='        + done),
            { cache: 'no-store' }
          );
          if (!r.ok) { const txt = await r.text().catch(() => ''); throw new Error('Import HTTP ' + r.status + ': ' + txt); }
          const j = await r.json();
          if (!j.ok) throw new Error(j.msg || 'Erro na importação.');

          done  = j.done;
          total = j.total || done;
          const pct = total > 0 ? Math.max(3, Math.round((done / total) * 60)) : 10;
          setProgress(
            'IMPORTANDO',
            pct,
            done.toLocaleString('pt-BR') + ' / ' + total.toLocaleString('pt-BR') + ' linhas'
          );

          if (j.finished) break;
          byteOffset = j.byte_offset;
        }

        // 3. Finalização: MATCH / ONLY_PLAN / ONLY_SYS
        setModalTitle('<i class="fas fa-spinner fa-spin me-2"></i>Cruzando dados...');
        setProgress('CRUZANDO DADOS', 65, 'Gerando cruzamentos com DealerNet — aguarde...');

        const fr = await fetch(
          urlWith('action=process&phase=finalize&op=' + encodeURIComponent(op)),
          { cache: 'no-store' }
        );
        if (!fr.ok) { const txt = await fr.text().catch(() => ''); throw new Error('Finalize HTTP ' + fr.status + ': ' + txt); }
        const fj = await fr.json();
        if (!fj.ok) throw new Error(fj.msg || 'Erro na finalização.');

        setProgress(
          'CONCLUÍDO', 100,
          'Relação: ' + fj.match.toLocaleString('pt-BR') +
          ' | Só planilha: ' + fj.only_plan.toLocaleString('pt-BR') +
          ' | Só sistema: '  + fj.only_sys.toLocaleString('pt-BR')
        );
        if (elBar) elBar.classList.remove('progress-bar-animated');
        if (btnSee) {
          btnSee.classList.remove('d-none');
          btnSee.href = urlWith('action=results&op=' + encodeURIComponent(op) + '&tab=match');
        }
        setTimeout(() => goToResults(op), 1500);

      } catch (err) {
        showError('Erro no processamento', err.message || String(err));
      } finally {
        setProcessing(false);
        setModalTitle('<i class="fas fa-spinner me-2"></i>Processando...');
      }
    });
  }

  /* ── ATUALIZAR VALORES (chunked) ────────────────────────────────────────── */

  const btnUpdate = document.getElementById('btn-update-valores');
  if (btnUpdate) {
    btnUpdate.addEventListener('click', async function () {
      if (isProcessing) return;
      const op = btnUpdate.dataset.op;
      if (!op) { alert('Nenhuma importação ativa. Processe um arquivo primeiro.'); return; }

      if (!confirm(
        'Atualizar preços de todos os itens MATCH no DealerNet?\n\n' +
        'ATENÇÃO: não feche nem recarregue esta aba durante a operação.\n\n' +
        'O processo pode levar vários minutos dependendo do volume de registros.'
      )) return;

      setModalTitle('<i class="fas fa-sync fa-spin me-2"></i>Atualizando Preços...');
      showModal();
      resetBar();
      setProgress('INICIANDO', 2, 'Conectando ao DealerNet...');
      setProcessing(true);

      try {
        let offset    = 0;
        let total     = 0;
        let auditOp   = '';
        let totalOk   = 0;
        let totalFail = 0;
        const limit   = 100; // produtos por chunk — conservador para não travar o DB

        while (true) {
          const r = await fetch(
            urlWith('action=update_chunk' +
              '&op='       + encodeURIComponent(op) +
              '&offset='   + offset +
              '&limit='    + limit +
              '&total='    + total +
              '&audit_op=' + encodeURIComponent(auditOp)),
            { cache: 'no-store' }
          );
          if (!r.ok) { const txt = await r.text().catch(() => ''); throw new Error('Update HTTP ' + r.status + ': ' + txt); }
          const j = await r.json();
          if (!j.ok) throw new Error(j.msg || 'Erro na atualização de preços.');

          offset    = j.done;
          total     = j.total;
          auditOp   = j.audit_op  || auditOp;
          totalOk  += j.proc_ok   || 0;
          totalFail += j.proc_fail || 0;

          const pct = total > 0 ? Math.max(2, Math.min(99, Math.round((offset / total) * 97) + 2)) : 2;
          setProgress(
            'ATUALIZANDO',
            pct,
            offset.toLocaleString('pt-BR') + ' / ' + total.toLocaleString('pt-BR') +
            '  —  OK: ' + totalOk.toLocaleString('pt-BR') +
            (totalFail > 0 ? '  |  Falhas: ' + totalFail.toLocaleString('pt-BR') : '')
          );

          if (j.errors && j.errors.length > 0) {
            console.warn('Erros neste chunk:', j.errors);
          }
          if (j.finished) break;
        }

        const resumo = 'ID: ' + auditOp +
          '  —  OK: ' + totalOk.toLocaleString('pt-BR') +
          (totalFail > 0 ? '  |  Falhas: ' + totalFail.toLocaleString('pt-BR') : '');
        setProgress('CONCLUÍDO', 100, resumo);
        if (elBar) elBar.classList.remove('progress-bar-animated');
        if (btnSee) {
          btnSee.classList.remove('d-none');
          btnSee.href = urlWith('action=results&op=' + encodeURIComponent(op) + '&tab=match');
        }

      } catch (err) {
        showError('Erro na atualização', err.message || String(err));
      } finally {
        setProcessing(false);
        setModalTitle('<i class="fas fa-spinner me-2"></i>Processando...');
      }
    });
  }

  // PING de sanidade (útil no debug, pode remover em produção)
  fetch(urlWith('action=ping'), { cache: 'no-store' })
    .then(r => r.ok ? r.text() : Promise.reject('HTTP ' + r.status))
    .then(t => console.info('PING OK:', t))
    .catch(e => console.warn('PING FAIL:', e));

})();
</script>