<?php
namespace Controllers;

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
            return $this->xlsxExtract($tmp, /*headerRow*/2, [
                'ref_cols'   => ['PN','Item Description'],       // usa o que existir
                'valor_col'  => 'PREÇO UNITÁRIO',
                'desc_cols'  => ['Item Description']             // opcional
            ]);
        } elseif ($tipoLow === 'diamantados' || $tipoLow === 'diamantados (coroas)') {
            return $this->xlsxExtract($tmp, /*headerRow*/3, [
                'ref_cols'   => ['Etiquetas de fila','Item Description'],
                'valor_col'  => 'PREÇO UNITÁRIO',
                'desc_cols'  => ['Item Description']
            ]);
        } elseif ($tipoLow === 'rock tools') {
            return $this->xlsxExtract($tmp, /*headerRow*/3, [
                'ref_cols'   => ['Item Number','Product Description'],
                'valor_col'  => 'Valence Price List',
                'desc_cols'  => ['Product Description']
            ]);
        } else { // Spare Parts (header na 1ª linha)
            return $this->xlsxExtract($tmp, /*headerRow*/1, [
                'ref_cols'   => ['PN','Item Description'],
                'valor_col'  => 'Custo Boart USD  UNITÁRIO',
                'desc_cols'  => ['Item Description']
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
		// Reutilize seu leitor real que retorna um array de linhas:
		// $rows[0] = primeira linha, $rows[1] = segunda, ...
		$rows = $this->xlsxReadFirstSheet($tmp);

		$hdrIdx = $headerRow - 1;
		if (!isset($rows[$hdrIdx]) || !is_array($rows[$hdrIdx])) {
			throw new \RuntimeException('Cabeçalho não encontrado (linha ' . $headerRow . ').');
		}

		// Cabeçalho normalizado (trim)
		$header = array_map(function($v) {
			return trim((string)$v);
		}, $rows[$hdrIdx]);

		// Função para localizar índice de coluna por nome (case-insensitive; ignora espaços)
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
				'Nenhuma coluna de referência localizada (' . implode(', ', $map['ref_cols']) . ')'
			);
		}

		// Monta [ref => ['PartDescription'=>..., 'Valor'=>float]]
		$out = array();
		for ($r = $hdrIdx + 1; $r < count($rows); $r++) {
			$line = $rows[$r];
			if (!is_array($line)) {
				continue;
			}

			// Pega a primeira referência não vazia dentre as colunas candidatas
			$ref = '';
			foreach ($idxRefs as $ix) {
				$v = isset($line[$ix]) ? trim((string)$line[$ix]) : '';
				if ($v !== '') { $ref = $v; break; }
			}
			if ($ref === '') {
				continue;
			}

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
	
	
	
    private function indexBy(array $rows, string $key): array     { /* ... reuso do Getman ... */ }
    private function normalizeRefStrict(string $s): string        { /* ... já existente (Getman) ... */ }

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

    private function carregarListaProdutos(string $dealerDb, int $marcaId): array { /* ... reuso do Getman ... */ }
    private function xlsxReadFirstSheet(string $tmp): array { /* ... reuso do Getman (ZipArchive+XML) ... */ }
    private function exportCsv(): void { /* ... reuso do Getman ... */ }
    private function atualizarValores(string $dealerDb): string { /* ... reuso do Getman (auditoria + EXEC proc) ... */ }
}
