<?php
require_once 'utils/conectadb.php';

$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cliente_id <= 0) {
    echo '<div class="alert alert-danger">ID do cliente inválido.</div>';
    exit;
}

// Adicione as funções auxiliares se não existirem
if (!function_exists('formatarCPF')) {
    function formatarCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) == 11) {
            return substr($cpf, 0, 3) . '.' .
                   substr($cpf, 3, 3) . '.' .
                   substr($cpf, 6, 3) . '-' .
                   substr($cpf, 9, 2);
        }
        return $cpf;
    }
}
if (!function_exists('formatarCEP')) {
    function formatarCEP($cep) {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        if (strlen($cep) == 8) {
            return substr($cep, 0, 2) . '.' .
                   substr($cep, 2, 3) . '-' .
                   substr($cep, 5, 3);
        }
        return $cep;
    }
}
if (!function_exists('opcoesEstados')) {
    function opcoesEstados($selected = '') {
        $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
        $html = '';
        foreach ($ufs as $uf) {
            $html .= '<option value="'.$uf.'"'.($selected == $uf ? ' selected' : '').'>'.$uf.'</option>';
        }
        return $html;
    }
}

// Processar atualização do cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    mysqli_begin_transaction($link);

    try {
        // Dados básicos do cliente
        $nome = trim($_POST['nome']);
        $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);

        if (empty($nome) || empty($cpf)) {
            throw new Exception('Nome e CPF são obrigatórios');
        }

        // Verificar se CPF já existe para outro cliente
        $stmt = mysqli_prepare($link, "SELECT CLI_ID FROM CLIENTE WHERE CLI_CPF = ? AND CLI_ID != ?");
        mysqli_stmt_bind_param($stmt, 'si', $cpf, $cliente_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            throw new Exception('CPF já cadastrado para outro cliente');
        }
        mysqli_stmt_close($stmt);

        // Atualizar cliente
        $stmt = mysqli_prepare($link, "UPDATE CLIENTE SET CLI_NOMECOMPLETO = ?, CLI_CPF = ? WHERE CLI_ID = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $nome, $cpf, $cliente_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Atualizar contatos
        $contatos = $_POST['contato'] ?? [];
        $tipos_contato = $_POST['tipo_contato'] ?? [];

        // Remover contatos existentes
        $stmt = mysqli_prepare($link, "DELETE FROM CONTATO WHERE CON_CLI_ID = ?");
        mysqli_stmt_bind_param($stmt, 'i', $cliente_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Inserir novos contatos
        for ($i = 0; $i < count($contatos); $i++) {
            if (!empty($contatos[$i]) && !empty($tipos_contato[$i])) {
                $stmt = mysqli_prepare($link, "INSERT INTO CONTATO (CON_CLI_ID, CON_CONTATO, CON_TIPO) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iss', $cliente_id, $contatos[$i], $tipos_contato[$i]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        // Atualizar endereços
        $logradouros = $_POST['logradouro'] ?? [];
        $numeros = $_POST['numero'] ?? [];
        $complementos = $_POST['complemento'] ?? [];
        $bairros = $_POST['bairro'] ?? [];
        $cidades = $_POST['cidade'] ?? [];
        $estados = $_POST['estado'] ?? [];
        $ceps = $_POST['cep'] ?? [];

        // Remover endereços existentes
        $stmt = mysqli_prepare($link, "DELETE FROM ENDERECO WHERE END_CLI_ID = ?");
        mysqli_stmt_bind_param($stmt, 'i', $cliente_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Inserir novos endereços
        for ($i = 0; $i < count($logradouros); $i++) {
            if (!empty($logradouros[$i]) && !empty($cidades[$i])) {
                $cep_limpo = preg_replace('/[^0-9]/', '', $ceps[$i] ?? '');
                $stmt = mysqli_prepare($link, "
                    INSERT INTO ENDERECO (END_CLI_ID, END_LOGRADOURO, END_NUMERO, END_COMPLEMENTO, END_BAIRRO, END_CIDADE, END_ESTADO, END_CEP) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param(
                    $stmt,
                    'isssssss',
                    $cliente_id,
                    $logradouros[$i],
                    $numeros[$i] ?? '',
                    $complementos[$i] ?? '',
                    $bairros[$i] ?? '',
                    $cidades[$i],
                    $estados[$i] ?? '',
                    $cep_limpo
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        mysqli_commit($link);

        echo json_encode(['success' => true, 'message' => 'Cliente atualizado com sucesso!']);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($link);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Buscar dados do cliente
$stmt = mysqli_prepare($link, "SELECT * FROM CLIENTE WHERE CLI_ID = ?");
mysqli_stmt_bind_param($stmt, 'i', $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cliente = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$cliente) {
    echo '<div class="alert alert-danger">Cliente não encontrado.</div>';
    exit;
}

// Contatos
$stmt = mysqli_prepare($link, "SELECT * FROM CONTATO WHERE CON_CLI_ID = ? ORDER BY CON_ID");
mysqli_stmt_bind_param($stmt, 'i', $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$contatos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $contatos[] = $row;
}
mysqli_stmt_close($stmt);

// Endereços
$stmt = mysqli_prepare($link, "SELECT * FROM ENDERECO WHERE END_CLI_ID = ? ORDER BY END_ID");
mysqli_stmt_bind_param($stmt, 'i', $cliente_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$enderecos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $enderecos[] = $row;
}
mysqli_stmt_close($stmt);

// Fechar conexão
mysqli_close($link);
?>

<!-- Botão para abrir o modal de edição -->
<button type="button" class="btn btn-primary" onclick="abrirModalEditarCliente()">Editar Cliente</button>

<!-- Modal de Edição de Cliente -->
<div class="modal fade" id="modalEditarCliente" tabindex="-1" aria-labelledby="modalEditarClienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formEditarCliente" onsubmit="salvarCliente(event)">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarClienteLabel">Editar Cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <!-- Dados Básicos -->
            <div class="col-12 mb-4">
              <div class="card">
                <div class="card-header">
                  <h6 class="mb-0"><i class="bi bi-person"></i> Dados Básicos</h6>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-8 mb-3">
                      <label for="edit_nome" class="form-label required">Nome Completo</label>
                      <input type="text" class="form-control" id="edit_nome" name="nome" 
                             value="<?php echo htmlspecialchars($cliente['CLI_NOMECOMPLETO']); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label for="edit_cpf" class="form-label required">CPF</label>
                      <input type="text" class="form-control cpf-mask" id="edit_cpf" name="cpf" 
                             value="<?php echo formatarCPF($cliente['CLI_CPF']); ?>" required>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Contatos -->
            <div class="col-md-6 mb-4">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h6 class="mb-0"><i class="bi bi-telephone"></i> Contatos</h6>
                  <button type="button" class="btn btn-sm btn-success" onclick="adicionarContato()">
                    <i class="bi bi-plus"></i> Adicionar
                  </button>
                </div>
                <div class="card-body">
                  <div id="contatos-container">
                    <?php if (empty($contatos)): ?>
                      <div class="contato-item mb-3">
                        <div class="row">
                          <div class="col-md-8">
                            <input type="text" class="form-control telefone-mask" name="contato[]" placeholder="Telefone/Celular">
                          </div>
                          <div class="col-md-4">
                            <select class="form-control" name="tipo_contato[]">
                              <option value="Celular">Celular</option>
                              <option value="Telefone">Telefone</option>
                              <option value="WhatsApp">WhatsApp</option>
                            </select>
                          </div>
                        </div>
                      </div>
                    <?php else: ?>
                      <?php foreach ($contatos as $index => $contato): ?>
                        <div class="contato-item mb-3">
                          <div class="row">
                            <div class="col-md-8">
                              <input type="text" class="form-control telefone-mask" name="contato[]" 
                                     value="<?php echo htmlspecialchars($contato['CON_CONTATO']); ?>" placeholder="Telefone/Celular">
                            </div>
                            <div class="col-md-3">
                              <select class="form-control" name="tipo_contato[]">
                                <option value="Celular" <?php echo $contato['CON_TIPO'] == 'Celular' ? 'selected' : ''; ?>>Celular</option>
                                <option value="Telefone" <?php echo $contato['CON_TIPO'] == 'Telefone' ? 'selected' : ''; ?>>Telefone</option>
                                <option value="WhatsApp" <?php echo $contato['CON_TIPO'] == 'WhatsApp' ? 'selected' : ''; ?>>WhatsApp</option>
                              </select>
                            </div>
                            <div class="col-md-1">
                              <button type="button" class="btn btn-sm btn-danger" onclick="removerContato(this)">
                                <i class="bi bi-trash"></i>
                              </button>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <!-- Endereços -->
            <div class="col-md-6 mb-4">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Endereços</h6>
                  <button type="button" class="btn btn-sm btn-success" onclick="adicionarEndereco()">
                    <i class="bi bi-plus"></i> Adicionar
                  </button>
                </div>
                <div class="card-body">
                  <div id="enderecos-container">
                    <?php if (empty($enderecos)): ?>
                      <div class="endereco-item mb-3 p-3 border rounded">
                        <div class="row">
                          <div class="col-md-8 mb-2">
                            <input type="text" class="form-control" name="logradouro[]" placeholder="Logradouro">
                          </div>
                          <div class="col-md-4 mb-2">
                            <input type="text" class="form-control" name="numero[]" placeholder="Número">
                          </div>
                          <div class="col-md-6 mb-2">
                            <input type="text" class="form-control" name="complemento[]" placeholder="Complemento">
                          </div>
                          <div class="col-md-6 mb-2">
                            <input type="text" class="form-control" name="bairro[]" placeholder="Bairro">
                          </div>
                          <div class="col-md-5 mb-2">
                            <input type="text" class="form-control" name="cidade[]" placeholder="Cidade">
                          </div>
                          <div class="col-md-3 mb-2">
                            <select class="form-control" name="estado[]">
                              <option value="">UF</option>
                              <?php echo opcoesEstados(); ?>
                            </select>
                          </div>
                          <div class="col-md-4 mb-2">
                            <input type="text" class="form-control cep-mask" name="cep[]" placeholder="CEP">
                          </div>
                        </div>
                      </div>
                    <?php else: ?>
                      <?php foreach ($enderecos as $index => $endereco): ?>
                        <div class="endereco-item mb-3 p-3 border rounded">
                          <div class="row">
                            <div class="col-md-8 mb-2">
                              <input type="text" class="form-control" name="logradouro[]" 
                                     value="<?php echo htmlspecialchars($endereco['END_LOGRADOURO']); ?>" placeholder="Logradouro">
                            </div>
                            <div class="col-md-4 mb-2">
                              <input type="text" class="form-control" name="numero[]" 
                                     value="<?php echo htmlspecialchars($endereco['END_NUMERO']); ?>" placeholder="Número">
                            </div>
                            <div class="col-md-6 mb-2">
                              <input type="text" class="form-control" name="complemento[]" 
                                     value="<?php echo htmlspecialchars($endereco['END_COMPLEMENTO']); ?>" placeholder="Complemento">
                            </div>
                            <div class="col-md-6 mb-2">
                              <input type="text" class="form-control" name="bairro[]" 
                                     value="<?php echo htmlspecialchars($endereco['END_BAIRRO']); ?>" placeholder="Bairro">
                            </div>
                            <div class="col-md-5 mb-2">
                              <input type="text" class="form-control" name="cidade[]" 
                                     value="<?php echo htmlspecialchars($endereco['END_CIDADE']); ?>" placeholder="Cidade">
                            </div>
                            <div class="col-md-2 mb-2">
                              <select class="form-control" name="estado[]">
                                <option value="">UF</option>
                                <?php echo opcoesEstados($endereco['END_ESTADO']); ?>
                              </select>
                            </div>
                            <div class="col-md-4 mb-2">
                              <input type="text" class="form-control cep-mask" name="cep[]" 
                                     value="<?php echo formatarCEP($endereco['END_CEP']); ?>" placeholder="CEP">
                            </div>
                            <div class="col-md-1 mb-2">
                              <button type="button" class="btn btn-sm btn-danger" onclick="removerEndereco(this)">
                                <i class="bi bi-trash"></i>
                              </button>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg"></i> Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Mova o script para fora do modal -->
<script>
function abrirModalEditarCliente() {
    const modalElement = document.getElementById('modalEditarCliente');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
}

function salvarCliente(event) {
    event.preventDefault();

    const form = document.getElementById('formEditarCliente');
    const formData = new FormData(form);

    // Desabilitar botão de submit
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Salvando...';

    fetch('editar_cliente.php?id=<?php echo $cliente_id; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarCliente'));
            modal.hide();
            // Recarregar página
            window.location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao salvar cliente');
        console.error(error);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function adicionarContato() {
    const container = document.getElementById('contatos-container');
    const novoContato = document.createElement('div');
    novoContato.className = 'contato-item mb-3';
    novoContato.innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <input type="text" class="form-control telefone-mask" name="contato[]" placeholder="Telefone/Celular">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="tipo_contato[]">
                    <option value="Celular">Celular</option>
                    <option value="Telefone">Telefone</option>
                    <option value="WhatsApp">WhatsApp</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-danger" onclick="removerContato(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(novoContato);
    if (typeof aplicarMascaras === 'function') aplicarMascaras();
}

function removerContato(btn) {
    btn.closest('.contato-item').remove();
}

function adicionarEndereco() {
    const container = document.getElementById('enderecos-container');
    const novoEndereco = document.createElement('div');
    novoEndereco.className = 'endereco-item mb-3 p-3 border rounded';
    novoEndereco.innerHTML = `
        <div class="row">
            <div class="col-md-8 mb-2">
                <input type="text" class="form-control" name="logradouro[]" placeholder="Logradouro">
            </div>
            <div class="col-md-4 mb-2">
                <input type="text" class="form-control" name="numero[]" placeholder="Número">
            </div>
            <div class="col-md-6 mb-2">
                <input type="text" class="form-control" name="complemento[]" placeholder="Complemento">
            </div>
            <div class="col-md-6 mb-2">
                <input type="text" class="form-control" name="bairro[]" placeholder="Bairro">
            </div>
            <div class="col-md-5 mb-2">
                <input type="text" class="form-control" name="cidade[]" placeholder="Cidade">
            </div>
            <div class="col-md-2 mb-2">
                <select class="form-control" name="estado[]">
                    <option value="">UF</option>
                    <?php echo opcoesEstados(); ?>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <input type="text" class="form-control cep-mask" name="cep[]" placeholder="CEP">
            </div>
            <div class="col-md-1 mb-2">
                <button type="button" class="btn btn-sm btn-danger" onclick="removerEndereco(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(novoEndereco);
    if (typeof aplicarMascaras === 'function') aplicarMascaras();
}

function removerEndereco(btn) {
    btn.closest('.endereco-item').remove();
}
</script>

<?php

?>

