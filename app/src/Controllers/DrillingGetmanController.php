<?php
namespace Controllers;

use Database\Connection;

class DrillingGetmanController
{
    // Marca fixa: GETMAN (236)
    private const MARCA_NOME = 'Getman';
    private const MARCA_ID   = 236;

    // Defaults exibidos como texto (percentuais)
    private const DEF_MK_LE_TXT = '40%';
    private const DEF_FI_LE_TXT = '70%';
    private const DEF_MK_GT_TXT = '30%';
    private const DEF_FI_GT_TXT = '70%';

    /** Página principal + processamento + endpoints (export/update) */
    public function index()
    {
        \Security\Auth::requireAuth();
        // ⚠️ Somente quem tem ESCRITA acessa a tela
        \Security\Permission::require('drilling.tabela_getman', 2);

        // Cross‑DB: nome do banco DealerNet (GrupoValence_HML) do config
        global $config;
        $dealerDb = $config['db']['dealernet_database'] ?? '';
        if ($dealerDb === '') {
            return render_page('drilling/tabela_getman.php', [
                'erro' => 'Database do DealerNet não configurada (db.dealernet_database).',
                'rows' => [], 'soSistema'=>[], 'soPlanilha'=>[],
                'cot' => 5.0,
                'uiMkLe'=> self::DEF_MK_LE_TXT, 'uiFiLe'=> self::DEF_FI_LE_TXT,
                'uiMkGt'=> self::DEF_MK_GT_TXT, 'uiFiGt'=> self::DEF_FI_GT_TXT,
                'marcaNome'=> self::MARCA_NOME
            ]);
        }

		 // Endpoints GET (exportar / atualizar)
		if (($_GET['export'] ?? '') === '1') {
			$this->exportCsv();
			return;
		}
		if (($_GET['update'] ?? '') === '1') {
			// >>> alteração: grava mensagem em sessão e redireciona para a própria página
			$msg = $this->atualizarValores($dealerDb);
			$_SESSION['getman_flash'] = $msg;
			redirect('drilling/tabela-getman'); // volta para a tela, onde exibiremos o alerta
			return;
		}

        // ----- Exibição inicial / processamento da planilha -----
        $erro = null; $rows = []; $soSistema = []; $soPlanilha = [];
        $cotTxt = (string)($_POST['cotacao'] ?? '5.40');
        $mkLeTxt= (string)($_POST['mk_le']  ?? self::DEF_MK_LE_TXT);
        $fiLeTxt= (string)($_POST['fi_le']  ?? self::DEF_FI_LE_TXT);
        $mkGtTxt= (string)($_POST['mk_gt']  ?? self::DEF_MK_GT_TXT);
        $fiGtTxt= (string)($_POST['fi_gt']  ?? self::DEF_FI_GT_TXT);

        $cot  = $this->toNumber($cotTxt, 5.40);
        $mkLe = $this->percentToFraction($mkLeTxt, 0.40);
        $fiLe = $this->percentToFraction($fiLeTxt, 0.017);
        $mkGt = $this->percentToFraction($mkGtTxt, 0.30);
        $fiGt = $this->percentToFraction($fiGtTxt, 0.017);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
            try {
                // 1) Lê XLSX: mapa [ref => ['Valor'=>, 'PartDescription'=>]]
                $plan = $this->lerPlanilhaGetmanXlsx($_FILES['arquivo']['tmp_name']);
				
				// <<< BEGIN PATCH — normalização de separadores + cruzamento exato >>>

				// 2) Produtos do DealerNet (sistema)
				$sistema = $this->carregarListaProdutos($dealerDb, self::MARCA_ID);
				$sysByRef = $this->indexBy($sistema, 'ProdutoReferencia'); // mantém seu índice original

				// 2.1) Mapas de chave NORMALIZADA -> referência ORIGINAL
				$sysNorm2Ref  = [];  // ex.: "30GH08" (normalizado) -> "30-GH-08" (como está no DealerNet)
				$planNorm2Ref = [];  // ex.: "30GH08" (normalizado) -> "30GH08" (como está na planilha)

				foreach ($sysByRef as $refSys => $p) {
					$k = $this->normalizeRefStrict((string)$refSys);
					if ($k !== '' && !isset($sysNorm2Ref[$k])) {
						$sysNorm2Ref[$k] = (string)$refSys;
					}
				}
				foreach ($plan as $refPlan => $payload) {
					// A planilha já vem como mapa [ref => payload]; use a CHAVE
					$k = $this->normalizeRefStrict((string)$refPlan);
					if ($k !== '' && !isset($planNorm2Ref[$k])) {
						$planNorm2Ref[$k] = (string)$refPlan;
					}
				}

				// 3) Cruzamento + cálculos
				$params = ['mk_le'=>$mkLe,'fi_le'=>$fiLe,'mk_gt'=>$mkGt,'fi_gt'=>$fiGt];
				$rows       = [];
				$soSistema  = [];
				$soPlanilha = [];

				// 3.1) Match exato por chave NORMALIZADA
				$commonsNorm = array_intersect(array_keys($sysNorm2Ref), array_keys($planNorm2Ref));

				foreach ($commonsNorm as $kn) {
					$refSys  = $sysNorm2Ref[$kn];   // referência original no DealerNet (pode ter separadores)
					$refPlan = $planNorm2Ref[$kn];  // referência original na planilha

					$p       = $sysByRef[$refSys];      // linha completa do DealerNet
					$payload = $plan[$refPlan] ?? [];   // payload da planilha

					$valorUS = (float)($payload['Valor'] ?? 0);
					[$mkFrac, $fiFrac, $tipo] = $this->decideParamsByValue($params, $valorUS);
					$calc = $this->calcularPrecos($valorUS, $cot, $fiFrac, $mkFrac);

					$rows[] = [
						'Marca'              => $p['Marca'],
						'Empresa'            => $p['Empresa'],
						'ProdutoCodigo'      => $p['ProdutoCodigo'],
						'ProdutoReferencia'  => (string)$p['ProdutoReferencia'], // mantém a forma “oficial” do DealerNet
						'ProdutoDescricao'   => $p['ProdutoDescricao'],
						'NCMCodigo'          => $p['NCMCodigo'],
						'NCMIdentificador'   => (string)$p['NCMIdentificador'],
						'ValorPlanilha(US$)' => $valorUS,
						'Markup'             => $mkFrac,
						'FatorImportacao'    => $fiFrac,
						'CotacaoDolar'       => $cot,
						'Tipo'               => $tipo,
						'PrecoPublico'       => $calc['PP'],
						'PrecoSugerido'      => $calc['PS'],
						'PrecoGarantia'      => $calc['GA'],
						'PrecoReposicao'     => $calc['RP'],
					];
				}

				// 3.2) Apenas DealerNet = sysNorm - planNorm
				$onlySysNorm = array_diff(array_keys($sysNorm2Ref), array_keys($planNorm2Ref));
				foreach ($onlySysNorm as $kn) {
					$refSys = $sysNorm2Ref[$kn];
					$soSistema[] = $sysByRef[$refSys];
				}

				// 3.3) Apenas Planilha = planNorm - sysNorm
				$onlyPlanNorm = array_diff(array_keys($planNorm2Ref), array_keys($sysNorm2Ref));
				foreach ($onlyPlanNorm as $kn) {
					$refPlan  = $planNorm2Ref[$kn];
					$payload  = $plan[$refPlan] ?? [];
					$soPlanilha[] = [
						'ProdutoReferencia'   => (string)$refPlan,                       // forma como veio no XLSX
						'PartDescription'     => $payload['PartDescription'] ?? '',
						'ValorPlanilha(US$)'  => isset($payload['Valor']) ? $payload['Valor'] : 0,
						'RefNormalizada'      => $kn,                                    // diagnóstico
					];
				}

				// <<< END PATCH >>>

				


                // guarda na sessão para Export/Update
                $_SESSION['getman_result']   = $rows;
                $_SESSION['getman_sysOnly']  = $soSistema;
                $_SESSION['getman_xlsxOnly'] = $soPlanilha;

            } catch (\Throwable $e) {
                $erro = 'Falha ao processar a planilha: '.$e->getMessage();
            }
        } else {
            // inicial
            $_SESSION['getman_result']   = $_SESSION['getman_result']   ?? [];
            $_SESSION['getman_sysOnly']  = $_SESSION['getman_sysOnly']  ?? [];
            $_SESSION['getman_xlsxOnly'] = $_SESSION['getman_xlsxOnly'] ?? [];
            $rows       = $_SESSION['getman_result'];
            $soSistema  = $_SESSION['getman_sysOnly'];
            $soPlanilha = $_SESSION['getman_xlsxOnly'];
        }

