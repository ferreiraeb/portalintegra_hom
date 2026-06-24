<?php ob_start(); ?>
<section class="content pt-3">
  <div class="container" style="max-width:540px;">
    <div class="card card-primary">
      <div class="card-header"><h3 class="card-title">Alterar Senha</h3></div>
      <form method="post">
        <div class="card-body">
          <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
          <?php csrf_field(); ?>
          <div class="form-group">
            <label>Nova Senha</label>
            <input name="nova_senha" type="password" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Confirmar Senha</label>
            <input name="confirma_senha" type="password" class="form-control" required>
          </div>
        </div>
        <div class="card-footer text-right">
          <button class="btn btn-primary">Alterar</button>
        </div>
      </form>
    </div>
  </div>
</section>
<?php $content = function(){ echo ob_get_clean(); }; require __DIR__ . '/../layouts/adminlte.php'; ?>