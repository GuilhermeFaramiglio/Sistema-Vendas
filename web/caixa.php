<?php
include 'utils/conectadb.php';

// Obter conexão com o banco usando $link
// $link já está disponível após o include

// Buscar clientes para o select
function getClientes($link) {
    $clientes = [];
    $sql = "SELECT CLI_ID, CLI_NOMECOMPLETO, CLI_CPF FROM CLIENTE ORDER BY CLI_NOMECOMPLETO";
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $clientes[] = $row;
        }
        mysqli_free_result($result);
    }
    return $clientes;
}

// Buscar produtos para o select
function getProdutos($link) {
    $produtos = [];
    $sql = "SELECT PRO_ID, PRO_DESCRICAO, PRO_TIPO, PRO_TAMANHO, PRO_VALORVENDA, PRO_QUANTIDADE 
            FROM PRODUTO 
            WHERE PRO_QUANTIDADE > 0 
            ORDER BY PRO_DESCRICAO";
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $produtos[] = $row;
        }
        mysqli_free_result($result);
    }
    return $produtos;
}

$clientes = getClientes($link);
$produtos = getProdutos($link);

// Processar venda quando formulário for enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Iniciar transação
    mysqli_begin_transaction($link);

    try {
        // Dados da venda
        $cliente_id = intval($_POST['cliente_id']);
        $forma_pagamento = trim($_POST['forma_pagamento']);
        $parcelas = isset($_POST['parcelas']) ? intval($_POST['parcelas']) : 1;
        $desconto = floatval(str_replace(['R$ ', '.', ','], ['', '', '.'], $_POST['desconto'] ?? '0'));

        // Validações
        if ($cliente_id <= 0) {
            throw new Exception('Selecione um cliente');
        }

        if (empty($forma_pagamento)) {
            throw new Exception('Selecione a forma de pagamento');
        }

        // Processar itens da venda
        $produtos_venda = $_POST['produto'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $precos = $_POST['preco'] ?? [];

        if (empty($produtos_venda) || count($produtos_venda) == 0) {
            throw new Exception('Adicione pelo menos um produto à venda');
        }

        $subtotal = 0;
        $itens_venda = [];

        for ($i = 0; $i < count($produtos_venda); $i++) {
            if (empty($produtos_venda[$i]) || empty($quantidades[$i]) || empty($precos[$i])) {
                continue;
            }

            $produto_id = intval($produtos_venda[$i]);
            $quantidade = intval($quantidades[$i]);
            $preco_unitario = floatval(str_replace(['R$ ', '.', ','], ['', '', '.'], $precos[$i]));

            if ($produto_id <= 0 || $quantidade <= 0 || $preco_unitario <= 0) {
                continue;
            }

            // Verificar estoque
            $sql = "SELECT PRO_QUANTIDADE, PRO_DESCRICAO FROM PRODUTO WHERE PRO_ID = ?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "i", $produto_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $produto = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$produto) {
                throw new Exception("Produto não encontrado");
            }

            if ($produto['PRO_QUANTIDADE'] < $quantidade) {
                throw new Exception("Estoque insuficiente para o produto: " . $produto['PRO_DESCRICAO']);
            }

            $total_item = $quantidade * $preco_unitario;
            $subtotal += $total_item;

            $itens_venda[] = [
                'produto_id' => $produto_id,
                'quantidade' => $quantidade,
                'preco_unitario' => $preco_unitario,
                'total_item' => $total_item
            ];
        }

        if (empty($itens_venda)) {
            throw new Exception('Nenhum item válido encontrado na venda');
        }

        $total = $subtotal - $desconto;

        if ($total <= 0) {
            throw new Exception('Total da venda deve ser maior que zero');
        }

        // Inserir venda
        $sql = "INSERT INTO VENDA (VEN_CLI_ID, VEN_FORMAPAGAMENTO, VEN_PARCELAS, VEN_SUBTOTAL, VEN_DESCONTO, VEN_TOTAL) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "isiddd", $cliente_id, $forma_pagamento, $parcelas, $subtotal, $desconto, $total);
        mysqli_stmt_execute($stmt);
        $venda_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        // Inserir itens da venda e atualizar estoque
        foreach ($itens_venda as $item) {
            // Inserir item da venda
            $sql = "INSERT INTO ITENSVENDA (ITV_VEN_ID, ITV_PRO_ID, ITV_QUANTIDADE, ITV_PRECOUNITARIO) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "iiid", $venda_id, $item['produto_id'], $item['quantidade'], $item['preco_unitario']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Atualizar estoque
            $sql = "UPDATE PRODUTO SET PRO_QUANTIDADE = PRO_QUANTIDADE - ? WHERE PRO_ID = ?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $item['quantidade'], $item['produto_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Confirmar transação
        mysqli_commit($link);

        $message = "Venda realizada com sucesso! Número da venda: #$venda_id";
        $message_type = 'success';

        // Limpar formulário
        $_POST = array();

    } catch (Exception $e) {
        // Desfazer transação em caso de erro
        mysqli_rollback($link);
        $message = 'Erro ao processar venda: ' . $e->getMessage();
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
            <i class="bi bi-cash-register"></i> Caixa - Nova Venda
        </h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="" id="form-venda">
    <div class="row">
        <!-- Seleção do Cliente -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person"></i> Cliente
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="cliente_id" class="form-label required">Selecione o Cliente</label>
                        <select class="form-control" id="cliente_id" name="cliente_id" required>
                            <option value="">Escolha um cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['CLI_ID']; ?>" 
                                        <?php echo (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cliente['CLI_ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['CLI_NOMECOMPLETO']); ?> 
                                    (CPF: <?php echo substr($cliente['CLI_CPF'], 0, 3) . '.***.***-' . substr($cliente['CLI_CPF'], -2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCadastroCliente">
                        <i class="bi bi-person-plus"></i> Cadastrar Novo Cliente
                    </button>
                </div>
            </div>
        </div>

        <!-- Forma de Pagamento -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-credit-card"></i> Pagamento
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="forma_pagamento" class="form-label required">Forma de Pagamento</label>
                        <select class="form-control" id="forma_pagamento" name="forma_pagamento" required onchange="toggleParcelas()">
                            <option value="">Selecione</option>
                            <option value="Pix" <?php echo (isset($_POST['forma_pagamento']) && $_POST['forma_pagamento'] == 'Pix') ? 'selected' : ''; ?>>Pix</option>
                            <option value="Débito" <?php echo (isset($_POST['forma_pagamento']) && $_POST['forma_pagamento'] == 'Débito') ? 'selected' : ''; ?>>Cartão de Débito</option>
                            <option value="Crédito" <?php echo (isset($_POST['forma_pagamento']) && $_POST['forma_pagamento'] == 'Crédito') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                            <option value="Dinheiro" <?php echo (isset($_POST['forma_pagamento']) && $_POST['forma_pagamento'] == 'Dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                        </select>
                    </div>
                    <div id="parcelas-div" style="display: none;">
                        <label for="parcelas" class="form-label">Parcelas</label>
                        <select class="form-control" id="parcelas" name="parcelas">
                            <option value="1">1x</option>
                            <option value="2">2x</option>
                            <option value="3">3x</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Produtos -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-cart"></i> Produtos
                    </h5>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalCadastroProduto">
                            <i class="bi bi-box-seam"></i> Cadastrar Produto
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="addItemVenda()">
                            <i class="bi bi-plus"></i> Adicionar Produto
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Cabeçalho da tabela -->
                    <div class="row mb-2">
                        <div class="col-md-4"><strong>Produto</strong></div>
                        <div class="col-md-2"><strong>Quantidade</strong></div>
                        <div class="col-md-3"><strong>Preço Unitário</strong></div>
                        <div class="col-md-2"><strong>Total</strong></div>
                        <div class="col-md-1"><strong>Ação</strong></div>
                    </div>
                    
                    <!-- Container para os itens -->
                    <div id="itens-venda">
                        <!-- Primeiro item (sempre presente) -->
                        <div class="row mb-3 item-venda">
                            <div class="col-md-4">
                                <select class="form-control" name="produto[]" onchange="updatePreco(this)" required>
                                    <option value="">Selecione o produto</option>
                                    <?php foreach ($produtos as $produto): ?>
                                        <option value="<?php echo $produto['PRO_ID']; ?>" 
                                                data-preco="<?php echo $produto['PRO_VALORVENDA']; ?>"
                                                data-estoque="<?php echo $produto['PRO_QUANTIDADE']; ?>">
                                            <?php echo htmlspecialchars($produto['PRO_DESCRICAO']); ?>
                                            <?php if (!empty($produto['PRO_TAMANHO'])): ?>
                                                (<?php echo $produto['PRO_TAMANHO']; ?>)
                                            <?php endif; ?>
                                            - Estoque: <?php echo $produto['PRO_QUANTIDADE']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control" name="quantidade[]" placeholder="Qtd" 
                                       min="1" required onchange="calculateItemTotal(this)">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="preco[]" placeholder="R$ 0,00" 
                                       readonly onchange="calculateItemTotal(this)">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control" name="total_item[]" placeholder="R$ 0,00" readonly>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeItemVenda(this)" disabled>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Totais -->
    <div class="row">
        <div class="col-md-8"></div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calculator"></i> Totais
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Subtotal:</label>
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control" id="subtotal" name="subtotal" readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="desconto" class="form-label">Desconto:</label>
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control" id="desconto" name="desconto" 
                                   placeholder="R$ 0,00" onchange="calculateTotal()">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label"><strong>Total:</strong></label>
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control fw-bold" id="total" name="total" readonly>
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
                <i class="bi bi-check-circle"></i> Finalizar Venda
            </button>
            <button type="button" class="btn btn-warning btn-lg ms-2" onclick="clearForm()">
                <i class="bi bi-arrow-clockwise"></i> Limpar
            </button>
            <a href="../index.php" class="btn btn-secondary btn-lg ms-2">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</form>

<script>
// Produtos disponíveis (para JavaScript)
const produtos = <?php echo json_encode($produtos); ?>;

// Função para adicionar item à venda
function addItemVenda() {
    const container = document.getElementById('itens-venda');
    const itemCount = container.children.length;
    
    const newItem = document.createElement('div');
    newItem.className = 'row mb-3 item-venda';
    newItem.innerHTML = `
        <div class="col-md-4">
            <select class="form-control" name="produto[]" onchange="updatePreco(this)" required>
                <option value="">Selecione o produto</option>
                <?php foreach ($produtos as $produto): ?>
                    <option value="<?php echo $produto['PRO_ID']; ?>" 
                            data-preco="<?php echo $produto['PRO_VALORVENDA']; ?>"
                            data-estoque="<?php echo $produto['PRO_QUANTIDADE']; ?>">
                        <?php echo htmlspecialchars($produto['PRO_DESCRICAO']); ?>
                        <?php if (!empty($produto['PRO_TAMANHO'])): ?>
                            (<?php echo $produto['PRO_TAMANHO']; ?>)
                        <?php endif; ?>
                        - Estoque: <?php echo $produto['PRO_QUANTIDADE']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control" name="quantidade[]" placeholder="Qtd" 
                   min="1" required onchange="calculateItemTotal(this)">
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control" name="preco[]" placeholder="R$ 0,00" 
                   readonly onchange="calculateItemTotal(this)">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" name="total_item[]" placeholder="R$ 0,00" readonly>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeItemVenda(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(newItem);
    updateRemoveButtons();
}

// Função para remover item da venda
function removeItemVenda(button) {
    const container = document.getElementById('itens-venda');
    if (container.children.length > 1) {
        button.closest('.item-venda').remove();
        updateRemoveButtons();
        calculateTotal();
    }
}

// Função para atualizar botões de remover
function updateRemoveButtons() {
    const container = document.getElementById('itens-venda');
    const removeButtons = container.querySelectorAll('button[onclick*="removeItemVenda"]');
    
    removeButtons.forEach((button, index) => {
        button.disabled = container.children.length === 1;
    });
}

// Função para atualizar preço quando produto é selecionado
function updatePreco(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const preco = selectedOption.getAttribute('data-preco');
    const estoque = selectedOption.getAttribute('data-estoque');
    
    const row = selectElement.closest('.item-venda');
    const precoInput = row.querySelector('input[name="preco[]"]');
    const quantidadeInput = row.querySelector('input[name="quantidade[]"]');
    
    if (preco) {
        precoInput.value = 'R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');
        quantidadeInput.max = estoque;
        calculateItemTotal(quantidadeInput);
    } else {
        precoInput.value = '';
        quantidadeInput.max = '';
        calculateItemTotal(quantidadeInput);
    }
}

// Função para calcular total do item
function calculateItemTotal(element) {
    const row = element.closest('.item-venda');
    const quantidade = parseInt(row.querySelector('input[name="quantidade[]"]').value) || 0;
    const precoStr = row.querySelector('input[name="preco[]"]').value.replace(/[R$\s.]/g, '').replace(',', '.');
    const preco = parseFloat(precoStr) || 0;
    
    const total = quantidade * preco;
    const totalInput = row.querySelector('input[name="total_item[]"]');
    
    if (total > 0) {
        totalInput.value = 'R$ ' + total.toFixed(2).replace('.', ',');
    } else {
        totalInput.value = '';
    }
    
    calculateTotal();
}

// Função para calcular total geral
function calculateTotal() {
    let subtotal = 0;
    const items = document.querySelectorAll('.item-venda');
    
    items.forEach(item => {
        const totalItemStr = item.querySelector('input[name="total_item[]"]').value;
        if (totalItemStr) {
            const value = parseFloat(totalItemStr.replace(/[R$\s.]/g, '').replace(',', '.'));
            if (!isNaN(value)) {
                subtotal += value;
            }
        }
    });
    
    const descontoStr = document.getElementById('desconto').value.replace(/[R$\s.]/g, '').replace(',', '.');
    const desconto = parseFloat(descontoStr) || 0;
    const total = subtotal - desconto;
    
    document.getElementById('subtotal').value = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
    document.getElementById('total').value = 'R$ ' + Math.max(0, total).toFixed(2).replace('.', ',');
}

// Função para mostrar/ocultar parcelas
function toggleParcelas() {
    const formaPagamento = document.getElementById('forma_pagamento').value;
    const parcelasDiv = document.getElementById('parcelas-div');
    
    if (formaPagamento === 'Crédito') {
        parcelasDiv.style.display = 'block';
        document.getElementById('parcelas').required = true;
    } else {
        parcelasDiv.style.display = 'none';
        document.getElementById('parcelas').value = '1';
        document.getElementById('parcelas').required = false;
    }
}

// Função para cadastrar cliente rápido
function cadastrarClienteRapido() {
    const nome = document.getElementById('cliente_nome_rapido').value.trim();
    const cpf = document.getElementById('cliente_cpf_rapido').value.trim();
    const telefone = document.getElementById('cliente_telefone_rapido').value.trim();
    const nomeContato = document.getElementById('cliente_contato_rapido').value.trim();

    if (!nome || !cpf) {
        alert('Nome e CPF são obrigatórios');
        return;
    }

    const dados = `nome=${encodeURIComponent(nome)}&cpf=${encodeURIComponent(cpf)}&telefone=${encodeURIComponent(telefone)}&contato=${encodeURIComponent(nomeContato)}`;

    fetch('cadastro_cliente_rapido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: dados
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Adicionar cliente ao select
            const select = document.getElementById('cliente_id');
            const option = new Option(`${data.cliente.nome} (CPF: ${data.cliente.cpf_formatado})`, data.cliente.id);
            select.add(option);
            select.value = data.cliente.id;

            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalCadastroCliente'));
            modal.hide();

            // Limpar formulário
            document.getElementById('formClienteRapido').reset();

            alert('Cliente cadastrado com sucesso!');
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao cadastrar cliente');
        console.error(error);
    });
}

// Função para cadastrar produto rápido
function cadastrarProdutoRapido() {
    const descricao = document.getElementById('produto_descricao_rapido').value;
    const valorVenda = document.getElementById('produto_valor_rapido').value;
    const quantidade = document.getElementById('produto_quantidade_rapido').value;
    
    if (!descricao || !valorVenda || !quantidade) {
        alert('Descrição, valor e quantidade são obrigatórios');
        return;
    }
    
    // Enviar via AJAX
    fetch('cadastro_produto_rapido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `descricao=${encodeURIComponent(descricao)}&valor_venda=${encodeURIComponent(valorVenda)}&quantidade=${encodeURIComponent(quantidade)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarregar a página para atualizar a lista de produtos
            alert('Produto cadastrado com sucesso! A página será recarregada.');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao cadastrar produto');
        console.error(error);
    });
}

// Função para limpar formulário
function clearForm() {
    if (confirm('Tem certeza que deseja limpar todos os dados?')) {
        document.getElementById('form-venda').reset();
        
        // Manter apenas um item
        const container = document.getElementById('itens-venda');
        while (container.children.length > 1) {
            container.removeChild(container.lastChild);
        }
        
        updateRemoveButtons();
        calculateTotal();
        toggleParcelas();
    }
}

// Aplicar máscara de dinheiro ao desconto
document.addEventListener('DOMContentLoaded', function() {
    const descontoInput = document.getElementById('desconto');
    descontoInput.addEventListener('input', function() {
        formatMoney(this);
        calculateTotal();
    });
    
    // Inicializar
    updateRemoveButtons();
    calculateTotal();
    toggleParcelas();
});
</script>

<!-- Modal de Cadastro Rápido de Cliente -->
<div class="modal fade" id="modalCadastroCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus"></i> Cadastro Rápido de Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formClienteRapido">
                    <div class="mb-3">
                        <label for="cliente_nome_rapido" class="form-label">Nome Completo *</label>
                        <input type="text" class="form-control" id="cliente_nome_rapido" required>
                    </div>
                    <div class="mb-3">
                        <label for="cliente_cpf_rapido" class="form-label">CPF *</label>
                        <input type="text" class="form-control" id="cliente_cpf_rapido" required>
                    </div>
                    <div class="mb-3">
                        <label for="cliente_telefone_rapido" class="form-label">Telefone *</label>
                        <input type="text" class="form-control" id="cliente_telefone_rapido" required>
                    </div>
                    <div class="mb-3">
                        <label for="cliente_contato_rapido" class="form-label">Nome contato *</label>
                        <input type="text" class="form-control" id="cliente_contato_rapido" required>
                    </div>
                    <small class="text-muted">* Campos obrigatórios. Dados adicionais podem ser completados posteriormente na listagem de clientes.</small>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="cadastrarClienteRapido()">
                    <i class="bi bi-check"></i> Cadastrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Cadastro Rápido de Produto -->
<div class="modal fade" id="modalCadastroProduto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-box-seam"></i> Cadastro Rápido de Produto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formProdutoRapido">
                    <div class="mb-3">
                        <label for="produto_descricao_rapido" class="form-label">Descrição *</label>
                        <input type="text" class="form-control" id="produto_descricao_rapido" required>
                    </div>
                    <div class="mb-3">
                        <label for="produto_valor_rapido" class="form-label">Valor de Venda *</label>
                        <input type="text" class="form-control" id="produto_valor_rapido" placeholder="R$ 0,00" required>
                    </div>
                    <div class="mb-3">
                        <label for="produto_quantidade_rapido" class="form-label">Quantidade *</label>
                        <input type="number" class="form-control" id="produto_quantidade_rapido" min="1" required>
                    </div>
                    <small class="text-muted">* Campos obrigatórios. Dados adicionais podem ser completados posteriormente na listagem de produtos.</small>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="cadastrarProdutoRapido()">
                    <i class="bi bi-check"></i> Cadastrar
                </button>
            </div>
        </div>
    </div>
</div>

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

