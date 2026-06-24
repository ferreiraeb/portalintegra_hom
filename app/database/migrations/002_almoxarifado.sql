-- =============================================
-- Controle de Empréstimo a Colaboradores
-- =============================================

-- ------------------------------------------------------------
-- 1. CATÁLOGO
-- ------------------------------------------------------------

IF OBJECT_ID('dbo.categorias', 'U') IS NULL
CREATE TABLE dbo.categorias (
                                id          INT IDENTITY(1,1) PRIMARY KEY,
                                nome        NVARCHAR(100)    NOT NULL,
                                descricao   NVARCHAR(MAX)    NULL,
                                ativo       BIT              NOT NULL DEFAULT 1,
                                created_at  DATETIME         NOT NULL DEFAULT GETDATE(),
                                updated_at  DATETIME         NULL
);

IF OBJECT_ID('dbo.tipos_item', 'U') IS NULL
CREATE TABLE dbo.tipos_item (
                                id              INT IDENTITY(1,1) PRIMARY KEY,
                                categoria_id    INT              NOT NULL,
                                nome            NVARCHAR(150)    NOT NULL,
                                descricao       NVARCHAR(MAX)    NULL,
                                is_determinado  BIT              NOT NULL DEFAULT 1,
                                tabela_detalhe  NVARCHAR(80)     NULL,
                                ativo           BIT              NOT NULL DEFAULT 1,
                                created_at      DATETIME         NOT NULL DEFAULT GETDATE(),
                                CONSTRAINT fk_tipos_item_categoria FOREIGN KEY (categoria_id) REFERENCES dbo.categorias(id)
);

-- ------------------------------------------------------------
-- 2. ITENS
-- ------------------------------------------------------------

IF OBJECT_ID('dbo.itens', 'U') IS NULL
CREATE TABLE dbo.itens (
                           id               INT IDENTITY(1,1) PRIMARY KEY,
                           tipo_item_id     INT              NOT NULL,
                           descricao        NVARCHAR(255)    NOT NULL,
                           status           NVARCHAR(20)     NOT NULL DEFAULT 'disponivel',
                           quantidade_total INT              NOT NULL DEFAULT 1,
                           localizacao      NVARCHAR(100)    NULL,
                           observacao       NVARCHAR(MAX)    NULL,
                           created_by       INT              NULL,
                           updated_by       INT              NULL,
                           created_at       DATETIME         NOT NULL DEFAULT GETDATE(),
                           updated_at       DATETIME         NULL,
                           CONSTRAINT fk_itens_tipo_item  FOREIGN KEY (tipo_item_id) REFERENCES dbo.tipos_item(id),
                           CONSTRAINT fk_itens_created_by FOREIGN KEY (created_by)   REFERENCES dbo.users(id),
                           CONSTRAINT fk_itens_updated_by FOREIGN KEY (updated_by)   REFERENCES dbo.users(id),
                           CONSTRAINT chk_itens_status    CHECK (status IN ('disponivel','em_uso','reservado','bloqueado','baixado','extraviado')),
                           CONSTRAINT chk_itens_qtd_total CHECK (quantidade_total >= 0)
);

CREATE INDEX ix_itens_tipo_status ON dbo.itens(tipo_item_id, status);

-- ------------------------------------------------------------
-- 3. DETALHAMENTO — Itens Determinados
-- ------------------------------------------------------------

-- 3.1 Linha / Chip (ciclo de vida independente do aparelho)
IF OBJECT_ID('dbo.item_linha_telefonica', 'U') IS NULL
CREATE TABLE dbo.item_linha_telefonica (
                                           id               INT IDENTITY(1,1) PRIMARY KEY,
                                           item_id          INT              NOT NULL,
                                           numero_linha     NVARCHAR(30)     NULL,
                                           numero_chip      NVARCHAR(30)     NULL,
                                           numero_anterior  NVARCHAR(30)     NULL,
                                           operadora        NVARCHAR(80)     NULL,
                                           tipo_chip        NVARCHAR(10)     NULL,
                                           status_linha     NVARCHAR(20)     NOT NULL DEFAULT 'ativo',
                                           contrato         NVARCHAR(100)    NULL,
                                           plano            NVARCHAR(200)    NULL,
                                           custo_mensal     DECIMAL(10,2)    NULL,
                                           CONSTRAINT fk_linha_tel_item      FOREIGN KEY (item_id) REFERENCES dbo.itens(id),
                                           CONSTRAINT uq_linha_tel_item      UNIQUE (item_id),
                                           CONSTRAINT chk_linha_tipo_chip    CHECK (tipo_chip    IS NULL OR tipo_chip    IN ('SIM','eSIM')),
                                           CONSTRAINT chk_linha_status_linha CHECK (status_linha IN ('ativo','inativo','cancelado'))
);

