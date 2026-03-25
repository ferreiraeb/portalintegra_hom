<?php
$title = $title ?? 'Documentação do Sistema — Portal Integra';
?>
<section class="content pt-3">
  <div class="container-fluid">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title mb-0"><?= htmlspecialchars($title) ?></h3>
      </div>
      <div class="card-body">

        <p class="text-muted">
          Esta página consolida a arquitetura, padrões, permissões e fluxos atuais do Portal Integra.  
          Use-a como referência rápida para manutenção e evolução do sistema.
        </p>

        <!-- SUMÁRIO -->
        <div class="mb-3">
          <h5>Sumário</h5>
          <ol class="mb-0">
            <li><a href="#estrutura">Estrutura de Pastas</a></li>
            <li><a href="#config">Configuração (config.php)</a></li>
            <li><a href="#bd">Banco de Dados (Portal_Integra)</a></li>
            <li><a href="#roteador">Roteador (public/index.php)</a></li>
            <li><a href="#layout">Layout & UI (AdminLTE)</a></li>
            <li><a href="#usuarios">Módulo Usuários</a></li>
            <li><a href="#permissoes">Permissões e Modal de Permissões</a></li>
            <li><a href="#drilling-di">Drilling → Análise DI</a></li>
            <li><a href="#drilling-getman">Drilling → Tabela Getman</a></li>
            <li><a href="#seguranca">Segurança & Boas Práticas</a></li>
            <li><a href="#brand">Brand (Logo & Favicon)</a></li>
            <li><a href="#roadmap">Roadmap / Próximos Passos</a></li>
            <li><a href="#handoff">Prompt de Continuidade (Hand-off)</a></li>
          </ol>
        </div>

        <!-- 1) ESTRUTURA -->
        <h4 id="estrutura" class="mt-4">1) Estrutura de Pastas</h4>
<pre class="mb-3" style="white-space:pre-wrap;word-break:break-word;">
/config/
  config.php

/public/
  index.php
  .htaccess
  /assets/
    /img/
      logo_portalIntegra.png
    /favicon/
      favicon.ico
      favicon-16.png
      favicon-32.png
      favicon-180.png
      favicon-192.png
      favicon-512.png
  manifest.webmanifest (opcional)

/src/
  /Controllers/
    AuthController.php
    UserController.php
    PermissionController.php
    DrillingController.php
    DrillingGetmanController.php
    DocsController.php
  /Database/
    Connection.php
  /Modules/
    /Drilling/
      AnaliseDIService.php
  /Security/
    Auth.php
    Permission.php
  /Services/
    LdapService.php
  /Support/
    helpers.php

/views/
  /layouts/
    adminlte.php
    auth.php
  /auth/
    login.php
  /home/
    index.php
  /users/
    index.php
    form.php
    _ad_readonly.php
    _change_password_form.php
  /permissions/
    _user_permissions.php
  /drilling/
    analise_di.php
    tabela_getman.php
  /docs/
    index.php

/database/migrations/
  001_init.sql
