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
}
else {
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

    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
    .modal-content { background: var(--card-bg); margin: 3% auto; padding: 35px; border-radius: 20px; box-shadow: 0 10px 50px rgba(0,0,0,0.3); border: 1px solid var(--border-color); position: relative; }
    .close-modal { position: absolute; top: 20px; right: 25px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); transition: color 0.2s; }
    .close-modal:hover { color: var(--primary-red); }
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
                <?php
endforeach; ?>
            </select>
            <?php if ($filtro_cidade): ?>
                <a href="index.php" class="btn-export" style="font-size: 0.8rem; padding: 6px 12px;"><i class="fas fa-times"></i> Limpar</a>
            <?php
endif; ?>
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
        <!-- Resumo de Disponibilidade dos Professores -->
        <div class="dash-section" id="availability_section">
            <div class="dash-section-header" style="flex-wrap: wrap; gap: 15px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <h3><i class="fas fa-chart-line" style="color: var(--primary-red);"></i> Análise de Desempenho</h3>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; margin-left: auto;">
                    <button onclick="openAnalyticModal()" class="btn-export" style="background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color); cursor: pointer;">
                        <i class="fas fa-expand-arrows-alt"></i> <span>Análise Detalhada</span>
                    </button>
                    <a href="agenda_professores.php" class="btn-export" style="background: var(--primary-red); color: #fff; border: none;">
                        <i class="fas fa-calendar-alt"></i> Agenda Completa
                    </a>
                </div>
            </div>
            <div class="dash-section-body" style="padding: 0;">
                <?php
// Lógica de Disponibilidade
$start_month = date('Y-m-01');
$end_month = date('Y-m-t');

// Contar dias úteis (Seg-Sáb) no mês atual
$total_util = 0;
$current_date_calc = strtotime($start_month);
$end_date_calc = strtotime($end_month);
while ($current_date_calc <= $end_date_calc) {
    if (date('N', $current_date_calc) < 7) { // 1 (Seg) a 6 (Sáb)
        $total_util++;
    }
    $current_date_calc = strtotime("+1 day", $current_date_calc);
}

