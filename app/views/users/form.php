<?php
$mode   = $mode ?? 'create';
$isEdit = ($mode === 'edit');
$u      = $user ?? ['nome'=>'','login'=>'','email'=>'','origem'=>'local'];
$error  = $error ?? null;
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:720px;">
    <div class="card card-primary">
      <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? 'Editar Usuário' : 'Novo Usuário' ?></h3>
      </div>

      <form method="post">
        <div class="card-body">
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <?php csrf_field(); ?>

          <div class="form-group">
            <label>Nome</label>
            <input name="nome" class="form-control" value="<?= htmlspecialchars($u['nome'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label>Login</label>
            <input name="login" class="form-control"
                   value="<?= htmlspecialchars($u['login'] ?? '') ?>"
                   <?= $isEdit ? 'readonly' : 'required' ?>>
          </div>

          <div class="form-group">
            <label>E-mail</label>
            <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($u['email'] ?? '') ?>">
          </div>

          <?php if (!$isEdit || ($isEdit && ($u['origem'] ?? 'local') === 'local')): ?>
          <div class="form-group">
            <label>Senha <?= $isEdit ? '(deixe em branco para não alterar)' : '' ?></label>
            <input name="senha" type="password" class="form-control" <?= $isEdit ? '' : 'required' ?>>
          </div>
          <?php else: ?>
          <div class="alert alert-info">
            Usuário de origem AD: senha não é gerenciada aqui.
          </div>
          <?php endif; ?>
        </div>

        <div class="card-footer text-right">
          <a href="<?= base_url('users') ?>" class="btn btn-secondary">Cancelar</a>
          <button class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</section>