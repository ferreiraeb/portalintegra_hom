<?php
/**
 * RH — Composição de Remuneração
 *
 * @var \Support\ListTable $lt
 * @var array  $rows
 * @var int    $total
 * @var int    $from
 * @var int    $to
 * @var string|null $erro
 * @var array  $meta
 * @var bool   $hasCache
 * @var bool   $isRunning
 * @var int    $pollMs
 * @var int    $staleTicks
 */
$erro        = $erro ?? null;
$hasCache    = $hasCache ?? false;
$isRunning   = $isRunning ?? false;
$meta        = $meta ?? [];
$pollMs      = (int)($pollMs ?? 5000);
$staleTicks  = (int)($staleTicks ?? 12);

$fetchedLabel = null;
if (!empty($meta['fetched_at'])) {
    $ts = strtotime((string)$meta['fetched_at']);
    $fetchedLabel = $ts !== false ? date('d-m-Y H:i:s', $ts) : (string)$meta['fetched_at'];
}
$rowCountCache = (int)($meta['row_count'] ?? 0);

$reqBase = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?'));
$scheme  = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME']
         : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? '';
$path    = $reqBase ?: ('/' . ltrim(base_url('rh/composicao-remuneracao'), '/'));
if (strpos($path, 'http://') !== 0 && strpos($path, 'https://') !== 0) {
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    $absBase = $scheme . '://' . $host . rtrim($path, '/');
} else {
    $absBase = $path;
}
?>
<section class="content pt-3">
  <div class="container-fluid">

    <?php if ($erro): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="fas fa-exclamation-triangle mr-1"></i>Erro:</strong>
        <?= e($erro) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
    <?php endif; ?>

    <?php if (!$hasCache && !$isRunning): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle mr-1"></i>
        Ainda não há dados no SQL Server. Clique em <strong>Atualizar dados</strong> para sincronizar a view Oracle
        (grava em batches no Portal_Integra; a consulta pode levar bastante tempo).
      </div>
    <?php endif; ?>

    <form method="get" action="<?= base_url('rh/composicao-remuneracao') ?>" id="frmRhCompFilter">
      <input type="hidden" name="sort"     value="<?= e($lt->getSort()) ?>">
      <input type="hidden" name="dir"      value="<?= e($lt->getDir()) ?>">
      <input type="hidden" name="per_page" value="<?= $lt->getPerPage() ?>">
      <input type="hidden" name="page"     value="1">

      <div class="card">

        <div class="card-header d-flex align-items-center flex-wrap" style="gap:.5rem">
          <h3 class="card-title mb-0 mr-auto">Composição Remuneração</h3>

          <?php if ($fetchedLabel): ?>
            <span class="badge badge-light border text-muted" title="Última consulta salva">
              <i class="far fa-clock mr-1"></i>
              SQL: <?= e($fetchedLabel) ?>
              <?php if ($rowCountCache > 0): ?>
                · <?= number_format($rowCountCache, 0, ',', '.') ?> regs.
              <?php endif; ?>
            </span>
          <?php endif; ?>

          <button type="button" id="btnRhCompRefresh" class="btn btn-sm btn-primary"
                  <?= $isRunning ? 'disabled' : '' ?>>
            <i class="fas fa-sync-alt mr-1" id="btnRhCompRefreshIcon"></i>
            <span id="btnRhCompRefreshLabel"><?= $isRunning ? 'Atualizando...' : 'Atualizar dados' ?></span>
          </button>

          <button type="submit" class="btn btn-sm btn-secondary" <?= !$hasCache ? 'disabled' : '' ?>>
            <i class="fas fa-search mr-1"></i>Filtrar
          </button>

          <?php
            $exportParams = $lt->filterQueryParams();
            $exportParams['sort']   = $lt->getSort();
            $exportParams['dir']    = $lt->getDir();
            $exportParams['action'] = 'export';
          ?>
          <a href="<?= e(base_url('rh/composicao-remuneracao') . '?' . http_build_query($exportParams)) ?>"
             class="btn btn-sm btn-outline-secondary<?= !$hasCache ? ' disabled' : '' ?>"
             title="Exportar CSV completo (arquivo pronto) ou filtrado">
            <i class="fas fa-file-export mr-1"></i>Exportar CSV
          </a>

          <?php if ($lt->hasFilters()): ?>
            <a href="<?= base_url('rh/composicao-remuneracao') ?>" class="btn btn-sm btn-outline-secondary" title="Limpar filtros">
              <i class="fas fa-times mr-1"></i>Limpar
            </a>
          <?php endif; ?>
        </div>

        <div class="card-body p-0" id="rhCompTableWrap">
          <div class="table-scroll-top" id="tblRhCompScrollTop" aria-hidden="true">
            <div id="tblRhCompScrollSpacer"></div>
          </div>
          <div class="table-responsive table-scroll-body p-0" id="tblRhCompScrollBody">
            <table class="table table-hover table-sm mb-0" id="tblRhComp">
              <thead>
                <!-- Linha 1: cabeçalhos ordenáveis -->
                <tr>
                  <?php foreach ($lt->cols() as $key => $col): ?>
                    <th class="text-nowrap <?= e($col['th_class'] ?? '') ?>"><?= $lt->sortLink($key) ?></th>
                  <?php endforeach; ?>
                </tr>
                <!-- Linha 2: filtros por coluna (mesmo padrão de colaboradores) -->
                <tr class="thead-filter">
                  <?php foreach ($lt->cols() as $key => $col): ?>
                    <th class="p-1 <?= e($col['th_class'] ?? '') ?>"><?= $lt->filterInput($key) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($hasCache && !empty($rows)): ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <?php foreach ($lt->cols() as $colKey => $col): ?>
                        <td class="text-nowrap <?= e($col['th_class'] ?? '') ?>"><?php
                          echo isset($col['render'])
                              ? ($col['render'])($row[$colKey] ?? '')
                              : e((string)($row[$colKey] ?? ''));
                        ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="<?= count($lt->cols()) ?>" class="text-center text-muted py-4">
                      <?= $hasCache ? 'Nenhum registro encontrado com os filtros atuais.' : 'Sem dados em cache.' ?>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($hasCache && $total > 0): ?>
          <?= $lt->paginationFooter($total, $from, $to, 'registros', $lt->hasFilters()) ?>
        <?php endif; ?>

      </div>
    </form>
  </div>
