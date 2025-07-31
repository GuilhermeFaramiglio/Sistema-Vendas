<?php
require_once 'utils/conectadb.php';

// Parâmetros de filtro
$nome_filtro = isset($_GET['nome']) ? trim($_GET['nome']) : '';
$cpf_filtro = isset($_GET['cpf']) ? trim($_GET['cpf']) : '';

// Função para buscar clientes
function getClientes($link, $nome_filtro, $cpf_filtro) {
    $sql = "
        SELECT 
            c.CLI_ID,
            c.CLI_NOMECOMPLETO,
            c.CLI_CPF,
            COUNT(DISTINCT co.CON_ID) as total_contatos,
            COUNT(DISTINCT e.END_ID) as total_enderecos,
            COUNT(DISTINCT v.VEN_ID) as total_vendas,
            COALESCE(SUM(v.VEN_TOTAL), 0) as valor_total_compras
        FROM CLIENTE c
        LEFT JOIN CONTATO co ON c.CLI_ID = co.CON_CLI_ID
        LEFT JOIN ENDERECO e ON c.CLI_ID = e.END_CLI_ID
        LEFT JOIN VENDA v ON c.CLI_ID = v.VEN_CLI_ID
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    if (!empty($nome_filtro)) {
        $sql .= " AND c.CLI_NOMECOMPLETO LIKE ?";
        $params[] = "%$nome_filtro%";
        $types .= 's';
    }

    if (!empty($cpf_filtro)) {
        $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf_filtro);
        $sql .= " AND c.CLI_CPF LIKE ?";
        $params[] = "%$cpf_limpo%";
        $types .= 's';
    }

    $sql .= " GROUP BY c.CLI_ID ORDER BY c.CLI_NOMECOMPLETO";

    $stmt = $link->prepare($sql);

    if ($types && $stmt) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $clientes = [];
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }

    return $clientes;
}

// Função para buscar detalhes completos de um cliente
function getDetalhesCliente($link, $cliente_id) {
    // Dados básicos do cliente
    $stmt = $link->prepare("SELECT * FROM CLIENTE WHERE CLI_ID = ?");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente = $result->fetch_assoc();

    if (!$cliente) return null;

    // Contatos
    $stmt = $link->prepare("SELECT * FROM CONTATO WHERE CON_CLI_ID = ? ORDER BY CON_ID");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente['contatos'] = [];
    while ($row = $result->fetch_assoc()) {
        $cliente['contatos'][] = $row;
    }

    // Endereços
    $stmt = $link->prepare("SELECT * FROM ENDERECO WHERE END_CLI_ID = ? ORDER BY END_ID");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente['enderecos'] = [];
    while ($row = $result->fetch_assoc()) {
        $cliente['enderecos'][] = $row;
    }

    // Últimas vendas
    $stmt = $link->prepare("
        SELECT VEN_ID, VEN_DATAVENDA, VEN_TOTAL 
        FROM VENDA 
        WHERE VEN_CLI_ID = ? 
        ORDER BY VEN_DATAVENDA DESC 
        LIMIT 5
    ");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente['vendas'] = [];
    while ($row = $result->fetch_assoc()) {
        $cliente['vendas'][] = $row;
    }

    return $cliente;
}

$clientes = getClientes($link, $nome_filtro, $cpf_filtro);

// Calcular totais
$total_clientes = count($clientes);
$valor_total_geral = array_sum(array_column($clientes, 'valor_total_compras'));
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Listagem de clientes'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.html">
                <img src="../images/logo.png" alt="Logo" style="height:75px; margin-right:-200px;">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="caixa.php">
                            <i class="bi bi-cart-plus"></i> Caixa
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-plus-circle"></i> Cadastros
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="cadastro_cliente.php">
                                <i class="bi bi-person-plus"></i> Cadastrar Cliente
                            </a></li>
                            <li><a class="dropdown-item" href="cadastro_produto.php">
                                <i class="bi bi-box-seam"></i> Cadastrar Produto
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-list-ul"></i> Listagens
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="listagem_vendas.php">
                                <i class="bi bi-receipt"></i> Histórico de Vendas
                            </a></li>
                            <li><a class="dropdown-item" href="listagem_clientes.php">
                                <i class="bi bi-people"></i> Clientes
                            </a></li>
                            <li><a class="dropdown-item" href="listagem_produtos.php">
                                <i class="bi bi-boxes"></i> Produtos
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Container Principal -->
    <div class="container mt-4 fade-in-up">

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <i class="bi bi-people"></i> Listagem de Clientes
        </h1>
    </div>
</div>

<!-- Cards de resumo -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">
                        <i class="bi bi-people"></i> Total de Clientes
                    </h6>
                    <h2 class="mb-0"><?php echo $total_clientes; ?></h2>
                    <small class="text-muted">clientes cadastrados</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">
                        <i class="bi bi-currency-dollar"></i> Valor Total em Compras
                    </h6>
                    <h2 class="mb-0">R$ <?php echo number_format($valor_total_geral, 2, ',', '.'); ?></h2>
                    <small class="text-muted">valor gasto pelos clientes</small>
                </div>
            </div>
        </div>
        <!-- Adicione mais cards de resumo aqui se necessário -->
    </div>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-funnel"></i> Filtros
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?php echo htmlspecialchars($nome_filtro); ?>"
                                   placeholder="Nome do cliente">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" 
                                   value="<?php echo htmlspecialchars($cpf_filtro); ?>"
                                   placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Clientes -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i> Clientes Cadastrados
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($clientes)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Nenhum cliente encontrado para os filtros selecionados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome Completo</th>
                                    <th>CPF</th>
                                    <th>Contatos</th>
                                    <th>Endereços</th>
                                    <th>Vendas</th>
                                    <th>Total Compras</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $cliente['CLI_ID']; ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cliente['CLI_NOMECOMPLETO']); ?></strong>
                                        </td>
                                        <td>
                                            <code><?php echo substr($cliente['CLI_CPF'], 0, 3) . '.***.***-' . substr($cliente['CLI_CPF'], -2); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $cliente['total_contatos']; ?> contato(s)
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $cliente['total_enderecos']; ?> endereço(s)
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo $cliente['total_vendas']; ?> venda(s)
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">
                                                R$ <?php echo number_format($cliente['valor_total_compras'], 2, ',', '.'); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="verDetalhesCliente(<?php echo $cliente['CLI_ID']; ?>)">
                                                <i class="bi bi-eye"></i> Ver
                                            </button>
                                            <a href="cadastro_cliente.php?id=<?php echo $cliente['CLI_ID']; ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<br>

