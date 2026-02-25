<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    if (!can_delete()) { http_response_code(403); die('Permissão insuficiente.'); }
    $id = $_GET['id'];
    $stmt = $mysqli->prepare("DELETE FROM turmas WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: turmas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $curso_id = $_POST['curso_id'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $turno = !empty($_POST['turno']) ? $_POST['turno'] : null;
    $cidade = !empty($_POST['cidade']) ? $_POST['cidade'] : null;
    $vagas = !empty($_POST['vagas']) ? (int)$_POST['vagas'] : null;
    $horario = !empty($_POST['horario']) ? $_POST['horario'] : null;
    $dias_semana = !empty($_POST['dias_semana']) ? $_POST['dias_semana'] : null;
    $docente1 = !empty($_POST['docente1']) ? $_POST['docente1'] : null;
    $docente2 = !empty($_POST['docente2']) ? $_POST['docente2'] : null;
    $docente3 = !empty($_POST['docente3']) ? $_POST['docente3'] : null;
    $docente4 = !empty($_POST['docente4']) ? $_POST['docente4'] : null;
    $ambiente = !empty($_POST['ambiente']) ? $_POST['ambiente'] : null;
    $local_turma = !empty($_POST['local_turma']) ? $_POST['local_turma'] : null;

    if ($id) {
        $stmt = $mysqli->prepare("UPDATE turmas SET nome = ?, curso_id = ?, data_inicio = ?, data_fim = ?, turno = ?, cidade = ?, vagas = ?, horario = ?, dias_semana = ?, docente1 = ?, docente2 = ?, docente3 = ?, docente4 = ?, ambiente = ?, local_turma = ? WHERE id = ?");
        $stmt->bind_param('sissssissssssssi', $nome, $curso_id, $data_inicio, $data_fim, $turno, $cidade, $vagas, $horario, $dias_semana, $docente1, $docente2, $docente3, $docente4, $ambiente, $local_turma, $id);
        $stmt->execute();
        header("Location: turmas.php?msg=updated");
    } else {
        $stmt = $mysqli->prepare("INSERT INTO turmas (nome, curso_id, data_inicio, data_fim, turno, cidade, vagas, horario, dias_semana, docente1, docente2, docente3, docente4, ambiente, local_turma) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sissssissssssss', $nome, $curso_id, $data_inicio, $data_fim, $turno, $cidade, $vagas, $horario, $dias_semana, $docente1, $docente2, $docente3, $docente4, $ambiente, $local_turma);
        $stmt->execute();
        header("Location: turmas.php?msg=created");
    }
    exit;
}

header("Location: turmas.php");
?>
