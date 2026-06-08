<?php /** @var array $tipos @var array $categoriasAtivas @var int $filtroCategoria */ ?>
<section class="content pt-3">
  <div class="container-fluid">
    <form method="get" action="<?= base_url('tipos-item') ?>">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title mb-0 mr-auto">Tipos de Item</h3>
          <select name="categoria_id" class="form-control form-control-sm ml-2" style="width:200px" onchange="this.form.submit()">
            <option value="">Todas as categorias</option>
            <?php foreach ($categoriasAtivas as $c): ?>
              <option value="<?= (int)$c->id ?>" <?= $filtroCategoria === $c->id ? 'selected' : '' ?>>
                <?= e($c->nome) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($filtroCategoria): ?>
            <a href="<?= base_url('tipos-item') ?>" class="btn btn-sm btn-outline-secondary ml-1" title="Limpar filtro">
              <i class="fas fa-times"></i>
            </a>
          <?php endif; ?>
          <a href="<?= base_url('tipos-item/criar') ?>" class="btn btn-sm btn-primary ml-2">
            <i class="fas fa-plus mr-1"></i>Novo Tipo
          </a>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Rastreável</th>
                <th>Tabela detalhe</th>
                <th>Status</th>
                <th class="text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!empty($tipos)): ?>
              <?php foreach ($tipos as $t): ?>
              <tr class="<?= $t['ativo'] ? '' : 'text-muted' ?>">
                <td><?= (int)$t['id'] ?></td>
                <td><?= e($t['nome']) ?></td>
                <td><?= e($t['nome_categoria']) ?></td>
                <td>
                  <?php if ($t['is_determinado']): ?>
                    <span class="badge badge-info">Sim</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Não</span>
                  <?php endif; ?>
                </td>
                <td><code><?= e($t['tabela_detalhe'] ?? '—') ?></code></td>
                <td>
                  <?php if ($t['ativo']): ?>
                    <span class="badge badge-success">Ativo</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Inativo</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <a href="<?= base_url('tipos-item/'.(int)$t['id'].'/editar') ?>"
                     class="btn btn-xs btn-outline-primary">Editar</a>
                  <form method="post"
                        action="<?= base_url('tipos-item/'.(int)$t['id'].'/toggle') ?>"
                        style="display:inline"
                        onsubmit="return confirm('Alterar status?')">
                    <?php csrf_field(); ?>
                    <button class="btn btn-xs <?= $t['ativo'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                      <?= $t['ativo'] ? 'Desativar' : 'Ativar' ?>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Nenhum tipo encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </form>
  </div>
</section>

