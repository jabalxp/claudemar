<?php
require_once 'includes/db.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: cursos.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $carga_horaria = $_POST['carga_horaria'];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE cursos SET nome = ?, carga_horaria = ? WHERE id = ?");
        $stmt->execute([$nome, $carga_horaria, $id]);
        header("Location: cursos.php?msg=updated");
    } else {
        $stmt = $pdo->prepare("INSERT INTO cursos (nome, carga_horaria) VALUES (?, ?)");
        $stmt->execute([$nome, $carga_horaria]);
        header("Location: cursos.php?msg=created");
    }
    exit;
}

header("Location: cursos.php");
?>
