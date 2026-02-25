<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    if (!can_delete()) { http_response_code(403); die('Permissão insuficiente.'); }
    $id = $_GET['id'];
    $stmt = $mysqli->prepare("DELETE FROM salas WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: salas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $tipo = $_POST['tipo'];
    $capacidade = $_POST['capacidade'];
    $area = !empty($_POST['area']) ? $_POST['area'] : null;
    $cidade = !empty($_POST['cidade']) ? $_POST['cidade'] : null;

    if ($id) {
        // Update
        $stmt = $mysqli->prepare("UPDATE salas SET nome = ?, tipo = ?, capacidade = ?, area = ?, cidade = ? WHERE id = ?");
        $stmt->bind_param('ssissi', $nome, $tipo, $capacidade, $area, $cidade, $id);
        $stmt->execute();
        header("Location: salas.php?msg=updated");
    } else {
        // Insert
        $stmt = $mysqli->prepare("INSERT INTO salas (nome, tipo, capacidade, area, cidade) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssiss', $nome, $tipo, $capacidade, $area, $cidade);
        $stmt->execute();
        header("Location: salas.php?msg=created");
    }
    exit;
}

header("Location: salas.php");
?>
