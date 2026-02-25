<?php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? redirect
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['obrigar_troca_senha'] ?? 0)) {
        header('Location: trocar_senha.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!$email || !$senha) {
        $error = 'Preencha todos os campos.';
    } else {
        $stmt = $mysqli->prepare("SELECT id, nome, email, senha, role, obrigar_troca_senha, professor_id FROM usuarios WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($senha, $user['senha'])) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']             = (int)$user['id'];
            $_SESSION['user_nome']           = $user['nome'];
            $_SESSION['user_email']          = $user['email'];
            $_SESSION['user_role']           = $user['role'];
            $_SESSION['professor_id']        = $user['professor_id'] ? (int)$user['professor_id'] : null;
            $_SESSION['obrigar_troca_senha'] = (int)$user['obrigar_troca_senha'];

            if ($user['obrigar_troca_senha']) {
                header('Location: trocar_senha.php');
            } elseif ($user['role'] === 'professor') {
                header('Location: minha_agenda.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'E-mail ou senha inválidos.';
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
    <title>SENAI | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: var(--bg-color, #f4f6f9);
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        .login-card {
            background: var(--card-bg, #fff);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px 35px;
            text-align: center;
        }
        .login-card .logo {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-red, #ed1c24);
            margin-bottom: 5px;
        }
        .login-card .subtitle {
            font-size: 0.85rem;
            color: var(--text-muted, #6c757d);
            margin-bottom: 30px;
        }
        .login-card .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        .login-card label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: var(--text-color, #333);
        }
        .login-card input[type="email"],
        .login-card input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 10px;
            font-size: 0.95rem;
            background: var(--bg-color, #f4f6f9);
            color: var(--text-color, #333);
            transition: border 0.2s;
            box-sizing: border-box;
        }
        .login-card input:focus {
            outline: none;
            border-color: var(--primary-red, #ed1c24);
        }
        .login-card .btn-login {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            background: var(--primary-red, #ed1c24);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        .login-card .btn-login:hover {
            background: #c41820;
        }
        .error-msg {
            background: #fff3f3;
            color: #d32f2f;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 0.85rem;
            border: 1px solid #ffcdd2;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">SENAI</div>
            <div class="subtitle">Gestão Escolar — Acesso ao Sistema</div>

            <?php if ($error): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="senha"><i class="fas fa-lock"></i> Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
        </div>
    </div>
    <script src="assets/js/theme.js"></script>
</body>
</html>
