<?php
namespace Modules\Mining;

use PDO;
use SimpleXMLElement;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

class AnaliseDIService
{
    /** Texto padrão quando não encontra no DealerNet */
    public const NOT_FOUND_TEXT = 'Referência não localizada no DealerNet';

    /* ============================================================
     *  MAPEAMENTOS E CONFIGS
     * ============================================================ */

    /** Lista de marcas disponíveis (ajuste conforme sua realidade) */
    public static function marcas(): array
    {
        return [
            '152' => 'BOART LONGYEAR',
            '236' => 'Getman',
        ];
    }

    /** De/para da marca → DePara_Codigo (para persistência no DealerNet) */
    public static function deparaCodigoPorMarca(string $marcaCod): ?int
    {
        switch ($marcaCod) {
            case '236': return 42; // GETMAN
            case '152': return 31; // BOART LONGYEAR
            default:    return null;
        }
    }

    /**
     * Sanitiza o nome da base para cross‑database seguro.
     * Retorna o prefixo no padrão: [NomeBase].dbo.
     */
    public static function dbPrefix(string $dbName): string
    {
        // Permite letras, números, underline e ponto; remove outros caracteres
        $safe = preg_replace('/[^A-Za-z0-9_.]/', '', $dbName);
        if ($safe === '') {
            throw new \InvalidArgumentException('Nome de database inválido.');
        }
        return '[' . $safe . '].dbo.';
    }

    /* ============================================================
     *  AUXILIARES
     * ============================================================ */

    public static function normStr($v): string
    {
        $v = (string)$v;
        $v = str_replace("\r", '', $v);
        return trim($v);
    }

    /** Converte datas comuns para dd/mm/aaaa quando possível */
    public static function formatDateBR(?string $s): string
    {
        $s = trim((string)$s);
        if ($s === '') return '';
        // YYYY-MM-DD ou YYYY-MM-DDThh:mm:ss
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/',$s,$m)) {
            return sprintf('%02d/%02d/%04d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        // dd/mm/aaaa
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/',$s)) return $s;
        // yyyymmdd
        if (preg_match('/^(\d{8})$/',$s,$m)) {
            $y = substr($m[1],0,4); $mo = substr($m[1],4,2); $d = substr($m[1],6,2);
            return sprintf('%02d/%02d/%04d',(int)$d,(int)$mo,(int)$y);
        }
        return $s;
    }

    /** Localiza o nó infNFe em diferentes formatos de XML de NF-e */
    public static function localizarInfNFe(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        if (isset($xml->infNFe)) return $xml->infNFe;
        if (isset($xml->NFe) && isset($xml->NFe->infNFe)) return $xml->NFe->infNFe;
        $matches = $xml->xpath('//infNFe');
        return ($matches && count($matches) > 0) ? $matches[0] : null;
    }

    /** Compara NCM do XML com o NCM do DealerNet */
    public static function validarNcmTexto(string $ncmXML, string $ncmDealer): string
    {
        if ($ncmXML !== '' && $ncmDealer !== '') {
            return ($ncmXML === $ncmDealer) ? 'OK' : 'DIFERENTE';
        }
        return '';
    }

    /** Atalho para XPath → retorna texto do 1º nó que casar a expressão */
    public static function extractText(DOMXPath $xpath, DOMElement $context, string $expr): ?string
    {
        $nodes = $xpath->query($expr, $context);
        if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
            return trim($nodes->item(0)->textContent ?? '');
        }
        return null;
    }

    /** Extrai o PN (token imediatamente após "PN:" até o próximo espaço) */
    public static function extractPnFromDescription(string $desc): string
    {
        if (preg_match('/PN:\s*([^\s]+)/u', $desc, $m)) {
            return $m[1] ?? '';
        }
        return '';
    }

