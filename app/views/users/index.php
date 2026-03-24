<?php
// Valores vindos do controller (com fallback para GET)
$q       = isset($q) ? $q : ($_GET['q'] ?? '');
$origem  = isset($origem) ? $origem : ($_GET['origem'] ?? '');
$page    = isset($page) ? (int)$page : (int)($_GET['page'] ?? 1);
$perPage = isset($perPage) ? (int)$perPage : (int)($_GET['per_page'] ?? 25);
$sort    = isset($sort) ? $sort : ($_GET['sort'] ?? 'nome');
$dir     = isset($dir) ? strtolower($dir) : strtolower($_GET['dir'] ?? 'asc');
$total   = isset($total) ? (int)$total : 0;

if ($page < 1) $page = 1;
$allowedPer = [10,25,50,100];
if (!in_array($perPage, $allowedPer, true)) $perPage = 25;

$from = $total ? (($page - 1) * $perPage + 1) : 0;
$to   = min($total, $page * $perPage);

$permUsers = \Security\Permission::level('users.manage'); // cacheado na sessão

// Helper para montar links preservando query string (com overrides)
function linkQS(array $overrides = []) {
  $params = array_merge($_GET, $overrides);
  $qs = count($params) ? ('?' . http_build_query($params)) : '';
  return base_url('users') . $qs;
}

// Helper de ordenação por coluna (gera link seguro)
function sortLink($col, $label, $cur, $dir) {
  $newDir = ($cur === $col && strtolower($dir) === 'asc') ? 'desc' : 'asc';
  $qs = array_merge($_GET, ['sort'=>$col, 'dir'=>$newDir, 'page'=>1]);
  $icon = '';
  if ($cur === $col) $icon = strtolower($dir) === 'asc' ? ' &uarr;' : ' &darr;';
  return '<a href="'. base_url('users') .'?'. htmlspecialchars(http_build_query($qs)) .'">' . htmlspecialchars($label) . $icon . '</a>';
}
?>
<section class="content pt-3">
  <div class="container-fluid">
    <div class="card">

      <!-- Cabeçalho com filtros e botão Novo -->
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title mb-2 mb-sm-0">Usuários</h3>

        <!-- FORM DE FILTRO (GET) -->
        <form action="<?= base_url('users') ?>" method="get" class="form-inline">
          <input type="text"
                 name="q"
                 class="form-control form-control-sm mr-2"
                 placeholder="Buscar... (use * para 'contém')"
                 value="<?= htmlspecialchars($q) ?>">

          <select name="origem" class="form-control form-control-sm mr-2">
            <option value="">Todas as origens</option>
            <option value="local" <?= $origem==='local' ? 'selected' : '' ?>>Local</option>
            <option value="ad"    <?= $origem==='ad'    ? 'selected' : '' ?>>AD</option>
          </select>

          <select name="per_page" class="form-control form-control-sm mr-2">
            <?php foreach ([10,25,50,100] as $pp): ?>
              <option value="<?= $pp ?>" <?= ($perPage===$pp) ? 'selected':'' ?>><?= $pp ?>/página</option>
            <?php endforeach; ?>
          </select>

          <!-- preserva ordenação atual -->
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <input type="hidden" name="dir"  value="<?= htmlspecialchars($dir) ?>">

          <button class="btn btn-sm btn-secondary">Aplicar</button>
        </form>

        <?php if ($permUsers >= 2): ?>
          <a href="<?= base_url('users/create') ?>" class="btn btn-sm btn-primary ml-2">Novo</a>
        <?php endif; ?>
      </div>

      <!-- Tabela -->
      <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th><?= sortLink('nome','Nome',$sort,$dir) ?></th>
              <th><?= sortLink('login','Login',$sort,$dir) ?></th>
              <th><?= sortLink('origem','Origem',$sort,$dir) ?></th>
              <th><?= sortLink('email','E-mail',$sort,$dir) ?></th>
              <th><?= sortLink('is_active','Status',$sort,$dir) ?></th>
              <th class="text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= e($u['nome'] ?? '') ?></td>
              <td><?= e($u['login'] ?? '') ?></td>
              <td>
                <?php if ($u['origem'] === 'ad'): ?>
                  <span class="badge badge-info">AD</span>
                <?php else: ?>
                  <span class="badge badge-secondary">LOCAL</span>
                <?php endif; ?>
              </td>
              
              <td><?= e($u['email'] ?? '') ?></td>
              <td><?= $u['is_active'] ? 'Ativo' : 'Inativo' ?></td>

              <td class="text-right">
                <?php if ($u['origem'] === 'local'): ?>
                  <?php if ($permUsers >= 2): ?>
                    <!-- Editar -->
                    <a href="<?= base_url('users/edit?id='.(int)$u['id']) ?>"
                       class="btn btn-xs btn-outline-primary">Editar</a>

                    <!-- Excluir -->
                    <form action="<?= base_url('users/delete') ?>"
                          method="post"
                          style="display:inline"
                          onsubmit="return confirm('Excluir usuário local?');">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="btn btn-xs btn-outline-danger">Excluir</button>
                    </form>
                  <?php else: ?>
                    <!-- Leitura: sem editar/excluir -->
                    <span class="text-muted small d-inline-block">Somente leitura</span>
                  <?php endif; ?>

                <?php else: ?>
                  <!-- AD: sem editar/excluir; mostra ver dados do AD -->
                  <button type="button"
                          class="btn btn-xs btn-outline-secondary"
                          onclick="openModalWithUrl(
                            '<?= base_url('users/view-ad') ?>?id=<?= (int)$u['id'] ?>&modal=1',
                            'Dados do AD — <?= htmlspecialchars($u['login'], ENT_QUOTES, 'UTF-8') ?>',
                            'xl'
                          )">
                    Ver dados do AD
                  </button>
                <?php endif; ?>

                <?php if ($permUsers >= 2): ?>
                  <!-- Botão Permissões (para Local e AD) -->
                  <button type="button"
                          class="btn btn-xs btn-outline-info ml-1"
                          onclick="openModalWithUrl(
                            '<?= base_url('users/permissions') ?>?id=<?= (int)$u['id'] ?>&modal=1',
                            'Permissões — <?= htmlspecialchars($u['login'], ENT_QUOTES, 'UTF-8') ?>',
                            'lg'
                          )">
                    Permissões
                  </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center text-muted">Nenhum registro encontrado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="small text-muted">
          <?php if ($total > 0): ?>
            Mostrando <?= $from ?>–<?= $to ?> de <?= $total ?>
          <?php else: ?>
            Nenhum registro
          <?php endif; ?>
        </div>
        <?php
          $lastPage = max(1, (int)ceil($total / $perPage));
          $prev = max(1, $page - 1);
          $next = min($lastPage, $page + 1);
        ?>
        <div>
          <a class="btn btn-sm btn-outline-secondary <?= $page<=1?'disabled':'' ?>"
             href="<?= linkQS(['page'=>$prev]) ?>">« Anterior</a>
          <span class="mx-2">Página <?= $page ?> / <?= $lastPage ?></span>
          <a class="btn btn-sm btn-outline-secondary <?= $page>=$lastPage?'disabled':'' ?>"
             href="<?= linkQS(['page'=>$next]) ?>">Próxima »</a>
        </div>
      </div>

    </div>
  </div>

</section>
