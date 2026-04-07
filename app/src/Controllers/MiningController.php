<?php
namespace Controllers;

use Database\Connection;
use Modules\Mining\AnaliseDIService as S;

class MiningController
{
    public function analiseDI()
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('drilling.analise_di', 1);

        global $config;
        $dealerDb = $config['db']['dealernet_database'] ?? '';
        if ($dealerDb === '') {
            return render_page('mining/analise_di.php', [
                'marcas' => S::marcas(),
                'erro'   => 'Database do DealerNet não configurada (db.dealernet_database).',
                'avisos' => [], 'rows'=>[], 'tipoXML'=>null, 'canGroup'=>false, 'refGroups'=>[], 'persistLog'=>null
            ]);
        }

        $pdo = Connection::get(); // ✅ mesma conexão
        $erro = null; $avisos = []; $rows = []; $tipoXML = null; $canGroup=false; $refGroups=[]; $persistLog=null;
        $marcas = S::marcas();
        $marcaSelecionada = $_POST['marca_codigo'] ?? null;

        // Upload de XML
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml'])) {
            if (!$marcaSelecionada || !isset($marcas[$marcaSelecionada])) {
                $erro = 'Selecione uma marca válida antes de processar o XML.';
            } else {
                $file = $_FILES['xml'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $erro = 'Falha ao receber o arquivo. Código: ' . $file['error'];
                } else {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if ($ext !== 'xml') {
                        $erro = 'Por favor, anexe um arquivo com extensão .xml';
                    } else {
                        libxml_use_internal_errors(true);
                        $xmlNF  = simplexml_load_file($file['tmp_name']);
                        $infNFe = ($xmlNF !== false) ? S::localizarInfNFe($xmlNF) : null;
                        try {
                            if ($infNFe !== null) {
                                $tipoXML = 'NF';
                                $rows = S::processNF($infNFe, $marcaSelecionada, $dealerDb, $pdo);
                                unset($_SESSION['di_rows'], $_SESSION['marca_sel']);
                            } else {
                                $tipoXML = 'DI';
                                $rows = S::processDI($file['tmp_name'], $marcaSelecionada, $dealerDb, $pdo);
                                $_SESSION['di_rows']  = $rows;
                                $_SESSION['marca_sel'] = $marcaSelecionada;
                            }
                        } catch (\Throwable $e) {
                            $erro = 'Estrutura do XML da NF do DI não reconhecida, favor verificar se foi anexado o XML da Nota.';
                        }
                        if (empty($rows) && !$erro) $erro = 'Estrutura do XML da NF do DI não reconhecida, favor verificar se foi anexado o XML da Nota.';
                    }
                }
            }
        }

        // Correção de referências (somente DI) — requer escrita
        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && !isset($_FILES['xml'])
            && ($_POST['acao'] ?? '') === 'corrigir_refs') {

            \Security\Permission::require('drilling.analise_di', 2);

            $tipoXML = 'DI';
            $rows = $_SESSION['di_rows'] ?? [];
            $marcaSessao = $_SESSION['marca_sel'] ?? null;

            if (empty($rows) || !$marcaSessao || !isset($marcas[$marcaSessao])) {
                $erro = 'Não há dados de DI carregados ou a marca não está definida. Reenvie o XML DI e selecione a marca.';
            } else {
                $refs = (isset($_POST['refs']) && is_array($_POST['refs'])) ? $_POST['refs'] : [];
                foreach ($rows as $i => $r) {
                    if (isset($refs[(string)$i])) $rows[$i][5] = S::normStr($refs[(string)$i]); // Referência
                    $refAtual = $rows[$i][5];

                    [$dealer, $ncmDealer] = S::buscarDealerENcm($pdo, $dealerDb, $marcaSessao, $refAtual);
                    $rows[$i][4] = $dealer;
                    $rows[$i][9] = $ncmDealer;

                    if ($refAtual !== '' && $rows[$i][4] === S::NOT_FOUND_TEXT) {
                        $rows[$i][7] = 'Cadastre o item no DealerNet ou atualize o número da referência e clique em "Enviar correções".';
                    } elseif ($refAtual === '') { $rows[$i][7] = 'Referência não localizada no XML, favor informar a referência correta ao lado.'; }
                    else { $rows[$i][7] = ''; }

                    $ncmXML = (string)($rows[$i][8] ?? '');
                    $rows[$i][10] = S::validarNcmTexto($ncmXML, $ncmDealer);
                }
                $_SESSION['di_rows'] = $rows;
            }
        }

        // Persistência do resumo (somente DI) — requer escrita
        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && !isset($_FILES['xml'])
            && ($_POST['acao'] ?? '') === 'persistir_resumo') {

            \Security\Permission::require('drilling.analise_di', 2);

            $tipoXML = 'DI';
            $rows = $_SESSION['di_rows'] ?? [];
            $marcaSessao = $_SESSION['marca_sel'] ?? null;

            if (empty($rows) || !$marcaSessao || !isset($marcas[$marcaSessao])) {
                $erro = 'Não há dados de DI carregados ou a marca não está definida. Reenvie o XML DI e selecione a marca.';
            } else {
                $pending = S::pendentesDI($rows);
                if (count($pending) > 0) {
                    $erro = 'Ainda há pendências de referência/DealerNet. Corrija e clique novamente.';
                } else {
                    $refGroups = S::computeRefGroupsDI($rows);
                    $persistLog = ['inserted'=>0, 'updated'=>0, 'details'=>[]];

                    $deparaCod = S::deparaCodigoPorMarca($marcaSessao);
                    if ($deparaCod !== null) {
                        try {
                            $pdo->beginTransaction();
                            $pfx = S::dbPrefix($dealerDb); // "[GrupoValence_HML].dbo."

                            foreach ($refGroups as $ref => $data) {
                                $codDealer = $data['dealer'];
                                if ($codDealer === '' || $codDealer === S::NOT_FOUND_TEXT) continue;
                                $pipeCodes = implode("\n", $data['codes']);
                                $codDealerStr = (string)$codDealer;

                                // existe?
                                $sqlSel = "SELECT TOP 1 1
                                           FROM {$pfx}DeParaRelacionamento
                                           WHERE DePara_Codigo = :dep
                                             AND DeParaRelacionamento_Tabela = 'DIP'
                                             AND DeParaRelacionamento_CodigoDe = :cod";
                                $stmtSel = $pdo->prepare($sqlSel);
                                $stmtSel->execute([':dep'=>$deparaCod, ':cod'=>$codDealer]);
                                $exists = (bool)$stmtSel->fetch();

                                if ($exists) {
                                    $sqlUpd = "UPDATE {$pfx}DeParaRelacionamento
                                               SET DeParaRelacionamento_IdentificadorPara = :pipe,
                                                   DeParaRelacionamento_UsuCodCriacao = 1,
                                                   DeParaRelacionamento_CodigoDeString = :codStr
                                               WHERE DePara_Codigo = :dep
                                                 AND DeParaRelacionamento_Tabela = 'DIP'
                                                 AND DeParaRelacionamento_CodigoDe = :cod";
                                    $stmtUpd = $pdo->prepare($sqlUpd);
                                    $stmtUpd->execute([
                                        ':pipe'=>$pipeCodes, ':codStr'=>$codDealerStr, ':dep'=>$deparaCod, ':cod'=>$codDealer
                                    ]);
                                    $persistLog['updated']++;
                                    $persistLog['details'][] = "Atualizado: DePara={$deparaCod}, DealerNet={$codDealer}, Itens={$pipeCodes}";
                                } else {
                                    $sqlIns = "INSERT INTO {$pfx}DeParaRelacionamento
                                               (DePara_Codigo, DeParaRelacionamento_Tabela, DeParaRelacionamento_CodigoDe,
                                                DeParaRelacionamento_CodigoDeString, DeParaRelacionamento_IdentificadorPara,
                                                DeParaRelacionamento_UsuCodCriacao, DeParaRelacionamento_DataCriacao)
                                               VALUES
                                               (:dep, 'DIP', :cod, :codStr, :pipe, 1, GETDATE())";
                                    $stmtIns = $pdo->prepare($sqlIns);
                                    $stmtIns->execute([
                                        ':dep'=>$deparaCod, ':cod'=>$codDealer, ':codStr'=>$codDealerStr, ':pipe'=>$pipeCodes
                                    ]);
                                    $persistLog['inserted']++;
                                    $persistLog['details'][] = "Inserido: DePara={$deparaCod}, DealerNet={$codDealer}, Itens={$pipeCodes}";
                                }
                            }
                            $pdo->commit();
                        } catch (\Throwable $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $erro = 'Falha ao gravar resumo: ' . $e->getMessage();
                        }
                    } else {
                        $avisos[] = "Aviso: marca sem DePara_Codigo mapeado.";
                    }
                }
            }
        }

        // Carregamento de sessão / flags
        if ($tipoXML === 'DI' && empty($rows) && isset($_SESSION['di_rows'])) $rows = $_SESSION['di_rows'];
        if ($tipoXML === 'DI' && !empty($rows)) {
            $pending = S::pendentesDI($rows);
            $canGroup = (count($pending) === 0);
            if ($canGroup) $refGroups = S::computeRefGroupsDI($rows);
        }

        render_page('mining/analise_di.php', compact(
            'marcas','erro','avisos','rows','tipoXML','canGroup','refGroups','persistLog'
        ));
    }
}
?>