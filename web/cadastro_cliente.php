<?php
include 'utils/conectadb.php';

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Usar a variável $link do conectadb.php (mysqli)
    $nome_completo = trim($_POST['nome_completo']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);

    // Validar CPF
    if (strlen($cpf) != 11) {
        $message = 'CPF deve ter 11 dígitos';
        $message_type = 'danger';
    } else {
        // Verificar se CPF já existe
        $stmt = mysqli_prepare($link, "SELECT CLI_ID FROM CLIENTE WHERE CLI_CPF = ?");
        mysqli_stmt_bind_param($stmt, "s", $cpf);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $message = 'CPF já cadastrado no sistema';
            $message_type = 'danger';
            mysqli_stmt_close($stmt);
        } else {
            mysqli_stmt_close($stmt);

            // Iniciar transação
            mysqli_begin_transaction($link);

            try {
                // Inserir cliente
                $stmt = mysqli_prepare($link, "INSERT INTO CLIENTE (CLI_NOMECOMPLETO, CLI_CPF) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ss", $nome_completo, $cpf);
                mysqli_stmt_execute($stmt);
                $cliente_id = mysqli_insert_id($link);
                mysqli_stmt_close($stmt);

                // Inserir contatos
                for ($i = 1; $i <= 2; $i++) {
                    $telefone = isset($_POST["telefone_$i"]) ? preg_replace('/[^0-9]/', '', $_POST["telefone_$i"]) : '';
                    $nome_contato = isset($_POST["nome_contato_$i"]) ? trim($_POST["nome_contato_$i"]) : '';

                    if (!empty($telefone)) {
                        $stmt = mysqli_prepare($link, "INSERT INTO CONTATO (CON_CLI_ID, CON_TELEFONE, CON_NOMECONTATO) VALUES (?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "iss", $cliente_id, $telefone, $nome_contato);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }

                // Inserir endereços
                for ($i = 1; $i <= 2; $i++) {
                    $endereco = isset($_POST["endereco_$i"]) ? trim($_POST["endereco_$i"]) : '';
                    $cidade = isset($_POST["cidade_$i"]) ? trim($_POST["cidade_$i"]) : '';
                    $cep = isset($_POST["cep_$i"]) ? preg_replace('/[^0-9]/', '', $_POST["cep_$i"]) : '';
                    $estado = isset($_POST["estado_$i"]) ? trim($_POST["estado_$i"]) : '';

                    if (!empty($endereco)) {
                        $stmt = mysqli_prepare($link, "INSERT INTO ENDERECO (END_CLI_ID, END_ENDERECO, END_CIDADE, END_CEP, END_ESTADO) VALUES (?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "issss", $cliente_id, $endereco, $cidade, $cep, $estado);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }

                // Confirmar transação
                mysqli_commit($link);

                $message = 'Cliente cadastrado com sucesso!';
                $message_type = 'success';

                // Limpar formulário
                $_POST = array();
            } catch (Exception $e) {
                mysqli_rollback($link);
                $message = 'Erro ao cadastrar cliente: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
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
            <i class="bi bi-person-plus"></i> Cadastro de Cliente
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
                    <i class="bi bi-person-fill"></i> Dados do Cliente
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Dados Básicos -->
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="nome_completo" class="form-label required">Nome Completo</label>
                            <input type="text" class="form-control" id="nome_completo" name="nome_completo" 
                                   value="<?php echo isset($_POST['nome_completo']) ? htmlspecialchars($_POST['nome_completo']) : ''; ?>" 
                                   required maxlength="150">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cpf" class="form-label required">CPF</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" 
                                   value="<?php echo isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : ''; ?>" 
                                   required maxlength="14" placeholder="000.000.000-00">
                        </div>
                    </div>

                    <!-- Contatos -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-telephone"></i> Contatos
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Contato 1 -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefone_1" class="form-label">Telefone 1</label>
                                    <input type="text" class="form-control" id="telefone_1" name="telefone_1" 
                                           value="<?php echo isset($_POST['telefone_1']) ? htmlspecialchars($_POST['telefone_1']) : ''; ?>" 
                                           maxlength="15" placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nome_contato_1" class="form-label">Nome do Contato 1</label>
                                    <input type="text" class="form-control" id="nome_contato_1" name="nome_contato_1" 
                                           value="<?php echo isset($_POST['nome_contato_1']) ? htmlspecialchars($_POST['nome_contato_1']) : ''; ?>" 
                                           maxlength="50" placeholder="Ex: Próprio cliente">
                                </div>
                            </div>
                            
                            <!-- Contato 2 -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefone_2" class="form-label">Telefone 2</label>
                                    <input type="text" class="form-control" id="telefone_2" name="telefone_2" 
                                           value="<?php echo isset($_POST['telefone_2']) ? htmlspecialchars($_POST['telefone_2']) : ''; ?>" 
                                           maxlength="15" placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nome_contato_2" class="form-label">Nome do Contato 2</label>
                                    <input type="text" class="form-control" id="nome_contato_2" name="nome_contato_2" 
                                           value="<?php echo isset($_POST['nome_contato_2']) ? htmlspecialchars($_POST['nome_contato_2']) : ''; ?>" 
                                           maxlength="50" placeholder="Ex: Cônjuge, familiar">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Endereços -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-geo-alt"></i> Endereços
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Endereço 1 -->
                            <h6 class="text-primary">Endereço Principal</h6>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="endereco_1" class="form-label">Endereço 1</label>
                                    <input type="text" class="form-control" id="endereco_1" name="endereco_1" 
                                           value="<?php echo isset($_POST['endereco_1']) ? htmlspecialchars($_POST['endereco_1']) : ''; ?>" 
                                           maxlength="200" placeholder="Rua, número, bairro">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="cep_1" class="form-label">CEP 1</label>
                                    <input type="text" class="form-control" id="cep_1" name="cep_1" 
                                           value="<?php echo isset($_POST['cep_1']) ? htmlspecialchars($_POST['cep_1']) : ''; ?>" 
                                           maxlength="9" placeholder="00000-000">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="cidade_1" class="form-label">Cidade 1</label>
                                    <input type="text" class="form-control" id="cidade_1" name="cidade_1" 
                                           value="<?php echo isset($_POST['cidade_1']) ? htmlspecialchars($_POST['cidade_1']) : ''; ?>" 
                                           maxlength="100">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="estado_1" class="form-label">Estado 1</label>
                                    <select class="form-control" id="estado_1" name="estado_1">
                                        <option value="">Selecione</option>
                                        <option value="AC" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'AC') ? 'selected' : ''; ?>>Acre</option>
                                        <option value="AL" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'AL') ? 'selected' : ''; ?>>Alagoas</option>
                                        <option value="AP" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'AP') ? 'selected' : ''; ?>>Amapá</option>
                                        <option value="AM" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'AM') ? 'selected' : ''; ?>>Amazonas</option>
                                        <option value="BA" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'BA') ? 'selected' : ''; ?>>Bahia</option>
                                        <option value="CE" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'CE') ? 'selected' : ''; ?>>Ceará</option>
                                        <option value="DF" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'DF') ? 'selected' : ''; ?>>Distrito Federal</option>
                                        <option value="ES" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'ES') ? 'selected' : ''; ?>>Espírito Santo</option>
                                        <option value="GO" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'GO') ? 'selected' : ''; ?>>Goiás</option>
                                        <option value="MA" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'MA') ? 'selected' : ''; ?>>Maranhão</option>
                                        <option value="MT" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'MT') ? 'selected' : ''; ?>>Mato Grosso</option>
                                        <option value="MS" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'MS') ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                                        <option value="MG" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'MG') ? 'selected' : ''; ?>>Minas Gerais</option>
                                        <option value="PA" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'PA') ? 'selected' : ''; ?>>Pará</option>
                                        <option value="PB" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'PB') ? 'selected' : ''; ?>>Paraíba</option>
                                        <option value="PR" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'PR') ? 'selected' : ''; ?>>Paraná</option>
                                        <option value="PE" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'PE') ? 'selected' : ''; ?>>Pernambuco</option>
                                        <option value="PI" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'PI') ? 'selected' : ''; ?>>Piauí</option>
                                        <option value="RJ" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'RJ') ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                        <option value="RN" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'RN') ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                                        <option value="RS" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'RS') ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                        <option value="RO" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'RO') ? 'selected' : ''; ?>>Rondônia</option>
                                        <option value="RR" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'RR') ? 'selected' : ''; ?>>Roraima</option>
                                        <option value="SC" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'SC') ? 'selected' : ''; ?>>Santa Catarina</option>
                                        <option value="SP" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'SP') ? 'selected' : ''; ?>>São Paulo</option>
                                        <option value="SE" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'SE') ? 'selected' : ''; ?>>Sergipe</option>
                                        <option value="TO" <?php echo (isset($_POST['estado_1']) && $_POST['estado_1'] == 'TO') ? 'selected' : ''; ?>>Tocantins</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Endereço 2 -->
                            <h6 class="text-secondary">Endereço Alternativo (Opcional)</h6>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="endereco_2" class="form-label">Endereço 2</label>
                                    <input type="text" class="form-control" id="endereco_2" name="endereco_2" 
                                           value="<?php echo isset($_POST['endereco_2']) ? htmlspecialchars($_POST['endereco_2']) : ''; ?>" 
                                           maxlength="200" placeholder="Rua, número, bairro">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="cep_2" class="form-label">CEP 2</label>
                                    <input type="text" class="form-control" id="cep_2" name="cep_2" 
                                           value="<?php echo isset($_POST['cep_2']) ? htmlspecialchars($_POST['cep_2']) : ''; ?>" 
                                           maxlength="9" placeholder="00000-000">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="cidade_2" class="form-label">Cidade 2</label>
                                    <input type="text" class="form-control" id="cidade_2" name="cidade_2" 
                                           value="<?php echo isset($_POST['cidade_2']) ? htmlspecialchars($_POST['cidade_2']) : ''; ?>" 
                                           maxlength="100">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="estado_2" class="form-label">Estado 2</label>
                                    <select class="form-control" id="estado_2" name="estado_2">
                                        <option value="">Selecione</option>
                                        <option value="AC" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'AC') ? 'selected' : ''; ?>>Acre</option>
                                        <option value="AL" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'AL') ? 'selected' : ''; ?>>Alagoas</option>
                                        <option value="AP" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'AP') ? 'selected' : ''; ?>>Amapá</option>
                                        <option value="AM" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'AM') ? 'selected' : ''; ?>>Amazonas</option>
                                        <option value="BA" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'BA') ? 'selected' : ''; ?>>Bahia</option>
                                        <option value="CE" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'CE') ? 'selected' : ''; ?>>Ceará</option>
                                        <option value="DF" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'DF') ? 'selected' : ''; ?>>Distrito Federal</option>
                                        <option value="ES" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'ES') ? 'selected' : ''; ?>>Espírito Santo</option>
                                        <option value="GO" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'GO') ? 'selected' : ''; ?>>Goiás</option>
                                        <option value="MA" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'MA') ? 'selected' : ''; ?>>Maranhão</option>
                                        <option value="MT" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'MT') ? 'selected' : ''; ?>>Mato Grosso</option>
                                        <option value="MS" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'MS') ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                                        <option value="MG" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'MG') ? 'selected' : ''; ?>>Minas Gerais</option>
                                        <option value="PA" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'PA') ? 'selected' : ''; ?>>Pará</option>
                                        <option value="PB" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'PB') ? 'selected' : ''; ?>>Paraíba</option>
                                        <option value="PR" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'PR') ? 'selected' : ''; ?>>Paraná</option>
                                        <option value="PE" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'PE') ? 'selected' : ''; ?>>Pernambuco</option>
                                        <option value="PI" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'PI') ? 'selected' : ''; ?>>Piauí</option>
                                        <option value="RJ" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'RJ') ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                        <option value="RN" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'RN') ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                                        <option value="RS" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'RS') ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                        <option value="RO" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'RO') ? 'selected' : ''; ?>>Rondônia</option>
                                        <option value="RR" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'RR') ? 'selected' : ''; ?>>Roraima</option>
                                        <option value="SC" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'SC') ? 'selected' : ''; ?>>Santa Catarina</option>
                                        <option value="SP" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'SP') ? 'selected' : ''; ?>>São Paulo</option>
                                        <option value="SE" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'SE') ? 'selected' : ''; ?>>Sergipe</option>
                                        <option value="TO" <?php echo (isset($_POST['estado_2']) && $_POST['estado_2'] == 'TO') ? 'selected' : ''; ?>>Tocantins</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i> Cadastrar Cliente
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
// Aplicar máscaras aos campos
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf');
    cpfInput.addEventListener('input', function() {
        formatCPF(this);
    });
    
    // Máscara para telefones
    const phoneInputs = document.querySelectorAll('input[id^="telefone_"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPhone(this);
        });
    });
    
    // Máscara para CEPs
    const cepInputs = document.querySelectorAll('input[id^="cep_"]');
    cepInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatCEP(this);
        });
    });
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

