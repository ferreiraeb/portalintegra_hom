-- =============================================
-- Seed: Categorias e Tipos de Item
-- Almoxarifado — dados iniciais conforme requisitos
-- =============================================
-- Idempotente: só insere o que ainda não existir pelo nome.
-- =============================================

DECLARE @cat_ti  INT,
        @cat_op  INT,
        @cat_rh  INT;

-- ------------------------------------------------------------
-- 1. CATEGORIAS
-- ------------------------------------------------------------

IF NOT EXISTS (SELECT 1 FROM dbo.categorias WHERE nome = 'TI')
    INSERT INTO dbo.categorias (nome, descricao, ativo)
    VALUES (N'TI', N'Equipamentos, linhas e periféricos corporativos da Tecnologia da Informação', 1);

SET @cat_ti = (SELECT id FROM dbo.categorias WHERE nome = 'TI');

IF NOT EXISTS (SELECT 1 FROM dbo.categorias WHERE nome = 'Operação')
    INSERT INTO dbo.categorias (nome, descricao, ativo)
    VALUES (N'Operação', N'Veículos, ferramentas e equipamentos de campo', 1);

SET @cat_op = (SELECT id FROM dbo.categorias WHERE nome = 'Operação');

IF NOT EXISTS (SELECT 1 FROM dbo.categorias WHERE nome = 'RH')
    INSERT INTO dbo.categorias (nome, descricao, ativo)
    VALUES (N'RH', N'Cartões benefício, EPIs e uniformes', 1);

SET @cat_rh = (SELECT id FROM dbo.categorias WHERE nome = 'RH');

-- ------------------------------------------------------------
-- 2. TIPOS DE ITEM — TI
--    is_determinado = 1  → item rastreável com tabela de detalhe
--    is_determinado = 0  → item genérico (sem identificação individual)
-- ------------------------------------------------------------

-- Notebook  (rastreável — item_equipamento_ti)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Notebook')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Notebook', N'Notebooks corporativos rastreados por número de série', 1, N'item_equipamento_ti', 1);

-- Desktop  (rastreável — item_equipamento_ti)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Desktop')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Desktop', N'Computadores de mesa e workstations rastreados por número de série', 1, N'item_equipamento_ti', 1);

-- Monitor  (rastreável — item_equipamento_ti)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Monitor')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Monitor', N'Monitores rastreados por número de série / etiqueta', 1, N'item_equipamento_ti', 1);

-- Celular corporativo  (rastreável — item_equipamento_ti)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Celular corporativo')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Celular corporativo', N'Smartphones corporativos rastreados por IMEI e número de série', 1, N'item_equipamento_ti', 1);

-- Tablet  (rastreável — item_equipamento_ti)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Tablet')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Tablet', N'Tablets corporativos rastreados por IMEI e número de série', 1, N'item_equipamento_ti', 1);

-- Linha corporativa  (rastreável — item_linha_telefonica)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Linha corporativa')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Linha corporativa', N'Chips/eSIMs corporativos com rastreamento de operadora, contrato e plano', 1, N'item_linha_telefonica', 1);

-- Impressora  (rastreável — item_equipamento_ti)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Impressora')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Impressora', N'Impressoras e multifuncionais rastreadas por número de série', 1, N'item_equipamento_ti', 1);

-- Periféricos  (genérico — sem tabela de detalhe)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_ti AND nome = 'Periféricos')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_ti, N'Periféricos', N'Headsets, teclados, mouses sem fio, webcams e demais acessórios sem rastreamento individual', 0, NULL, 1);

-- ------------------------------------------------------------
-- 3. TIPOS DE ITEM — Operação
-- ------------------------------------------------------------

-- Veículo  (rastreável — item_veiculo)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_op AND nome = 'Veículo')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_op, N'Veículo', N'Veículos de frota ou locados, rastreados por placa e RENAVAM', 1, N'item_veiculo', 1);

-- Ferramentas  (genérico)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_op AND nome = 'Ferramentas')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_op, N'Ferramentas', N'Chaves de fenda, alicates, chave Allen, maletas, multímetros e similares — controle por quantidade', 0, NULL, 1);

-- Equipamentos  (genérico)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_op AND nome = 'Equipamentos')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_op, N'Equipamentos', N'Máquinas de solda e equipamentos de operação — controle por quantidade', 0, NULL, 1);

-- ------------------------------------------------------------
-- 4. TIPOS DE ITEM — RH
-- ------------------------------------------------------------

-- Cartão benefício  (rastreável — item_cartao)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_rh AND nome = 'Cartão benefício')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_rh, N'Cartão benefício', N'Cartões VT, Mobilidade, Onfly, VR/VA — rastreados por número e fornecedor', 1, N'item_cartao', 1);

-- EPI  (genérico)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_rh AND nome = 'EPI')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_rh, N'EPI', N'Equipamentos de Proteção Individual — controle por quantidade', 0, NULL, 1);

-- Uniforme  (genérico)
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_item WHERE categoria_id = @cat_rh AND nome = 'Uniforme')
    INSERT INTO dbo.tipos_item (categoria_id, nome, descricao, is_determinado, tabela_detalhe, ativo)
    VALUES (@cat_rh, N'Uniforme', N'Uniformes e EPCs — controle por quantidade', 0, NULL, 1);