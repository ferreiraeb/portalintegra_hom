<?php
/**
 * Visão geral de permissões.
 * Vars: $codes  = [code => label], $byPerm = [code => [2 => [...users], 1 => [...users]]]
 * Acesso restrito a users.manage >= 2 (garantido pelo controller).
 */

$levelLabels = [
    2 => ['label' => 'Escrita',  'badge' => 'badge-danger',  'icon' => 'fas fa-pen',      'row' => 'table-danger'],
    1 => ['label' => 'Leitura',  'badge' => 'badge-primary', 'icon' => 'fas fa-eye',      'row' => 'table-primary'],
];

$originBadge = function(string $origem): string {
    return $origem === 'ad'
        ? '<span class="badge badge-info" title="Active Directory">AD</span>'
        : '<span class="badge badge-secondary" title="Usuário local">LOCAL</span>';
};
?>
<section class="content pt-3">
  <div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0"><i class="mr-2 text-muted"></i>Permissões</h4>
      <a href="<?= base_url('users') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Voltar para Usuários
      </a>
    </div>

    <div class="row">
      <?php foreach ($codes as $code => $label): ?>
        <?php
          $write  = $byPerm[$code][2] ?? [];
          $read   = $byPerm[$code][1] ?? [];
          $total  = count($write) + count($read);
        ?>
        <div class="col-12 col-xl-6 mb-4">
          <div class="card shadow-sm h-100">

            <!-- Card header -->
            <div class="card-header d-flex align-items-center justify-content-between py-2">
              <div>
                <i class="text-muted"></i>
                <strong><?= e($label) ?></strong>
                <br>
              </div>
              <div class="ml-auto flex-shrink-0 d-flex align-items-center gap-1">
                <span class="badge badge-danger mr-1" title="Escrita">
                  <i class="fas fa-pen mr-1"></i><?= count($write) ?>
                </span>
                <span class="badge badge-primary" title="Leitura">
                  <i class="fas fa-eye mr-1"></i><?= count($read) ?>
                </span>
              </div>
            </div>

            <!-- Card body -->
            <div class="card-body p-0">
              <?php if ($total === 0): ?>
                <div class="p-3 text-center text-muted small">
                  <i class="fas fa-lock mr-1"></i> Nenhum usuário com acesso a esta permissão.
                </div>
              <?php else: ?>
                <table class="table table-sm mb-0">
                  <thead class="thead-light">
                    <tr>
                      <th style="width:40%">Usuário</th>
                      <th style="width:20%">Login</th>
                      <th style="width:15%">Origem</th>
                      <th style="width:15%">Nível</th>
                      <th style="width:10%" class="text-right">Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ([2, 1] as $lvl): ?>
                      <?php $users = $byPerm[$code][$lvl] ?? []; ?>
                      <?php foreach ($users as $u): ?>
                        <?php $meta = $levelLabels[$lvl]; ?>
                        <tr class="<?= $meta['row'] ?>" style="opacity:0.9">
                          <td class="align-middle"><?= e($u['nome'] ?: $u['login']) ?></td>
                          <td class="align-middle text-monospace small"><?= e($u['login']) ?></td>
                          <td class="align-middle"><?= $originBadge($u['origem']) ?></td>
                          <td class="align-middle">
                            <span class="badge <?= $meta['badge'] ?>">
                              <i class="<?= $meta['icon'] ?> mr-1"></i><?= $meta['label'] ?>
                            </span>
                          </td>
                          <td class="align-middle text-right">
                              <button type="button"
                                      class="btn btn-xs btn-outline-secondary"
                                      title="Editar permissões de <?= e($u['login']) ?>"
                                      onclick="openModalWithUrl(
                                        '<?= base_url('users/permissions') ?>?id=<?= (int)$u['id'] ?>&modal=1',
                                        'Permissões — <?= htmlspecialchars($u['login'], ENT_QUOTES) ?>',
                                        'lg'
                                      )">
                                <i class="fas fa-edit"></i>
                              </button>
                            </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

          </div><!-- /.card -->
        </div><!-- /.col -->
      <?php endforeach; ?>
    </div><!-- /.row -->

  </div>
</section>

<script>
// Após salvar permissões no modal, recarrega a página para refletir mudanças
(function () {
  var $modal = $('#genericModal');
  if (!$modal.length) return;

  $modal.on('hidden.bs.modal', function () {
    // Só recarrega se o modal foi usado para editar permissões (URL contém 'users/permissions')
    var lastUrl = $modal.data('lastPermUrl');
    if (lastUrl) {
      $modal.removeData('lastPermUrl');
      location.reload();
    }
  });

  // Marca quando o modal de permissões foi aberto
  var _orig = window.openModalWithUrl;
  window.openModalWithUrl = function (url, title, size) {
    if (url && url.indexOf('users/permissions') !== -1) {
      $('#genericModal').data('lastPermUrl', url);
    }
    _orig.apply(this, arguments);
  };
})();
</script>




