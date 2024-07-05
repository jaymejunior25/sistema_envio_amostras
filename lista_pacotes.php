<?php
session_start();
include 'db.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if ($_SESSION['user_type'] != 'admin') {
    header('Location: index.php');
    exit();
}

$stmt = $dbconn->prepare("SELECT id, nome FROM unidadehemopa");
$stmt->execute();
$locais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inicializar variáveis para filtros
$filter = '';
$local_id = '';
$searchType = '';
$searchQuery = '';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['filter'])) {
        $filter = $_GET['filter'];
    }
    if (isset($_GET['local_id'])) {
        $local_id = $_GET['local_id'];
    }
    if (isset($_GET['searchType']) && isset($_GET['searchQuery'])) {
        $searchType = $_GET['searchType'];
        $searchQuery = $_GET['searchQuery'];
    }
}

// Construir a consulta SQL com base nos filtros
$sql = "SELECT p.id, p.status, p.codigobarras, p.descricao, p.data_envio, p.data_recebimento, p.data_cadastro, l_envio.nome AS envio_nome, u_envio.usuario AS enviado_por, u_recebimento.usuario AS recebido_por,
        u_cadastro.usuario AS cadastrado_por, l_cadastro.nome AS cadastro_nome 
        FROM pacotes p 
        LEFT JOIN unidadehemopa l_envio ON p.unidade_envio_id = l_envio.id 
        LEFT JOIN unidadehemopa l_cadastro ON p.unidade_cadastro_id = l_cadastro.id 
        LEFT JOIN usuarios u_cadastro ON p.usuario_cadastro_id = u_cadastro.id 
        LEFT JOIN usuarios u_envio ON p.usuario_envio_id = u_envio.id 
        LEFT JOIN usuarios u_recebimento ON p.usuario_recebimento_id = u_recebimento.id";

$conditions = [];
$params = [];

if ($filter == 'enviados') {
    $conditions[] = "p.data_envio IS NOT NULL";
} elseif ($filter == 'recebidos') {
    $conditions[] = "p.data_recebimento IS NOT NULL";
}

if (!empty($local_id)) {
    $conditions[] = "p.unidade_envio_id = :local_id";
    $params[':local_id'] = $local_id;
}

if (!empty($searchType) && !empty($searchQuery)) {
    $queryParam = '%' . $searchQuery . '%';
    switch ($searchType) {
        case 'codigobarras':
            $conditions[] = "p.codigobarras LIKE :query";
            break;
        case 'usuario_cadastro':
            $conditions[] = "u_cadastro.usuario LIKE :query";
            break;
        case 'usuario_envio':
            $conditions[] = "u_envio.usuario LIKE :query";
            break;
        case 'usuario_recebimento':
            $conditions[] = "u_recebimento.usuario LIKE :query";
            break;
        case 'unidade_cadastro':
            $conditions[] = "l_cadastro.nome LIKE :query";
            break;
        case 'unidade_envio':
            $conditions[] = "l_envio.nome LIKE :query";
            break;
        case 'data_cadastro':
            $conditions[] = "TO_CHAR(p.data_cadastro, 'YYYY-MM-DD') LIKE :query";
            break;
        case 'data_envio':
            $conditions[] = "TO_CHAR(p.data_envio, 'YYYY-MM-DD') LIKE :query";
            break;
        case 'data_recebimento':
            $conditions[] = "TO_CHAR(p.data_recebimento, 'YYYY-MM-DD') LIKE :query";
            break;
        default:
            break;
    }
    $params[':query'] = $queryParam;
}


if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY p.data_cadastro DESC";  // Ordenar por data de cadastro decrescente

