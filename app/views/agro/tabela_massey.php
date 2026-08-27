<?php
require_once __DIR__ . '/../components/_tabela_buttons.php';

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
$exportHref    = $absBase . '?action=export&tab=match' . ($currentOp ? '&op=' . urlencode($currentOp) : '');
$exportSysHref = $absBase . '?action=export&tab=sys'   . ($currentOp ? '&op=' . urlencode($currentOp) : '');
$exportPlanHref= $absBase . '?action=export&tab=plan'  . ($currentOp ? '&op=' . urlencode($currentOp) : '');
$updateHref = $absBase . '?action=update' . ($currentOp ? '&op=' . urlencode($currentOp) : '');


// Meta locais — array_merge garante que chaves ausentes recebam defaults
// (protege contra controller passando array incompleto, inclusive com OPcache antigo)
$match = array_merge(['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>true],  (array)($matchMeta ?? []));
$sys   = array_merge(['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>false], (array)($sysMeta   ?? []));
$plan  = array_merge(['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>false], (array)($planMeta  ?? []));
$filters = array_merge(['f_emp'=>'','f_cod'=>'','f_ref'=>'','f_desc'=>'','f_ncm'=>'','f_ncmid'=>''], (array)($filters ?? []));

?>
<section class="content pt-3">
  <div class="container-fluid">

    <div class="card card-outline card-primary">

      <!-- ── Card header ───────────────────────────────────────────────── -->
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Agro — Tabela Massey Ferguson</h3>
        <span class="text-muted small">Marca: <strong><?= e($marcaNome ?? 'Massey Ferguson') ?></strong></span>
      </div>

      <div class="card-body">

        <?php if ($flash): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i>
            <span style="white-space:pre-wrap;"><?= e($flash) ?></span>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <span style="white-space:pre-wrap;"><?= e($erro) ?></span>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <!-- UPLOAD / PROCESS -->


        <form id="mf-form" class="mb-3" enctype="multipart/form-data">
		<div class="form-row">
          <div class="form-group col-md-8">
			<label class="mb-0">Arquivo (.lp%) <span class="text-danger">*</span></label>
			<div class="custom-file">
              <input type="file" class="custom-file-input" id="arquivo" name="arquivo" accept=".txt,.lp%" required>
              <label class="custom-file-label" for="arquivo" data-browse="Procurar">Escolher arquivo...</label>
            </div>
			<small class="form-text text-muted">
                O arquivo deve ser o enviado pelo fabricante no formato "lp%", sem manipulações.
            </small>
          </div>
		</div>
		
		<div class="form-row">

          <div class="form-group col-md-2">
            <label class="form-label mb-0">Overprice</label>
            <input type="text" name="overprice" id="overprice" value="<?= e($uiOver ?? '15%') ?>" class="form-control form-control-sm" placeholder="15% ou 0,15">
            <small class="form-text text-muted">Ex.: 15%</small>
          </div>

          <div class="form-group col-md-2">
            <label class="form-label mb-0">Fator de Conversão</label>
            <input type="text" name="indice_custo" id="indice_custo" value="<?= e($uiIndice ?? '70%') ?>" class="form-control form-control-sm" placeholder="70% ou 0,70">
            <small class="form-text text-muted">Ex.: 70%</small>
          </div>
		  
		</div>
		
		<div class="form-row">
			<div class="form-group col-md-8">
			  <!-- Marca ID oculto (fixo) -->
			  <input type="hidden" name="marca_id" value="96">
			  <?= tabela_btn_processar() ?>
			 </div>
		 </div>
		

        </form>

        <!-- Progresso de importação renderizado pelo PortalModal global (views/components/progress_modal.php) -->
        <!-- Toast renderizado pelo componente global: views/components/toast.php (PortalToast) -->

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
            <a class="nav-link <?= $match['tabActive']?'active':'' ?>" href="<?= htmlspecialchars($matchTabHref) ?>">
              Relação de Itens <?= $match['total'] > 0 ? '(' . number_format($match['total'], 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link <?= $sys['tabActive']?'active':'' ?>" href="<?= htmlspecialchars($sysTabHref) ?>">
              Itens Apenas DealerNet <?= $sys['total'] > 0 ? '(' . number_format($sys['total'], 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link <?= $plan['tabActive']?'active':'' ?>" href="<?= htmlspecialchars($planTabHref) ?>">
              Itens Apenas Planilha <?= $plan['total'] > 0 ? '(' . number_format($plan['total'], 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
        </ul>
		


        <div class="tab-content mt-3" id="mfTabsContent">

          <!-- RELAÇÃO (MATCH) -->
          <div class="tab-pane fade <?= $match['tabActive']?'show active':'' ?>" id="pane-relacao" role="tabpanel">
            <!-- Filtros + page size -->
            <form id="form-match" method="get" class="row g-2 align-items-end mb-2">
              <?= filterHiddenInputs(['action'=>'results','op'=>$currentOp,'tab'=>'match','pM'=>1,'ppS'=>$sys['pp'],'pS'=>$sys['p'],'ppP'=>$plan['pp'],'pP'=>$plan['p']]) ?>
              <input type="hidden" name="ppM" value="<?= (int)$match['pp'] ?>">
              <div class="col-12 col-md-2 d-none"><!-- Empresa (oculto) -->
                <label class="form-label mb-0 small font-weight-bold">Empresa</label>
                <input type="text" name="f_emp" value="<?= htmlspecialchars($filters['f_emp']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label mb-0 small font-weight-bold">Código</label>
                <input type="text" name="f_cod" value="<?= htmlspecialchars($filters['f_cod']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label mb-0 small font-weight-bold">Referência</label>
                <input type="text" name="f_ref" value="<?= htmlspecialchars($filters['f_ref']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                <input type="text" name="f_desc" value="<?= htmlspecialchars($filters['f_desc']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-1">
                <label class="form-label mb-0 small font-weight-bold">NCM</label>
                <input type="text" name="f_ncm" value="<?= htmlspecialchars($filters['f_ncm']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label mb-0 small font-weight-bold">NCM Ident.</label>
                <input type="text" name="f_ncmid" value="<?= htmlspecialchars($filters['f_ncmid']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>

              <div class="col-6 col-md-1">
                <label class="form-label mb-0 small font-weight-bold">Itens/página</label>
                <select class="form-select form-control form-control-sm" name="ppM" onchange="this.form.submit()">
                  <?= ppOptions($match['pp']) ?>
                </select>
              </div>
              <div class="col-6 col-md-2 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 massey-clear-btn">
                  <i class="fas fa-times mr-1"></i>Limpar
                </button>
              </div>
            </form>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-muted">
                <?php if ($match['total'] > 0): ?>
                  Exibindo <strong><?= number_format(($match['p']-1)*$match['pp']+1, 0, ',', '.') ?>–<?= number_format(min($match['p']*$match['pp'], $match['total']), 0, ',', '.') ?></strong> de <strong><?= number_format($match['total'],0,',','.') ?></strong> itens
                <?php else: ?>
                  Total filtrado: <strong>0</strong> itens
                <?php endif; ?>
              </span>
              <?php if ($currentOp): ?>
                <div>
                  <?= tabela_btn_export($exportHref) ?>
                  <?= tabela_btn_atualizar('btn-update-valores', (int)$match['total'], false,
                      ['extra_attrs' => 'data-op="' . e($currentOp) . '"']) ?>
                </div>
              <?php endif; ?>
            </div>

            <?php if (empty($rows)): ?>
              <div class="text-muted">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                 <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <th style="display:none">Empresa</th><th>Código</th><th>Referência</th><th>Descrição</th><th>NCM</th>
                      <th class="text-right">ValorPlanilha (R$)</th><th class="text-right">Overprice</th><th class="text-right">Fator de Conversão</th>
                      <th class="text-right">Preço Público</th><th class="text-right">Preço Sugerido</th><th class="text-right">Preço Garantia</th><th class="text-right">Preço Reposição</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                      <td style="display:none"><?= htmlspecialchars($r['Empresa'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoCodigo'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoReferencia'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoDescricao'] ?? '') ?></td>
                      <td><?= htmlspecialchars(trim(($r['NCMIdentificador'] ?? '').' '.($r['NCMCodigo'] ?? ''))) ?></td>
                      <td class="text-right"><?= number_format((float)($r['ValorPlanilha'] ?? 0), 2, ',', '.') ?></td>
                      <td class="text-right"><?= number_format((float)($r['Overprice'] ?? 0) * 100, 2, ',', '.') ?>%</td>
                      <td class="text-right"><?= number_format((float)($r['IndiceParaCusto'] ?? 0) * 100, 2, ',', '.') ?>%</td>
                      <td class="text-right"><?= number_format((float)($r['PrecoPublico'] ?? 0), 2, ',', '.') ?></td>
                      <td class="text-right"><?= number_format((float)($r['PrecoSugerido'] ?? 0), 2, ',', '.') ?></td>
                      <td class="text-right"><?= number_format((float)($r['PrecoGarantia'] ?? 0), 2, ',', '.') ?></td>
                      <td class="text-right"><?= number_format((float)($r['PrecoReposicao'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <?php $meta = $match; include __DIR__ . '/../components/_server_paginator.php'; ?>
            <?php endif; ?>
          </div>

          <!-- APENAS DEALERNET (ONLY_SYS) -->
          <div class="tab-pane fade <?= $sys['tabActive']?'show active':'' ?>" id="pane-sistema" role="tabpanel">
            <small class="form-text text-muted mb-2 d-block">
              Itens presentes no DealerNet mas não encontrados na planilha.
            </small>
            <form id="form-sys" method="get" class="row g-2 align-items-end mb-2">
              <?= filterHiddenInputs(['action'=>'results','op'=>$currentOp,'tab'=>'sys','pS'=>1,'ppM'=>$match['pp'],'pM'=>$match['p'],'ppP'=>$plan['pp'],'pP'=>$plan['p']]) ?>
              <input type="hidden" name="ppS" value="<?= (int)$sys['pp'] ?>">
              <div class="col-12 col-md-2 d-none"><!-- Empresa (oculto) -->
                <label class="form-label mb-0 small font-weight-bold">Empresa</label>
                <input type="text" name="f_emp" value="<?= htmlspecialchars($filters['f_emp']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label mb-0 small font-weight-bold">Código</label>
                <input type="text" name="f_cod" value="<?= htmlspecialchars($filters['f_cod']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label mb-0 small font-weight-bold">Referência</label>
                <input type="text" name="f_ref" value="<?= htmlspecialchars($filters['f_ref']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                <input type="text" name="f_desc" value="<?= htmlspecialchars($filters['f_desc']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-1">
                <label class="form-label mb-0 small font-weight-bold">NCM</label>
                <input type="text" name="f_ncm" value="<?= htmlspecialchars($filters['f_ncm']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label mb-0 small font-weight-bold">NCM Ident.</label>
                <input type="text" name="f_ncmid" value="<?= htmlspecialchars($filters['f_ncmid']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-1">
                <label class="form-label mb-0 small font-weight-bold">Itens/página</label>
                <select class="form-select form-control form-control-sm" name="ppS" onchange="this.form.submit()">
                  <?= ppOptions($sys['pp']) ?>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 massey-clear-btn">
                  <i class="fas fa-times mr-1"></i>Limpar
                </button>
              </div>
            </form>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-muted">
                <?php if ($sys['total'] > 0): ?>
                  Exibindo <strong><?= number_format(($sys['p']-1)*$sys['pp']+1, 0, ',', '.') ?>–<?= number_format(min($sys['p']*$sys['pp'], $sys['total']), 0, ',', '.') ?></strong> de <strong><?= number_format($sys['total'],0,',','.') ?></strong> itens
                <?php else: ?>
                  Total filtrado: <strong>0</strong> itens
                <?php endif; ?>
              </span>
              <?php if ($currentOp): ?>
                <div>
                  <?= tabela_btn_export($exportSysHref) ?>
                </div>
              <?php endif; ?>
            </div>

            <?php if (empty($soSistema)): ?>
              <div class="text-muted">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr><th>Código</th><th>Referência</th><th>Descrição</th><th>NCM</th></tr>
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

              <?php $meta = $sys; include __DIR__ . '/../components/_server_paginator.php'; ?>
            <?php endif; ?>
          </div>

          <!-- APENAS PLANILHA (ONLY_PLAN) -->
          <div class="tab-pane fade <?= $plan['tabActive']?'show active':'' ?>" id="pane-planilha" role="tabpanel">
            <small class="form-text text-muted mb-2 d-block">
              Itens encontrados na planilha mas não presentes no DealerNet.
            </small>
            <form id="form-plan" method="get" class="row g-2 align-items-end mb-2">
              <?= filterHiddenInputs(['action'=>'results','op'=>$currentOp,'tab'=>'plan','pP'=>1,'ppM'=>$match['pp'],'pM'=>$match['p'],'ppS'=>$sys['pp'],'pS'=>$sys['p']]) ?>
              <input type="hidden" name="ppP" value="<?= (int)$plan['pp'] ?>">
              <!-- Empresa (oculto) -->
              <input type="hidden" name="f_emp" value="<?= htmlspecialchars($filters['f_emp']) ?>">
              <!-- Código e NCM não se aplicam a itens apenas-planilha (ProdutoCodigo/NCM são NULL) -->
              <input type="hidden" name="f_cod"   value="">
              <input type="hidden" name="f_ncm"   value="">
              <input type="hidden" name="f_ncmid" value="">
              <div class="col-6 col-md-3">
                <label class="form-label mb-0 small font-weight-bold">Referência</label>
                <input type="text" name="f_ref" value="<?= htmlspecialchars($filters['f_ref']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                <input type="text" name="f_desc" value="<?= htmlspecialchars($filters['f_desc']) ?>" class="form-control form-control-sm" placeholder="contém...">
              </div>
              <div class="col-6 col-md-1">
                <label class="form-label mb-0 small font-weight-bold">Itens/página</label>
                <select class="form-select form-control form-control-sm" name="ppP" onchange="this.form.submit()">
                  <?= ppOptions($plan['pp']) ?>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 massey-clear-btn">
                  <i class="fas fa-times mr-1"></i>Limpar
                </button>
              </div>
            </form>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-muted">
                <?php if ($plan['total'] > 0): ?>
                  Exibindo <strong><?= number_format(($plan['p']-1)*$plan['pp']+1, 0, ',', '.') ?>–<?= number_format(min($plan['p']*$plan['pp'], $plan['total']), 0, ',', '.') ?></strong> de <strong><?= number_format($plan['total'],0,',','.') ?></strong> itens
                <?php else: ?>
                  Total filtrado: <strong>0</strong> itens
                <?php endif; ?>
              </span>
              <?php if ($currentOp): ?>
                <div>
                  <?= tabela_btn_export($exportPlanHref) ?>
                </div>
              <?php endif; ?>
            </div>

            <?php if (empty($soPlanilha)): ?>
              <div class="text-muted">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr><th>Ref. Planilha</th><th>Descrição</th><th class="text-right">ValorPlanilha (R$)</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($soPlanilha as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['ProdutoReferencia'] ?? '') ?></td>
                      <td><?= htmlspecialchars($r['ProdutoDescricao'] ?? '') ?></td>
                      <td class="text-right"><?= number_format((float)($r['ValorPlanilha'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <?php $meta = $plan; include __DIR__ . '/../components/_server_paginator.php'; ?>
            <?php endif; ?>
          </div>

        </div><!-- /tab-content -->
      </div><!-- /card-body -->
    </div><!-- /card -->
  </div><!-- /container-fluid -->
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Atualiza label do custom file input
  var input = document.getElementById('arquivo');
  if (input) {
    input.addEventListener('change', function () {
      var lbl = document.querySelector('label.custom-file-label[for="arquivo"]');
      if (lbl && input.files && input.files.length > 0) lbl.textContent = input.files[0].name;
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// Massey Ferguson — Import chunked + Atualizar Valores chunked
// Cada requisição processa um pedaço pequeno e atualiza a barra em tempo real.
// O usuário é avisado para não fechar a aba durante o processamento.
// ─────────────────────────────────────────────────────────────────────────────
(function () {
  'use strict';

  // Constantes de polling alinhadas com o layout global (adminlte.php)
  const POLL_MS     = <?= \Controllers\AgroMasseyController::UPDATE_POLL_INTERVAL_MS ?>;
  const STALE_TICKS = <?= \Controllers\AgroMasseyController::UPDATE_STALE_TICKS ?>;

  // Sinaliza ao script global do layout que esta página já gerencia o polling
  window.masseyPageActive = true;

  const form       = document.getElementById('mf-form');

  const ABS_BASE        = "<?= addslashes($absBase) ?>";
  const CURRENT_OP_INIT = "<?= addslashes($currentOp) ?>";  // op da página atual (vazio se não há resultados)

  let isProcessing = false;

  /* ── helpers ────────────────────────────────────────────────────────────── */

  function urlWith(qs) {
    const u = new URL(ABS_BASE);
    new URLSearchParams(qs).forEach((v, k) => u.searchParams.set(k, v));
    return u.toString();
  }

  /* ── Wrappers do PortalModal (views/components/progress_modal.php) ── */

  function showModal(title, bodyHtml) {
    PortalModal.show(
      title    || '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i>Processando...',
      bodyHtml || ''
    );
  }

  function setProgress(step, pct, msg) {
    var label = (step ? '<strong class="text-uppercase small">' + step + '</strong> — ' : '') + (msg || '');
    PortalModal.update(PortalModal.progressHtml(pct, label));
  }

  function setModalTitle(html) {
    PortalModal.setTitle(html);
  }

  function showError(title, detail) {
    PortalModal.show(
      '<i class="fas fa-exclamation-circle mr-2 text-danger"></i>' + (title || 'Erro'),
      '<div class="alert alert-danger mb-0">' + (detail || 'Falha inesperada.') + '</div>'
    );
    console.error(title, detail);
  }

  function resetBar() { /* no-op — PortalModal.show() always starts fresh */ }

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
        showError('Upload com erro', err.message || String(err));
        alert(err.message || err);
        return;
      }
      if (!uploadResp.ok || !uploadResp.op) {
        showError('Upload falhou', uploadResp.msg || 'Sem OP retornado.');
        return;
      }

      const op = uploadResp.op;
      showModal(
        '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i>Importando arquivo...',
        PortalModal.progressHtml(3, 'Arquivo enviado. Iniciando importação em etapas...')
      );
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

        const resultUrl = urlWith('action=results&op=' + encodeURIComponent(op) + '&tab=match');
        setModalTitle('<i class="fas fa-check-circle mr-2 text-success"></i>Processamento concluído');
        PortalModal.update(
          PortalModal.progressHtml(100,
            'Relação: ' + fj.match.toLocaleString('pt-BR') +
            ' | Só planilha: ' + fj.only_plan.toLocaleString('pt-BR') +
            ' | Só sistema: '  + fj.only_sys.toLocaleString('pt-BR')
          ) +
          '<div class="text-center mt-3">' +
            '<a href="' + resultUrl + '" class="btn btn-success">' +
              '<i class="fas fa-check mr-1"></i> Ver Resultados' +
            '</a>' +
          '</div>'
        );
        setTimeout(() => goToResults(op), 1500);

      } catch (err) {
        showError('Erro no processamento', err.message || String(err));
      } finally {
        setProcessing(false);
      }
    });
  }

  /* ── ATUALIZAR VALORES — PortalToast + worker CLI async ─────────────── */
  //
  // O toast do canto inferior direito é renderizado pelo componente global
  // views/components/toast.php (window.PortalToast), incluído em adminlte.php.

  let isUpdating  = false;
  let origBtnHtml = '';

  /* ── Aliases para o componente PortalToast ───────────────────────────── */
  const progressHtml     = (pct, msg, canNav) => PortalToast.progressHtml(pct, msg, canNav);
  const showNotice       = (type, title, html, ms) => PortalToast.show(type, title, html, ms);
  const updateNoticeBody = (html) => PortalToast.update(html);

  /* ── Polling de status → atualiza o toast ao vivo ──────────────────── */
  async function pollUpdateProgress(op, canNav) {
    let lastUpdatedAt = null;
    let staleTicks    = 0;
    while (true) {
      await new Promise(res => setTimeout(res, POLL_MS));
      try {
        const r = await fetch(
          urlWith('action=status&op=' + encodeURIComponent(op)),
          { cache: 'no-store' }
        );
        if (!r.ok) continue;
        const j = await r.json();
        if (!j.ok || !j.data) continue;
        const step = j.data.Step || '';

        if (step === 'UPDATE_DONE') {
          localStorage.removeItem('pi_massey_update');
          showNotice('success', 'Massey — Preços atualizados',
            (j.data.Message || 'Concluído.') +
            '<br><small class="text-muted">Pode continuar normalmente.</small>',
            30000);
          return;
        }
        if (step === 'UPDATE_ERROR') {
          localStorage.removeItem('pi_massey_update');
          showNotice('danger', 'Massey — Erro',
            (j.data.Message || 'Erro desconhecido.') +
            '<br><small class="text-muted">Verifique e tente novamente.</small>',
            30000);
          return;
        }
        if (step === 'UPDATE_RUNNING' || step === 'UPDATE_QUEUED') {
          if (j.data.UpdatedAt === lastUpdatedAt) {
            if (++staleTicks >= STALE_TICKS) {
              localStorage.removeItem('pi_massey_update');
              showNotice('danger', 'Massey — Interrompido',
                'Sem progresso detectado (60 s). A operação pode ter sido interrompida.<br>' +
                '<small class="text-muted">Tente novamente.</small>', 30000);
              return;
            }
          } else {
            staleTicks    = 0;
            lastUpdatedAt = j.data.UpdatedAt;
          }
          const done = parseInt(j.data.Done,  10) || 0;
          const tot  = parseInt(j.data.Total, 10) || 0;
          const pct  = tot > 0 ? Math.round(done / tot * 100) : 0;
          updateNoticeBody(progressHtml(pct, j.data.Message || 'Processando...', canNav));
        }
      } catch (_) { /* rede — retenta */ }
    }
  }

  /* ── Fallback A: JS-batch loop (exec desabilitado) ──────────────────── */
  async function runBatchedWithToast(op) {
    let offset = 0, total = 0, auditOp = '', totalF = 0, totalA = 0;
    while (true) {
      const qs = 'action=update_all'
        + '&op='              + encodeURIComponent(op)
        + '&offset='          + offset
        + '&total='           + total
        + '&audit_op='        + encodeURIComponent(auditOp)
        + '&acc_fechados='    + totalF
        + '&acc_atualizados=' + totalA;
      const r = await fetch(urlWith(qs), { cache: 'no-store' });
      if (!r.ok) { const t = await r.text().catch(() => ''); throw new Error('HTTP ' + r.status + ': ' + t); }
      const j = await r.json();
      if (!j.ok) throw new Error(j.msg || 'Erro.');
      offset   = j.offset; total  = j.total  || total;
      auditOp  = j.audit_op || auditOp;
      totalF  += j.batch_fechados    || 0;
      totalA  += j.batch_atualizados || 0;
      const pct = total > 0 ? Math.round(offset / total * 100) : 0;
      updateNoticeBody(progressHtml(pct,
        offset.toLocaleString('pt-BR') + ' / ' + total.toLocaleString('pt-BR') + ' produtos',
        false));
      if (j.finished) break;
    }
    const msg = totalA.toLocaleString('pt-BR') + ' preços atualizados'
      + (totalF > 0 ? ', ' + totalF.toLocaleString('pt-BR') + ' fechados' : '') + '.';
    showNotice('success', 'Massey — Preços atualizados', msg, 30000);
  }

  /* ── Fallback B: chunk SP (SP_LOTE não existe) ──────────────────────── */
  async function runChunkedWithToast(op) {
    let offset = 0, total = 0, auditOp = '', totalOk = 0, totalFail = 0;
    const limit = 100;
    while (true) {
      const r = await fetch(urlWith(
        'action=update_chunk&op=' + encodeURIComponent(op) +
        '&offset=' + offset + '&limit=' + limit +
        '&total=' + total + '&audit_op=' + encodeURIComponent(auditOp)
      ), { cache: 'no-store' });
      if (!r.ok) { const t = await r.text().catch(() => ''); throw new Error('Chunk HTTP ' + r.status + ': ' + t); }
      const j = await r.json();
      if (!j.ok) throw new Error(j.msg || 'Erro.');
      offset    = j.done; total = j.total; auditOp = j.audit_op || auditOp;
      totalOk  += j.proc_ok   || 0;
      totalFail += j.proc_fail || 0;
      const pct = total > 0 ? Math.round(offset / total * 100) : 0;
      updateNoticeBody(progressHtml(pct,
        offset.toLocaleString('pt-BR') + ' / ' + total.toLocaleString('pt-BR') +
        ' — OK: ' + totalOk.toLocaleString('pt-BR') +
        (totalFail > 0 ? ' | Falhas: ' + totalFail.toLocaleString('pt-BR') : ''),
        false));
      if (j.finished) break;
    }
    const msg = totalOk.toLocaleString('pt-BR') + ' preços atualizados'
      + (totalFail > 0 ? ', ' + totalFail + ' falhas' : '') + '.';
    showNotice('success', 'Massey — Preços atualizados', msg, 30000);
  }

  /* ── Botão "Atualizar Valores" ── */
  const btnUpdate = document.getElementById('btn-update-valores');
  if (btnUpdate) {
    btnUpdate.addEventListener('click', async function () {
      if (isProcessing || isUpdating) return;
      const op = btnUpdate.dataset.op;
      if (!op) { alert('Nenhuma importação ativa. Processe um arquivo primeiro.'); return; }
      if (!confirm(
        'Atualizar preços de todos os itens MATCH no DealerNet?\n\n' +
        'O processo rodará em segundo plano — você pode navegar livremente.'
      )) return;

      origBtnHtml = btnUpdate.innerHTML;
      isUpdating  = true;
      btnUpdate.disabled = true;
      btnUpdate.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Atualizando...';

      // Mostra o toast imediatamente
      showNotice('info', 'Massey — Atualizando', progressHtml(0, 'Iniciando...', true));

      try {
        let asyncOk  = false;
        let startErr = null;
        try {
          const r = await fetch(
            urlWith('action=update_all_start&op=' + encodeURIComponent(op)),
            { cache: 'no-store' }
          );
          if (!r.ok) { const t = await r.text().catch(() => ''); throw new Error('HTTP ' + r.status + ': ' + t); }
          const j = await r.json();
          if (!j.ok) throw new Error(j.msg || 'Falha ao iniciar worker.');
          asyncOk = (j.async === true);
        } catch (e) { startErr = e; }

        if (startErr && /já está em andamento/i.test(startErr.message)) {
          updateNoticeBody(progressHtml(0, 'Já em andamento. Aguardando...', true));
          await pollUpdateProgress(op, true);
          return;
        }

        if (asyncOk) {
          // Async: pede permissão de notificação, salva no localStorage, pollar
          if (window.Notification && Notification.permission === 'default') {
            Notification.requestPermission();
          }
          localStorage.setItem('pi_massey_update', JSON.stringify({
            op: op,
            statusUrl: ABS_BASE + '?action=status&op=' + encodeURIComponent(op),
            startedAt: Date.now()
          }));
          updateNoticeBody(progressHtml(0, 'Worker iniciado...', true));
          await pollUpdateProgress(op, true);

        } else {
          // Fallback JS-loop (exec desabilitado)
          if (startErr) console.warn('update_all_start falhou, fallback JS-batch:', startErr.message);
          window.onbeforeunload = () => 'Atualização em andamento. Sair irá interromper o processo.';
          updateNoticeBody(progressHtml(0, 'Iniciando (modo direto)...', false));
          try {
            await runBatchedWithToast(op);
          } catch (batchErr) {
            if (/PRC_VALENCE_DN_ATUALIZAR_PRECOS_LOTE|Could not find/i.test(batchErr.message)) {
              try { await runChunkedWithToast(op); }
              catch (ce) { showNotice('danger', 'Massey — Erro', ce.message || String(ce), 20000); }
            } else {
              showNotice('danger', 'Massey — Erro', batchErr.message || String(batchErr), 20000);
            }
          }
        }

      } catch (e) {
        showNotice('danger', 'Massey — Erro', e.message || String(e), 20000);
      } finally {
        window.onbeforeunload = null;
        isUpdating = false;
        btnUpdate.disabled = false;
        btnUpdate.innerHTML = origBtnHtml;
      }
    });
  }

  /* ── Polling de status para page-load resume (notice flutuante) ─────── */
  async function pollUpdateStatus(op) {
    let lastUpdatedAt = null;
    let staleTicks    = 0;
    while (true) {
      await new Promise(res => setTimeout(res, POLL_MS));
      try {
        const r = await fetch(
          urlWith('action=status&op=' + encodeURIComponent(op)),
          { cache: 'no-store' }
        );
        const j = await r.json();
        if (!j.ok || !j.data) break;
        const step = j.data.Step || '';
        if (step === 'UPDATE_DONE') {
          localStorage.removeItem('pi_massey_update');
          showNotice('success', 'Massey — Preços atualizados',
            (j.data.Message || 'Concluído.') +
            '<br><small class="text-muted">Pode continuar normalmente.</small>', 30000);
          break;
        }
        if (step === 'UPDATE_ERROR') {
          localStorage.removeItem('pi_massey_update');
          showNotice('danger', 'Massey — Erro',
            j.data.Message || 'Erro desconhecido.', 30000);
          break;
        }
        if (step === 'UPDATE_RUNNING' || step === 'UPDATE_QUEUED') {
          if (j.data.UpdatedAt === lastUpdatedAt) {
            if (++staleTicks >= STALE_TICKS) {
              showNotice('danger', 'Massey — Interrompido',
                'Sem progresso detectado (60 s). A operação pode ter sido interrompida.', 30000);
              break;
            }
          } else {
            staleTicks = 0; lastUpdatedAt = j.data.UpdatedAt;
          }
          const done = parseInt(j.data.Done,  10) || 0;
          const tot  = parseInt(j.data.Total, 10) || 0;
          const pct  = tot > 0 ? Math.round(done / tot * 100) : 0;
          updateNoticeBody(progressHtml(pct, j.data.Message || 'Processando...', true));
        }
      } catch (_) { /* rede — retenta */ }
    }
    isUpdating = false;
    if (btnUpdate) btnUpdate.disabled = false;
  }

  /* ── Verificação no carregamento: retoma polling se havia update em curso ─ */
  if (CURRENT_OP_INIT) {
    (async () => {
      try {
        const r = await fetch(
          urlWith('action=status&op=' + encodeURIComponent(CURRENT_OP_INIT)),
          { cache: 'no-store' }
        );
        const j = await r.json();
        if (!j.ok || !j.data) return;
        const step = j.data.Step || '';

        if (step === 'UPDATE_DONE') {
          localStorage.removeItem('pi_massey_update');
          showNotice('success', 'Massey — Preços atualizados',
            (j.data.Message || 'Concluído.') +
            '<br><small class="text-muted">Pode continuar normalmente.</small>', 30000);
          return;
        }
        if (step === 'UPDATE_ERROR') {
          localStorage.removeItem('pi_massey_update');
          showNotice('danger', 'Massey — Erro',
            (j.data.Message || 'Erro desconhecido.') +
            '<br><small class="text-muted">Verifique e tente novamente.</small>', 30000);
          return;
        }
        if (step !== 'UPDATE_RUNNING' && step !== 'UPDATE_QUEUED') return;

        // Ignora se UpdatedAt > 10 min
        const updatedAt = j.data.UpdatedAt;
        if (updatedAt) {
          const ageMs = Date.now() - new Date(updatedAt.replace(' ', 'T')).getTime();
          if (ageMs > 10 * 60 * 1000) return;
        }

        // Ainda rodando: mostra toast e pollar
        isUpdating = true;
        if (btnUpdate) btnUpdate.disabled = true;
        const done = parseInt(j.data.Done,  10) || 0;
        const tot  = parseInt(j.data.Total, 10) || 0;
        const pct  = tot > 0 ? Math.round(done / tot * 100) : 0;
        showNotice('info', 'Massey — Atualizando',
          progressHtml(pct, j.data.Message || 'Processando...', true));
        await pollUpdateStatus(CURRENT_OP_INIT);
      } catch (_) { /* ignora */ }
    })();
  }

  // PING de sanidade (pode remover em produção)
  fetch(urlWith('action=ping'), { cache: 'no-store' })
    .then(r => r.ok ? r.text() : Promise.reject('HTTP ' + r.status))
    .then(t => console.info('PING OK:', t))
    .catch(e => console.warn('PING FAIL:', e));

  // ── Modal de progresso para Exportar CSV ─────────────────────────────────
  // Bloqueia a tela enquanto o servidor gera o arquivo.
  // O servidor seta um cookie (mf_csv_ready={token}) logo após o fetchAll();
  // o JS detecta via polling e fecha o modal quando o arquivo começa a chegar.
  (function () {

    /**
     * Gera o corpo do modal de exportação.
     * @param {number}  pct   0-100
     * @param {string}  msg   HTML da linha de status
     * @param {boolean} done  true → barra verde sem animação, sem aviso de navegação
     */
    function exportBody(pct, msg, done) {
      var barCls = done
        ? 'progress-bar bg-success'
        : 'progress-bar progress-bar-striped progress-bar-animated bg-primary';
      return (
        '<div class="progress mb-3" style="height:20px;border-radius:10px;overflow:hidden;">' +
          '<div class="' + barCls + '"' +
               ' role="progressbar"' +
               ' style="width:' + pct + '%;transition:width .6s ease;"' +
               ' aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
            pct + '%' +
          '</div>' +
        '</div>' +
        '<p class="mb-3 text-dark">' + msg + '</p>' +
        (done ? '' :
          '<div class="alert alert-warning py-1 px-2 mb-0 small">' +
            '<i class="fas fa-exclamation-triangle mr-1"></i>' +
            '<strong>Não navegue</strong> — sair desta página enquanto o arquivo é gerado pode interromper o processo.' +
          '</div>'
        )
      );
    }

    document.querySelectorAll('a[href*="action=export"]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();

        var url = link.href;

        // Abre o modal bloqueante
        var pct = 2;
        PortalModal.show(
          '<i class="fas fa-file-export mr-2 text-secondary"></i>Exportar CSV',
          exportBody(pct, '<i class="fas fa-database mr-1 text-muted"></i>Consultando dados no banco…', false)
        );

        // Barra que cresce de forma logarítmica até ~90 % enquanto espera o fetch
        var animTimer = setInterval(function () {
          pct += Math.max(0.4, (90 - pct) * 0.045);
          if (pct >= 90) { pct = 90; clearInterval(animTimer); }
          PortalModal.update(exportBody(
            Math.round(pct),
            '<i class="fas fa-cog fa-spin mr-1 text-muted"></i>Gerando arquivo CSV… Isso pode levar alguns minutos para tabelas grandes.',
            false
          ));
        }, 900);

        // Usa fetch para saber exatamente quando o arquivo ficou pronto
        fetch(url, { cache: 'no-store' })
          .then(function (response) {
            if (!response.ok) throw new Error('Erro HTTP ' + response.status);
            // Tenta extrair o nome do arquivo do header Content-Disposition
            var disposition = response.headers.get('Content-Disposition') || '';
            var fnMatch = disposition.match(/filename="?([^";\r\n]+)"?/i);
            var filename = fnMatch ? fnMatch[1].trim() : 'export.csv';
            return response.blob().then(function (blob) {
              return { blob: blob, filename: filename };
            });
          })
          .then(function (result) {
            clearInterval(animTimer);

            // Dispara o download via blob URL — sem navegar para fora da página
            var blobUrl = URL.createObjectURL(result.blob);
            var a = document.createElement('a');
            a.href = blobUrl;
            a.download = result.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 10000);

            // Vai para 100 % imediatamente quando o arquivo chega
            PortalModal.update(exportBody(
              100,
              '<i class="fas fa-check-circle mr-1 text-success"></i>' +
              '<strong>Arquivo pronto!</strong> O download deve iniciar automaticamente.',
              true
            ));

            // Fecha modal após breve pausa
            setTimeout(function () { PortalModal.hide(); }, 1500);
          })
          .catch(function (err) {
            clearInterval(animTimer);
            PortalModal.update(exportBody(
              0,
              '<i class="fas fa-times-circle mr-1 text-danger"></i>' +
              '<strong>Erro ao gerar o arquivo.</strong> ' + (err.message || ''),
              true
            ));
          });
      });
    });

  }());

})();

/* ── Ir para página — Massey (server-side pagination) ── */
(function () {
  function masseyGoTo(input) {
    var p   = parseInt(input.value, 10);
    var max = parseInt(input.getAttribute('data-max'), 10) || 1;
    if (isNaN(p) || p < 1) p = 1;
    if (p > max) p = max;
    var qs   = input.getAttribute('data-qs');
    var base = input.getAttribute('data-base');
    window.location.href = base + '?' + qs.replace('%d', p);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.massey-goto-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var inp = btn.previousElementSibling;
        if (inp) masseyGoTo(inp);
      });
    });
    document.querySelectorAll('.massey-goto-input').forEach(function (inp) {
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); masseyGoTo(inp); }
      });
    });
  });
}());

/* ── Auto-filtro Massey (debounce) — dispara submit após 450 ms sem digitação ── */
(function () {
  'use strict';

  var DEBOUNCE_MS = 450;

  function initAutoFilter(formId) {
    var form = document.getElementById(formId);
    if (!form) return;

    var timer = null;

    /* Debounce em inputs de texto */
    form.querySelectorAll('input[type="text"]').forEach(function (inp) {
      inp.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, DEBOUNCE_MS);
      });

      /* Enter imediato */
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(timer);
          form.submit();
        }
      });
    });

    /* Botão Limpar: zera inputs de texto e submete */
    var clearBtn = form.querySelector('.massey-clear-btn');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        clearTimeout(timer);
        form.querySelectorAll('input[type="text"]').forEach(function (i) { i.value = ''; });
        form.submit();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initAutoFilter('form-match');
    initAutoFilter('form-sys');
    initAutoFilter('form-plan');
  });
}());
</script>
