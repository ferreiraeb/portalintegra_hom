<?php /** @var array $categorias */ ?>
<section class="content pt-3">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0 mr-auto">Categorias</h3>
        <a href="<?= base_url('categorias/criar') ?>" class="btn btn-sm btn-primary ml-2">
          <i class="fas fa-plus mr-1"></i>Nova Categoria
        </a>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover table-sm mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Nome</th>
              <th>Descrição</th>
              <th>Status</th>
              <th class="text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($categorias)): ?>
            <?php foreach ($categorias as $cat): ?>
            <tr class="<?= $cat->ativo ? '' : 'text-muted' ?>">
              <td><?= (int)$cat->id ?></td>
              <td><?= e($cat->nome) ?></td>
              <td><?= e($cat->descricao ?? '—') ?></td>
              <td>
                <?php if ($cat->ativo): ?>
                  <span class="badge badge-success">Ativa</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Inativa</span>
                <?php endif; ?>
              </td>
              <td class="text-right">
                <a href="<?= base_url('categorias/'.(int)$cat->id.'/editar') ?>"
                   class="btn btn-xs btn-outline-primary">Editar</a>
                <form method="post"
                      action="<?= base_url('categorias/'.(int)$cat->id.'/toggle') ?>"
                      style="display:inline"
                      onsubmit="return confirm('Alterar status desta categoria?')">
                  <?php csrf_field(); ?>
                  <button class="btn btn-xs <?= $cat->ativo ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                    <?= $cat->ativo ? 'Desativar' : 'Ativar' ?>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

