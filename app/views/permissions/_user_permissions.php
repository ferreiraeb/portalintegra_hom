<?php
// Espera: $user (alvo), $levels = ['users.manage'=>0..2, 'drilling.analise_di'=>0..2, 'drilling.tabela_getman'=>0..2]
$levels = $levels ?? [
        'users.manage'           => 0,
        'drilling.analise_di'    => 0,
        'drilling.tabela_getman' => 0,
        'drilling.tabela_boart'  => 0,
        'agro.tabela_massey'     => 0,
];

$mapText = [
        0 => 'Nenhum — não vê o menu nem a tela',
        1 => 'Leitura — vê a tela (quando aplicável), sem criar/editar/excluir/gravar',
        2 => 'Escrita — acesso completo à tela (criar/editar/excluir/gravar)',
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

    <!-- Drilling → Análise DI -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Drilling → Análise DI</strong></div>
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

    <!-- Drilling → Tabela Getman -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Drilling → Tabela Getman</strong></div>
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

    <!-- Drilling → Tabela Boart LongYear -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Permissões — Drilling → Tabela Boart LongYear</strong></div>
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