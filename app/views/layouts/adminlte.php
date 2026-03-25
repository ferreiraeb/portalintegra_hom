<?php
$user    = $_SESSION['user'] ?? null;
$config  = $config ?? [];
$appName = $config['app']['name'] ?? 'Portal Integra';
$layout  = $config['app']['menu_layout'] ?? 'sidebar';

// Rota atual (sem query)
$currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// Helpers para menu ativo
function is_current(string $path, string $currentPath): bool {
  return trim($path, '/') === trim($currentPath, '/');
}
function starts_with_path(string $prefix, string $currentPath): bool {
  $prefix = trim($prefix, '/');
  $cur    = trim($currentPath, '/');
  if ($prefix === '') return $cur === '';
  return strpos($cur, $prefix) === 0;
}
function active_class(string $path, string $currentPath): string {
  return is_current($path, $currentPath) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($appName) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3/dist/css/adminlte.min.css">
  
  <!-- Favicon / App icons -->
	<link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('assets/favicon/favicon-32.png') ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?= base_url('assets/favicon/favicon-16.png') ?>">
	<link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('assets/favicon/favicon-180.png') ?>">

	<!-- Cor da barra no mobile (Chrome/Android, Edge, etc.) -->
	<meta name="theme-color" content="#0F5D9A">

  <style>
    /* Melhorias nos modais para campos longos (DN/UPN) */
    #genericModal .modal-body {
      overflow-wrap: anywhere;
      word-break: break-word;
    }
    .code-wrap {
      font-family: monospace;
      white-space: pre-wrap;
      word-break: break-word;
    }
	


  .main-sidebar .brand-link .brand-image {
    height: 130px !important;     /* força a altura */
    max-height: none !important; /* remove o limite do tema */
    width: auto;
    float: none !important;
    display: inline-block;
  }

  .main-sidebar .brand-link {
    height: auto;
    padding: 12px 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .sidebar-mini.sidebar-collapse .main-sidebar .brand-link .brand-image {
    height: 32px !important;
  }

	
	
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <?php if ($layout === 'topbar'): ?>
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="<?= base_url('home') ?>" class="nav-link<?= (is_current('home',$currentPath) || is_current('', $currentPath)) ? ' active':'' ?>">Início</a>
        </li>
        <?php if (\Security\Permission::level('users.manage') >= 1): ?>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="<?= base_url('users') ?>" class="nav-link<?= starts_with_path('users',$currentPath) ? ' active':'' ?>">Usuários</a>
        </li>
        <?php endif; ?>
      </ul>
    <?php else: ?>
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
      </ul>
    <?php endif; ?>
	
	<ul class="navbar-nav ml-auto">
	  <?php if ($user): ?>
		<!-- Nome do usuário logado (alinhado) -->
		<li class="nav-item d-none d-sm-flex align-items-center mr-2">
		  <a href="javascript:void(0)" class="nav-link d-flex align-items-center py-0">
			<i class="far fa-user mr-1"></i>
			<span><?= htmlspecialchars($user['nome'] ?? $user['login'] ?? 'Usuário') ?></span>
		  </a>
		</li>

		
		

    <!-- Alterar senha → apenas para origem LOCAL -->
    <?php if (strtolower($user['origem'] ?? '') !== 'ad'): ?>
    <li class="nav-item d-flex align-items-center">
      <a href="javascript:void(0)"
         class="nav-link d-flex align-items-center py-0"
         onclick="openModalWithUrl('<?= base_url('alterar-senha') ?>?modal=1','Alterar Senha','md')">
        Alterar Senha
      </a>
    </li>
    <?php endif; ?>


		

		<!-- Sair -->
		<li class="nav-item d-flex align-items-center">
		  <a href="<?= base_url('logout') ?>" class="nav-link">Sair</a>
		</li>
	  <?php endif; ?>
	</ul>
	
	
	

	
  </nav>

  <?php if ($layout === 'sidebar'): ?>
  <!-- Sidebar -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">

	<a href="<?= base_url('') ?>" class="brand-link text-center" style="height:auto; padding: 15px 10px;">
	  <img src="<?= base_url('assets/img/logo_clara_vertical.png') ?>"
		   alt="Portal Integra"
		   class="brand-image"
		   style="height: 150px; width: auto; max-width: 100%; float: none !important;">
	</a>


    <div class="sidebar">
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
          <!-- Início -->
          <li class="nav-item">
            <a href="<?= base_url('home') ?>" class="nav-link<?= (is_current('home',$currentPath) || is_current('', $currentPath)) ? ' active':'' ?>">
              <i class="nav-icon fas fa-home"></i><p>Início</p>
            </a>
          </li>

          <?php if (\Security\Permission::level('users.manage') >= 1): ?>
          <?php $usersMenuOpen = starts_with_path('users', $currentPath); ?>
          <li class="nav-item has-treeview<?= $usersMenuOpen ? ' menu-open' : '' ?>">
            <a href="<?= base_url('users') ?>" class="nav-link<?= $usersMenuOpen ? ' active':'' ?>">
              <i class="nav-icon fas fa-users"></i>
              <p>Usuários<i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview"<?= $usersMenuOpen ? ' style="display:block;"' : '' ?>>
              <li class="nav-item">
                <a href="<?= base_url('users') ?>" class="nav-link<?= is_current('users',$currentPath) ? ' active':'' ?>">
                  <i class="far fa-circle nav-icon"></i><p>Listar</p>
                </a>
              </li>

            </ul>
          </li>
          <?php endif; ?>
		  
		<?php
			// Mostrar o grupo "Drilling" se o usuário enxergar qualquer subitem:
			// - Análise DI (nível >= 1)
			// - Tabela Getman (nível >= 2)
			$canSeeAnaliseDI   = (\Security\Permission::level('drilling.analise_di')   >= 1);
			$canSeeTabelaGetman= (\Security\Permission::level('drilling.tabela_getman')>= 2);
			$drillingMenuOpen  = starts_with_path('drilling', $currentPath);
			?>

			<?php if ($canSeeAnaliseDI || $canSeeTabelaGetman): ?>
			  <li class="nav-item has-treeview<?= $drillingMenuOpen ? ' menu-open' : '' ?>">
				<a href="<?= base_url('drilling/analise-di') ?>" class="nav-link<?= $drillingMenuOpen ? ' active' : '' ?>">
				  <i class="nav-icon fas fa-search"></i>
				  <p>Drilling<i class="right fas fa-angle-left"></i></p>
				</a>

				<ul class="nav nav-treeview"<?= $drillingMenuOpen ? ' style="display:block;"' : '' ?>>

				  <?php if ($canSeeAnaliseDI): ?>
				  <li class="nav-item">
					<a href="<?= base_url('drilling/analise-di') ?>" class="nav-link<?= is_current('drilling/analise-di', $currentPath) ? ' active' : '' ?>">
					  <i class="far fa-circle nav-icon"></i>
					  <p>Análise DI</p>
					</a>
				  </li>
				  <?php endif; ?>

				  <?php if ($canSeeTabelaGetman): ?>
				  <li class="nav-item">
					<a href="<?= base_url('drilling/tabela-getman') ?>" class="nav-link<?= is_current('drilling/tabela-getman', $currentPath) ? ' active' : '' ?>">
					  <i class="far fa-circle nav-icon"></i>
					  <p>Tabela Getman</p>
					</a>
				  </li>
				  <?php endif; ?>

				</ul>
			  </li>
			<?php endif; ?>
		  
		  
        </ul>
      </nav>
    </div>
  </aside>
  <?php endif; ?>

  <!-- Content -->
  <div class="content-wrapper">
    <?php if (isset($content) && is_callable($content)) { $content(); } ?>
  </div>

  <footer class="main-footer small text-center">
    <?= htmlspecialchars($appName) ?> &copy; <?= date('Y') ?>
  </footer>
</div>

<!-- Modal genérico -->
<div class="modal fade" id="genericModal" tabindex="-1" role="dialog" aria-labelledby="genericModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="genericModalLabel">Janela</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-3" id="genericModalBody">
        <div class="text-center text-muted small">Carregando...</div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary btn-sm" id="genericModalSubmitBtn" style="display:none;">Salvar</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3/dist/js/adminlte.min.js"></script>

<script>
(function(){
  /**
   * Abre o modal genérico carregando uma URL via GET.
   * size: 'md' (padrão) | 'lg' | 'xl'
   */
  window.openModalWithUrl = function(url, title, size) {
    var $modal   = $('#genericModal');
    var $dialog  = $modal.find('.modal-dialog');
    var $body    = $('#genericModalBody');
    var $title   = $('#genericModalLabel');
    var $submit  = $('#genericModalSubmitBtn');

    // Título e estado inicial
    $title.text(title || 'Janela');
    $body.html('<div class="text-center text-muted small">Carregando...</div>');
    $submit.hide().off('click');

    // Ajuste de tamanho seguro
    $dialog.removeClass('modal-sm modal-md modal-lg modal-xl');
    if (size === 'xl') {
      $dialog.addClass('modal-xl');
    } else if (size === 'lg') {
      $dialog.addClass('modal-lg');
    } else {
      $dialog.addClass('modal-md');
    }

    // Abre modal
    $modal.modal('show');

    // Carrega conteúdo
    $.get(url, function(html) {
      $body.html(html);

      var $form = $body.find('form#modalForm');
      if ($form.length) {
        $submit.show().off('click').on('click', function(){
          $form.trigger('submit');
        });
      } else {
        $submit.hide();
      }
    }).fail(function(xhr){
      $body.html('<div class="alert alert-danger">Falha ao carregar ('+xhr.status+').</div>');
      $submit.hide();
    });
  };

  // Ao fechar, reseta para modal-md
  $('#genericModal').on('hidden.bs.modal', function(){
    var $dialog = $(this).find('.modal-dialog');
    $dialog.removeClass('modal-sm modal-md modal-lg modal-xl').addClass('modal-md');
    $('#genericModalBody').html('');
    $('#genericModalSubmitBtn').hide().off('click');
  });
})();
</script>

<!-- (Opcional) Fallback JS para marcar ativo -->
<script>
$(function(){
  var cur = (location.pathname || '/').replace(/^\/+|\/+$/g,''); // 'users', 'users/create', etc.
  $('.nav-sidebar a.nav-link').each(function(){
    var href = $(this).attr('href') || '';
    var path = href.replace(/^\/+|\/+$/g,'');
    if (path && (path === cur)) {
      $(this).addClass('active');
      var treeItem = $(this).closest('.has-treeview');
      treeItem.addClass('menu-open');
      treeItem.children('a.nav-link').addClass('active');
    }
  });
});
</script>
</body>
</html>
``