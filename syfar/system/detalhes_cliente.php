<?php
require_once 'utils/conectadb.php';

$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cliente_id <= 0) {
    echo '<div class="alert alert-danger">ID do cliente inválido.</div>';
    exit;
}

// Buscar dados completos do cliente
// Dados básicos do cliente
$sql = "SELECT * FROM CLIENTE WHERE CLI_ID = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cliente = mysqli_fetch_assoc($result);

if (!$cliente) {
    echo '<div class="alert alert-danger">Cliente não encontrado.</div>';
    exit;
}

// Contatos
$sql = "SELECT * FROM CONTATO WHERE CON_CLI_ID = ? ORDER BY CON_ID";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$contatos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $contatos[] = $row;
}

// Endereços
$sql = "SELECT * FROM ENDERECO WHERE END_CLI_ID = ? ORDER BY END_ID";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$enderecos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $enderecos[] = $row;
}

// Últimas vendas
$sql = "
    SELECT VEN_ID, VEN_DATAVENDA, VEN_TOTAL, VEN_FORMAPAGAMENTO
    FROM VENDA 
    WHERE VEN_CLI_ID = ? 
    ORDER BY VEN_DATAVENDA DESC 
    LIMIT 10";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$vendas = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vendas[] = $row;
}

// Estatísticas
$sql = "
    SELECT 
        COUNT(*) as total_vendas,
        COALESCE(SUM(VEN_TOTAL), 0) as valor_total,
        COALESCE(AVG(VEN_TOTAL), 0) as ticket_medio,
        MAX(VEN_DATAVENDA) as ultima_compra
    FROM VENDA 
    WHERE VEN_CLI_ID = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);

function formatarCPF($cpf) {
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

function formatarTelefone($telefone) {
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

function formatarCEP($cep) {
    return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
}
?>


<div class="row">
    <!-- Dados Básicos -->
    <div class="col-md-6">
        <h6><i class="bi bi-person-fill"></i> Dados Básicos</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>ID:</strong></td>
                <td>#<?php echo $cliente['CLI_ID']; ?></td>
            </tr>
            <tr>
                <td><strong>Nome:</strong></td>
                <td><?php echo htmlspecialchars($cliente['CLI_NOMECOMPLETO']); ?></td>
            </tr>
            <tr>
                <td><strong>CPF:</strong></td>
                <td><?php echo formatarCPF($cliente['CLI_CPF']); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Estatísticas -->
    <div class="col-md-6">
        <h6><i class="bi bi-graph-up"></i> Estatísticas</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Total de Vendas:</strong></td>
                <td><span class="badge bg-primary"><?php echo $stats['total_vendas']; ?></span></td>
            </tr>
            <tr>
                <td><strong>Valor Total:</strong></td>
                <td><strong class="text-success">R$ <?php echo number_format($stats['valor_total'], 2, ',', '.'); ?></strong></td>
            </tr>
            <tr>
                <td><strong>Ticket Médio:</strong></td>
                <td>R$ <?php echo number_format($stats['ticket_medio'], 2, ',', '.'); ?></td>
            </tr>
            <tr>
                <td><strong>Última Compra:</strong></td>
                <td>
                    <?php if ($stats['ultima_compra']): ?>
                        <?php echo date('d/m/Y', strtotime($stats['ultima_compra'])); ?>
                    <?php else: ?>
                        <span class="text-muted">Nenhuma compra</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<hr>

<!-- Contatos -->
<div class="row">
    <div class="col-md-6">
        <h6><i class="bi bi-telephone"></i> Contatos</h6>
        <?php if (empty($contatos)): ?>
            <div class="alert alert-info alert-sm">Nenhum contato cadastrado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Telefone</th>
                            <th>Nome do Contato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contatos as $contato): ?>
                            <tr>
                                <td><?php echo formatarTelefone($contato['CON_TELEFONE']); ?></td>
                                <td><?php echo htmlspecialchars($contato['CON_NOMECONTATO'] ?: 'Não informado'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Endereços -->
    <div class="col-md-6">
        <h6><i class="bi bi-geo-alt"></i> Endereços</h6>
        <?php if (empty($enderecos)): ?>
            <div class="alert alert-info alert-sm">Nenhum endereço cadastrado.</div>
        <?php else: ?>
            <?php foreach ($enderecos as $index => $endereco): ?>
                <div class="card mb-2">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">Endereço <?php echo $index + 1; ?></h6>
                        <p class="card-text mb-1">
                            <strong><?php echo htmlspecialchars($endereco['END_ENDERECO']); ?></strong><br>
                            <?php if ($endereco['END_CIDADE']): ?>
                                <?php echo htmlspecialchars($endereco['END_CIDADE']); ?>
                                <?php if ($endereco['END_ESTADO']): ?>
                                    - <?php echo htmlspecialchars($endereco['END_ESTADO']); ?>
                                <?php endif; ?>
                                <br>
                            <?php endif; ?>
                            <?php if ($endereco['END_CEP']): ?>
                                CEP: <?php echo formatarCEP($endereco['END_CEP']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<hr>

<!-- Histórico de Vendas -->
<div class="row">
    <div class="col-12">
        <h6><i class="bi bi-receipt"></i> Últimas Vendas</h6>
        <?php if (empty($vendas)): ?>
            <div class="alert alert-info">Nenhuma venda realizada.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Pagamento</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendas as $venda): ?>
                            <tr>
                                <td><strong>#<?php echo $venda['VEN_ID']; ?></strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venda['VEN_DATAVENDA'])); ?></td>
                                <td><strong class="text-success">R$ <?php echo number_format($venda['VEN_TOTAL'], 2, ',', '.'); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo $venda['VEN_FORMAPAGAMENTO']; ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-outline-primary" 
                                            onclick="verDetalhesVenda(<?php echo $venda['VEN_ID']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($vendas) >= 10): ?>
                <div class="text-center">
                    <a href="listagem_vendas.php?cliente=<?php echo urlencode($cliente['CLI_NOMECOMPLETO']); ?>" 
                       class="btn btn-sm btn-outline-primary">
                        Ver todas as vendas deste cliente
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function verDetalhesVenda(vendaId) {
    // Fechar o modal atual
    const modalAtual = bootstrap.Modal.getInstance(document.getElementById('modalDetalhesCliente'));
    modalAtual.hide();
    
    // Aguardar um pouco e abrir o modal de detalhes da venda
    setTimeout(() => {
        window.parent.verDetalhes(vendaId);
    }, 300);
}
</script>

