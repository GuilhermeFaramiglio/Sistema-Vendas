<?php
include 'utils/conectadb.php'; // agora usando $link diretamente

// Parâmetros de filtro
$mes_filtro = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$cliente_filtro = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$tipo_produto_filtro = isset($_GET['tipo_produto']) ? strtoupper(trim($_GET['tipo_produto'])) : '';
$data_inicio_filtro = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim_filtro = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

// Função para buscar vendas
function getVendas($link, $mes_filtro, $cliente_filtro, $tipo_produto_filtro, $data_inicio_filtro, $data_fim_filtro) {
    $vendas = [];
    $sql = "
        SELECT 
            v.VEN_ID,
            v.VEN_DATAVENDA,
            c.CLI_NOMECOMPLETO,
            c.CLI_CPF,
            v.VEN_FORMAPAGAMENTO,
            v.VEN_PARCELAS
        FROM VENDA v
        INNER JOIN CLIENTE c ON v.VEN_CLI_ID = c.CLI_ID
        WHERE 1=1
    ";
    $types = '';
    $params = [];

    if (!empty($mes_filtro)) {
        $sql .= " AND DATE_FORMAT(v.VEN_DATAVENDA, '%Y-%m') = ?";
        $types .= 's';
        $params[] = $mes_filtro;
    }

    if (!empty($data_inicio_filtro)) {
        $sql .= " AND DATE(v.VEN_DATAVENDA) >= ?";
        $types .= 's';
        $params[] = $data_inicio_filtro;
    }
    if (!empty($data_fim_filtro)) {
        $sql .= " AND DATE(v.VEN_DATAVENDA) <= ?";
        $types .= 's';
        $params[] = $data_fim_filtro;
    }

    if (!empty($cliente_filtro)) {
        $sql .= " AND (c.CLI_NOMECOMPLETO LIKE ? OR c.CLI_CPF LIKE ?)";
        $types .= 'ss';
        $params[] = "%$cliente_filtro%";
        $params[] = "%$cliente_filtro%";
    }

    if (!empty($tipo_produto_filtro)) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM ITENSVENDA iv2
            INNER JOIN PRODUTO p2 ON iv2.ITV_PRO_ID = p2.PRO_ID
            WHERE iv2.ITV_VEN_ID = v.VEN_ID AND TRIM(UPPER(p2.PRO_TIPO)) = ?
        )";
        $types .= 's';
        $params[] = $tipo_produto_filtro;
    }

    $sql .= " ORDER BY v.VEN_DATAVENDA DESC";

    $stmt = $link->prepare($sql);

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Buscar itens filtrados por tipo
            $itens = [];
            $subtotal = 0;
            $total = 0;
            $desconto = 0;
            $total_itens = 0;

            $item_sql = "
                SELECT 
                    iv.ITV_QUANTIDADE,
                    iv.ITV_PRECOUNITARIO,
                    (iv.ITV_QUANTIDADE * iv.ITV_PRECOUNITARIO) AS ITV_TOTAL,
                    p.PRO_DESCRICAO,
                    p.PRO_TIPO,
                    p.PRO_TAMANHO
                FROM ITENSVENDA iv
                INNER JOIN PRODUTO p ON iv.ITV_PRO_ID = p.PRO_ID
                WHERE iv.ITV_VEN_ID = ?
            ";
            if (!empty($tipo_produto_filtro)) {
                $item_sql .= " AND TRIM(UPPER(p.PRO_TIPO)) = ?";
                $item_stmt = $link->prepare($item_sql);
                $item_stmt->bind_param('is', $row['VEN_ID'], $tipo_produto_filtro);
            } else {
                $item_stmt = $link->prepare($item_sql);
                $item_stmt->bind_param('i', $row['VEN_ID']);
            }

            if ($item_stmt->execute()) {
                $item_result = $item_stmt->get_result();
                while ($item = $item_result->fetch_assoc()) {
                    $itens[] = $item;
                    $subtotal += $item['ITV_TOTAL'];
                    $total_itens += $item['ITV_QUANTIDADE'];
                }
            }

            // Desconto e total: se quiser considerar desconto proporcional, precisa ajustar aqui.
            // Para simplicidade, vamos mostrar subtotal dos itens filtrados e total igual ao subtotal.
            $row['VEN_SUBTOTAL'] = $subtotal;
            $row['VEN_TOTAL'] = $subtotal;
            $row['VEN_DESCONTO'] = 0;
            $row['total_itens'] = $total_itens;
            $row['itens'] = $itens;

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
            (iv.ITV_QUANTIDADE * iv.ITV_PRECOUNITARIO) AS ITV_TOTAL,
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
    return $itens;
}

