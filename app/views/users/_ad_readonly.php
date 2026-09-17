<?php
// Espera $user vindo do controller
function fmt_bool($v) { return ((int)$v) ? 'Sim' : 'Não'; }
function fmt_datetime($dt) { return $dt ? date('d/m/Y H:i', strtotime($dt)) : '-'; }

// Mapeamento de campos para exibição (label => valor)
$rows = [
  'Nome'                  => $user['nome'] ?? '',
  'Login (sAMAccountName)'=> $user['login'] ?? '',
  'UPN'                   => $user['upn'] ?? '',
  'E-mail'                => $user['email'] ?? '',
  'Departamento'          => $user['department'] ?? '',
  'Cargo (Title)'         => $user['title'] ?? '',
  'Empresa'               => $user['company'] ?? '',
  'Escritório'            => $user['office'] ?? '',
  'Telefone'              => $user['phone'] ?? '',
  'Matrícula (employeeNumber)' => $user['employeeNumber'] ?? '',
  'UserAccountControl'    => isset($user['userAccountControl']) ? (string)(int)$user['userAccountControl'] : '-',
  'LockoutTime'           => isset($user['lockoutTime']) ? (string)(int)$user['lockoutTime'] : '-',
  'Ativo (base local)'    => fmt_bool($user['is_active'] ?? 0),
  'DN (distinguishedName)'=> $user['ad_dn'] ?? '',
  'Última Sincronização'  => fmt_datetime($user['last_sync_at'] ?? null),
];
?>
<div class="container-fluid">
  <div class="mb-3">
    <h5 class="mb-0"><?= htmlspecialchars($user['nome'] ?? $user['login']) ?></h5>
    <small class="text-muted">Origem: AD</small>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-striped">
      <thead class="thead-light">
        <tr>
          <th style="width: 260px;">Campo</th>
          <th>Valor</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $label => $value): ?>
        <tr>
          <td class="font-weight-bold align-middle"><?= htmlspecialchars($label) ?></td>
          <td class="align-middle">
            <?php
              // Para DN e campos muito longos, usamos uma <div> mono com quebra
              $isLongField = in_array($label, ['DN (distinguishedName)', 'UPN']);
              if ($isLongField):
            ?>
              <div class="code-wrap"><?= htmlspecialchars($value !== '' ? $value : '-') ?></div>
            <?php else: ?>
              <?= htmlspecialchars($value !== '' ? $value : '-') ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="alert alert-info mt-2 mb-0 small">
    Estes dados são sincronizados do Active Directory e exibidos aqui em modo somente leitura.
    Alterações de senha, ativação/desativação e dados do perfil devem ser feitas no AD.
  </div>
</div>