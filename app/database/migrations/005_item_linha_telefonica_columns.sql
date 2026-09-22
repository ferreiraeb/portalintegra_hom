-- =============================================
-- item_linha_telefonica: campos de contrato e empresa
-- =============================================

IF COL_LENGTH('dbo.item_linha_telefonica', 'cnpj_empresa') IS NULL
    ALTER TABLE dbo.item_linha_telefonica ADD cnpj_empresa NVARCHAR(14) NULL;

IF COL_LENGTH('dbo.item_linha_telefonica', 'IdLinha') IS NULL
    ALTER TABLE dbo.item_linha_telefonica ADD IdLinha INT NULL;

IF COL_LENGTH('dbo.item_linha_telefonica', 'data_contrato') IS NULL
    ALTER TABLE dbo.item_linha_telefonica ADD data_contrato DATE NULL;

IF COL_LENGTH('dbo.item_linha_telefonica', 'duracao') IS NULL
    ALTER TABLE dbo.item_linha_telefonica ADD duracao INT NULL;

IF COL_LENGTH('dbo.item_linha_telefonica', 'fim_contrato') IS NULL
    ALTER TABLE dbo.item_linha_telefonica ADD fim_contrato DATE NULL;

IF COL_LENGTH('dbo.item_linha_telefonica', 'fidelizacao') IS NULL
    ALTER TABLE dbo.item_linha_telefonica ADD fidelizacao NVARCHAR(3) NULL;

IF COL_LENGTH('dbo.item_linha_telefonica', 'observacoes') IS NULL
    ALTER TABLE dbo.item_linha_telefonica ADD observacoes NVARCHAR(MAX) NULL;
