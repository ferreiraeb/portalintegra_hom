<?php
return [
    'app' => [
        'name' => 'Portal Integra',
        'env'  => 'development', // production | staging | development
        'debug'=> true,
        'timezone' => 'America/Sao_Paulo',
        // layout: 'sidebar' ou 'topbar'
        'menu_layout' => 'sidebar',
        'base_url' => '/', // ajuste se publicar sob subpasta
        'session_name' => 'portal_integra_sid',
        'csrf_key' => 'troque-esta-chave-com-32+caracteres',
    ],
	/*
    'db' => [
        'driver' => 'sqlsrv',
        'server' => '10.16.96.10,3310',
        'database' => 'Portal_Integra',
        'username' => 'portalintegra',
        'password' => 'Val@Portal#Integra%2026', // TROCAR

	    // >>> Novos parâmetros (ODBC 18)
    	// Em laboratório com certificado self-signed:
    	'encrypt' => true,
    	'trust_server_certificate' => true,
		
        'options'  => [
            // PDO::SQLSRV_ATTR_DIRECT_QUERY, PDO::ATTR_ERRMODE, etc. serão definidos no Connection.php
        ],
        // Placeholders já previstos para Oracle/MySQL nas próximas fases
        'oracle' => [],
        'mysql'  => [],
    ],*/
	'db' => [
		'driver'   => 'sqlsrv',
		'server'   => '10.16.96.10,3310', //HOMOLOGAÇÃO
		'database' => 'Portal_Integra',
		'username' => 'portalintegra',
		'password' => 'Val@Portal#Integra%2026',
		'options'  => [],

		// ODBC Driver 18 força criptografia por padrão.
		// O servidor usa certificado self-signed → precisamos confiar nele.
		'encrypt'                  => false,
		'trust_server_certificate' => false,

		// 👇 NOVO: informe o nome da outra base no MESMO servidor
		'dealernet_database' => 'GrupoValence_HML',
	],
    'ldap' => [
        'enabled'      => true,
        'ldap_uri'     => 'ldap://10.14.237.13',
        'ldap_port'    => 389,
        'use_starttls' => false,
        'bind_dn'      => 'conexao.ldap@valence.ad',
        'bind_password'=> 'V@lence%Ldap#@', // TROCAR
        'base_dn'      => 'DC=Valence,DC=ad',
        'user_filter'  => '(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))',
        'attributes'   => [
            'distinguishedName','displayName','givenName','sn','mail','department','title','company',
            'manager','directReports','physicalDeliveryOfficeName','telephoneNumber','sAMAccountName',
            'userPrincipalName','employeeNumber','userAccountControl','lockoutTime'
        ],
        // Para autenticar via formulário, montamos UPN domain: username@valence.ad
        'user_upn_suffix' => '@valence.ad',
        // Frequência da sincronização (usaremos cron a cada hora)
        'sync_interval_minutes' => 60
    ],
    'auth' => [
        'password_algo' => PASSWORD_BCRYPT,
        'password_options' => ['cost' => 10],
        // Permissão default ao importar do AD: nenhum (0), admin define depois
        'default_permission_level' => 0
    ],
];

?>
