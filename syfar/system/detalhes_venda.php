<?php
include 'utils/conectadb.php';

$venda_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venda_id <= 0) {
    echo '<div class="alert alert-danger">ID da venda inválido.</div>';
    exit;
}

// Buscar dados da venda
$stmt = $link->prepare("
    SELECT 
        v.VEN_ID,
        v.VEN_DATAVENDA,
        c.CLI_NOMECOMPLETO,
        c.CLI_CPF,
        v.VEN_FORMAPAGAMENTO,
        v.VEN_PARCELAS,
        v.VEN_SUBTOTAL,
        v.VEN_DESCONTO,
        v.VEN_TOTAL
    FROM VENDA v
    INNER JOIN CLIENTE c ON v.VEN_CLI_ID = c.CLI_ID
    WHERE v.VEN_ID = ?
");

if (!$stmt) {
    echo '<div class="alert alert-danger">Erro na preparação da consulta de venda.</div>';
    exit;
}

$stmt->bind_param('i', $venda_id);

if (!$stmt->execute()) {
    echo '<div class="alert alert-danger">Erro ao executar consulta de venda.</div>';
    exit;
}

$result = $stmt->get_result();
$venda = $result->fetch_assoc();

if (!$venda) {
    echo '<div class="alert alert-danger">Venda não encontrada.</div>';
    exit;
}

// Buscar itens da venda
$stmt = $link->prepare("
    SELECT 
        iv.ITV_QUANTIDADE,
        iv.ITV_PRECOUNITARIO,
        iv.ITV_TOTALITEM,
        p.PRO_DESCRICAO,
        p.PRO_TIPO,
        p.PRO_TAMANHO,
        p.PRO_MARCA,
        p.PRO_CODIGOFORNECEDOR
    FROM ITENSVENDA iv
    INNER JOIN PRODUTO p ON iv.ITV_PRO_ID = p.PRO_ID
    WHERE iv.ITV_VEN_ID = ?
    ORDER BY p.PRO_DESCRICAO
");

if (!$stmt) {
    echo '<div class="alert alert-danger">Erro na preparação da consulta dos itens.</div>';
    exit;
}

$stmt->bind_param('i', $venda_id);

if (!$stmt->execute()) {
    echo '<div class="alert alert-danger">Erro ao executar consulta dos itens.</div>';
    exit;
}

$result = $stmt->get_result();
$itens = [];

while ($row = $result->fetch_assoc()) {
    $itens[] = $row;
}
?>


<div class="row">
    <div class="col-md-6">
        <h6><i class="bi bi-info-circle"></i> Informações da Venda</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Número:</strong></td>
                <td>#<?php echo $venda['VEN_ID']; ?></td>
            </tr>
            <tr>
                <td><strong>Data/Hora:</strong></td>
                <td><?php echo date('d/m/Y H:i:s', strtotime($venda['VEN_DATAVENDA'])); ?></td>
            </tr>
            <tr>
                <td><strong>Cliente:</strong></td>
                <td><?php echo htmlspecialchars($venda['CLI_NOMECOMPLETO']); ?></td>
            </tr>
            <tr>
                <td><strong>CPF:</strong></td>
                <td><?php echo substr($venda['CLI_CPF'], 0, 3) . '.***.***-' . substr($venda['CLI_CPF'], -2); ?></td>
            </tr>
            <tr>
                <td><strong>Pagamento:</strong></td>
                <td>
                    <?php echo $venda['VEN_FORMAPAGAMENTO']; ?>
                    <?php if ($venda['VEN_FORMAPAGAMENTO'] == 'Crédito' && $venda['VEN_PARCELAS'] > 1): ?>
                        (<?php echo $venda['VEN_PARCELAS']; ?>x)
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6><i class="bi bi-calculator"></i> Valores</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Subtotal:</strong></td>
                <td>R$ <?php echo number_format($venda['VEN_SUBTOTAL'], 2, ',', '.'); ?></td>
            </tr>
            <tr>
                <td><strong>Desconto:</strong></td>
                <td>
                    <?php if ($venda['VEN_DESCONTO'] > 0): ?>
                        <span class="text-danger">-R$ <?php echo number_format($venda['VEN_DESCONTO'], 2, ',', '.'); ?></span>
                    <?php else: ?>
                        <span class="text-muted">R$ 0,00</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="table-success">
                <td><strong>Total:</strong></td>
                <td><strong>R$ <?php echo number_format($venda['VEN_TOTAL'], 2, ',', '.'); ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<h6><i class="bi bi-cart"></i> Itens da Venda</h6>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Código Fornecedor</th>
                <th>Tipo</th>
                <th>Tamanho</th>
                <th>Qtd</th>
                <th>Preço Unit.</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $item): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($item['PRO_DESCRICAO']); ?></strong>
                        <?php if (!empty($item['PRO_MARCA'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($item['PRO_MARCA']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?php echo htmlspecialchars($item['PRO_CODIGOFORNECEDOR']); ?></code>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['PRO_TIPO']); ?></span>
                    </td>
                    <td>
                        <?php echo !empty($item['PRO_TAMANHO']) ? htmlspecialchars($item['PRO_TAMANHO']) : '-'; ?>
                    </td>
                    <td><?php echo $item['ITV_QUANTIDADE']; ?></td>
                    <td>R$ <?php echo number_format($item['ITV_PRECOUNITARIO'], 2, ',', '.'); ?></td>
                    <td><strong>R$ <?php echo number_format($item['ITV_TOTALITEM'], 2, ',', '.'); ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