// Buscar ocupação dos professores
$stmt_disp = $pdo->prepare("
                    SELECT p.id, p.nome, p.especialidade, COUNT(a.id) as dias_ocupados
                    FROM professores p
                    LEFT JOIN agenda a ON p.id = a.professor_id AND a.data BETWEEN ? AND ?
                    GROUP BY p.id
                ");
$stmt_disp->execute([$start_month, $end_month]);
$prof_availability = $stmt_disp->fetchAll();

// Calcular porcentagem
foreach ($prof_availability as &$p) {
    $livre = max(0, $total_util - $p['dias_ocupados']);
    $p['perc_livre'] = ($total_util > 0) ? ($livre / $total_util) * 100 : 0;
    $p['dias_livres'] = $livre;
}

// Ordenar
$ordem = isset($_GET['ordem_disp']) ? $_GET['ordem_disp'] : 'mais';
usort($prof_availability, function ($a, $b) use ($ordem) {
    return $ordem == 'mais' ? $b['perc_livre'] <=> $a['perc_livre'] : $a['perc_livre'] <=> $b['perc_livre'];
});

// Top 10
$prof_availability = array_slice($prof_availability, 0, 10);
?>
                <?php
// Buscar agenda detalhada para o Gantt (Top 10 professores selecionados)
$prof_ids_gantt = array_column($prof_availability, 'id');
$agenda_gantt = [];
if (!empty($prof_ids_gantt)) {
    $placeholders = implode(',', array_fill(0, count($prof_ids_gantt), '?'));
    $stmt_agenda = $pdo->prepare("
        SELECT a.data, a.professor_id, t.nome as turma_nome
        FROM agenda a
        JOIN turmas t ON a.turma_id = t.id
        WHERE a.professor_id IN ($placeholders) AND a.data BETWEEN ? AND ?
    ");
    $stmt_agenda->execute(array_merge($prof_ids_gantt, [$start_month, $end_month]));
    while ($row = $stmt_agenda->fetch()) {
        $agenda_gantt[$row['professor_id']][$row['data']] = $row['turma_nome'];
    }
}

$days_in_month = (int)date('t');
?>
                <style>
                    .dash-gantt-container { padding: 20px; overflow-x: auto; background: var(--bg-color); border-radius: 0 0 16px 16px; }
                    .dash-gantt-row { display: flex; align-items: center; margin-bottom: 12px; gap: 15px; }
                    .dash-gantt-label { width: 140px; font-weight: 700; font-size: 0.85rem; color: var(--text-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
                    .dash-gantt-label:hover { color: var(--primary-red); }
                    .dash-gantt-bar { 
                        flex: 1; height: 32px; display: flex; border-radius: 6px; overflow: hidden; 
                        border: 1px solid var(--border-color); background: var(--card-bg);
                        background-image: linear-gradient(to right, rgba(0,0,0,0.03) 1px, transparent 1px);
                        background-size: calc(100% / <?php echo $days_in_month; ?>) 100%;
                        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
                    }
                    .gantt-seg { flex: 1; height: 100%; position: relative; cursor: help; border-right: 1px solid rgba(0,0,0,0.02); }
                    .gantt-seg-busy { background: #d32f2f; }
                    .gantt-seg-free { background: #2e7d32; }
                    .gantt-seg-weekend { opacity: 0.6; filter: saturate(0.5); }
                    .dash-gantt-days { display: flex; margin-left: 155px; margin-bottom: 5px; border-bottom: 1px solid var(--border-color); }
                    .gantt-day-num { flex: 1; text-align: center; font-size: 0.6rem; font-weight: 700; opacity: 0.5; }

                    .dash-chart-container { display: none; padding: 30px; min-height: 350px; align-items: center; justify-content: center; }
                    .dash-chart-container.active { display: flex; }
                </style>

                <div id="dash_view_gantt" class="dash-chart-container active" style="flex-direction: column; align-items: stretch; justify-content: flex-start; padding: 20px; display: flex;">
                    <div class="dash-gantt-container" style="background: transparent; padding: 0;">
                        <div id="gantt_rows_container">
                            <?php if (empty($prof_availability)): ?>
                                <p style="text-align:center; padding: 20px; opacity:0.5;">Nenhum professor encontrado.</p>
                            <?php
else: ?>
                                <?php foreach (array_slice($prof_availability, 0, 5) as $pa): ?>
                                    <div class="dash-gantt-row" style="margin-bottom: 8px;">
                                        <div class="dash-gantt-label" style="width: 110px; font-size: 0.75rem;"><?php echo htmlspecialchars($pa['nome']); ?></div>
                                        <div class="dash-gantt-bar" style="height: 24px;">
                                            <?php for ($i = 1; $i <= $days_in_month; $i++):
            $dt = sprintf("%s-%02d", date('Y-m'), $i);
            $is_busy = $agenda_gantt[$pa['id']][$dt] ?? false;
            $class = $is_busy ? 'gantt-seg-busy' : 'gantt-seg-free';
?>
                                                <div class="gantt-seg <?php echo $class; ?>"></div>
                                            <?php
        endfor; ?>
                                        </div>
                                    </div>
                                <?php
    endforeach; ?>
                            <?php
endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="analyticModal" class="modal" style="display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);">
            <div class="modal-content" style="max-width: 95%; width: 1400px; height: 90vh; display: flex; flex-direction: column; padding: 0; overflow: hidden; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: 0 20px 50px rgba(0,0,0,0.3);">
                <div class="modal-header" style="padding: 20px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--card-bg);">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-weight: 800; color: var(--primary-red);">
                            <i class="fas fa-chart-line"></i> Análise de Desempenho
                        </h2>
                        <div class="view-selector" style="background: var(--bg-color); padding: 4px; border-radius: 12px; border: 1px solid var(--border-color);">
                            <button onclick="switchModalChart('gantt')" class="view-btn active" id="modal_btn_gantt" style="border: none; border-radius: 8px;"><i class="fas fa-align-left"></i> Gantt</button>
                            <button onclick="switchModalChart('donut')" class="view-btn" id="modal_btn_donut" style="border: none; border-radius: 8px;"><i class="fas fa-chart-pie"></i> Rosca</button>
                            <button onclick="switchModalChart('bar')" class="view-btn" id="modal_btn_bar" style="border: none; border-radius: 8px;"><i class="fas fa-chart-bar"></i> Carga</button>
                        </div>
                    </div>
                    <button class="close-modal" onclick="closeModal('analyticModal')" style="position: static; font-size: 1.5rem; background: var(--bg-color); border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-color); transition: 0.2s;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 30px; background: #f8f9fa;">
                    <!-- Gantt Premium Mode -->
                    <div id="modal_view_gantt" class="modal-chart-pane active">
                        <div class="premium-gantt-card">
                            <div class="gantt-header-dates">
                                <div style="width: 200px;"></div>
                                <div class="gantt-timeline-header">
                                    <?php for ($i = 1; $i <= $days_in_month; $i++):
    $dt = sprintf("%s-%02d", date('Y-m'), $i);
    $dow = date('N', strtotime($dt));
    $is_weekend = ($dow >= 6);
?>
                                        <div class="gantt-day-header <?php echo $is_weekend ? 'is-weekend' : ''; ?>">
                                            <span><?php echo $i; ?></span>
                                        </div>
                                    <?php
endfor; ?>
                                </div>
                            </div>
                            
                            <div class="gantt-rows-scroll">
                                <?php foreach ($prof_availability as $pa): ?>
                                    <div class="premium-gantt-row">
                                        <div class="gantt-prof-info">
                                            <div class="prof-avatar"><?php echo substr($pa['nome'], 0, 1); ?></div>
                                            <div class="prof-det">
                                                <span class="p-name"><?php echo htmlspecialchars($pa['nome']); ?></span>
                                                <span class="p-spec"><?php echo htmlspecialchars($pa['especialidade']); ?></span>
                                            </div>
                                        </div>
                                        <div class="gantt-track">
                                            <?php
    $current_seq = null;
    $seq_start = 0;
    $seq_count = 0;

    for ($i = 1; $i <= $days_in_month + 1; $i++) {
        $dt = ($i <= $days_in_month) ? sprintf("%s-%02d", date('Y-m'), $i) : null;
        $busy_val = ($dt && isset($agenda_gantt[$pa['id']][$dt])) ? $agenda_gantt[$pa['id']][$dt] : null;

        if ($busy_val !== $current_seq) {
            if ($current_seq !== null) {
                $width = ($seq_count / $days_in_month) * 100;
                $left = (($seq_start - 1) / $days_in_month) * 100;
                echo "<div class='gantt-task-bar' style='left: {$left}%; width: {$width}%;' title='{$current_seq}'>
                                                                <span class='bar-text'>{$current_seq}</span>
                                                              </div>";
            }
            elseif ($i > 1 && $seq_count > 0) {
            // Espaço livre - opcionalmente desenhar algo
            }
            $current_seq = $busy_val;
            $seq_start = $i;
            $seq_count = 1;
        }
        else {
            $seq_count++;
        }
    }
?>
                                            <div class="gantt-grid-overlay">
                                                <?php for ($i = 1; $i <= $days_in_month; $i++)
        echo "<div class='gantt-grid-line'></div>"; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php
endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Donut Mode -->
                    <div id="modal_view_donut" class="modal-chart-pane" style="display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; height: 100%; align-items: center;">
                            <div style="position: relative;">
                                <canvas id="modalDonutChart"></canvas>
                                <div id="donutCenterText" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                    <span style="font-size: 3rem; font-weight: 800; color: var(--primary-red);">0%</span>
                                    <p style="margin: 0; font-size: 0.9rem; opacity: 0.7;">Livre</p>
                                </div>
                            </div>
                            <div id="donutLegend" class="donut-legend-grid">
                                <!-- JS Populated -->
                            </div>
                        </div>
                    </div>

                    <!-- Bar Mode -->
                    <div id="modal_view_bar" class="modal-chart-pane" style="display: none;">
                        <div style="height: 500px;">
                            <canvas id="modalBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* Premium Gantt Styles */
            .premium-gantt-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
            .gantt-header-dates { display: flex; background: #f1f3f5; border-bottom: 2px solid #dee2e6; }
            .gantt-timeline-header { flex: 1; display: flex; }
            .gantt-day-header { flex: 1; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; color: #495057; border-left: 1px solid #dee2e6; }
            .gantt-day-header.is-weekend { background: #e9ecef; }
            
            .premium-gantt-row { display: flex; border-bottom: 1px solid #f1f3f5; min-height: 60px; }
            .gantt-prof-info { width: 200px; padding: 10px 15px; display: flex; align-items: center; gap: 10px; border-right: 2px solid #dee2e6; background: #fff; }
            .prof-avatar { width: 32px; height: 32px; background: var(--primary-red); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem; }
            .prof-det { display: flex; flex-direction: column; overflow: hidden; }
            .p-name { font-weight: 700; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .p-spec { font-size: 0.7rem; opacity: 0.6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            
            .gantt-track { flex: 1; position: relative; background: #fff; }
            .gantt-grid-overlay { position: absolute; inset: 0; display: flex; pointer-events: none; }
            .gantt-grid-line { flex: 1; border-right: 1px solid #f1f3f5; height: 100%; }
            
            .gantt-task-bar { 
                position: absolute; top: 12px; bottom: 12px; border-radius: 20px; 
                background: linear-gradient(90deg, #d32f2f, #ff5252); color: #fff;
                display: flex; align-items: center; padding: 0 15px; font-size: 0.7rem; font-weight: 700;
                box-shadow: 0 4px 8px rgba(211, 47, 47, 0.3); z-index: 2;
                transition: transform 0.2s; cursor: pointer;
            }
            .gantt-task-bar:hover { transform: scaleY(1.1); z-index: 3; }
            .bar-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

            /* Modal Pane visibility */
            .modal-chart-pane { height: 100%; }
            
            .donut-legend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .legend-card { background: #fff; padding: 12px; border-radius: 10px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
            .l-dot { width: 8px; height: 8px; border-radius: 50%; }
            .l-name { font-weight: 700; font-size: 0.8rem; flex: 1; }
            .l-val { font-weight: 800; color: var(--primary-red); }
        </style>

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
                    <?php
else: ?>
                        <?php
    $colors = ['#e53935', '#1976d2', '#388e3c', '#ff8f00', '#9c27b0', '#00838f', '#6d4c41'];
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
                        <?php $i++;
    endforeach; ?>
                    <?php
endif; ?>
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
                    <?php
else: ?>
                        <?php foreach ($proximas_turmas as $pt): ?>
                        <div class="city-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                            <div style="font-weight: 700; font-size: 0.9rem;"><?php echo htmlspecialchars($pt['nome']); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                <?php echo htmlspecialchars($pt['curso_nome']); ?> · <?php echo $pt['turno']; ?>
                                <?php if (!empty($pt['cidade'])): ?>
                                    · <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pt['cidade']); ?>
                                <?php
        endif; ?>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--primary-red); font-weight: 600;">
                                <i class="fas fa-calendar"></i> Início: <?php echo date('d/m/Y', strtotime($pt['data_inicio'])); ?>
                            </div>
                        </div>
                        <?php
    endforeach; ?>
                    <?php
endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let dashCharts = {};

function openAnalyticModal() {
    const modal = document.getElementById('analyticModal');
    modal.style.display = 'flex';
    switchModalChart('gantt');
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function switchModalChart(mode) {
    document.querySelectorAll('.modal-chart-pane').forEach(el => {
        el.style.display = 'none';
        el.classList.remove('active');
    });
    document.querySelectorAll('.modal-header .view-btn').forEach(el => el.classList.remove('active'));
    
    const pane = document.getElementById('modal_view_' + mode);
    if (pane) {
        pane.style.display = 'block';
        setTimeout(() => pane.classList.add('active'), 10);
    }
    document.getElementById('modal_btn_' + mode).classList.add('active');
    
    if (mode === 'donut') initModalDonutChart();
    if (mode === 'bar') initModalBarChart();
}

function initModalDonutChart() {
    if (dashCharts.modalDonut) return;
    const ctx = document.getElementById('modalDonutChart').getContext('2d');
    
    <?php
$total_days_all = count($prof_availability) * $total_util;
$total_busy_all = array_sum(array_column($prof_availability, 'dias_ocupados'));
$total_free_all = max(0, $total_days_all - $total_busy_all);
$perc_total_livre = ($total_days_all > 0) ? round(($total_free_all / $total_days_all) * 100) : 0;
?>
    
    document.querySelector('#donutCenterText span').innerText = '<?php echo $perc_total_livre; ?>%';
    
    dashCharts.modalDonut = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Ocupado', 'Livre'],
            datasets: [{
                data: [<?php echo $total_busy_all; ?>, <?php echo $total_free_all; ?>],
                backgroundColor: ['#d32f2f', '#4CAF50'],
                hoverOffset: 15,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '80%',
            animation: { animateScale: true, animateRotate: true }
        }
    });

    const legend = document.getElementById('donutLegend');
    <?php $top_available = array_slice($prof_availability, 0, 6); ?>
    let legendHtml = '';
    <?php foreach ($top_available as $tp): ?>
        legendHtml += `
            <div class="legend-card" style="box-shadow: 0 4px 10px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05);">
                <div class="l-dot" style="background: <?php echo $tp['perc_livre'] > 50 ? '#4CAF50' : '#d32f2f'; ?>;"></div>
                <div class="l-name"><?php echo htmlspecialchars($tp['nome']); ?></div>
                <div class="l-val"><?php echo round($tp['perc_livre']); ?>%</div>
            </div>`;
    <?php
endforeach; ?>
    legend.innerHTML = legendHtml;
}

function initModalBarChart() {
    if (dashCharts.modalBar) return;
    const ctx = document.getElementById('modalBarChart').getContext('2d');
    const labels = <?php echo json_encode(array_map(function ($p) {
    return $p['nome'];
}, $prof_availability)); ?>;
    const busyData = <?php echo json_encode(array_map(function ($p) {
    return (int)$p['dias_ocupados'];
}, $prof_availability)); ?>;
    const freeData = <?php echo json_encode(array_map(function ($p) {
    return (int)$p['dias_livres'];
}, $prof_availability)); ?>;

    dashCharts.modalBar = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Ocupado', data: busyData, backgroundColor: '#d32f2f', borderRadius: 8 },
                { label: 'Livre', data: freeData, backgroundColor: '#4CAF50', borderRadius: 8 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }
            },
            plugins: { 
                legend: { position: 'top', labels: { usePointStyle: true, font: { weight: 'bold' } } } 
            }
        }
    });
}

window.onclick = function(event) {
    if (event.target.className === 'modal') closeModal('analyticModal');
}
window.onkeydown = function(event) {
    if (event.key === 'Escape') closeModal('analyticModal');
}
</script>

<?php include 'includes/footer.php'; ?>
