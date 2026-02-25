<?php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$auth_user_id   = (int)$_SESSION['user_id'];
$auth_user_nome = $_SESSION['user_nome'] ?? '';
$auth_role      = $_SESSION['user_role'] ?? 'professor';
$must_change    = (int)($_SESSION['obrigar_troca_senha'] ?? 0);

// Professor e Gestor só podem trocar senha quando o admin resetou (obrigar_troca_senha = 1)
if (!$must_change && in_array($auth_role, ['professor', 'gestor'])) {
    header('Location: ' . ($auth_role === 'professor' ? 'minha_agenda.php' : 'index.php'));
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha   = $_POST['nova_senha'] ?? '';
    $confirma     = $_POST['confirma_senha'] ?? '';

    // Validations
    if (strlen($nova_senha) < 6) {
        $error = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($nova_senha !== $confirma) {
        $error = 'As senhas não coincidem.';
    } elseif (strtolower($nova_senha) === 'senaisp') {
        $error = 'Você não pode usar a senha padrão. Escolha uma senha diferente.';
    } else {
        $hash = password_hash($nova_senha, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare("UPDATE usuarios SET senha = ?, obrigar_troca_senha = 0 WHERE id = ?");
        $stmt->bind_param('si', $hash, $auth_user_id);
        $stmt->execute();

        $_SESSION['obrigar_troca_senha'] = 0;

        $success = 'Senha alterada com sucesso!';

        // If it was a forced change, redirect after a moment
        if ($must_change) {
            $role = $_SESSION['user_role'] ?? 'professor';
            if ($role === 'professor') {
                header('Location: minha_agenda.php');
            } else {
                header('Location: index.php');
            }
            exit;
        }
    }
}

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENAI | Trocar Senha</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; margin: 0; background: var(--bg-color, #f4f6f9);
        }
        .change-card {
            background: var(--card-bg, #fff); border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px 35px; text-align: center; width: 100%; max-width: 420px;
        }
        .change-card .logo { font-size: 2rem; font-weight: 800; color: var(--primary-red, #ed1c24); margin-bottom: 5px; }
        .change-card .subtitle { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 25px; }
        .change-card .form-group { margin-bottom: 16px; text-align: left; }
        .change-card label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; }
        .change-card input[type="password"] {
            width: 100%; padding: 12px 14px; border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 10px; font-size: 0.95rem; background: var(--bg-color, #f4f6f9);
            color: var(--text-color); box-sizing: border-box;
        }
        .change-card input:focus { outline: none; border-color: var(--primary-red); }
        .btn-save {
            width: 100%; padding: 13px; border: none; border-radius: 10px;
            background: var(--primary-red, #ed1c24); color: #fff;
            font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 10px;
        }
        .btn-save:hover { background: #c41820; }
        .error-msg { background: #fff3f3; color: #d32f2f; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; border: 1px solid #ffcdd2; }
        .success-msg { background: #f0fff4; color: #2e7d32; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; border: 1px solid #c8e6c9; }
        .warning-box { background: #fff8e1; color: #e65100; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.82rem; border: 1px solid #ffe082; }
        .password-rules { font-size: 0.78rem; color: var(--text-muted); margin-top: 8px; text-align: left; }
    </style>
</head>
<body>
    <div class="change-card">
        <div class="logo">SENAI</div>
        <div class="subtitle">Olá, <?php echo htmlspecialchars($auth_user_nome); ?></div>

        <?php if ($must_change): ?>
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Troca de senha obrigatória.</strong><br>
                Você está usando a senha padrão. Por segurança, defina uma nova senha.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-msg"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-key"></i> Nova Senha</label>
                <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres" required minlength="6">
            </div>
            <div class="form-group">
                <label><i class="fas fa-key"></i> Confirmar Nova Senha</label>
                <input type="password" name="confirma_senha" placeholder="Repita a nova senha" required minlength="6">
            </div>
            <div class="password-rules">
                <i class="fas fa-info-circle"></i> Mínimo de 6 caracteres. Não pode ser "senaisp".
            </div>
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Salvar Nova Senha</button>
        </form>

        <?php if (!$must_change): ?>
            <div style="margin-top: 15px;">
                <a href="index.php" style="color: var(--text-muted); font-size: 0.85rem;">
                    <i class="fas fa-arrow-left"></i> Voltar ao sistema
                </a>
            </div>
        <?php endif; ?>
    </div>
    <script src="assets/js/theme.js"></script>
</body>
</html>
