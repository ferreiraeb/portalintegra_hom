/**
 * Exports (globals):
 *   initTable(tbodyId, infoId, navId, filterBarId, resolveByName)
 *   initUpdateLoop(btnId, brandName, BASE, TOTAL_ROWS)
 *   initProcessModal(formSel, title, body)
 *   exportBody(pct, msg, done)
 */

/* ── Utilidade: escapa HTML para uso seguro em innerHTML ── */
function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/* ── Filtros + Paginação client-side ── */

/**
 * @param {string}      tbodyId
 * @param {string}      infoId
 * @param {string}      navId
 * @param {string|null} filterBarId
 * @param {boolean}     resolveByName
 */
function initTable(tbodyId, infoId, navId, filterBarId, resolveByName) {
  'use strict';

  var PER_PAGE_DEFAULT = 25;

  var tbody     = document.getElementById(tbodyId);
  var infoEl    = document.getElementById(infoId);
  var navEl     = document.getElementById(navId);
  var filterBar = filterBarId ? document.getElementById(filterBarId) : null;
  if (!tbody) return;

  if (resolveByName && filterBar) {
    var tbl = tbody.closest('table');
    if (tbl) {
      var headers = Array.from(tbl.querySelectorAll('thead th')).map(function (th) {
        return th.textContent.trim();
      });
      filterBar.querySelectorAll('[data-col-name]').forEach(function (inp) {
        var idx = headers.indexOf(inp.getAttribute('data-col-name'));
        inp.setAttribute('data-col', idx >= 0 ? idx : -1);
      });
    }
  }

  var allRows      = Array.from(tbody.querySelectorAll('tr'));
  var filteredRows = allRows.slice();
  var PER_PAGE     = PER_PAGE_DEFAULT;
  var cur          = 1;

  function getTerms() {
    if (!filterBar) return [];
    var terms = [];
    filterBar.querySelectorAll('input[data-col]').forEach(function (inp) {
      var val = inp.value.trim().toLowerCase();
      var col = parseInt(inp.getAttribute('data-col'), 10);
      if (val && col >= 0) terms.push({ val: val, col: col });
    });
    return terms;
  }

  function applyFilters() {
    var terms = getTerms();
    if (terms.length === 0) {
      filteredRows = allRows.slice();
    } else {
      filteredRows = allRows.filter(function (row) {
        var cells = row.querySelectorAll('td');
        return terms.every(function (t) {
          var cell = cells[t.col];
          return cell && cell.textContent.trim().toLowerCase().indexOf(t.val) !== -1;
        });
      });
    }
    render(1);
  }

  function render(p) {
    var total = filteredRows.length;
    var pages = Math.max(1, Math.ceil(total / PER_PAGE));
    cur = Math.max(1, Math.min(p, pages));
    var from = (cur - 1) * PER_PAGE;
    var to   = Math.min(from + PER_PAGE, total);

    allRows.forEach(function (r) { r.style.display = 'none'; });
    filteredRows.forEach(function (r, i) {
      r.style.display = (i >= from && i < to) ? '' : 'none';
    });

    if (infoEl) {
      if (total === 0) {
        infoEl.innerHTML = 'Nenhum item encontrado para os filtros aplicados.';
      } else {
        infoEl.innerHTML =
          'Exibindo <strong>' + (from + 1).toLocaleString('pt-BR') + '&ndash;' +
          to.toLocaleString('pt-BR') + '</strong> de <strong>' +
          total.toLocaleString('pt-BR') + '</strong> itens';
      }
    }

    if (navEl) {
      if (pages <= 1) { navEl.innerHTML = ''; return; }
      var pPrev = Math.max(1, cur - 1);
      var pNext = Math.min(pages, cur + 1);
      var d1 = cur === 1     ? ' disabled' : '';
      var dN = cur === pages ? ' disabled' : '';
      navEl.innerHTML =
        '<div class="d-flex align-items-center flex-wrap" style="gap:8px;">' +
        '<ul class="pagination pagination-sm mb-0">' +
        '<li class="page-item' + d1 + '"><a class="page-link" href="#" data-p="1">&laquo; Primeiro</a></li>' +
        '<li class="page-item' + d1 + '"><a class="page-link" href="#" data-p="' + pPrev + '">&lsaquo; Anterior</a></li>' +
        '<li class="page-item disabled"><span class="page-link">Pág. ' + cur + ' / ' + pages + '</span></li>' +
        '<li class="page-item' + dN + '"><a class="page-link" href="#" data-p="' + pNext + '">Próxima &rsaquo;</a></li>' +
        '<li class="page-item' + dN + '"><a class="page-link" href="#" data-p="' + pages + '">Última &raquo;</a></li>' +
        '</ul>' +
        '<div class="d-inline-flex align-items-center" style="gap:4px;">' +
        '<input type="number" min="1" max="' + pages + '" class="form-control form-control-sm" style="width:70px;" placeholder="Pág.">' +
        '<button type="button" class="btn btn-sm btn-outline-secondary">Ir</button>' +
        '</div>' +
        '</div>';
      navEl.querySelectorAll('a.page-link').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          render(parseInt(a.getAttribute('data-p'), 10));
        });
      });
      var gotoInp = navEl.querySelector('input[type="number"]');
      var gotoBtn = navEl.querySelector('button');
      if (gotoInp && gotoBtn) {
        gotoBtn.addEventListener('click', function () {
          var p2 = parseInt(gotoInp.value, 10);
          if (!isNaN(p2)) render(p2);
          gotoInp.value = '';
        });
        gotoInp.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            var p2 = parseInt(gotoInp.value, 10);
            if (!isNaN(p2)) render(p2);
            gotoInp.value = '';
          }
        });
      }
    }
  }

  /* Bind filter inputs */
  if (filterBar) {
    filterBar.querySelectorAll('input[data-col]').forEach(function (inp) {
      inp.addEventListener('input', applyFilters);
    });
    var clearBtn = filterBar.querySelector('.btn-filter-clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        filterBar.querySelectorAll('input[data-col]').forEach(function (i) { i.value = ''; });
        applyFilters();
      });
    }
    var ppSel = filterBar.querySelector('.select-pp');
    if (ppSel) {
      ppSel.addEventListener('change', function () {
        PER_PAGE = parseInt(ppSel.value, 10) || PER_PAGE_DEFAULT;
        render(1);
      });
    }
  }

  render(1);
}

