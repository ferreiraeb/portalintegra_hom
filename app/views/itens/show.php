<?php
$i = $item ?? [];
$statusLabels  = [
    'disponivel' => 'Disponível',
    'em_uso'     => 'Em uso',
    'reservado'  => 'Reservado',
    'bloqueado'  => 'Bloqueado',
    'baixado'    => 'Baixado',
    'extraviado' => 'Extraviado',
];
$statusClasses = [
    'disponivel' => 'success',
    'em_uso'     => 'primary',
    'reservado'  => 'warning',
    'bloqueado'  => 'danger',
    'baixado'    => 'secondary',
    'extraviado' => 'dark',
];
$statusAtual = $i['status'] ?? 'disponivel';
$tabelaAtual = $i['tabela_detalhe'] ?? '';
$isDeterminado = (int)($i['is_determinado'] ?? 0);
$showEmprestimoCard = $isDeterminado && !empty($emprestimosItem);
$containerMax = $showEmprestimoCard ? '1100px' : '800px';

// Helpers de exibição
$rv = fn(string $key, $def = '—') => e($i[$key] ?? '') ?: $def;
$rd = fn(string $key, $def = '—') => format_date_br($i[$key] ?? null, $def);
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:<?= $containerMax ?>;">

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-warning alert-dismissible">
        <?= e($_SESSION['flash_error']) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success alert-dismissible">
        <?= e($_SESSION['flash_success']) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="row">
      <div class="<?= $showEmprestimoCard ? 'col-lg-7' : 'col-12' ?>">
    <div class="card card-primary">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0 mr-auto">Visualizar Item</h3>
        <span class="badge badge-<?= $statusClasses[$statusAtual] ?? 'secondary' ?> ml-2" style="font-size:.85rem">
          <?= e($statusLabels[$statusAtual] ?? $statusAtual) ?>
        </span>
      </div>

      <div class="card-body">

        <!-- ── Campos comuns ── -->
        <div class="form-group">
          <label>Tipo de Item</label>
          <input class="form-control" readonly
                 value="<?= e(($cat['nome'] ?? '') . ($tipo['nome'] ?? '') ? trim(($cat['nome'] ?? '') . ' / ' . ($tipo['nome'] ?? ''), ' /') : '—') ?>">
        </div>

        <div class="form-group">
          <label>Descrição</label>
          <input class="form-control" readonly value="<?= e($i['descricao'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label>Status</label>
              <input class="form-control" readonly
                     value="<?= e($statusLabels[$statusAtual] ?? $statusAtual) ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Quantidade total</label>
              <input class="form-control" readonly value="<?= (int)($i['quantidade_total'] ?? 1) ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Em uso</label>
              <input class="form-control" readonly value="<?= (int)($i['quantidade_em_uso'] ?? 0) ?>">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Observação</label>
          <textarea class="form-control" rows="2" readonly><?= e($i['observacao'] ?? '') ?></textarea>
        </div>

        <?php if ($tabelaAtual): ?>
        <hr>

        <?php if ($tabelaAtual === 'item_linha_telefonica'): ?>
          <h6 class="text-muted mb-3">Linha Telefônica</h6>
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Número da linha</label>
              <input class="form-control" readonly value="<?= $rv('numero_linha') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>ICC-ID / Número do chip</label>
              <input class="form-control" readonly value="<?= $rv('numero_chip') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Número anterior (portabilidade)</label>
              <input class="form-control" readonly value="<?= $rv('numero_anterior') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Operadora</label>
              <input class="form-control" readonly value="<?= $rv('operadora') ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Tipo de chip</label>
              <input class="form-control" readonly value="<?= $rv('tipo_chip') ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Status da linha</label>
              <?php
                $statusLinhaVal = $i['status_linha'] ?? '';
                $statusLinhaLabels = \Models\ItemLinhaTelefonica::statusLinhaLabels();
                $statusLinhaTexto = $statusLinhaLabels[$statusLinhaVal] ?? ($statusLinhaVal !== '' ? $statusLinhaVal : '');
              ?>
              <input class="form-control" readonly value="<?= e($statusLinhaTexto ?: '—') ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Custo mensal (R$)</label>
              <input class="form-control" readonly value="<?= $rv('custo_mensal') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Contrato</label>
              <input class="form-control" readonly value="<?= $rv('contrato') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Plano</label>
              <input class="form-control" readonly value="<?= $rv('plano') ?>">
            </div>
          </div>

        <?php elseif ($tabelaAtual === 'item_equipamento_ti'): ?>
          <h6 class="text-muted mb-3">Equipamento TI</h6>
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Proprietário</label>
              <input class="form-control" readonly value="<?= $rv('proprietario') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Marca</label>
              <input class="form-control" readonly value="<?= $rv('marca') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Modelo</label>
              <input class="form-control" readonly value="<?= $rv('modelo') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Número de série</label>
              <input class="form-control" readonly value="<?= $rv('numero_serie') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Etiqueta / Patrimônio</label>
              <input class="form-control" readonly value="<?= $rv('etiqueta') ?>">
            </div>
            <?php if (!empty($i['imei'])): ?>
            <div class="col-md-6 form-group">
              <label>IMEI</label>
              <input class="form-control" readonly value="<?= $rv('imei') ?>">
            </div>
            <?php endif; ?>
            <?php if (!empty($i['linha_item_id'])): ?>
            <div class="col-md-6 form-group">
              <label>Linha telefônica vinculada</label>
              <?php
                $linhaTexto = '—';
                if (!empty($linhaVinculada)) {
                    $linhaTexto = $linhaVinculada->numero_linha ?: $linhaVinculada->descricao;
                    if (!empty($linhaVinculada->operadora)) {
                        $linhaTexto .= ' — ' . $linhaVinculada->operadora;
                    }
                }
              ?>
              <input class="form-control" readonly value="<?= e($linhaTexto) ?>">
            </div>
            <?php endif; ?>
            <div class="col-md-6 form-group">
              <label>MAC Address</label>
              <input class="form-control" readonly value="<?= $rv('mac_address') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Fornecedor</label>
              <input class="form-control" readonly value="<?= $rv('fornecedor') ?>">
            </div>
          </div>

        <?php elseif ($tabelaAtual === 'item_veiculo'): ?>
          <h6 class="text-muted mb-3">Veículo</h6>
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Marca</label>
              <input class="form-control" readonly value="<?= $rv('marca') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Modelo</label>
              <input class="form-control" readonly value="<?= $rv('modelo') ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Placa</label>
              <input class="form-control" readonly value="<?= $rv('placa') ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Ano</label>
              <input class="form-control" readonly value="<?= $rv('ano') ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Cor</label>
              <input class="form-control" readonly value="<?= $rv('cor') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>RENAVAM</label>
              <input class="form-control" readonly value="<?= $rv('renavam') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Proprietário</label>
              <input class="form-control" readonly value="<?= $rv('proprietario') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Data de contratação</label>
              <input class="form-control" readonly value="<?= $rd('data_contratacao') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Data de vencimento</label>
              <input class="form-control" readonly value="<?= $rd('data_vencimento') ?>">
            </div>
          </div>

        <?php elseif ($tabelaAtual === 'item_cartao'): ?>
          <h6 class="text-muted mb-3">Cartão Benefício</h6>
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Tipo de cartão</label>
              <input class="form-control" readonly value="<?= $rv('tipo_cartao') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Bandeira</label>
              <input class="form-control" readonly value="<?= $rv('bandeira') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Número do cartão</label>
              <input class="form-control" readonly value="<?= $rv('numero_cartao') ?>">
            </div>
            <div class="col-md-6 form-group">
              <label>Fornecedor</label>
              <input class="form-control" readonly value="<?= $rv('fornecedor') ?>">
            </div>
          </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>

      <div class="card-footer d-flex justify-content-end align-items-center flex-wrap">
        <?php
          $voltarLista = !empty($listaUrl)
              ? base_url($listaUrl)
              : (!empty($tipo['id']) ? base_url('itens/tipo/'.(int)$tipo['id']) : null);
        ?>
        <?php if ($voltarLista): ?>
        <a href="<?= $voltarLista ?>" class="btn btn-secondary mr-2 mb-1">
          <i class="fas fa-arrow-left mr-1"></i>Voltar à lista
        </a>
        <?php endif; ?>
        <?php if (($nivelUsuario ?? 0) >= 2): ?>
        <a href="<?= base_url('itens/'.(int)$i['id'].'/editar') ?>" class="btn btn-primary mr-2 mb-1">
          <i class="fas fa-edit mr-1"></i>Editar
        </a>
        <button type="submit"
                form="frmDeleteItemShow"
                class="btn btn-danger mb-1"
                onclick="return confirm('Excluir permanentemente este item? Esta ação não pode ser desfeita.')">
          <i class="fas fa-trash mr-1"></i>Excluir
        </button>
        <?php endif; ?>
      </div>
    </div>
      </div>

      <?php if ($showEmprestimoCard): ?>
      <div class="col-lg-5">
        <?php foreach ($emprestimosItem as $emprestimo): ?>
          <?php include __DIR__ . '/_emprestimo_card.php'; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>

    <?php if (($nivelUsuario ?? 0) >= 2): ?>
    <form id="frmDeleteItemShow"
          method="post"
          action="<?= base_url('itens/'.(int)$i['id'].'/excluir') ?>"
          style="display:none">
      <?php csrf_field(); ?>
    </form>
    <?php endif; ?>
  </div>
</section>
