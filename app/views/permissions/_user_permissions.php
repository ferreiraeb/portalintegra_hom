<?php
// Espera: $user (alvo), $levels = ['users.manage'=>0..2, ...], $categorias = [], $almoxCatLevels = []
$levels = $levels ?? [
        'is_admin'               => 0,
        'users.manage'           => 0,
        'drilling.analise_di'    => 0,
        'drilling.tabela_getman' => 0,
        'drilling.tabela_boart'  => 0,
        'agro.tabela_massey'     => 0,
        'hr.colaboradores'       => 0,
        'almoxarifado.manage'    => 0,
];
$categorias     = $categorias     ?? [];
$almoxCatLevels = $almoxCatLevels ?? [];

$mapText = [
        0 => 'Nenhum — não vê o menu nem a tela',
        1 => 'Leitura — vê a tela (quando aplicável), sem criar/editar/excluir/gravar',
        2 => 'Escrita — acesso completo à tela (criar/editar/excluir/gravar)',
];

$almoxNivelText = [
        0 => 'Sem acesso — não vê itens nem empréstimos desta categoria',
        1 => 'Consultar — lista e visualiza itens e empréstimos',
        2 => 'Editar — consultar + criar/editar itens e empréstimos',
        3 => 'Gerenciar — editar + alterar status dos itens',
];

