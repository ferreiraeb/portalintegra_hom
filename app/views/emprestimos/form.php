<?php
$e    = $emprestimo ?? [];
$erro = $erro ?? null;
$v    = fn(string $key, $def = '') => e($e[$key] ?? $def);
$preItem = $itemPreSelecionado ?? null; // ['id','descricao','tipo_nome'] or null
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:720px;">
    <div class="card card-primary">
      <div class="card-header">
        <h3 class="card-title">Novo Empréstimo</h3>
      </div>

      <form method="post" action="<?= base_url('emprestimos/criar') ?>">
        <?php csrf_field(); ?>
        <?php if ($preItem): ?>
          <input type="hidden" name="_item_locked" value="1">
        <?php endif; ?>

        <div class="card-body">
          <?php if ($erro): ?>
            <div class="alert alert-danger"><?= e($erro) ?></div>
          <?php endif; ?>

          <!-- Item -->
          <div class="form-group">
            <label>Item <span class="text-danger">*</span></label>
            <?php if ($preItem): ?>
              <input type="hidden" name="item_id" value="<?= (int)$preItem['id'] ?>">
              <div class="form-control-plaintext border rounded px-3 py-2 bg-light">
                <strong><?= e($preItem['descricao']) ?></strong>
                <small class="text-muted ml-2"><?= e($preItem['tipo_nome']) ?></small>
              </div>
            <?php else: ?>
              <select name="item_id" class="form-control" required>
                <option value="">Selecione…</option>
                <?php
                $grupoAtual = '';
                foreach ($itensDisponiveis as $item):
                    if ($item['tipo_nome'] !== $grupoAtual) {
                        if ($grupoAtual !== '') echo '</optgroup>';
                        $grupoAtual = $item['tipo_nome'];
                        echo '<optgroup label="' . e($grupoAtual) . '">';
                    }
                ?>
                  <option value="<?= (int)$item['id'] ?>"
                    <?= ($e['item_id'] ?? 0) == $item['id'] ? 'selected' : '' ?>>
                    <?= e($item['descricao']) ?>
                  </option>
                <?php endforeach; ?>
                <?php if ($grupoAtual !== '') echo '</optgroup>'; ?>
              </select>
            <?php endif; ?>
          </div>

          <!-- Colaborador (autocomplete Oracle) -->
          <div class="form-group position-relative">
            <label>Colaborador <span class="text-danger">*</span></label>
            <input type="text" id="colab_busca" class="form-control"
                   placeholder="Pesquise por nome ou CPF…"
                   autocomplete="off"
                   value="<?= $v('colaborador_nome') ?>">
            <div id="colab_dropdown"
                 style="position:absolute;z-index:1050;width:100%;background:#fff;
                        border:1px solid #ced4da;border-radius:0 0 .25rem .25rem;
                        display:none;max-height:220px;overflow-y:auto;box-shadow:0 4px 8px rgba(0,0,0,.15);">
            </div>
            <input type="hidden" name="colaborador_nome" id="colaborador_nome"
                   value="<?= $v('colaborador_nome') ?>">
            <input type="hidden" name="colaborador_codpessoa" id="colaborador_codpessoa"
                   value="<?= $v('colaborador_codpessoa') ?>">
            <?php if (!empty($e['colaborador_nome'])): ?>
              <small class="text-success mt-1 d-block" id="colab_info">
                <i class="fas fa-check-circle mr-1"></i><?= e($e['colaborador_nome']) ?>
                <?php if (!empty($e['colaborador_codpessoa'])): ?>
                  <span class="text-muted">(cód. <?= e($e['colaborador_codpessoa']) ?>)</span>
                <?php endif; ?>
              </small>
            <?php else: ?>
              <small class="text-muted mt-1 d-block" id="colab_info"></small>
            <?php endif; ?>
          </div>

          <div class="row">
            <div class="col-md-4 form-group">
              <label>Quantidade</label>
              <input name="quantidade" type="number" min="1" class="form-control"
                     value="<?= max(1, (int)($e['quantidade'] ?? 1)) ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Data de entrega <span class="text-danger">*</span></label>
              <input name="data_entrega" type="date" class="form-control" required
                     value="<?= $v('data_entrega', date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4 form-group">
              <label>Prev. devolução</label>
              <input name="data_prevista_devolucao" type="date" class="form-control"
                     value="<?= $v('data_prevista_devolucao') ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Observação</label>
            <textarea name="observacao" class="form-control" rows="2"><?= $v('observacao') ?></textarea>
          </div>
        </div>

        <div class="card-footer text-right">
          <a href="<?= base_url($preItem ? 'itens/'.(int)$preItem['id'] : 'emprestimos') ?>"
             class="btn btn-secondary">Cancelar</a>
          <button class="btn btn-primary"><i class="fas fa-check mr-1"></i>Emprestar</button>
        </div>
      </form>
    </div>
  </div>
