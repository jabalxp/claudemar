<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    if (!can_delete()) { http_response_code(403); die('Permissão insuficiente.'); }
    $id = $_GET['id'];
    $stmt = $mysqli->prepare("DELETE FROM cursos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: cursos.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $carga_horaria = $_POST['carga_horaria'];
    $tipo = !empty($_POST['tipo']) ? $_POST['tipo'] : null;
    $area = !empty($_POST['area']) ? $_POST['area'] : null;

    if ($id) {
        $stmt = $mysqli->prepare("UPDATE cursos SET nome = ?, carga_horaria = ?, tipo = ?, area = ? WHERE id = ?");
        $stmt->bind_param('sissi', $nome, $carga_horaria, $tipo, $area, $id);
        $stmt->execute();
        header("Location: cursos.php?msg=updated");
    } else {
        $stmt = $mysqli->prepare("INSERT INTO cursos (nome, carga_horaria, tipo, area) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('siss', $nome, $carga_horaria, $tipo, $area);
        $stmt->execute();
        header("Location: cursos.php?msg=created");
    }
    exit;
}

header("Location: cursos.php");
?>
