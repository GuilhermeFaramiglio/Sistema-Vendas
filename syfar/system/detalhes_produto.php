<?php
require_once 'utils/conectadb.php'; // Conexão via $link

$produto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($produto_id <= 0) {
    echo '<div class="alert alert-danger">ID do produto inválido.</div>';
    exit;
}

// Buscar dados completos do produto
// ===================== PRODUTO =====================
$query = "SELECT * FROM PRODUTO WHERE PRO_ID = ?";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "i", $produto_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$produto = mysqli_fetch_assoc($result);

if (!$produto) {
    echo '<div class="alert alert-danger">Produto não encontrado.</div>';
    exit;
}

// ================= HISTÓRICO DE VENDAS =================
$query = "
    SELECT 
        v.VEN_ID,
        v.VEN_DATAVENDA,
        c.CLI_NOMECOMPLETO,
        iv.ITV_QUANTIDADE,
        iv.ITV_PRECOUNITARIO,
        (iv.ITV_QUANTIDADE * iv.ITV_PRECOUNITARIO) AS ITV_TOTALITEM
    FROM ITENSVENDA iv
    INNER JOIN VENDA v ON iv.ITV_VEN_ID = v.VEN_ID
    INNER JOIN CLIENTE c ON v.VEN_CLI_ID = c.CLI_ID
    WHERE iv.ITV_PRO_ID = ?
    ORDER BY v.VEN_DATAVENDA DESC
    LIMIT 10
";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "i", $produto_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$vendas = mysqli_fetch_all($result, MYSQLI_ASSOC);

// =================== ESTATÍSTICAS =====================
$query = "
    SELECT 
        COALESCE(SUM(iv.ITV_QUANTIDADE), 0) AS total_vendido,
        COALESCE(SUM(iv.ITV_QUANTIDADE * iv.ITV_PRECOUNITARIO), 0) AS valor_total_vendido,
        COUNT(DISTINCT v.VEN_ID) AS total_vendas,
        COALESCE(AVG(iv.ITV_PRECOUNITARIO), 0) AS preco_medio_venda
    FROM ITENSVENDA iv
    INNER JOIN VENDA v ON iv.ITV_VEN_ID = v.VEN_ID
    WHERE iv.ITV_PRO_ID = ?
";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "i", $produto_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);

// Cálculos adicionais
$margem_lucro = $produto['PRO_VALORVENDA'] - $produto['PRO_VALORCOMPRA'];
$percentual_margem = $produto['PRO_VALORCOMPRA'] > 0 
    ? ($margem_lucro / $produto['PRO_VALORCOMPRA'] * 100) 
    : 0;

$valor_estoque = $produto['PRO_VALORCOMPRA'] * $produto['PRO_QUANTIDADE'];
?>

<div class="row">
    <!-- Imagem do Produto -->
    <div class="col-md-4">
        <?php if (!empty($produto['PRO_IMAGEM'])): ?>
            <img src="../<?php echo htmlspecialchars($produto['PRO_IMAGEM']); ?>" 
                 alt="<?php echo htmlspecialchars($produto['PRO_DESCRICAO']); ?>" 
                 class="img-fluid rounded">
        <?php else: ?>
            <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                 style="height: 200px;">
                <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Informações Básicas -->
    <div class="col-md-8">
        <h5><?php echo htmlspecialchars($produto['PRO_DESCRICAO']); ?></h5>
        
        <table class="table table-sm">
            <tr>
                <td><strong>ID:</strong></td>
                <td>#<?php echo $produto['PRO_ID']; ?></td>
            </tr>
            <tr>
                <td><strong>Código Fornecedor:</strong></td>
                <td><?php echo htmlspecialchars($produto['PRO_CODIGOFORNECEDOR'] ?: 'Não informado'); ?></td>
            </tr>
            <tr>
                <td><strong>Data de Cadastro:</strong></td>
                <td><?php echo date('d/m/Y', strtotime($produto['PRO_DATACADASTRO'])); ?></td>
            </tr>
            <tr>
                <td><strong>Tipo:</strong></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($produto['PRO_TIPO']); ?></span></td>
            </tr>
            <tr>
                <td><strong>Marca:</strong></td>
                <td><?php echo htmlspecialchars($produto['PRO_MARCA'] ?: 'Não informado'); ?></td>
            </tr>
            <tr>
                <td><strong>Modelo:</strong></td>
                <td><?php echo htmlspecialchars($produto['PRO_MODELO'] ?: 'Não informado'); ?></td>
            </tr>
            <tr>
                <td><strong>Tamanho:</strong></td>
                <td><?php echo htmlspecialchars($produto['PRO_TAMANHO'] ?: 'Não informado'); ?></td>
            </tr>
            <tr>
                <td><strong>Unidade:</strong></td>
                <td><?php echo htmlspecialchars($produto['PRO_UNIDADE'] ?: 'Não informado'); ?></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<!-- Informações Financeiras e de Estoque -->
