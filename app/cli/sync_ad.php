#!/usr/bin/env php
<?php
require __DIR__ . '/../bootstrap.php';

use Services\LdapService;
use Database\Connection;

$ldap = new LdapService($config['ldap']);
$pdo  = Connection::get();

$users = $ldap->searchActiveUsers();
$now = date('Y-m-d H:i:s');

$ins = $pdo->prepare("
INSERT INTO users (nome, login, email, origem, ad_dn, upn, department, title, company, office, phone, employeeNumber, userAccountControl, lockoutTime, is_active, created_at, last_sync_at)
VALUES (:nome, :login, :email, 'ad', :dn, :upn, :department, :title, :company, :office, :phone, :employeeNumber, :uac, :lockoutTime, 1, GETDATE(), GETDATE())
");

$upd = $pdo->prepare("
UPDATE users SET 
  nome=:nome, email=:email, ad_dn=:dn, upn=:upn, department=:department, title=:title,
  company=:company, office=:office, phone=:phone, employeeNumber=:employeeNumber,
  userAccountControl=:uac, lockoutTime=:lockoutTime, origem='ad', last_sync_at=GETDATE(), updated_at=GETDATE()
WHERE login=:login
");

foreach ($users as $u) {
    $login = $u['sAMAccountName'] ?: ($u['upn'] ?: null);
    if (!$login) continue;

    $st = $pdo->prepare("SELECT id FROM users WHERE login=:login");
    $st->execute([':login'=>$login]);
    $exists = $st->fetch();

    $params = [
        ':nome' => $u['displayName'] ?: ($u['givenName'].' '.$u['sn']),
        ':login'=> $login,
        ':email'=> $u['mail'] ?? null,
        ':dn'   => $u['dn'] ?? null,
        ':upn'  => $u['upn'] ?? null,
        ':department'=> $u['department'] ?? null,
        ':title'=> $u['title'] ?? null,
        ':company'=> $u['company'] ?? null,
        ':office'=> $u['office'] ?? null,
        ':phone'=> $u['phone'] ?? null,
        ':employeeNumber'=> $u['employeeNumber'] ?? null,
        ':uac' => $u['userAccountControl'] ?? null,
        ':lockoutTime'=> $u['lockoutTime'] ?? null
    ];

    if ($exists) $upd->execute($params);
    else $ins->execute($params);
}

echo "Sync AD concluída: ".count($users)." usuários processados.\n";
?>