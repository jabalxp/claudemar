<?php
require_once 'includes/db.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM salas WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: salas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $tipo = $_POST['tipo'];
    $capacidade = $_POST['capacidade'];

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE salas SET nome = ?, tipo = ?, capacidade = ? WHERE id = ?");
        $stmt->execute([$nome, $tipo, $capacidade, $id]);
        header("Location: salas.php?msg=updated");
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO salas (nome, tipo, capacidade) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $tipo, $capacidade]);
        header("Location: salas.php?msg=created");
    }
    exit;
}

header("Location: salas.php");
?>
