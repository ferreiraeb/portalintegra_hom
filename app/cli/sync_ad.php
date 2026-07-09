#!/usr/bin/env php
<?php
require __DIR__ . '/../bootstrap.php';

use Services\LdapService;
use Database\Connection;

function sync_log(string $message, bool $stderr = false): void
{
	$line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
	if ($stderr) {
		fwrite(STDERR, $line);
	}
	echo $line;
}

try {
	sync_log('sync_ad iniciado');

	$ldap  = new LdapService($config['ldap']);
	$pdo   = Connection::get();
	$users = $ldap->searchActiveUsers();

	$ins = $pdo->prepare("
		INSERT INTO users (nome, login, email, origem, ad_dn, upn, department, title, company, office, phone, employeeNumber, userAccountControl, lockoutTime, is_active, created_at, last_sync_at)
		VALUES (:nome, :login, :email, 'ad', :dn, :upn, :department, :title, :company, :office, :phone, :employeeNumber, :uac, :lockoutTime, 1, GETDATE(), GETDATE())
	");

	$upd = $pdo->prepare("
		UPDATE users SET
		  nome=:nome, email=:email, ad_dn=:dn, upn=:upn, department=:department, title=:title,
		  company=:company, office=:office, phone=:phone, employeeNumber=:employeeNumber,
		  userAccountControl=:uac, lockoutTime=:lockoutTime, origem='ad', is_active=1,
		  last_sync_at=GETDATE(), updated_at=GETDATE()
		WHERE LOWER(login)=:login
	");

	$deact = $pdo->prepare("
		UPDATE users SET is_active = 0, last_sync_at = GETDATE(), updated_at = GETDATE()
		WHERE id = :id
	");

	$syncedLogins = [];
	$inserted     = 0;
	$updated      = 0;
	$deactivated  = 0;

	foreach ($users as $u) {
		$login = $u['sAMAccountName'] ?: ($u['upn'] ?: null);
		if (!$login) continue;
		$login = strtolower(trim($login));
		$syncedLogins[$login] = true;

		$st = $pdo->prepare("SELECT id FROM users WHERE LOWER(login)=:login");
		$st->execute([':login' => $login]);
		$exists = $st->fetch();

		$params = [
			':nome'           => trim($u['displayName'] ?: (($u['givenName'] ?? '') . ' ' . ($u['sn'] ?? ''))),
			':login'          => $login,
			':email'          => $u['mail'] ?? null,
			':dn'             => $u['dn'] ?? null,
			':upn'            => $u['upn'] ?? null,
			':department'     => $u['department'] ?? null,
			':title'          => $u['title'] ?? null,
			':company'        => $u['company'] ?? null,
			':office'         => $u['office'] ?? null,
			':phone'          => $u['phone'] ?? null,
			':employeeNumber' => $u['employeeNumber'] ?? null,
			':uac'            => $u['userAccountControl'] ?? null,
			':lockoutTime'    => $u['lockoutTime'] ?? null,
		];

		if ($exists) {
			$upd->execute($params);
			$updated++;
		} else {
			$ins->execute($params);
			$inserted++;
		}
	}

	$stAd = $pdo->query("SELECT id, login FROM users WHERE origem = 'ad' AND is_active = 1");
	while ($row = $stAd->fetch()) {
		$dbLogin = strtolower(trim($row['login'] ?? ''));
		if ($dbLogin !== '' && !isset($syncedLogins[$dbLogin])) {
			$deact->execute([':id' => (int)$row['id']]);
			$deactivated++;
		}
	}

	sync_log(sprintf(
		'Sync AD concluída: %d processado(s), %d inserido(s), %d atualizado(s), %d inativado(s).',
		count($users),
		$inserted,
		$updated,
		$deactivated
	));
	exit(0);
} catch (\Throwable $e) {
	sync_log('ERRO sync_ad: ' . $e->getMessage(), true);
	exit(1);
}
