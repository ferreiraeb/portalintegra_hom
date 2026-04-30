<?php
// Variáveis esperadas: $marcas,$erro,$avisos,$rows,$tipoXML,$canGroup,$refGroups,$persistLog
?>
<section class="content pt-3">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Mining — Análise DI</h3>
      </div>
      <div class="card-body">

        <p class="text-muted mb-2">
          Selecione a <strong>Marca</strong> e anexe o <strong>XML</strong> (NF ou DI).<br>
          Em <strong>DI</strong>, ajuste as referências na tabela e clique em <em>Enviar correções</em>.
        </p>

        <!-- Upload -->
        <form class="form-inline mb-3" method="post" enctype="multipart/form-data" autocomplete="off">
          <?php csrf_field(); ?>
          <div class="form-group mr-2 mb-2">
            <label class="mr-2 mb-0">Marca</label>
            <select name="marca_codigo" class="form-control" required>
              <option value="" disabled selected>-- escolha --</option>
              <?php foreach ($marcas as $cod => $nome): ?>
                <option value="<?= htmlspecialchars($cod) ?>"><?= htmlspecialchars($nome) ?> = <?= htmlspecialchars($cod) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mr-2 mb-2">
            <input type="file" name="xml" class="form-control-file" accept=".xml,text/xml" required>
          </div>
          <button type="submit" class="btn btn-primary mb-2">Processar XML</button>

          <?php if (!empty($rows)): ?>
            <span class="badge badge-light ml-2 mb-2">
              <?= count($rows) ?> linha(s)<?= $tipoXML ? " • Tipo: " . htmlspecialchars($tipoXML) : "" ?>
            </span>
          <?php endif; ?>
        </form>

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php foreach ($avisos as $aviso): ?>
          <div class="alert alert-warning"><?= htmlspecialchars($aviso) ?></div>
        <?php endforeach; ?>

        <?php if (empty($erro) && !empty($rows)): ?>
          <div class="table-responsive">
            <?php if ($tipoXML === 'DI'): ?>
              <!-- Correção embutida -->
              <form method="post" autocomplete="off" class="mb-2">
                <?php csrf_field(); ?>
                <input type="hidden" name="acao" value="corrigir_refs">
                <table class="table table-hover table-bordered table-sm">
                  <thead class="thead-light">
                    <tr>
                      <?php
                      $headers = ['DI','Data DI','Codigo Item DI','Nº','Cod Produto DealerNet','Referência','Descrição','Observações','NCM XML','NCM DealerNet','Validação NCM'];
                      foreach ($headers as $h): ?>
                        <th><?= htmlspecialchars($h) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $idxDealer = array_search('Cod Produto DealerNet', $headers);
                      $idxRef    = array_search('Referência', $headers);
                      $idxValid  = array_search('Validação NCM', $headers);
                    ?>
                    <?php foreach ($rows as $i => $r): ?>
                      <tr>
                        <?php foreach ($headers as $c => $h): ?>
                          <?php
                            $val = isset($r[$c]) ? $r[$c] : '';
                            $tdClass = '';
                            if ($c === $idxDealer && $val === \Modules\Mining\AnaliseDIService::NOT_FOUND_TEXT) $tdClass = 'text-danger font-weight-bold';
                            if ($c === $idxValid) {
                              if ($val === 'OK') $tdClass = 'bg-success text-white font-weight-bold';
                              elseif ($val === 'DIFERENTE') $tdClass = 'bg-warning text-dark font-weight-bold';
                            }
                          ?>
                          <td class="<?= $tdClass ?>">
                            <?php if ($c === $idxRef): ?>
                              <input type="text" class="form-control form-control-sm" name="refs[<?= (int)$i ?>]"
                                     value="<?= htmlspecialchars((string)$val) ?>"
                                     placeholder="Informe a Referência (PN)">
                            <?php else: ?>
                              <?= htmlspecialchars((string)$val) ?>
                            <?php endif; ?>
                          </td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

                <?php if (\Security\Permission::level('drilling.analise_di') >= 2): ?>
                  <button type="submit" class="btn btn-success">Enviar correções</button>
                <?php else: ?>
                  <span class="text-muted">Você tem permissão de <strong>leitura</strong>: as correções ficam desabilitadas.</span>
                <?php endif; ?>

                <button type="button" id="exportBtn" class="btn btn-info ml-2">Exportar CSV</button>
              </form>
            <?php else: ?>
              <!-- NF -->
              <table class="table table-hover table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <?php
                      $headers = ['DI','Data DI','Nº','Cod Produto DealerNet','Referência','Descrição','Observações','NCM XML','NCM DealerNet','Validação NCM'];
                      foreach ($headers as $h): ?>
                        <th><?= htmlspecialchars($h) ?></th>
                      <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $idxDealer = array_search('Cod Produto DealerNet', $headers);
                    $idxValid  = array_search('Validação NCM', $headers);
                  ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <?php foreach ($headers as $c => $h): ?>
                        <?php
                          $val = isset($r[$c]) ? $r[$c] : '';
                          $tdClass = '';
                          if ($c === $idxDealer && $val === \Modules\Mining\AnaliseDIService::NOT_FOUND_TEXT) $tdClass = 'text-danger font-weight-bold';
                          if ($c === $idxValid) {
                            if ($val === 'OK') $tdClass = 'bg-success text-white font-weight-bold';
                            elseif ($val === 'DIFERENTE') $tdClass = 'bg-warning text-dark font-weight-bold';
                          }
                        ?>
                        <td class="<?= $tdClass ?>"><?= htmlspecialchars((string)$val) ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <button id="exportBtn" class="btn btn-info">Exportar CSV</button>
            <?php endif; ?>
          </div>

          <!-- Resumo e persistência (apenas DI e sem pendências) -->
          <?php if ($tipoXML === 'DI' && $canGroup): ?>
            <?php if (!empty($persistLog)): ?>
              <div class="alert alert-info mt-3">
                <strong>Resumo gravado em DeParaRelacionamento:</strong><br>
                Inseridos: <?= (int)$persistLog['inserted'] ?> • Atualizados: <?= (int)$persistLog['updated'] ?><br>
                <?php if (!empty($persistLog['details'])): ?>
                  <ul class="mb-0 mt-1">
                    <?php foreach ($persistLog['details'] as $line): ?>
                      <li><?= htmlspecialchars($line) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($refGroups)): ?>
              <div class="card mt-3">
                <div class="card-header"><strong>Resumo por Referência (PN)</strong> — Cod Produto DealerNet + Código(s) do Item DI</div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <thead class="thead-light">
                        <tr><th>Referência</th><th>Cod Produto DealerNet</th><th>Código(s) do Item DI</th></tr>
                      </thead>
                      <tbody>
                        <?php foreach ($refGroups as $ref => $data): ?>
                          <tr>
                            <td><?= htmlspecialchars($ref) ?></td>
                            <td><?= htmlspecialchars($data['dealer']) ?></td>
                            <td><pre class="mb-0" style="white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars(implode("\n", $data['codes'])) ?></pre></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <?php if (\Security\Permission::level('drilling.analise_di') >= 2): ?>
                <form method="post" class="mt-2">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="acao" value="persistir_resumo">
                  <button type="submit" class="btn btn-success">Gravar resumo no banco</button>
                </form>
              <?php else: ?>
                <div class="text-muted mt-2">Você tem permissão de <strong>leitura</strong>: gravação desabilitada.</div>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>

          <div class="mt-3 text-muted small">
            <span>Separador CSV: ;</span>
          </div>

        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if (empty($erro) && !empty($rows)): ?>