<div class="row">
    <div class="col-md-6">
        <h6><i class="bi bi-currency-dollar"></i> Informações Financeiras</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Valor de Compra:</strong></td>
                <td>R$ <?php echo number_format($produto['PRO_VALORCOMPRA'], 2, ',', '.'); ?></td>
            </tr>
            <tr>
                <td><strong>Valor de Venda:</strong></td>
                <td>R$ <?php echo number_format($produto['PRO_VALORVENDA'], 2, ',', '.'); ?></td>
            </tr>
            <tr class="<?php echo ($margem_lucro > 0) ? 'table-success' : 'table-danger'; ?>">
                <td><strong>Margem de Lucro:</strong></td>
                <td>
                    <strong>R$ <?php echo number_format($margem_lucro, 2, ',', '.'); ?></strong>
                    (<?php echo number_format($percentual_margem, 1); ?>%)
                </td>
            </tr>
            <tr>
                <td><strong>Valor do Estoque:</strong></td>
                <td><strong>R$ <?php echo number_format($valor_estoque, 2, ',', '.'); ?></strong></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6><i class="bi bi-box"></i> Informações de Estoque</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Total Vendido:</strong></td>
                <td><span class="badge bg-info"><?php echo $stats['total_vendido']; ?> unidades</span></td>
            </tr>
            <tr>
                <td><strong>Valor Total Vendido:</strong></td>
                <td><strong class="text-success">R$ <?php echo number_format($stats['valor_total_vendido'], 2, ',', '.'); ?></strong></td>
            </tr>
            <tr>
                <td><strong>Preço Médio de Venda:</strong></td>
                <td>R$ <?php echo number_format($stats['preco_medio_venda'], 2, ',', '.'); ?></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<!-- Histórico de Vendas -->
<div class="row">
    <div class="col-12">
        <h6><i class="bi bi-graph-up"></i> Estatísticas de Vendas</h6>
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body p-2 text-center">
                        <h6><?php echo $stats['total_vendas']; ?></h6>
                        <small>Vendas Realizadas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body p-2 text-center">
                        <h6><?php echo $stats['total_vendido']; ?></h6>
                        <small>Unidades Vendidas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body p-2 text-center">
                        <h6>R$ <?php echo number_format($stats['valor_total_vendido'], 2, ',', '.'); ?></h6>
                        <small>Faturamento Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body p-2 text-center">
                        <h6>R$ <?php echo number_format($stats['preco_medio_venda'], 2, ',', '.'); ?></h6>
                        <small>Preço Médio</small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($vendas)): ?>
            <h6><i class="bi bi-receipt"></i> Últimas Vendas</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Venda</th>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Qtd</th>
                            <th>Preço Unit.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendas as $venda): ?>
                            <tr>
                                <td><strong>#<?php echo $venda['VEN_ID']; ?></strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venda['VEN_DATAVENDA'])); ?></td>
                                <td><?php echo htmlspecialchars($venda['CLI_NOMECOMPLETO']); ?></td>
                                <td><?php echo $venda['ITV_QUANTIDADE']; ?></td>
                                <td>R$ <?php echo number_format($venda['ITV_PRECOUNITARIO'], 2, ',', '.'); ?></td>
                                <td><strong>R$ <?php echo number_format($venda['ITV_TOTALITEM'], 2, ',', '.'); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                Este produto ainda não foi vendido.
            </div>
        <?php endif; ?>
    </div>
</div>