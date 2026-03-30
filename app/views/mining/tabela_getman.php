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

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <?= e($erro) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <!-- ── Formulário de processamento ──────────────────────────────── -->
        <form action="<?= $base ?>" method="post" enctype="multipart/form-data" class="mb-3">
          <?php csrf_field(); ?>

          <div class="form-row">
            <div class="form-group col-md-8">
              <label class="mb-0">Arquivo (.xlsx) <span class="text-danger">*</span></label>
              <div class="custom-file">
                <input type="file" class="custom-file-input" id="arquivo" name="arquivo" accept=".xlsx" required>
                <label class="custom-file-label" for="arquivo">Escolher arquivo...</label>
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
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted">
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
                  <tbody>
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
              <div class="mb-2 text-muted">Total: <strong><?= number_format(count($soSistema), 0, ',', '.') ?></strong></div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($soSistema[0]) as $c): ?>
                        <th><?= e($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
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
              <div class="mb-2 text-muted">Total: <strong><?= number_format(count($soPlanilha), 0, ',', '.') ?></strong></div>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($soPlanilha[0]) as $c): ?>
                        <th><?= e($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
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

  var BASE       = <?= json_encode(base_url('mining/tabela-getman')) ?>;
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
  var btnUpdate = document.getElementById('btn-update-getman');
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
        '<i class="fas fa-database mr-2 text-primary"></i> Atualizar Valores — Getman',
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
