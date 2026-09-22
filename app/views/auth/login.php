<?php
// views/auth/login.php
$loginAttempt = $loginAttempt ?? '';
ob_start();
?>


<div class="login-card-wide"> 
		<div class="text-center mb-3">
		  <img src="<?= base_url('assets/img/logo_HD_transparente.png') ?>"
			   alt="Portal Integra"
			   style="max-width: 400px; width: 100%; height: auto;">
		</div>
</div>
<div class="login-card-wide"> 
  
		<div class="card card-primary">
		  <div class="card-header"><h3 class="card-title">Acesso</h3></div>
		  <form method="post" autocomplete="on">
			<div class="card-body">
			  <?php if (!empty($error)): ?>
				<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
			  <?php endif; ?>
			  <?php csrf_field(); ?>
			  <div class="form-group">
				<label>Login de rede ou e-mail</label>
				<input type="text" name="login" class="form-control" required autofocus
					   autocomplete="username"
					   placeholder="ex: joao.silva ou joao.silva@valence.com.br"
					   value="<?= e($loginAttempt) ?>">
			  </div>
			  <div class="form-group">
				<label>Senha</label>
				<input type="password" name="senha" class="form-control" required autocomplete="current-password">
			  </div>
			</div>
			<div class="card-footer text-right">
			  <button class="btn btn-primary">Entrar</button>
			</div>
		  </form>
		</div>
</div>	
<?php
$content = function() { echo ob_get_clean(); };
require __DIR__ . '/../layouts/auth.php';
