<?php
$modo   = $modo ?? 'criar';
$isEdit = $modo === 'editar';
$i      = $item ?? [];
$erro   = $erro ?? null;
$tipoFixo = $tipoFixo ?? null;
$listaUrl = $listaUrl ?? null;
$titulo = $isEdit ? 'Editar Item' : 'Novo Item';
if (!$isEdit && $tipoFixo) {
    $titulo = 'Novo ' . $tipoFixo->nome;
}
$action = $isEdit
    ? base_url('itens/'.(int)$i['id'].'/editar')
    : base_url('itens/criar');
$voltarUrl = $listaUrl
    ? base_url($listaUrl)
    : ($isEdit && !empty($i['tipo_item_id']) ? base_url('itens/tipo/'.(int)$i['tipo_item_id']) : null);

// Para comportamento JS no carregamento da edição
$editTipoId        = (int)($tipoFixo?->id ?? $i['tipo_item_id'] ?? 0);
$editTabela        = e($tipoFixo?->tabela_detalhe ?? $i['tabela_detalhe'] ?? '');
$editIsDeterminado = (int)($tipoFixo?->is_determinado ?? $i['is_determinado'] ?? 0);

function fv(array $item, string $key, $default = ''): string {
    return e($item[$key] ?? $default);
}

$v = fn(string $key, $def = '') => e($i[$key] ?? $def);
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:800px;">
    <div class="card card-primary">
      <div class="card-header">
        <h3 class="card-title"><?= e($titulo) ?></h3>
      </div>

      <form method="post" action="<?= $action ?>">
        <?php csrf_field(); ?>
        <div class="card-body">
          <?php if ($erro): ?>
            <div class="alert alert-danger"><?= e($erro) ?></div>
          <?php endif; ?>

          <!-- ── Campos comuns ── -->
          <div class="form-group">
            <label>Tipo de Item <span class="text-danger">*</span></label>
            <?php if ($tipoFixo || $isEdit): ?>
              <input type="hidden" name="tipo_item_id" value="<?= $editTipoId ?>">
              <input type="text" class="form-control" readonly value="<?= e($tipoFixo?->nome ?? '—') ?>">
            <?php else: ?>
            <select id="selectTipo" name="tipo_item_id" class="form-control" required
                    data-is-determinado="<?= $editIsDeterminado ?>"
                    data-tabela="<?= $editTabela ?>">
              <option value="">Selecione…</option>
              <?php foreach ($tiposPorCategoria as $nomeCat => $tipos): ?>
                <optgroup label="<?= e($nomeCat) ?>">
                  <?php foreach ($tipos as $t): 
                      $isMovel = preg_match('/celular|tablet/i', $t->nome) ? '1' : '0';
                  ?>
                    <option value="<?= (int)$t->id ?>"
                            data-tabela="<?= e($t->tabela_detalhe ?? '') ?>"
                            data-determinado="<?= (int)$t->is_determinado ?>"
                            data-is-movel="<?= $isMovel ?>"
                      <?= $editTipoId === $t->id ? 'selected' : '' ?>>
                      <?= e($t->nome) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label>Descrição <span class="text-danger">*</span></label>
            <input name="descricao" class="form-control" required value="<?= $v('descricao') ?>">
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Status <span class="text-danger">*</span></label>
                <?php $statusAtualForm = $i['status'] ?? 'disponivel'; ?>
                <?php if ($statusAtualForm === 'em_uso'): ?>
                  <input type="hidden" name="status" value="em_uso">
                  <select class="form-control" disabled>
                    <option selected>Em uso</option>
                  </select>
                  <small class="form-text text-muted">Controlado pelos empréstimos.</small>
                <?php else: ?>
                <select name="status" class="form-control" required>
                  <?php foreach ([
                      'disponivel' => 'Disponível',
                      'reservado'  => 'Reservado',
                      'bloqueado'  => 'Bloqueado',
                      'baixado'    => 'Baixado',
                      'extraviado' => 'Extraviado',
                  ] as $sv => $sl): ?>
                    <option value="<?= $sv ?>" <?= $statusAtualForm === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                  <?php endforeach; ?>
                </select>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Quantidade total</label>
                <input id="qtdTotal" name="quantidade_total" type="number" min="1"
                       class="form-control" value="<?= max(1,(int)($i['quantidade_total']??1)) ?>">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Observação</label>
            <textarea name="observacao" class="form-control" rows="2"><?= $v('observacao') ?></textarea>
          </div>

          <hr>

          <!-- ── Blocos de detalhe ── -->
          <div id="blocos-detalhe">

            <!-- Linha Telefônica (item_linha_telefonica) -->
            <div class="detalhe-bloco" data-tabela="item_linha_telefonica" style="display:none">
              <h6 class="text-muted mb-3">Linha Telefônica</h6>
              <div class="row">
                <div class="col-md-6 form-group">
                  <label>Número da linha</label>
                  <input name="numero_linha" class="form-control" placeholder="Ex: 31999998888" value="<?= $v('numero_linha') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>ICC-ID / Número do chip</label>
                  <input name="numero_chip" class="form-control" value="<?= $v('numero_chip') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Número anterior (portabilidade)</label>
                  <input name="numero_anterior" class="form-control" value="<?= $v('numero_anterior') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Operadora</label>
                  <input name="operadora" class="form-control" placeholder="Ex: Claro, Vivo, TIM" value="<?= $v('operadora') ?>">
                </div>
                <div class="col-md-4 form-group">
                  <label>Tipo de chip</label>
                  <select name="tipo_chip" class="form-control">
                    <option value="">— selecione —</option>
                    <?php foreach (['SIM', 'eSIM'] as $tc): ?>
                      <option value="<?= $tc ?>" <?= ($i['tipo_chip'] ?? '') === $tc ? 'selected' : '' ?>><?= $tc ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4 form-group">
                  <label>Status da linha</label>
                  <select name="status_linha" class="form-control">
                    <?php foreach (['ativo' => 'Ativo', 'inativo' => 'Inativo', 'cancelado' => 'Cancelado'] as $sv => $sl): ?>
                      <option value="<?= $sv ?>" <?= ($i['status_linha'] ?? 'ativo') === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4 form-group">
                  <label>Custo mensal (R$)</label>
                  <input name="custo_mensal" type="number" step="0.01" min="0" class="form-control" value="<?= $v('custo_mensal') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Contrato</label>
                  <input name="contrato" class="form-control" value="<?= $v('contrato') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Plano</label>
                  <input name="plano" class="form-control" value="<?= $v('plano') ?>">
                </div>
              </div>
            </div>

            <!-- Equipamento TI -->
            <div class="detalhe-bloco" data-tabela="item_equipamento_ti" style="display:none">
              <h6 class="text-muted mb-3">Equipamento TI</h6>
              <div class="row">
                <div class="col-md-6 form-group">
                  <label>Proprietário</label>
                  <select name="proprietario" class="form-control">
                    <option value="">— selecione —</option>
                    <?php foreach (['Voke', 'Minascopy', 'Líder', 'Valence', 'TTG'] as $p): ?>
                      <option value="<?= $p ?>" <?= ($i['proprietario'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 form-group">
                  <label>Marca</label>
                  <input name="marca" class="form-control" placeholder="Dell, Apple, Samsung…" value="<?= $v('marca') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Modelo</label>
                  <input name="modelo" class="form-control" value="<?= $v('modelo') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Número de série</label>
                  <input name="numero_serie" class="form-control" value="<?= $v('numero_serie') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Etiqueta / Patrimônio</label>
                  <input name="etiqueta" class="form-control" value="<?= $v('etiqueta') ?>">
                </div>
                <!-- IMEI e Linha — apenas para celular/tablet -->
                <div class="col-md-6 form-group campo-movel" style="display:none">
                  <label>IMEI</label>
                  <input name="imei" class="form-control" maxlength="20" value="<?= $v('imei') ?>">
                </div>
                <div class="col-md-6 form-group campo-movel" style="display:none">
                  <label>Linha telefônica</label>
                  <select name="linha_item_id" class="form-control">
                    <option value="">— nenhuma —</option>
                    <?php foreach ($linhasDisponiveis as $ln): ?>
                      <option value="<?= (int)$ln->id ?>"
                        <?= ($i['linha_item_id'] ?? '') == $ln->id ? 'selected' : '' ?>>
                        <?= e($ln->numero_linha ?: $ln->descricao) ?>
                        <?php if ($ln->operadora): ?> — <?= e($ln->operadora) ?><?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <!-- MAC Address -->
                <div class="col-md-6 form-group">
                  <label>MAC Address</label>
                  <input name="mac_address" class="form-control" maxlength="17"
                         placeholder="AA:BB:CC:DD:EE:FF" value="<?= $v('mac_address') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Fornecedor</label>
                  <input name="fornecedor" class="form-control" value="<?= $v('fornecedor') ?>">
                </div>
              </div>
            </div>

            <!-- Veículo (item_veiculo) -->
            <div class="detalhe-bloco" data-tabela="item_veiculo" style="display:none">
              <h6 class="text-muted mb-3">Veículo</h6>
              <div class="row">
                <div class="col-md-6 form-group">
                  <label>Marca</label>
                  <input name="marca" class="form-control" value="<?= $v('marca') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Modelo</label>
                  <input name="modelo" class="form-control" value="<?= $v('modelo') ?>">
                </div>
                <div class="col-md-4 form-group">
                  <label>Placa</label>
                  <input name="placa" class="form-control" value="<?= $v('placa') ?>">
                </div>
                <div class="col-md-4 form-group">
                  <label>Ano</label>
                  <input name="ano" type="number" min="1900" max="2100" class="form-control" value="<?= $v('ano') ?>">
                </div>
                <div class="col-md-4 form-group">
                  <label>Cor</label>
                  <input name="cor" class="form-control" value="<?= $v('cor') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>RENAVAM</label>
                  <input name="renavam" class="form-control" value="<?= $v('renavam') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Proprietário</label>
                  <select name="proprietario" class="form-control">
                    <option value="">— selecione —</option>
                    <?php foreach (['Stellants/Flua', 'Valence', 'Barros e Braga', 'MM', 'Localiza'] as $p): ?>
                      <option value="<?= $p ?>" <?= ($i['proprietario'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 form-group">
                  <label>Data de contratação</label>
                  <input name="data_contratacao" type="date" class="form-control" value="<?= $v('data_contratacao') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Data de vencimento</label>
                  <input name="data_vencimento" type="date" class="form-control" value="<?= $v('data_vencimento') ?>">
                </div>
              </div>
            </div>

            <!-- Cartão Benefício (item_cartao) -->
            <div class="detalhe-bloco" data-tabela="item_cartao" style="display:none">
              <h6 class="text-muted mb-3">Cartão Benefício</h6>
              <div class="row">
                <div class="col-md-6 form-group">
                  <label>Tipo de cartão</label>
                  <select name="tipo_cartao" class="form-control">
                    <option value="">— selecione —</option>
                    <?php foreach (['Onfly', 'Combustível'] as $tc): ?>
                      <option value="<?= $tc ?>" <?= ($i['tipo_cartao'] ?? '') === $tc ? 'selected' : '' ?>><?= $tc ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 form-group">
                  <label>Bandeira</label>
                  <input name="bandeira" class="form-control" placeholder="Visa, Mastercard…" value="<?= $v('bandeira') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Número do cartão</label>
                  <input name="numero_cartao" class="form-control" maxlength="20" value="<?= $v('numero_cartao') ?>">
                </div>
                <div class="col-md-6 form-group">
                  <label>Fornecedor</label>
                  <input name="fornecedor" class="form-control" value="<?= $v('fornecedor') ?>">
                </div>
              </div>
            </div>

          </div><!-- /#blocos-detalhe -->
        </div>

        <div class="card-footer text-right">
          <?php if ($voltarUrl): ?>
          <a href="<?= $voltarUrl ?>" class="btn btn-secondary">Cancelar</a>
          <?php endif; ?>
          <button class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</section>

<script>
(function () {
  var selectTipo = document.getElementById('selectTipo');
  var qtdTotal   = document.getElementById('qtdTotal');
  var blocos     = document.querySelectorAll('.detalhe-bloco');

  function ocultarBlocos() {
    blocos.forEach(function (b) { b.style.display = 'none'; });
  }

  function aplicarCamposMovel(isMovel) {
    var moveis = document.querySelectorAll('.campo-movel');
    moveis.forEach(function (el) { el.style.display = isMovel ? '' : 'none'; });
  }

  function aplicarTipo(isDeterminado, tabela, isMovel) {
    ocultarBlocos();
    if (isDeterminado && tabela) {
      var bloco = document.querySelector('.detalhe-bloco[data-tabela="' + tabela + '"]');
      if (bloco) bloco.style.display = '';
      qtdTotal.value    = 1;
      qtdTotal.readOnly = true;
    } else {
      qtdTotal.readOnly = false;
    }
    if (tabela === 'item_equipamento_ti') {
      aplicarCamposMovel(isMovel);
    }
  }

  if (selectTipo) {
    selectTipo.addEventListener('change', function () {
      var opt = this.options[this.selectedIndex];
      if (!opt || !opt.value) { ocultarBlocos(); qtdTotal.readOnly = false; return; }
      aplicarTipo(
        parseInt(opt.getAttribute('data-determinado') || '0', 10),
        opt.getAttribute('data-tabela') || '',
        opt.getAttribute('data-is-movel') === '1'
      );
    });
  }

  // Inicialização na edição ou tipo fixo na criação
  <?php if ($editTipoId): ?>
  (function () {
    var isMovel = <?= preg_match('/celular|tablet/i', $tipoFixo?->nome ?? '') ? 'true' : 'false' ?>;
    aplicarTipo(
      <?= $editIsDeterminado ?>,
      '<?= $editTabela ?>',
      isMovel
    );
  })();
  <?php endif; ?>
})();
</script>

