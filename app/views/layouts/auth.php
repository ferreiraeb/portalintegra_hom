<?php
// views/layouts/auth.php
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($config['app']['name'] ?? 'Portal Integra') ?> — Acesso</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- AdminLTE & deps (CDN) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3/dist/css/adminlte.min.css">
  <script src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3/dist/js/adminlte.min.js"></script>

  <style>
    body.login-page { min-height: 100vh; }
	  
  .login-card-wide {
    width: 440px;     /* controle centralizado */
    max-width: 92vw;
    margin: 0 auto;   /* centraliza */
  }

	
	
  </style>
</head>
<body class="hold-transition login-page">
  <!-- Conteúdo específico da página -->
  <div class="login-box">
    <?php if (isset($content) && is_callable($content)) { $content(); } ?>
  </div>
</body>
</html>