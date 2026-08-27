<?php
$modo    = $modo ?? 'criar';
$isEdit  = $modo === 'editar';
$t       = $tipo ?? ['categoria_id' => 0, 'nome' => '', 'descricao' => ''];
$erro    = $erro ?? null;
$titulo  = $isEdit ? 'Editar Tipo de Item' : 'Novo Tipo de Item';
$action  = $isEdit
    ? base_url('tipos-item/'.(int)$t['id'].'/editar')
    : base_url('tipos-item/criar');
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
            <label>Categoria <span class="text-danger">*</span></label>
            <select name="categoria_id" class="form-control" required>
              <option value="">Selecione...</option>
              <?php foreach ($categoriasAtivas as $c): ?>
                <option value="<?= (int)$c->id ?>"
                  <?= (int)($t['categoria_id'] ?? 0) === (int)$c->id ? 'selected' : '' ?>>
                  <?= e($c->nome) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Nome <span class="text-danger">*</span></label>
            <input name="nome" class="form-control" required value="<?= e($t['nome'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Descricao</label>
            <textarea name="descricao" class="form-control" rows="3"><?= e($t['descricao'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="card-footer text-right">
          <a href="<?= base_url('tipos-item') ?>" class="btn btn-secondary">Cancelar</a>
          <button class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</section>