        render_page('drilling/tabela_getman.php', [
            'erro'      => $erro,
            'rows'      => $rows,
            'soSistema' => $soSistema,
            'soPlanilha'=> $soPlanilha,
            'cot'       => $cot,
            'uiMkLe'    => $mkLeTxt ?: self::DEF_MK_LE_TXT,
            'uiFiLe'    => $fiLeTxt ?: self::DEF_FI_LE_TXT,
            'uiMkGt'    => $mkGtTxt ?: self::DEF_MK_GT_TXT,
            'uiFiGt'    => $fiGtTxt ?: self::DEF_FI_GT_TXT,
            'marcaNome' => self::MARCA_NOME
        ]);
    }

    /* =================== ENDPOINTS AUXILIARES =================== */

    /** GET ?export=1 */
    private function exportCsv(): void
    {
        $dados = $_SESSION['getman_result'] ?? [];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="getman_result.csv"');
        // BOM para Excel
        echo "\xEF\xBB\xBF";

        if (empty($dados)) { echo "Sem dados.\n"; return; }

        $headers = array_keys($dados[0]);
        $f = fopen('php://output','w');
        fputcsv($f, $headers, ';');

        foreach ($dados as $r) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $this->csvFormat($h, $r[$h] ?? '');
            }
            fputcsv($f, $line, ';');
        }
        fclose($f);
    }

    /** GET ?update=1 */
    private function atualizarValores(string $dealerDb): string
    {
        $resultado = $_SESSION['getman_result'] ?? [];
        if (empty($resultado)) return "Sem dados para atualizar.";

        $pdo = Connection::get();
        // 1) Testa permissão de EXECUTE na proc do DealerNet
        $hasExec = 0;
        try {
            $check = $pdo->query("SELECT HAS_PERMS_BY_NAME('{$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS','OBJECT','EXECUTE') AS HasExec");
            $hasExec = (int)$check->fetchColumn();
        } catch (\Throwable $e) {
            // se falhar o HAS_PERMS_BY_NAME, tentaremos assim mesmo e reportamos erro da EXEC se houver
            $hasExec = 1;
        }
        if ($hasExec !== 1) {
            return "Sem permissão para executar {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS. Solicite GRANT EXECUTE ao DBA.";
        }

        // 2) Auditoria no Portal_Integra
        $opGuid = $this->uuidv4();
        $pdo->beginTransaction();
        try {
            $ins = "
                INSERT INTO [Portal_Integra].dbo.Atualizacao_Precos_DealerNet
                (IDUnico, dataHoraProcessamento, Marca_Codigo, Marca_Empresa,
                 Marca, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                 NCMCodigo, NCMIdentificador, [ValorPlanilha(US$)], Markup,
                 FatorImportacao, CotacaoDolar, Tipo,
                 PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
                VALUES
                (:id,:dh,:marcaCod,:marcaEmp,:marca,:emp,:cod,:ref,:desc,:ncmCod,:ncmId,:pl,:mk,:fi,:cot,:tipo,:pp,:ps,:ga,:rp)
            ";
            $stmt = $pdo->prepare($ins);
            $now  = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

            foreach ($resultado as $r) {
                $stmt->execute([
                    ':id'       => $opGuid,
                    ':dh'       => $now,
                    ':marcaCod' => self::MARCA_ID,
                    ':marcaEmp' => (string)($r['Empresa'] ?? ''),
                    ':marca'    => (string)self::MARCA_NOME,
                    ':emp'      => (string)($r['Empresa'] ?? ''),
                    ':cod'      => (int)($r['ProdutoCodigo'] ?? 0),
                    ':ref'      => (string)($r['ProdutoReferencia'] ?? ''),
                    ':desc'     => (string)($r['ProdutoDescricao'] ?? ''),
                    ':ncmCod'   => ($r['NCMCodigo'] ?? null) !== null ? (int)$r['NCMCodigo'] : null,
                    ':ncmId'    => (string)($r['NCMIdentificador'] ?? ''),
                    ':pl'       => (float)($r['ValorPlanilha(US$)'] ?? 0),
                    ':mk'       => (float)($r['Markup'] ?? 0),
                    ':fi'       => (float)($r['FatorImportacao'] ?? 0),
                    ':cot'      => (float)($r['CotacaoDolar'] ?? 0),
                    ':tipo'     => (string)($r['Tipo'] ?? ''),
                    ':pp'       => (float)($r['PrecoPublico'] ?? 0),
                    ':ps'       => (float)($r['PrecoSugerido'] ?? 0),
                    ':ga'       => (float)($r['PrecoGarantia'] ?? 0),
                    ':rp'       => (float)($r['PrecoReposicao'] ?? 0),
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return "Falha ao registrar auditoria: ".$e->getMessage();
        }

        // 3) EXEC da procedure por item no DealerNet
        $ok = 0; $fail = 0; $errs = [];
        $hoje = (new \DateTime('today', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $execSql = "
            EXEC {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS
                @Marca_Codigo = :marca,
                @Produto_Codigo = :prod,
                @PrecoPublico = :pp,
                @PrecoSugerido = :ps,
                @PrecoGarantia = :ga,
                @PrecoReposicao = :rp,
                @DataVigencia = :vig,
                @DataValidade = :val,
                @Empresa_Codigo = :empCod,
                @Usuario_Codigo = :usr
        ";
        $stmtExec = $pdo->prepare($execSql);

        foreach ($resultado as $r) {
            try {
                $stmtExec->execute([
                    ':marca'  => self::MARCA_ID,
                    ':prod'   => (int)$r['ProdutoCodigo'],
                    ':pp'     => (float)$r['PrecoPublico'],
                    ':ps'     => (float)$r['PrecoSugerido'],
                    ':ga'     => (float)$r['PrecoGarantia'],
                    ':rp'     => (float)$r['PrecoReposicao'],
                    ':vig'    => $hoje,
                    ':val'    => null,   // DataValidade = NULL
                    ':empCod' => null,   // Empresa_Codigo = NULL
                    ':usr'    => 813     // usuário técnico (ajustável)
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $fail++; $errs[] = "Produto {$r['ProdutoCodigo']}: ".$e->getMessage();
            }
        }

        if ($fail > 0) {
            return "Operação registrada (ID={$opGuid}). Atualizações OK: {$ok}. Falhas: {$fail}.\n"
                 . implode("\n", $errs);
        }
        return "Operação concluída. ID={$opGuid}. Itens atualizados: {$ok}.";
    }

    /* =================== FUNÇÕES DE DOMÍNIO =================== */

    private function toNumber(string $s, float $def=0.0): float {
        $num = preg_replace('/[^\d,.\-]/','', $s);
        $num = str_replace(',', '.', $num);
        if ($num === '' || $num === '.' || $num === '-' || $num === '+') return $def;
        return (float)$num;
    }

    /** "40%" → 0.40 ; "1,7%" → 0.017 ; números >1 sem % também viram fração (ex.: 40 → 0.40) */
    private function percentToFraction(string $txt, float $default): float {
        $s = trim($txt);
        $hasPercent = strpos($s, '%') !== false;
        $s = preg_replace('/[^\d.,+\-]/','', $s);
        $s = str_replace(',', '.', $s);
        if ($s === '' || $s === '.' || $s === '-' || $s === '+') return $default;
        $num = (float)$s;
        if ($hasPercent || $num > 1.0) return $num / 100.0;
        return $num;
    }

    /** Decide parâmetros de markup/fator pela faixa de valor (<= US$10k ou > US$10k) */
    private function decideParamsByValue(array $usdFractionParams, float $valorUS): array {
        if ($valorUS <= 10000.0)
            return [$usdFractionParams['mk_le'],$usdFractionParams['fi_le'],'Getman-SpareParts<10k'];
        return   [$usdFractionParams['mk_gt'],$usdFractionParams['fi_gt'],'Getman-SpareParts>10k'];
    }

    /** Calcula 4 preços (PP/PS/GA/RP) */
    private function calcularPrecos(float $valorUS, float $cot, float $fator, float $markup): array {
        $valor_com_fator = $valorUS + ($valorUS * $fator);
        $valor_com_markup= $valorUS * $markup;
        $pp = ($valor_com_fator + $valor_com_markup) * $cot; // Público
        $ps = ($valor_com_fator + $valor_com_markup) * $cot; // Sugerido
        $ga = ($valor_com_fator) * $cot; // Garantia
        $rp = ($valor_com_fator) * $cot; // Reposição
        return ['PP'=>$pp,'PS'=>$ps,'GA'=>$ga,'RP'=>$rp];
    }

    /** Indexa linhas por chave */
    private function indexBy(array $rows, string $key): array {
        $m = [];
        foreach ($rows as $r) if (isset($r[$key])) $m[(string)$r[$key]] = $r;
        return $m;
    }

    /** Leitor XLSX (ZipArchive + SimpleXML) → mapa [ref => {Valor, PartDescription}] */
    private function lerPlanilhaGetmanXlsx(string $filePath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) throw new \RuntimeException("Não foi possível abrir o XLSX.");

        $sheetPath = $this->xlsxFirstSheetPath($zip);
        $sheet = $this->loadXmlFromZip($zip, $sheetPath);
        if (!$sheet) throw new \RuntimeException("Não foi possível ler a planilha interna.");

        $sst = $this->xlsxSharedStrings($zip);
        $zip->close();

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $c) {
                $r = (string)$c['r'];
                $ci = $this->colToIdx($r); // 1-based
                $t  = (string)$c['t'];     // 's' => shared string
                $v  = '';
                if (isset($c->v))       $v = (string)$c->v;
                elseif (isset($c->is->t)) $v = (string)$c->is->t; // inlineStr

                if ($t === 's') { $idx = (int)$v; $val = $sst[$idx] ?? ''; }
                else             { $val = $v; }

                if (is_numeric($val)) $val = $val + 0; else $val = trim((string)$val);
                $line[$ci] = $val;
            }
            if (!empty($line)) $rows[] = $line;
        }

        // Detecta cabeçalho (até 10 primeiras linhas)
        [$hdrRow, $hdrMap, $idxRef, $idxPreco, $rawHeader, $idxDesc] = $this->findHeaderAndCols($rows);
        if ($idxRef === null) {
            $det = implode("\n", array_map(function($v){return (string)$v;}, $rawHeader));
            throw new \RuntimeException("Coluna PartNum não encontrada. Cabeçalho detectado: ".$det);
        }
        if ($idxPreco === null) throw new \RuntimeException("Coluna de preço não identificada.");

        $data = [];
        for ($r = $hdrRow+1; $r < count($rows); $r++) {
            $row = $rows[$r];
            if (!is_array($row) || empty($row)) continue;

            $ref  = isset($row[$idxRef])  ? trim((string)$row[$idxRef]) : '';
            if ($ref === '') continue;

            $desc = ($idxDesc !== null && isset($row[$idxDesc])) ? (string)$row[$idxDesc] : '';
            $raw  = $row[$idxPreco] ?? 0;

            if (is_string($raw)) {
                $num = preg_replace('/[^\d,.\-]/','', $raw);
                $num = str_replace(['.',','],['','.'], $num);
                $val = (float)$num;
            } else {
                $val = (float)$raw;
            }
            $data[$ref] = ['Valor'=>$val,'PartDescription'=>$desc];
        }
        return $data;
    }

    /* ------------ Helpers de XLSX ------------- */
    private function colToIdx($cellRef): int {
        if (!$cellRef) return 0;
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
        $n = 0; for ($i=0; $i<strlen($letters); $i++){ $n = $n*26 + (ord($letters[$i]) - 64); }
        return $n;
    }
    private function loadXmlFromZip(\ZipArchive $zip, string $path) {
        $idx = $zip->locateName($path, 0);
        if ($idx === false) return null;
        $data = $zip->getFromIndex($idx);
        if ($data === false) return null;
        return @simplexml_load_string($data);
    }
    private function xlsxFirstSheetPath(\ZipArchive $zip): string {
        $wb = $this->loadXmlFromZip($zip, 'xl/workbook.xml');
        if (!$wb) return 'xl/worksheets/sheet1.xml';
        $rels = $this->loadXmlFromZip($zip, 'xl/_rels/workbook.xml.rels');
        if (!$rels) return 'xl/worksheets/sheet1.xml';

        $ns = $wb->getNamespaces(true);
        $sheet = $wb->sheets->sheet[0] ?? null;
        if (!$sheet) return 'xl/worksheets/sheet1.xml';
        $rid = (string)$sheet->attributes($ns['r'])['id'];

        foreach ($rels->Relationship as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $target = (string)$rel['Target'];
                if (strpos($target, 'worksheets/') === false) $target = 'xl/' . ltrim($target, '/');
                else $target = 'xl/' . $target;
                return $target;
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }
    private function xlsxSharedStrings(\ZipArchive $zip): array {
        $sst = $this->loadXmlFromZip($zip, 'xl/sharedStrings.xml');
        $arr = [];
        if (!$sst) return $arr;
        foreach ($sst->si as $si) {
            $textParts = [];
            if (isset($si->t)) $textParts[] = (string)$si->t;
            if (isset($si->r)){
                foreach ($si->r as $r) { if (isset($r->t)) $textParts[] = (string)$r->t; }
            }
            $arr[] = trim(implode('', $textParts));
        }
        return $arr;
    }
	
    private function findHeaderAndCols(array $rows): array {
        $headerRowIdx = null; $headerMap = []; $rawHeader = [];
        $maxScan = min(10, count($rows));
        $want = ['partnum','partnumber'];

        for ($r=0; $r<$maxScan; $r++) {
            if (empty($rows[$r])) continue;
            $map = [];
            foreach ($rows[$r] as $colIdx => $val) {
                $txt = is_string($val) ? trim($val) : (string)$val;
                if ($txt !== '') $map[$colIdx] = $txt;
            }
            if (!$map) continue;
            $norms = array_map(function($x){
                return strtolower(preg_replace('/[\s_]+/','',trim((string)$x)));
            }, array_values($map));

            if (count(array_intersect($norms, $want))>0 || in_array('partnum',$norms,true)) {
                $headerRowIdx = $r; $headerMap = $map; $rawHeader = array_values($map); break;
            }
        }
        if ($headerRowIdx === null && count($rows) >= 2) {
            $headerRowIdx = 1; $headerMap = $rows[1]; $rawHeader = array_values($rows[1]);
        }
        $colPartNumIdx=null; $colPrecoIdx=null; $colPartDescIdx=null;

        foreach ($headerMap as $i=>$h){
            $hn = strtolower(preg_replace('/[\s_]+/','',trim((string)$h)));
            if ($hn === 'partnum' || $hn === 'partnumber') $colPartNumIdx = $i;
            if ($hn === 'partdescription') $colPartDescIdx = $i;
        }
        foreach ($headerMap as $i=>$h){
            $label = trim((string)$h); if ($label === '') continue;
            $normL = strtoupper($label);
            if (preg_match('/\b20\d{2}\b/', $label) || strpos($normL, 'GLP') !== false) { $colPrecoIdx = $i; break; }
        }
        if ($colPrecoIdx === null) { end($headerMap); $colPrecoIdx = key($headerMap); reset($headerMap); }

        return [$headerRowIdx, $headerMap, $colPartNumIdx, $colPrecoIdx, $rawHeader, $colPartDescIdx];
    }

    /** Consulta cross‑database para listar produtos + preços vigentes por empresa/tabela */
    private function carregarListaProdutos(string $dealerDb, int $marcaId): array
    {
        $pdo = Connection::get();
        $pfx = '['.preg_replace('/[^A-Za-z0-9_.]/','',$dealerDb).'].dbo.';

        $sql = "
WITH Emp AS (
    SELECT E.Empresa_Codigo, E.Empresa_Nome, M.Marca_Descricao
    FROM {$pfx}Marca M
    INNER JOIN {$pfx}EmpresaMarca EM ON EM.EmpresaMarca_MarcaCod = M.Marca_Codigo
    INNER JOIN {$pfx}Empresa E ON E.Empresa_Codigo = EM.Empresa_Codigo
    WHERE M.Marca_Codigo = :marca
),
Prod AS (
    SELECT
      PM.Produto_Codigo,
      COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico) AS Produto_Referencia,
      P.Produto_Descricao AS ProdutoDescricao,
      P.Produto_DescricaoDetalhada AS DescricaoDetalhada,
      N.NCM_Identificador,
      N.NCM_Codigo
    FROM {$pfx}ProdutoMarca PM
    INNER JOIN {$pfx}Produto P ON P.Produto_Codigo = PM.Produto_Codigo
    LEFT JOIN {$pfx}NCM N ON N.NCM_Codigo = P.Produto_NCMCod
    WHERE PM.ProdutoMarca_MarcaCod = :marca2
),
TabEmp AS (
    SELECT etp.Empresa_Codigo, etp.EmpresaTabelaPreco_TabPrecoCod AS TabelaPreco_Codigo, tp.TabelaPreco_Tipo
    FROM {$pfx}EmpresaTabelaPreco etp
    INNER JOIN Emp e ON e.Empresa_Codigo = etp.Empresa_Codigo
    INNER JOIN {$pfx}TabelaPreco tp ON tp.TabelaPreco_Codigo = etp.EmpresaTabelaPreco_TabPrecoCod
    WHERE tp.TabelaPreco_Tipo IN ('PP','PS','GA','RP')
),
PrecoBase AS (
    SELECT
      te.Empresa_Codigo,
      pp.Produto_Codigo,
      tp.TabelaPreco_Tipo,
      pp.ProdutoPreco_Valor,
      pp.ProdutoPreco_DataVigencia,
      pp.ProdutoPreco_DataValidade,
      pp.ProdutoPreco_DataAlteracao,
      pp.ProdutoPreco_DataCriacao,
      ROW_NUMBER() OVER (
        PARTITION BY te.Empresa_Codigo, pp.Produto_Codigo, tp.TabelaPreco_Tipo
        ORDER BY
         CASE WHEN pp.ProdutoPreco_DataVigencia IS NOT NULL
            AND pp.ProdutoPreco_DataVigencia <= GETDATE()
            AND (pp.ProdutoPreco_DataValidade IS NULL OR pp.ProdutoPreco_DataValidade >= GETDATE())
           THEN 0 ELSE 1 END,
         pp.ProdutoPreco_DataVigencia DESC,
         pp.ProdutoPreco_DataAlteracao DESC,
         pp.ProdutoPreco_DataCriacao DESC
      ) AS rn
    FROM {$pfx}ProdutoPreco pp
    INNER JOIN TabEmp te ON te.TabelaPreco_Codigo = pp.ProdutoPreco_TabelaPrecoCod
    INNER JOIN {$pfx}TabelaPreco tp ON tp.TabelaPreco_Codigo = pp.ProdutoPreco_TabelaPrecoCod
),
PrecoTop AS (
    SELECT Empresa_Codigo, Produto_Codigo, TabelaPreco_Tipo, ProdutoPreco_Valor
    FROM PrecoBase
    WHERE rn = 1
)
SELECT
  e.Marca_Descricao AS Marca,
  e.Empresa_Nome AS Empresa,
  pr.Produto_Codigo AS ProdutoCodigo,
  pr.Produto_Referencia AS ProdutoReferencia,
  pr.ProdutoDescricao,
  pr.DescricaoDetalhada,
  pr.NCM_Codigo AS NCMCodigo,
  pr.NCM_Identificador AS NCMIdentificador,
  CAST(MAX(CASE WHEN pt.TabelaPreco_Tipo='PP' THEN pt.ProdutoPreco_Valor END) AS decimal(18,4)) AS PrecoPublico,
  CAST(MAX(CASE WHEN pt.TabelaPreco_Tipo='PS' THEN pt.ProdutoPreco_Valor END) AS decimal(18,4)) AS PrecoSugerido,
  CAST(MAX(CASE WHEN pt.TabelaPreco_Tipo='GA' THEN pt.ProdutoPreco_Valor END) AS decimal(18,4)) AS PrecoGarantia,
  CAST(MAX(CASE WHEN pt.TabelaPreco_Tipo='RP' THEN pt.ProdutoPreco_Valor END) AS decimal(18,4)) AS PrecoReposicao
FROM Emp e
CROSS JOIN Prod pr
LEFT JOIN PrecoTop pt
  ON pt.Empresa_Codigo = e.Empresa_Codigo
 AND pt.Produto_Codigo = pr.Produto_Codigo
GROUP BY
  e.Marca_Descricao, e.Empresa_Nome,
  pr.Produto_Codigo, pr.Produto_Referencia, pr.ProdutoDescricao, pr.DescricaoDetalhada,
  pr.NCM_Codigo, pr.NCM_Identificador
ORDER BY e.Empresa_Nome, pr.ProdutoDescricao
OPTION (RECOMPILE);
        ";

        $st = $pdo->prepare($sql);
        $st->bindValue(':marca',  $marcaId, \PDO::PARAM_INT);
        $st->bindValue(':marca2', $marcaId, \PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll();
        // Numeric cast
        foreach ($rows as &$r) {
            foreach (['PrecoPublico','PrecoSugerido','PrecoGarantia','PrecoReposicao'] as $k) {
                if (isset($r[$k]) && $r[$k] !== null) $r[$k] = (float)$r[$k];
            }
        }
        return $rows;
    }

    /** Formatação CSV: colunas “cruas” vs numéricas PT-BR */
    private function csvFormat(string $col, $val): string
    {
        static $raw = ['ProdutoCodigo','ProdutoReferencia','NCMIdentificador','NCMCodigo'];
        if (in_array($col, $raw, true)) return (string)$val;
        if (is_numeric($val)) return number_format((float)$val, 2, ',', '.');
        return (string)$val;
    }

    /** GUID v4 simples */
    private function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
	
	/** Normaliza referência mantendo letras e números, removendo apenas separadores. */
	private function normalizeRefStrict(string $s): string
	{
		$u = strtoupper(trim($s));
		// Mantém [A-Z0-9], remove separadores (espaço, hífen, barra, ponto, underscore, etc.)
		return preg_replace('/[^A-Z0-9]/', '', $u) ?? '';
	}

	
	/** Constrói índice por referência normalizada para lookups exatos. */
	/*
	private function buildNormalizedIndex(array $rows, string $refKey = 'ProdutoReferencia'): array
	{
		$idx = [];
		foreach ($rows as $r) {
			$ref = isset($r[$refKey]) ? (string)$r[$refKey] : '';
			if ($ref === '') continue;
			$k = $this->normalizeRefStrict($ref);
			if ($k !== '' && !isset($idx[$k])) {
				$idx[$k] = $r;
			}
		}
		return $idx;
	}	
	
	*/
	
	
	
}
?>
