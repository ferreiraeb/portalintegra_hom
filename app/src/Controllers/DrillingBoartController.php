<?php
namespace Controllers;

use Database\Connection;

class DrillingBoartController
{
    const MARCA_ID   = 152;                  // Boart
    const MARCA_NOME = 'Boart LongYear';

    public function index()
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('drilling.tabela_boart', 2);

        global $config;
        $dealerDb = $config['db']['dealernet_database'] ?? '';
        if ($dealerDb === '') {
            return render_page('drilling/tabela_boart.php', [
                'erro' => 'Database do DealerNet não configurada (db.dealernet_database).',
                'rows'=>[], 'soSistema'=>[], 'soPlanilha'=>[],
                'cot' => 5.0, 'marcaNome'=> self::MARCA_NOME
            ]);
        }

        // Export / Update
        if (($_GET['export'] ?? '') === '1') { $this->exportCsv(); return; }
        if (($_GET['update'] ?? '') === '1') {
            $msg = $this->atualizarValores($dealerDb);
            $_SESSION['boart_flash'] = $msg;
            redirect('drilling/tabela-boart'); return;
        }
        // AJAX: atualização de preços em chunks (PortalModal)
        if (($_GET['action'] ?? '') === 'update_chunk') {
            $this->ajaxUpdateChunk($dealerDb);
            return;
        }

        $erro=null; $rows=[]; $soSistema=[]; $soPlanilha=[];
        $cotTxt = (string)($_POST['cotacao'] ?? '5.40');
        $cot = $this->toNumber($cotTxt, 5.40);

