<?php
/**
 * @var int   $mes
 * @var int   $ano
 * @var string $mesLabel
 * @var array<int, string> $mesesPt
 * @var int   $prevMes
 * @var int   $prevAno
 * @var int   $nextMes
 * @var int   $nextAno
 * @var int   $diasNoMes
 * @var int   $offset
 * @var ?int  $hojeDia
 * @var array<int, array<int, array<string, mixed>>> $porDia
 * @var array<int, array<string, mixed>> $aniversariantes
 * @var ?string $erro
 */
/**
 * @var array<int, array<string, mixed>> $aniversariantesSem
 * @var array<int, array<string, mixed>> $aniversariantesDia
 * @var string $semanaLabel
 * @var string $diaLabel
 * @var bool $isGlobalAdmin
 * @var int $semanaAnoRef
 */
$baseCal = base_url('colaboradores/calendario');
$emailSendUrl = base_url('colaboradores/calendario/enviar-email');
$anivJsonUrl = base_url('colaboradores/calendario/aniversariantes');
$colabBaseUrl = base_url('colaboradores/');
$initialRef = $hojeDia !== null ? sprintf('%04d-%02d-%02d', $ano, $mes, $hojeDia) : null;
$prevUrl = $baseCal . '?' . http_build_query(['mes' => $prevMes, 'ano' => $prevAno]);
$nextUrl = $baseCal . '?' . http_build_query(['mes' => $nextMes, 'ano' => $nextAno]);
$totalMes = count($aniversariantes);
$diasComAniv = count($porDia);
$totalSem = count($aniversariantesSem);
$totalDia = count($aniversariantesDia);

$diasSemanaPt = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

$badgeDataAniv = function (int $mesAniv, int $diaAniv, int $anoRef) use ($diasSemanaPt): string {
    if (!checkdate($mesAniv, $diaAniv, $anoRef)) {
        return sprintf('%02d/%02d', $diaAniv, $mesAniv);
    }
    $dt = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $anoRef, $mesAniv, $diaAniv));
    return ($diasSemanaPt[(int)$dt->format('N')] ?? '') . ' ' . sprintf('%02d/%02d', $diaAniv, $mesAniv);
};