</pre>

        <!-- 2) CONFIG -->
        <h4 id="config" class="mt-4">2) Configuração (<code>/config/config.php</code>)</h4>
        <ul>
          <li><strong>App:</strong> <code>name</code>, timezone, <code>menu_layout</code>, <code>base_url</code>, <code>session_name</code>, <code>csrf_key</code>.</li>
          <li><strong>DB principal (Portal_Integra):</strong> <code>driver=sqlsrv</code>, <code>server</code>, <code>database=Portal_Integra</code>, <code>username</code>, <code>password</code>.</li>
          <li><strong>DealerNet (cross‑database, MESMO servidor):</strong> <code>db['dealernet_database'] = 'GrupoValence_HML'</code>.</li>
          <li><strong>LDAP/AD:</strong> bind de serviço, base DN, atributos, <code>user_upn_suffix</code>, etc.</li>
        </ul>

        <!-- 3) BANCO -->
        <h4 id="bd" class="mt-4">3) Banco de Dados (Portal_Integra)</h4>
        <p><strong>Tabelas chave:</strong></p>
        <ul>
          <li><code>users</code>: local/AD; perfil; campos AD (dn, upn, department, title...). <em>Login</em> é <strong>UNIQUE</strong> (case‑insensitive recomendado via coluna computada normalizada).</li>
          <li><code>permissions</code>: <code>code</code> (UNIQUE), <code>name</code>.</li>
          <li><code>user_permissions</code>: <code>user_id</code>, <code>permission_code</code>, <code>level</code> (0,1,2).</li>
        </ul>
        <p><strong>Permissions seeds atuais:</strong> <code>users.manage</code>, <code>drilling.analise_di</code>, <code>drilling.tabela_getman</code>.</p>

        <!-- 4) ROTEADOR -->
        <h4 id="roteador" class="mt-4">4) Roteador (<code>/public/index.php</code>)</h4>
        <p>Switch por <code>$path</code>; cada rota chama um controller e view. Proteções de permissão no controller.</p>

        <!-- 5) LAYOUT -->
        <h4 id="layout" class="mt-4">5) Layout & UI</h4>
        <ul>
          <li><strong>Navbar:</strong> nome do usuário; “Alterar Senha” (apenas origem <code>local</code>); “Sair”.</li>
          <li><strong>Sidebar:</strong> logo (classe <code>.portal-logo</code>, evitando <code>.brand-image</code> do AdminLTE), grupos “Início”, “Usuários”, “Drilling”.</li>
          <li><strong>Modal genérico:</strong> botão “Salvar” aparece quando o conteúdo possuir <code>&lt;form id="modalForm"&gt;</code>. Tamanhos: md/lg/xl com <code>openModalWithUrl(url, title, size)</code>.</li>
          <li><strong>Login:</strong> logo acima do card; largura da <code>.login-box</code> ajustada (ex.: 560px).</li>
        </ul>

        <!-- 6) USUÁRIOS -->
        <h4 id="usuarios" class="mt-4">6) Módulo Usuários</h4>
        <ul>
          <li><strong>Listagem:</strong> filtros por texto/origem; ordenação; paginação; ações condicionadas a <code>users.manage</code>.</li>
          <li><strong>AD:</strong> “Ver dados do AD” em modal XL (somente leitura). Usuários AD não editam nem alteram senha no sistema.</li>
          <li><strong>Local:</strong> criar/editar/excluir (nível 2). “Alterar Senha” via modal (apenas origem local).</li>
          <li><strong>Permissões:</strong> botão “Permissões” abre modal para atribuir 0/1/2 aos recursos principais.</li>
        </ul>

        <!-- 7) PERMISSÕES -->
        <h4 id="permissoes" class="mt-4">7) Permissões</h4>
        <ul>
          <li><strong>Estratégia:</strong> guardar níveis por <code>(user_id, permission_code)</code>. Cache em sessão ao logar.</li>
          <li><strong>Menu:</strong> exibe itens de acordo com <code>Permission::level(code)</code>.</li>
          <li><strong>Rotas:</strong> <code>Permission::require(code, minLevel)</code> em controllers.</li>
          <li><strong>Modal:</strong> <em>Users, Drilling → Análise DI, Drilling → Tabela Getman</em> com 0/1/2.</li>
        </ul>

        <!-- 8) DRILLING DI -->
        <h4 id="drilling-di" class="mt-4">8) Drilling → Análise DI</h4>
        <ul>
          <li><strong>Service:</strong> <code>AnaliseDIService</code> — processamento NF/DI (XML), XPath, dealer e NCM no DealerNet via cross‑db (<code>[db].dbo.Tabela</code>).</li>
          <li><strong>Controller:</strong> rota exige perm ≥1; ações de “corrigir” e “persistir” exigem 2.</li>
          <li><strong>View:</strong> abas; export CSV; resumo por referência; persistência em <code>DeParaRelacionamento</code> no DealerNet.</li>
        </ul>

        <!-- 9) DRILLING GETMAN -->
        <h4 id="drilling-getman" class="mt-4">9) Drilling → Tabela Getman</h4>
        <ul>
          <li><strong>Acesso:</strong> permissão <code>drilling.tabela_getman</code> = <strong>2</strong>.</li>
          <li><strong>Parser XLSX:</strong> <em>ZipArchive + SimpleXML</em>; detecção de cabeçalho (PartNum, PartDescription, coluna de preço).</li>
          <li><strong>Cruzamento:</strong> DealerNet × Planilha → <em>Relação de Itens</em>, <em>Itens Apenas DealerNet</em>, <em>Itens Apenas Planilha</em>.</li>
          <li><strong>Export CSV</strong>; <strong>Atualizar Valores</strong> = auditoria (Portal_Integra) + <code>EXEC</code> da procedure por item no DealerNet (mensagem via flash na própria tela).</li>
        </ul>

        <!-- 10) SEGURANÇA -->
        <h4 id="seguranca" class="mt-4">10) Segurança & Boas Práticas</h4>
        <ul>
          <li><strong>CSRF:</strong> usar <code>csrf_field()</code> em todos os formulários.</li>
          <li><strong>XSS:</strong> <code>htmlspecialchars(..., ENT_QUOTES, 'UTF-8')</code> para todo output variável.</li>
          <li><strong>Auth:</strong> <code>Auth::requireAuth()</code>; AD não altera senha pelo sistema.</li>
          <li><strong>SQLSRV:</strong> não usar <code>PDO::SQLSRV_ATTR_*</code> no <code>new PDO(...)</code>; se necessário, aplicar no <em>statement</em> com <code>$stmt->setAttribute(...)</code>.</li>
          <li><strong>Views:</strong> nunca escrever âncoras malformadas (padrão correto: <code>href="<?= base_url('rota') ?>"</code>).</li>
        </ul>

        <!-- 11) BRAND -->
        <h4 id="brand" class="mt-4">11) Brand (Logo & Favicon)</h4>
        <ul>
          <li><strong>Logo:</strong> <code>/public/assets/img/logo_portalIntegra.png</code>.  
              Sidebar: classe <code>.portal-logo</code> com altura controlada (ex.: 56px normal, 32px colapsada).  
              Login: altura sugerida 64px.</li>
          <li><strong>Favicon:</strong> <code>/public/assets/favicon/</code> (<code>.ico</code> + PNGs 16/32/180/192/512) e links nos dois layouts (<code>&lt;head&gt;</code>).</li>
        </ul>

        <!-- 12) ROADMAP -->
        <h4 id="roadmap" class="mt-4">12) Roadmap / Próximos Passos</h4>
        <ul>
          <li>Auditoria ampla (portal): tabela <code>audit_log</code> (rota, usuário, payload, timestamp).</li>
          <li>Paginação nas tabelas do Drilling quando arquivos forem grandes.</li>
          <li>Perfis (roles) agregando permissões (admin, operador, auditor etc.).</li>
          <li>Outras marcas em Drilling reaproveitando o pipeline da Getman.</li>
        </ul>

        <!-- 13) HAND-OFF PROMPT -->
        <h4 id="handoff" class="mt-4">13) Prompt de Continuidade (Hand-off)</h4>
