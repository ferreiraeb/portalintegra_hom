<?php
$c = $colaborador ?? [];

$dateFields = ['NASCIMENTO', 'DATAADMISSAO', 'DATARESCISAO'];
$formatDate = function ($val): string {
    if ($val === null || $val === '') return '—';
    if ($val instanceof \DateTime) return $val->format('d/m/Y');
    $ts = strtotime((string)$val);
    if ($ts === false) return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    // Corrige bug de Oracle com datas de 2 dígitos (nascimento)
    if ((int)date('Y', $ts) > (int)date('Y')) $ts = strtotime('-100 years', $ts);
    return htmlspecialchars(date('d/m/Y', $ts), ENT_QUOTES, 'UTF-8');
};

$cols = [
    'CODPESSOA'              => 'Cód. Pessoa',
    'CODCONTRATO'            => 'Cód. Contrato',
    'CPF'                    => 'CPF',
    'SEXO'                   => 'Sexo',
    'NASCIMENTO'             => 'Nascimento',
    'CARGO'                  => 'Cargo',
    'EMPRESA'                => 'Empresa',
    'UNIDADE'                => 'Unidade',
    'CLASSIFICACAOGERENCIAL' => 'Classificação Gerencial',
    'CENTROCUSTO'            => 'Centro de Custo',
    'SETOR'                  => 'Setor',
    'LIDER'                  => 'Líder',
    'SITUACAOCONTRATO'       => 'Situação Contrato',
    'DATAADMISSAO'           => 'Admissão',
    'DATARESCISAO'           => 'Rescisão',
];

$statusLabelsEmp = ['ativo' => 'Em uso', 'reservado' => 'Reservado'];
$statusBadgeEmp  = ['ativo' => 'primary', 'reservado' => 'warning'];
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:900px;">

    <?php if ($erro): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <strong><i class="fas fa-exclamation-triangle mr-1"></i>Erro:</strong> <?= e($erro) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
    <?php endif; ?>

    <?php if ($c): ?>

    <!-- ── Dados do colaborador ── -->
    <div class="card card-primary">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0 mr-auto">
          <i class="fas fa-user mr-2"></i><?= e($c['NOMECOMPLETO'] ?? '') ?>
        </h3>
        <?php $status = $c['STATUS'] ?? ''; ?>
        <span class="badge badge-<?= $status === 'ATIVO' ? 'success' : 'secondary' ?> ml-2" style="font-size:.85rem">
          <?= e($status) ?>
        </span>
      </div>

      <div class="card-body">
        <div class="row">
          <?php foreach ($cols as $key => $label):
            $val = $c[$key] ?? null;
            if ($val === null || $val === '') continue;
          ?>
          <div class="col-md-6 col-lg-4">
            <div class="form-group">
              <label><?= e($label) ?></label>
              <?php if ($key === 'CPF' && strlen((string)$val) === 11):
                $v = (string)$val; ?>
                <input class="form-control" readonly
                       value="<?= e(substr($v,0,3).'.'.substr($v,3,3).'.'.substr($v,6,3).'-'.substr($v,9,2)) ?>">
              <?php elseif (in_array($key, $dateFields)): ?>
                <input class="form-control" readonly value="<?= $formatDate($val) ?>">
              <?php else: ?>
                <input class="form-control" readonly value="<?= e((string)$val) ?>">
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card-footer">
        <a href="<?= base_url('hr/colaboradores') ?>" class="btn btn-secondary">
          <i class="fas fa-arrow-left mr-1"></i>Voltar à lista
        </a>
      </div>
    </div>

    <!-- ── Itens atribuídos ── -->
    <div class="card card-info mt-1">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">
          <i class="fas fa-box mr-1"></i>Itens atribuídos
        </h3>
        <?php if (!empty($itens)): ?>
          <span class="badge badge-light ml-2"><?= count($itens) ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($itens)): ?>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>Item</th>
              <th>Tipo / Categoria</th>
              <th>Situação</th>
              <th>Desde</th>
              <th>Prev. devolução</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($itens as $item): ?>
            <tr>
              <td>
                <a href="<?= base_url('itens/' . (int)$item['item_id']) ?>">
                  <?= e($item['item_descricao']) ?>
                </a>
              </td>
              <td>
                <?= e($item['tipo_nome']) ?>
                <small class="text-muted d-block"><?= e($item['categoria_nome']) ?></small>
              </td>
              <td>
                <span class="badge badge-<?= e($statusBadgeEmp[$item['status']] ?? 'secondary') ?>">
                  <?= e($statusLabelsEmp[$item['status']] ?? $item['status']) ?>
                </span>
              </td>
              <td><?= e((string)($item['data_entrega'] ?? '')) ?></td>
              <td><?= e((string)($item['data_prevista_devolucao'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-center text-muted py-4">
        <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
        Nenhum item atribuído no momento.
      </div>
      <?php endif; ?>
    </div>

    <?php endif; ?>

  </div>
</section>
