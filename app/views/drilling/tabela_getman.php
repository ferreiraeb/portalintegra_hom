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
                <form method="get" class="mb-0">
				  <input type="hidden" name="update" value="1">
				  <button class="btn btn-success btn-sm">Atualizar Valores</button>
				</form>
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