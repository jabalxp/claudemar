<?php 
require_once 'includes/db.php';
include 'includes/header.php'; 

// Fetch stats
$count_prof = $pdo->query("SELECT COUNT(*) FROM professores")->fetchColumn();
$count_salas = $pdo->query("SELECT COUNT(*) FROM salas")->fetchColumn();
$count_turmas = $pdo->query("SELECT COUNT(*) FROM turmas")->fetchColumn();
$count_aulas = $pdo->query("SELECT COUNT(*) FROM agenda")->fetchColumn();
$count_cursos = $pdo->query("SELECT COUNT(*) FROM cursos")->fetchColumn();

// Fetch distinct cities for filter
$stmt_cidades = $pdo->query("SELECT DISTINCT cidade FROM turmas WHERE cidade IS NOT NULL AND cidade != '' ORDER BY cidade ASC");
$cidades = $stmt_cidades->fetchAll(PDO::FETCH_COLUMN);

// City filter
$filtro_cidade = isset($_GET['cidade']) ? $_GET['cidade'] : '';

// Fetch today's classes with optional city filter
$today = date('Y-m-d');
if ($filtro_cidade) {
    $stmt_today = $pdo->prepare("
        SELECT a.*, t.nome as turma_nome, t.cidade, p.nome as professor_nome, s.nome as sala_nome 
        FROM agenda a 
        JOIN turmas t ON a.turma_id = t.id 
        JOIN professores p ON a.professor_id = p.id 
        JOIN salas s ON a.sala_id = s.id 
        WHERE a.data = ? AND t.cidade = ?
        ORDER BY a.hora_inicio ASC
    ");
    $stmt_today->execute([$today, $filtro_cidade]);
} else {
    $stmt_today = $pdo->prepare("
        SELECT a.*, t.nome as turma_nome, t.cidade, p.nome as professor_nome, s.nome as sala_nome 
        FROM agenda a 
        JOIN turmas t ON a.turma_id = t.id 
        JOIN professores p ON a.professor_id = p.id 
        JOIN salas s ON a.sala_id = s.id 
        WHERE a.data = ? 
        ORDER BY a.hora_inicio ASC
    ");
    $stmt_today->execute([$today]);
}
$aulas_hoje = $stmt_today->fetchAll();

// Turmas by city count
$stmt_turmas_cidade = $pdo->query("SELECT COALESCE(cidade, 'Sede') as cidade, COUNT(*) as total FROM turmas GROUP BY COALESCE(cidade, 'Sede') ORDER BY total DESC");
$turmas_por_cidade = $stmt_turmas_cidade->fetchAll();

// Upcoming turmas (next 5 turmas starting)
$stmt_proximas = $pdo->prepare("
    SELECT t.nome, t.cidade, c.nome as curso_nome, t.data_inicio, t.turno 
    FROM turmas t 
    JOIN cursos c ON t.curso_id = c.id 
    WHERE t.data_inicio >= ? 
    ORDER BY t.data_inicio ASC 
    LIMIT 5
");
$stmt_proximas->execute([$today]);
$proximas_turmas = $stmt_proximas->fetchAll();
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: var(--card-bg);
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 15px;
    }
    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 4px;
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        font-weight: 600;
    }
    .stat-card .stat-link {
        display: inline-block;
        margin-top: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--primary-red);
        text-decoration: none;
    }
    .stat-card .stat-link:hover {
        text-decoration: underline;
    }
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 80px;
        height: 80px;
        border-radius: 0 16px 0 80px;
        opacity: 0.08;
    }
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    @media (max-width: 900px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    .dash-section {
        background: var(--card-bg);
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    .dash-section-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .dash-section-header h3 {
        font-size: 1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dash-section-body {
        padding: 20px 24px;
    }
    .filter-bar {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 16px 24px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    .filter-bar label {
        font-weight: 700;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .filter-bar select {
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-color);
        color: var(--text-color);
        font-weight: 600;
        min-width: 180px;
    }
    .city-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
    }
    .city-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
    }
    .city-list-item:last-child {
        border-bottom: none;
    }
    .city-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }
    .export-bar {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .btn-export {
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-color);
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.2s;
    }
    .btn-export:hover {
        background: var(--bg-color);
        border-color: var(--primary-red);
        color: var(--primary-red);
    }
    .btn-export-excel {
        background: #217346;
        color: #fff;
        border-color: #1a5c38;
    }
    .btn-export-excel:hover {
        background: #1a5c38;
        color: #fff;
    }
    .welcome-banner {
        background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
        color: #fff;
        border-radius: 16px;
        padding: 30px 35px;
        margin-bottom: 25px;
        position: relative;
        overflow: hidden;
    }
    .welcome-banner h2 {
        font-size: 1.5rem;
        margin-bottom: 6px;
    }
    .welcome-banner p {
        opacity: 0.9;
        font-size: 0.95rem;
    }
    .welcome-banner .welcome-date {
        position: absolute;
        top: 30px;
        right: 35px;
        background: rgba(255,255,255,0.18);
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9rem;
    }
</style>

<div class="dashboard-home">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <h2><i class="fas fa-chart-line"></i> Dashboard — Gestão Escolar SENAI</h2>
        <p>Visão geral do sistema com turmas, professores, ambientes e agenda.</p>
        <div class="welcome-date"><i class="fas fa-calendar-day"></i> <?php echo date('d/m/Y'); ?></div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(237,28,36,0.12); color: #e53935;">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-number"><?php echo $count_prof; ?></div>
            <div class="stat-label">Professores</div>
            <a href="professores.php" class="stat-link">Gerenciar <i class="fas fa-arrow-right"></i></a>
            <div style="position:absolute;top:0;right:0;width:80px;height:80px;border-radius:0 16px 0 80px;background:#e53935;opacity:0.07;"></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(25,118,210,0.12); color: #1976d2;">
                <i class="fas fa-door-open"></i>
            </div>
            <div class="stat-number"><?php echo $count_salas; ?></div>
            <div class="stat-label">Ambientes</div>
            <a href="salas.php" class="stat-link">Gerenciar <i class="fas fa-arrow-right"></i></a>
            <div style="position:absolute;top:0;right:0;width:80px;height:80px;border-radius:0 16px 0 80px;background:#1976d2;opacity:0.07;"></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(56,142,60,0.12); color: #388e3c;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo $count_turmas; ?></div>
            <div class="stat-label">Turmas</div>
            <a href="turmas.php" class="stat-link">Gerenciar <i class="fas fa-arrow-right"></i></a>
            <div style="position:absolute;top:0;right:0;width:80px;height:80px;border-radius:0 16px 0 80px;background:#388e3c;opacity:0.07;"></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(255,143,0,0.12); color: #ff8f00;">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="stat-number"><?php echo $count_cursos; ?></div>
            <div class="stat-label">Cursos</div>
            <a href="cursos.php" class="stat-link">Gerenciar <i class="fas fa-arrow-right"></i></a>
            <div style="position:absolute;top:0;right:0;width:80px;height:80px;border-radius:0 16px 0 80px;background:#ff8f00;opacity:0.07;"></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(156,39,176,0.12); color: #9c27b0;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-number"><?php echo $count_aulas; ?></div>
            <div class="stat-label">Aulas Agendadas</div>
            <a href="planejamento.php" class="stat-link">Novo Planejamento <i class="fas fa-arrow-right"></i></a>
            <div style="position:absolute;top:0;right:0;width:80px;height:80px;border-radius:0 16px 0 80px;background:#9c27b0;opacity:0.07;"></div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <label><i class="fas fa-filter"></i> Filtrar por Cidade:</label>
        <form method="GET" action="index.php" style="display: flex; gap: 10px; align-items: center;">
            <select name="cidade" onchange="this.form.submit()">
                <option value="">Todas as Cidades</option>
                <?php foreach ($cidades as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filtro_cidade == $c ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filtro_cidade): ?>
                <a href="index.php" class="btn-export" style="font-size: 0.8rem; padding: 6px 12px;"><i class="fas fa-times"></i> Limpar</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Exportar Turmas:</span>
            <a href="export_excel.php?tipo=excel" class="btn-export btn-export-excel" title="Planilha Excel formatada (.xls)">
                <i class="fas fa-file-excel"></i> Excel (.xls)
            </a>
            <a href="export_excel.php?tipo=powerbi" class="btn-export" style="background:#f2c811;color:#000;border-color:#d4a800;" title="CSV otimizado para importar no Power BI Desktop">
                <i class="fas fa-chart-bar"></i> Power BI (.csv)
            </a>
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); white-space: nowrap; margin-left: 6px;">Agenda:</span>
            <a href="export_excel.php?tipo=agenda" class="btn-export" title="Agenda completa — Excel formatado (.xls)">
                <i class="fas fa-calendar-check"></i> Excel (.xls)
            </a>
            <a href="export_excel.php?tipo=agenda_powerbi" class="btn-export" style="background:#f2c811;color:#000;border-color:#d4a800;" title="Agenda completa para Power BI Desktop">
                <i class="fas fa-chart-bar"></i> Power BI (.csv)
            </a>
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Today's Classes -->
        <div class="dash-section">
            <div class="dash-section-header">
                <h3><i class="fas fa-clock" style="color: var(--primary-red);"></i> Aulas de Hoje — <?php echo date('d/m/Y'); ?>
                    <?php if ($filtro_cidade): ?>
                        <span class="city-badge" style="background: #e3f2fd; color: #1565c0;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($filtro_cidade); ?></span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="dash-section-body" style="padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 12px 20px; background: var(--bg-color); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Horário</th>
                            <th style="padding: 12px 20px; background: var(--bg-color); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Turma</th>
                            <th style="padding: 12px 20px; background: var(--bg-color); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Professor</th>
                            <th style="padding: 12px 20px; background: var(--bg-color); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Sala</th>
                            <th style="padding: 12px 20px; background: var(--bg-color); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Cidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($aulas_hoje)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-calendar-times" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.4;"></i>
                                    Não há aulas agendadas para hoje<?php echo $filtro_cidade ? ' em ' . htmlspecialchars($filtro_cidade) : ''; ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($aulas_hoje as $a): ?>
                            <tr>
                                <td style="padding: 12px 20px; font-weight: 800; color: var(--primary-red);"><?php echo substr($a['hora_inicio'], 0, 5); ?> - <?php echo substr($a['hora_fim'], 0, 5); ?></td>
                                <td style="padding: 12px 20px;"><strong><?php echo htmlspecialchars($a['turma_nome']); ?></strong></td>
                                <td style="padding: 12px 20px;"><?php echo htmlspecialchars($a['professor_nome']); ?></td>
                                <td style="padding: 12px 20px;"><span style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem;"><?php echo htmlspecialchars($a['sala_nome']); ?></span></td>
                                <td style="padding: 12px 20px;">
                                    <?php if (!empty($a['cidade'])): ?>
                                        <span class="city-badge" style="background: #e8f5e9; color: #2e7d32;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($a['cidade']); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">Sede</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar: Cities & Upcoming -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <!-- Turmas by City -->
            <div class="dash-section">
                <div class="dash-section-header">
                    <h3><i class="fas fa-map-marked-alt" style="color: #1976d2;"></i> Turmas por Cidade</h3>
                </div>
                <div class="dash-section-body">
                    <?php if (empty($turmas_por_cidade)): ?>
                        <p style="color: var(--text-muted); text-align: center;">Nenhuma turma cadastrada.</p>
                    <?php else: ?>
                        <?php 
                        $colors = ['#e53935','#1976d2','#388e3c','#ff8f00','#9c27b0','#00838f','#6d4c41'];
                        $i = 0;
                        foreach ($turmas_por_cidade as $tc): 
                            $color = $colors[$i % count($colors)];
                        ?>
                        <div class="city-list-item">
                            <div style="display: flex; align-items: center;">
                                <span class="city-dot" style="background: <?php echo $color; ?>;"></span>
                                <span style="font-weight: 600;"><?php echo htmlspecialchars($tc['cidade']); ?></span>
                            </div>
                            <span style="font-weight: 800; font-size: 1.1rem; color: <?php echo $color; ?>;"><?php echo $tc['total']; ?></span>
                        </div>
                        <?php $i++; endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Classes -->
            <div class="dash-section">
                <div class="dash-section-header">
                    <h3><i class="fas fa-rocket" style="color: #ff8f00;"></i> Próximas Turmas</h3>
                </div>
                <div class="dash-section-body">
                    <?php if (empty($proximas_turmas)): ?>
                        <p style="color: var(--text-muted); text-align: center;">Nenhuma turma futura.</p>
                    <?php else: ?>
                        <?php foreach ($proximas_turmas as $pt): ?>
                        <div class="city-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                            <div style="font-weight: 700; font-size: 0.9rem;"><?php echo htmlspecialchars($pt['nome']); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                <?php echo htmlspecialchars($pt['curso_nome']); ?> · <?php echo $pt['turno']; ?>
                                <?php if (!empty($pt['cidade'])): ?>
                                    · <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pt['cidade']); ?>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--primary-red); font-weight: 600;">
                                <i class="fas fa-calendar"></i> Início: <?php echo date('d/m/Y', strtotime($pt['data_inicio'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