        // Tipo selecionado (Ferramentais, Diamantados, Rock Tools, Spare Parts)
        $tipoTabela = (string)($_POST['tipo_tabela'] ?? 'Ferramentais');

        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['arquivo'])) {
            try {
                // 1) Lê XLSX conforme o tipo
                $plan = $this->lerPlanilhaBoartXlsx($_FILES['arquivo']['tmp_name'], $tipoTabela);

                // 2) Carrega DealerNet (mesma query base da Getman trocando marca)
                $sistema = $this->carregarListaProdutos($dealerDb, self::MARCA_ID);

                // 2.1) Índices normalizados (reutiliza normalizeRefStrict do Getman)
                $sysByRef = $this->indexBy($sistema, 'ProdutoReferencia');
                $sysNorm2Ref = []; $planNorm2Ref = [];
                foreach ($sysByRef as $refSys => $p) {
                    $k = $this->normalizeRefStrict((string)$refSys);
                    if ($k!=='' && !isset($sysNorm2Ref[$k])) $sysNorm2Ref[$k]=(string)$refSys;
                }
                foreach ($plan as $refPlan => $payload) {
                    $k = $this->normalizeRefStrict((string)$refPlan);
                    if ($k!=='' && !isset($planNorm2Ref[$k])) $planNorm2Ref[$k]=(string)$refPlan;
                }

                // 3) Parametrização de Markup/FI por tipo e faixa (≤ 10k | > 10k)
                $params = $this->paramsBoart($tipoTabela);

                $rows=[]; $soSistema=[]; $soPlanilha=[];
                $commons = array_intersect(array_keys($sysNorm2Ref), array_keys($planNorm2Ref));

                foreach ($commons as $kn) {
                    $refSys  = $sysNorm2Ref[$kn];
                    $refPlan = $planNorm2Ref[$kn];

                    $p       = $sysByRef[$refSys];
                    $valorUS = (float)($plan[$refPlan]['Valor'] ?? 0);

                    // decide markup/fator pela FAIXA (<=10k / >10k)
                    [$mkFrac, $fiFrac, $tipo] = $this->decideParamsBoartByValue($params, $valorUS);

                    // cálculo igual à Getman: em USD -> aplica FI/Markup -> converte pela cotação
                    $calc = $this->calcularPrecosUSD($valorUS, $cot, $fiFrac, $mkFrac);

                    $rows[] = [
                        'Marca'              => $p['Marca'],
                        'Empresa'            => $p['Empresa'],
                        'ProdutoCodigo'      => $p['ProdutoCodigo'],
                        'ProdutoReferencia'  => (string)$p['ProdutoReferencia'],
                        'ProdutoDescricao'   => $p['ProdutoDescricao'],
                        'NCMCodigo'          => $p['NCMCodigo'],
                        'NCMIdentificador'   => (string)$p['NCMIdentificador'],
                        'ValorPlanilha(US$)' => $valorUS,
                        'Markup'             => $mkFrac,
                        'FatorImportacao'    => $fiFrac,
                        'CotacaoDolar'       => $cot,
                        'Tipo'               => $tipo, // conforme especificado
                        'PrecoPublico'       => $this->moneyRound($calc['PP']),
                        'PrecoSugerido'      => $this->moneyRound($calc['PS']),
                        'PrecoGarantia'      => $this->moneyRound($calc['GA']),
                        'PrecoReposicao'     => $this->moneyRound($calc['RP']),
                    ];
                }

                // Apenas DealerNet
                foreach (array_diff(array_keys($sysNorm2Ref), array_keys($planNorm2Ref)) as $kn) {
                    $soSistema[] = $sysByRef[$sysNorm2Ref[$kn]];
                }
                // Apenas Planilha
                foreach (array_diff(array_keys($planNorm2Ref), array_keys($sysNorm2Ref)) as $kn) {
                    $refPlan = $planNorm2Ref[$kn];
                    $payload = $plan[$refPlan] ?? [];
                    $soPlanilha[] = [
                        'ProdutoReferencia'   => (string)$refPlan,
                        'PartDescription'     => $payload['PartDescription'] ?? '',
                        'ValorPlanilha(US$)'  => isset($payload['Valor']) ? $payload['Valor'] : 0,
                        'RefNormalizada'      => $kn,
                    ];
                }

                $_SESSION['boart_result']   = $rows;
                $_SESSION['boart_sysOnly']  = $soSistema;
                $_SESSION['boart_xlsxOnly'] = $soPlanilha;

            } catch (\Throwable $e) {
                $erro = 'Falha ao processar a planilha: '.$e->getMessage();
            }
        } else {
            $_SESSION['boart_result']   = $_SESSION['boart_result']   ?? [];
            $_SESSION['boart_sysOnly']  = $_SESSION['boart_sysOnly']  ?? [];
            $_SESSION['boart_xlsxOnly'] = $_SESSION['boart_xlsxOnly'] ?? [];
            $rows       = $_SESSION['boart_result'];
            $soSistema  = $_SESSION['boart_sysOnly'];
            $soPlanilha = $_SESSION['boart_xlsxOnly'];
        }

        render_page('drilling/tabela_boart.php', [
            'erro'       => $erro,
            'rows'       => $rows,
            'soSistema'  => $soSistema,
            'soPlanilha' => $soPlanilha,
            'cot'        => $cot,
            'marcaNome'  => self::MARCA_NOME,
            'tipoTabela' => $tipoTabela
        ]);
    }

    /* ========================= Helpers específicos Boart ========================= */

    private function paramsBoart(string $tipo): array
    {
        // retorna estrutura com as faixas (<=10k e >10k) + rótulo Tipo
        switch (strtolower($tipo)) {
            case 'diamantados':
            case 'diamantados (coroas)':
                return [
                    'le' => ['mk'=>0.15, 'fi'=>0.35, 'tipo'=>'Boart-Diamantados'],
                    'gt' => ['mk'=>0.15, 'fi'=>0.35, 'tipo'=>'Boart-Diamantados'],
                ];
            case 'rock tools':
                return [
                    'le' => ['mk'=>0.20, 'fi'=>0.35, 'tipo'=>'Boart-RockTools'],
                    'gt' => ['mk'=>0.20, 'fi'=>0.35, 'tipo'=>'Boart-RockTools'],
                ];
            case 'spare parts':
                return [
                    'le' => ['mk'=>0.40, 'fi'=>0.70, 'tipo'=>'Boart-SpareParts<10k'],
                    'gt' => ['mk'=>0.30, 'fi'=>0.70, 'tipo'=>'Boart-SpareParts>10k'],
                ];
            case 'ferramentais':
            default:
                return [
                    'le' => ['mk'=>0.30, 'fi'=>0.35, 'tipo'=>'Boart-Ferramentais'],
                    'gt' => ['mk'=>0.30, 'fi'=>0.35, 'tipo'=>'Boart-Ferramentais'],
                ];
        }
    }

    private function decideParamsBoartByValue(array $params, float $valorUSD): array
    {
        if ($valorUSD <= 10000.0) {
            return [ (float)$params['le']['mk'], (float)$params['le']['fi'], (string)$params['le']['tipo'] ];
        }
        return [ (float)$params['gt']['mk'], (float)$params['gt']['fi'], (string)$params['gt']['tipo'] ];
    }

    private function lerPlanilhaBoartXlsx(string $tmp, string $tipo): array
    {
        // Reutilize aqui o mesmo leitor XLSX que você usa no Getman.
        // Abaixo mapeamos (por tipo) a LINHA de cabeçalho e as COLUNAS de referência/descrição/valor.
        $tipoLow = strtolower($tipo);
        if ($tipoLow === 'ferramentais') {
            // Header na 2ª linha. PN é ref; Item Description como fallback de ref e também desc.
            return $this->xlsxExtract($tmp, /*headerRow*/2, [
                'ref_cols'  => ['PN', 'Item Description'],
                'valor_col' => 'PREÇO UNITÁRIO',
                'desc_cols' => ['Item Description'],
            ]);
        } elseif ($tipoLow === 'diamantados' || $tipoLow === 'diamantados (coroas)') {
            // Header na 6ª linha (linhas 1-5 contêm logo/título). Etiquetas de fila é ref.
            return $this->xlsxExtract($tmp, /*headerRow*/6, [
                'ref_cols'  => ['Etiquetas de fila', 'Item description'],
                'valor_col' => 'Preço Unitário',
                'desc_cols' => ['Item description'],
            ]);
        } elseif ($tipoLow === 'rock tools') {
            // Header na 3ª linha. Item Number é ref; Product description como fallback e desc.
            return $this->xlsxExtract($tmp, /*headerRow*/3, [
                'ref_cols'  => ['Item Number', 'Product description'],
                'valor_col' => 'Valence Price List',
                'desc_cols' => ['Product description'],
            ]);
        } else { // Spare Parts (header na 1ª linha)
            // PN é ref; Item description como fallback e desc.
            return $this->xlsxExtract($tmp, /*headerRow*/1, [
                'ref_cols'  => ['PN', 'Item description'],
                'valor_col' => 'Custo Boart USD Unitário',
                'desc_cols' => ['Item description'],
            ]);
        }
    }

    /**
     * Extrai do XLSX: retorna mapa [ref => ['PartDescription'=>..., 'Valor'=>float]]
     * - headerRow é 1-based (1=primeira linha)
     * - ref_cols: array de colunas candidatas; usa a primeira que tiver valor na linha
     * - valor_col: coluna de preço
     * - desc_cols: (opcional) para descrição
     *
     * Esta função deve reutilizar o mesmo motor de leitura já usado na Getman (ZipArchive+XML).
     * Se você já tem algo como $this->xlsxReadSheet() / $this->getSharedStrings(), use aqui.
     */
   private function xlsxExtract(string $tmp, int $headerRow, array $map): array
	{
		// $rows is keyed by 1-based actual row number (XML r attribute).
		$rows = $this->xlsxReadFirstSheet($tmp);

		if (!isset($rows[$headerRow])) {
			throw new \RuntimeException('Cabeçalho não encontrado (linha ' . $headerRow . ').');
		}

		// Cabeçalho normalizado (trim)
		$header = array_map(function($v) {
			return trim((string)$v);
		}, $rows[$headerRow]);

		// Localiza índice de coluna por nome (case-insensitive; ignora espaços)
		$findIndex = function($name) use ($header) {
			$needle = preg_replace('/\s+/', '', mb_strtolower($name));
			foreach ($header as $i => $h) {
				$hay = preg_replace('/\s+/', '', mb_strtolower($h));
				if ($hay === $needle) {
					return $i;
				}
			}
			return null;
		};

		// Índices das colunas principais
		$idxValor = $findIndex($map['valor_col']);
		if ($idxValor === null) {
			throw new \RuntimeException('Coluna de preço não localizada: ' . $map['valor_col']);
		}

		$idxDesc = null;
		if (!empty($map['desc_cols']) && is_array($map['desc_cols'])) {
			foreach ($map['desc_cols'] as $dc) {
				$i = $findIndex($dc);
				if ($i !== null) { $idxDesc = $i; break; }
			}
		}

		$idxRefs = array();
		if (!empty($map['ref_cols']) && is_array($map['ref_cols'])) {
			foreach ($map['ref_cols'] as $rc) {
				$i = $findIndex($rc);
				if ($i !== null) { $idxRefs[] = $i; }
			}
		}
		if (!$idxRefs) {
			throw new \RuntimeException(
				'Nenhuma coluna de referência localizada (' . implode(', ', $map['ref_cols'] ?? []) . ')'
			);
		}

		// Itera linhas de dados em ordem (salta cabeçalho e tudo acima)
		$out = array();
		$rowNums = array_keys($rows);
		sort($rowNums);

		foreach ($rowNums as $rowNum) {
			if ($rowNum <= $headerRow) continue;
			$line = $rows[$rowNum];
			if (!is_array($line)) continue;

			// Primeira referência não vazia dentre as colunas candidatas
			$ref = '';
			foreach ($idxRefs as $ix) {
				$v = isset($line[$ix]) ? trim((string)$line[$ix]) : '';
				if ($v !== '') { $ref = $v; break; }
			}
			if ($ref === '') continue;

			// Valor
			$rawValor = isset($line[$idxValor]) ? (string)$line[$idxValor] : '';
			$valor = $this->toNumber($rawValor, 0.0);

			// Descrição (opcional)
			$desc = '';
			if ($idxDesc !== null && isset($line[$idxDesc])) {
				$desc = (string)$line[$idxDesc];
			}

			$out[$ref] = array(
				'PartDescription' => $desc,
				'Valor'           => $valor
			);
		}

		return $out;
	}

    /* ========================= Reusos/Utilidades (mesmas da Getman) ========================= */

   // private function toNumber(string $s, float $def=0.0): float { /* ... reuso do Getman ... */ }
	private function toNumber(string $s, float $def=0.0): float {
        $num = preg_replace('/[^\d,.\-]/','', $s);
        $num = str_replace(',', '.', $num);
        if ($num === '' || $num === '.' || $num === '-' || $num === '+') return $def;
        return (float)$num;
    }
	
	
	
    private function indexBy(array $rows, string $key): array
    {
        $m = [];
        foreach ($rows as $r) if (isset($r[$key])) $m[(string)$r[$key]] = $r;
        return $m;
    }

    private function normalizeRefStrict(string $s): string
    {
        $u = strtoupper(trim($s));
        return preg_replace('/[^A-Z0-9]/', '', $u) ?? '';
    }

    private function calcularPrecosUSD(float $valorUS, float $cot, float $fi, float $mk): array
    {
        // Mesma política da Getman: aplica FI e Markup e converte. Ajuste se seu fluxo faz conversão antes/depois.
        // Exemplo: custo_brl = valorUS * cot * (1 + fi); sugerido = custo_brl * (1 + mk); etc.
        $custo = $valorUS * $cot * (1.0 + $fi);
        $ps = $custo * (1.0 + $mk);
        // Preços derivados (seu padrão)
        return [
            'PS' => $ps,
            'PP' => $ps,      // se PP = PS no seu padrão
            'GA' => $custo,   // exemplo: garantia/reposição conforme sua regra da Getman
            'RP' => $custo
        ];
    }

    private function moneyRound(float $v, int $scale = 0): float
    {
        return round($v, $scale, PHP_ROUND_HALF_UP);
    }

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
        foreach ($rows as &$r) {
            foreach (['PrecoPublico','PrecoSugerido','PrecoGarantia','PrecoReposicao'] as $k) {
                if (isset($r[$k]) && $r[$k] !== null) $r[$k] = (float)$r[$k];
            }
        }
        return $rows;
    }

    /**
     * Lê a primeira aba do XLSX via ZipArchive+SimpleXML.
     * Retorna array de linhas; cada linha é um array indexado por coluna (1-based).
     */
    private function xlsxReadFirstSheet(string $tmp): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new \RuntimeException('Não foi possível abrir o arquivo XLSX.');
        }

        $sheetPath = $this->xlsxFirstSheetPath($zip);
        $sheet = $this->loadXmlFromZip($zip, $sheetPath);
        if (!$sheet) {
            $zip->close();
            throw new \RuntimeException('Não foi possível ler a planilha interna do XLSX.');
        }

        $sst = $this->xlsxSharedStrings($zip);
        $zip->close();

        // Rows are keyed by their actual 1-based row number from <row r="N">.
        // This means blank rows before the header do NOT shift indices.
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowNum = (int)($row['r'] ?? 0);
            $line = [];
            foreach ($row->c as $c) {
                $r  = (string)$c['r'];
                $ci = $this->colToIdx($r);
                $t  = (string)$c['t'];
                $v  = '';
                if (isset($c->v))         $v = (string)$c->v;
                elseif (isset($c->is->t)) $v = (string)$c->is->t;

                if ($t === 's') { $val = $sst[(int)$v] ?? ''; }
                else            { $val = $v; }

                if (is_numeric($val)) $val = $val + 0;
                else                  $val = trim((string)$val);

                $line[$ci] = $val;
            }
            if (!empty($line) && $rowNum > 0) $rows[$rowNum] = $line;
        }
        return $rows;
    }

    /* ------------ XLSX helpers (same as Getman) ------------- */

    private function colToIdx($cellRef): int
    {
        if (!$cellRef) return 0;
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n;
    }

    private function loadXmlFromZip(\ZipArchive $zip, string $path)
    {
        $idx = $zip->locateName($path, 0);
        if ($idx === false) return null;
        $data = $zip->getFromIndex($idx);
        if ($data === false) return null;
        return @simplexml_load_string($data);
    }

    private function xlsxFirstSheetPath(\ZipArchive $zip): string
    {
        $wb = $this->loadXmlFromZip($zip, 'xl/workbook.xml');
        if (!$wb) return 'xl/worksheets/sheet1.xml';
        $rels = $this->loadXmlFromZip($zip, 'xl/_rels/workbook.xml.rels');
        if (!$rels) return 'xl/worksheets/sheet1.xml';

        $ns    = $wb->getNamespaces(true);
        $sheet = $wb->sheets->sheet[0] ?? null;
        if (!$sheet) return 'xl/worksheets/sheet1.xml';
        $rid = (string)$sheet->attributes($ns['r'])['id'];

        foreach ($rels->Relationship as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $target = (string)$rel['Target'];
                if (strpos($target, 'worksheets/') === false) $target = 'xl/' . ltrim($target, '/');
                else                                           $target = 'xl/' . $target;
                return $target;
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private function xlsxSharedStrings(\ZipArchive $zip): array
    {
        $sst = $this->loadXmlFromZip($zip, 'xl/sharedStrings.xml');
        $arr = [];
        if (!$sst) return $arr;
        foreach ($sst->si as $si) {
            $parts = [];
            if (isset($si->t)) $parts[] = (string)$si->t;
            if (isset($si->r)) {
                foreach ($si->r as $r) { if (isset($r->t)) $parts[] = (string)$r->t; }
            }
            $arr[] = trim(implode('', $parts));
        }
        return $arr;
    }

    /* ------------ exportCsv / atualizarValores ------------- */

    private function exportCsv(): void
    {
        $dados = $_SESSION['boart_result'] ?? [];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="boart_result.csv"');
        echo "\xEF\xBB\xBF"; // BOM para Excel

        if (empty($dados)) { echo "Sem dados.\n"; return; }

        $headers = array_keys($dados[0]);
        $f = fopen('php://output', 'w');
        fputcsv($f, $headers, ';');

        static $rawCols = ['ProdutoCodigo','ProdutoReferencia','NCMIdentificador','NCMCodigo'];
        foreach ($dados as $r) {
            $line = [];
            foreach ($headers as $h) {
                $val = $r[$h] ?? '';
                if (in_array($h, $rawCols, true)) $line[] = (string)$val;
                elseif (is_numeric($val))          $line[] = number_format((float)$val, 2, ',', '.');
                else                               $line[] = (string)$val;
            }
            fputcsv($f, $line, ';');
        }
        fclose($f);
    }

    private function atualizarValores(string $dealerDb): string
    {
        $resultado = $_SESSION['boart_result'] ?? [];
        if (empty($resultado)) return 'Sem dados para atualizar.';

        $pdo = Connection::get();
        $hasExec = 0;
        try {
            $check = $pdo->query("SELECT HAS_PERMS_BY_NAME('{$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS','OBJECT','EXECUTE') AS HasExec");
            $hasExec = (int)$check->fetchColumn();
        } catch (\Throwable $e) { $hasExec = 1; }

        if ($hasExec !== 1) {
            return "Sem permissão para executar {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS.";
        }

        $opGuid = $this->uuidv4();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("
                INSERT INTO [Portal_Integra].dbo.Atualizacao_Precos_DealerNet
                (IDUnico, dataHoraProcessamento, Marca_Codigo, Marca_Empresa,
                 Marca, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                 NCMCodigo, NCMIdentificador, [ValorPlanilha(US$)], Markup,
                 FatorImportacao, CotacaoDolar, Tipo,
                 PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
                VALUES
                (:id,:dh,:marcaCod,:marcaEmp,:marca,:emp,:cod,:ref,:desc,:ncmCod,:ncmId,:pl,:mk,:fi,:cot,:tipo,:pp,:ps,:ga,:rp)
            ");
            $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

            foreach ($resultado as $r) {
                $ins->execute([
                    ':id'       => $opGuid,
                    ':dh'       => $now,
                    ':marcaCod' => self::MARCA_ID,
                    ':marcaEmp' => (string)($r['Empresa'] ?? ''),
                    ':marca'    => self::MARCA_NOME,
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
            return 'Falha ao registrar auditoria: ' . $e->getMessage();
        }

        $ok = 0; $fail = 0; $errs = [];
        $hoje = (new \DateTime('today', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $execSql = "
            EXEC {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS
                @Marca_Codigo   = :marca,
                @Produto_Codigo = :prod,
                @PrecoPublico   = :pp,
                @PrecoSugerido  = :ps,
                @PrecoGarantia  = :ga,
                @PrecoReposicao = :rp,
                @DataVigencia   = :vig,
                @DataValidade   = :val,
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
                    ':val'    => null,
                    ':empCod' => null,
                    ':usr'    => 813,
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $errs[] = "Produto {$r['ProdutoCodigo']}: " . $e->getMessage();
            }
        }

        if ($fail > 0) {
            return "Operação registrada (ID={$opGuid}). OK: {$ok}. Falhas: {$fail}.\n" . implode("\n", $errs);
        }
        return "Operação concluída. ID={$opGuid}. Itens atualizados: {$ok}.";
    }

    /* ──────────────────────────────────────────────────────────────────────
     * AJAX: Atualizar Valores em chunks (PortalModal)
     * GET params: action=update_chunk, offset, limit, audit_op
     * Retorna JSON: {ok, done, total, finished, audit_op, proc_ok, proc_fail, errors}
     * ────────────────────────────────────────────────────────────────────── */
    private function ajaxUpdateChunk(string $dealerDb): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $resultado = $_SESSION['boart_result'] ?? [];
        session_write_close();

        try {
            $total = count($resultado);
            if ($total === 0) {
                echo json_encode(['ok' => false, 'msg' => 'Sem dados de relação na sessão. Processe uma planilha primeiro.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $offset  = max(0, (int)($_GET['offset']   ?? 0));
            $limit   = min(200, max(1, (int)($_GET['limit'] ?? 50)));
            $auditOp = preg_replace('/[^0-9a-fA-F\-]/', '', (string)($_GET['audit_op'] ?? ''));

            $pdo  = Connection::get();
            $now  = (new \DateTime('now',   new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
            $hoje = (new \DateTime('today', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

            // Primeira chamada: verifica permissão e gera GUID de auditoria
            if ($offset === 0) {
                $hasExec = 0;
                try {
                    $st = $pdo->query("SELECT HAS_PERMS_BY_NAME('{$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS','OBJECT','EXECUTE') AS HasExec");
                    $hasExec = (int)$st->fetchColumn();
                } catch (\Throwable $e) { $hasExec = 1; }
                if ($hasExec !== 1) {
                    echo json_encode(['ok' => false, 'msg' => "Sem permissão para executar {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS."], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $auditOp = $this->uuidv4();
            }
            if ($auditOp === '') $auditOp = $this->uuidv4();

            $chunk = array_slice($resultado, $offset, $limit);
            if (empty($chunk)) {
                echo json_encode(['ok' => true, 'done' => $offset, 'total' => $total, 'finished' => true,
                    'audit_op' => $auditOp, 'proc_ok' => 0, 'proc_fail' => 0, 'errors' => []], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 1) Auditoria para este chunk
            $ins = $pdo->prepare("
                INSERT INTO [Portal_Integra].dbo.Atualizacao_Precos_DealerNet
                (IDUnico, dataHoraProcessamento, Marca_Codigo, Marca_Empresa,
                 Marca, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                 NCMCodigo, NCMIdentificador, [ValorPlanilha(US$)], Markup,
                 FatorImportacao, CotacaoDolar, Tipo,
                 PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $pdo->beginTransaction();
            try {
                foreach ($chunk as $r) {
                    $ins->execute([
                        $auditOp, $now,
                        self::MARCA_ID,
                        (string)($r['Empresa'] ?? ''),
                        self::MARCA_NOME,
                        (string)($r['Empresa'] ?? ''),
                        (int)($r['ProdutoCodigo'] ?? 0),
                        (string)($r['ProdutoReferencia'] ?? ''),
                        (string)($r['ProdutoDescricao'] ?? ''),
                        ($r['NCMCodigo'] ?? null) !== null ? (int)$r['NCMCodigo'] : null,
                        (string)($r['NCMIdentificador'] ?? ''),
                        (float)($r['ValorPlanilha(US$)'] ?? 0),
                        (float)($r['Markup'] ?? 0),
                        (float)($r['FatorImportacao'] ?? 0),
                        (float)($r['CotacaoDolar'] ?? 0),
                        (string)($r['Tipo'] ?? ''),
                        (float)($r['PrecoPublico'] ?? 0),
                        (float)($r['PrecoSugerido'] ?? 0),
                        (float)($r['PrecoGarantia'] ?? 0),
                        (float)($r['PrecoReposicao'] ?? 0),
                    ]);
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['ok' => false, 'msg' => 'Falha ao registrar auditoria: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 2) EXEC da stored procedure por produto do chunk
            $execSql = "
                EXEC {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS
                     @Marca_Codigo   = ?,
                     @Produto_Codigo = ?,
                     @PrecoPublico   = ?,
                     @PrecoSugerido  = ?,
                     @PrecoGarantia  = ?,
                     @PrecoReposicao = ?,
                     @DataVigencia   = ?,
                     @DataValidade   = ?,
                     @Empresa_Codigo = ?,
                     @Usuario_Codigo = ?
            ";
            $ok = 0; $fail = 0; $errs = [];
            foreach ($chunk as $r) {
                try {
                    $st = $pdo->prepare($execSql);
                    $st->execute([
                        self::MARCA_ID,
                        (int)$r['ProdutoCodigo'],
                        (float)$r['PrecoPublico'],
                        (float)$r['PrecoSugerido'],
                        (float)$r['PrecoGarantia'],
                        (float)$r['PrecoReposicao'],
                        $hoje, null, null, 813,
                    ]);
                    try { do { $st->fetchAll(); } while ($st->nextRowset()); } catch (\Throwable $ignored) {}
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    if (count($errs) < 5) $errs[] = "Produto {$r['ProdutoCodigo']}: " . $e->getMessage();
                }
            }

            $newDone  = $offset + count($chunk);
            $finished = $newDone >= $total;

            echo json_encode([
                'ok'        => true,
                'done'      => $newDone,
                'total'     => $total,
                'finished'  => $finished,
                'audit_op'  => $auditOp,
                'proc_ok'   => $ok,
                'proc_fail' => $fail,
                'errors'    => $errs,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** GUID v4 simples */
    private function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
