-- Executar no banco: Portal_Integra (SQL Server)
-- Rascunhos de edição do organograma

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name = 'org_chart_drafts' AND xtype = 'U')
CREATE TABLE dbo.org_chart_drafts (
    id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE(),
    base_snapshot_at DATETIME NULL,
    title NVARCHAR(200) NULL,
    payload NVARCHAR(MAX) NOT NULL,
    CONSTRAINT fk_org_chart_drafts_user FOREIGN KEY (user_id) REFERENCES dbo.users(id)
);

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_org_chart_drafts_user' AND object_id = OBJECT_ID('dbo.org_chart_drafts')
)
CREATE INDEX IX_org_chart_drafts_user
    ON dbo.org_chart_drafts (user_id, updated_at DESC);

IF NOT EXISTS (SELECT 1 FROM dbo.permissions WHERE code = 'hr.organograma_draft')
INSERT INTO dbo.permissions (code, name)
VALUES ('hr.organograma_draft', N'Organograma — Proposta de edição');
