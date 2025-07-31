<?php
include 'utils/conectadb.php';

if (!function_exists('formatarMoeda')) {
    function formatarMoeda($valor) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}

// Total de Clientes
$sqlClientes = "SELECT COUNT(*) AS total FROM CLIENTE";
$resClientes = mysqli_query($link, $sqlClientes);
$totalClientes = ($row = mysqli_fetch_assoc($resClientes)) ? $row['total'] : 0;

// Total de Produtos
$sqlProdutos = "SELECT COUNT(*) AS total FROM PRODUTO";
$resProdutos = mysqli_query($link, $sqlProdutos);
$totalProdutos = ($row = mysqli_fetch_assoc($resProdutos)) ? $row['total'] : 0;

// Vendas do Mês
$mesAtual = date('Y-m');
$sqlVendasMes = "SELECT COALESCE(SUM(VEN_TOTAL),0) AS total FROM VENDA WHERE DATE_FORMAT(VEN_DATAVENDA, '%Y-%m') = '$mesAtual'";
$resVendasMes = mysqli_query($link, $sqlVendasMes);
$valorVendasMes = ($row = mysqli_fetch_assoc($resVendasMes)) ? $row['total'] : 0;

// Vendas Hoje
$sqlVendasHoje = "SELECT COUNT(*) AS total FROM VENDA WHERE DATE(VEN_DATAVENDA) = CURDATE()";
$resVendasHoje = mysqli_query($link, $sqlVendasHoje);
$vendasHoje = ($row = mysqli_fetch_assoc($resVendasHoje)) ? $row['total'] : 0;

