-- Executar no banco: Portal_Integra (SQL Server)

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
CREATE TABLE dbo.users (
    id INT IDENTITY(1,1) PRIMARY KEY,
    nome NVARCHAR(200) NOT NULL,
    login NVARCHAR(150) NOT NULL UNIQUE, -- pode ser sAMAccountName ou login local
    email NVARCHAR(200) NULL,
    senha_hash NVARCHAR(255) NULL,       -- NULL p/ usuários AD
    origem NVARCHAR(20) NOT NULL DEFAULT 'local', -- 'local' | 'ad'
    ad_dn NVARCHAR(500) NULL,
    upn NVARCHAR(200) NULL,
    department NVARCHAR(200) NULL,
    title NVARCHAR(150) NULL,
    company NVARCHAR(200) NULL,
    office NVARCHAR(200) NULL,
    phone NVARCHAR(100) NULL,
    employeeNumber NVARCHAR(100) NULL,
    userAccountControl INT NULL,
    lockoutTime BIGINT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    last_sync_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NULL
);

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='permissions' AND xtype='U')
CREATE TABLE dbo.permissions (
    id INT IDENTITY(1,1) PRIMARY KEY,
    code NVARCHAR(150) NOT NULL UNIQUE, -- ex: 'users.manage'
    name NVARCHAR(200) NOT NULL         -- ex: 'Gestão de Usuários'
);

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='user_permissions' AND xtype='U')
CREATE TABLE dbo.user_permissions (
    id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NOT NULL,
    permission_code NVARCHAR(150) NOT NULL,
    level TINYINT NOT NULL DEFAULT 0,  -- 0=nenhum, 1=leitura, 2=leitura_gravacao
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES dbo.users(id),
    CONSTRAINT uq_user_perm UNIQUE (user_id, permission_code)
);

IF NOT EXISTS (SELECT * FROM permissions WHERE code='users.manage')
INSERT INTO permissions (code, name) VALUES ('users.manage', N'Gestão de Usuários');

-- Semente de usuário administrador local (altere senha após o 1º acesso)
IF NOT EXISTS (SELECT * FROM users WHERE login='admin')
INSERT INTO users (nome, login, email, senha_hash, origem, is_active)
VALUES (N'Administrador', 'admin', 'admin@localhost', 
        '$2y$10$Qx5q9hL1QTrJr2L2o7rWEO4wz4fH6R2p7M0Hf8cD0JH3rU2b3Z0i2', -- senha: Admin@123 (trocar)
        'local', 1);

-- Dá permissão total na gestão de usuários ao admin
IF NOT EXISTS (SELECT 1 FROM user_permissions up
               JOIN users u ON u.id = up.user_id
               WHERE u.login='admin' AND up.permission_code='users.manage')
INSERT INTO user_permissions (user_id, permission_code, level)
SELECT id, 'users.manage', 2 FROM users WHERE login='admin';



SELECT TOP 10 id, nome, login, origem, is_active, created_at
FROM dbo.users
WHERE login = 'admin';



UPDATE dbo.users
SET senha_hash = '$2y$10$DaMbNUDKhQx/Dmdxz8bq/e4CiRhz6Y5UtHofgWVK3IdFcRhS4qTQW', -- Admin@123
    origem = 'local',
    is_active = 1,
    updated_at = GETDATE()
WHERE login = 'admin';

