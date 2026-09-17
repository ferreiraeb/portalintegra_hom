-- =============================================
-- Almoxarifado: permissões por categoria
-- Usa as tabelas existentes: permissions + user_permissions
-- Códigos: almoxarifado.cat.{categoria_id}  (nivel 0-3)
--          almoxarifado.manage               (nivel 0-2)
-- =============================================

-- ------------------------------------------------------------
-- 1. Permissão de gerenciamento das configurações (Categorias / Tipos de item)
-- ------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM dbo.permissions WHERE code = 'almoxarifado.manage')
    INSERT INTO dbo.permissions (code, name)
    VALUES ('almoxarifado.manage', N'Almoxarifado — Gerenciar configurações (categorias e tipos de item)');

-- ------------------------------------------------------------
-- 2. Uma linha em dbo.permissions para cada categoria existente
--    Código: almoxarifado.cat.<id>
-- ------------------------------------------------------------
INSERT INTO dbo.permissions (code, name)
SELECT
    'almoxarifado.cat.' + CAST(id AS NVARCHAR(20)),
    N'Almoxarifado — Categoria: ' + nome
FROM dbo.categorias
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.permissions p
    WHERE p.code = 'almoxarifado.cat.' + CAST(dbo.categorias.id AS NVARCHAR(20))
);

-- -- ------------------------------------------------------------
-- -- 3. Admin recebe nivel=3 (Gerenciar) em todas as categorias
-- --    e nivel=2 (Escrita) em almoxarifado.manage
-- -- ------------------------------------------------------------
--
-- -- almoxarifado.manage nivel=2
-- IF NOT EXISTS (
--     SELECT 1 FROM dbo.user_permissions up
--     JOIN dbo.users u ON u.id = up.user_id
--     WHERE u.login = 'admin' AND up.permission_code = 'almoxarifado.manage'
-- )
--     INSERT INTO dbo.user_permissions (user_id, permission_code, level)
--     SELECT id, 'almoxarifado.manage', 2 FROM dbo.users WHERE login = 'admin';
--
-- -- Nivel=3 para cada categoria
-- INSERT INTO dbo.user_permissions (user_id, permission_code, level)
-- SELECT
--     u.id,
--     'almoxarifado.cat.' + CAST(c.id AS NVARCHAR(20)),
--     3
-- FROM dbo.users u
-- CROSS JOIN dbo.categorias c
-- WHERE u.login = 'admin'
--   AND NOT EXISTS (
--       SELECT 1 FROM dbo.user_permissions up2
--       WHERE up2.user_id = u.id
--         AND up2.permission_code = 'almoxarifado.cat.' + CAST(c.id AS NVARCHAR(20))
--   );
