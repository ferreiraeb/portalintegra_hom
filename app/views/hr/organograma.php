<?php
?>
<section class="content pt-2">
  <div class="container-fluid h-100">

    <!-- ── Card shell (fills viewport height) ───────────────────────────── -->
    <div class="card mb-0" style="display:flex;flex-direction:column;height:calc(100vh - 120px);">

      <!-- ── Toolbar ──────────────────────────────────────────── -->
      <div class="card-header d-flex align-items-center py-2" style="flex-shrink:0;gap:.5rem;">
        <h3 class="card-title mb-0 mr-auto">Organograma</h3>

      <!-- Search -->
      <div id="org-search" style="position:relative;margin-left:.5rem;">
          <input id="org-search-input" type="text" class="form-control form-control-sm"
                 placeholder="Buscar colaborador…" autocomplete="off" style="width:220px;">
          <ul id="org-search-dropdown" class="list-unstyled"
              style="display:none;position:absolute;top:100%;left:0;width:300px;background:#fff;
        border:1px solid #ced4da;border-radius:4px;z-index:9999;max-height:260px;
        overflow-y:auto;margin:2px 0 0;padding:4px 0;box-shadow:0 4px 12px rgba(0,0,0,.15);"></ul>
      </div>

        <!-- Zoom controls -->
        <button id="btn-zoom-out" class="btn btn-sm btn-outline-secondary" title="Diminuir zoom"><i class="fas fa-minus"></i></button>
        <span id="zoom-label" class="small text-muted" style="min-width:42px;text-align:center;">100%</span>
        <button id="btn-zoom-in"  class="btn btn-sm btn-outline-secondary" title="Aumentar zoom"><i class="fas fa-plus"></i></button>
        <button id="btn-reset"       class="btn btn-sm btn-outline-primary ml-1" title="Centralizar">
          <i class="fas fa-compress-arrows-alt mr-1"></i>Centralizar
        </button>
        <button id="btn-expand-all"  class="btn btn-sm btn-outline-secondary ml-1" title="Expandir tudo">
          <i class="fas fa-expand-alt mr-1"></i>Expandir tudo
        </button>
        <button id="btn-collapse-all" class="btn btn-sm btn-outline-secondary ml-1" title="Colapsar tudo">
          <i class="fas fa-compress-alt mr-1"></i>Colapsar tudo
        </button>
        <button id="btn-orphans" class="btn btn-sm btn-outline-warning ml-1" title="Colaboradores com líder inativo">
          <i class="fas fa-exclamation-triangle mr-1"></i>Líderes inativos
        </button>

      </div>

      <!-- ── Stats bar ─────────────────────────────────────────────── -->
      <div id="org-stats" style="flex-shrink:0;display:flex;gap:1.5rem;padding:.35rem 1rem;
           background:#f8f9fa;border-bottom:1px solid #dee2e6;font-size:.78rem;color:#6c757d;">
        <span><i class="fas fa-users text-primary mr-1"></i><strong id="stat-colaboradores">0</strong> Colaboradores visíveis</span>
        <span><i class="fas fa-layer-group text-success mr-1"></i><strong id="stat-niveis">0</strong> Níveis expandidos</span>
        <span><i class="fas fa-building text-warning mr-1"></i><strong id="stat-empresas">0</strong> Empresas</span>
      </div>

      <!-- ── Canvas area ───────────────────────────────────────────────────── -->
      <div class="card-body p-0" style="flex:1;overflow:hidden;position:relative;">

        <!-- Loading -->
        <div id="org-loading" style="position:absolute;inset:0;display:flex;align-items:center;
             justify-content:center;background:rgba(255,255,255,.85);z-index:20;">
          <div class="text-center text-muted">
            <div class="spinner-border text-secondary mb-2" role="status" style="width:2rem;height:2rem;">
              <span class="sr-only">Carregando…</span></div>
            <div>Carregando organograma…</div>
          </div>
        </div>

        <!-- Error -->
        <div id="org-error" style="display:none;position:absolute;inset:0;align-items:center;
             justify-content:center;z-index:20;">
          <div class="alert alert-danger mb-0" style="max-width:500px;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <span id="org-error-msg">Erro ao carregar o organograma.</span>
          </div>
        </div>

        <!-- org-root: clip boundary + pan/zoom event target -->
        <div id="org-root" style="position:relative;width:100%;height:100%;overflow:hidden;background:#f0f4f8;cursor:grab;">
          <div id="org-canvas" style="position:absolute;top:0;left:0;transform-origin:0 0;will-change:transform;">
            <div id="org-nodes" style="position:relative;"></div>
            <svg id="org-svg" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible;width:1px;height:1px;"></svg>
          </div>

          <!-- Minimap -->
          <div id="org-minimap" style="position:absolute;bottom:16px;right:16px;
               background:rgba(255,255,255,.92);border:1px solid #ccc;border-radius:6px;
               box-shadow:0 2px 8px rgba(0,0,0,.15);padding:4px;z-index:10;">
            <canvas id="org-minimap-canvas" width="180" height="120" style="display:block;cursor:crosshair;"></canvas>
          </div>
        </div>

      </div>
    </div>

  </div>
