<?php
$statusLabels = [
    'ativo'       => 'Ativo',
    'devolvido'   => 'Devolvido',
    'extraviado'  => 'Extraviado',
    'transferido' => 'Transferido',
    'cancelado'   => 'Cancelado',
];
$statusClasses = [
    'ativo'       => 'primary',
    'devolvido'   => 'success',
    'extraviado'  => 'dark',
    'transferido' => 'info',
    'cancelado'   => 'secondary',
];
?>
<section class="content pt-3">
  <div class="container-fluid">
    <form method="get" action="<?= base_url('emprestimos') ?>">
      <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap">
          <h3 class="card-title mb-0 mr-auto">Empréstimos</h3>

          <select name="status" class="form-control form-control-sm ml-2" style="width:155px" onchange="this.form.submit()">
            <option value="">Todos status</option>
            <?php foreach ($statusLabels as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($filtroStatus ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>

          <input name="colaborador" class="form-control form-control-sm ml-1" style="width:180px"
                 placeholder="Colaborador…" value="<?= e($filtroColab ?? '') ?>">
          <button type="submit" class="btn btn-sm btn-outline-secondary ml-1">
            <i class="fas fa-search"></i>
          </button>
          <?php if (($filtroStatus ?? '') !== '' || ($filtroColab ?? '') !== ''): ?>
            <a href="<?= base_url('emprestimos') ?>" class="btn btn-sm btn-outline-secondary ml-1" title="Limpar filtros">
              <i class="fas fa-times"></i>
            </a>
          <?php endif; ?>

          <?php if ($canCreate ?? false): ?>
          <a href="<?= base_url('emprestimos/criar') ?>" class="btn btn-sm btn-primary ml-2">
            <i class="fas fa-plus mr-1"></i>Novo Empréstimo
          </a>
          <?php endif; ?>
        </div>

        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Item</th>
                <th>Colaborador</th>
                <th>Qtd</th>
                <th>Data Entrega</th>
                <th>Prev. Devolução</th>
                <th>Status</th>
                <th class="text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!empty($emprestimos)): ?>
              <?php foreach ($emprestimos as $emp): ?>
              <tr>
                <td><?= (int)$emp['id'] ?></td>
                <td>
                  <?= e($emp['item_descricao']) ?>
                  <small class="text-muted d-block"><?= e($emp['tipo_nome']) ?></small>
                </td>
                <td>
                  <?= e($emp['colaborador_nome']) ?>
                  <small class="text-muted d-block"><?= e($emp['colaborador_codpessoa']) ?></small>
                </td>
                <td><?= (int)$emp['quantidade'] ?></td>
                <td><?= $emp['data_entrega'] ? e($emp['data_entrega']) : '—' ?></td>
                <td><?= $emp['data_prevista_devolucao'] ? e($emp['data_prevista_devolucao']) : '—' ?></td>
                <td>
                  <span class="badge badge-<?= $statusClasses[$emp['status']] ?? 'secondary' ?>">
                    <?= e($statusLabels[$emp['status']] ?? $emp['status']) ?>
                  </span>
                </td>
                <td class="text-right">
                  <a href="<?= base_url('emprestimos/'.(int)$emp['id']) ?>"
                     class="btn btn-xs btn-outline-secondary">Ver</a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Nenhum empréstimo encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </form>
  </div>
</section>