$stmt = $dbconn->prepare($sql);
$stmt->execute($params);
$pacotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Pacotes</title>
    <link rel="icon" type="image/png" href="icon2.png" sizes="32x32" />
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container container-customlistas">
        <h1 class="text-center mb-4" style="color: #28a745;">Listar Pacotes</h1>
        <form method="GET" action="" class="form-inline mb-4 justify-content-center">
            <div class="form-group mr-3">
                <label for="filter" class="mr-2" style="color: #28a745;">Filtrar por:</label>
                <select name="filter" id="filter" class="form-control">
                    <option value="">Todos</option>
                    <option value="enviados" <?php if ($filter == 'enviados') echo 'selected'; ?>>Enviados</option>
                    <option value="recebidos" <?php if ($filter == 'recebidos') echo 'selected'; ?>>Recebidos</option>
                </select>
            </div>
            <div class="form-group mr-3">
                <label for="local_id" class="mr-2" style="color: #28a745;">Local:</label>
                <select name="local_id" id="local_id" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($locais as $local): ?>
                        <option value="<?php echo $local['id']; ?>" <?php if ($local_id == $local['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($local['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mr-3">
                <label for="searchType" class="mr-2" style="color: #28a745;">Pesquisar por:</label>
                <select name="searchType" id="searchType" class="form-control">
                    <option value="">Selecionar</option>
                    <option value="codigobarras" <?php if ($searchType == 'codigobarras') echo 'selected'; ?>>Código de Barras</option>
                    <option value="usuario_cadastro" <?php if ($searchType == 'usuario_cadastro') echo 'selected'; ?>>Usuário que Cadastrou</option>
                    <option value="usuario_envio" <?php if ($searchType == 'usuario_envio') echo 'selected'; ?>>Usuário que Enviou</option>
                    <option value="usuario_recebimento" <?php if ($searchType == 'usuario_recebimento') echo 'selected'; ?>>Usuário que Recebeu</option>
                    <option value="unidade_cadastro" <?php if ($searchType == 'unidade_cadastro') echo 'selected'; ?>>Unidade que Cadastrou</option>
                    <option value="unidade_envio" <?php if ($searchType == 'unidade_envio') echo 'selected'; ?>>Unidade que Enviou</option>
                    <option value="data_cadastro" <?php if ($searchType == 'data_cadastro') echo 'selected'; ?>>Data de Cadastro</option>
                    <option value="data_envio" <?php if ($searchType == 'data_envio') echo 'selected'; ?>>Data de Envio</option>
                    <option value="data_recebimento" <?php if ($searchType == 'data_recebimento') echo 'selected'; ?>>Data de Recebimento</option>
                </select>
            </div>
            <div class="form-group mr-3">
                <label for="searchQuery" class="mr-2" style="color: #28a745;">Consulta:</label>
                <input type="text" name="searchQuery" id="searchQuery" class="form-control" value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <button type="submit" class="btn btn-custom"><i class="fas fa-filter"></i> Filtrar</button>
        </form>
        
        <div class="table-wrapper" style="position: relative;" id="managerTable">  
            <table class="table table-bordered table-hover table-striped" >
                <thead class="theadfixed">
                    <tr>
                        <th>Codigo de Barras</th>
                        <th>Status</th>
                        <th>Descrição</th>
                        <th>Data de Cadastro</th>
                        <th>Data de Envio</th>
                        <th>Data de Recebimento</th>
                        <th>Local de Cadastro</th>
                        <th>Local de Envio</th>
                        <th>Cadastrado por</th>
                        <th>Enviado por</th>
                        <th>Recebido por</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pacotes) > 0): ?>
                        <?php foreach ($pacotes as $pacote): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pacote['codigobarras']); ?></td>
                                <td>
                                    <?php if ($pacote['status'] == 'cadastrado'): ?>
                                        <span class="badge badge-danger">cadastrado</span>
                                    <?php elseif($pacote['status'] == 'enviado'): ?>
                                        <span class="badge badge-warning">enviado</span>
                                    <?php elseif($pacote['status'] == 'recebido'): ?>
                                        <span class="badge badge-success">recebido</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($pacote['descricao']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['data_cadastro']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['data_envio']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['data_recebimento']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['cadastro_nome']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['envio_nome']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['cadastrado_por']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['enviado_por']); ?></td>
                                <td><?php echo htmlspecialchars($pacote['recebido_por']); ?></td>
                                <td class="headcol">
                                    <a href="editar_pacote.php?id=<?php echo $pacote['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
                                    <button type="button"  class="btn btn-danger btn-sm" onclick="openDeleteModal(<?php echo $pacote['id']; ?>)">Excluir</button>
                                </td>
                                
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12">Nenhum pacote encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-secondary">  <i class="fas fa-angle-left"></i> Voltar</a>
        </div>
        <a href="logout.php" class="btn btn-danger btn-lg mt-3">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div> 
        

    <div class="fixed-bottom toggle-footer cursor_to_down" id="footer_fixed">
        <div class="fixed-bottom border-top bg-light text-center footer-content p-2" style="z-index:4;">
            <div class="footer-text">
                Desenvolvido com &#128151; por Gerencia de Informatica - GETIN <br>
                <a class="text-reset fw-bold" href="http://www.hemopa.pa.gov.br/site/">© Todos os direitos reservados 2024 Hemopa.</a>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Senha -->
    <div class="modal fade" id="confirmPasswordModal" tabindex="-1" role="dialog" aria-labelledby="confirmPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmPasswordModalLabel">Confirmação de Senha</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="deleteForm" method="POST" action="excluir_pacotes.php">
                        <input type="hidden" name="id" id="pacoteIdToDelete">
                        <div class="form-group">
                            <label for="senha_confirmacao">Digite sua senha:</label>
                            <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.6.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function openDeleteModal(pacoteId) {
            $('#pacoteIdToDelete').val(pacoteId);
            $('#confirmPasswordModal').modal('show');
        }

        // Função para monitorar inatividade
        let inactivityTime = function () {
            let time;
            window.onload = resetTimer;
            document.onmousemove = resetTimer;
            document.onkeypress = resetTimer;
            document.onscroll = resetTimer;
            document.onclick = resetTimer;

            function logout() {
                alert("Você foi desconectado devido à inatividade.");
                window.location.href = 'logout.php';
            }

            function resetTimer() {
                clearTimeout(time);
                time = setTimeout(logout, 900000);  // Tempo em milissegundos 900000 = (15 minutos)
            }
        };

        inactivityTime();
    </script>
</body>
</html>