</section>

<!-- ── Líderes Inativos Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="modal-orphans" tabindex="-1" role="dialog" aria-labelledby="modal-orphans-title" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-orphans-title">
          <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
          Colaboradores com líder inativo ou desligado
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-0">
        <!-- Loading state -->
        <div id="orphans-loading" class="text-center text-muted py-5">
          <div class="spinner-border text-secondary mb-2" role="status" style="width:2rem;height:2rem;">
            <span class="sr-only">Carregando…</span>
          </div>
          <div>Carregando…</div>
        </div>
        <!-- Error state -->
        <div id="orphans-error" class="alert alert-danger m-3" style="display:none;"></div>
        <!-- Results -->
        <div id="orphans-results" style="display:none;">
          <div class="px-3 pt-2 pb-1 d-flex align-items-center" style="gap:.75rem;">
            <span class="text-muted small">
              <strong id="orphans-count">0</strong> colaborador(es) encontrado(s)
            </span>
            <input id="orphans-filter" type="text" class="form-control form-control-sm ml-auto"
              placeholder="Filtrar por nome, cargo…" style="max-width:260px;" autocomplete="off">
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered mb-0" style="font-size:.82rem;">
              <thead class="thead-light">
                <tr>
                  <th>Colaborador</th>
                  <th>Cargo</th>
                  <th>Empresa</th>
                  <th>Unidade</th>
                  <th>Líder</th>
                  <th>Cargo do Líder</th>
                  <th>Status Líder</th>
                  <th>Rescisão Líder</th>
                </tr>
              </thead>
              <tbody id="orphans-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<style>