$renderLista = function (array $rows, string $emptyMsg, callable $badgeFn) use ($erro): void {
    if ($erro) {
        echo '<p class="text-muted p-3 mb-0">Não foi possível carregar a listagem.</p>';
        return;
    }
    if (empty($rows)) {
        echo '<p class="text-muted p-3 mb-0">' . e($emptyMsg) . '</p>';
        return;
    }
    echo '<ul class="list-group list-group-flush cal-birthday-list">';
    foreach ($rows as $row) {
        $cod  = (string)($row['CODPESSOA'] ?? '');
        $nome = (string)($row['NOMECOMPLETO'] ?? '');
        $url  = base_url('colaboradores/' . urlencode($cod));
        $badge = $badgeFn($row);
        echo '<li class="list-group-item d-flex align-items-center py-2">';
        if ($badge !== '') {
            echo '<span class="cal-list-day badge badge-warning mr-2">' . e($badge) . '</span>';
        }
        echo '<a href="' . e($url) . '" class="cal-list-name">' . e($nome) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
};
?>
<section class="content pt-3">
  <div class="container-fluid" style="max-width:1140px;">

    <?php if ($erro): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="fas fa-exclamation-triangle mr-1"></i>Erro ao carregar aniversariantes:</strong>
        <?= e($erro) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0 mr-auto">
          <i class="fas fa-birthday-cake text-warning mr-2"></i>Calendário de Aniversários
        </h3>
        <?php if (!$erro): ?>
          <span class="badge badge-light border text-muted ml-2">
            <?= $totalMes ?> aniversariante<?= $totalMes === 1 ? '' : 's' ?> · <?= $diasComAniv ?> dia<?= $diasComAniv === 1 ? '' : 's' ?>
          </span>
        <?php endif; ?>
        <?php if ($isGlobalAdmin): ?>
          <button type="button" class="btn btn-sm btn-warning ml-2" id="btnSendBirthdayEmail">
            <i class="fas fa-paper-plane mr-1"></i>Enviar email de aniversário
          </button>
        <?php endif; ?>
      </div>

      <div class="card-body pb-3">
        <div class="row">
          <!-- Calendário -->
          <div class="col-lg-7 mb-3 mb-lg-0">
            <div class="cal-nav d-flex align-items-center justify-content-center mb-3">
          <a href="<?= e($prevUrl) ?>" class="btn btn-sm btn-outline-secondary cal-nav-btn" title="Mês anterior">
            <i class="fas fa-chevron-left"></i>
          </a>

          <div class="cal-month-picker-wrap mx-2">
            <button type="button" class="btn btn-link cal-month-label px-3" id="btnPickMonth"
                    aria-haspopup="true" aria-expanded="false" aria-controls="calMonthDropdown">
              <?= e($mesLabel) ?>, <?= (int)$ano ?>
              <i class="fas fa-caret-down ml-1 cal-month-caret"></i>
            </button>

            <div class="cal-month-dropdown shadow" id="calMonthDropdown" hidden>
              <div class="cal-year-nav">
                <button type="button" class="btn btn-sm btn-light cal-year-btn" id="pickYearPrev" title="Ano anterior">
                  <i class="fas fa-chevron-left"></i>
                </button>
                <span class="cal-year-label" id="pickYearLabel"><?= (int)$ano ?></span>
                <button type="button" class="btn btn-sm btn-light cal-year-btn" id="pickYearNext" title="Próximo ano">
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>

              <div class="cal-months-grid" role="listbox" aria-label="Escolher mês">
                <?php foreach ($mesesPt as $num => $label): ?>
                  <button type="button"
                          class="cal-month-btn<?= $num === $mes ? ' is-current' : '' ?>"
                          data-mes="<?= $num ?>"
                          role="option"
                          aria-selected="<?= $num === $mes ? 'true' : 'false' ?>">
                    <?= e(mb_substr($label, 0, 3, 'UTF-8')) ?>
                  </button>
                <?php endforeach; ?>
              </div>

              <div class="cal-picker-footer">
                <button type="button" class="btn btn-sm btn-link p-0" id="pickToday">Ir para hoje</button>
              </div>
            </div>
          </div>

          <a href="<?= e($nextUrl) ?>" class="btn btn-sm btn-outline-secondary cal-nav-btn" title="Próximo mês">
            <i class="fas fa-chevron-right"></i>
          </a>
        </div>

        <!-- Grade do calendário -->
        <div class="cal-grid mb-0">
          <?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $wd): ?>
            <div class="cal-weekday"><?= $wd ?></div>
          <?php endforeach; ?>

          <?php for ($i = 0; $i < $offset; $i++): ?>
            <div class="cal-day cal-day--empty"></div>
          <?php endfor; ?>

          <?php for ($dia = 1; $dia <= $diasNoMes; $dia++):
            $pessoas   = $porDia[$dia] ?? [];
            $hasBirth  = !empty($pessoas);
            $isToday   = $hojeDia !== null && $dia === $hojeDia;
            $tooltipNames = $hasBirth
                ? array_values(array_map(fn($p) => (string)($p['NOMECOMPLETO'] ?? ''), $pessoas))
                : [];
            $classes   = 'cal-day cal-day--clickable';
            if ($hasBirth) $classes .= ' cal-day--birthday';
            if ($isToday)  $classes .= ' cal-day--today';
            if ($hojeDia !== null && $dia === $hojeDia) $classes .= ' cal-day--selected';
          ?>
            <div class="<?= $classes ?>"
                 data-cal-day="<?= $dia ?>"
                 role="button"
                 tabindex="0"
                 aria-label="Dia <?= $dia ?>"
                 <?php if ($hasBirth): ?>
                   data-cal-tooltip="<?= e(json_encode($tooltipNames, JSON_UNESCAPED_UNICODE)) ?>"
                 <?php endif; ?>>
              <span class="cal-day-num"><?= $dia ?></span>
              <?php if ($hasBirth): ?>
                <span class="cal-day-dot" aria-hidden="true"></span>
              <?php endif; ?>
            </div>
          <?php endfor; ?>

          <?php
            $totalCells = $offset + $diasNoMes;
            $remainder  = $totalCells % 7;
            if ($remainder !== 0) {
                for ($i = 0; $i < 7 - $remainder; $i++) {
                    echo '<div class="cal-day cal-day--empty"></div>';
                }
            }
          ?>
        </div>
          </div>

          <!-- Listagem com abas -->
          <div class="col-lg-5">
            <div class="cal-list-panel border rounded h-100 d-flex flex-column">
              <div class="cal-list-tabs" id="calListTabs" role="tablist">
                <button type="button" class="cal-tab-btn is-active" data-tab="mes" role="tab"
                        aria-selected="true" aria-controls="cal-pane-mes">
                  <span class="cal-tab-label">Mês</span>
                  <span class="cal-tab-count"><?= $totalMes ?></span>
                </button>
                <button type="button" class="cal-tab-btn" data-tab="semana" role="tab"
                        aria-selected="false" aria-controls="cal-pane-semana">
                  <span class="cal-tab-label">Semana</span>
                  <span class="cal-tab-count" id="cal-tab-count-semana"><?= $totalSem ?></span>
                </button>
                <button type="button" class="cal-tab-btn" data-tab="dia" role="tab"
                        aria-selected="false" aria-controls="cal-pane-dia">
                  <span class="cal-tab-label">Dia</span>
                  <span class="cal-tab-count" id="cal-tab-count-dia"><?= $totalDia ?></span>
                </button>
              </div>

              <div class="cal-tab-panes flex-grow-1">
                <div class="cal-tab-pane active" id="cal-pane-mes" role="tabpanel">
                  <div class="cal-pane-title px-3 pt-2 pb-1 text-muted small">
                    Aniversariantes em <?= e($mesLabel) ?>
                  </div>
                  <?php $renderLista(
                      $aniversariantes,
                      'Nenhum aniversariante neste mês.',
                      fn(array $row) => 'Dia ' . (int)($row['DIA_ANIV'] ?? 0)
                  ); ?>
                </div>

                <div class="cal-tab-pane" id="cal-pane-semana" role="tabpanel" hidden>
                  <div class="cal-pane-title px-3 pt-2 pb-1 text-muted small" id="cal-pane-semana-title">
                    Aniversariantes da semana (<?= e($semanaLabel) ?>)
                  </div>
                  <div id="cal-pane-semana-body">
                  <?php $renderLista(
                      $aniversariantesSem,
                      'Nenhum aniversariante nesta semana.',
                      function (array $row) use ($badgeDataAniv, $semanaAnoRef) {
                          return $badgeDataAniv((int)($row['MES_ANIV'] ?? 0), (int)($row['DIA_ANIV'] ?? 0), $semanaAnoRef);
                      }
                  ); ?>
                  </div>
                </div>

                <div class="cal-tab-pane" id="cal-pane-dia" role="tabpanel" hidden>
                  <div class="cal-pane-title px-3 pt-2 pb-1 text-muted small" id="cal-pane-dia-title">
                    Aniversariantes do dia (<?= e($diaLabel) ?>)
                  </div>
                  <div id="cal-pane-dia-body">
                  <?php $renderLista(
                      $aniversariantesDia,
                      'Nenhum aniversariante neste dia.',
                      fn(array $row) => 'Dia ' . (int)($row['DIA_ANIV'] ?? 0)
                  ); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<?php if ($isGlobalAdmin): ?>