// URL de ação (mantém ?id=...)
$actionUrl = base_url('users/permissions?id='.(int)$user['id']);
?>
<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" method="post" id="modalForm">
    <?php csrf_field(); ?>

    <div class="mb-2">
        <h5 class="mb-0"><?= htmlspecialchars($user['nome'] ?: $user['login']) ?></h5>
        <small class="text-muted">
            Usuário <?= strtoupper($user['origem']) ?> • Login: <?= htmlspecialchars($user['login']) ?>
        </small>
    </div>

    <!-- Administrador Global (visível apenas para admins) -->
    <?php if (\Security\Permission::isAdmin()): ?>
    <div class="card mb-3 border-danger">
        <div class="card-header py-2 bg-danger text-white"><strong>⚠ Administrador Global (is_admin)</strong></div>
        <div class="card-body">
            <div class="custom-control custom-radio mb-2">
                <input type="radio"
                       id="perm_is_admin_0"
                       name="perm_is_admin"
                       class="custom-control-input"
                       value="0"
                       <?= ((int)($levels['is_admin'] ?? 0) === 0) ? 'checked' : '' ?>>
                <label class="custom-control-label" for="perm_is_admin_0">
                    <strong>0:</strong> Não — permissões individuais se aplicam normalmente
                </label>
            </div>
            <div class="custom-control custom-radio mb-2">
                <input type="radio"
                       id="perm_is_admin_1"
                       name="perm_is_admin"
                       class="custom-control-input"
                       value="1"
                       <?= ((int)($levels['is_admin'] ?? 0) === 1) ? 'checked' : '' ?>>
                <label class="custom-control-label" for="perm_is_admin_1">
                    <strong>1:</strong> Sim — acesso total a todos os módulos e funções do sistema
                </label>
            </div>
            <small class="form-text text-danger font-weight-bold">
                Atenção: administradores globais ignoram todas as restrições de permissão.
            </small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Usuários -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Módulo “Usuários”</strong></div>
        <div class="card-body">
            <?php foreach ($mapText as $val => $label): ?>
                <div class="custom-control custom-radio mb-2">
                    <input type="radio"
                           id="perm_users_<?= (int)$val ?>"
                           name="perm_users_manage"
                           class="custom-control-input"
                           value="<?= (int)$val ?>"
                            <?= ((int)($levels['users.manage'] ?? 0) === (int)$val) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="perm_users_<?= (int)$val ?>">
                        <strong><?= (int)$val ?>:</strong> <?= htmlspecialchars($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mining → Análise DI -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Mining → Análise DI</strong></div>
        <div class="card-body">
            <?php foreach ($mapText as $val => $label): ?>
                <div class="custom-control custom-radio mb-2">
                    <input type="radio"
                           id="perm_drill_di_<?= (int)$val ?>"
                           name="perm_drilling_analise_di"
                           class="custom-control-input"
                           value="<?= (int)$val ?>"
                            <?= ((int)($levels['drilling.analise_di'] ?? 0) === (int)$val) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="perm_drill_di_<?= (int)$val ?>">
                        <strong><?= (int)$val ?>:</strong> <?= htmlspecialchars($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mining → Tabela Getman -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Mining → Tabela Getman</strong></div>
        <div class="card-body">
            <?php foreach ($mapText as $val => $label): ?>
                <div class="custom-control custom-radio mb-2">
                    <input type="radio"
                           id="perm_drill_getman_<?= (int)$val ?>"
                           name="perm_drilling_tabela_getman"
                           class="custom-control-input"
                           value="<?= (int)$val ?>"
                            <?= ((int)($levels['drilling.tabela_getman'] ?? 0) === (int)$val) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="perm_drill_getman_<?= (int)$val ?>">
                        <strong><?= (int)$val ?>:</strong> <?= htmlspecialchars($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
            <small class="form-text text-muted">
                Observação: a tela "Tabela Getman" exige nível <strong>2 (Escrita)</strong> para acesso/visualização do menu.
            </small>
        </div>
    </div>

    <!-- Mining → Tabela Boart LongYear -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Mining → Tabela Boart LongYear</strong></div>
        <div class="card-body">
            <?php foreach ($mapText as $val => $label): ?>
                <div class="custom-control custom-radio mb-2">
                    <input type="radio"
                           id="perm_drill_boart_<?= (int)$val ?>"
                           name="perm_drilling_tabela_boart"
                           class="custom-control-input"
                           value="<?= (int)$val ?>"
                            <?= ((int)($levels['drilling.tabela_boart'] ?? 0) === (int)$val) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="perm_drill_boart_<?= (int)$val ?>">
                        <strong><?= (int)$val ?>:</strong> <?= htmlspecialchars($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
            <small class="form-text text-muted">
                Observação: a tela "Tabela Boart LongYear" exige nível <strong>2 (Escrita)</strong> para acesso/visualização do menu.
            </small>
        </div>
    </div>

    <!-- Agro → Tabela Massey Ferguson -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Agro → Tabela Massey Ferguson</strong></div>
        <div class="card-body">
            <?php foreach ($mapText as $val => $label): ?>
                <div class="custom-control custom-radio mb-2">
                    <input type="radio"
                           id="perm_agro_massey_<?= (int)$val ?>"
                           name="perm_agro_tabela_massey"
                           class="custom-control-input"
                           value="<?= (int)$val ?>"
                            <?= ((int)($levels['agro.tabela_massey'] ?? 0) === (int)$val) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="perm_agro_massey_<?= (int)$val ?>">
                        <strong><?= (int)$val ?>:</strong> <?= htmlspecialchars($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
            <small class="form-text text-muted">
                Observação: a tela "Tabela Massey Ferguson" exige nível <strong>2 (Escrita)</strong> para acesso/visualização do menu.
            </small>
        </div>
    </div>

    <!-- Colaboradores -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Colaboradores</strong></div>
        <div class="card-body">
            <?php foreach ($mapText as $val => $label): ?>
                <div class="custom-control custom-radio mb-2">
                    <input type="radio"
                           id="perm_hr_colabs_<?= (int)$val ?>"
                           name="perm_hr_colaboradores"
                           class="custom-control-input"
                           value="<?= (int)$val ?>"
                            <?= ((int)($levels['hr.colaboradores'] ?? 0) === (int)$val) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="perm_hr_colabs_<?= (int)$val ?>">
                        <strong><?= (int)$val ?>:</strong> <?= htmlspecialchars($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
            <small class="form-text text-muted">
                Nível <strong>1 (Leitura)</strong> já é suficiente para ver o menu e listar colaboradores.
            </small>
        </div>
    </div>

    <!-- Almoxarifado — Gerenciar Configurações -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Almoxarifado (Gerenciar Configurações)</strong></div>
        <div class="card-body">
            <?php foreach ($mapText as $val => $label): ?>
                <div class="custom-control custom-radio mb-2">
                    <input type="radio"
                           id="perm_alm_manage_<?= (int)$val ?>"
                           name="perm_almoxarifado_manage"
                           class="custom-control-input"
                           value="<?= (int)$val ?>"
                            <?= ((int)($levels['almoxarifado.manage'] ?? 0) === (int)$val) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="perm_alm_manage_<?= (int)$val ?>">
                        <strong><?= (int)$val ?>:</strong> <?= htmlspecialchars($label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
            <small class="form-text text-muted">
                Nível <strong>2 (Escrita)</strong> permite criar e editar categorias e tipos de item.
            </small>
        </div>
    </div>

    <?php if (!empty($categorias)): ?>
    <!-- Almoxarifado — Permissões por Categoria -->
    <div class="card mb-3">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:30%">Categoria</th>
                        <?php foreach ($almoxNivelText as $v => $lbl): ?>
                            <th class="text-center" style="width:17.5%">
                                <span title="<?= htmlspecialchars($lbl) ?>"><?= $v ?> — <?= ['Sem acesso','Consultar','Editar','Gerenciar'][$v] ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categorias as $cat): ?>
                    <?php $catCurrentLevel = (int)($almoxCatLevels[$cat->id] ?? 0); ?>
                    <tr>
                        <td class="align-middle"><strong><?= e($cat->nome) ?></strong></td>
                        <?php foreach ($almoxNivelText as $v => $lbl): ?>
                            <td class="text-center align-middle">
                                <div class="custom-control custom-radio d-inline-block">
                                    <input type="radio"
                                           id="perm_alm_cat_<?= $cat->id ?>_<?= $v ?>"
                                           name="perm_alm_cat_<?= $cat->id ?>"
                                           class="custom-control-input"
                                           value="<?= $v ?>"
                                           <?= $catCurrentLevel === $v ? 'checked' : '' ?>>
                                    <label class="custom-control-label"
                                           for="perm_alm_cat_<?= $cat->id ?>_<?= $v ?>"
                                           title="<?= htmlspecialchars($lbl) ?>">
                                    </label>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div id="permFeedback" class="mt-2"></div>
</form>

<script>
    (function(){
        var $form = $('#modalForm');
        $form.on('submit', function(e){
            e.preventDefault();
            $('#permFeedback').html('');
            var data = $form.serialize();

            $.ajax({
                url: $form.attr('action') + '&ajax=1',
                method: 'POST',
                data: data,
                dataType: 'json'
            }).done(function(resp){
                if (resp.ok) {
                    $('#permFeedback').html('<div class="alert alert-success py-1 my-2">'+ (resp.message || 'Permissões atualizadas.') +'</div>');
                    setTimeout(function(){ $('#genericModal').modal('hide'); }, 900);
                } else {
                    $('#permFeedback').html('<div class="alert alert-danger py-1 my-2">'+ (resp.message || 'Falha ao salvar.') +'</div>');
                }
            }).fail(function(xhr){
                var msg = 'Erro ao salvar ('+xhr.status+').';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                $('#permFeedback').html('<div class="alert alert-danger py-1 my-2">'+ msg +'</div>');
            });
        });
    })();
</script>