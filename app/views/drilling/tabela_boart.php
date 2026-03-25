<?php
/**
 * views/drilling/tabela_boart.php
 * Boart LongYear (Marca 152) — similar à Getman, com seletor de tipo e parsing específico.
 *
 * Requer variáveis:
 * - $erro, $rows, $soSistema, $soPlanilha, $cot, $marcaNome, $tipoTabela
 * E usa flash: $_SESSION['boart_flash']
 */

// Helpers locais de formatação (não conflitam com os globais):
$fmtPerc = function($v) {
    // $v já é fração (ex.: 0.30 → "30,00%")
    if ($v === null || $v === '') return '';
    return number_format((float)$v * 100, 2, ',', '.') . '%';
};
$fmt2 = function($v) {
    // 2 casas decimais (tabela de preços, BRL/USD finais)
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 2, ',', '.');
};
$fmt4 = function($v) {
    // 4 casas (valores de planilha em USD)
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 4, ',', '.');
};

// Opções de tipo
$tipos = ['Ferramentais','Diamantados','Rock Tools','Spare Parts'];

// Atalhos de estado
$temResultado = !empty($rows);
$temSysOnly   = !empty($soSistema);
$temXlsxOnly  = !empty($soPlanilha);

// Mensagem flash
$flash = $_SESSION['boart_flash'] ?? null;
if ($flash) unset($_SESSION['boart_flash']);
?>

<section class="content pt-3">
  <div class="container-fluid">

    <div class="card card-outline card-primary">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Tabela <?= e($marcaNome ?? 'Boart LongYear') ?></h3>
        <div class="card-tools">
          <!-- Ações rápidas (habilitadas só com resultado) -->
          <a href="<?= base_url('drilling/tabela-boart?export=1') ?>"
             class="btn btn-sm btn-outline-secondary <?= $temResultado ? '' : 'disabled' ?>"
             title="Exportar CSV"
             <?= $temResultado ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
            <i class="fas fa-file-export"></i> Exportar CSV
          </a>
          <a href="<?= base_url('drilling/tabela-boart?update=1') ?>"
             class="btn btn-sm btn-primary ml-1 <?= $temResultado ? '' : 'disabled' ?>"
             title="Atualizar valores no DealerNet"
             onclick="return <?= $temResultado ? 'confirm(\'Confirmar atualização de valores no DealerNet?\')' : 'false' ?>;"
             <?= $temResultado ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
            <i class="fas fa-database"></i> Atualizar Valores
          </a>
        </div>
      </div>

      <div class="card-body">

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?= e($erro) ?>
          </div>
        <?php endif; ?>

        <?php if ($flash): ?>
          <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <?= e($flash) ?>
          </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form method="post" action="<?= base_url('drilling/tabela-boart') ?>" enctype="multipart/form-data" class="mb-3">
          <?php csrf_field(); ?>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label for="tipo_tabela" class="mb-0">Tipo da Tabela <span class="text-danger">*</span></label>
              <select name="tipo_tabela" id="tipo_tabela" class="form-control form-control-sm" required>
                <?php foreach ($tipos as $opt): ?>
                  <option value="<?= e($opt) ?>" <?= (isset($tipoTabela) && $tipoTabela === $opt) ? 'selected' : '' ?>>
                    <?= e($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="form-text text-muted">
                Ferramentais, Diamantados (Coroas), Rock Tools ou Spare Parts.
              </small>
            </div>

            <div class="form-group col-md-2">
              <label for="cotacao" class="mb-0">Cotação do Dólar</label>
              <input type="text" name="cotacao" id="cotacao" inputmode="decimal"
                     class="form-control form-control-sm"
                     value="<?= e((string)($cot ?? '5.40')) ?>">
              <small class="form-text text-muted">Ex.: 5.40</small>
            </div>

            <div class="form-group col-md-4">
              <label for="arquivo" class="mb-0">Arquivo (.xlsx) <span class="text-danger">*</span></label>
              <div class="custom-file">
                <input type="file"
                       class="custom-file-input"
                       id="arquivo"
                       name="arquivo"
                       accept=".xlsx,.xls"
                       required>
                <label class="custom-file-label" for="arquivo">Escolher arquivo...</label>
              </div>
              <small class="form-text text-muted">Planilha de preço Boart no formato Excel.</small>
            </div>

            <div class="form-group col-md-3 d-flex align-items-end">
              <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-sync"></i> Processar
              </button>
            </div>
          </div>

          <!-- Dicas rápidas por tipo -->
          <div class="alert alert-secondary py-2">
            <strong>Mapeamento por tipo:</strong>
            <ul class="mb-0 pl-3">
              <li><em>Ferramentais</em>: Header na <strong>2ª</strong> linha. PN / Item Description → <code>ProdutoReferencia</code>; <strong>PREÇO UNITÁRIO</strong> → Valor.</li>
              <li><em>Diamantados (Coroas)</em>: Header na <strong>3ª</strong> linha. Etiquetas de fila / Item Description → <code>ProdutoReferencia</code>; <strong>PREÇO UNITÁRIO</strong> → Valor.</li>
              <li><em>Rock Tools</em>: Header na <strong>3ª</strong> linha. Item Number / Product Description → <code>ProdutoReferencia</code>; <strong>Valence Price List</strong> → Valor.</li>
              <li><em>Spare Parts</em>: Header na <strong>1ª</strong> linha. PN / Item Description → <code>ProdutoReferencia</code>; <strong>Custo Boart USD UNITÁRIO</strong> → Valor.</li>
            </ul>
          </div>
        </form>

        <!-- Abas de resultado -->
        <ul class="nav nav-tabs" id="boartTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="tab-relacao" data-toggle="tab" href="#relacao" role="tab">
              Relação de Itens <?= $temResultado ? '(' . count($rows) . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-sysonly" data-toggle="tab" href="#sysonly" role="tab">
              Itens Apenas DealerNet <?= $temSysOnly ? '(' . count($soSistema) . ')' : '' ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-xlsxonly" data-toggle="tab" href="#xlsxonly" role="tab">
              Itens Apenas Planilha <?= $temXlsxOnly ? '(' . count($soPlanilha) . ')' : '' ?>
            </a>
          </li>
        </ul>

        <div class="tab-content pt-3" id="boartTabsContent">

          <!-- RELAÇÃO -->
          <div class="tab-pane fade show active" id="relacao" role="tabpanel">
            <?php if (!$temResultado): ?>
              <div class="text-muted">Nenhum item processado ainda.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
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
            <?php if (!$temSysOnly): ?>
              <div class="text-muted">Sem divergências do lado do DealerNet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-striped">
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
            <?php if (!$temXlsxOnly): ?>
              <div class="text-muted">Sem divergências do lado da planilha.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-striped">
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

  </div>
</section>

<script>
// Atualiza label do input de arquivo
document.addEventListener('DOMContentLoaded', function() {
  var input = document.getElementById('arquivo');
  if (input) {
    input.addEventListener('change', function() {
      var lbl = document.querySelector('label.custom-file-label[for="arquivo"]');
      if (lbl && input.files && input.files.length > 0) {
        lbl.textContent = input.files[0].name;
      }
    });
  }
});
</script>