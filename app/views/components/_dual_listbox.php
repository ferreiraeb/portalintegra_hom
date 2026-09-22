<?php
/**
 * Dual listbox reutilizável — duas listas com botões de transferência.
 *
 * Preferir dual_listbox() em helpers.php. Variáveis esperadas:
 *
 * @var string      $dlbId
 * @var string|null $dlbLabel
 * @var string      $dlbLeftLabel
 * @var string      $dlbRightLabel
 * @var array       $dlbLeft       [['value' => '1', 'text' => '…', 'attrs' => ['data-foo' => 'bar']], …]
 * @var array       $dlbRight
 * @var string      $dlbSelectedName   Nome do campo POST (ex.: tipo_ids → tipo_ids[])
 * @var array       $dlbMetaNames      Mapa attr → campo POST (ex.: ['origem' => 'tipo_origem'] → tipo_origem[id])
 * @var int|string  $dlbContextId      Valor de contexto; meta com mesmo valor não é enviada
 * @var string|null $dlbFormId
 * @var int         $dlbSize
 * @var bool        $dlbFilter
 */

$dlbRenderOption = static function (array $item): string {
    $value = e((string)($item['value'] ?? ''));
    $text  = e((string)($item['text'] ?? ''));
    $attrs = '';
    foreach ($item['attrs'] ?? [] as $k => $v) {
        if (!is_string($k) || !str_starts_with($k, 'data-')) {
            continue;
        }
        $attrs .= ' ' . $k . '="' . e((string)$v) . '"';
    }

    return '<option value="' . $value . '"' . $attrs . '>' . $text . '</option>';
};

