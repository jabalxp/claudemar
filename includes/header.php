<?php
$current_page = basename($_SERVER['PHP_SELF']);
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENAI | Gestão Escolar</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>SENAI</h2>
                <p style="font-size: 0.8rem; opacity: 0.8;">Gestão Escolar</p>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="professores.php" class="<?php echo $current_page == 'professores.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i> Professores
                    </a>
                </li>
                <li>
                    <a href="salas.php" class="<?php echo $current_page == 'salas.php' ? 'active' : ''; ?>">
                        <i class="fas fa-door-open"></i> Ambientes
                    </a>
                </li>
                <li>
                    <a href="cursos.php" class="<?php echo $current_page == 'cursos.php' ? 'active' : ''; ?>">
                        <i class="fas fa-graduation-cap"></i> Cursos
                    </a>
                </li>
                <li>
                    <a href="turmas.php" class="<?php echo $current_page == 'turmas.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Turmas
                    </a>
                </li>
                <li>
                    <a href="planejamento.php" class="<?php echo $current_page == 'planejamento.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i> Planejamento
                    </a>
                </li>
                <li style="margin-top: 15px; padding: 0 20px; font-size: 0.7rem; text-transform: uppercase; opacity: 0.6;">Visualizações</li>
                <li>
                    <a href="agenda_professores.php" class="<?php echo $current_page == 'agenda_professores.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Agenda Professores
                    </a>
                </li>
                <li>
                    <a href="agenda_salas.php" class="<?php echo $current_page == 'agenda_salas.php' ? 'active' : ''; ?>">
                        <i class="fas fa-door-open"></i> Agenda Salas
                    </a>
                </li>
                <li style="margin-top: 15px; padding: 0 20px; font-size: 0.7rem; text-transform: uppercase; opacity: 0.6;">Ferramentas</li>
                <li>
                    <a href="import_excel.php" class="<?php echo $current_page == 'import_excel.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-import"></i> Importar Excel
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <h3 id="page-title">Bem-vindo, Administrador</h3>
                </div>
                <div class="header-user">
                    <button id="theme-toggle" title="Alternar Tema">
                        <i class="fas <?php echo $theme == 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <span>Olá, <strong>Beneguiguiris</strong></span>
                    <div class="avatar" style="width: 35px; height: 35px; border-radius: 50%; background: #ccc; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </header>
            
            <div class="content-wrapper">
