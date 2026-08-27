<?php
$modo     = $modo ?? 'criar';
$isEdit   = $modo === 'editar';
$cat      = $categoria ?? ['nome' => '', 'descricao' => '', 'ativo' => 1];
$erro     = $erro ?? null;
$titulo   = $isEdit ? 'Editar Categoria' : 'Nova Categoria';
$action   = $isEdit
    ? base_url('categorias/'.(int)$cat['id'].'/editar')
    : base_url('categorias/criar');

$tiposDisponiveis = $tiposDisponiveis ?? [];
$tiposNaCategoria = $tiposNaCategoria ?? [];
$categoriaId      = (int)($cat['id'] ?? 0);

$mapTipoDualItem = static function (array $t, bool $inCategoria) use ($categoriaId): array {
    $id     = (int)$t['id'];
    $nome   = (string)($t['nome'] ?? '');
    $catNom = (string)($t['nome_categoria'] ?? '');
    $ativo  = (int)($t['ativo'] ?? 1);
    $origem = (int)($t['categoria_id'] ?? 0);
    $text   = $inCategoria ? $nome : "{$nome} ({$catNom})";
    if (!$ativo) {
        $text .= ' [inativo]';
    }

    return [
        'value' => $id,
        'text'  => $text,
        'attrs' => [
            'data-origem'          => (string)$origem,
            'data-categoria-nome'  => $catNom,
            'data-can-remove'      => $origem !== $categoriaId ? '1' : '0',
        ],
    ];
};
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:<?= $isEdit ? '920px' : '640px' ?>;">
    <div class="card card-primary">
      <div class="card-header">
        <h3 class="card-title"><?= e($titulo) ?></h3>
      </div>
      <form method="post" action="<?= $action ?>" id="form-categoria">
        <?php csrf_field(); ?>
        <div class="card-body">
          <?php if ($erro): ?>
            <div class="alert alert-danger"><?= e($erro) ?></div>
          <?php endif; ?>

          <div class="form-group">
            <label>Nome <span class="text-danger">*</span></label>
            <input name="nome" class="form-control" required
                   value="<?= e($cat['nome'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Descrição</label>
            <textarea name="descricao" class="form-control" rows="3"><?= e($cat['descricao'] ?? '') ?></textarea>
          </div>

          <?php if ($isEdit): ?>
          <hr>
          <?php
            dual_listbox([
                'id'           => 'cat-tipos',
                'label'        => 'Tipos de item',
                'leftLabel'    => 'No sistema (outras categorias)',
                'rightLabel'   => 'Nesta categoria',
                'left'         => array_map(static fn(array $t) => $mapTipoDualItem($t, false), $tiposDisponiveis),
                'right'        => array_map(static fn(array $t) => $mapTipoDualItem($t, true), $tiposNaCategoria),
                'selectedName' => 'tipo_ids',
                'metaNames'    => ['origem' => 'tipo_origem'],
                'contextId'    => $categoriaId,
                'formId'       => 'form-categoria',
            ]);
          ?>
          <?php else: ?>
          <hr>
          <p class="text-muted mb-0">
            <i class="fas fa-info-circle"></i>
            Salve a categoria para vincular tipos de item existentes.
          </p>
          <?php endif; ?>
        </div>
        <div class="card-footer text-right">
          <a href="<?= base_url('categorias') ?>" class="btn btn-secondary">Cancelar</a>
          <button class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</section>
