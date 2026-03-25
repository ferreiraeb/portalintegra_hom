<?php
// Espera: $erro, $rows, $soSistema, $soPlanilha, $cot, $uiMkLe,$uiFiLe,$uiMkGt,$uiFiGt, $marcaNome
$base = base_url('drilling/tabela-getman');
function br_money($v){ return is_numeric($v) ? number_format((float)$v,2,',','.') : $v; }
function br_pct($f){ return number_format(((float)$f)*100,2,',','.').'%'; }
?>
<section class="content pt-3">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Drilling — Tabela Getman</h3>
        <span class="text-muted small">Marca: <strong><?= htmlspecialchars($marcaNome ?? 'Getman') ?></strong></span>
      </div>


		<?php
		$flash = $_SESSION['getman_flash'] ?? '';
		$alertClass = (stripos($flash, 'Falha') !== false || stripos($flash, 'Sem permissão') !== false) ? 'alert-danger' : 'alert-info';
		?>
		<?php if ($flash): ?>
		  <div class="alert <?= $alertClass ?> alert-dismissible fade show" role="alert">
			<?= nl2br(htmlspecialchars($flash)) ?>
			<button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
			  <span aria-hidden="true">&times;</span>
			</button>
		  </div>
		  <?php unset($_SESSION['getman_flash']); ?>
		<?php endif; ?>




      <div class="card-body">
        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <!-- Form de processamento -->
        <form action="<?= $base ?>" method="post" enctype="multipart/form-data" class="mb-3">
          <?php csrf_field(); ?>
          <div class="form-row">
            <div class="form-group col-md-8">
              <label>Clique no botão abaixo e selecione a planilha GETMAN (XLSX)</label>
              <input type="file" name="arquivo" class="form-control-file" accept=".xlsx" required>
              <small class="form-text text-muted">
                A planilha anexa deve possuir o seguinte cabeçalho: PartNum / PartDescription / Coluna de preço (ex.: “2026 GLP25”).
              </small>
            </div>
		  </div>
		  <div class="form-row">
            <div class="form-group col-md-2">
              <label>Cotação do Dólar</label>
              <input type="number" name="cotacao" step="0.0001" class="form-control" value="<?= htmlspecialchars((string)$cot) ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label>Markup ≤ US$ 10.000</label>
              <input type="text" name="mk_le" class="form-control" value="<?= htmlspecialchars($uiMkLe) ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label>Fator ≤ US$ 10.000</label>
              <input type="text" name="fi_le" class="form-control" value="<?= htmlspecialchars($uiFiLe) ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label>Markup &gt; US$ 10.000</label>
              <input type="text" name="mk_gt" class="form-control" value="<?= htmlspecialchars($uiMkGt) ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label>Fator &gt; US$ 10.000</label>
              <input type="text" name="fi_gt" class="form-control" value="<?= htmlspecialchars($uiFiGt) ?>" required>
            </div>
          </div>

          <!-- Marca ID oculto (fixo) -->
          <input type="hidden" name="marca_id" value="236">

          <button class="btn btn-primary">Processar</button>
          <?php if (!empty($rows)): ?>
            <a href="<?= $base ?>?export=1" class="btn btn-info ml-2">Exportar CSV</a>
          <?php endif; ?>
        </form>

        <!-- Abas -->
        <ul class="nav nav-tabs" id="getmanTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="tab-relacao" data-toggle="tab" href="#pane-relacao" role="tab">Relação de Itens</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-sistema" data-toggle="tab" href="#pane-sistema" role="tab">Itens Apenas DealerNet</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-planilha" data-toggle="tab" href="#pane-planilha" role="tab">Itens Apenas Planilha</a>
          </li>
        </ul>

        <div class="tab-content pt-3" id="getmanTabsContent">
          <!-- RELAÇÃO -->
          <div class="tab-pane fade show active" id="pane-relacao" role="tabpanel">
            <?php if (!empty($rows)): ?>
              <div class="d-flex align-items-center mb-2">
                <span class="text-muted mr-3">Total de linhas : <strong><?= number_format(count($rows),0,',','.') ?></strong></span>
                <button id="btn-update-getman"
                        type="button"
                        class="btn btn-success btn-sm"
                        data-total="<?= count($rows) ?>">
                  <i class="fas fa-database mr-1"></i> Atualizar Valores
                </button>
                <small class="text-muted ml-2">Registra auditoria (Portal_Integra) e executa a procedure por item (DealerNet).</small>
              </div>
            <?php else: ?>
              <div class="alert alert-warning">Nenhum registro processado.</div>
            <?php endif; ?>

            <?php if (!empty($rows)): ?>
              <div class="table-responsive">
                <table class="table table-hover table-sm">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($rows[0]) as $c): ?>
                        <th><?= htmlspecialchars($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r): ?>
                      <tr>
                        <?php foreach ($r as $k=>$v): ?>
                          <?php if (in_array($k,['ProdutoCodigo','ProdutoReferencia','NCMIdentificador','NCMCodigo'],true)): ?>
                            <td><?= htmlspecialchars((string)$v) ?></td>
                          <?php elseif (in_array($k,['Markup','FatorImportacao'],true)): ?>
                            <td class="text-right"><?= br_pct($v) ?></td>
                          <?php elseif (is_numeric($v)): ?>
                            <td class="text-right"><?= br_money($v) ?></td>
                          <?php else: ?>
                            <td><?= htmlspecialchars((string)$v) ?></td>
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
            <small class="form-text text-muted">Os itens desta lista não foram localizados na planilha anexa, porém estão presentes no DealerNet,</small>
			<div class="mb-2 text-muted">Total de linhas: <strong><?= number_format(count($soSistema),0,',','.') ?></strong></div>
            <?php if (empty($soSistema)): ?>
              <div class="alert alert-secondary mb-0">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover table-sm">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($soSistema[0]) as $c): ?>
                        <th><?= htmlspecialchars($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($soSistema as $r): ?>
                      <tr>
                        <?php foreach ($r as $k=>$v): ?>
                          <?php if (in_array($k,['ProdutoCodigo','ProdutoReferencia','NCMIdentificador','NCMCodigo'],true)): ?>
                            <td><?= htmlspecialchars((string)$v) ?></td>
                          <?php elseif (is_numeric($v)): ?>
                            <td class="text-right"><?= br_money($v) ?></td>
                          <?php else: ?>
                            <td><?= htmlspecialchars((string)$v) ?></td>
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
		    <small class="form-text text-muted">Os itens desta lista foram localizados na planilha anexa, porém não estão presentes no DealerNet,</small>
            <div class="mb-2 text-muted">Total de linhas: <strong><?= number_format(count($soPlanilha),0,',','.') ?></strong></div>
            <?php if (empty($soPlanilha)): ?>
              <div class="alert alert-secondary mb-0">Nenhum registro.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover table-sm">
                  <thead class="thead-light">
                    <tr>
                      <?php foreach (array_keys($soPlanilha[0]) as $c): ?>
                        <th><?= htmlspecialchars($c) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($soPlanilha as $r): ?>
                      <tr>
                        <?php foreach ($r as $k=>$v): ?>
                          <?php if (in_array($k,['ProdutoReferencia','PartDescription'],true)): ?>
                            <td><?= htmlspecialchars((string)$v) ?></td>
                          <?php elseif (is_numeric($v)): ?>
                            <td class="text-right"><?= br_money($v) ?></td>
                          <?php else: ?>
                            <td><?= htmlspecialchars((string)$v) ?></td>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>


    </div>
  </div>
</section>

<script>
(function () {
  'use strict';

  var BASE        = <?= json_encode(base_url('drilling/tabela-getman')) ?>;
  var TOTAL_ROWS  = <?= (int)count($rows ?? []) ?>;
  var isUpdating  = false;

  /* ── Processar: mostra modal bloqueante enquanto o POST carrega ── */
  var form = document.querySelector('form[method="post"]');
  if (form) {
    form.addEventListener('submit', function () {
      PortalModal.show(
        '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i> Processando planilha&hellip;',
        '<div class="text-muted mb-2">Lendo arquivo XLSX e cruzando com DealerNet.</div>' +
        '<div class="text-muted small">Aguarde — isso pode levar alguns segundos.</div>'
      );
      /* Deixa o form submeter normalmente; o modal some quando a nova página carrega. */
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
        '<i class="fas fa-database mr-2 text-success"></i> Atualizar Valores — Getman',
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

        /* Sucesso / sucesso-com-falhas */
        var alertType = procFail > 0 ? 'warning' : 'success';
        var doneTitle = procFail > 0 ? 'Concluido com falhas' : 'Concluido';
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
          + (auditOp ? '<br><small class="text-muted">ID auditoria: ' + auditOp + '</small>' : '')
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
