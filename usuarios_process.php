<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Only admin and gestor can manage users
require_role(['admin', 'gestor']);

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

$nome         = trim($_POST['nome'] ?? '');
$email        = trim($_POST['email'] ?? '');
$role         = $_POST['role'] ?? 'professor';
$professor_id = ($_POST['professor_id'] ?? '') !== '' ? (int)$_POST['professor_id'] : null;

// Validate role value
$valid_roles = ['admin', 'gestor', 'professor'];
if (!in_array($role, $valid_roles)) {
    $role = 'professor';
}

// Gestor restrictions: can only create professors, cannot edit or delete
if ($auth_user_role === 'gestor') {
    if ($action === 'update') {
        http_response_code(403);
        die('Gestores não podem editar usuários.');
    }
    // Gestores can only create professor accounts
    $role = 'professor';
}

if (!$nome || !$email) {
    header('Location: usuarios.php?msg=erro_dados');
    exit;
}

if ($action === 'create') {
    // Check duplicate email
    $ck = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ?");
    $ck->bind_param('s', $email);
    $ck->execute();
    if ($ck->get_result()->fetch_row()) {
        // Email already exists — redirect with error
        header('Location: usuarios.php?msg=email_duplicado');
        exit;
    }

    $hash = password_hash('senaisp', PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare("INSERT INTO usuarios (nome, email, senha, role, professor_id, obrigar_troca_senha) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('ssssi', $nome, $email, $hash, $role, $professor_id);
    $stmt->execute();

    header('Location: usuarios.php?msg=criado');
    exit;
}

if ($action === 'update') {
    // Only admin can update
    if ($auth_user_role !== 'admin') {
        http_response_code(403);
        die('Permissão insuficiente.');
    }

    // Check duplicate email (excluding current user)
    $ck = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $ck->bind_param('si', $email, $id);
    $ck->execute();
    if ($ck->get_result()->fetch_row()) {
        header('Location: usuarios.php?msg=email_duplicado');
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE usuarios SET nome = ?, email = ?, role = ?, professor_id = ? WHERE id = ?");
    $stmt->bind_param('sssii', $nome, $email, $role, $professor_id, $id);
    $stmt->execute();

    header('Location: usuarios.php?msg=atualizado');
    exit;
}

header('Location: usuarios.php');
exit;
