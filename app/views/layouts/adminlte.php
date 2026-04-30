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
    .thead-filter th { background: #f8f9fa; }
    .thead-filter input, .thead-filter select { font-size: .8rem; }
	


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
              <?php if (\Security\Permission::level('users.manage') >= 2): ?>
              <li class="nav-item">
                <a href="<?= base_url('users/permissions-overview') ?>" class="nav-link<?= is_current('users/permissions-overview',$currentPath) ? ' active':'' ?>">
                  <i class="far fa-circle nav-icon"></i><p>Permissões</p>
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>
		  
		<?php
			// Mostrar o grupo "Drilling" se o usuário enxergar qualquer subitem:
			// - Análise DI (nível >= 1)
			// - Tabela Getman (nível >= 2)
			$canSeeAnaliseDI   = (\Security\Permission::level('drilling.analise_di')   >= 1);
			$canBoart  = (\Security\Permission::level('drilling.tabela_boart') >= 2);
			$canGetman = (\Security\Permission::level('drilling.tabela_getman') >= 2);
			//$canSeeTabelaGetman= (\Security\Permission::level('drilling.tabela_getman')>= 2);
			$miningMenuOpen  = starts_with_path('mining', $currentPath);
			


			?>

			
			<?php if ($canSeeAnaliseDI || $canBoart || $canGetman): ?>
			  <li class="nav-item has-treeview<?= $miningMenuOpen ? ' menu-open' : '' ?>">
				<a href="<?= base_url('mining') ?>" class="nav-link<?= $miningMenuOpen ? ' active' : '' ?>">
				  <i class="nav-icon fas fa-search"></i>
				  <p>Mining<i class="right fas fa-angle-left"></i></p>
				</a>
				<ul class="nav nav-treeview"<?= $miningMenuOpen ? ' style="display:block;"' : '' ?>>

				  <?php if ($canSeeAnaliseDI): ?>
				  <li class="nav-item">
					<a href="<?= base_url('mining/analise-di') ?>" class="nav-link<?= is_current('mining/analise-di', $currentPath) ? ' active' : '' ?>">
					  <i class="far fa-circle nav-icon"></i><p>Análise DI</p>
					</a>
				  </li>
				  <?php endif; ?>

				  <?php if ($canBoart): ?>
				  <li class="nav-item">
					<a href="<?= base_url('mining/tabela-boart') ?>" class="nav-link<?= is_current('mining/tabela-boart', $currentPath) ? ' active' : '' ?>">
					  <i class="far fa-circle nav-icon"></i><p>Tabela Boart LongYear</p>
					</a>
				  </li>
				  <?php endif; ?>

				  <?php if ($canGetman): ?>
				  <li class="nav-item">
					<a href="<?= base_url('mining/tabela-getman') ?>" class="nav-link<?= is_current('mining/tabela-getman', $currentPath) ? ' active' : '' ?>">
					  <i class="far fa-circle nav-icon"></i><p>Tabela Getman</p>
					</a>
				  </li>
				  <?php endif; ?>

				</ul>
			  </li>
			<?php endif; ?>
			
			
			<?php 
						
				$canMassey = (\Security\Permission::level('agro.tabela_massey') >= 2); 
				$agroMenuOpen      = starts_with_path('agro', $currentPath);
				
			?>
			<?php if ($canMassey): ?>
			<li class="nav-item has-treeview<?= $agroMenuOpen ? ' menu-open' : '' ?>">
			  <a href="<?= base_url('agro/tabela-massey') ?>" class="nav-link<?= $agroMenuOpen ? ' active' : '' ?>">
				<i class="nav-icon fas fa-tractor"></i>
				<p>Agro<i class="right fas fa-angle-left"></i></p>
			  </a>
			  <ul class="nav nav-treeview"<?= $agroMenuOpen ? ' style="display:block;"' : '' ?>>
				<li class="nav-item">
				  <a href="<?= base_url('agro/tabela-massey') ?>" class="nav-link<?= is_current('agro/tabela-massey', $currentPath) ? ' active' : '' ?>">
					<i class="far fa-circle nav-icon"></i><p>Tabela Massey Ferguson</p>
				  </a>
				</li>
			  </ul>
			</li>
			<?php endif; ?>
			
			

		 
		 
		<!-- HR — Colaboradores -->
		<?php $canRhColabs = (\Security\Permission::level('hr.colaboradores') >= 1); ?>
		<?php if ($canRhColabs): ?>
		<?php $rhMenuOpen = starts_with_path('hr', $currentPath); ?>
		<li class="nav-item has-treeview<?= $rhMenuOpen ? ' menu-open' : '' ?>">
		  <a href="<?= base_url('hr/colaboradores') ?>" class="nav-link<?= $rhMenuOpen ? ' active' : '' ?>">
			<i class="nav-icon fas fa-id-badge"></i>
			<p>Colaboradores<i class="right fas fa-angle-left"></i></p>
		  </a>
		  <ul class="nav nav-treeview"<?= $rhMenuOpen ? ' style="display:block;"' : '' ?>>
			<li class="nav-item">
			  <a href="<?= base_url('hr/colaboradores') ?>" class="nav-link<?= is_current('hr/colaboradores', $currentPath) ? ' active' : '' ?>">
				<i class="far fa-circle nav-icon"></i><p>Listar</p>
			  </a>
			</li>
			<li class="nav-item">
			  <a href="<?= base_url('hr/organograma') ?>" class="nav-link<?= is_current('hr/organograma', $currentPath) ? ' active' : '' ?>">
				<i class="far fa-circle nav-icon"></i><p>Organograma</p>
			  </a>
			</li>
		  </ul>
		</li>
		<?php endif; ?>

		<!-- LINK PARA ACESSAR A DOCUMENTAÇÃO -->
		<!-- OCULTANDO O MENU DE DOCUMENTOS
		<?php if (\Security\Permission::level('users.manage') >= 1): ?>
		<li class="nav-item mt-3">
		  <a href="<?= base_url('docs') ?>"  class="nav-link<?= is_current('docs', $currentPath) ? ' active' : '' ?>">
			<i class="nav-icon fas fa-book"></i>
			<p>Documentação</p>
		  </a>
		</li>
		<?php endif; ?>
		-->

		 
		  
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
<?php include __DIR__ . '/../components/toast.php'; ?>
<?php include __DIR__ . '/../components/progress_modal.php'; ?>
<!-- ── Massey: polling global de atualização de preços ───────────────────
     Funciona em qualquer página enquanto o worker CLI roda em background.
     Usa PortalToast (views/components/toast.php) + notificação nativa.
──────────────────────────────────────────────────────────────────────── -->
<script>
(function () {
  'use strict';

  var LS_KEY      = 'pi_massey_update';
  var POLL_MS     = 5000;
  var STALE_MS    = 2 * 60 * 60 * 1000;
  var STALE_TICKS = 12; // 12 × 5 s = 60 s sem progresso

  var raw = localStorage.getItem(LS_KEY);
  if (!raw) return;

  var pending;
  try { pending = JSON.parse(raw); } catch (_) { localStorage.removeItem(LS_KEY); return; }
  if (!pending || !pending.statusUrl) { localStorage.removeItem(LS_KEY); return; }

  if (pending.startedAt && (Date.now() - pending.startedAt) > STALE_MS) {
    localStorage.removeItem(LS_KEY); return;
  }

  // A página Massey tem seu próprio toast — não duplicar
  if (window.masseyPageActive) return;

  // Pede permissão de notificação nativa (silencioso se já decidido)
  if (window.Notification && Notification.permission === 'default') {
    Notification.requestPermission();
  }

  function nativeNotify(title, body) {
    if (window.Notification && Notification.permission === 'granted') {
      try { new Notification(title, { body: body, icon: '<?= base_url('assets/favicon/favicon-32.png') ?>' }); }
      catch (_) {}
    }
  }

  var lastUpdatedAt = null;
  var staleTicks    = 0;

  function tick() {
    if (window.masseyPageActive) {
      PortalToast.hide();
      return;
    }

    fetch(pending.statusUrl, { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (j) {
        if (!j.ok || !j.data) { schedule(); return; }
        var step = j.data.Step || '';

        if (step === 'UPDATE_DONE') {
          localStorage.removeItem(LS_KEY);
          PortalToast.show('success', 'Massey — Preços Atualizados',
            (j.data.Message || 'Concluído.') +
            '<br><small class="text-muted">Pode continuar normalmente.</small>',
            30000);
          nativeNotify('Portal Integra — Massey Ferguson',
            j.data.Message || 'Preços atualizados com sucesso.');
          return;
        }
        if (step === 'UPDATE_ERROR') {
          localStorage.removeItem(LS_KEY);
          PortalToast.show('danger', 'Massey — Erro',
            (j.data.Message || 'Ocorreu um erro.') +
            '<br><small class="text-muted">Verifique e tente novamente.</small>');
          nativeNotify('Portal Integra — Erro',
            j.data.Message || 'Erro na atualização de preços.');
          return;
        }
        if (step === 'UPDATE_RUNNING' || step === 'UPDATE_QUEUED') {
          if (j.data.UpdatedAt === lastUpdatedAt) {
            if (++staleTicks >= STALE_TICKS) {
              localStorage.removeItem(LS_KEY);
              PortalToast.show('danger', 'Massey — Interrompido',
                'Sem progresso detectado (60 s). A operação pode ter sido interrompida.');
              return;
            }
          } else {
            staleTicks    = 0;
            lastUpdatedAt = j.data.UpdatedAt;
          }
          var done = parseInt(j.data.Done,  10) || 0;
          var tot  = parseInt(j.data.Total, 10) || 0;
          var pct  = tot > 0 ? Math.round(done / tot * 100) : 0;
          PortalToast.update(PortalToast.progressHtml(pct, j.data.Message || 'Processando...'));
          schedule();
        } else {
          localStorage.removeItem(LS_KEY);
          PortalToast.hide();
        }
      })
      .catch(function () { schedule(); });
  }

  function schedule() { setTimeout(tick, POLL_MS); }

  // Mostra o toast imediatamente (com 0%) e começa a pollar após 1 s
  PortalToast.show('info', 'Massey — Atualizando',
    PortalToast.progressHtml(0, 'Aguardando progresso...'));
  setTimeout(tick, 1000);
})();
</script>
</body>
</html>