<dialog class="cal-email-dialog" id="dlgBirthdayEmail" aria-labelledby="dlgBirthdayEmailTitle">
  <form method="dialog" id="frmBirthdayEmail" class="cal-email-dialog-inner">
    <div class="cal-email-dialog-header">
      <h5 class="mb-0" id="dlgBirthdayEmailTitle">
        <i class="fas fa-paper-plane text-warning mr-2"></i>Enviar e-mail de aniversário
      </h5>
      <button type="button" class="close cal-email-dialog-close" id="btnCloseBirthdayDlg" aria-label="Fechar">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <div class="cal-email-dialog-body">
      <p class="cal-email-desc small mb-3">
        Envia o <strong>e-mail de grupo</strong> (lista dos aniversariantes do dia) e os
        <strong>e-mails individuais</strong> de parabéns para os destinatários informados abaixo.
        Usa os aniversariantes de <strong>hoje</strong> (<?= e($diaLabel) ?>).
      </p>
      <?php if ($totalDia === 0): ?>
        <div class="alert alert-warning py-2 small mb-3">
          <i class="fas fa-exclamation-triangle mr-1"></i>Não há aniversariantes hoje — o envio não será possível.
        </div>
      <?php else: ?>
        <div class="alert alert-light border py-2 small mb-3">
          <strong><?= $totalDia ?></strong> aniversariante<?= $totalDia === 1 ? '' : 's' ?> hoje.
        </div>
      <?php endif; ?>
      <div class="form-group mb-0">
        <label for="birthdayEmailTargets">E-mails destino</label>
        <textarea id="birthdayEmailTargets" class="form-control form-control-sm" rows="3"
                  placeholder="ex.: ti@valence.com.br, parceiro@exemplo.com"></textarea>
        <small class="form-text cal-email-hint">Separe múltiplos e-mails por vírgula, espaço ou linha.</small>
      </div>
      <div id="birthdayEmailFeedback" class="mt-3" hidden></div>
    </div>
    <div class="cal-email-dialog-footer">
      <button type="button" class="btn btn-secondary btn-sm" id="btnCancelBirthdayDlg">Cancelar</button>
      <button type="submit" class="btn btn-warning btn-sm" id="btnSubmitBirthdayEmail"
              <?= $totalDia === 0 ? 'disabled' : '' ?>>
        <i class="fas fa-paper-plane mr-1"></i>Enviar
      </button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<style>