-- 3.2 Equipamentos TI (unificada)
IF OBJECT_ID('dbo.item_equipamento_ti', 'U') IS NULL
CREATE TABLE dbo.item_equipamento_ti (
                                         id               INT IDENTITY(1,1) PRIMARY KEY,
                                         item_id          INT              NOT NULL,
                                         numero_serie     NVARCHAR(100)    NULL,
                                         etiqueta         NVARCHAR(80)     NULL,
                                         marca            NVARCHAR(100)    NULL,
                                         modelo           NVARCHAR(150)    NULL,
                                         proprietario     NVARCHAR(50)     NULL,
                                         imei             NVARCHAR(20)     NULL,
                                         linha_item_id    INT              NULL,
                                         mac_address      NVARCHAR(17)     NULL,
                                         CONSTRAINT fk_equip_ti_item   FOREIGN KEY (item_id)       REFERENCES dbo.itens(id),
                                         CONSTRAINT fk_equip_ti_linha  FOREIGN KEY (linha_item_id) REFERENCES dbo.itens(id),
                                         CONSTRAINT uq_equip_ti_item   UNIQUE (item_id),
                                         CONSTRAINT chk_equip_ti_prop  CHECK (proprietario IS NULL OR proprietario IN (
                                                                                                                       'Voke','Minascopy','Líder','Valence','TTG'
                                             ))
);

-- 3.3 Veículo
IF OBJECT_ID('dbo.item_veiculo', 'U') IS NULL
CREATE TABLE dbo.item_veiculo (
                                  id           INT IDENTITY(1,1) PRIMARY KEY,
                                  item_id      INT              NOT NULL,
                                  placa        NVARCHAR(10)     NULL,
                                  marca        NVARCHAR(100)    NULL,
                                  modelo       NVARCHAR(150)    NULL,
                                  ano          SMALLINT         NULL,
                                  cor          NVARCHAR(50)     NULL,
                                  renavam      NVARCHAR(20)     NULL,
                                  proprietario NVARCHAR(100)    NULL,
                                  data_contratacao   DATE       NULL,
                                  data_vencimento    DATE       NULL,
                                  CONSTRAINT fk_veiculo_item    FOREIGN KEY (item_id) REFERENCES dbo.itens(id),
                                  CONSTRAINT uq_veiculo_item    UNIQUE (item_id),
                                  CONSTRAINT chk_veiculo_prop   CHECK (proprietario IS NULL OR proprietario IN (
                                                                                                                'Stellants/Flua','Valence','Barros e Braga','MM','Localiza'
                                      ))
);

-- 3.4 Cartão Benefício
IF OBJECT_ID('dbo.item_cartao', 'U') IS NULL
CREATE TABLE dbo.item_cartao (
                                 id              INT IDENTITY(1,1) PRIMARY KEY,
                                 item_id         INT              NOT NULL,
                                 tipo_cartao     NVARCHAR(50)     NULL,
                                 numero_cartao   NVARCHAR(20)     NULL,
                                 descricao       NVARCHAR(255)    NULL,
                                 bandeira        NVARCHAR(50)     NULL,
                                 fornecedor      NVARCHAR(100)    NULL,
                                 CONSTRAINT fk_cartao_item   FOREIGN KEY (item_id) REFERENCES dbo.itens(id),
                                 CONSTRAINT uq_cartao_item   UNIQUE (item_id),
                                 CONSTRAINT chk_cartao_tipo  CHECK (tipo_cartao IS NULL OR tipo_cartao IN ('Onfly','Combustível'))
);

-- ------------------------------------------------------------
-- 4. EMPRÉSTIMOS
-- ------------------------------------------------------------

