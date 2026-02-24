<?php
require_once 'includes/db.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM professores WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: professores.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $especialidade = $_POST['especialidade'];
    $email = $_POST['email'];
    $cor_agenda = $_POST['cor_agenda'];

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE professores SET nome = ?, especialidade = ?, email = ?, cor_agenda = ? WHERE id = ?");
        $stmt->execute([$nome, $especialidade, $email, $cor_agenda, $id]);
        header("Location: professores.php?msg=updated");
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO professores (nome, especialidade, email, cor_agenda) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $especialidade, $email, $cor_agenda]);
        header("Location: professores.php?msg=created");
    }
    exit;
}

header("Location: professores.php");
?>
