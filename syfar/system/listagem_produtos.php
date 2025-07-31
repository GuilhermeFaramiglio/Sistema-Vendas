<?php
include 'utils/conectadb.php'; // Garante $link como conexão ativa com o banco

// Função para buscar tipos de produtos
function buscarTiposProdutos($link) {
    $tipos = [];
    $sql = "SELECT DISTINCT PRO_TIPO FROM PRODUTO ORDER BY PRO_TIPO";
    $result = $link->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tipos[] = $row['PRO_TIPO'];
        }
    }

    return $tipos;
}

// Função para buscar produtos com filtros
function buscarProdutos($link, $mes_filtro, $tipo_filtro, $descricao_filtro) {
    $where = [];
    $params = [];
    $types = '';

    if ($mes_filtro) {
        $where[] = "DATE_FORMAT(PRO_DATACADASTRO, '%Y-%m') = ?";
        $params[] = $mes_filtro;
        $types .= 's';
    }

    if ($tipo_filtro) {
        $where[] = "PRO_TIPO = ?";
        $params[] = $tipo_filtro;
        $types .= 's';
    }

    if ($descricao_filtro) {
        $where[] = "(PRO_DESCRICAO LIKE ? OR PRO_MARCA LIKE ? OR PRO_MODELO LIKE ?)";
        $params[] = "%$descricao_filtro%";
        $params[] = "%$descricao_filtro%";
        $params[] = "%$descricao_filtro%";
        $types .= 'sss';
    }

    $sql = "SELECT * FROM PRODUTO";
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY PRO_DATACADASTRO DESC";

    $stmt = $link->prepare($sql);

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $produtos = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $produtos[] = $row;
        }
    }

    return $produtos;
}

// Função para calcular total de produtos
function calcularTotalProdutos($produtos) {
    return count($produtos);
}

// Função para calcular valor total do estoque com base em PRO_VALORVENDA * PRO_QUANTIDADE
function calcularValorTotalEstoque($produtos) {
    $total = 0;
    foreach ($produtos as $produto) {
        $total += $produto['PRO_VALORVENDA'] * $produto['PRO_QUANTIDADE'];
    }
    return $total;
}

// Recebe filtros do GET
$mes_filtro = $_GET['mes'] ?? '';
$tipo_filtro = $_GET['tipo'] ?? '';
$descricao_filtro = $_GET['descricao'] ?? '';

// Executa as buscas e cálculos
$tipos = buscarTiposProdutos($link);
$produtos = buscarProdutos($link, $mes_filtro, $tipo_filtro, $descricao_filtro);
$total_produtos = calcularTotalProdutos($produtos);
$valor_total_estoque = calcularValorTotalEstoque($produtos);
?>



<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Listagem de produtos'; ?></title>
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
                <i class="bi bi-boxes"></i> Listagem de Produtos
            </h1>
        </div>
    </div>


    <!-- Cards de resumo -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">
                        <i class="bi bi-boxes"></i> Total de Produtos
                    </h6>
                    <h2 class="mb-0"><?php echo $total_produtos; ?></h2>
                    <small class="text-muted">produtos encontrados</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">
                        <i class="bi bi-currency-dollar"></i> Valor do Estoque
                    </h6>
                    <h2 class="mb-0">R$ <?php echo number_format($valor_total_estoque, 2, ',', '.'); ?></h2>
                    <small class="text-muted">valor investido</small>
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
                    <i class="bi bi-funnel"></i> Filtros
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="mes" class="form-label">Mês de Cadastro</label>
                                <input type="month" class="form-control" id="mes" name="mes" 
                                       value="<?php echo htmlspecialchars($mes_filtro); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="tipo" class="form-label">Tipo</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="">Todos os tipos</option>
                                    <?php foreach ($tipos as $tipo): ?>
                                        <option value="<?php echo htmlspecialchars($tipo); ?>" 
                                                <?php echo ($tipo_filtro == $tipo) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="descricao" class="form-label">Descrição/Marca/Modelo</label>
                                <input type="text" class="form-control" id="descricao" name="descricao" 
                                       value="<?php echo htmlspecialchars($descricao_filtro); ?>"
                                       placeholder="Buscar por descrição, marca ou modelo">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
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

    <!-- Lista de Produtos -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list"></i> Produtos Cadastrados
                </div>
                <div class="card-body">
                    <?php if (empty($produtos)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i>
                            Nenhum produto encontrado para os filtros selecionados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cód. Fornecedor</th>
                                        <th>Descrição</th>
                                        <th>Marca</th>
                                        <th>Modelo</th>
                                        <th>Tipo</th>
                                        <th>Qtd. Disponível</th>
                                        <th>Preço Unit.</th>
                                        <th>Data Cadastro</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos as $produto): ?>
                                        <tr>
                                            <td><?php echo $produto['PRO_ID']; ?></td>
                                            <td><?php echo htmlspecialchars($produto['PRO_CODIGOFORNECEDOR']); ?></td>
                                            <td><?php echo htmlspecialchars($produto['PRO_DESCRICAO']); ?></td>
                                            <td><?php echo htmlspecialchars($produto['PRO_MARCA']); ?></td>
                                            <td><?php echo htmlspecialchars($produto['PRO_MODELO']); ?></td>
                                            <td><?php echo htmlspecialchars($produto['PRO_TIPO']); ?></td>
                                            <td><?php echo $produto['PRO_QUANTIDADE']; ?></td>
                                            <td>R$ <?php echo number_format($produto['PRO_VALORVENDA'], 2, ',', '.'); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($produto['PRO_DATACADASTRO'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary btn-ver-detalhes" data-id="<?php echo $produto['PRO_ID']; ?>" data-bs-toggle="modal" data-bs-target="#modalDetalhesProduto">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                    <!-- opção de exclusão ficará oculta por enquanto -->
                                                <!-- <a href="excluir_produto.php?id=<?php echo $produto['PRO_ID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este produto?');">
                                                    <i class="bi bi-trash"></i>
                                                </a> -->
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
        <a href="cadastro_produto.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Cadastrar Novo Produto
        </a>
    </div>
</div>

</div>

   

<div class="modal fade" id="modalDetalhesProduto" tabindex="-1" aria-labelledby="modalDetalhesProdutoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5><i class="bi bi-info-circle"></i> Detalhes do Produto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" id="conteudoDetalhesProduto">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status"></div>
          <p>Carregando detalhes...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const botoesDetalhes = document.querySelectorAll('.btn-ver-detalhes');
    const modalBody = document.getElementById('conteudoDetalhesProduto');

    botoesDetalhes.forEach(botao => {
        botao.addEventListener('click', function () {
            const idProduto = this.getAttribute('data-id');
            modalBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p>Carregando detalhes...</p>
                </div>
            `;

            fetch(`detalhes_produto.php?id=${idProduto}`)
                .then(response => response.text())
                .then(html => {
                    modalBody.innerHTML = html;
                })
                .catch(error => {
                    modalBody.innerHTML = `<div class="alert alert-danger">Erro ao carregar os detalhes do produto.</div>`;
                    console.error(error);
                });
        });
    });
});
</script>

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