IF OBJECT_ID('dbo.emprestimos', 'U') IS NULL
CREATE TABLE dbo.emprestimos (
                                 id                       INT IDENTITY(1,1) PRIMARY KEY,
                                 item_id                  INT              NOT NULL,
                                 quantidade               INT              NOT NULL DEFAULT 1,
                                 quantidade_devolvida     INT              NOT NULL DEFAULT 0,
                                 colaborador_codpessoa    NVARCHAR(30)     NOT NULL,
                                 colaborador_nome         NVARCHAR(200)    NOT NULL,
                                 criado_por               INT              NULL,
                                 data_entrega             DATE             NOT NULL,
                                 data_prevista_devolucao  DATE             NULL,
                                 data_devolucao           DATE             NULL,
                                 status                   NVARCHAR(20)     NOT NULL DEFAULT 'ativo',
                                 observacao               NVARCHAR(MAX)    NULL,
                                 created_at               DATETIME         NOT NULL DEFAULT GETDATE(),
                                 updated_at               DATETIME         NULL,
                                 CONSTRAINT fk_emprestimos_item        FOREIGN KEY (item_id)    REFERENCES dbo.itens(id),
                                 CONSTRAINT fk_emprestimos_criado_por  FOREIGN KEY (criado_por) REFERENCES dbo.users(id),
                                 CONSTRAINT chk_emprestimos_status   CHECK (status IN ('ativo', 'reservado', 'devolvido','extraviado','transferido', 'cancelado')),
                                 CONSTRAINT chk_emprestimos_qtd      CHECK (quantidade >= 1),
                                 CONSTRAINT chk_emprestimos_qtd_dev  CHECK (quantidade_devolvida >= 0 AND quantidade_devolvida <= quantidade)
);

CREATE INDEX ix_emprestimos_colab_status ON dbo.emprestimos(colaborador_codpessoa, status);
CREATE INDEX ix_emprestimos_item_status   ON dbo.emprestimos(item_id, status);

-- ------------------------------------------------------------
-- 5. TERMOS DE RESPONSABILIDADE
-- ------------------------------------------------------------

IF OBJECT_ID('dbo.termos_responsabilidade', 'U') IS NULL
CREATE TABLE dbo.termos_responsabilidade (
                                             id                       INT IDENTITY(1,1) PRIMARY KEY,
                                             colaborador_codpessoa    NVARCHAR(30)     NOT NULL,
                                             colaborador_nome         NVARCHAR(200)    NOT NULL,
                                             numero_termo             NVARCHAR(50)     NULL,
                                             status                   NVARCHAR(20)     NOT NULL DEFAULT 'pendente_envio',
                                             motivo_cancelamento      NVARCHAR(500)    NULL,
                                             data_criacao             DATETIME         NOT NULL DEFAULT GETDATE(),
                                             data_envio               DATETIME         NULL,
                                             data_assinatura          DATETIME         NULL,
                                             data_expiracao           DATETIME         NULL,
                                             d4sign_uuid              NVARCHAR(100)    NULL,
                                             d4sign_doc_id            NVARCHAR(100)    NULL,
                                             documento_url            NVARCHAR(500)    NULL,
                                             documento_url_assinado   NVARCHAR(500)    NULL,
                                             gerado_por               INT              NULL,
                                             created_at               DATETIME         NOT NULL DEFAULT GETDATE(),
                                             updated_at               DATETIME         NULL,
                                             CONSTRAINT fk_termos_gerado_por FOREIGN KEY (gerado_por) REFERENCES dbo.users(id),
                                             CONSTRAINT chk_termos_status    CHECK (status IN ('pendente_envio','enviado','lido','assinado','cancelado','expirado'))
);

IF OBJECT_ID('dbo.termo_emprestimos', 'U') IS NULL
CREATE TABLE dbo.termo_emprestimos (
                                       id              INT IDENTITY(1,1) PRIMARY KEY,
                                       termo_id        INT NOT NULL,
                                       emprestimo_id   INT NOT NULL,
                                       incluido_em     DATETIME NOT NULL DEFAULT GETDATE(),
                                       CONSTRAINT fk_termo_emp_termo      FOREIGN KEY (termo_id)      REFERENCES dbo.termos_responsabilidade(id),
                                       CONSTRAINT fk_termo_emp_emprestimo FOREIGN KEY (emprestimo_id) REFERENCES dbo.emprestimos(id),
                                       CONSTRAINT uq_termo_emprestimo     UNIQUE (termo_id, emprestimo_id)
);