/* Atualizar Valores: loop AJAX com PortalModal */

/**
 * @param {string} btnId
 * @param {string} brandName
 * @param {string} BASE
 * @param {number} TOTAL_ROWS
 */
function initUpdateLoop(btnId, brandName, BASE, TOTAL_ROWS) {
  'use strict';

  var isUpdating = false;
  var btnUpdate  = document.getElementById(btnId);
  if (!btnUpdate) return;

  btnUpdate.addEventListener('click', async function () {
    if (isUpdating) return;
    if (TOTAL_ROWS === 0) {
      alert('Nenhum item de relação para atualizar. Processe uma planilha primeiro.');
      return;
    }
    if (!confirm(
      'Atualizar preços de ' + TOTAL_ROWS.toLocaleString('pt-BR') + ' itens no DealerNet?\n\n' +
      'Não navegue durante o processo.'
    )) return;

    isUpdating = true;
    window.onbeforeunload = function () {
      return 'Atualizacao em andamento - sair ira interromper o processo.';
    };

    PortalModal.show(
      '<i class="fas fa-database mr-2 text-primary"></i> Atualizar Valores — ' + brandName,
      PortalModal.progressHtml(0, 'Iniciando...')
    );

    var offset = 0, auditOp = '', procOk = 0, procFail = 0, errs = [];
    var LIMIT  = 50;

    try {
      while (true) {
        var qs = 'action=update_chunk'
               + '&offset='   + offset
               + '&limit='    + LIMIT
               + '&audit_op=' + encodeURIComponent(auditOp);

        var resp = await fetch(BASE + '?' + qs, { cache: 'no-store' });
        if (!resp.ok) {
          var errTxt = '';
          try { errTxt = await resp.text(); } catch (_) {}
          throw new Error('HTTP ' + resp.status + (errTxt ? ': ' + errTxt : ''));
        }
        var j = await resp.json();
        if (!j.ok) throw new Error(j.msg || 'Erro desconhecido.');

        offset    = j.done;
        auditOp   = j.audit_op  || auditOp;
        procOk   += j.proc_ok   || 0;
        procFail += j.proc_fail || 0;
        if (j.errors && j.errors.length) {
          errs = errs.concat(j.errors);
          if (errs.length > 5) errs = errs.slice(0, 5);
        }

        var pct = j.total > 0 ? Math.round(offset / j.total * 100) : 0;
        PortalModal.update(PortalModal.progressHtml(
          pct,
          offset.toLocaleString('pt-BR') + ' / ' + (j.total || TOTAL_ROWS).toLocaleString('pt-BR') + ' itens'
        ));

        if (j.finished) break;
      }

      var alertType = procFail > 0 ? 'warning' : 'success';
      var doneTitle = procFail > 0 ? 'Concluído com falhas' : 'Concluído';
      var errDetail = '';
      if (errs.length) {
        errDetail = '<details class="mt-2">'
          + '<summary class="small text-muted">Detalhes (' + errs.length + ' erro(s))</summary>'
          + '<pre class="small mt-1" style="max-height:120px;overflow:auto;white-space:pre-wrap;">'
          + escHtml(errs.join('\n')) + '</pre></details>';
      }
      var doneBody =
        '<div class="alert alert-' + alertType + ' mb-2">'
        + '<strong>' + doneTitle + '</strong><br>'
        + procOk.toLocaleString('pt-BR') + ' preco(s) atualizado(s)'
        + (procFail > 0 ? ', <strong>' + procFail + ' falha(s)</strong>' : '') + '.'
        + (auditOp ? '<br><small class="text-white">ID auditoria: ' + auditOp + '</small>' : '')
        + '</div>'
        + errDetail;

      PortalModal.setTitle('<i class="fas fa-check-circle mr-2 text-success"></i> Atualizacao concluida');
      PortalModal.update(doneBody);

    } catch (err) {
      PortalModal.setTitle('<i class="fas fa-exclamation-circle mr-2 text-danger"></i> Erro na atualizacao');
      PortalModal.update(
        '<div class="alert alert-danger mb-0">'
        + '<strong>Erro:</strong> ' + escHtml(String(err.message || err))
        + '</div>'
      );
    } finally {
      isUpdating = false;
      window.onbeforeunload = null;
    }
  });
}

