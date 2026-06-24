<?php
$statusLabels = [
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
?>
<section class="content pt-3">
  <div class="container-fluid">
    <form method="get" action="<?= base_url('itens') ?>">
      <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap">
          <h3 class="card-title mb-0 mr-auto">Itens</h3>

          <select name="categoria_id" class="form-control form-control-sm ml-2" style="width:160px" onchange="this.form.submit()">
            <option value="">Todas categorias</option>
            <?php foreach ($categoriasAtivas as $c): ?>
              <option value="<?= (int)$c->id ?>" <?= $filtroCategoria===$c->id ? 'selected':'' ?>><?= e($c->nome) ?></option>
            <?php endforeach; ?>
          </select>

          <select name="tipo_item_id" class="form-control form-control-sm ml-1" style="width:160px" onchange="this.form.submit()">
            <option value="">Todos os tipos</option>
            <?php foreach ($tiposAtivos as $t): ?>
              <option value="<?= (int)$t->id ?>" <?= $filtroTipo===$t->id ? 'selected':'' ?>><?= e($t->nome) ?></option>
            <?php endforeach; ?>
          </select>

          <select name="status" class="form-control form-control-sm ml-1" style="width:140px" onchange="this.form.submit()">
            <option value="">Todos status</option>
            <?php foreach ($statusLabels as $v => $l): ?>
              <option value="<?= $v ?>" <?= $filtroStatus===$v ? 'selected':'' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>

          <?php if ($filtroCategoria || $filtroTipo || $filtroStatus): ?>
            <a href="<?= base_url('itens') ?>" class="btn btn-sm btn-outline-secondary ml-1" title="Limpar filtros">
              <i class="fas fa-times"></i>
            </a>
          <?php endif; ?>

          <?php if ($canCreate ?? false): ?>
          <a href="<?= base_url('itens/criar') ?>" class="btn btn-sm btn-primary ml-2">
            <i class="fas fa-plus mr-1"></i>Novo Item
          </a>
          <?php endif; ?>
        </div>

        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Descrição</th>
                <th>Categoria</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Qtd Total</th>
                <th class="text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!empty($itens)): ?>
              <?php foreach ($itens as $i): ?>
              <tr>
                <td><?= (int)$i['id'] ?></td>
                <td><?= e($i['descricao']) ?></td>
                <td><?= e($i['nome_categoria']) ?></td>
                <td><?= e($i['nome_tipo']) ?></td>
                <td>
                  <span class="badge badge-<?= $statusClasses[$i['status']] ?? 'secondary' ?>">
                    <?= e($statusLabels[$i['status']] ?? $i['status']) ?>
                  </span>
                </td>
                <td><?= (int)$i['quantidade_total'] ?></td>
                <td class="text-right">
                  <a href="<?= base_url('itens/'.(int)$i['id']) ?>" class="btn btn-xs btn-outline-secondary">Ver</a>
                  <?php if (($i['nivel_usuario'] ?? 0) >= 2): ?>
                  <a href="<?= base_url('itens/'.(int)$i['id'].'/editar') ?>" class="btn btn-xs btn-outline-primary">Editar</a>
                  <a href="<?= base_url('emprestimos/criar?item_id='.(int)$i['id']) ?>" class="btn btn-xs btn-outline-success">Emprestar</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Nenhum item encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </form>
  </div>
</section>