.cal-month-picker-wrap {
  position: relative;
}
.cal-nav,
.cal-month-picker-wrap {
  overflow: visible;
}
.cal-nav-btn { width: 36px; }
.cal-month-label {
  font-size: 1.15rem;
  font-weight: 600;
  color: #343a40;
  text-decoration: none;
  white-space: nowrap;
}
.cal-month-label:hover,
.cal-month-label[aria-expanded="true"] {
  text-decoration: none;
  color: #007bff;
}
.cal-month-caret {
  font-size: .85rem;
  opacity: .7;
  transition: transform .15s;
}
.cal-month-label[aria-expanded="true"] .cal-month-caret {
  transform: rotate(180deg);
}

.cal-month-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  z-index: 1050;
  width: 280px;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 10px;
  padding: 12px;
}
.cal-year-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
}
.cal-year-label {
  font-size: 1.05rem;
  font-weight: 700;
  min-width: 4rem;
  text-align: center;
}
.cal-year-btn {
  width: 32px;
  height: 32px;
  padding: 0;
  line-height: 1;
}
.cal-months-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}
.cal-month-btn {
  border: 1px solid #e9ecef;
  background: #f8f9fa;
  border-radius: 6px;
  padding: .45rem .25rem;
  font-size: .82rem;
  font-weight: 500;
  color: #495057;
  cursor: pointer;
  transition: background .12s, border-color .12s, color .12s;
}
.cal-month-btn:hover {
  background: #e9f2ff;
  border-color: #b8daff;
  color: #0056b3;
}
.cal-month-btn.is-current {
  background: #007bff;
  border-color: #007bff;
  color: #fff;
}
.cal-picker-footer {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px solid #eee;
  text-align: center;
}