    /** Busca “data da DI” com várias alternativas de campos */
    public static function findDataDI(DOMXPath $xpath, DOMElement $merc, string $numeroDI): string
    {
        $candidates = [
            'ancestor::declaracaoImportacao/dataRegistroDI',
            'ancestor::declaracaoImportacao/dataDI',
            'ancestor::declaracaoImportacao/dataRegistroDeclaracao',
            'ancestor::declaracaoImportacao/dataRegistro',
            'ancestor::*/descendant::dataRegistroDI',
            'ancestor::*/descendant::dataDI',
            'ancestor::*/descendant::dataRegistroDeclaracao',
            'ancestor::*/descendant::dataRegistro',
        ];
        foreach ($candidates as $xp) {
            $v = self::extractText($xpath, $merc, $xp);
            if ($v) return self::formatDateBR($v);
        }
        if ($numeroDI !== '') {
            $globalCandidates = [
                "//declaracaoImportacao[numeroDI={$numeroDI}]/dataRegistroDI",
                "//declaracaoImportacao[numeroDI={$numeroDI}]/dataDI",
                "//declaracaoImportacao[numeroDI={$numeroDI}]/dataRegistroDeclaracao",
                "//declaracaoImportacao[numeroDI={$numeroDI}]/dataRegistro",
            ];
            foreach ($globalCandidates as $gx) {
                $n = $xpath->query($gx);
                if ($n && $n->length > 0) {
                    $v = trim($n->item(0)->textContent ?? '');
                    if ($v !== '') return self::formatDateBR($v);
                }
            }
        }
        return '';
    }

    /* ============================================================
     *  QUERIES NO “DEALERNET” (MESMO SERVIDOR VIA CROSS-DB)
     * ============================================================ */

    /**
     * Busca Dealer (PM.Produto_Codigo) e NCM_Identificador na base de DealerNet,
     * referenciando as tabelas com nome qualificado: [Base].dbo.Tabela
     */
    public static function buscarDealerENcm(PDO $pdo, string $dealerDb, string $marcaCod, string $referencia): array
    {
        $dealer = self::NOT_FOUND_TEXT; $ncmDealer = '';
        if ($marcaCod === '' || $referencia === '') return [$dealer, $ncmDealer];

        $pfx = self::dbPrefix($dealerDb); // "[GrupoValence_HML].dbo."

        $sql = "
            SELECT
                PM.ProdutoMarca_MarcaCod,
                PM.Produto_Codigo,
                PM.ProdutoMarca_Referencia,
                PM.ProdutoMarca_ReferenciaAlfanumerico,
                P.Produto_Descricao,
                N.NCM_Codigo,
                N.NCM_Identificador
            FROM {$pfx}ProdutoMarca PM
            JOIN {$pfx}Produto P ON PM.Produto_Codigo = P.Produto_Codigo
            LEFT JOIN {$pfx}NCM N ON N.NCM_Codigo = P.Produto_NCMCod
            WHERE PM.ProdutoMarca_MarcaCod = :marca
              AND PM.ProdutoMarca_Referencia = :ref";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':marca' => $marcaCod, ':ref' => $referencia]);
        $row = $stmt->fetch();
        if (!$row) return [$dealer, $ncmDealer];

        if (!empty($row['Produto_Codigo']))    $dealer    = self::normStr($row['Produto_Codigo']);
        if (!empty($row['NCM_Identificador'])) $ncmDealer = self::normStr($row['NCM_Identificador']);
        return [$dealer, $ncmDealer];
    }

    /* ============================================================
     *  PROCESSADORES (NF e DI)
     * ============================================================ */

