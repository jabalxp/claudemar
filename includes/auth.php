<?php
/**
 * Authentication & Authorization Middleware
 * Include this file AFTER db.php in every protected page.
 *
 * Usage:
 *   require_once 'includes/auth.php';                // Requires login (any role)
 *   require_role(['admin']);                           // Only admin
 *   require_role(['admin', 'gestor']);                 // Admin or gestor
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Check if user is authenticated ──
if (!isset($_SESSION['user_id'])) {
    // For AJAX requests, return 401 JSON
    if (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || isset($_GET['ajax_availability'])
    ) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// ── Load user data into convenient variables ──
$auth_user_id    = (int)$_SESSION['user_id'];
$auth_user_nome  = $_SESSION['user_nome']  ?? '';
$auth_user_email = $_SESSION['user_email'] ?? '';
$auth_user_role  = $_SESSION['user_role']  ?? 'professor';
$auth_obrigar_troca = (int)($_SESSION['obrigar_troca_senha'] ?? 0);

// ── If user must change password, redirect everywhere except the change-password endpoint ──
$current_script = basename($_SERVER['SCRIPT_NAME']);
$allowed_during_troca = ['trocar_senha.php', 'logout.php'];
if ($auth_obrigar_troca && !in_array($current_script, $allowed_during_troca)) {
    // For AJAX, return JSON
    if (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || isset($_GET['ajax_availability'])
    ) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Troca de senha obrigatória']);
        exit;
    }
    header('Location: trocar_senha.php');
    exit;
}

// ── Professor redirect: if professor tries to access anything other than their agenda ──
if ($auth_user_role === 'professor') {
    $professor_allowed = ['minha_agenda.php', 'logout.php', 'trocar_senha.php'];
    // Also allow agenda_professores AJAX for their own data
    if (!in_array($current_script, $professor_allowed)) {
        if (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || isset($_GET['ajax_availability'])
        ) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
        header('Location: minha_agenda.php');
        exit;
    }
}

/**
 * Require specific role(s). Call after including auth.php.
 * @param array $roles e.g. ['admin'] or ['admin', 'gestor']
 */
function require_role(array $roles)
{
    global $auth_user_role;
    if (!in_array($auth_user_role, $roles)) {
        if (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        ) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Permissão insuficiente']);
            exit;
        }
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 - Acesso Negado</h1><p>Você não tem permissão para acessar esta página.</p><a href="index.php">Voltar</a></body></html>';
        exit;
    }
}

/**
 * Check if current user can delete records.
 * Only admin can delete.
 */
function can_delete(): bool
{
    global $auth_user_role;
    return $auth_user_role === 'admin';
}

/**
 * Check if current user can create/edit records.
 * Admin and gestor can create/edit.
 */
function can_edit(): bool
{
    global $auth_user_role;
    return in_array($auth_user_role, ['admin', 'gestor']);
}
?>