.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  user-select: none;
}
.cal-weekday {
  text-align: center;
  font-size: .75rem;
  font-weight: 600;
  color: #6c757d;
  padding: .25rem 0 .5rem;
  text-transform: uppercase;
}
.cal-day {
  position: relative;
  aspect-ratio: 1;
  min-height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  transition: background .15s, box-shadow .15s;
}
.cal-day--empty {
  background: transparent;
  border-color: transparent;
}
.cal-day--clickable {
  cursor: pointer;
}
.cal-day--clickable:hover:not(.cal-day--selected) {
  background: #e9ecef;
  border-color: #ced4da;
}
.cal-day--clickable:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, .35);
}
.cal-day--birthday {
  background: #fff3cd;
  border-color: #ffeeba;
}
.cal-day--birthday:hover {
  background: #ffe8a1;
  box-shadow: 0 2px 6px rgba(0,0,0,.08);
}
.cal-day--today .cal-day-num {
  background: #007bff;
  color: #fff;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}
.cal-day--selected:not(.cal-day--today) .cal-day-num {
  background: #495057;
  color: #fff;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}
.cal-day--selected.cal-day--today .cal-day-num {
  box-shadow: 0 0 0 2px #495057;
}
.cal-day-num {
  font-size: .9rem;
  line-height: 1;
  z-index: 1;
}
.cal-day-dot {
  position: absolute;
  bottom: 6px;
  left: 50%;
  transform: translateX(-50%);
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #e0a800;
}

.cal-tooltip {
  position: fixed;
  z-index: 2000;
  pointer-events: none;
  background: linear-gradient(145deg, #1a303b 0%, #243b47 100%);
  color: #fff;
  padding: 10px 14px;
  border-radius: 10px;
  font-size: .82rem;
  line-height: 1.35;
  box-shadow: 0 10px 28px rgba(0, 0, 0, .22), 0 0 0 1px rgba(255, 255, 255, .08);
  max-width: 240px;
  opacity: 0;
  visibility: hidden;
  transform: translate(-50%, calc(-100% - 10px)) scale(.96);
  transition: opacity .16s ease, transform .16s ease, visibility .16s;
}
.cal-tooltip.is-visible {
  opacity: 1;
  visibility: visible;
  transform: translate(-50%, calc(-100% - 10px)) scale(1);
}
.cal-tooltip-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #f9c70c;
  margin-bottom: 6px;
  font-weight: 700;
}
.cal-tooltip-title i {
  font-size: .75rem;
}
.cal-tooltip ul {
  margin: 0;
  padding: 0;
  list-style: none;
}
.cal-tooltip li {
  padding: 3px 0;
  border-bottom: 1px solid rgba(255, 255, 255, .08);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cal-tooltip li:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.cal-list-day {
  min-width: 72px;
  font-size: .75rem;
  white-space: nowrap;
}
.cal-list-panel {
  background: #fff;
  min-height: 320px;
}
.cal-list-tabs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 10px 12px 0;
  background: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  flex-shrink: 0;
}
.cal-tab-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 100%;
  min-height: 56px;
  padding: 8px 6px 10px;
  border: 1px solid transparent;
  border-bottom: none;
  border-radius: 8px 8px 0 0;
  background: transparent;
  color: #6c757d;
  font-weight: 600;
  font-size: .84rem;
  line-height: 1.2;
  cursor: pointer;
  transition: background .15s, color .15s, border-color .15s;
}
.cal-tab-btn:hover {
  color: #007bff;
  background: rgba(255, 255, 255, .7);
}
.cal-tab-btn.is-active {
  color: #007bff;
  background: #fff;
  border-color: #dee2e6;
  margin-bottom: -1px;
  padding-bottom: 11px;
}
.cal-tab-label {
  display: block;
  text-align: center;
}
.cal-tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.6rem;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: .7rem;
  font-weight: 700;
  background: #e9ecef;
  color: #495057;
}
.cal-tab-btn.is-active .cal-tab-count {
  background: #e7f1ff;
  color: #007bff;
}
.cal-tab-panes {
  overflow-y: auto;
  max-height: 520px;
}
.cal-tab-pane[hidden] {
  display: none !important;
}
.cal-pane-title {
  border-bottom: 1px solid #f0f0f0;
}
.cal-birthday-list .list-group-item {
  font-size: .88rem;
}
.cal-list-name {
  font-weight: 500;
  color: #007bff;
}
.cal-list-name:hover {
  color: #0056b3;
  text-decoration: underline;
}

.cal-email-dialog {
  border: none;
  border-radius: 10px;
  padding: 0;
  max-width: 520px;
  width: calc(100vw - 32px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, .18);
  background: #fff;
  color: #212529;
}
.cal-email-dialog::backdrop {
  background: rgba(0, 0, 0, .45);
}
.cal-email-dialog-inner {
  margin: 0;
  padding: 0;
  background: #fff;
  color: #212529;
}
.cal-email-dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid #dee2e6;
  background: #fff;
}
.cal-email-dialog-header h5 {
  color: #212529;
  font-weight: 600;
  font-size: 1.05rem;
}
.cal-email-dialog-body {
  padding: 16px;
  background: #fff;
  color: #343a40;
}
.cal-email-desc {
  color: #495057;
  line-height: 1.5;
}
.cal-email-desc strong {
  color: #212529;
}
.cal-email-dialog-body label {
  color: #212529;
  font-weight: 600;
}
.cal-email-hint {
  color: #6c757d;
}
.cal-email-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid #dee2e6;
  background: #fff;
}
.cal-email-dialog-close {
  padding: 0;
  margin: 0;
  background: none;
  border: none;
  font-size: 1.25rem;
  line-height: 1;
  color: #495057;
  opacity: .8;
  cursor: pointer;
}
.cal-email-dialog-close:hover {
  opacity: 1;
  color: #212529;
}

@media (max-width: 991px) {
  .cal-tab-panes { max-height: none; }
  .cal-list-panel { min-height: 0; }
}
@media (max-width: 576px) {
  .cal-day { min-height: 40px; }
  .cal-day-num { font-size: .8rem; }
  .cal-month-label { font-size: 1rem; }
  .cal-month-dropdown {
    width: min(280px, calc(100vw - 32px));
  }
}
</style>

