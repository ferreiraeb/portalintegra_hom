<?php /* Portal Progress Modal — modal de progresso bloqueante.
 * Incluído por views/layouts/adminlte.php (antes do </body>).
 *
 * Só pode ser fechado clicando no botão X — ESC e clique no backdrop são ignorados.
 * Enquanto aberto, window.onbeforeunload deve ser gerenciado pelo código chamador.
 *
 * window.PortalModal = { show, update, setTitle, hide, progressHtml }
 *
 *   show(title, bodyHtml)       — abre o modal com título e corpo
 *   update(bodyHtml)            — atualiza apenas o corpo (mantém título e modal aberto)
 *   setTitle(html)              — atualiza apenas o título
 *   hide()                      — fecha o modal programaticamente
 *   progressHtml(pct, msg)      — gera HTML com barra de progresso + aviso de navegação
 */ ?>
<!-- ── Portal Progress Modal ─────────────────────────────────────────────── -->
<div id="portal-progress-modal"
     class="modal fade"
     data-backdrop="static"
     data-keyboard="false"
     tabindex="-1"
     role="dialog"
     aria-labelledby="portal-modal-title-id"
     aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content shadow">

      <div class="modal-header" style="border-bottom:2px solid #007bff;">
        <h5 class="modal-title" id="portal-modal-title-id">
          <span id="portal-modal-title-text"><i class="fas fa-spinner fa-spin mr-2 text-primary"></i>Processando...</span>
        </h5>
        <!-- X fecha; backdrop e ESC estão desabilitados -->
        <button type="button"
                id="portal-modal-close-btn"
                class="close"
                data-dismiss="modal"
                aria-label="Fechar"
                title="Fechar (interrompe o processo)">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div id="portal-modal-body" class="modal-body">
        <!-- Conteúdo injetado pelo JS -->
      </div>

    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var modalEl  = document.getElementById('portal-progress-modal');
  var titleEl  = document.getElementById('portal-modal-title-text');
  var bodyEl   = document.getElementById('portal-modal-body');
  var closeBtn = document.getElementById('portal-modal-close-btn');

  /** Abre o modal com título e corpo. */
  function _show(title, bodyHtml) {
    if (titleEl) titleEl.innerHTML = title || 'Processando...';
    if (bodyEl)  bodyEl.innerHTML  = bodyHtml || '';
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
      jQuery(modalEl).modal({ backdrop: 'static', keyboard: false });
      jQuery(modalEl).modal('show');
    }
  }

  /** Atualiza apenas o corpo (mantém modal aberto e título). */
  function _update(bodyHtml) {
    if (bodyEl) bodyEl.innerHTML = bodyHtml || '';
  }

  /** Atualiza apenas o título. */
  function _setTitle(html) {
    if (titleEl) titleEl.innerHTML = html || '';
  }

  /** Fecha o modal programaticamente. */
  function _hide() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
      jQuery(modalEl).modal('hide');
    }
  }

  /**
   * Gera HTML de barra de progresso + aviso de navegação.
   * @param {number} pct  0–100
   * @param {string} msg  Mensagem de status
   */
  function _progressHtml(pct, msg) {
    pct = Math.max(0, Math.min(100, (pct | 0)));
    return (
      '<div class="progress mb-2" style="height:18px;border-radius:9px;overflow:hidden;">' +
        '<div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"' +
             ' role="progressbar"' +
             ' style="width:' + pct + '%;transition:width .4s ease;"' +
             ' aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
          pct + '%' +
        '</div>' +
      '</div>' +
      '<div class="mb-2 text-dark">' + (msg || 'Processando...') + '</div>' +
      '<div class="alert alert-warning py-1 px-2 mb-0 small">' +
        '<i class="fas fa-exclamation-triangle mr-1"></i>' +
        '<strong>Não navegue</strong> — fechar ou sair desta página <strong>interromperá</strong> o processo.' +
      '</div>'
    );
  }

  /* O botão X limpa o beforeunload para que o aviso do browser não apareça
     ao fechar intencionalmente via X. */
  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      window.onbeforeunload = null;
    });
  }

  window.PortalModal = {
    show:         _show,
    update:       _update,
    setTitle:     _setTitle,
    hide:         _hide,
    progressHtml: _progressHtml
  };
})();
</script>

