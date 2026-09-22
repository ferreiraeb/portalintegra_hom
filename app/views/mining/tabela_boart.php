<?php
/**
 * views/mining/tabela_boart.php
 * Boart LongYear (Marca 152)
 *
 * Requer variáveis:
 * $erro, $rows, $soSistema, $soPlanilha, $cot, $marcaNome, $tipoTabela
 * Flash: $_SESSION['boart_flash']
 */
require_once __DIR__ . '/../components/_tabela_buttons.php';

$fmtPerc = fn($v) => ($v === null || $v === '') ? '' : number_format((float)$v * 100, 2, ',', '.') . '%';
$fmt2    = fn($v) => ($v === null || $v === '') ? '' : number_format((float)$v, 2, ',', '.');
$fmt4    = fn($v) => ($v === null || $v === '') ? '' : number_format((float)$v, 4, ',', '.');

$tipos = ['Ferramentais', 'Diamantados', 'Rock Tools', 'Spare Parts'];

$temResultado = !empty($rows);
$temSysOnly   = !empty($soSistema);
$temXlsxOnly  = !empty($soPlanilha);

$flash = $_SESSION['boart_flash'] ?? null;
if ($flash) unset($_SESSION['boart_flash']);
?>

<section class="content pt-3">
  <div class="container-fluid">
    <div class="card card-outline card-primary">

      <!-- ── Card header ───────────────────────────────────────────────── -->
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Mining — Tabela <?= e($marcaNome ?? 'Boart LongYear') ?></h3>
        <span class="text-muted small">Marca: <strong><?= e($marcaNome ?? 'Boart LongYear') ?></strong></span>
      </div>

      <div class="card-body">

        <?php include __DIR__ . '/../components/_erro_alert.php'; ?>

        <?php if ($flash): ?>
          <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle mr-1"></i>
            <?= e($flash) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <!-- ── Formulário de processamento ──────────────────────────────── -->
        <form method="post" action="<?= base_url('mining/tabela-boart') ?>" enctype="multipart/form-data" class="mb-3">
          <?php csrf_field(); ?>

          <!-- Linha 1: Upload do arquivo -->
          <div class="form-row">
            <div class="form-group col-md-8">
              <label for="arquivo" class="mb-0">Arquivo (.xlsx) <span class="text-danger">*</span></label>
              <div class="custom-file">
                <input type="file" class="custom-file-input" id="arquivo" name="arquivo"
                       accept=".xlsx,.xls" required>
                <label class="custom-file-label" for="arquivo" data-browse="Procurar">Escolher arquivo...</label>
              </div>
              <small class="form-text text-muted">
                Planilha de preço Boart no formato Excel. Escolher o
                <a href="#tipo_tabela" id="link-tipo" class="text-danger font-weight-bold" style="cursor:pointer;">tipo da tabela</a>.
              </small>
            </div>
          </div>

          <!-- Linha 2: Cotação + Markup + Fator + Tipo -->
          <div class="form-row">
            <div class="form-group col-md-2">
              <label for="cotacao" class="mb-0">Cotação do Dólar</label>
              <input type="text" name="cotacao" id="cotacao" inputmode="decimal"
                     class="form-control form-control-sm"
                     value="<?= e((string)($cot ?? '5.40')) ?>">
              <small class="form-text text-muted">Ex.: 5.40</small>
            </div>

            <div class="form-group col-md-2">
              <label for="markup" class="mb-0">Markup</label>
              <input type="text" name="markup" id="markup"
                     class="form-control form-control-sm"
                     value="<?= e($uiMk ?? '30%') ?>">
              <small class="form-text text-muted">Ex.: 30%</small>
            </div>

            <div class="form-group col-md-2">
              <label for="fator" class="mb-0">Fator de Importação</label>
              <input type="text" name="fator" id="fator"
                     class="form-control form-control-sm"
                     value="<?= e($uiFi ?? '35%') ?>">
              <small class="form-text text-muted">Ex.: 35%</small>
            </div>

            <div class="form-group col-md-3">
              <label for="tipo_tabela" class="mb-0">Tipo da Tabela <span class="text-danger">*</span></label>
              <select name="tipo_tabela" id="tipo_tabela" class="form-control form-control-sm" required>
                <?php foreach ($tipos as $opt): ?>
                  <option value="<?= e($opt) ?>" <?= (isset($tipoTabela) && $tipoTabela === $opt) ? 'selected' : '' ?>>
                    <?= e($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="form-text text-muted">Ferramentais, Diamantados, Rock Tools ou Spare Parts.</small>
            </div>
          </div>

          <!-- Linha 3: Botão Processar -->
          <div class="form-row">
            <div class="form-group col-md-4">
              <?= tabela_btn_processar() ?>
            </div>
          </div>

          <!-- Dicas rápidas por tipo -->
          <div class="alert alert-secondary py-2" id="mapeamento-tipos" style="display:none;">
            <strong>Mapeamento por tipo:</strong>
            <ul class="mb-0 pl-3">
              <li><em>Ferramentais</em>: Header na <strong>2ª</strong> linha. <code>PN</code> → <code>ProdutoReferencia</code>; <strong>PREÇO UNITÁRIO</strong> → Valor.</li>
              <li><em>Diamantados (Coroas)</em>: Header na <strong>3ª</strong> linha. <code>Etiquetas de fila</code> → <code>ProdutoReferencia</code>; <strong>PREÇO UNITÁRIO</strong> → Valor.</li>
              <li><em>Rock Tools</em>: Header na <strong>3ª</strong> linha. <code>Item Number</code> → <code>ProdutoReferencia</code>; <strong>Valence Price List</strong> → Valor.</li>
              <li><em>Spare Parts</em>: Header na <strong>1ª</strong> linha. <code>PN</code> → <code>ProdutoReferencia</code>; <strong>Custo Boart USD UNITÁRIO</strong> → Valor.</li>
            </ul>
          </div>
        </form>

        <script>
        (function () {
            var defaults = {
                'Ferramentais': { markup: '30%', fator: '35%' },
                'Diamantados':  { markup: '15%', fator: '35%' },
                'Rock Tools':   { markup: '20%', fator: '35%' },
                'Spare Parts':  { markup: '40%', fator: '70%' }
            };

            function applyDefaults() {
                var tipoSel  = document.getElementById('tipo_tabela');
                var markupIn = document.getElementById('markup');
                var fatorIn  = document.getElementById('fator');

                if (!tipoSel || !markupIn || !fatorIn) return;

                var def = defaults[tipoSel.value];
                if (!def) return;

                markupIn.value = def.markup;
                fatorIn.value  = def.fator;
            }

            function showMapeamento() {
                var el = document.getElementById('mapeamento-tipos');
                if (el) el.style.display = '';
            }

            document.addEventListener('DOMContentLoaded', function () {
                applyDefaults();

                var tipoSel = document.getElementById('tipo_tabela');
                if (tipoSel) {
                    tipoSel.addEventListener('change', function () {
                        applyDefaults();
                        showMapeamento();
                    });
                    tipoSel.addEventListener('click', function () {
                        var el = document.getElementById('mapeamento-tipos');
                        if (!el) return;
                        // toggle on repeated click, show on first
                        if (el.style.display === 'none') {
                            el.style.display = '';
                        } else {
                            el.style.display = 'none';
                        }
                    });
                }

                var linkTipo = document.getElementById('link-tipo');
                if (linkTipo) {
                    linkTipo.addEventListener('click', function (e) {
                        e.preventDefault();
                        var el = document.getElementById('mapeamento-tipos');
                        if (!el) return;
                        if (el.style.display === 'none') {
                            el.style.display = '';
                        } else {
                            el.style.display = 'none';
                        }
                        if (tipoSel) tipoSel.focus();
                    });
                }
            });
        })();
        </script>

        <!-- ── Abas de resultado ─────────────────────────────────────────── -->
        <ul class="nav nav-tabs" id="boartTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="tab-relacao" data-toggle="tab" href="#relacao" role="tab">
              Relação de Itens <?= $temResultado ? '(' . number_format(count($rows), 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-sysonly" data-toggle="tab" href="#sysonly" role="tab">
              Itens Apenas DealerNet <?= $temSysOnly ? '(' . number_format(count($soSistema), 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-xlsxonly" data-toggle="tab" href="#xlsxonly" role="tab">
              Itens Apenas Planilha <?= $temXlsxOnly ? '(' . number_format(count($soPlanilha), 0, ',', '.') . ')' : '' ?>
            </a>
          </li>
        </ul>

        <div class="tab-content pt-3" id="boartTabsContent">

          <!-- RELAÇÃO -->
          <div class="tab-pane fade show active" id="relacao" role="tabpanel">
            <?php if (!$temResultado): ?>
              <div class="alert alert-secondary">Nenhum item processado ainda.</div>
            <?php else: ?>
              <!-- Filtros -->
              <div id="filter-bar-relacao" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Código</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Código" placeholder="contém...">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Referência</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Referência" placeholder="contém...">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Descrição" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">NCM</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="NCM" placeholder="contém...">
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
                  <?= tabela_btn_export(base_url('mining/tabela-boart?export=1')) ?>
                  <?= tabela_btn_atualizar('btn-update-boart', count($rows)) ?>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <th>Marca</th>
                      <th>Empresa</th>
                      <th>Código</th>
                      <th>Referência</th>
                      <th>Descrição</th>
                      <th>NCM</th>
                      <th>NCM Id.</th>
                      <th class="text-right">ValorPlanilha (US$)</th>
                      <th class="text-right">Markup</th>
                      <th class="text-right">Fator</th>
                      <th class="text-right">Cotação</th>
                      <th>Tipo</th>
                      <th class="text-right">Preço Público</th>
                      <th class="text-right">Preço Sugerido</th>
                      <th class="text-right">Preço Garantia</th>
                      <th class="text-right">Preço Reposição</th>
                    </tr>
                  </thead>
                  <tbody id="tbody-relacao">
                    <?php foreach ($rows as $r): ?>
                      <tr>
                        <td><?= e($r['Marca'] ?? '') ?></td>
                        <td><?= e($r['Empresa'] ?? '') ?></td>
                        <td><?= e($r['ProdutoCodigo'] ?? '') ?></td>
                        <td><?= e($r['ProdutoReferencia'] ?? '') ?></td>
                        <td><?= e($r['ProdutoDescricao'] ?? '') ?></td>
                        <td><?= e($r['NCMCodigo'] ?? '') ?></td>
                        <td><?= e($r['NCMIdentificador'] ?? '') ?></td>
                        <td class="text-right"><?= $fmt4($r['ValorPlanilha(US$)'] ?? 0) ?></td>
                        <td class="text-right"><?= $fmtPerc($r['Markup'] ?? 0) ?></td>
                        <td class="text-right"><?= $fmtPerc($r['FatorImportacao'] ?? 0) ?></td>
                        <td class="text-right"><?= $fmt4($r['CotacaoDolar'] ?? 0) ?></td>
                        <td><?= e($r['Tipo'] ?? '') ?></td>
                        <td class="text-right"><?= $fmt2($r['PrecoPublico'] ?? 0) ?></td>
                        <td class="text-right"><?= $fmt2($r['PrecoSugerido'] ?? 0) ?></td>
                        <td class="text-right"><?= $fmt2($r['PrecoGarantia'] ?? 0) ?></td>
                        <td class="text-right"><?= $fmt2($r['PrecoReposicao'] ?? 0) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <nav aria-label="Paginação Relação" class="mt-2" id="nav-relacao"></nav>
            <?php endif; ?>
          </div>

          <!-- APENAS DEALERNET -->
          <div class="tab-pane fade" id="sysonly" role="tabpanel">
            <small class="form-text text-muted mb-2 d-block">
              Itens presentes no DealerNet mas não encontrados na planilha.
            </small>
            <?php if (!$temSysOnly): ?>
              <div class="alert alert-secondary">Sem divergências do lado do DealerNet.</div>
            <?php else: ?>
              <!-- Filtros -->
              <div id="filter-bar-sistema" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Código</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Código" placeholder="contém...">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Referência</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Referência" placeholder="contém...">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Descrição" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">NCM</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="NCM" placeholder="contém...">
                </div>
                <div class="col-6 col-md-1">
                  <label class="form-label mb-0 small font-weight-bold">NCM Id.</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="NCM Id." placeholder="contém...">
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
                <?= tabela_btn_export(base_url('mining/tabela-boart?export=1&tab=sys')) ?>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <th>Marca</th>
                      <th>Empresa</th>
                      <th>Código</th>
                      <th>Referência</th>
                      <th>Descrição</th>
                      <th>NCM</th>
                      <th>NCM Id.</th>
                    </tr>
                  </thead>
                  <tbody id="tbody-sistema">
                    <?php foreach ($soSistema as $p): ?>
                      <tr>
                        <td><?= e($p['Marca'] ?? '') ?></td>
                        <td><?= e($p['Empresa'] ?? '') ?></td>
                        <td><?= e($p['ProdutoCodigo'] ?? '') ?></td>
                        <td><?= e($p['ProdutoReferencia'] ?? '') ?></td>
                        <td><?= e($p['ProdutoDescricao'] ?? '') ?></td>
                        <td><?= e($p['NCMCodigo'] ?? '') ?></td>
                        <td><?= e($p['NCMIdentificador'] ?? '') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <nav aria-label="Paginação DealerNet" class="mt-2" id="nav-sistema"></nav>
            <?php endif; ?>
          </div>

          <!-- APENAS PLANILHA -->
          <div class="tab-pane fade" id="xlsxonly" role="tabpanel">
            <small class="form-text text-muted mb-2 d-block">
              Itens encontrados na planilha mas não presentes no DealerNet.
            </small>
            <?php if (!$temXlsxOnly): ?>
              <div class="alert alert-secondary">Sem divergências do lado da planilha.</div>
            <?php else: ?>
              <!-- Filtros -->
              <div id="filter-bar-planilha" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Ref. Planilha</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Ref. Planilha" placeholder="contém...">
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label mb-0 small font-weight-bold">Descrição</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Descrição" placeholder="contém...">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label mb-0 small font-weight-bold">Ref. Normalizada</label>
                  <input type="text" class="form-control form-control-sm" data-col-name="Ref. Normalizada" placeholder="contém...">
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
                <?= tabela_btn_export(base_url('mining/tabela-boart?export=1&tab=plan')) ?>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <th>Ref. Planilha</th>
                      <th>Descrição</th>
                      <th class="text-right">ValorPlanilha (US$)</th>
                      <th>Ref. Normalizada</th>
                    </tr>
                  </thead>
                  <tbody id="tbody-planilha">
                    <?php foreach ($soPlanilha as $x): ?>
                      <tr>
                        <td><?= e($x['ProdutoReferencia'] ?? '') ?></td>
                        <td><?= e($x['PartDescription'] ?? '') ?></td>
                        <td class="text-right"><?= $fmt4($x['ValorPlanilha(US$)'] ?? 0) ?></td>
                        <td><?= e($x['RefNormalizada'] ?? '') ?></td>
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

  var BASE       = <?= json_encode(base_url('mining/tabela-boart')) ?>;
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

  initUpdateLoop('btn-update-boart', 'Boart LongYear', BASE, TOTAL_ROWS);

  document.addEventListener('DOMContentLoaded', function () {
    initTable('tbody-relacao', 'info-relacao', 'nav-relacao',   'filter-bar-relacao',  true);
    initTable('tbody-sistema', 'info-sistema', 'nav-sistema',   'filter-bar-sistema',  true);
    initTable('tbody-planilha','info-planilha', 'nav-planilha', 'filter-bar-planilha', true);
  });

}());
</script>
