<?php
include 'utils/conectadb.php';

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Dados do produto
        $codigo_fornecedor = trim($_POST['codigo_fornecedor']);
        $tipo = trim($_POST['tipo']);
        $descricao = trim($_POST['descricao']);
        $quantidade = intval($_POST['quantidade']);
        $unidade = trim($_POST['unidade']);
        $tamanho = trim($_POST['tamanho']);
        $modelo = trim($_POST['modelo']);
        $marca = trim($_POST['marca']);
        $valor_compra = floatval(str_replace(['R$ ', '.', ','], ['', '', '.'], $_POST['valor_compra']));
        $valor_venda = floatval(str_replace(['R$ ', '.', ','], ['', '', '.'], $_POST['valor_venda']));

        // Validações
        if (empty($descricao)) {
            throw new Exception('Descrição é obrigatória');
        }
        if ($valor_compra <= 0) {
            throw new Exception('Valor de compra deve ser maior que zero');
        }
        if ($valor_venda <= 0) {
            throw new Exception('Valor de venda deve ser maior que zero');
        }

        // Upload da imagem (se houver)
        $imagem_path = '';
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_extension = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_path)) {
                    $imagem_path = 'uploads/' . $new_filename;
                }
            } else {
                throw new Exception('Formato de imagem não permitido. Use JPG, JPEG, PNG ou GIF.');
            }
        }

        // Inserir produto
        $sql = "INSERT INTO PRODUTO (
            PRO_CODIGOFORNECEDOR, PRO_TIPO, PRO_DESCRICAO, PRO_QUANTIDADE, 
            PRO_UNIDADE, PRO_TAMANHO, PRO_MODELO, PRO_MARCA, PRO_IMAGEM, 
            PRO_VALORCOMPRA, PRO_VALORVENDA
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssssss',
            $codigo_fornecedor,
            $tipo,
            $descricao,
            $quantidade,
            $unidade,
            $tamanho,
            $modelo,
            $marca,
            $imagem_path,
            $valor_compra,
            $valor_venda
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Erro ao inserir produto: ' . mysqli_stmt_error($stmt));
        }

        $message = 'Produto cadastrado com sucesso!';
        $message_type = 'success';
        $_POST = array();

    } catch (Exception $e) {
        $message = 'Erro ao cadastrar produto: ' . $e->getMessage();
        $message_type = 'danger';
    }
}
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
            <i class="bi bi-box-seam"></i> Cadastro de Produto
        </h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-box-fill"></i> Dados do Produto
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Dados Básicos -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="codigo_fornecedor" class="form-label">Código do Fornecedor</label>
                            <input type="text" class="form-control" id="codigo_fornecedor" name="codigo_fornecedor" 
                                   value="<?php echo isset($_POST['codigo_fornecedor']) ? htmlspecialchars($_POST['codigo_fornecedor']) : ''; ?>" 
                                   maxlength="50" placeholder="Código interno do fornecedor">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label required">Tipo</label>
                            <select class="form-control" id="tipo" name="tipo" required>
                                <option value="">Selecione o tipo</option>
                                <option value="Semijoia" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Semijoia') ? 'selected' : ''; ?>>Semijoia</option>
                                <option value="Cosmético" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Cosmético') ? 'selected' : ''; ?>>Cosmético</option>
                                <option value="Roupa" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Roupa') ? 'selected' : ''; ?>>Roupa</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="descricao" class="form-label required">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3" 
                                      required maxlength="200" placeholder="Descrição detalhada do produto"><?php echo isset($_POST['descricao']) ? htmlspecialchars($_POST['descricao']) : ''; ?></textarea>
                        </div>
                    </div>

                    <!-- Estoque e Unidade -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="quantidade" class="form-label required">Quantidade</label>
                            <input type="number" class="form-control" id="quantidade" name="quantidade" 
                                   value="<?php echo isset($_POST['quantidade']) ? $_POST['quantidade'] : '0'; ?>" 
                                   required min="0" step="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="unidade" class="form-label">Unidade</label>
                            <select class="form-control" id="unidade" name="unidade">
                                <option value="">Selecione</option>
                                <option value="Peça" <?php echo (isset($_POST['unidade']) && $_POST['unidade'] == 'Peça') ? 'selected' : ''; ?>>Peça</option>
                                <option value="Unitário" <?php echo (isset($_POST['unidade']) && $_POST['unidade'] == 'Unitário') ? 'selected' : ''; ?>>Unitário</option>
                                <option value="Conjunto" <?php echo (isset($_POST['unidade']) && $_POST['unidade'] == 'Conjunto') ? 'selected' : ''; ?>>Conjunto</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tamanho" class="form-label">Tamanho</label>
                            <select class="form-control" id="tamanho" name="tamanho">
                                <option value="">Selecione</option>
                                <option value="P" <?php echo (isset($_POST['tamanho']) && $_POST['tamanho'] == 'P') ? 'selected' : ''; ?>>P</option>
                                <option value="M" <?php echo (isset($_POST['tamanho']) && $_POST['tamanho'] == 'M') ? 'selected' : ''; ?>>M</option>
                                <option value="G" <?php echo (isset($_POST['tamanho']) && $_POST['tamanho'] == 'G') ? 'selected' : ''; ?>>G</option>
                                <option value="GG" <?php echo (isset($_POST['tamanho']) && $_POST['tamanho'] == 'GG') ? 'selected' : ''; ?>>GG</option>
                                <option value="Único" <?php echo (isset($_POST['tamanho']) && $_POST['tamanho'] == 'Único') ? 'selected' : ''; ?>>Único</option>
                            </select>
                        </div>
                    </div>

                    <!-- Modelo e Marca -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modelo" class="form-label">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" 
                                   value="<?php echo isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : ''; ?>" 
                                   maxlength="50" placeholder="Modelo do produto">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="marca" class="form-label">Marca</label>
                            <input type="text" class="form-control" id="marca" name="marca" 
                                   value="<?php echo isset($_POST['marca']) ? htmlspecialchars($_POST['marca']) : ''; ?>" 
                                   maxlength="50" placeholder="Marca do produto">
                        </div>
                    </div>

                    <!-- Valores -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valor_compra" class="form-label required">Valor de Compra</label>
                            <input type="text" class="form-control" id="valor_compra" name="valor_compra" 
                                   value="<?php echo isset($_POST['valor_compra']) ? htmlspecialchars($_POST['valor_compra']) : ''; ?>" 
                                   required placeholder="R$ 0,00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="valor_venda" class="form-label required">Valor de Venda</label>
                            <input type="text" class="form-control" id="valor_venda" name="valor_venda" 
                                   value="<?php echo isset($_POST['valor_venda']) ? htmlspecialchars($_POST['valor_venda']) : ''; ?>" 
                                   required placeholder="R$ 0,00">
                        </div>
                    </div>

                    <!-- Imagem -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="imagem" class="form-label">Imagem do Produto</label>
                            <input type="file" class="form-control" id="imagem" name="imagem" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif">
                            <div class="form-text">Formatos aceitos: JPG, JPEG, PNG, GIF. Tamanho máximo: 2MB</div>
                        </div>
                    </div>

                    <!-- Preview da margem de lucro -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi bi-calculator"></i> Cálculo de Margem
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Valor de Compra:</strong>
                                            <span id="preview_compra">R$ 0,00</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Valor de Venda:</strong>
                                            <span id="preview_venda">R$ 0,00</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Margem de Lucro:</strong>
                                            <span id="preview_margem" class="text-success">R$ 0,00 (0%)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Cadastrar Produto
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-lg ms-2">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Função para formatar campo de moeda enquanto digita
function formatMoney(input) {
    let v = input.value.replace(/\D/g, '');
    v = (v / 100).toFixed(2) + '';
    v = v.replace('.', ',');
    v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    input.value = 'R$ ' + v;
}

