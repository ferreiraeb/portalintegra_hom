<?php
$modo     = $modo ?? 'criar';
$isEdit   = $modo === 'editar';
$cat      = $categoria ?? ['nome' => '', 'descricao' => '', 'ativo' => 1];
$erro     = $erro ?? null;
$titulo   = $isEdit ? 'Editar Categoria' : 'Nova Categoria';
$action   = $isEdit
    ? base_url('categorias/'.(int)$cat['id'].'/editar')
    : base_url('categorias/criar');
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:640px;">
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

          <div class="form-group">
            <label>Nome <span class="text-danger">*</span></label>
            <input name="nome" class="form-control" required
                   value="<?= e($cat['nome'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Descrição</label>
            <textarea name="descricao" class="form-control" rows="3"><?= e($cat['descricao'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="card-footer text-right">
          <a href="<?= base_url('categorias') ?>" class="btn btn-secondary">Cancelar</a>
          <button class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</section>