</section>

<script>
(function () {
  var busca       = document.getElementById('colab_busca');
  var dropdown    = document.getElementById('colab_dropdown');
  var hiddenNome  = document.getElementById('colaborador_nome');
  var hiddenCod   = document.getElementById('colaborador_codpessoa');
  var info        = document.getElementById('colab_info');
  var debounce    = null;
  var baseUrl     = <?= json_encode(base_url('hr/colaboradores/autocomplete')) ?>;

  function fecharDropdown() {
    dropdown.style.display = 'none';
    dropdown.innerHTML     = '';
  }

  function renderItem(item) {
    var el = document.createElement('a');
    el.href      = '#';
    el.className = 'list-group-item list-group-item-action py-2 px-3';
    el.style.fontSize = '.9rem';
    el.textContent = item.label;
    el.addEventListener('mousedown', function (e) {
      e.preventDefault();
      hiddenNome.value = item.nome;
      hiddenCod.value  = item.codpessoa;
      busca.value      = item.label;
      info.className   = 'text-success mt-1 d-block';
      info.innerHTML   = '<i class="fas fa-check-circle mr-1"></i>' +
                         escHtml(item.nome) +
                         ' <span class="text-muted">(cód. ' + escHtml(item.codpessoa) + ')</span>';
      fecharDropdown();
    });
    return el;
  }

  function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
  }

  function buscar(q) {
    fetch(baseUrl + '?q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        fecharDropdown();
        if (!Array.isArray(data) || data.length === 0) return;
        data.forEach(function (item) {
          dropdown.appendChild(renderItem(item));
        });
        dropdown.style.display = 'block';
      })
      .catch(function () { fecharDropdown(); });
  }

  busca.addEventListener('input', function () {
    clearTimeout(debounce);
    var q = busca.value.trim();
    // Limpa seleção ao digitar
    hiddenNome.value = '';
    hiddenCod.value  = '';
    info.className   = 'text-muted mt-1 d-block';
    info.textContent = '';
    if (q === '') { fecharDropdown(); return; }
    debounce = setTimeout(function () { buscar(q); }, 300);
  });

  busca.addEventListener('focus', function () {
    if (busca.value.trim() === '') buscar('');
  });

  document.addEventListener('click', function (e) {
    if (!busca.contains(e.target) && !dropdown.contains(e.target)) fecharDropdown();
  });

  // Validação antes do submit: garante que um colaborador foi selecionado
  busca.closest('form').addEventListener('submit', function (e) {
    if (hiddenNome.value === '' || hiddenCod.value === '') {
      e.preventDefault();
      busca.classList.add('is-invalid');
      busca.focus();
      var fb = busca.parentNode.querySelector('.invalid-feedback');
      if (!fb) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        fb.textContent = 'Selecione um colaborador da lista.';
        busca.parentNode.insertBefore(fb, dropdown.nextSibling);
      }
    } else {
      busca.classList.remove('is-invalid');
    }
  });
})();
</script>
