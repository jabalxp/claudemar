<?php
require_once 'includes/db.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM turmas WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: turmas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $curso_id = $_POST['curso_id'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $turno = $_POST['turno'];
    $cidade = !empty($_POST['cidade']) ? $_POST['cidade'] : null;

    if ($id) {
        $stmt = $pdo->prepare("UPDATE turmas SET nome = ?, curso_id = ?, data_inicio = ?, data_fim = ?, turno = ?, cidade = ? WHERE id = ?");
        $stmt->execute([$nome, $curso_id, $data_inicio, $data_fim, $turno, $cidade, $id]);
        header("Location: turmas.php?msg=updated");
    } else {
        $stmt = $pdo->prepare("INSERT INTO turmas (nome, curso_id, data_inicio, data_fim, turno, cidade) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $curso_id, $data_inicio, $data_fim, $turno, $cidade]);
        header("Location: turmas.php?msg=created");
    }
    exit;
}

header("Location: turmas.php");
?>