</section>

<style>
#rhCompTableWrap .table-scroll-top {
  overflow-x: auto;
  overflow-y: hidden;
  border-bottom: 1px solid #dee2e6;
}
#rhCompTableWrap .table-scroll-top > div { height: 1px; }
#rhCompTableWrap #tblRhComp th,
#rhCompTableWrap #tblRhComp td {
  white-space: nowrap;
  vertical-align: middle;
}
#rhCompTableWrap .thead-filter th { background: #f8f9fa; }
#rhCompTableWrap .thead-filter input,
#rhCompTableWrap .thead-filter select {
  min-width: 96px;
  font-size: .8rem;
}
#rhCompTableWrap #tblRhComp th.col-rh-date,
#rhCompTableWrap #tblRhComp td.col-rh-date {
  min-width: 110px;
}
</style>

<script>
(function () {
  'use strict';

  var ABS_BASE   = <?= json_encode($absBase, JSON_UNESCAPED_UNICODE) ?>;
  var POLL_MS    = <?= (int)$pollMs ?>;
  var STALE_TICKS = <?= (int)$staleTicks ?>;
  var RESUME     = <?= $isRunning ? 'true' : 'false' ?>;
  var LS_KEY     = 'pi_rh_comp_refresh';

  // Esta página gerencia o modal — toast só no layout (outras páginas)
  window.rhCompPageActive = true;
  if (window.PortalToast && PortalToast.hide) {
    PortalToast.hide();
  }

  // Igual colaboradores: não envia inputs de filtro vazios na querystring
  var frm = document.getElementById('frmRhCompFilter');
  if (frm) {
    frm.addEventListener('submit', function () {
      Array.prototype.forEach.call(frm.elements, function (el) {
        if (el.tagName === 'INPUT' && el.value === '' && el.type !== 'hidden') {
          el.disabled = true;
        }
      });
    });
  }

  var btn      = document.getElementById('btnRhCompRefresh');
  var btnIcon  = document.getElementById('btnRhCompRefreshIcon');
  var btnLabel = document.getElementById('btnRhCompRefreshLabel');
  var polling  = false;

  function urlWith(qs) {
    var u = new URL(ABS_BASE, window.location.origin);
    Object.keys(qs || {}).forEach(function (k) { u.searchParams.set(k, qs[k]); });
    return u.toString();
  }

  function statusUrl() {
    return urlWith({ action: 'refresh_status' });
  }

  function setBusy(busy) {
    if (!btn) return;
    btn.disabled = !!busy;
    if (btnIcon) btnIcon.className = busy ? 'fas fa-spinner fa-spin mr-1' : 'fas fa-sync-alt mr-1';
    if (btnLabel) btnLabel.textContent = busy ? 'Atualizando...' : 'Atualizar dados';
  }

  function progressHtml(pct, msg) {
    if (window.PortalModal && PortalModal.progressHtml) {
      return PortalModal.progressHtml(pct, msg || '', true);
    }
    return '<div class="small">' + (msg || '') + ' (' + pct + '%)</div>';
  }

  function showModal(title, bodyHtml) {
    if (window.PortalModal) {
      PortalModal.show(title || '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i>Atualizando...', bodyHtml || '');
    }
  }

  function setProgress(pct, msg) {
    var html = progressHtml(pct, msg);
    if (window.PortalModal) {
      PortalModal.update(html);
    }
  }

  function showError(detail) {
    localStorage.removeItem(LS_KEY);
    var body = '<div class="alert alert-danger mb-0" style="white-space:pre-wrap;">' + (detail || 'Erro desconhecido') + '</div>';
    if (window.PortalModal) {
      PortalModal.show('<i class="fas fa-exclamation-triangle mr-2 text-danger"></i>Falha na atualização', body);
    } else {
      alert(detail || 'Erro na atualização');
    }
  }

  async function pollStatus() {
    if (polling) return;
    polling = true;
    setBusy(true);
    window.onbeforeunload = function () {
      return 'A sincronização está em andamento. Tem certeza que deseja sair?';
    };

    var stale = 0;
    var lastFingerprint = '';

    showModal(
      '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i>RH — Composição Remuneração',
      progressHtml(0, 'Aguardando progresso...')
    );

    try {
      while (true) {
        var resp = await fetch(statusUrl(), { credentials: 'same-origin', cache: 'no-store' });
        var j = await resp.json();
        if (!j || !j.ok) {
          throw new Error((j && j.msg) ? j.msg : 'Status inválido');
        }

        var pct = Math.max(0, Math.min(100, parseInt(j.progress, 10) || 0));
        var msg = j.message || 'Processando...';
        setProgress(pct, msg);

        var fp = String(j.done || 0) + '|' + (j.message || '') + '|' + (j.step || '');
        var workerBusy = j.worker_alive && (
          j.step === 'counting' || j.step === 'fetching' || j.step === 'writing'
            || j.step === 'streaming' || j.step === 'activating'
            || j.step === 'init' || j.step === 'queued'
        );

        if (workerBusy) {
          stale = 0;
          lastFingerprint = fp;
        } else if (fp === lastFingerprint) {
          stale++;
        } else {
          stale = 0;
          lastFingerprint = fp;
        }

        if (j.status === 'done') {
          localStorage.removeItem(LS_KEY);
          setProgress(100, 'Concluído. Recarregando...');
          setTimeout(function () { window.location.href = ABS_BASE; }, 600);
          return;
        }
        if (j.status === 'error') {
          throw new Error(j.error || j.message || 'Erro na atualização');
        }
        if (stale >= STALE_TICKS) {
          throw new Error('Sem progresso detectado (15 min). A operação pode ter sido interrompida.');
        }

        await new Promise(function (r) { setTimeout(r, POLL_MS); });
      }
    } catch (err) {
      setBusy(false);
      showError(err && err.message ? err.message : String(err));
    } finally {
      polling = false;
      window.onbeforeunload = null;
    }
  }

  async function startRefresh() {
    if (polling) return;
    setBusy(true);

    showModal(
      '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i>RH — Composição Remuneração',
      progressHtml(0, 'Solicitando atualização...')
    );

    try {
      var resp = await fetch(urlWith({ action: 'refresh_start' }), { credentials: 'same-origin' });
      var j = await resp.json();
      if (!j || !j.ok) {
        throw new Error((j && j.msg) ? j.msg : 'Falha ao iniciar');
      }

      if (j.already) {
        await pollStatus();
        return;
      }

      if (j.async === false) {
        localStorage.removeItem(LS_KEY);
        setProgress(100, 'Concluído. Recarregando...');
        setTimeout(function () { window.location.href = ABS_BASE; }, 500);
        return;
      }

      localStorage.setItem(LS_KEY, JSON.stringify({
        statusUrl: statusUrl(),
        startedAt: Date.now()
      }));

      await pollStatus();
    } catch (err) {
      setBusy(false);
      showError(err && err.message ? err.message : String(err));
    }
  }

  if (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      startRefresh();
    });
  }

  // Scroll sync (top + body)
  (function syncScroll() {
    var top = document.getElementById('tblRhCompScrollTop');
    var body = document.getElementById('tblRhCompScrollBody');
    var spacer = document.getElementById('tblRhCompScrollSpacer');
    var table = document.getElementById('tblRhComp');
    if (!top || !body || !spacer || !table) return;
    function syncWidth() { spacer.style.width = table.scrollWidth + 'px'; }
    syncWidth();
    window.addEventListener('resize', syncWidth);
    top.addEventListener('scroll', function () { body.scrollLeft = top.scrollLeft; });
    body.addEventListener('scroll', function () { top.scrollLeft = body.scrollLeft; });
  })();

  if (RESUME) {
    pollStatus();
  }
})();
</script>