// Função para formatar valor numérico para moeda BR
function formatCurrency(value) {
    return 'R$ ' + value.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
}

// Função para calcular margem de lucro
function calculateMargin() {
    const valorCompraInput = document.getElementById('valor_compra');
    const valorVendaInput = document.getElementById('valor_venda');

    // Extrai apenas números e vírgula, converte para float
    let compraStr = valorCompraInput.value.replace(/[^\d,]/g, '').replace(',', '.');
    let vendaStr = valorVendaInput.value.replace(/[^\d,]/g, '').replace(',', '.');

    const compra = parseFloat(compraStr) || 0;
    const venda = parseFloat(vendaStr) || 0;

    const margem = venda - compra;
    const percentual = compra > 0 ? ((margem / compra) * 100) : 0;

    document.getElementById('preview_compra').textContent = formatCurrency(compra);
    document.getElementById('preview_venda').textContent = formatCurrency(venda);

    const margemElement = document.getElementById('preview_margem');
    margemElement.textContent = formatCurrency(margem) + ' (' + percentual.toFixed(1) + '%)';

    // Colorir baseado na margem
    if (margem > 0) {
        margemElement.className = 'text-success';
    } else if (margem < 0) {
        margemElement.className = 'text-danger';
    } else {
        margemElement.className = 'text-muted';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const valorCompraInput = document.getElementById('valor_compra');
    const valorVendaInput = document.getElementById('valor_venda');

    // Aplica máscara e calcula margem ao digitar
    valorCompraInput.addEventListener('input', function() {
        formatMoney(this);
        calculateMargin();
    });

    valorVendaInput.addEventListener('input', function() {
        formatMoney(this);
        calculateMargin();
    });

    // Aplica máscara inicial se já houver valor
    if (valorCompraInput.value) formatMoney(valorCompraInput);
    if (valorVendaInput.value) formatMoney(valorVendaInput);

    // Calcular margem inicial
    calculateMargin();
});
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