$dlbMetaJson = htmlspecialchars(json_encode($dlbMetaNames, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="form-group mb-0 dual-listbox-field">
  <?php if ($dlbLabel): ?>
    <label class="d-block" for="<?= e($dlbId) ?>-left"><?= e($dlbLabel) ?></label>
  <?php endif; ?>

  <div class="dual-listbox row"
       id="<?= e($dlbId) ?>"
       data-dual-listbox
       data-selected-name="<?= e($dlbSelectedName) ?>"
       data-meta-names="<?= $dlbMetaJson ?>"
       data-context-id="<?= e((string)$dlbContextId) ?>"
       <?= $dlbFormId ? 'data-form-id="' . e($dlbFormId) . '"' : '' ?>>
    <div class="col-md-5">
      <div class="dual-listbox__header"><?= e($dlbLeftLabel) ?></div>
      <?php if ($dlbFilter): ?>
      <input type="search" class="form-control form-control-sm dual-listbox__filter mb-1"
             placeholder="Filtrar..." data-side="left" autocomplete="off"
             aria-label="Filtrar <?= e($dlbLeftLabel) ?>">
      <?php endif; ?>
      <select multiple size="<?= (int)$dlbSize ?>" class="form-control dual-listbox__list dual-listbox__left"
              id="<?= e($dlbId) ?>-left" aria-label="<?= e($dlbLeftLabel) ?>">
        <?php foreach ($dlbLeft as $item): ?>
          <?= $dlbRenderOption($item) ?>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2 d-flex flex-column justify-content-center align-items-center dual-listbox__actions py-2">
      <button type="button" class="btn btn-outline-secondary btn-sm mb-2 dual-listbox__btn"
              data-action="add" title="Adicionar">
        <i class="fas fa-chevron-right"></i>
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm mb-2 dual-listbox__btn"
              data-action="add-all" title="Adicionar todos">
        <i class="fas fa-angle-double-right"></i>
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm mb-2 dual-listbox__btn"
              data-action="remove" title="Remover">
        <i class="fas fa-chevron-left"></i>
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm dual-listbox__btn"
              data-action="remove-all" title="Remover todos (permitidos)">
        <i class="fas fa-angle-double-left"></i>
      </button>
    </div>

    <div class="col-md-5">
      <div class="dual-listbox__header"><?= e($dlbRightLabel) ?></div>
      <?php if ($dlbFilter): ?>
      <input type="search" class="form-control form-control-sm dual-listbox__filter mb-1"
             placeholder="Filtrar..." data-side="right" autocomplete="off"
             aria-label="Filtrar <?= e($dlbRightLabel) ?>">
      <?php endif; ?>
      <select multiple size="<?= (int)$dlbSize ?>" class="form-control dual-listbox__list dual-listbox__right"
              id="<?= e($dlbId) ?>-right" aria-label="<?= e($dlbRightLabel) ?>">
        <?php foreach ($dlbRight as $item): ?>
          <?= $dlbRenderOption($item) ?>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="dual-listbox__hidden d-none" id="<?= e($dlbId) ?>-hidden"></div>
  </div>
</div>

<?php if (!defined('DUAL_LISTBOX_ASSETS_LOADED')): ?>
<?php define('DUAL_LISTBOX_ASSETS_LOADED', true); ?>
<style>
  .dual-listbox__header {
    font-weight: 600;
    font-size: .9rem;
    margin-bottom: .35rem;
  }
  .dual-listbox__list {
    min-height: 220px;
    font-size: .875rem;
  }
  .dual-listbox__actions .btn {
    width: 2.5rem;
  }
</style>
<script>
(function () {
  if (window.PortalDualListbox) {
    document.querySelectorAll('[data-dual-listbox]:not([data-dual-init])').forEach(function (el) {
      window.PortalDualListbox.init(el);
    });
    return;
  }

  function parseMetaNames(raw) {
    try { return JSON.parse(raw || '{}') || {}; } catch (e) { return {}; }
  }

  function optionTextForSide(opt, toRight) {
    var inativo = /\[inativo\]$/.test(opt.textContent) ? ' [inativo]' : '';
    var nome = opt.textContent.replace(/\s*\[inativo\]$/, '').replace(/\s*\([^)]+\)$/, '').trim();
    if (toRight) {
      return nome + inativo;
    }
    var suffix = opt.getAttribute('data-suffix-left') || '';
    if (!suffix) {
      var catNom = opt.getAttribute('data-categoria-nome') || '';
      suffix = catNom ? ' (' + catNom + ')' : '';
    }
    return nome + suffix + inativo;
  }

  function canRemove(opt) {
    return opt.getAttribute('data-can-remove') !== '0';
  }

  function sortSelect(sel) {
    var opts = Array.from(sel.options).sort(function (a, b) {
      return a.textContent.localeCompare(b.textContent, 'pt-BR', { sensitivity: 'base' });
    });
    sel.innerHTML = '';
    opts.forEach(function (o) { sel.appendChild(o); });
  }

  function initBox(box) {
    if (box.getAttribute('data-dual-init')) return;
    box.setAttribute('data-dual-init', '1');

    var selLeft   = box.querySelector('.dual-listbox__left');
    var selRight  = box.querySelector('.dual-listbox__right');
    var hidden    = box.querySelector('.dual-listbox__hidden');
    var fieldName = box.getAttribute('data-selected-name') || 'dual_selected';
    var metaNames = parseMetaNames(box.getAttribute('data-meta-names'));
    var contextId = String(box.getAttribute('data-context-id') || '');
    var formId    = box.getAttribute('data-form-id');
    var form      = formId ? document.getElementById(formId) : box.closest('form');

    function moveSelected(from, to, toRight) {
      Array.from(from.selectedOptions).forEach(function (opt) {
        if (!toRight && !canRemove(opt)) return;
        var clone = opt.cloneNode(true);
        clone.textContent = optionTextForSide(opt, toRight);
        clone.hidden = false;
        clone.removeAttribute('data-hidden');
        to.appendChild(clone);
        opt.remove();
      });
      sortSelect(to);
      syncHidden();
    }

    function moveAll(from, to, toRight, onlyRemovable) {
      Array.from(from.options).filter(function (opt) {
        return !onlyRemovable || canRemove(opt);
      }).forEach(function (opt) {
        var clone = opt.cloneNode(true);
        clone.textContent = optionTextForSide(opt, toRight);
        clone.hidden = false;
        clone.removeAttribute('data-hidden');
        to.appendChild(clone);
        opt.remove();
      });
      sortSelect(to);
      syncHidden();
    }

    function syncHidden() {
      if (!hidden) return;
      hidden.innerHTML = '';
      Array.from(selRight.options).forEach(function (opt) {
        var id = opt.value;
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = fieldName + '[]';
        inp.value = id;
        hidden.appendChild(inp);

        Object.keys(metaNames).forEach(function (attr) {
          var postName = metaNames[attr];
          var val = opt.getAttribute('data-' + attr);
          if (val === null || val === '') return;
          if (attr === 'origem' && contextId !== '' && String(val) === contextId) return;
          var meta = document.createElement('input');
          meta.type = 'hidden';
          meta.name = postName + '[' + id + ']';
          meta.value = val;
          hidden.appendChild(meta);
        });
      });
    }

    box.querySelectorAll('.dual-listbox__btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.getAttribute('data-action');
        if (action === 'add') moveSelected(selLeft, selRight, true);
        if (action === 'add-all') moveAll(selLeft, selRight, true, false);
        if (action === 'remove') moveSelected(selRight, selLeft, false);
        if (action === 'remove-all') moveAll(selRight, selLeft, false, true);
      });
    });

    box.querySelectorAll('.dual-listbox__filter').forEach(function (input) {
      input.addEventListener('input', function () {
        var target = input.getAttribute('data-side') === 'right' ? selRight : selLeft;
        var q = input.value.trim().toLowerCase();
        Array.from(target.options).forEach(function (opt) {
          var match = !q || opt.textContent.toLowerCase().indexOf(q) !== -1;
          opt.hidden = !match;
        });
      });
    });

    selLeft.addEventListener('dblclick', function () { moveSelected(selLeft, selRight, true); });
    selRight.addEventListener('dblclick', function () { moveSelected(selRight, selLeft, false); });

    if (form) {
      form.addEventListener('submit', syncHidden);
    }

    syncHidden();
  }

  window.PortalDualListbox = { init: initBox };

  function boot() {
    document.querySelectorAll('[data-dual-listbox]').forEach(initBox);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>
<?php endif; ?>
