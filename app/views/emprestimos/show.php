<?php
$emp = $emprestimo ?? [];
$statusLabels = [
    'ativo'       => 'Ativo',
    'reservado'   => 'Reservado',
    'devolvido'   => 'Devolvido',
    'extraviado'  => 'Extraviado',
    'transferido' => 'Transferido',
    'cancelado'   => 'Cancelado',
];
$statusClasses = [
    'ativo'       => 'primary',
    'reservado'   => 'warning',
    'devolvido'   => 'success',
    'extraviado'  => 'dark',
    'transferido' => 'info',
    'cancelado'   => 'secondary',
];
$status = $emp['status'] ?? 'ativo';
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:700px;">

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-warning alert-dismissible">
        <?= e($_SESSION['flash_error']) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0 mr-auto">
          <?= $status === 'reservado' ? 'Reserva' : 'Empréstimo' ?>
          #<?= (int)$emp['id'] ?>
        </h3>
        <span class="badge badge-<?= $statusClasses[$status] ?? 'secondary' ?>" style="font-size:.85rem">
          <?= e($statusLabels[$status] ?? $status) ?>
        </span>
      </div>

      <div class="card-body">
        <dl class="row">
          <dt class="col-sm-4">Item</dt>
          <dd class="col-sm-8">
            <a href="<?= base_url('itens/'.(int)$emp['item_id']) ?>">
              <?= e($emp['item_descricao'] ?? '—') ?>
            </a>
            <small class="text-muted ml-1"><?= e($emp['tipo_nome'] ?? '') ?></small>
          </dd>
          <dt class="col-sm-4">Colaborador</dt>
          <dd class="col-sm-8">
            <?= e($emp['colaborador_nome'] ?? '—') ?>
            <small class="text-muted">(<?= e($emp['colaborador_codpessoa'] ?? '') ?>)</small>
          </dd>
          <dt class="col-sm-4">Quantidade</dt>
          <dd class="col-sm-8"><?= (int)$emp['quantidade'] ?></dd>
          <dt class="col-sm-4"><?= $status === 'reservado' ? 'Data prevista' : 'Data de entrega' ?></dt>
          <dd class="col-sm-8"><?= e($emp['data_entrega'] ?? '—') ?></dd>
          <dt class="col-sm-4">Prev. devolução</dt>
          <dd class="col-sm-8"><?= $emp['data_prevista_devolucao'] ? e($emp['data_prevista_devolucao']) : '—' ?></dd>
          <?php if (!empty($emp['data_devolucao'])): ?>
          <dt class="col-sm-4">Data devolução</dt>
          <dd class="col-sm-8"><?= e($emp['data_devolucao']) ?></dd>
          <?php endif; ?>
          <?php if (!empty($emp['observacao'])): ?>
          <dt class="col-sm-4">Observação</dt>
          <dd class="col-sm-8"><?= nl2br(e($emp['observacao'])) ?></dd>
          <?php endif; ?>
        </dl>

        <?php if ($status === 'ativo' && ($nivelUsuario ?? 0) >= 2): ?>
        <hr>
        <form method="post" action="<?= base_url('emprestimos/'.(int)$emp['id'].'/devolver') ?>">
          <?php csrf_field(); ?>
          <div class="form-group">
            <label>Observação da devolução</label>
            <input name="observacao" class="form-control form-control-sm" placeholder="Opcional">
          </div>
          <button class="btn btn-success btn-sm"
                  onclick="return confirm('Confirmar devolução?')">
            <i class="fas fa-undo mr-1"></i>Registrar Devolução
          </button>
        </form>
        <?php endif; ?>

        <?php if ($status === 'reservado' && ($nivelUsuario ?? 0) >= 2): ?>
        <hr>
        <div class="d-flex gap-2">
          <form method="post"
                action="<?= base_url('emprestimos/'.(int)$emp['id'].'/ativar') ?>"
                onsubmit="return confirm('Confirmar ativação do empréstimo?')">
            <?php csrf_field(); ?>
            <button class="btn btn-primary btn-sm mr-2">
              <i class="fas fa-check mr-1"></i>Confirmar Entrega (Ativar Empréstimo)
            </button>
          </form>
          <form method="post"
                action="<?= base_url('emprestimos/'.(int)$emp['id'].'/cancelar') ?>"
                onsubmit="return confirm('Cancelar esta reserva?')">
            <?php csrf_field(); ?>
            <button class="btn btn-outline-danger btn-sm">
              <i class="fas fa-times mr-1"></i>Cancelar Reserva
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <div class="card-footer">
        <a href="<?= base_url($status === 'reservado' ? 'emprestimos?tab=reservas' : 'emprestimos') ?>"
           class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-arrow-left mr-1"></i>Voltar
        </a>
      </div>
    </div>
  </div>
</section>

