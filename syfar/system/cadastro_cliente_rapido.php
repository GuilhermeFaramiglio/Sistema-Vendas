<?php
require_once 'utils/conectadb.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Função para limpar e obter valor do POST
function getPost($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Função para resposta JSON e encerramento
function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

try {
    // $link já está disponível via require
    if (!$link) {
        throw new Exception('Erro de conexão com o banco de dados');
    }

    $nome = getPost('nome');
    $cpf = getPost('cpf');
    $telefone = getPost('telefone');
    $nomeContato = getPost('contato') ?: $nome; 

    // Validações
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }

    if (empty($cpf)) {
        throw new Exception('CPF é obrigatório');
    }

    // Verificar se CPF já existe
    $stmt = mysqli_prepare($link, "SELECT CLI_ID FROM CLIENTE WHERE CLI_CPF = ?");
    mysqli_stmt_bind_param($stmt, 's', $cpf);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        throw new Exception('CPF já cadastrado');
    }
    mysqli_stmt_close($stmt);

    // Inserir cliente
    $stmt = mysqli_prepare($link, "INSERT INTO CLIENTE (CLI_NOMECOMPLETO, CLI_CPF) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'ss', $nome, $cpf);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Erro ao cadastrar cliente: ' . mysqli_stmt_error($stmt));
    }
    if (mysqli_stmt_affected_rows($stmt) <= 0) {
        throw new Exception('Erro ao cadastrar cliente: Nenhuma linha afetada.');
    }
    $clienteId = mysqli_insert_id($link);
    mysqli_stmt_close($stmt);

    // Inserir contato se fornecido
    if (!empty($telefone)) {
        $stmt = mysqli_prepare($link, "INSERT INTO CONTATO (CON_CLI_ID, CON_TELEFONE, CON_NOMECONTATO) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iss', $clienteId, $telefone, $nomeContato);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Erro ao cadastrar contato: ' . mysqli_stmt_error($stmt));
        }
        if (mysqli_stmt_affected_rows($stmt) <= 0) {
            throw new Exception('Erro ao cadastrar contato: Nenhuma linha afetada.');
        }
        mysqli_stmt_close($stmt);
    }

    // Formatar CPF para exibição
    $cpfFormatado = substr($cpf, 0, 3) . '.***.***-' . substr($cpf, -2);

    jsonResponse([
        'success' => true,
        'message' => 'Cliente cadastrado com sucesso',
        'cliente' => [
            'id' => $clienteId,
            'nome' => $nome,
            'cpf_formatado' => $cpfFormatado
        ]
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
