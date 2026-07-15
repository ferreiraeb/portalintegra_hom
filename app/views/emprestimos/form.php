<?php
$e    = $emprestimo ?? [];
$erro = $erro ?? null;
$v    = fn(string $key, $def = '') => e($e[$key] ?? $def);
$preItem = $itemPreSelecionado ?? null; // ['id','descricao','tipo_nome','tipo_item_id'] or null
$itemSel = $itemSelecionado ?? null;
$tiposPorCategoria = $tiposPorCategoria ?? [];
$tipoInicial = (int)($preItem['tipo_item_id'] ?? $itemSel['tipo_item_id'] ?? 0);
$itemInicial   = $preItem ?? $itemSel;
$isDeterminado = (int)($preItem['is_determinado'] ?? $itemSel['is_determinado'] ?? 0);
if (!$isDeterminado && $tipoInicial) {
    foreach ($tiposPorCategoria as $tipos) {
        foreach ($tipos as $t) {
            if ((int)$t->id === $tipoInicial) {
                $isDeterminado = (int)$t->is_determinado;
                break 2;
            }
        }
    }
}
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

          <!-- Tipo de item + Item -->
          <?php if ($preItem): ?>
          <div class="form-group">
            <label>Item <span class="text-danger">*</span></label>
            <input type="hidden" name="item_id" value="<?= (int)$preItem['id'] ?>">
            <div class="form-control-plaintext border rounded px-3 py-2 bg-light">
              <strong><?= e($preItem['descricao']) ?></strong>
              <small class="text-muted ml-2"><?= e($preItem['tipo_nome']) ?></small>
            </div>
          </div>
          <?php else: ?>
          <div class="form-group">
            <label>Tipo de Item <span class="text-danger">*</span></label>
            <select id="tipo_item_id" class="form-control" required>
              <option value="">Selecione…</option>
              <?php foreach ($tiposPorCategoria as $nomeCat => $tipos): ?>
                <optgroup label="<?= e($nomeCat) ?>">
                  <?php foreach ($tipos as $t): ?>
                    <option value="<?= (int)$t->id ?>"
                      data-determinado="<?= (int)$t->is_determinado ?>"
                      <?= $tipoInicial === (int)$t->id ? 'selected' : '' ?>>
                      <?= e($t->nome) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group position-relative">
            <label>Item <span class="text-danger">*</span></label>
            <input type="text" id="item_busca" class="form-control"
                   placeholder="Clique para ver a lista ou digite para filtrar…"
                   autocomplete="off"
                   <?= $tipoInicial ? '' : 'disabled' ?>
                   value="<?= $itemInicial ? e($itemInicial['descricao']) : '' ?>">
            <div id="item_dropdown"
                 style="position:absolute;z-index:1050;width:100%;background:#fff;
                        border:1px solid #ced4da;border-radius:0 0 .25rem .25rem;
                        display:none;max-height:220px;overflow-y:auto;box-shadow:0 4px 8px rgba(0,0,0,.15);">
            </div>
            <input type="hidden" name="item_id" id="item_id"
                   value="<?= $itemInicial ? (int)$itemInicial['id'] : '' ?>">
            <small class="text-muted mt-1 d-block" id="item_hint">
              <?= $tipoInicial
                  ? 'Clique no campo para ver os itens disponíveis. Digite ao menos 3 caracteres para filtrar (descrição, placa, nº de série, linha, IMEI, etc.).'
                  : 'Selecione um tipo de item primeiro.' ?>
            </small>
          </div>
          <?php endif; ?>

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
              <input name="quantidade" id="quantidade" type="number" min="1" class="form-control<?= $isDeterminado ? ' bg-light' : '' ?>"
                     value="<?= $isDeterminado ? 1 : max(1, (int)($e['quantidade'] ?? 1)) ?>"
                     <?= $isDeterminado ? 'readonly' : '' ?>>
              <small class="text-muted<?= $isDeterminado ? '' : ' d-none' ?>" id="qtd_hint">
                Itens rastreáveis são emprestados um a um.
              </small>
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
  // ── Item (tipo + autocomplete) ──
  var selectTipo   = document.getElementById('tipo_item_id');
  var itemBusca    = document.getElementById('item_busca');
  var itemDropdown = document.getElementById('item_dropdown');
  var hiddenItemId = document.getElementById('item_id');
  var itemHint     = document.getElementById('item_hint');
  var qtdInput     = document.getElementById('quantidade');
  var qtdHint      = document.getElementById('qtd_hint');

  function setQuantidadeRastreavel(rastreavel) {
    if (!qtdInput) return;
    if (rastreavel) {
      qtdInput.value = 1;
      qtdInput.readOnly = true;
      qtdInput.classList.add('bg-light');
      if (qtdHint) qtdHint.classList.remove('d-none');
    } else {
      qtdInput.readOnly = false;
      qtdInput.classList.remove('bg-light');
      if (qtdHint) qtdHint.classList.add('d-none');
    }
  }

  function tipoSelecionadoRastreavel() {
    if (!selectTipo || !selectTipo.value) return false;
    var opt = selectTipo.options[selectTipo.selectedIndex];
    return opt && opt.getAttribute('data-determinado') === '1';
  }

  if (selectTipo) {
    var itemDebounce = null;
    var itemBaseUrl  = <?= json_encode(base_url('emprestimos/itens/autocomplete')) ?>;
    var itemCache    = null;
    var itemCacheTipo = '';

    function fecharItemDropdown() {
      itemDropdown.style.display = 'none';
      itemDropdown.innerHTML     = '';
    }

    function limparItemSelecao() {
      hiddenItemId.value = '';
      itemBusca.classList.remove('is-invalid');
    }

    function invalidarItemCache() {
      itemCache     = null;
      itemCacheTipo = '';
    }

    function renderItemOpcao(item) {
      var el = document.createElement('a');
      el.href      = '#';
      el.className = 'list-group-item list-group-item-action py-2 px-3';
      el.style.fontSize = '.9rem';
      el.textContent = item.label;
      el.addEventListener('mousedown', function (e) {
        e.preventDefault();
        hiddenItemId.value = item.id;
        itemBusca.value    = item.label;
        itemBusca.classList.remove('is-invalid');
        fecharItemDropdown();
      });
      return el;
    }

    function exibirItens(data, q) {
      fecharItemDropdown();
      if (!Array.isArray(data) || data.length === 0) {
        if (q.length >= 3) {
          var vazio = document.createElement('div');
          vazio.className = 'px-3 py-2 text-muted';
          vazio.style.fontSize = '.9rem';
          vazio.textContent = 'Nenhum item encontrado.';
          itemDropdown.appendChild(vazio);
          itemDropdown.style.display = 'block';
        }
        return;
      }
      data.forEach(function (item) {
        itemDropdown.appendChild(renderItemOpcao(item));
      });
      itemDropdown.style.display = 'block';
    }

    function fetchItens(q) {
      var tipoId = selectTipo.value;
      if (!tipoId) { fecharItemDropdown(); return Promise.resolve([]); }

      var url = itemBaseUrl + '?tipo_item_id=' + encodeURIComponent(tipoId);
      if (q.length >= 3) {
        url += '&q=' + encodeURIComponent(q);
      }

      return fetch(url)
        .then(function (r) { return r.json(); })
        .catch(function () { return []; });
    }

    function carregarListaCompleta() {
      var tipoId = selectTipo.value;
      if (!tipoId) { fecharItemDropdown(); return; }

      if (itemCache !== null && itemCacheTipo === tipoId) {
        exibirItens(itemCache, '');
        return;
      }

      fetchItens('')
        .then(function (data) {
          if (!Array.isArray(data)) data = [];
          itemCache     = data;
          itemCacheTipo = tipoId;
          exibirItens(data, '');
        });
    }

    function buscarItens(q) {
      if (!selectTipo.value) { fecharItemDropdown(); return; }

      if (q.length >= 3) {
        fetchItens(q).then(function (data) {
          exibirItens(Array.isArray(data) ? data : [], q);
        });
      } else {
        carregarListaCompleta();
      }
    }

    function abrirItemDropdown() {
      if (!selectTipo.value) return;
      buscarItens(itemBusca.value.trim());
    }

    selectTipo.addEventListener('change', function () {
      limparItemSelecao();
      itemBusca.value = '';
      invalidarItemCache();
      fecharItemDropdown();
      setQuantidadeRastreavel(tipoSelecionadoRastreavel());
      if (selectTipo.value) {
        itemBusca.disabled = false;
        itemBusca.placeholder = 'Clique para ver a lista ou digite para filtrar…';
        itemHint.textContent = 'Clique no campo para ver os itens disponíveis. Digite ao menos 3 caracteres para filtrar (descrição, placa, nº de série, linha, IMEI, etc.).';
      } else {
        itemBusca.disabled = true;
        itemBusca.placeholder = 'Selecione o tipo e pesquise o item…';
        itemHint.textContent = 'Selecione um tipo de item primeiro.';
      }
    });

    itemBusca.addEventListener('input', function () {
      clearTimeout(itemDebounce);
      limparItemSelecao();
      var q = itemBusca.value.trim();
      itemDebounce = setTimeout(function () { buscarItens(q); }, 300);
    });

    itemBusca.addEventListener('focus', abrirItemDropdown);
    itemBusca.addEventListener('click', abrirItemDropdown);

    document.addEventListener('click', function (e) {
      if (!itemBusca.contains(e.target) && !itemDropdown.contains(e.target)) {
        fecharItemDropdown();
      }
    });

    if (selectTipo.value) {
      setQuantidadeRastreavel(tipoSelecionadoRastreavel());
    }
  } else if (qtdInput && qtdInput.readOnly) {
    setQuantidadeRastreavel(true);
  }

  // ── Colaborador (autocomplete Oracle) ──
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
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        fecharDropdown();
        if (data && data.error) {
          info.className = 'text-danger mt-1 d-block';
          info.textContent = data.error;
          return;
        }
        if (!Array.isArray(data) || data.length === 0) {
          if (q !== '') {
            info.className = 'text-muted mt-1 d-block';
            info.textContent = 'Nenhum colaborador encontrado.';
          }
          return;
        }
        info.className = 'text-muted mt-1 d-block';
        info.textContent = '';
        data.forEach(function (item) {
          dropdown.appendChild(renderItem(item));
        });
        dropdown.style.display = 'block';
      })
      .catch(function () {
        fecharDropdown();
        info.className = 'text-danger mt-1 d-block';
        info.textContent = 'Erro ao buscar colaboradores. Tente novamente.';
      });
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

  // Validação antes do submit
  busca.closest('form').addEventListener('submit', function (e) {
    if (selectTipo && hiddenItemId && hiddenItemId.value === '') {
      e.preventDefault();
      itemBusca.classList.add('is-invalid');
      if (!selectTipo.value) {
        selectTipo.classList.add('is-invalid');
        selectTipo.focus();
      } else {
        itemBusca.focus();
      }
      return;
    }
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
