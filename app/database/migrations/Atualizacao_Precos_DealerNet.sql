/* ======================================================================
   TABELA DE AUDITORIA / HISTÓRICO DE ATUALIZAÇÕES DE PREÇOS (DealerNet)
   Banco: Portal_Integra
   Obs.: Armazena TODAS as colunas da aba "Planilha Resultante"
         + Marca_Codigo, Marca_Empresa, dataHoraProcessamento, ID (PK),
           e um IDUnico (GUID) igual para todos os itens da mesma operação.
   ====================================================================== */
USE [Portal_Integra];
GO

IF OBJECT_ID('dbo.Atualizacao_Precos_DealerNet', 'U') IS NOT NULL
    DROP TABLE dbo.Atualizacao_Precos_DealerNet;
GO

CREATE TABLE dbo.Atualizacao_Precos_DealerNet
(
    ID                         BIGINT IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_Atualizacao_Precos_DealerNet PRIMARY KEY CLUSTERED,

    IDUnico                    UNIQUEIDENTIFIER NOT NULL, -- GUID por operação
    dataHoraProcessamento      DATETIME2(0)     NOT NULL CONSTRAINT DF_APD_dataHoraProcessamento DEFAULT (GETDATE()),

    -- Campos adicionais solicitados:
    Marca_Codigo               INT              NOT NULL,
    Marca_Empresa              NVARCHAR(200)    NOT NULL,  -- nome da empresa relacionada à marca na linha

    -- Colunas da "Planilha Resultante"
    Marca                      NVARCHAR(150)    NULL,
    Empresa                    NVARCHAR(200)    NULL,
    ProdutoCodigo              INT              NOT NULL,
    ProdutoReferencia          NVARCHAR(100)    NULL,
    ProdutoDescricao           NVARCHAR(300)    NULL,
    NCMCodigo                  INT              NULL,
    NCMIdentificador           NVARCHAR(50)     NULL,
    [ValorPlanilha(US$)]       DECIMAL(18,4)    NULL,
    Markup                     DECIMAL(18,6)    NULL,  -- armazenado como fração (ex.: 0,40 = 40%)
    FatorImportacao            DECIMAL(18,6)    NULL,  -- fração (ex.: 0,017 = 1,7%)
    CotacaoDolar               DECIMAL(18,6)    NULL,
    Tipo                       NVARCHAR(100)    NULL,
    PrecoPublico               DECIMAL(18,4)    NULL,
    PrecoSugerido              DECIMAL(18,4)    NULL,
    PrecoGarantia              DECIMAL(18,4)    NULL,
    PrecoReposicao             DECIMAL(18,4)    NULL
);
GO

-- Índices auxiliares para navegação e rastreio
CREATE INDEX IX_APD_IDUnico ON dbo.Atualizacao_Precos_DealerNet (IDUnico);
CREATE INDEX IX_APD_Produto ON dbo.Atualizacao_Precos_DealerNet (ProdutoCodigo);
CREATE INDEX IX_APD_Data ON dbo.Atualizacao_Precos_DealerNet (dataHoraProcessamento);
GO