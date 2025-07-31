<?php
require_once 'utils/conectadb.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    if (!isset($link) || !($link instanceof mysqli)) {
        throw new Exception('Conexão com o banco de dados não estabelecida corretamente.');
    }

    $descricao = trim($_POST['descricao'] ?? '');
    $valorVenda = trim($_POST['valor_venda'] ?? '');
    $quantidade = intval($_POST['quantidade'] ?? 0);

    if (empty($descricao)) {
        throw new Exception('Descrição é obrigatória');
    }

    if (empty($valorVenda)) {
        throw new Exception('Valor de venda é obrigatório');
    }

    if ($quantidade <= 0) {
        throw new Exception('Quantidade deve ser maior que zero');
    }

    $valorVenda = floatval(str_replace(['R$', '.', ','], ['', '', '.'], $valorVenda));

    if ($valorVenda <= 0) {
        throw new Exception('Valor de venda inválido');
    }

    $valorCompra = $valorVenda * 0.7;

    $stmt = $link->prepare("
        INSERT INTO PRODUTO (
            PRO_DESCRICAO,
            PRO_VALORVENDA,
            PRO_VALORCOMPRA,
            PRO_QUANTIDADE,
            PRO_DATACADASTRO
        ) VALUES (?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar a query: ' . $link->error);
    }

    $stmt->bind_param('sddi', $descricao, $valorVenda, $valorCompra, $quantidade);
    $stmt->execute();

    if ($stmt->affected_rows <= 0) {
        throw new Exception('Erro ao cadastrar o produto');
    }

    $produtoId = $stmt->insert_id;

    echo json_encode([
        'success' => true,
        'message' => 'Produto cadastrado com sucesso',
        'produto' => [
            'id' => $produtoId,
            'descricao' => $descricao,
            'valor_venda' => $valorVenda,
            'quantidade' => $quantidade
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>