<?php /* Portal Toast — componente reutilizável de notificação flutuante.
 * Incluído por views/layouts/adminlte.php (antes do </body>).
 * Expõe window.PortalToast = { show, update, hide, progressHtml }.
 *
 * show(type, title, bodyHtml, autohideMs)
 *   type: 'info' | 'success' | 'danger' | 'warning'
 *
 * update(bodyHtml)          — atualiza só o corpo (mantém tipo/título)
 * hide()                    — oculta o toast
 * progressHtml(pct, msg, canNav) — gera HTML com barra de progresso
 */ ?>
<!-- ── Portal Toast ──────────────────────────────────────────────────────── -->
<div id="portal-toast"
     role="alert" aria-live="polite"
     style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:1090;
            min-width:280px;max-width:400px;display:none;">
  <div id="portal-toast-card" class="card card-outline mb-0 shadow"
       style="border-left:4px solid #007bff;">
    <div class="card-header py-2 d-flex align-items-center" style="background:#fff;">
      <span id="portal-toast-icon" class="mr-2"></span>
      <strong id="portal-toast-title" class="flex-grow-1 small text-uppercase"></strong>
      <button id="portal-toast-close" type="button"
              class="btn btn-sm p-0 ml-3 text-secondary" title="Fechar">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div id="portal-toast-body" class="card-body py-2 px-3 small"></div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var el       = document.getElementById('portal-toast');
  var card     = document.getElementById('portal-toast-card');
  var iconEl   = document.getElementById('portal-toast-icon');
  var titleEl  = document.getElementById('portal-toast-title');
  var bodyEl   = document.getElementById('portal-toast-body');
  var closeBtn = document.getElementById('portal-toast-close');
  var _timer   = null;

  var COLORS = {
    info:    '#007bff',
    success: '#28a745',
    danger:  '#dc3545',
    warning: '#ffc107'
  };
  var ICONS = {
    info:    '<i class="fas fa-sync fa-spin text-primary"></i>',
    success: '<i class="fas fa-check-circle text-success"></i>',
    danger:  '<i class="fas fa-exclamation-circle text-danger"></i>',
    warning: '<i class="fas fa-exclamation-triangle text-warning"></i>'
  };

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      _hide();
      if (_timer) { clearTimeout(_timer); _timer = null; }
    });
  }

  /**
   * Exibe (ou atualiza) o toast.
   * @param {string}      type        'info' | 'success' | 'danger' | 'warning'
   * @param {string}      titleText   Texto do cabeçalho
   * @param {string}      bodyHtml    HTML do corpo
   * @param {number|null} autohideMs  ms para auto-ocultar (null = não oculta)
   */
  function _show(type, titleText, bodyHtml, autohideMs) {
    type = type || 'info';
    if (card)    card.style.borderLeft  = '4px solid ' + (COLORS[type] || COLORS.info);
    if (iconEl)  iconEl.innerHTML       = ICONS[type]  || ICONS.info;
    if (titleEl) titleEl.textContent    = titleText    || '';
    if (bodyEl)  bodyEl.innerHTML       = bodyHtml     || '';
    if (el)      el.style.display       = 'block';
    if (_timer)  { clearTimeout(_timer); _timer = null; }
    if (autohideMs) _timer = setTimeout(_hide, autohideMs);
  }

  /** Atualiza apenas o corpo (mantém tipo/título/border da chamada anterior). */
  function _update(bodyHtml) {
    if (bodyEl) bodyEl.innerHTML = bodyHtml || '';
  }

  function _hide() {
    if (el) el.style.display = 'none';
  }

  /**
   * Gera HTML de barra de progresso para operações assíncronas.
   * @param {number}  pct    0–100
   * @param {string}  msg    Mensagem de status
   * @param {boolean} canNav true → "Pode navegar" | false → "Não feche esta aba"
   */
  function _progressHtml(pct, msg, canNav) {
    pct = Math.max(0, Math.min(100, (pct | 0)));
    return (pct > 0
        ? '<div class="progress mb-1" style="height:12px;border-radius:6px;overflow:hidden;">' +
            '<div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" ' +
            'style="width:' + pct + '%;transition:width .4s ease;"></div>' +
          '</div>'
        : '') +
      '<div>' + (msg || 'Processando...') + '</div>' +
      (canNav !== false
        ? '<small class="text-muted">Pode navegar normalmente.</small>'
        : '<small class="text-warning font-weight-bold">Não feche esta aba.</small>');
  }

  window.PortalToast = {
    show:         _show,
    update:       _update,
    hide:         _hide,
    progressHtml: _progressHtml
  };
})();
</script>

