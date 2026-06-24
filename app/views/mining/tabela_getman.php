<?php
// Espera: $erro, $rows, $soSistema, $soPlanilha, $cot, $uiMkLe,$uiFiLe,$uiMkGt,$uiFiGt, $marcaNome
require_once __DIR__ . '/../components/_tabela_buttons.php';

$base = base_url('mining/tabela-getman');

$temResultado = !empty($rows);
$temSysOnly   = !empty($soSistema);
$temXlsxOnly  = !empty($soPlanilha);

$flash = $_SESSION['getman_flash'] ?? '';
$flashClass = (stripos($flash, 'Falha') !== false || stripos($flash, 'Sem permissão') !== false)
    ? 'alert-danger' : 'alert-info';
if ($flash) unset($_SESSION['getman_flash']);
?>
<section class="content pt-3">
  <div class="container-fluid">
    <div class="card card-outline card-primary">

      <!-- ── Card header ───────────────────────────────────────────────── -->
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Mining — Tabela Getman</h3>
        <span class="text-muted small">Marca: <strong><?= e($marcaNome ?? 'Getman') ?></strong></span>
      </div>

      <div class="card-body">

        <?php if ($flash): ?>
          <div class="alert <?= $flashClass ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $flashClass === 'alert-danger' ? 'exclamation-triangle' : 'info-circle' ?> mr-1"></i>
            <?= nl2br(e($flash)) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <?php include __DIR__ . '/../components/_erro_alert.php'; ?>

        <!-- ── Formulário de processamento ──────────────────────────────── -->
        <form action="<?= $base ?>" method="post" enctype="multipart/form-data" class="mb-3">
          <?php csrf_field(); ?>

          <div class="form-row">
            <div class="form-group col-md-8">
              <label class="mb-0">Arquivo (.xlsx) <span class="text-danger">*</span></label>
              <div class="custom-file">
                <input type="file" class="custom-file-input" id="arquivo" name="arquivo" accept=".xlsx" required>
                <label class="custom-file-label" for="arquivo" data-browse="Procurar">Escolher arquivo...</label>
              </div>
              <small class="form-text text-muted">
                Cabeçalho esperado: <code>PartNum</code> / <code>PartDescription</code> / coluna de preço (ex.: "2026 GLP25").
              </small>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-2">
              <label class="mb-0">Cotação do Dólar</label>
              <input type="number" name="cotacao" step="0.0001"
                     class="form-control form-control-sm"
                     value="<?= e((string)$cot) ?>" required>
              <small class="form-text text-muted">Ex.: 5.40</small>
            </div>
            <div class="form-group col-md-2">
              <label class="mb-0">Markup ≤ US$ 10.000</label>
              <input type="text" name="mk_le" class="form-control form-control-sm" value="<?= e($uiMkLe) ?>" required>
              <small class="form-text text-muted">Ex.: 30%</small>
            </div>
            <div class="form-group col-md-2">
              <label class="mb-0">Fator ≤ US$ 10.000</label>
              <input type="text" name="fi_le" class="form-control form-control-sm" value="<?= e($uiFiLe) ?>" required>
              <small class="form-text text-muted">Ex.: 35%</small>
            </div>
            <div class="form-group col-md-2">
              <label class="mb-0">Markup &gt; US$ 10.000</label>
              <input type="text" name="mk_gt" class="form-control form-control-sm" value="<?= e($uiMkGt) ?>" required>
              <small class="form-text text-muted">Ex.: 20%</small>
            </div>
            <div class="form-group col-md-2">
              <label class="mb-0">Fator &gt; US$ 10.000</label>
              <input type="text" name="fi_gt" class="form-control form-control-sm" value="<?= e($uiFiGt) ?>" required>
              <small class="form-text text-muted">Ex.: 35%</small>
            </div>
          </div>

          <input type="hidden" name="marca_id" value="236">

          <div class="form-row">
            <div class="form-group col-md-4">
              <?= tabela_btn_processar() ?>
            </div>
          </div>
        </form>

        <!-- ── Abas de resultado ─────────────────────────────────────────── -->
        <ul class="nav nav-tabs" id="getmanTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="tab-relacao" data-toggle="tab" href="#pane-relacao" role="tab">
              Relação de Itens <?= $temResultado ? '(' . number_format(count($rows), 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-sistema" data-toggle="tab" href="#pane-sistema" role="tab">
              Itens Apenas DealerNet <?= $temSysOnly ? '(' . number_format(count($soSistema), 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-planilha" data-toggle="tab" href="#pane-planilha" role="tab">
              Itens Apenas Planilha <?= $temXlsxOnly ? '(' . number_format(count($soPlanilha), 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
        </ul>

        <div class="tab-content pt-3" id="getmanTabsContent">

          <!-- RELAÇÃO -->
          <div class="tab-pane fade show active" id="pane-relacao" role="tabpanel">
            <?php if (!$temResultado): ?>
              <div class="alert alert-secondary">Nenhum registro processado.</div>
            <?php else: ?>
              <!-- Filtros (client-side; colunas resolvidas pelo nome do <th>) -->
              <div id="filter-bar-relacao" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Código</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="ProdutoCodigo" placeholder="contém...">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Referência</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="ProdutoReferencia" placeholder="contém...">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="ProdutoDescricao" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">NCM</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="NCMCodigo" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">Tipo</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Tipo" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">Itens/pág</label>
                  <select class="form-control form-control-sm select-pp">
                    <option value="25">25</option>
                    <option value="100">100</option>
                    <option value="500">500</option>
                  </select>
                </div>
                <div class="col-6 col-md-2 d-flex align-items-end">
                  <button type="button" class="btn btn-sm btn-outline-secondary btn-filter-clear w-100">
                    <i class="fas fa-times mr-1"></i>Limpar
                  </button>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted" id="info-relacao">
                  Total: <strong><?= number_format(count($rows), 0, ',', '.') ?></strong> itens
                </span>
                <div>
                  <?= tabela_btn_export($base . '?export=1') ?>
                  <?= tabela_btn_atualizar('btn-update-getman', count($rows)) ?>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($rows[0]) as $c): ?>
                        <th><?= e($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody id="tbody-relacao">
                    <?php foreach ($rows as $r): ?>
                      <tr>
                        <?php foreach ($r as $k => $v): ?>
                          <?php if (in_array($k, ['ProdutoCodigo','ProdutoReferencia','NCMIdentificador','NCMCodigo','PartDescription'], true)): ?>
                            <td><?= e((string)$v) ?></td>
                          <?php elseif (in_array($k, ['Markup','FatorImportacao'], true)): ?>
                            <td class="text-right"><?= number_format((float)$v * 100, 2, ',', '.') ?>%</td>
                          <?php elseif (is_numeric($v)): ?>
                            <td class="text-right"><?= number_format((float)$v, 2, ',', '.') ?></td>
                          <?php else: ?>
                            <td><?= e((string)$v) ?></td>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <nav aria-label="Paginação Relação" class="mt-2" id="nav-relacao"></nav>
            <?php endif; ?>
          </div>

          <!-- APENAS DEALERNET -->
          <div class="tab-pane fade" id="pane-sistema" role="tabpanel">
            <small class="form-text text-muted mb-2 d-block">
              Itens presentes no DealerNet mas não encontrados na planilha.
            </small>
            <?php if (!$temSysOnly): ?>
              <div class="alert alert-secondary mb-0">Nenhum registro.</div>
            <?php else: ?>
              <!-- Filtros (client-side; colunas resolvidas pelo nome do <th>) -->
              <div id="filter-bar-sistema" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Código</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="ProdutoCodigo" placeholder="contém...">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Referência</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="ProdutoReferencia" placeholder="contém...">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="ProdutoDescricao" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">NCM</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="NCMCodigo" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">NCM Id.</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="NCMIdentificador" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">Itens/pág</label>
                  <select class="form-control form-control-sm select-pp">
                    <option value="25">25</option>
                    <option value="100">100</option>
                    <option value="500">500</option>
                  </select>
                </div>
                <div class="col-6 col-md-2 d-flex align-items-end">
                  <button type="button" class="btn btn-sm btn-outline-secondary btn-filter-clear w-100">
                    <i class="fas fa-times mr-1"></i>Limpar
                  </button>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted" id="info-sistema">Total: <strong><?= number_format(count($soSistema), 0, ',', '.') ?></strong></span>
                <?= tabela_btn_export($base . '?export=1&tab=sys') ?>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($soSistema[0]) as $c): ?>
                        <th><?= e($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody id="tbody-sistema">
                    <?php foreach ($soSistema as $r): ?>
                      <tr>
                        <?php foreach ($r as $k => $v): ?>
                          <?php if (is_numeric($v)): ?>
                            <td class="text-right"><?= number_format((float)$v, 2, ',', '.') ?></td>
                          <?php else: ?>
                            <td><?= e((string)$v) ?></td>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <nav aria-label="Paginação DealerNet" class="mt-2" id="nav-sistema"></nav>
            <?php endif; ?>
          </div>

          <!-- APENAS PLANILHA -->
          <div class="tab-pane fade" id="pane-planilha" role="tabpanel">
            <small class="form-text text-muted mb-2 d-block">
              Itens encontrados na planilha mas não presentes no DealerNet.
            </small>
            <?php if (!$temXlsxOnly): ?>
              <div class="alert alert-secondary mb-0">Nenhum registro.</div>
            <?php else: ?>
              <!-- Filtros (client-side; colunas resolvidas pelo nome do <th>) -->
              <div id="filter-bar-planilha" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Referência</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="ProdutoReferencia" placeholder="contém...">
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="PartDescription" placeholder="contém...">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Ref. Normalizada</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="RefNormalizada" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">Itens/pág</label>
                  <select class="form-control form-control-sm select-pp">
                    <option value="25">25</option>
                    <option value="100">100</option>
                    <option value="500">500</option>
                  </select>
                </div>
                <div class="col-6 col-md-2 d-flex align-items-end">
                  <button type="button" class="btn btn-sm btn-outline-secondary btn-filter-clear w-100">
                    <i class="fas fa-times mr-1"></i>Limpar
                  </button>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted" id="info-planilha">Total: <strong><?= number_format(count($soPlanilha), 0, ',', '.') ?></strong></span>
                <?= tabela_btn_export($base . '?export=1&tab=plan') ?>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($soPlanilha[0]) as $c): ?>
                        <th><?= e($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody id="tbody-planilha">
                    <?php foreach ($soPlanilha as $r): ?>
                      <tr>
                        <?php foreach ($r as $k => $v): ?>
                          <?php if (is_numeric($v)): ?>
                            <td class="text-right"><?= number_format((float)$v, 2, ',', '.') ?></td>
                          <?php else: ?>
                            <td><?= e((string)$v) ?></td>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <nav aria-label="Paginação Planilha" class="mt-2" id="nav-planilha"></nav>
            <?php endif; ?>
          </div>

        </div><!-- /tab-content -->
      </div><!-- /card-body -->
    </div><!-- /card -->
  </div><!-- /container-fluid -->
</section>

<script src="<?= base_url('assets/js/client-table.js') ?>"></script>
<script>
(function () {
  'use strict';

  var BASE       = <?= json_encode(base_url('mining/tabela-getman')) ?>;
  var TOTAL_ROWS = <?= (int)count($rows ?? []) ?>;

  /* Atualiza label do custom file input */
  document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('arquivo');
    if (input) {
      input.addEventListener('change', function () {
        var lbl = document.querySelector('label.custom-file-label[for="arquivo"]');
        if (lbl && input.files && input.files.length > 0) lbl.textContent = input.files[0].name;
      });
    }
  });

  /* Processar: mostra modal bloqueante enquanto o POST carrega */
  initProcessModal('form[method="post"]');

  initUpdateLoop('btn-update-getman', 'Getman', BASE, TOTAL_ROWS);

  document.addEventListener('DOMContentLoaded', function () {
    initTable('tbody-relacao', 'info-relacao', 'nav-relacao',   'filter-bar-relacao',  true);
    initTable('tbody-sistema', 'info-sistema', 'nav-sistema',   'filter-bar-sistema',  true);
    initTable('tbody-planilha','info-planilha', 'nav-planilha', 'filter-bar-planilha', true);
  });

}());
</script>
