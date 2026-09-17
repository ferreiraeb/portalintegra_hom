<?php
require __DIR__ . '/../bootstrap.php';
use Database\ExternalConnections;

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = ExternalConnections::mssql('dealernet');

    // sem qualquer setAttribute aqui, só um SELECT simples:
    $stmt = $pdo->prepare("SELECT TOP 1 PM.Produto_Codigo FROM dbo.ProdutoMarca PM");
    $stmt->execute();
    $row = $stmt->fetch();

    echo "OK. Conectou e leu: " . json_encode($row) . PHP_EOL;
} catch (\Throwable $e) {
    echo "ERRO: " . $e->getMessage() . PHP_EOL;
}
?>