-- 1) Staging de importação
IF OBJECT_ID('[Portal_Integra].dbo.MF_Import', 'U') IS NULL
CREATE TABLE [Portal_Integra].dbo.MF_Import (
  ID                int IDENTITY(1,1) PRIMARY KEY,
  OpID              uniqueidentifier NOT NULL,
  RowNum            int NOT NULL,
  ProdutoReferencia nvarchar(80) NOT NULL,
  ProdutoDescricao  nvarchar(200) NULL,
  ValorPlanilha     decimal(18,4) NULL,
  RefNorm           varchar(80) NOT NULL,
  CreatedAt         datetime2(0) NOT NULL CONSTRAINT DF_MF_Import_CreatedAt DEFAULT (sysdatetime())
);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_MF_Import_Op_RefNorm' AND object_id = OBJECT_ID('[Portal_Integra].dbo.MF_Import'))
  CREATE INDEX IX_MF_Import_Op_RefNorm ON [Portal_Integra].dbo.MF_Import(OpID, RefNorm);

-- 2) Resultados
IF OBJECT_ID('[Portal_Integra].dbo.MF_Result', 'U') IS NULL
CREATE TABLE [Portal_Integra].dbo.MF_Result (
  ID                 int IDENTITY(1,1) PRIMARY KEY,
  OpID               uniqueidentifier NOT NULL,
  Tipo               varchar(20) NOT NULL, -- MATCH | ONLY_PLAN | ONLY_SYS
  Empresa            nvarchar(120) NULL,
  ProdutoCodigo      int NULL,
  ProdutoReferencia  nvarchar(80) NULL,
  ProdutoDescricao   nvarchar(250) NULL,
  NCMCodigo          int NULL,
  NCMIdentificador   nvarchar(50) NULL,
  ValorPlanilha      decimal(18,4) NULL,
  Overprice          decimal(9,6) NULL,
  IndiceParaCusto    decimal(9,6) NULL,
  PrecoPublico       decimal(18,4) NULL,
  PrecoSugerido      decimal(18,4) NULL,
  PrecoGarantia      decimal(18,4) NULL,
  PrecoReposicao     decimal(18,4) NULL,
  CreatedAt          datetime2(0) NOT NULL CONSTRAINT DF_MF_Result_CreatedAt DEFAULT (sysdatetime())
);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_MF_Result_Op_Tipo' AND object_id = OBJECT_ID('[Portal_Integra].dbo.MF_Result'))
  CREATE INDEX IX_MF_Result_Op_Tipo ON [Portal_Integra].dbo.MF_Result(OpID, Tipo);

-- 3) Progresso (job/etapas)
IF OBJECT_ID('[Portal_Integra].dbo.JobProgress', 'U') IS NULL
CREATE TABLE [Portal_Integra].dbo.JobProgress (
  OpID             uniqueidentifier NOT NULL PRIMARY KEY,
  MarcaId          int NOT NULL,
  MarcaNome        nvarchar(80) NOT NULL,
  Step             varchar(40) NOT NULL,   -- INIT | IMPORT | MATCH | ONLY_PLAN | ONLY_SYS | DONE | ERROR
  Status           varchar(20) NOT NULL,   -- PENDING | RUNNING | OK | ERROR
  Message          nvarchar(4000) NULL,
  Total            int NULL,
  Done             int NULL,
  StartedAt        datetime2(0) NOT NULL,
  UpdatedAt        datetime2(0) NOT NULL,
  FinishedAt       datetime2(0) NULL,
  Overprice        decimal(9,6) NOT NULL,
  IndiceParaCusto  decimal(9,6) NOT NULL,
  UploadedFilePath nvarchar(400) NULL
);