    /**
     * Processa XML de NF (usando infNFe já localizado).
     * Retorna linhas no formato:
     * [DI, Data DI, Nº, Cod Produto DealerNet, Referência, Descrição, Observações, NCM XML, NCM DealerNet, Validação]
     * Obs.: Em NF o campo "Nº" vem de nItem e a lista de DI (se houver) vem de det->prod->DI
     */
    public static function processNF(SimpleXMLElement $infNFe, string $marcaCod, string $dealerDb, PDO $pdo): array
    {
        $rows = [];
        foreach ($infNFe->det as $det) {
            if (!isset($det->prod)) continue;

            // nItem
            $nItem = '';
            foreach ($det->attributes() as $attrName => $attrValue) {
                if ($attrName === 'nItem') { $nItem = (string)$attrValue; break; }
            }
            if ($nItem === '' && isset($det->prod->nItem)) $nItem = self::normStr($det->prod->nItem);

            // Produto, descrição e NCM do XML
            $cProd  = isset($det->prod->cProd) ? self::normStr($det->prod->cProd) : '';
            $xProd  = isset($det->prod->xProd) ? self::normStr($det->prod->xProd) : '';
            $ncmXML = isset($det->prod->NCM)   ? self::normStr((string)$det->prod->NCM) : '';

            // Dealer/NCM DealerNet
            [$dealer, $ncmDealer] = self::buscarDealerENcm($pdo, $dealerDb, $marcaCod, $cProd);
            $valid = self::validarNcmTexto($ncmXML, $ncmDealer);

            // DI vinculadas a esse item (pouco comum em NF)
            $dis = [];
            if (isset($det->prod->DI)) {
                if (is_array($det->prod->DI) || $det->prod->DI instanceof \Traversable) {
                    foreach ($det->prod->DI as $di) $dis[] = $di;
                } else { $dis[] = $det->prod->DI; }
            }
            if (count($dis) === 0) {
                $rows[] = ['', '', $nItem, $dealer, $cProd, $xProd, '', $ncmXML, $ncmDealer, $valid];
            } else {
                foreach ($dis as $di) {
                    $nDI = isset($di->nDI) ? self::normStr($di->nDI) : '';
                    $dDI = isset($di->dDI) ? self::formatDateBR(self::normStr($di->dDI)) : '';
                    $rows[] = [$nDI, $dDI, $nItem, $dealer, $cProd, $xProd, '', $ncmXML, $ncmDealer, $valid];
                }
            }
        }
        return $rows;
    }

    /**
     * Processa XML de DI (arquivo completo).
     * Retorna linhas no formato:
     * [DI, Data DI, Codigo Item DI, Nº, Cod Produto DealerNet, Referência, Descrição, Observações, NCM XML, NCM DealerNet, Validação]
     */
    public static function processDI(string $xmlTmpPath, string $marcaCod, string $dealerDb, PDO $pdo): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $loaded = $dom->load(
            $xmlTmpPath,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS | LIBXML_COMPACT | LIBXML_PARSEHUGE
        );
        if (!$loaded) throw new \RuntimeException('Falha ao carregar o XML DI.');

        $xpath = new DOMXPath($dom);
        $mercs = $xpath->query('//mercadoria');
        if (!$mercs || $mercs->length === 0) {
            throw new \RuntimeException('Nenhuma <mercadoria> encontrada.');
        }