<pre style="white-space:pre-wrap;word-break:break-word;">
Você é agora o(a) desenvolvedor(a) assistente do projeto “Portal Integra”. 
Siga as diretrizes abaixo. Não reescreva do zero; evolua o que existe.

=== CONTEXTO DO SISTEMA ===
- Stack: PHP 7.2+ (XAMPP/Windows), AdminLTE 3 + Bootstrap 4, SQL Server (pdo_sqlsrv), LDAP/AD.
- Arquitetura: MVC leve, roteador em /public/index.php; conexão única PDO em /src/Database/Connection.php; helpers em /src/Support/helpers.php.
- Layouts: /views/layouts/adminlte.php (logado) e /views/layouts/auth.php (login).
- Modal genérico: botão “Salvar” aparece quando o conteúdo possui &lt;form id="modalForm"&gt;; função JS openModalWithUrl(url, title, size).
- Permissões (0/1/2): gravadas em permissions/user_permissions. Menu condiciona por Permission::level(code), rotas protegem com Permission::require(code,min).
- Usuários: origem local|ad; AD não altera senha; listagem com filtros/paginação/ordenação; modal de permissões; “Ver dados do AD” (XL).
- Drilling → Análise DI: service AnaliseDIService (NF/DI XML, XPath, DealerNet cross‑db [db].dbo.Tabela), controller exige perm ≥1; ações corrigir/persistir exigem 2.
- Drilling → Tabela Getman: parser XLSX, cruzamento com DealerNet, abas (Relação, Apenas DealerNet, Apenas Planilha), export CSV, “Atualizar Valores” (auditoria + EXEC proc).
- UI/Brand: logo portal_portalIntegra.png; classe .portal-logo na sidebar; favicons em /public/assets/favicon/ para ambos layouts.

=== REGRAS ESSENCIAIS ===
1) Em views, nunca usar âncoras malformadas; sempre href="<?= base_url('rota') ?>".
2) Em modais, sempre &lt;form id="modalForm"&gt; com csrf_field().
3) Atributos SQLSRV específicos não entram no new PDO; se preciso, use $stmt->setAttribute(...).
4) Controllers: Permission::require('code', minLevel) para proteger rotas.
5) Menus: exibir itens apenas quando Permission::level(code) ≥ mínimo.
6) AD: ocultar “Alterar Senha” e bloquear back-end para origem ad.

=== O QUE EVOLUIR ===
- Criar novos módulos/telas com o mesmo padrão (permissões 0/1/2, modal, layout).
- Adicionar novas permissions (code) e gerir via modal.
- Reaproveitar pipeline de Drilling para outras marcas (ajustar parser e consultas).
- Implementar auditoria global (audit_log).
- Expandir paginação/ordenar/filtros onde necessário.

=== CHECKLIST NOVA TELA ===
- [ ] Permission criada (permissions) e seed (dar 2 para admin, se aplicável).
- [ ] Menu: subitem exibido apenas se Permission::level(code) ≥ mínimo.
- [ ] Rota em public/index.php e proteção no controller com Permission::require(code, min).
- [ ] View AdminLTE com botões respeitando níveis 0/1/2.
- [ ] Modal: &lt;form id="modalForm"&gt; + csrf_field().
- [ ] Se consultar DealerNet, sempre prefixar com [db].dbo.Tabela e usar Connection::get().
- [ ] Testar com usuário 0/1/2.
</pre>

      </div>
    </div>
  </div>
</section>