/* Exportar CSV: fetch modal */

/**
 * @param {number}  pct
 * @param {string}  msg
 * @param {boolean} done
 */
function exportBody(pct, msg, done) {
  var barCls = done
    ? 'progress-bar bg-success'
    : 'progress-bar progress-bar-striped progress-bar-animated bg-primary';
  return (
    '<div class="progress mb-3" style="height:20px;border-radius:10px;overflow:hidden;">' +
      '<div class="' + barCls + '"' +
           ' role="progressbar"' +
           ' style="width:' + pct + '%;transition:width .6s ease;"' +
           ' aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
        pct + '%' +
      '</div>' +
    '</div>' +
    '<p class="mb-3 text-dark">' + msg + '</p>' +
    (done ? '' :
      '<div class="alert alert-warning py-1 px-2 mb-0 small">' +
        '<i class="fas fa-exclamation-triangle mr-1"></i>' +
        '<strong>Não navegue</strong> — sair desta página enquanto o arquivo é gerado pode interromper o processo.' +
      '</div>'
    )
  );
}

/* Processar: modal bloqueante */

/**
 * @param {string} formSel
 * @param {string} title
 * @param {string} body
 */
function initProcessModal(formSel, title, body) {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector(formSel || 'form[method="post"]');
    if (!form) return;
    form.addEventListener('submit', function () {
      PortalModal.show(
        title || '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i> Processando planilha&hellip;',
        body  || '<div class="text-muted mb-2">Lendo arquivo XLSX e cruzando com DealerNet.</div>' +
                 '<div class="text-muted small">Aguarde — isso pode levar alguns segundos.</div>'
      );
    });
  });
}

/* CSV export handler */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('a[href*="export=1"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();

      var url = link.href;
      var pct = 2;

      PortalModal.show(
        '<i class="fas fa-file-export mr-2 text-secondary"></i>Exportar CSV',
        exportBody(pct, '<i class="fas fa-database mr-1 text-muted"></i>Consultando dados no banco…', false)
      );

      var animTimer = setInterval(function () {
        pct += Math.max(0.4, (90 - pct) * 0.045);
        if (pct >= 90) { pct = 90; clearInterval(animTimer); }
        PortalModal.update(exportBody(
          Math.round(pct),
          '<i class="fas fa-cog fa-spin mr-1 text-muted"></i>Gerando arquivo CSV… Isso pode levar alguns minutos para tabelas grandes.',
          false
        ));
      }, 900);

      fetch(url, { cache: 'no-store' })
        .then(function (response) {
          if (!response.ok) throw new Error('Erro HTTP ' + response.status);
          var disposition = response.headers.get('Content-Disposition') || '';
          var fnMatch = disposition.match(/filename="?([^";\r\n]+)"?/i);
          var filename = fnMatch ? fnMatch[1].trim() : 'export.csv';
          return response.blob().then(function (blob) {
            return { blob: blob, filename: filename };
          });
        })
        .then(function (result) {
          clearInterval(animTimer);

          var blobUrl = URL.createObjectURL(result.blob);
          var a = document.createElement('a');
          a.href = blobUrl;
          a.download = result.filename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 10000);

          PortalModal.update(exportBody(
            100,
            '<i class="fas fa-check-circle mr-1 text-success"></i>' +
            '<strong>Arquivo pronto!</strong> O download deve iniciar automaticamente.',
            true
          ));

          setTimeout(function () { PortalModal.hide(); }, 1500);
        })
        .catch(function (err) {
          clearInterval(animTimer);
          PortalModal.update(exportBody(
            0,
            '<i class="fas fa-times-circle mr-1 text-danger"></i>' +
            '<strong>Erro ao gerar o arquivo.</strong> ' + escHtml(err.message || ''),
            true
          ));
        });
    });
  });
});