// Valor gasto em compras no mês (baseado nos produtos cadastrados no mês atual)
$comprasMes = executarSelect("
    SELECT COALESCE(SUM(PRO_VALORCOMPRA * PRO_QUANTIDADE), 0) as total_compras
    FROM PRODUTO
    WHERE DATE_FORMAT(PRO_DATACADASTRO, '%Y-%m') = ?
", [$mesAtual]);

$valorComprasMes = $comprasMes[0]['total_compras'] ?? 0;

// Faturamento Esperado (estoque atual)
$sqlFaturamentoEsperado = "SELECT COALESCE(SUM(PRO_VALORVENDA * PRO_QUANTIDADE),0) AS total FROM PRODUTO";
$resFaturamentoEsperado = mysqli_query($link, $sqlFaturamentoEsperado);
$valorFaturamentoEsperado = ($row = mysqli_fetch_assoc($resFaturamentoEsperado)) ? $row['total'] : 0;

// Função para executar SELECTs parametrizados (para os gráficos)
function executarSelect($sql, $params = []) {
    global $link;
    $stmt = mysqli_prepare($link, $sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dados = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dados[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $dados;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
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
                <!-- <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-link nav-link" id="darkModeToggle" type="button">
                            <i class="bi bi-moon-fill"></i>
                        </button>
                    </li>
                </ul> -->
            </div>
        </div>
    </nav>

    <!-- Container Principal -->
    <div class="container mt-4 fade-in-up">

    <!-- Título da Página -->
<div class="row mb-4">
    <div class="col-12">
        <h1 class="text-custom-primary">
            <i class="bi bi-speedometer2"></i> Visão Geral do Sistema
        </h1>
        <p class="text-muted">Acompanhe as principais métricas do seu negócio</p>
    </div>
</div>

<!-- Cards de Métricas Principais -->
<div class="row mb-4">
    <!-- Total de Clientes -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-custom card-clientes h-100">
            <div class="card-body text-center">
                <i class="bi bi-people display-4 mb-3"></i>
                <h2><?php echo number_format($totalClientes); ?></h2>
                <h5 class="card-title">Clientes</h5>
                <small>Total cadastrados</small>
            </div>
        </div>
    </div>
    
    <!-- Total de Produtos -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-custom card-produtos h-100">
            <div class="card-body text-center">
                <i class="bi bi-box-seam display-4 mb-3"></i>
                <h2><?php echo number_format($totalProdutos); ?></h2>
                <h5 class="card-title">Produtos</h5>
                <small>Total cadastrados</small>
            </div>
        </div>
    </div>
    
    <!-- Vendas do Mês -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-custom card-vendas h-100">
            <div class="card-body text-center">
                <i class="bi bi-graph-up display-4 mb-3"></i>
                <h2><?php echo formatarMoeda($valorVendasMes); ?></h2>
                <h5 class="card-title">Vendas do Mês</h5>
                <small>Faturamento mensal</small>
            </div>
        </div>
    </div>
    
    <!-- Vendas Hoje -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-custom card-estoque h-100">
            <div class="card-body text-center">
                <i class="bi bi-calendar-day display-4 mb-3"></i>
                <h2><?php echo number_format($vendasHoje); ?></h2>
                <h5 class="card-title">Vendas Hoje</h5>
                <small>Vendas realizadas</small>
            </div>
        </div>
    </div>
</div>

<!-- Cards de Informações Financeiras -->
<div class="row mb-4">
    <!-- Compras do Mês -->
    <div class="col-lg-6 col-md-12 mb-3">
        <div class="card content-card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-cart-plus"></i> Compras do Mês
                </h5>
            </div>
            <div class="card-body text-center">
                <h2 class="text-custom-secondary"><?php echo formatarMoeda($valorComprasMes); ?></h2>
                <p class="text-muted">Valor investido em estoque</p>
            </div>
        </div>
    </div>
    
    <!-- Faturamento Esperado -->
    <div class="col-lg-6 col-md-12 mb-3">
        <div class="card content-card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-graph-up"></i> Faturamento Esperado
                </h5>
            </div>
            <div class="card-body text-center">
                <h2 class="text-custom-secondary"><?php echo formatarMoeda($valorFaturamentoEsperado); ?></h2>
                <p class="text-muted">Valor total baseado no estoque</p>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos de Análise -->
<div class="row mb-4">
    <!-- Gráfico de Vendas por Mês -->
    <div class="col-lg-6 col-md-12 mb-3">
        <div class="card content-card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-bar-chart"></i> Vendas por Mês
                </h5>
            </div>
            <div class="card-body">
                <canvas id="graficoVendas" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Gráfico de Faturamento Esperado -->
    <div class="col-lg-6 col-md-12 mb-3">
        <div class="card content-card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-graph-up"></i> Faturamento Esperado
                </h5>
            </div>
            <div class="card-body">
                <canvas id="graficoFaturamento" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dados para os gráficos (últimos 12 meses)
<?php
// Buscar dados de vendas dos últimos 12 meses
$dadosVendas = [];
$dadosFaturamento = [];
$meses = [];

for ($i = 11; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $mesNome = date('M/Y', strtotime("-$i months"));
    $meses[] = $mesNome;
    
    // Vendas do mês
    $vendasMesData = executarSelect("
        SELECT COALESCE(SUM(VEN_TOTAL), 0) as total 
        FROM VENDA 
        WHERE DATE_FORMAT(VEN_DATAVENDA, '%Y-%m') = ?
    ", [$mes]);
    $dadosVendas[] = floatval($vendasMesData[0]['total'] ?? 0);
    
    // Faturamento esperado baseado no estoque cadastrado naquele mês
    $faturamentoMesData = executarSelect("
        SELECT COALESCE(SUM(p.PRO_VALORVENDA * p.PRO_QUANTIDADE), 0) as total 
        FROM PRODUTO p 
        WHERE DATE_FORMAT(p.PRO_DATACADASTRO, '%Y-%m') = ?
    ", [$mes]);
    $dadosFaturamento[] = floatval($faturamentoMesData[0]['total'] ?? 0);
}
?>

const meses = <?php echo json_encode($meses); ?>;
const dadosVendas = <?php echo json_encode($dadosVendas); ?>;
const dadosFaturamento = <?php echo json_encode($dadosFaturamento); ?>;

// Gráfico de Vendas (Colunas)
const ctxVendas = document.getElementById('graficoVendas').getContext('2d');
new Chart(ctxVendas, {
    type: 'bar',
    data: {
        labels: meses,
        datasets: [{
            label: 'Vendas (R$)',
            data: dadosVendas,
            backgroundColor: '#01558c',
            borderColor: '#2494d1',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Vendas: R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    }
                }
            }
        }
    }
});

// Gráfico de Faturamento Esperado (Linha)
const ctxFaturamento = document.getElementById('graficoFaturamento').getContext('2d');
new Chart(ctxFaturamento, {
    type: 'line',
    data: {
        labels: meses,
        datasets: [{
            label: 'Faturamento Esperado (R$)',
            data: dadosFaturamento,
            borderColor: '#01558c',
            backgroundColor: 'rgba(0, 140, 255, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Faturamento: R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    }
                }
            }
        }
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
    <script src="./assets/js/script.js"></script>
</body>
</html>