/* ── Cards ──────────────────────────────────────────────── */
.org-card {
  position:absolute; width:220px; background:#fff; border-radius:8px;
  border-left:4px solid #1a3c5e; box-shadow:0 2px 8px rgba(0,0,0,.12);
  padding:10px 12px 18px; cursor:default;
  transition:box-shadow .15s; user-select:none; box-sizing:border-box;
}
.org-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.22); }
.org-card__avatar { float:left; margin-right:8px; font-size:1.55rem; color:#1a3c5e; line-height:1; }
.org-card__body   { overflow:hidden; }
.org-card__name   { font-weight:700; font-size:.82rem; color:#1a3c5e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.org-card__role   { font-size:.72rem; color:#555; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:1px; }
.org-card__meta   { margin-top:4px; display:flex; gap:3px; flex-wrap:wrap; }
.org-card__meta .badge { font-size:.62rem; max-width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* ── Expand button ───────────────────────────────────────── */
.org-expand-btn {
  position:absolute; bottom:-13px; left:50%; transform:translateX(-50%);
  background:#1a3c5e; color:#fff; border:none; border-radius:12px;
  padding:2px 10px; font-size:10px; cursor:pointer;
  display:flex; align-items:center; gap:4px; z-index:2; white-space:nowrap;
}
.org-expand-btn:hover { background:#0d2440; }
.org-expand-btn .fa-chevron-up { display:none; }
.org-card.expanded .org-expand-btn .fa-chevron-down { display:none; }
.org-card.expanded .org-expand-btn .fa-chevron-up   { display:inline; }

/* ── Search highlight ────────────────────────────────────── */
.org-highlight { animation:org-pulse .45s ease 3; }
@keyframes org-pulse {
  0%,100% { box-shadow:0 0 0 0 rgba(26,60,94,.7); }
  50%     { box-shadow:0 0 0 10px rgba(26,60,94,0); }
}

/* ── Search dropdown ─────────────────────────────────────── */
#org-search-dropdown li { padding:6px 12px; cursor:pointer; border-bottom:1px solid #f0f0f0; font-size:.82rem; }
#org-search-dropdown li:last-child { border-bottom:none; }
#org-search-dropdown li:hover { background:#f0f4f8; }
</style>

<script>
(function () {
  'use strict';

  // ── Constants ─────────────────────────────────────────────────────────────
  var CARD_W   = 220, CARD_H   = 100;
  var H_GAP    = 40,  V_GAP    = 80;
  var ZOOM_MIN = 0.3, ZOOM_MAX = 2.0, ZOOM_STEP = 0.1;
  var MM_W = 180,     MM_H = 120;
  var BASE_URL = <?= json_encode(base_url('hr/organograma')) ?>;
  var SVG_NS   = 'http://www.w3.org/2000/svg';

  // ── Global state ──────────────────────────────────────────────────────────
  window.OrgChart = {
    nodes:     new Map(),
    layout:    new Map(),
    expanded:  new Set(),
    visible:   new Set(),
    transform: { tx: 0, ty: 0, scale: 1 }
  };
  var fetchedIds  = new Set();
  var fetchingIds = new Set();

  // ── DOM refs ──────────────────────────────────────────────────────────────
  var rootEl    = document.getElementById('org-root');
  var canvasEl  = document.getElementById('org-canvas');
  var nodesEl   = document.getElementById('org-nodes');
  var svgEl     = document.getElementById('org-svg');
  var zoomLabel = document.getElementById('zoom-label');
  var mmCanvas  = document.getElementById('org-minimap-canvas');
  var mmCtx     = mmCanvas ? mmCanvas.getContext('2d') : null;

  // ════════════════════════════════════════════════════════════════════════════
  // Layout + Render
  // ════════════════════════════════════════════════════════════════════════════

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function getDirectChildren(pid) {
    var r = [];
    OrgChart.visible.forEach(function(id){ var n=OrgChart.nodes.get(id); if(n&&n.pid===pid) r.push(id); });
    return r;
  }

  function computeSubtreeWidth(id) {
    if (!OrgChart.expanded.has(id)) return CARD_W;
    var ch = getDirectChildren(id);
    if (!ch.length) return CARD_W;
    var total = ch.reduce(function(s,cid){ return s+computeSubtreeWidth(cid); }, 0);
    return Math.max(CARD_W, total + H_GAP*(ch.length-1));
  }

  function layoutSubtree(id, x, y) {
    var sw = computeSubtreeWidth(id);
    OrgChart.layout.set(id, { x: x+(sw-CARD_W)/2, y: y, w: CARD_W, h: CARD_H });
    if (OrgChart.expanded.has(id)) {
      var ch = getDirectChildren(id);
      var cx = x, cy = y+CARD_H+V_GAP;
      ch.forEach(function(cid){ layoutSubtree(cid,cx,cy); cx+=computeSubtreeWidth(cid)+H_GAP; });
    }
  }

  function layoutTree() {
    OrgChart.layout.clear();
    var roots = [];
    OrgChart.visible.forEach(function(id){ var n=OrgChart.nodes.get(id); if(n&&n.pid===null) roots.push(id); });
    var x = 0;
    roots.forEach(function(id){ layoutSubtree(id,x,0); x+=computeSubtreeWidth(id)+H_GAP; });
  }

  function createCardEl(node) {
    var div = document.createElement('div');
    div.className = 'org-card'; div.dataset.id = node.id;
    var em = node.empresa ? '<span class="badge badge-secondary" title="'+esc(node.empresa)+'">'+esc(node.empresa)+'</span>' : '';
    var un = node.unidade ? '<span class="badge badge-light"     title="'+esc(node.unidade)+'">'+esc(node.unidade)+'</span>' : '';
    div.innerHTML =
      '<div class="org-card__avatar"><i class="fas fa-user-circle"></i></div>'+
      '<div class="org-card__body">'+
        '<div class="org-card__name" title="'+esc(node.nome) +'">'+esc(node.nome) +'</div>'+
        '<div class="org-card__role" title="'+esc(node.cargo)+'">'+esc(node.cargo)+'</div>'+
        '<div class="org-card__meta">'+em+un+'</div>'+
      '</div>';
    if (node.hasChildren) appendExpandBtn(div, node);
    return div;
  }

  function renderNodes() {
    // Add new
    OrgChart.visible.forEach(function(id){
      if (!document.querySelector('.org-card[data-id="'+id+'"]')) {
        var n = OrgChart.nodes.get(id);
        if (n) nodesEl.appendChild(createCardEl(n));
      }
    });
    // Remove stale
    nodesEl.querySelectorAll('.org-card').forEach(function(el){
      if (!OrgChart.visible.has(el.dataset.id)) el.remove();
    });
    // Position + state
    OrgChart.visible.forEach(function(id){
      var l=OrgChart.layout.get(id), el=document.querySelector('.org-card[data-id="'+id+'"]');
      if (!l||!el) return;
      el.style.left = l.x+'px'; el.style.top = l.y+'px';
      el.classList.toggle('expanded', OrgChart.expanded.has(id));
      // Sync icon — only if not currently fetching (loading state is set by handleExpandToggle)
      if (!fetchingIds.has(id)) {
        var expBtn=el.querySelector('.org-expand-btn');
        if (expBtn && !expBtn.querySelector('.fa-spinner')) {
          setExpandBtnState(id, OrgChart.expanded.has(id) ? 'expanded' : 'collapsed');
        }
      }
    });
    requestAnimationFrame(function(){ redrawLines(); drawMinimap(); updateStats(); });
  }

  // ════════════════════════════════════════════════════════════════════════════
  // SVG Lines
  // ════════════════════════════════════════════════════════════════════════════

  function styleConn(p){
    p.setAttribute('stroke','#1a3c5e'); p.setAttribute('stroke-width','2');
    p.setAttribute('fill','none');       p.setAttribute('stroke-linecap','round');
  }

  function groupConnectors(pid) {
    var pl = OrgChart.layout.get(pid); if (!pl) return [];
    var ch = getDirectChildren(pid);   if (!ch.length) return [];
    var paths=[], px=pl.x+CARD_W/2, py=pl.y+CARD_H, midY=py+V_GAP/2;
    var centers = ch.map(function(cid){ var cl=OrgChart.layout.get(cid); return cl?{x:cl.x+CARD_W/2,y:cl.y}:null; }).filter(Boolean);
    if (!centers.length) return [];
    var p1=document.createElementNS(SVG_NS,'path'); p1.setAttribute('d','M '+px+' '+py+' V '+midY); styleConn(p1); paths.push(p1);
    if (centers.length>1) {
      var xs=centers.map(function(c){return c.x;}), lx=Math.min.apply(null,xs), rx=Math.max.apply(null,xs);
      var p2=document.createElementNS(SVG_NS,'path'); p2.setAttribute('d','M '+lx+' '+midY+' H '+rx); styleConn(p2); paths.push(p2);
    }
    centers.forEach(function(c){ var p=document.createElementNS(SVG_NS,'path'); p.setAttribute('d','M '+c.x+' '+midY+' V '+c.y); styleConn(p); paths.push(p); });
    return paths;
  }

  function redrawLines() {
    while (svgEl.firstChild) svgEl.removeChild(svgEl.firstChild);
    OrgChart.expanded.forEach(function(id){ groupConnectors(id).forEach(function(p){ svgEl.appendChild(p); }); });
  }

  // ════════════════════════════════════════════════════════════════════════════
  // Pan + Zoom
  // ════════════════════════════════════════════════════════════════════════════

  function applyTransform() {
    var t=OrgChart.transform;
    canvasEl.style.transform='translate('+t.tx+'px,'+t.ty+'px) scale('+t.scale+')';
    if (zoomLabel) zoomLabel.textContent=Math.round(t.scale*100)+'%';
    drawMinimap();
  }

  function zoomTo(ns, px, py) {
    var t=OrgChart.transform; ns=Math.min(ZOOM_MAX,Math.max(ZOOM_MIN,ns));
    t.tx=px-(px-t.tx)*(ns/t.scale); t.ty=py-(py-t.ty)*(ns/t.scale); t.scale=ns; applyTransform();
  }

  function resetView() {
    var roots=[]; OrgChart.visible.forEach(function(id){ var n=OrgChart.nodes.get(id); if(n&&n.pid===null) roots.push(id); });
    if (!roots.length) { OrgChart.transform={tx:40,ty:40,scale:1}; applyTransform(); return; }
    var tw=-H_GAP; roots.forEach(function(id){ tw+=computeSubtreeWidth(id)+H_GAP; });
    OrgChart.transform.tx=(rootEl.clientWidth-tw)/2; OrgChart.transform.ty=40; OrgChart.transform.scale=1; applyTransform();
  }

  function initPanZoom() {
    var drag=false, lx=0, ly=0;
    rootEl.addEventListener('mousedown',function(e){
      if (e.target.closest('.org-card')||e.target.closest('#org-minimap')||e.target.closest('#org-search')) return;
      drag=true; lx=e.clientX; ly=e.clientY; rootEl.style.cursor='grabbing'; e.preventDefault();
    });
    document.addEventListener('mousemove',function(e){
      if (!drag) return;
      OrgChart.transform.tx+=e.clientX-lx; OrgChart.transform.ty+=e.clientY-ly; lx=e.clientX; ly=e.clientY; applyTransform();
    });
    document.addEventListener('mouseup',function(){ drag=false; rootEl.style.cursor='grab'; });
    rootEl.addEventListener('wheel',function(e){
      e.preventDefault();
      var r=rootEl.getBoundingClientRect();
      zoomTo(OrgChart.transform.scale+(e.deltaY<0?ZOOM_STEP:-ZOOM_STEP), e.clientX-r.left, e.clientY-r.top);
    },{passive:false});
    function cp(){ return {x:rootEl.clientWidth/2,y:rootEl.clientHeight/2}; }
    var bzi=document.getElementById('btn-zoom-in'),  bzo=document.getElementById('btn-zoom-out'), brs=document.getElementById('btn-reset');
    var bex=document.getElementById('btn-expand-all'), bcl=document.getElementById('btn-collapse-all');
    if (bzi) bzi.addEventListener('click',function(){ var c=cp(); zoomTo(OrgChart.transform.scale+ZOOM_STEP,c.x,c.y); });
    if (bzo) bzo.addEventListener('click',function(){ var c=cp(); zoomTo(OrgChart.transform.scale-ZOOM_STEP,c.x,c.y); });
    if (brs) brs.addEventListener('click', resetView);
    if (bcl) bcl.addEventListener('click', function(){
      // Collapse all: hide all children of every expanded node from the root level
      var roots=[];
      OrgChart.visible.forEach(function(id){ var n=OrgChart.nodes.get(id); if(n&&n.pid===null) roots.push(id); });
      roots.forEach(function(id){ collapseSubtree(id); });
      layoutTree(); renderNodes(); resetView();
    });
    if (bex) bex.addEventListener('click', function(){
      showLoading('Expandindo organograma…');

      function finish() {
        hideLoading();
        layoutTree(); renderNodes(); resetView();
      }

      // Single request — server returns every active employee at once.
      fetch(BASE_URL + '?format=json&expand_all=1')
        .then(function(r){ if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
        .then(function(data){
          if (data && data.error) throw new Error(data.error);
          data.forEach(function(n){
            OrgChart.nodes.set(n.id, n);
            OrgChart.visible.add(n.id);
            if (n.hasChildren) OrgChart.expanded.add(n.id);
            fetchedIds.add(n.id);
          });
          finish();
        })
        .catch(function(e){ hideLoading(); showError(e.message || 'Erro ao expandir.'); });
    });
  }

  // ════════════════════════════════════════════════════════════════════════════
  // Expand / Collapse + Fetch
  // ════════════════════════════════════════════════════════════════════════════

  function fetchChildren(pid) {
    return new Promise(function(resolve,reject){
      if (fetchedIds.has(pid)||fetchingIds.has(pid)){ resolve(); return; }
      fetchingIds.add(pid);
      fetch(BASE_URL+'?format=json&cpf='+encodeURIComponent(pid))
        .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
        .then(function(ch){
          if (ch&&ch.error) throw new Error(ch.error);
          ch.forEach(function(n){ n.pid=pid; OrgChart.nodes.set(n.id,n); });
          fetchedIds.add(pid); fetchingIds.delete(pid); resolve();
        }).catch(function(e){ fetchingIds.delete(pid); reject(e); });
    });
  }

  function getSubtreeIds(rootId) {
    var r=new Set(), q=[rootId];
    while(q.length){ var id=q.shift(); if(r.has(id)) continue; r.add(id); OrgChart.nodes.forEach(function(n,nid){ if(n.pid===id) q.push(nid); }); }
    r.delete(rootId); return r;
  }

  function countSubordinates(id){ return getSubtreeIds(id).size; }

  function collapseSubtree(id) {
    getSubtreeIds(id).forEach(function(sid){ OrgChart.visible.delete(sid); OrgChart.expanded.delete(sid); });
    OrgChart.expanded.delete(id);
  }

  function expandNode(id) {
    return fetchChildren(id).then(function(){
      OrgChart.nodes.forEach(function(n,nid){ if(n.pid===id) OrgChart.visible.add(nid); });
      OrgChart.expanded.add(id); layoutTree(); renderNodes();
    });
  }

  function setExpandBtnState(id, state) {
    // state: 'collapsed' | 'expanded' | 'loading'
    var btn=document.querySelector('.org-card[data-id="'+id+'"] .org-expand-btn');
    if (!btn) return;
    if (state==='loading') {
      btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    } else if (state==='expanded') {
      btn.innerHTML='<i class="fas fa-chevron-up"></i>';
    } else {
      btn.innerHTML='<i class="fas fa-chevron-down"></i>';
    }
  }

  function panToNode(id) {
    var l = OrgChart.layout.get(id); if (!l) return;
    var s = OrgChart.transform.scale;
    OrgChart.transform.tx = rootEl.clientWidth / 2 - (l.x + CARD_W / 2) * s;
    OrgChart.transform.ty = 40 - l.y * s;
    applyTransform();
  }

  function handleExpandToggle(id) {
    if (fetchingIds.has(id)) return;
    if (OrgChart.expanded.has(id)) {
      collapseSubtree(id); layoutTree(); renderNodes();
      panToNode(id);
    } else {
      setExpandBtnState(id, 'loading');
      expandNode(id)
        .then(function(){ setExpandBtnState(id, 'expanded'); panToNode(id); })
        .catch(function(e){ setExpandBtnState(id, 'collapsed'); showError(e.message||'Erro ao expandir.'); });
    }
  }

  function appendExpandBtn(cardEl, node) {
    var btn=document.createElement('button');
    btn.className='org-expand-btn'; btn.dataset.id=node.id;
    btn.innerHTML='<i class="fas fa-chevron-down"></i><i class="fas fa-chevron-up" style="display:none;"></i>';
    btn.addEventListener('click',function(e){ e.stopPropagation(); handleExpandToggle(node.id); });
    cardEl.appendChild(btn);
  }

  function updateExpandBtn(cardEl, id) {
    // no-op: badge removed; icon state is handled in handleExpandToggle
  }

  // ════════════════════════════════════════════════════════════════════════════
  // Minimap
  // ════════════════════════════════════════════════════════════════════════════

  var mmScale=1, mmDrag=false;

  function getBB() {
    var mx=Infinity,my=Infinity,ax=-Infinity,ay=-Infinity;
    OrgChart.layout.forEach(function(l){ mx=Math.min(mx,l.x); my=Math.min(my,l.y); ax=Math.max(ax,l.x+CARD_W); ay=Math.max(ay,l.y+CARD_H); });
    return (mx===Infinity)?null:{x:mx,y:my,w:ax-mx,h:ay-my};
  }

  function drawMinimap() {
    if (!mmCtx||!OrgChart.layout.size) return;
    var bb=getBB(); if (!bb||!bb.w||!bb.h) return;
    mmCtx.clearRect(0,0,MM_W,MM_H);
    mmScale=Math.min((MM_W-8)/bb.w,(MM_H-8)/bb.h);
    var ox=4-bb.x*mmScale, oy=4-bb.y*mmScale;
    mmCtx.fillStyle='#1a3c5e';
    OrgChart.layout.forEach(function(l){ mmCtx.fillRect(ox+l.x*mmScale,oy+l.y*mmScale,Math.max(2,CARD_W*mmScale),Math.max(2,CARD_H*mmScale)); });
    var t=OrgChart.transform;
    mmCtx.strokeStyle='#e74c3c'; mmCtx.lineWidth=1.5;
    mmCtx.strokeRect(ox+(-t.tx/t.scale)*mmScale, oy+(-t.ty/t.scale)*mmScale, (rootEl.clientWidth/t.scale)*mmScale, (rootEl.clientHeight/t.scale)*mmScale);
  }

  function mmNav(mx,my) {
    var bb=getBB(); if(!bb) return;
    var ox=4-bb.x*mmScale, oy=4-bb.y*mmScale;
    var cx=(mx-ox)/mmScale, cy=(my-oy)/mmScale, s=OrgChart.transform.scale;
    OrgChart.transform.tx=rootEl.clientWidth/2-cx*s; OrgChart.transform.ty=rootEl.clientHeight/2-cy*s; applyTransform();
  }

  function initMinimap() {
    if (!mmCanvas) return;
    mmCanvas.addEventListener('mousedown',function(e){ mmDrag=true; var r=mmCanvas.getBoundingClientRect(); mmNav(e.clientX-r.left,e.clientY-r.top); e.stopPropagation(); });
    document.addEventListener('mousemove',function(e){ if(!mmDrag) return; var r=mmCanvas.getBoundingClientRect(); mmNav(e.clientX-r.left,e.clientY-r.top); });
    document.addEventListener('mouseup',function(){ mmDrag=false; });
  }

  // ════════════════════════════════════════════════════════════════════════════
  // Search
  // ════════════════════════════════════════════════════════════════════════════

  function debounce(fn,ms){ var t; return function(){ var a=arguments,ctx=this; clearTimeout(t); t=setTimeout(function(){ fn.apply(ctx,a); },ms); }; }

  function expandAncestors(nodeId) {
    // If the node's direct parent isn't in our local map, fetch the full
    // ancestor chain from the server first (one CONNECT BY query going up).
    var node = OrgChart.nodes.get(nodeId);
    var directPid = node ? node.pid : null;
    var needsAncestorFetch = directPid && !OrgChart.nodes.has(directPid);

    var prime = needsAncestorFetch
      ? fetch(BASE_URL + '?format=json&ancestors=' + encodeURIComponent(nodeId))
          .then(function(r){ if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
          .then(function(data){
            if (data && data.error) throw new Error(data.error);
            // Populate nodes map with every ancestor returned (root → node order)
            data.forEach(function(n){ if (!OrgChart.nodes.has(n.id)) OrgChart.nodes.set(n.id, n); });
          })
      : Promise.resolve();

    return prime.then(function(){
      // Now walk up the (complete) chain and expand each ancestor in order
      var chain = []; var curId = (OrgChart.nodes.get(nodeId) || {}).pid;
      while (curId !== null && curId !== undefined) {
        chain.unshift(curId);
        var p = OrgChart.nodes.get(curId);
        if (!p) break;
        curId = p.pid;
      }
      return chain.reduce(function(pr, ancId){
        return pr.then(function(){
          if (OrgChart.expanded.has(ancId)) return;
          return fetchChildren(ancId).then(function(){
            OrgChart.nodes.forEach(function(n,id){ if(n.pid===ancId) OrgChart.visible.add(id); });
            OrgChart.expanded.add(ancId);
          });
        });
      }, Promise.resolve()).then(function(){ layoutTree(); renderNodes(); });
    });
  }

  function navigateToNode(nodeId) {
    var needsFetch = !OrgChart.visible.has(nodeId);
    if (needsFetch) showLoading('Localizando colaborador…');
    var ensureVis = OrgChart.visible.has(nodeId)
      ? Promise.resolve()
      : expandAncestors(nodeId).then(function(){ OrgChart.visible.add(nodeId); layoutTree(); renderNodes(); });
    ensureVis.then(function(){
      if (needsFetch) hideLoading();
      var l=OrgChart.layout.get(nodeId); if(!l) return;
      var s=OrgChart.transform.scale;
      OrgChart.transform.tx=rootEl.clientWidth/2-(l.x+CARD_W/2)*s;
      OrgChart.transform.ty=rootEl.clientHeight/2-(l.y+CARD_H/2)*s; applyTransform();
      var el=document.querySelector('.org-card[data-id="'+nodeId+'"]');
      if (el){ el.classList.add('org-highlight'); setTimeout(function(){ el.classList.remove('org-highlight'); },2400); }
    }).catch(function(e){ hideLoading(); showError(e.message||'Erro ao localizar colaborador.'); });
  }

  function clearDD(){ var d=document.getElementById('org-search-dropdown'),i=document.getElementById('org-search-input'); if(d) d.style.display='none'; if(i) i.value=''; }

  function renderDD(results) {
    var dd=document.getElementById('org-search-dropdown'); if(!dd) return;
    dd.innerHTML='';
    if (!results.length){
      dd.innerHTML='<li style="padding:8px 12px;color:#aaa;font-size:.82rem;">Nenhum resultado encontrado.</li>';
      dd.style.display='block'; return;
    }
    results.forEach(function(n){
      var li=document.createElement('li');
      var orphanBadge = n.isOrphan
        ? '<span style="margin-left:4px;font-size:.68rem;background:#dc3545;color:#fff;border-radius:4px;padding:1px 5px;vertical-align:middle;">Sem líder</span>'
        : '';
      li.innerHTML=
        '<div style="display:flex;align-items:center;gap:8px;">'+
          '<i class="fas fa-user-circle" style="color:'+(n.isOrphan?'#dc3545':'#1a3c5e')+';font-size:1.1rem;flex-shrink:0;"></i>'+
          '<div style="min-width:0;">'+
            '<div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(n.nome)+orphanBadge+'</div>'+
            (n.cargo?'<div style="font-size:.75rem;color:#666;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(n.cargo)+'</div>':'')+
            (n.breadcrumb?'<div style="font-size:.7rem;color:#aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="'+esc(n.breadcrumb)+'">'+esc(n.breadcrumb)+'</div>':'')+
          '</div>'+
        '</div>';
      li.addEventListener('mousedown',function(e){
        e.preventDefault();
        if (n.isOrphan) {
          showToast('O líder dessa pessoa foi inativado. Necessária realocação pelo RH.', 'danger');
          clearDD();
          return;
        }
        navigateToNode(n.id); clearDD();
      });
      dd.appendChild(li);
    });
    dd.style.display='block';
  }

  function initSearch() {
    var inp=document.getElementById('org-search-input'), dd=document.getElementById('org-search-dropdown'); if(!inp) return;
    var doSearch=debounce(function(q){
      if (!q||q.length<3){ if(dd) dd.style.display='none'; return; }
      if (dd) {
        dd.innerHTML='<li style="padding:8px 12px;color:#aaa;font-size:.82rem;"><i class="fas fa-spinner fa-spin mr-1"></i>Buscando…</li>';
        dd.style.display='block';
      }
      fetch(BASE_URL+'?format=json&search='+encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (data&&data.error){ renderDD([]); return; }
          data.forEach(function(n){ if(!OrgChart.nodes.has(n.id)) OrgChart.nodes.set(n.id,n); });
          renderDD(data);
        }).catch(function(){ renderDD([]); });
    },350);
    inp.addEventListener('input',function(){
      var q=this.value.trim();
      if (q.length<3 && dd) dd.style.display='none';
      doSearch(q);
    });
    inp.addEventListener('keydown',function(e){
      if(e.key==='Escape'){ clearDD(); this.blur(); }
      if(e.key==='Enter'&&dd){ var f=dd.querySelector('li'); if(f) f.dispatchEvent(new MouseEvent('mousedown',{bubbles:true})); }
    });
    document.addEventListener('click',function(e){ if(!e.target.closest('#org-search')) clearDD(); });
  }

  // ════════════════════════════════════════════════════════════════════════════
  // Stats
  // ════════════════════════════════════════════════════════════════════════════

  function nodeDepth(id){ var d=0,c=OrgChart.nodes.get(id); while(c&&c.pid!==null){ d++; c=OrgChart.nodes.get(c.pid); } return d; }

  function updateStats() {
    var mx=0; var em=new Set();
    OrgChart.visible.forEach(function(id){ var n=OrgChart.nodes.get(id); if(!n) return; mx=Math.max(mx,nodeDepth(id)); if(n.empresa) em.add(n.empresa); });
    var e1=document.getElementById('stat-colaboradores'),e2=document.getElementById('stat-niveis'),e3=document.getElementById('stat-empresas');
    if(e1) e1.textContent=OrgChart.visible.size; if(e2) e2.textContent=mx; if(e3) e3.textContent=em.size;
  }

  // ════════════════════════════════════════════════════════════════════════════
  // Orphans modal
  // ════════════════════════════════════════════════════════════════════════════

  var orphansData = null; // cached after first load

  function renderOrphansTable(filter) {
    var tbody = document.getElementById('orphans-tbody');
    if (!tbody || !orphansData) return;
    var q = (filter || '').toLowerCase();
    var rows = orphansData.filter(function(r){
      if (!q) return true;
      return (r.nome + r.cargo + r.empresa + r.unidade + r.lider_nome + r.lider_cargo).toLowerCase().indexOf(q) !== -1;
    });
    document.getElementById('orphans-count').textContent = rows.length;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Nenhum resultado.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function(r){
      var statusBadge = r.lider_status === 'INATIVO'
        ? '<span class="badge badge-danger">INATIVO</span>'
        : '<span class="badge badge-secondary">'+esc(r.lider_status)+'</span>';
      return '<tr>'+
        '<td>'+esc(r.nome)+'</td>'+
        '<td>'+esc(r.cargo)+'</td>'+
        '<td>'+esc(r.empresa)+'</td>'+
        '<td>'+esc(r.unidade)+'</td>'+
        '<td>'+esc(r.lider_nome)+'</td>'+
        '<td>'+esc(r.lider_cargo)+'</td>'+
        '<td>'+statusBadge+'</td>'+
        '<td>'+(r.lider_rescisao ? esc(r.lider_rescisao) : '<span class="text-muted">—</span>')+'</td>'+
      '</tr>';
    }).join('');
  }

  function initOrphansModal() {
    var btn = document.getElementById('btn-orphans');
    if (!btn) return;
    btn.addEventListener('click', function(){
      orphansData = null;
      var elLoad  = document.getElementById('orphans-loading');
      var elErr   = document.getElementById('orphans-error');
      var elRes   = document.getElementById('orphans-results');
      var elFilt  = document.getElementById('orphans-filter');
      if (elLoad) elLoad.style.display = 'block';
      if (elErr)  elErr.style.display  = 'none';
      if (elRes)  elRes.style.display  = 'none';
      if (elFilt) elFilt.value = '';
      $('#modal-orphans').modal('show');
      fetch(BASE_URL + '?format=json&orphans=1')
        .then(function(r){ if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
        .then(function(data){
          if (data && data.error) throw new Error(data.error);
          orphansData = data;
          if (elLoad) elLoad.style.display = 'none';
          if (elRes)  elRes.style.display  = 'block';
          renderOrphansTable('');
        })
        .catch(function(e){
          if (elLoad) elLoad.style.display = 'none';
          if (elErr)  { elErr.style.display='block'; elErr.textContent = e.message || 'Erro ao carregar dados.'; }
        });
    });

    var filtInp = document.getElementById('orphans-filter');
    if (filtInp) {
      filtInp.addEventListener('input', function(){ renderOrphansTable(this.value); });
    }
  }

  // ════════════════════════════════════════════════════════════════════════════
  // Boot
  // ════════════════════════════════════════════════════════════════════════════

  function showError(msg){ var e=document.getElementById('org-error'),m=document.getElementById('org-error-msg'); if(e){e.style.display='flex';} if(m) m.textContent=msg; }

  function showToast(msg, type) {
    // type: 'danger' | 'warning' | 'success'
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:99999;'
      + 'min-width:320px;max-width:520px;padding:12px 18px;border-radius:6px;font-size:.88rem;'
      + 'box-shadow:0 4px 16px rgba(0,0,0,.22);display:flex;align-items:center;gap:10px;';
    var colors = { danger:'#dc3545', warning:'#e0a800', success:'#28a745' };
    t.style.background = colors[type] || colors.danger;
    t.style.color = '#fff';
    t.innerHTML = '<i class="fas fa-'+(type==='success'?'check-circle':'exclamation-triangle')+'"></i><span>'+esc(msg)+'</span>';
    document.body.appendChild(t);
    setTimeout(function(){ t.style.transition='opacity .4s'; t.style.opacity='0';
      setTimeout(function(){ t.parentNode&&t.parentNode.removeChild(t); }, 420);
    }, 4000);
  }

  function showLoading(msg) {
    var ld=document.getElementById('org-loading');
    var txt=ld&&ld.querySelector('div>div:last-child');
    if (txt) txt.textContent = msg || 'Carregando…';
    if (ld) ld.style.display='flex';
  }

  function hideLoading() {
    var ld=document.getElementById('org-loading');
    if (ld) ld.style.display='none';
  }

  function initOrganograma() {
    initPanZoom(); initMinimap(); initSearch(); initOrphansModal();
    var ld=document.getElementById('org-loading');
    fetch(BASE_URL+'?format=json')
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
      .then(function(roots){
        if (roots&&roots.error) throw new Error(roots.error);
        roots.forEach(function(n){ n.pid=null; OrgChart.nodes.set(n.id,n); OrgChart.visible.add(n.id); });
        layoutTree(); renderNodes(); resetView();
        if (ld) ld.style.display='none';
      }).catch(function(e){ if(ld) ld.style.display='none'; showError(e.message||'Erro desconhecido.'); });
  }

  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded', initOrganograma);
  else initOrganograma();

})();
</script>