<!-- Botão para novo cliente -->
<div class="row mb-3">
    <div class="col-12">
        <a href="cadastro_cliente.php" class="btn btn-success">
            <i class="bi bi-person-plus"></i> Cadastrar Novo Cliente
        </a>
    </div>
</div>

<!-- Modal para detalhes do cliente -->
<div class="modal fade" id="modalDetalhesCliente" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person"></i> Detalhes do Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalhesClienteBody">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para edição do cliente
<div class="modal fade" id="modalEditarCliente" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Editar Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalEditarClienteBody">
                
            </div>
        </div>
    </div>
</div> -->

<script>
// Função para ver detalhes do cliente
function verDetalhesCliente(clienteId) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalhesCliente'));
    const modalBody = document.getElementById('modalDetalhesClienteBody');
    
    modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    modal.show();
    
    // Fazer requisição AJAX para buscar detalhes
    fetch('detalhes_cliente.php?id=' + clienteId)
        .then(response => response.text())
        .then(data => {
            modalBody.innerHTML = data;
        })
        .catch(error => {
            modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do cliente.</div>';
        });
}

// // Função para editar cliente
// function editarCliente(clienteId) {
//     const modal = new bootstrap.Modal(document.getElementById('modalEditarCliente'));
//     const modalBody = document.getElementById('modalEditarClienteBody');
    
//     modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
//     modal.show();
    
//     // Fazer requisição AJAX para buscar formulário de edição
//     fetch('editar_cliente.php?id=' + clienteId)
//         .then(response => response.text())
//         .then(data => {
//             modalBody.innerHTML = data;
//             // Aplicar máscaras após carregar o conteúdo
//             aplicarMascaras();
//         })
//         .catch(error => {
//             modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar formulário de edição.</div>';
//         });
// }

// Função para aplicar máscaras
function aplicarMascaras() {
    // Máscara para CPF
    const cpfInputs = document.querySelectorAll('.cpf-mask');
    cpfInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatCPF(this);
        });
    });
    
    // Máscara para telefone
    const telefoneInputs = document.querySelectorAll('.telefone-mask');
    telefoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatTelefone(this);
        });
    });
    
    // Máscara para CEP
    const cepInputs = document.querySelectorAll('.cep-mask');
    cepInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatCEP(this);
        });
    });
}

// Aplicar máscara no CPF
document.addEventListener('DOMContentLoaded', function() {
    const cpfInput = document.getElementById('cpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function() {
            formatCPF(this);
        });
    }
});
</script>

</div>

<!-- Footer -->
    <footer class="footer-custom">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Syfar Tecnologia. Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JavaScript Customizado -->
    <script src="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'assets/' : '../assets/'; ?>js/script.js"></script>
</body>
</html>

