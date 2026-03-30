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

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <?= e($erro) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

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
                <label class="custom-file-label" for="arquivo">Escolher arquivo...</label>
              </div>
              <small class="form-text text-muted">Planilha de preço Boart no formato Excel.</small>
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
          <div class="alert alert-secondary py-2">
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

            document.addEventListener('DOMContentLoaded', function () {
                applyDefaults();
                var tipoSel = document.getElementById('tipo_tabela');
                if (tipoSel) {
                    tipoSel.addEventListener('change', applyDefaults);
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
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted">
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
                  <tbody>
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
              <div class="mb-2 text-muted">Total: <strong><?= number_format(count($soSistema), 0, ',', '.') ?></strong></div>
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
                  <tbody>
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
              <div class="mb-2 text-muted">Total: <strong><?= number_format(count($soPlanilha), 0, ',', '.') ?></strong></div>
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
                  <tbody>
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

(function () {
  'use strict';

  var BASE       = <?= json_encode(base_url('mining/tabela-boart')) ?>;
  var TOTAL_ROWS = <?= (int)count($rows ?? []) ?>;
  var isUpdating = false;

  /* ── Processar: mostra modal bloqueante enquanto o POST carrega ── */
  var form = document.querySelector('form[method="post"]');
  if (form) {
    form.addEventListener('submit', function () {
      PortalModal.show(
        '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i> Processando planilha&hellip;',
        '<div class="text-muted mb-2">Lendo arquivo XLSX e cruzando com DealerNet.</div>' +
        '<div class="text-muted small">Aguarde — isso pode levar alguns segundos.</div>'
      );
    });
  }

  /* ── Atualizar Valores: loop AJAX com PortalModal ── */
  var btnUpdate = document.getElementById('btn-update-boart');
  if (btnUpdate) {
    btnUpdate.addEventListener('click', async function () {
      if (isUpdating) return;
      if (TOTAL_ROWS === 0) {
        alert('Nenhum item de relação para atualizar. Processe uma planilha primeiro.');
        return;
      }
      if (!confirm(
        'Atualizar preços de ' + TOTAL_ROWS.toLocaleString('pt-BR') + ' itens no DealerNet?\n\n' +
        'Não navegue durante o processo.'
      )) return;

      isUpdating = true;
      window.onbeforeunload = function () {
        return 'Atualizacao em andamento - sair ira interromper o processo.';
      };

      PortalModal.show(
        '<i class="fas fa-database mr-2 text-primary"></i> Atualizar Valores — Boart LongYear',
        PortalModal.progressHtml(0, 'Iniciando...')
      );

      var offset = 0, auditOp = '', procOk = 0, procFail = 0, errs = [];
      var LIMIT  = 50;

      try {
        while (true) {
          var qs = 'action=update_chunk'
                 + '&offset='   + offset
                 + '&limit='    + LIMIT
                 + '&audit_op=' + encodeURIComponent(auditOp);

          var resp = await fetch(BASE + '?' + qs, { cache: 'no-store' });
          if (!resp.ok) {
            var errTxt = '';
            try { errTxt = await resp.text(); } catch (_) {}
            throw new Error('HTTP ' + resp.status + (errTxt ? ': ' + errTxt : ''));
          }
          var j = await resp.json();
          if (!j.ok) throw new Error(j.msg || 'Erro desconhecido.');

          offset    = j.done;
          auditOp   = j.audit_op  || auditOp;
          procOk   += j.proc_ok   || 0;
          procFail += j.proc_fail || 0;
          if (j.errors && j.errors.length) {
            errs = errs.concat(j.errors);
            if (errs.length > 5) errs = errs.slice(0, 5);
          }

          var pct = j.total > 0 ? Math.round(offset / j.total * 100) : 0;
          PortalModal.update(PortalModal.progressHtml(
            pct,
            offset.toLocaleString('pt-BR') + ' / ' + (j.total || TOTAL_ROWS).toLocaleString('pt-BR') + ' itens'
          ));

          if (j.finished) break;
        }

        var alertType = procFail > 0 ? 'warning' : 'success';
        var doneTitle = procFail > 0 ? 'Concluído com falhas' : 'Concluído';
        var errDetail = '';
        if (errs.length) {
          errDetail = '<details class="mt-2">'
            + '<summary class="small text-muted">Detalhes (' + errs.length + ' erro(s))</summary>'
            + '<pre class="small mt-1" style="max-height:120px;overflow:auto;white-space:pre-wrap;">'
            + errs.join('\n') + '</pre></details>';
        }
        var doneBody =
          '<div class="alert alert-' + alertType + ' mb-2">'
          + '<strong>' + doneTitle + '</strong><br>'
          + procOk.toLocaleString('pt-BR') + ' preco(s) atualizado(s)'
          + (procFail > 0 ? ', <strong>' + procFail + ' falha(s)</strong>' : '') + '.'
          + (auditOp ? '<br><small class="text-white">ID auditoria: ' + auditOp + '</small>' : '')
          + '</div>'
          + errDetail;

        PortalModal.setTitle('<i class="fas fa-check-circle mr-2 text-success"></i> Atualizacao concluida');
        PortalModal.update(doneBody);

      } catch (err) {
        PortalModal.setTitle('<i class="fas fa-exclamation-circle mr-2 text-danger"></i> Erro na atualizacao');
        PortalModal.update(
          '<div class="alert alert-danger mb-0">'
          + '<strong>Erro:</strong> ' + String(err.message || err)
          + '</div>'
        );
      } finally {
        isUpdating = false;
        window.onbeforeunload = null;
      }
    });
  }

})();
</script>




