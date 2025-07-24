<?php
include 'utils/conectadb.php'; // agora usando $link diretamente

// Parâmetros de filtro
$mes_filtro = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$cliente_filtro = isset($_GET['cliente']) ? $_GET['cliente'] : '';

// Função para buscar vendas
function getVendas($link, $mes_filtro, $cliente_filtro) {
    $vendas = [];

    $sql = "
        SELECT 
            v.VEN_ID,
            v.VEN_DATAVENDA,
            c.CLI_NOMECOMPLETO,
            c.CLI_CPF,
            v.VEN_FORMAPAGAMENTO,
            v.VEN_PARCELAS,
            v.VEN_SUBTOTAL,
            v.VEN_DESCONTO,
            v.VEN_TOTAL,
            COUNT(iv.ITV_ID) AS total_itens
        FROM VENDA v
        INNER JOIN CLIENTE c ON v.VEN_CLI_ID = c.CLI_ID
        LEFT JOIN ITENSVENDA iv ON v.VEN_ID = iv.ITV_VEN_ID
        WHERE 1=1
    ";

    $types = '';
    $params = [];

    if (!empty($mes_filtro)) {
        $sql .= " AND DATE_FORMAT(v.VEN_DATAVENDA, '%Y-%m') = ?";
        $types .= 's';
        $params[] = $mes_filtro;
    }

    if (!empty($cliente_filtro)) {
        $sql .= " AND (c.CLI_NOMECOMPLETO LIKE ? OR c.CLI_CPF LIKE ?)";
        $types .= 'ss';
        $params[] = "%$cliente_filtro%";
        $params[] = "%$cliente_filtro%";
    }

    $sql .= " GROUP BY v.VEN_ID ORDER BY v.VEN_DATAVENDA DESC";

    $stmt = $link->prepare($sql);

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $vendas[] = $row;
        }
    }

    return $vendas;
}

// Função para buscar itens de uma venda
function getItensVenda($link, $venda_id) {
    $itens = [];

    $stmt = $link->prepare("
        SELECT 
            iv.ITV_QUANTIDADE,
            iv.ITV_PRECOUNITARIO,
            iv.ITV_TOTALITEM,
            p.PRO_DESCRICAO,
            p.PRO_TIPO,
            p.PRO_TAMANHO
        FROM ITENSVENDA iv
        INNER JOIN PRODUTO p ON iv.ITV_PRO_ID = p.PRO_ID
        WHERE iv.ITV_VEN_ID = ?
        ORDER BY p.PRO_DESCRICAO
    ");

    $stmt->bind_param('i', $venda_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $itens[] = $row;
        }
    }

    return $itens;
}

// Função para buscar clientes para o filtro
function getClientesParaFiltro($link) {
    $clientes = [];

    $sql = "SELECT CLI_ID, CLI_NOMECOMPLETO FROM CLIENTE ORDER BY CLI_NOMECOMPLETO";
    $result = $link->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
    }

    return $clientes;
}

// Execução
$vendas = getVendas($link, $mes_filtro, $cliente_filtro);
$clientes = getClientesParaFiltro($link);

// Totais
$total_vendas = count($vendas);
$valor_total = array_sum(array_column($vendas, 'VEN_TOTAL'));
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Sistema de Vendas'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop"></i> Sistema de Vendas
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
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
                    <li class="nav-item">
                        <a class="nav-link" href="caixa.php">
                            <i class="bi bi-cash-register"></i> Caixa
                        </a>
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
            <i class="bi bi-receipt"></i> Histórico de Vendas
        </h1>
    </div>
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
                        <div class="col-md-4 mb-3">
                            <label for="mes" class="form-label">Mês/Ano</label>
                            <input type="month" class="form-control" id="mes" name="mes" 
                                   value="<?php echo htmlspecialchars($mes_filtro); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cliente" class="form-label">Cliente</label>
                            <input type="text" class="form-control" id="cliente" name="cliente" 
                                   value="<?php echo htmlspecialchars($cliente_filtro); ?>"
                                   placeholder="Nome ou CPF do cliente">
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

<!-- Resumo -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-graph-up"></i> Total de Vendas
                </h5>
                <h2><?php echo $total_vendas; ?></h2>
                <small>vendas no período</small>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-currency-dollar"></i> Valor Total
                </h5>
                <h2>R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></h2>
                <small>faturamento no período</small>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Vendas -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i> Vendas Realizadas
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($vendas)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Nenhuma venda encontrada para os filtros selecionados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Data/Hora</th>
                                    <th>Cliente</th>
                                    <th>Pagamento</th>
                                    <th>Itens</th>
                                    <th>Subtotal</th>
                                    <th>Desconto</th>
                                    <th>Total</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendas as $venda): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $venda['VEN_ID']; ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($venda['VEN_DATAVENDA'])); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($venda['CLI_NOMECOMPLETO']); ?></strong><br>
                                            <small class="text-muted">
                                                CPF: <?php echo substr($venda['CLI_CPF'], 0, 3) . '.***.***-' . substr($venda['CLI_CPF'], -2); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $venda['VEN_FORMAPAGAMENTO']; ?>
                                                <?php if ($venda['VEN_FORMAPAGAMENTO'] == 'Crédito' && $venda['VEN_PARCELAS'] > 1): ?>
                                                    <br><?php echo $venda['VEN_PARCELAS']; ?>x
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $venda['total_itens']; ?> item(s)
                                            </span>
                                        </td>
                                        <td>R$ <?php echo number_format($venda['VEN_SUBTOTAL'], 2, ',', '.'); ?></td>
                                        <td>
                                            <?php if ($venda['VEN_DESCONTO'] > 0): ?>
                                                <span class="text-danger">
                                                    -R$ <?php echo number_format($venda['VEN_DESCONTO'], 2, ',', '.'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-success">
                                                R$ <?php echo number_format($venda['VEN_TOTAL'], 2, ',', '.'); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="verDetalhes(<?php echo $venda['VEN_ID']; ?>)">
                                                <i class="bi bi-eye"></i> Ver
                                            </button>
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

<!-- Modal para detalhes da venda -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-receipt"></i> Detalhes da Venda
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalhesBody">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Função para ver detalhes da venda
function verDetalhes(vendaId) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
    const modalBody = document.getElementById('modalDetalhesBody');
    
    modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    modal.show();
    
    // Fazer requisição AJAX para buscar detalhes
    fetch('detalhes_venda.php?id=' + vendaId)
        .then(response => response.text())
        .then(data => {
            modalBody.innerHTML = data;
        })
        .catch(error => {
            modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes da venda.</div>';
        });
}

// Limpar filtros
function limparFiltros() {
    document.getElementById('mes').value = '';
    document.getElementById('cliente').value = '';
    document.querySelector('form').submit();
}
</script>

    <!-- Footer -->
    <footer class="footer-custom">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Vendas. Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JavaScript Customizado -->
    <script src="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'assets/' : '../assets/'; ?>js/script.js"></script>
</body>
</html>