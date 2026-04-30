<?php
/** @var \Support\ListTable $lt */
$permUsers = \Security\Permission::level('users.manage');
?>
<section class="content pt-3">
  <div class="container-fluid">
    <form method="get" action="<?= base_url('users') ?>" id="frmUsersFilter">
      <input type="hidden" name="sort"     value="<?= e($lt->getSort()) ?>">
      <input type="hidden" name="dir"      value="<?= e($lt->getDir()) ?>">
      <input type="hidden" name="per_page" value="<?= $lt->getPerPage() ?>">
      <input type="hidden" name="page"     value="1">

      <div class="card">

        <!-- Cabeçalho -->
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title mb-0 mr-auto">Usuários</h3>

          <button type="submit" class="btn btn-sm btn-secondary ml-2">
            <i class="fas fa-search mr-1"></i>Filtrar
          </button>
          <?php if ($lt->hasFilters()): ?>
            <a href="<?= base_url('users') ?>" class="btn btn-sm btn-outline-secondary ml-2" title="Limpar filtros">
              <i class="fas fa-times mr-1"></i>Limpar
            </a>
          <?php endif; ?>

          <?php if ($permUsers >= 2): ?>
            <a href="<?= base_url('users') ?>/create" class="btn btn-sm btn-primary ml-2">
              <i class="fas fa-plus mr-1"></i>Novo
            </a>
            <button type="button"
                    id="btnSyncAd"
                    class="btn btn-sm btn-outline-secondary ml-2"
                    data-csrf="<?= csrf_token() ?>"
                    data-url="<?= base_url('users/sync-ad') ?>">
              <i class="fas fa-sync-alt mr-1"></i>Sincronizar AD
            </button>
          <?php endif; ?>
        </div>

        <!-- Tabela -->
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <!-- Linha 1: cabeçalhos ordenáveis -->
              <tr>
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <th class="<?= $col['th_class'] ?? '' ?>"><?= $lt->sortLink($key) ?></th>
                <?php endforeach; ?>
              </tr>
              <!-- Linha 2: filtros por coluna -->
              <tr class="thead-filter">
                <?php foreach ($lt->cols() as $key => $col): ?>
                  <th class="p-1"><?= $lt->filterInput($key) ?></th>
                <?php endforeach; ?>
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
                <td>
                  <?php if ($u['is_active']): ?>
                    <span class="badge badge-success">Ativo</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Inativo</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <?php if ($u['origem'] === 'local'): ?>
                    <?php if ($permUsers >= 2): ?>
                      <a href="<?= base_url('users/edit?id='.(int)$u['id']) ?>"
                         class="btn btn-xs btn-outline-primary">Editar</a>
                      <button type="button"
                              class="btn btn-xs btn-outline-danger"
                              onclick="confirmDeleteUser(<?= (int)$u['id'] ?>)">Excluir</button>
                    <?php else: ?>
                      <span class="text-muted small">Somente leitura</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <button type="button"
                            class="btn btn-xs btn-outline-secondary"
                            onclick="openModalWithUrl(
                              '<?= base_url('users/view-ad') ?>?id=<?= (int)$u['id'] ?>&modal=1',
                              'Dados do AD — <?= htmlspecialchars($u['login'], ENT_QUOTES) ?>',
                              'xl'
                            )">Ver AD</button>
                  <?php endif; ?>
                  <?php if ($permUsers >= 2): ?>
                    <button type="button"
                            class="btn btn-xs btn-outline-info ml-1"
                            onclick="openModalWithUrl(
                              '<?= base_url('users/permissions') ?>?id=<?= (int)$u['id'] ?>&modal=1',
                              'Permissões — <?= htmlspecialchars($u['login'], ENT_QUOTES) ?>',
                              'lg'
                            )">Permissões</button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?= $lt->paginationFooter($total, $from, $to, 'usuários', $lt->hasFilters()) ?>

      </div><!-- /.card -->
    </form><!-- /#frmUsersFilter -->
  </div>
</section>
<script>
(function(){
  var f=document.getElementById('frmUsersFilter');
  if(!f)return;
  f.addEventListener('submit',function(){
    Array.prototype.forEach.call(f.elements,function(el){
      if((el.tagName==='INPUT'||el.tagName==='SELECT')&&el.value===''&&el.type!=='hidden')el.disabled=true;
    });
  });
})();
</script>

<form id="frmDeleteUser" action="<?= base_url('users/delete') ?>" method="post" style="display:none">
  <?php csrf_field(); ?>
  <input type="hidden" name="id" id="deleteUserId" value="">
</form>

<script>
function confirmDeleteUser(id) {
  if (!confirm('Excluir usuário local?')) return;
  document.getElementById('deleteUserId').value = id;
  document.getElementById('frmDeleteUser').submit();
}
</script>

<script>
(function () {
  var btn = document.getElementById('btnSyncAd');
  if (!btn) return;

  btn.addEventListener('click', function () {
    var url  = btn.getAttribute('data-url');
    var csrf = btn.getAttribute('data-csrf');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sincronizando...';

    var old = document.getElementById('syncAdAlert');
    if (old) old.parentNode.removeChild(old);

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'csrf=' + encodeURIComponent(csrf)
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      showAlert(resp.ok ? 'alert-success' : 'alert-danger', resp.message);
      if (resp.ok) setTimeout(function () { location.reload(); }, 1500);
    })
    .catch(function (e) { showAlert('alert-danger', 'Erro na requisição: ' + e.message); })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Sincronizar AD';
    });
  });

  function showAlert(cls, msg) {
    var div = document.createElement('div');
    div.id = 'syncAdAlert';
    div.className = 'alert ' + cls + ' alert-dismissible fade show mx-3 mt-3';
    div.setAttribute('role', 'alert');
    div.innerHTML = msg
      + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>';
    var container = document.querySelector('.content.pt-3 .container-fluid');
    if (container) container.insertBefore(div, container.firstChild);
  }
})();
</script>

