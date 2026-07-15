<?php
$emp = $emprestimo ?? [];
$statusLabels = [
    'ativo'     => 'Ativo',
    'reservado' => 'Reservado',
];
$statusClasses = [
    'ativo'     => 'primary',
    'reservado' => 'warning',
];
$status = $emp['status'] ?? 'ativo';
?>
<div class="card card-outline card-info mb-3">
  <div class="card-header d-flex align-items-center py-2">
    <h3 class="card-title mb-0 mr-auto" style="font-size:1rem">
      <?= $status === 'reservado' ? 'Reserva' : 'Empréstimo' ?>
      #<?= (int)$emp['id'] ?>
    </h3>
    <span class="badge badge-<?= $statusClasses[$status] ?? 'secondary' ?>">
      <?= e($statusLabels[$status] ?? $status) ?>
    </span>
  </div>
  <div class="card-body py-3">
    <dl class="row mb-0" style="font-size:.9rem">
      <dt class="col-sm-5">Colaborador</dt>
      <dd class="col-sm-7">
        <a href="<?= base_url('hr/colaboradores/' . urlencode((string)($emp['colaborador_codpessoa'] ?? ''))) ?>">
          <?= e($emp['colaborador_nome'] ?? '—') ?>
        </a>
        <small class="text-muted d-block">(<?= e($emp['colaborador_codpessoa'] ?? '') ?>)</small>
      </dd>
      <dt class="col-sm-5">Quantidade</dt>
      <dd class="col-sm-7"><?= (int)($emp['quantidade'] ?? 1) ?></dd>
      <dt class="col-sm-5"><?= $status === 'reservado' ? 'Data prevista' : 'Data de entrega' ?></dt>
      <dd class="col-sm-7"><?= format_date_br($emp['data_entrega'] ?? null) ?></dd>
      <?php if (!empty($emp['data_prevista_devolucao'])): ?>
      <dt class="col-sm-5">Prev. devolução</dt>
      <dd class="col-sm-7"><?= format_date_br($emp['data_prevista_devolucao']) ?></dd>
      <?php endif; ?>
      <?php if (!empty($emp['observacao'])): ?>
      <dt class="col-sm-5">Observação</dt>
      <dd class="col-sm-7"><?= nl2br(e($emp['observacao'])) ?></dd>
      <?php endif; ?>
    </dl>
  </div>
  <div class="card-footer py-2">
    <a href="<?= base_url('emprestimos/' . (int)$emp['id']) ?>" class="btn btn-outline-info btn-sm">
      <i class="fas fa-external-link-alt mr-1"></i>Ver empréstimo
    </a>
  </div>
</div>