<script>
(function () {
  var baseCal    = <?= json_encode($baseCal) ?>;
  var currentMes = <?= (int)$mes ?>;
  var currentAno = <?= (int)$ano ?>;
  var anivJsonUrl = <?= json_encode($anivJsonUrl) ?>;
  var colabBaseUrl = <?= json_encode($colabBaseUrl) ?>;
  var initialRef = <?= json_encode($initialRef) ?>;
  var diasSemanaPt = <?= json_encode($diasSemanaPt) ?>;
  var activateCalTab = null;
  var pickAno    = currentAno;
  var minAno     = 1970;
  var maxAno     = 2100;

  function buildUrl(mes, ano) {
    return baseCal + '?mes=' + encodeURIComponent(mes) + '&ano=' + encodeURIComponent(ano);
  }

  function initCalPicker() {
    var btn      = document.getElementById('btnPickMonth');
    var dropdown = document.getElementById('calMonthDropdown');
    var yearLbl  = document.getElementById('pickYearLabel');
    if (!btn || !dropdown || !yearLbl) return;

    var monthBtns = dropdown.querySelectorAll('.cal-month-btn');

    function updateYearLabel() {
      yearLbl.textContent = String(pickAno);
      monthBtns.forEach(function (el) {
        var m = parseInt(el.getAttribute('data-mes'), 10);
        var isCurrent = (m === currentMes && pickAno === currentAno);
        el.classList.toggle('is-current', isCurrent);
        el.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
      });
    }

    function openPicker() {
      pickAno = currentAno;
      updateYearLabel();
      dropdown.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
    }

    function closePicker() {
      dropdown.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    }

    function togglePicker() {
      if (dropdown.hidden) openPicker();
      else closePicker();
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      togglePicker();
    });

    var yearPrev = document.getElementById('pickYearPrev');
    var yearNext = document.getElementById('pickYearNext');
    if (yearPrev) {
      yearPrev.addEventListener('click', function (e) {
        e.stopPropagation();
        if (pickAno > minAno) {
          pickAno--;
          updateYearLabel();
        }
      });
    }
    if (yearNext) {
      yearNext.addEventListener('click', function (e) {
        e.stopPropagation();
        if (pickAno < maxAno) {
          pickAno++;
          updateYearLabel();
        }
      });
    }

    monthBtns.forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        var mes = parseInt(el.getAttribute('data-mes'), 10);
        window.location.href = buildUrl(mes, pickAno);
      });
    });

    var pickToday = document.getElementById('pickToday');
    if (pickToday) {
      pickToday.addEventListener('click', function (e) {
        e.stopPropagation();
        var now = new Date();
        window.location.href = buildUrl(now.getMonth() + 1, now.getFullYear());
      });
    }

    document.addEventListener('click', closePicker);
    dropdown.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closePicker();
    });
  }

  function initCalTabs() {
    var tabs  = document.querySelectorAll('#calListTabs .cal-tab-btn');
    var panes = document.querySelectorAll('.cal-tab-pane');
    if (!tabs.length) return;

    function activateTab(target) {
      tabs.forEach(function (t) {
        var active = t.getAttribute('data-tab') === target;
        t.classList.toggle('is-active', active);
        t.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panes.forEach(function (pane) {
        var show = pane.id === 'cal-pane-' + target;
        pane.classList.toggle('active', show);
        pane.hidden = !show;
      });
    }

    activateCalTab = activateTab;

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activateTab(tab.getAttribute('data-tab'));
      });
    });
  }

  function badgeDataAnivJs(mesAniv, diaAniv, anoRef) {
    var pad = function (n) { return String(n).padStart(2, '0'); };
    if (!anoRef) anoRef = new Date().getFullYear();

    var wdMap = { 1: 'Seg', 2: 'Ter', 3: 'Qua', 4: 'Qui', 5: 'Sex', 6: 'Sáb', 7: 'Dom' };
    try {
      var dt = new Date(anoRef, mesAniv - 1, diaAniv);
      if (dt.getFullYear() === anoRef && dt.getMonth() === mesAniv - 1 && dt.getDate() === diaAniv) {
        // ISO weekday: Mon=1 … Sun=7 (alinhado ao PHP format('N'))
        var iso = dt.getDay() === 0 ? 7 : dt.getDay();
        var wd = wdMap[iso] || '';
        return wd + ' ' + pad(diaAniv) + '/' + pad(mesAniv);
      }
    } catch (e) { /* ignore */ }
    return pad(diaAniv) + '/' + pad(mesAniv);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderAnivListHtml(rows, emptyMsg, badgeFn) {
    if (!rows || !rows.length) {
      return '<p class="text-muted p-3 mb-0">' + escapeHtml(emptyMsg) + '</p>';
    }
    var html = '<ul class="list-group list-group-flush cal-birthday-list">';
    rows.forEach(function (row) {
      var badge = badgeFn(row);
      html += '<li class="list-group-item d-flex align-items-center py-2">';
      if (badge) {
        html += '<span class="cal-list-day badge badge-warning mr-2">' + escapeHtml(badge) + '</span>';
      }
      html += '<a href="' + escapeHtml(colabBaseUrl + encodeURIComponent(row.codpessoa)) + '" class="cal-list-name">'
        + escapeHtml(row.nome) + '</a></li>';
    });
    html += '</ul>';
    return html;
  }

  function setTabCount(id, count) {
    var el = document.getElementById(id);
    if (el) el.textContent = String(count);
  }

  function selectCalendarDay(dayEl) {
    document.querySelectorAll('.cal-day--selected').forEach(function (el) {
      el.classList.remove('cal-day--selected');
    });
    dayEl.classList.add('cal-day--selected');
  }

  function loadAniversariantesForRef(ref) {
    var semTitle = document.getElementById('cal-pane-semana-title');
    var semBody  = document.getElementById('cal-pane-semana-body');
    var diaTitle = document.getElementById('cal-pane-dia-title');
    var diaBody  = document.getElementById('cal-pane-dia-body');
    if (!semBody || !diaBody) return;

    semBody.innerHTML = '<p class="text-muted p-3 mb-0"><i class="fas fa-spinner fa-spin mr-1"></i>Carregando…</p>';
    diaBody.innerHTML = semBody.innerHTML;

    fetch(anivJsonUrl + '?ref=' + encodeURIComponent(ref), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var body = {};
          try { body = text ? JSON.parse(text) : {}; } catch (e) {
            throw new Error(text || 'Resposta inválida do servidor.');
          }
          return { ok: r.ok, body: body };
        });
      })
      .then(function (res) {
        if (!res.body.ok) {
          throw new Error(res.body.error || 'Erro ao carregar aniversariantes.');
        }
        if (semTitle) {
          semTitle.textContent = 'Aniversariantes da semana (' + res.body.semanaLabel + ')';
        }
        if (diaTitle) {
          diaTitle.textContent = 'Aniversariantes do dia (' + res.body.diaLabel + ')';
        }
        semBody.innerHTML = renderAnivListHtml(
          res.body.semana,
          'Nenhum aniversariante nesta semana.',
          function (row) {
            var anoRef = parseInt(String(ref).substring(0, 4), 10);
            return badgeDataAnivJs(row.mes_aniv, row.dia_aniv, anoRef);
          }
        );
        diaBody.innerHTML = renderAnivListHtml(
          res.body.dia,
          'Nenhum aniversariante neste dia.',
          function (row) { return 'Dia ' + row.dia_aniv; }
        );
        setTabCount('cal-tab-count-semana', res.body.semana.length);
        setTabCount('cal-tab-count-dia', res.body.dia.length);
        if (activateCalTab) activateCalTab('dia');
      })
      .catch(function (err) {
        var msg = escapeHtml(err.message || 'Falha ao carregar aniversariantes.');
        semBody.innerHTML = '<p class="text-danger p-3 mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>' + msg + '</p>';
        diaBody.innerHTML = semBody.innerHTML;
      });
  }

  function initCalTooltips() {
    var days = document.querySelectorAll('.cal-day--birthday[data-cal-tooltip]');
    if (!days.length) return;

    var tip = document.createElement('div');
    tip.className = 'cal-tooltip';
    tip.setAttribute('role', 'tooltip');
    document.body.appendChild(tip);

    var activeEl = null;

    function hideTip() {
      activeEl = null;
      tip.classList.remove('is-visible');
    }

    function positionTip(el) {
      var rect = el.getBoundingClientRect();
      var left = rect.left + (rect.width / 2);
      var top  = rect.top;
      tip.style.left = left + 'px';
      tip.style.top  = top + 'px';
    }

    function showTip(el) {
      var raw = el.getAttribute('data-cal-tooltip');
      if (!raw) return;

      var names;
      try {
        names = JSON.parse(raw);
      } catch (e) {
        return;
      }
      if (!Array.isArray(names) || !names.length) return;

      var listHtml = names.map(function (n) {
        return '<li>' + escapeHtml(n) + '</li>';
      }).join('');

      tip.innerHTML = '<div class="cal-tooltip-title">'
        + '<i class="fas fa-birthday-cake"></i>Aniversariantes</div><ul>' + listHtml + '</ul>';

      activeEl = el;
      positionTip(el);
      tip.classList.add('is-visible');
    }

    days.forEach(function (el) {
      el.addEventListener('mouseenter', function () { showTip(el); });
      el.addEventListener('mousemove', function () {
        if (activeEl === el) positionTip(el);
      });
      el.addEventListener('mouseleave', hideTip);
      el.addEventListener('focus', function () { showTip(el); });
      el.addEventListener('blur', hideTip);
    });

    window.addEventListener('scroll', hideTip, true);
    window.addEventListener('resize', hideTip);
  }

  function initCalDayClick() {
    var days = document.querySelectorAll('.cal-day--clickable');
    if (!days.length) return;

    function handleDayClick(el) {
      var dia = parseInt(el.getAttribute('data-cal-day'), 10);
      if (!dia) return;
      var ref = String(currentAno) + '-' + String(currentMes).padStart(2, '0') + '-' + String(dia).padStart(2, '0');
      selectCalendarDay(el);
      loadAniversariantesForRef(ref);
    }

    days.forEach(function (el) {
      el.addEventListener('click', function () { handleDayClick(el); });
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          handleDayClick(el);
        }
      });
    });
  }

  function initAll() {
    initCalPicker();
    initCalTabs();
    initCalTooltips();
    initCalDayClick();
    initBirthdayEmailDialog();
  }

  function initBirthdayEmailDialog() {
    var dlg      = document.getElementById('dlgBirthdayEmail');
    var openBtn  = document.getElementById('btnSendBirthdayEmail');
    if (!dlg || !openBtn) return;

    var form     = document.getElementById('frmBirthdayEmail');
    var targets  = document.getElementById('birthdayEmailTargets');
    var feedback = document.getElementById('birthdayEmailFeedback');
    var submitBtn = document.getElementById('btnSubmitBirthdayEmail');
    var cancelBtn = document.getElementById('btnCancelBirthdayDlg');
    var closeBtn  = document.getElementById('btnCloseBirthdayDlg');
    var sendUrl   = <?= json_encode($emailSendUrl) ?>;
    var csrfToken = <?= json_encode(csrf_token()) ?>;

    function closeDlg() {
      if (dlg.open) dlg.close();
    }

    function showFeedback(type, html) {
      if (!feedback) return;
      feedback.hidden = false;
      feedback.className = 'mt-3 alert alert-' + type + ' py-2 small mb-0';
      feedback.innerHTML = html;
    }

    openBtn.addEventListener('click', function () {
      if (feedback) {
        feedback.hidden = true;
        feedback.textContent = '';
      }
      dlg.showModal();
      if (targets) targets.focus();
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeDlg);
    if (closeBtn) closeBtn.addEventListener('click', closeDlg);

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!targets || !submitBtn) return;

        var emails = targets.value.trim();
        if (!emails) {
          showFeedback('danger', 'Informe ao menos um e-mail.');
          return;
        }

        submitBtn.disabled = true;
        var originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Enviando…';

        fetch(sendUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: 'emails=' + encodeURIComponent(emails) + '&csrf=' + encodeURIComponent(csrfToken)
        })
          .then(function (r) {
            return r.text().then(function (text) {
              var body = {};
              try {
                body = text ? JSON.parse(text) : {};
              } catch (e) {
                throw new Error(text || 'Resposta inválida do servidor.');
              }
              return { ok: r.ok, body: body };
            });
          })
          .then(function (res) {
            if (res.body.ok) {
              showFeedback('success', '<i class="fas fa-check-circle mr-1"></i>' + res.body.message);
            } else {
              showFeedback('danger', '<i class="fas fa-exclamation-triangle mr-1"></i>' + (res.body.error || 'Erro ao enviar.'));
            }
          })
          .catch(function (err) {
            showFeedback('danger', '<i class="fas fa-exclamation-triangle mr-1"></i>' + (err.message || 'Falha na comunicação com o servidor.'));
          })
          .finally(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
          });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
</script>