function getClientesParaFiltro($link) {
    $clientes = [];
    $result = $link->query("SELECT CLI_ID, CLI_NOMECOMPLETO, CLI_CPF FROM CLIENTE ORDER BY CLI_NOMECOMPLETO");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
    }
    return $clientes;
}

// Execução
$vendas = getVendas($link, $mes_filtro, $cliente_filtro, $tipo_produto_filtro, $data_inicio_filtro, $data_fim_filtro);
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
    <title><?php echo isset($page_title) ? $page_title : 'Histórico de vendas'; ?></title>
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
            <i class="bi bi-receipt"></i> Histórico de Vendas
        </h1>
    </div>
</div>

<!-- Cards de resumo -->
<div class="row mb-4">
    <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
        <div class="card text-center h-100">
            <div class="card-body">
                <h6 class="card-title text-muted mb-2">
                    <i class="bi bi-boxes"></i> Total de Vendas
                </h6>
                <h2 class="mb-0"><?php echo $total_vendas; ?></h2>
                <small class="text-muted">vendas realizadas</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
        <div class="card text-center h-100">
            <div class="card-body">
                <h6 class="card-title text-muted mb-2">
                    <i class="bi bi-currency-dollar"></i> Valor Total
                </h6>
                <h2 class="mb-0">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></h2>
                <small class="text-muted">faturamento no período</small>
            </div>
        </div>
    </div>
    <?php
    // Card de comissão para Semijoia (apenas se filtro ativo)
    if ($tipo_produto_filtro === 'SEMIJOIA') {
        $comissao = 0;
        if ($valor_total <= 1400) {
            $comissao = $valor_total * 0.30;
            $percentual = '30%';
        } elseif ($valor_total <= 1799) {
            $comissao = $valor_total * 0.35;
            $percentual = '35%';
        } else {
            $comissao = $valor_total * 0.40;
            $percentual = '40%';
        }
        ?>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card text-center h-100 border-warning">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">
                        <i class="bi bi-cash-coin"></i> Comissão Semijoia
                    </h6>
                    <h2 class="mb-0 text-warning">R$ <?php echo number_format($comissao, 2, ',', '.'); ?></h2>
                    <small class="text-muted">Comissão de <?php echo $percentual; ?> sobre R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></small>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
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
                        <div class="col-md-3 mb-3">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                                   value="<?php echo isset($_GET['data_inicio']) ? htmlspecialchars($_GET['data_inicio']) : ''; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" 
                                   value="<?php echo isset($_GET['data_fim']) ? htmlspecialchars($_GET['data_fim']) : ''; ?>">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="cliente" class="form-label">Cliente</label>
                            <input type="text" class="form-control" id="cliente" name="cliente" 
                                   value="<?php echo htmlspecialchars($cliente_filtro); ?>"
                                   placeholder="Nome ou CPF do cliente">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="tipo_produto" class="form-label">Tipo de Produto</label>
                            <select class="form-select" id="tipo_produto" name="tipo_produto">
                                <option value="">Todos</option>
                                <?php
                                // Buscar tipos de produto distintos (removendo espaços e agrupando corretamente)
                                $tipos = [];
                                $result = $link->query("SELECT TRIM(UPPER(PRO_TIPO)) AS PRO_TIPO FROM PRODUTO GROUP BY TRIM(UPPER(PRO_TIPO)) ORDER BY PRO_TIPO");
                                if ($result) {
                                    while ($row = $result->fetch_assoc()) {
                                        $tipos[] = $row['PRO_TIPO'];
                                    }
                                }
                                $tipo_produto_filtro = isset($_GET['tipo_produto']) ? strtoupper(trim($_GET['tipo_produto'])) : '';
                                foreach ($tipos as $tipo) {
                                    $selected = ($tipo_produto_filtro == $tipo) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($tipo) . "\" $selected>" . htmlspecialchars($tipo) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
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