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
                <!-- ✅ BOTÃO ATUALIZAR (Âncora de verdade) -->
                <a class="btn btn-success" href="<?= htmlspecialchars($updateHref) ?>">
                  <i class="fas fa-save me-1"></i> Atualizar Valores
                </a>
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
// JS (upload/process/status) – mantém o fluxo assíncrono com modal; polling 2s
(function() {
  const form   = document.getElementById('mf-form');
  const modalEl= document.getElementById('mfProgressModal');
  const elStep = document.getElementById('mf-step');
  const elBar  = document.getElementById('mf-bar');
  const elMsg  = document.getElementById('mf-msg');
  const btnSee = document.getElementById('mf-see-results');

  const ABS_BASE = "<?= addslashes($absBase) ?>";

  function urlWith(qs) {
    const u = new URL(ABS_BASE);
    const extra = new URLSearchParams(qs);
    extra.forEach((v,k) => u.searchParams.set(k,v));
    return u.toString();
  }

  const isBS5 = !!(window.bootstrap && window.bootstrap.Modal);
  function showModal() {
    if (isBS5) { const m = new bootstrap.Modal(modalEl); m.show(); return m; }
    if (window.jQuery && typeof jQuery.fn.modal === 'function') { jQuery(modalEl).modal('show'); return true; }
    return false;
  }

  let timer = null;
  let currentOp = null;

  function setProgress(step, pct, msg) {
    elStep.textContent = step || '';
    const p = Math.max(0, Math.min(100, pct|0));
    elBar.style.width = p + '%';
    elBar.textContent = p + '%';
    elMsg.textContent = msg || '';
  }

  function goToResults(op) {
    const url = urlWith('action=results&op=' + encodeURIComponent(op) + '&tab=match');
    window.location.href = url;
  }

  function showBackendError(title, detail) {
    showModal(); setProgress(title || 'Erro', 0, detail || 'Falha inesperada.');
    elBar.classList.remove('progress-bar-animated'); elBar.classList.add('bg-danger');
    console.error(title, detail);
  }

  function poll(op) {
    fetch(urlWith('action=status&op=' + encodeURIComponent(op)), {cache:'no-store'})
      .then(r => r.json())
      .then(j => {
        if (!j.ok) throw new Error(j.msg || 'Status falhou.');
        const d = j.data || {};
        const step = d.Step || '...';
        const st   = d.Status || '...';
        const tot  = (d.Total == null) ? 0 : d.Total;
        const done = (d.Done  == null) ? 0 : d.Done;
        let pct = 0;
        if (tot > 0) pct = Math.round((done/ tot) * 100);
        else {
          const map = {INIT:5, IMPORT:25, MATCH:55, ONLY_PLAN:75, ONLY_SYS:90, DONE:100, ERROR:0};
          pct = map[step] || 10;
        }
        setProgress(`${step} (${st})`, pct, d.Message || '');
        if (step === 'DONE' && st === 'OK') {
          if (timer) { clearInterval(timer); timer = null; }
          elBar.classList.remove('progress-bar-animated');
          if (btnSee) { btnSee.classList.remove('d-none'); btnSee.href = urlWith('action=results&op=' + encodeURIComponent(op)); }
          setTimeout(() => { goToResults(op); }, 800);
        } else if (step === 'ERROR' || st === 'ERROR') {
          if (timer) { clearInterval(timer); timer = null; }
          elBar.classList.remove('progress-bar-animated'); elBar.classList.add('bg-danger');
        }
      })
      .catch(err => showBackendError('Status com erro', err.message || err));
  }

  // PING para validar rota
  fetch(urlWith('action=ping'), {cache:'no-store'})
    .then(r => r.ok ? r.text() : Promise.reject('HTTP '+r.status))
    .then(t => console.info('PING OK:', t))
    .catch(e => console.warn('PING FAIL:', e));

  form.addEventListener('submit', function(ev) {
    ev.preventDefault();
    const fd = new FormData(form);
    fetch(urlWith('action=upload'), { method:'POST', body: fd })
      .then(async (r) => { if (!r.ok) { let txt = await r.text().catch(()=> ''); throw new Error(`Upload HTTP ${r.status}. ${txt}`); } return r.json(); })
      .then(j => {
        if (!j.ok || !j.op) throw new Error(j.msg || 'Falha no upload.');
        currentOp = j.op;
        showModal(); setProgress('INIT', 5, 'Arquivo enviado. Iniciando processamento ...');
        if (btnSee) { btnSee.classList.add('d-none'); btnSee.removeAttribute('href'); }
        elBar.classList.remove('bg-danger'); elBar.classList.add('progress-bar-animated');
        fetch(urlWith('action=process&op=' + encodeURIComponent(currentOp)), {cache:'no-store'}).catch(err => showBackendError('Process falhou', err.message || err));
        if (timer) clearInterval(timer); timer = setInterval(() => poll(currentOp), 2000); poll(currentOp);
      })
      .catch(err => { showBackendError('Upload com erro', err.message || err); alert(err.message || err); });
  });
})();
</script>