        $rows = [];
        foreach ($mercs as $merc) {
            if (!($merc instanceof DOMElement)) continue;

            $desc = self::extractText($xpath, $merc, './descricaoMercadoria') ?? '';
            $seq  = self::extractText($xpath, $merc, './numeroSequencialItem');
            if (!$seq) continue;

            $numeroAdicao = self::extractText($xpath, $merc, 'ancestor::*[numeroAdicao]/numeroAdicao')
                           ?: self::extractText($xpath, $merc, 'ancestor::*/descendant::numeroAdicao');
            $numeroDI     = self::extractText($xpath, $merc, 'ancestor::*[numeroDI]/numeroDI')
                           ?: self::extractText($xpath, $merc, 'ancestor::*/descendant::numeroDI');
            if (!($numeroAdicao && $numeroDI)) continue;

            // Data da DI
            $dataDI = self::findDataDI($xpath, $merc, $numeroDI);

            // Codigo Item DI: DI-ADIÇÃO/SEQ (com padding)
            $codigoItemDI = sprintf('%s-%s/%s',
                $numeroDI,
                str_pad($numeroAdicao, 3, '0', STR_PAD_LEFT),
                str_pad($seq, 2, '0', STR_PAD_LEFT)
            );

            // NCM do XML DI (vários caminhos possíveis)
            $ncmXML = self::extractText($xpath, $merc, 'ancestor::adicao/dadosMercadoriaCodigoNcm')
                   ?: self::extractText($xpath, $merc, 'ancestor::*/dadosMercadoriaCodigoNcm')
                   ?: self::extractText($xpath, $merc, './dadosMercadoriaCodigoNcm')
                   ?: '';

            // Referência (PN) na descrição + consulta Dealer/NCM no DealerNet
            $referencia = self::extractPnFromDescription($desc);
            [$dealer, $ncmDealer] = self::buscarDealerENcm($pdo, $dealerDb, $marcaCod, $referencia);

            // Observações
            $obs = '';
            if ($referencia !== '' && $dealer === self::NOT_FOUND_TEXT) {
                $obs = 'Cadastre o item no DealerNet ou atualize o número da referência e clique em "Enviar correções".';
            } elseif ($referencia === '') {
                $obs = 'Referência não localizada no XML, favor informar a referência correta ao lado.';
            }

            $valid = self::validarNcmTexto($ncmXML, $ncmDealer);

            // Monta a linha
            $rows[] = [
                $numeroDI, $dataDI, $codigoItemDI, $seq, $dealer, $referencia, $desc, $obs, $ncmXML, $ncmDealer, $valid
            ];
        }

        // Dedup + ordenação por (CodigoItemDI, Referência, Descrição)
        $uniq = [];
        foreach ($rows as $r) { $uniq[implode("\x1F", $r)] = $r; }
        $rows = array_values($uniq);

        usort($rows, function(array $a, array $b) {
            $ka = [$a[2], $a[5], $a[6]]; // CodigoItemDI, Referência, Descrição
            $kb = [$b[2], $b[5], $b[6]];
            for ($i=0; $i<3; $i++) {
                if ($ka[$i] == $kb[$i]) continue;
                return ($ka[$i] < $kb[$i]) ? -1 : 1;
            }
            return 0;
        });

        libxml_clear_errors();
        return $rows;
    }

    /* ============================================================
     *  AGRUPAMENTOS E PENDÊNCIAS
     * ============================================================ */

    /**
     * Agrupa itens de DI por referência (PN) quando possível.
     * Retorna: [ref => ['dealer' => codDealer, 'codes' => [CodigoItemDI,...]]]
     */
    public static function computeRefGroupsDI(array $rows): array
    {
        $refGroups = [];
        foreach ($rows as $r) {
            $ref    = isset($r[5]) ? trim((string)$r[5]) : '';
            $code   = isset($r[2]) ? trim((string)$r[2]) : '';
            $dealer = isset($r[4]) ? trim((string)$r[4]) : '';
            if ($ref === '' || $code === '' || $dealer === self::NOT_FOUND_TEXT) continue;

            if (!isset($refGroups[$ref])) $refGroups[$ref] = ['dealer'=>$dealer, 'codes'=>[]];
            if (empty($refGroups[$ref]['dealer'])) $refGroups[$ref]['dealer'] = $dealer;

            $refGroups[$ref]['codes'][] = $code;
        }

        foreach ($refGroups as $ref => $data) {
            $codes = array_values(array_unique($data['codes']));
            sort($codes, SORT_STRING);
            $refGroups[$ref]['codes'] = $codes;
        }
        ksort($refGroups, SORT_STRING);
        return $refGroups;
    }

    /**
     * Retorna índices das linhas pendentes (referência vazia ou DealerNet não encontrado)
     */
    public static function pendentesDI(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $r) {
            $ref    = isset($r[5]) ? trim((string)$r[5]) : '';
            $dealer = isset($r[4]) ? trim((string)$r[4]) : '';
            if ($ref === '' || $dealer === self::NOT_FOUND_TEXT) $out[] = $i;
        }
        return $out;
    }
}