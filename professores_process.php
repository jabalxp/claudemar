<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    if (!can_delete()) { http_response_code(403); die('Permissão insuficiente.'); }
    $id = $_GET['id'];
    $stmt = $mysqli->prepare("DELETE FROM professores WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: professores.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $especialidade = $_POST['especialidade'];
    $email = $_POST['email'];
    $cor_agenda = $_POST['cor_agenda'];
    $chc = (int)($_POST['carga_horaria_contratual'] ?? 0);
    $cidade = !empty($_POST['cidade']) ? $_POST['cidade'] : null;

    if ($id) {
        // Update
        $stmt = $mysqli->prepare("UPDATE professores SET nome = ?, especialidade = ?, email = ?, cor_agenda = ?, carga_horaria_contratual = ?, cidade = ? WHERE id = ?");
        $stmt->bind_param('ssssisi', $nome, $especialidade, $email, $cor_agenda, $chc, $cidade, $id);
        $stmt->execute();
        header("Location: professores.php?msg=updated");
    }
    else {
        // Insert
        $stmt = $mysqli->prepare("INSERT INTO professores (nome, especialidade, email, cor_agenda, carga_horaria_contratual, cidade) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssis', $nome, $especialidade, $email, $cor_agenda, $chc, $cidade);
        $stmt->execute();
        header("Location: professores.php?msg=created");
    }
    exit;
}

header("Location: professores.php");
?>
