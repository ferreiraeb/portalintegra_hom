-- Pessoa real no Oracle é o CPF (CODPESSOA se repete entre empresas).
-- Empréstimos e termos precisam do CPF para relacionar itens ao colaborador correto.

IF COL_LENGTH('dbo.emprestimos', 'colaborador_cpf') IS NULL
    ALTER TABLE dbo.emprestimos ADD colaborador_cpf NVARCHAR(14) NULL;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'ix_emprestimos_colab_cpf' AND object_id = OBJECT_ID('dbo.emprestimos')
)
    CREATE INDEX ix_emprestimos_colab_cpf ON dbo.emprestimos(colaborador_cpf);

IF COL_LENGTH('dbo.termos_responsabilidade', 'colaborador_cpf') IS NULL
    ALTER TABLE dbo.termos_responsabilidade ADD colaborador_cpf NVARCHAR(14) NULL;