<script>
// ====== Exporta CSV (colunas atuais) ======
(function(){
  // Coleta headers conforme $tipoXML
  var HEADERS = <?= json_encode($headers ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  function readRows(){
    var rows=[], trs=document.querySelectorAll('table tbody tr');
    trs.forEach(function(tr){
      var row=[], tds=tr.querySelectorAll('td');
      tds.forEach(function(td){
        var inp=td.querySelector('input.form-control');
        row.push(inp ? (inp.value||'') : (td.textContent||''));
      });
      rows.push(row);
    });
    return rows;
  }
  function csv(headers, rows, sep){
    sep = sep || ';';
    function esc(v){
      v=(v==null?'':String(v)).replace(/\r/g,'');
      var q=(v.indexOf(sep)>=0 || v.indexOf('"')>=0 || v.indexOf('\n')>=0);
      return q ? '"' + v.replace(/"/g,'""') + '"' : v;
    }
    return headers.map(esc).join(sep) + '\n' + rows.map(r => r.map(esc).join(sep)).join('\n') + '\n';
  }
  function download(text, name){
    var blob=new Blob([text],{type:'text/csv;charset=utf-8'}), url=URL.createObjectURL(blob);
    var a=document.createElement('a'); a.href=url; a.download=name||'itens.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(function(){ URL.revokeObjectURL(url); }, 500);
  }
  document.getElementById('exportBtn')?.addEventListener('click', function(){
    var name = prompt('Nome do arquivo CSV:','itens_nfe_di.csv') || 'itens_nfe_di.csv';
    download(csv(HEADERS, readRows(), ';'), name);
  });
})();
</script>
<?php endif; ?>