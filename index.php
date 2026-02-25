<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';


// Fetch stats
$count_prof = $mysqli->query("SELECT COUNT(*) FROM professores")->fetch_row()[0];
$count_salas = $mysqli->query("SELECT COUNT(*) FROM salas")->fetch_row()[0];
$count_turmas = $mysqli->query("SELECT COUNT(*) FROM turmas")->fetch_row()[0];
$count_aulas = $mysqli->query("SELECT COUNT(*) FROM agenda")->fetch_row()[0];
$count_cursos = $mysqli->query("SELECT COUNT(*) FROM cursos")->fetch_row()[0];

// Fetch distinct cities for filter
$cidades = array_column($mysqli->query("SELECT DISTINCT cidade FROM turmas WHERE cidade IS NOT NULL AND cidade != '' ORDER BY cidade ASC")->fetch_all(MYSQLI_NUM), 0);

// City filter
$filtro_cidade = isset($_GET['cidade']) ? $_GET['cidade'] : '';

// Fetch today's classes with optional city filter
$today = date('Y-m-d');
$sql_today = "
    SELECT a.*, t.nome as turma_nome, t.cidade, 
           p1.nome as professor_nome, p1.cor_agenda as professor_cor,
           p2.nome as professor_nome_2, 
           p3.nome as professor_nome_3, 
           s.nome as sala_nome 
    FROM agenda a 
    JOIN turmas t ON a.turma_id = t.id 
    JOIN professores p1 ON a.professor_id = p1.id 
    LEFT JOIN professores p2 ON a.professor_id_2 = p2.id 
    LEFT JOIN professores p3 ON a.professor_id_3 = p3.id 
    JOIN salas s ON a.sala_id = s.id 
    WHERE a.data = ? 
";
if ($filtro_cidade) {
    $sql_today .= " AND t.cidade = ?";
    $stmt_today = $mysqli->prepare($sql_today . " ORDER BY a.hora_inicio ASC");
    $stmt_today->bind_param('ss', $today, $filtro_cidade);
}
else {
    $stmt_today = $mysqli->prepare($sql_today . " ORDER BY a.hora_inicio ASC");
    $stmt_today->bind_param('s', $today);
}
$stmt_today->execute();
$aulas_hoje = $stmt_today->get_result()->fetch_all(MYSQLI_ASSOC);

// Turmas by city count
$turmas_por_cidade = $mysqli->query("SELECT COALESCE(cidade, 'Sede') as cidade, COUNT(*) as total FROM turmas GROUP BY COALESCE(cidade, 'Sede') ORDER BY total DESC")->fetch_all(MYSQLI_ASSOC);

// Upcoming turmas (next 5 turmas starting)
$stmt_proximas = $mysqli->prepare("
    SELECT t.nome, t.cidade, c.nome as curso_nome, t.data_inicio, t.turno 
    FROM turmas t 
    JOIN cursos c ON t.curso_id = c.id 
    WHERE t.data_inicio >= ? 
    ORDER BY t.data_inicio ASC 
    LIMIT 5
");
$stmt_proximas->bind_param('s', $today);
$stmt_proximas->execute();
$proximas_turmas = $stmt_proximas->get_result()->fetch_all(MYSQLI_ASSOC);
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
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Exportar:</span>
            <a href="export_excel.php?tipo=completo" class="btn-export btn-export-excel" title="Planilha completa multi-aba (Docentes, Ambientes, Cursos, Turmas, Agenda)">
                <i class="fas fa-file-excel"></i> Planilha Completa
            </a>
            <a href="export_excel.php?tipo=powerbi" class="btn-export" style="background:#f2c811;color:#000;border-color:#d4a800;" title="CSV Turmas para Power BI Desktop">
                <i class="fas fa-chart-bar"></i> Power BI Turmas
            </a>
            <a href="export_excel.php?tipo=agenda_powerbi" class="btn-export" style="background:#f2c811;color:#000;border-color:#d4a800;" title="CSV Agenda para Power BI Desktop">
                <i class="fas fa-chart-bar"></i> Power BI Agenda
            </a>
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="dash-column">
            <!-- Aulas de Hoje -->
            <div class="dash-section" style="margin-bottom: 20px;">
                <div class="dash-section-header">
                    <h3><i class="fas fa-calendar-day" style="color: var(--primary-red);"></i> Aulas de Hoje</h3>
                    <span class="city-badge" style="background: rgba(0,0,0,0.05); color: var(--text-muted);">
                        <?php echo count($aulas_hoje); ?> aulas agendadas
                    </span>
                </div>
                <div class="dash-section-body" style="padding: 10px 0;">
                    <?php if (empty($aulas_hoje)): ?>
                        <p style="padding: 20px; text-align: center; color: var(--text-muted);">Nenhuma aula hoje.</p>
                    <?php
else: ?>
                        <div class="today-classes-list">
                            <?php foreach ($aulas_hoje as $a): ?>
                                <div class="today-class-item" style="display: flex; align-items: center; padding: 12px 24px; border-bottom: 1px solid var(--border-color); gap: 15px;">
                                    <div class="class-time" style="min-width: 100px; font-weight: 800; color: var(--primary-red); font-size: 0.9rem;">
                                        <?php echo substr($a['hora_inicio'], 0, 5); ?> - <?php echo substr($a['hora_fim'], 0, 5); ?>
                                    </div>
                                    <div class="class-info" style="flex: 1;">
                                        <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-color);"><?php echo htmlspecialchars($a['turma_nome']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($a['cidade'] ?: 'Sede'); ?>
                                            &middot; <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($a['sala_nome']); ?>
                                        </div>
                                    </div>
                                    <div class="class-prof" style="text-align: right;">
                                        <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-color);">
                                            <?php
        $profs = array_filter([$a['professor_nome'], $a['professor_nome_2'], $a['professor_nome_3']]);
        echo implode(' / ', array_map('htmlspecialchars', $profs));
?>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">Professor(es)</div>
                                    </div>
                                </div>
                            <?php
    endforeach; ?>
                        </div>
                    <?php
endif; ?>
                </div>
            </div>

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
$stmt_disp = $mysqli->prepare("
    SELECT p.id, p.nome, p.especialidade, COUNT(a.id) as dias_ocupados
    FROM professores p
    LEFT JOIN agenda a ON (p.id = a.professor_id OR p.id = a.professor_id_2 OR p.id = a.professor_id_3) 
    AND a.data BETWEEN ? AND ?
    GROUP BY p.id
");
$stmt_disp->bind_param('ss', $start_month, $end_month);
$stmt_disp->execute();
$prof_availability = $stmt_disp->get_result()->fetch_all(MYSQLI_ASSOC);

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
    $stmt_agenda = $mysqli->prepare("
        SELECT a.data, a.professor_id, a.professor_id_2, a.professor_id_3, t.nome as turma_nome
        FROM agenda a
        JOIN turmas t ON a.turma_id = t.id
        WHERE (a.professor_id IN ($placeholders) OR a.professor_id_2 IN ($placeholders) OR a.professor_id_3 IN ($placeholders)) 
        AND a.data BETWEEN ? AND ?
    ");
    $types_gantt = str_repeat('i', count($prof_ids_gantt) * 3) . 'ss';
    $params_gantt = array_merge($prof_ids_gantt, $prof_ids_gantt, $prof_ids_gantt, [$start_month, $end_month]);
    $stmt_agenda->bind_param($types_gantt, ...$params_gantt);
    $stmt_agenda->execute();
    $result_gantt = $stmt_agenda->get_result();
    while ($row = $result_gantt->fetch_assoc()) {
        if (in_array($row['professor_id'], $prof_ids_gantt))
            $agenda_gantt[$row['professor_id']][$row['data']] = $row['turma_nome'];
        if (!empty($row['professor_id_2']) && in_array($row['professor_id_2'], $prof_ids_gantt))
            $agenda_gantt[$row['professor_id_2']][$row['data']] = $row['turma_nome'];
        if (!empty($row['professor_id_3']) && in_array($row['professor_id_3'], $prof_ids_gantt))
            $agenda_gantt[$row['professor_id_3']][$row['data']] = $row['turma_nome'];
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

                <?php
// Summary stats
$ocupacao_media = count($prof_availability) > 0 ? round(100 - (array_sum(array_column($prof_availability, 'perc_livre')) / count($prof_availability))) : 0;
$total_aulas_stmt = $mysqli->prepare("SELECT COUNT(*) FROM agenda WHERE data BETWEEN ? AND ?");
$total_aulas_stmt->bind_param('ss', $start_month, $end_month);
$total_aulas_stmt->execute();
$total_aulas_count = $total_aulas_stmt->get_result()->fetch_row()[0];
$prof_mais_livre = !empty($prof_availability) ? $prof_availability[0] : null;
$prof_mais_ocupado = !empty($prof_availability) ? end($prof_availability) : null;
reset($prof_availability);

// Turno distribution
$stmt_turno_dist = $mysqli->prepare("SELECT 
                        SUM(CASE WHEN hora_inicio < '12:00:00' THEN 1 ELSE 0 END) as manha,
                        SUM(CASE WHEN hora_inicio >= '12:00:00' AND hora_inicio < '18:00:00' THEN 1 ELSE 0 END) as tarde,
                        SUM(CASE WHEN hora_inicio >= '18:00:00' THEN 1 ELSE 0 END) as noite
                    FROM agenda WHERE data BETWEEN ? AND ?");
$stmt_turno_dist->bind_param('ss', $start_month, $end_month);
$stmt_turno_dist->execute();
$td = $stmt_turno_dist->get_result()->fetch_assoc();
$td_m = (int)($td['manha'] ?? 0);
$td_t = (int)($td['tarde'] ?? 0);
$td_n = (int)($td['noite'] ?? 0);
$td_total = max(1, $td_m + $td_t + $td_n);

$month_names_dash = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$dash_month_label = $month_names_dash[(int)date('n') - 1] . ' ' . date('Y');
?>

                <!-- Summary Stats Row -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding: 20px 20px 0;">
                    <div style="background: var(--bg-color); border-radius: 12px; padding: 14px 16px; border: 1px solid var(--border-color); text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 800; color: <?php echo $ocupacao_media > 70 ? '#d32f2f' : ($ocupacao_media > 40 ? '#ff8f00' : '#2e7d32'); ?>;"><?php echo $ocupacao_media; ?>%</div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">Ocupação Média</div>
                    </div>
                    <div style="background: var(--bg-color); border-radius: 12px; padding: 14px 16px; border: 1px solid var(--border-color); text-align: center; overflow: hidden;">
                        <div style="font-size: 0.82rem; font-weight: 800; color: #2e7d32; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $prof_mais_livre ? htmlspecialchars($prof_mais_livre['nome']) : '-'; ?>"><?php echo $prof_mais_livre ? htmlspecialchars($prof_mais_livre['nome']) : '-'; ?></div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Mais Disponível</div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #2e7d32;"><?php echo $prof_mais_livre ? round($prof_mais_livre['perc_livre']) . '% livre' : ''; ?></div>
                    </div>
                    <div style="background: var(--bg-color); border-radius: 12px; padding: 14px 16px; border: 1px solid var(--border-color); text-align: center; overflow: hidden;">
                        <div style="font-size: 0.82rem; font-weight: 800; color: #d32f2f; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $prof_mais_ocupado ? htmlspecialchars($prof_mais_ocupado['nome']) : '-'; ?>"><?php echo $prof_mais_ocupado ? htmlspecialchars($prof_mais_ocupado['nome']) : '-'; ?></div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Mais Ocupado</div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #d32f2f;"><?php echo $prof_mais_ocupado ? round(100 - $prof_mais_ocupado['perc_livre']) . '% ocup.' : ''; ?></div>
                    </div>
                    <div style="background: var(--bg-color); border-radius: 12px; padding: 14px 16px; border: 1px solid var(--border-color); text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 800; color: #9c27b0;"><?php echo $total_aulas_count; ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">Aulas Este Mês</div>
                    </div>
                </div>

                <!-- Turno Distribution -->
                <div style="padding: 16px 20px 0;">
                    <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                        <i class="fas fa-clock" style="color: var(--primary-red);"></i> Distribuição por Turno
                    </div>
                    <div style="display: flex; gap: 14px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; font-size:0.72rem; font-weight:700; margin-bottom:4px;">
                                <span>☀ Manhã</span><span style="color:#ff8f00;"><?php echo $td_m; ?> aulas (<?php echo round(($td_m / $td_total) * 100); ?>%)</span>
                            </div>
                            <div style="height:8px; background:var(--border-color); border-radius:4px; overflow:hidden;">
                                <div style="height:100%; width:<?php echo round(($td_m / $td_total) * 100); ?>%; background:linear-gradient(90deg,#ff8f00,#ffb74d); border-radius:4px; transition: width 0.5s;"></div>
                            </div>
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; font-size:0.72rem; font-weight:700; margin-bottom:4px;">
                                <span>☁ Tarde</span><span style="color:#1976d2;"><?php echo $td_t; ?> aulas (<?php echo round(($td_t / $td_total) * 100); ?>%)</span>
                            </div>
                            <div style="height:8px; background:var(--border-color); border-radius:4px; overflow:hidden;">
                                <div style="height:100%; width:<?php echo round(($td_t / $td_total) * 100); ?>%; background:linear-gradient(90deg,#1976d2,#64b5f6); border-radius:4px; transition: width 0.5s;"></div>
                            </div>
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; font-size:0.72rem; font-weight:700; margin-bottom:4px;">
                                <span>☽ Noite</span><span style="color:#7b1fa2;"><?php echo $td_n; ?> aulas (<?php echo round(($td_n / $td_total) * 100); ?>%)</span>
                            </div>
                            <div style="height:8px; background:var(--border-color); border-radius:4px; overflow:hidden;">
                                <div style="height:100%; width:<?php echo round(($td_n / $td_total) * 100); ?>%; background:linear-gradient(90deg,#7b1fa2,#ba68c8); border-radius:4px; transition: width 0.5s;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Improved Gantt: 8 professors with avatar, % bar, day segments -->
                <div id="dash_view_gantt" class="dash-chart-container active" style="flex-direction: column; align-items: stretch; justify-content: flex-start; padding: 16px 20px 20px; display: flex;">
                    <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                        <i class="fas fa-align-left" style="color: var(--primary-red);"></i> Mapa de Ocupação — <?php echo $dash_month_label; ?>
                    </div>
                    <div class="dash-gantt-container" style="background: transparent; padding: 0;">
                        <div id="gantt_rows_container">
                            <?php if (empty($prof_availability)): ?>
                                <p style="text-align:center; padding: 20px; opacity:0.5;">Nenhum professor encontrado.</p>
                            <?php
else: ?>
                                <?php foreach (array_slice($prof_availability, 0, 8) as $pa):
        $ocu_perc = ($total_util > 0) ? round(($pa['dias_ocupados'] / $total_util) * 100) : 0;
        $bar_color = $ocu_perc > 70 ? '#d32f2f' : ($ocu_perc > 40 ? '#ff8f00' : '#2e7d32');
?>
                                    <div class="dash-gantt-row" style="margin-bottom: 6px;">
                                        <div style="width: 170px; display: flex; align-items: center; gap: 8px; flex-shrink: 0; padding-right: 8px;">
                                            <div style="width:26px; height:26px; background: linear-gradient(135deg, #e53935, #c62828); color:#fff; border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.68rem; flex-shrink:0;"><?php echo mb_substr($pa['nome'], 0, 1); ?></div>
                                            <div style="min-width:0; flex:1;">
                                                <div class="dash-gantt-label" style="width:auto; font-size:0.72rem; margin:0;"><?php echo htmlspecialchars($pa['nome']); ?></div>
                                                <div style="height:4px; background:var(--border-color); border-radius:2px; margin-top:2px; overflow:hidden;">
                                                    <div style="height:100%; width:<?php echo $ocu_perc; ?>%; background:<?php echo $bar_color; ?>; border-radius:2px; transition: width 0.5s;"></div>
                                                </div>
                                            </div>
                                            <span style="font-size:0.68rem; font-weight:800; color:<?php echo $bar_color; ?>; flex-shrink:0; min-width:28px; text-align:right;"><?php echo $ocu_perc; ?>%</span>
                                        </div>
                                        <div class="dash-gantt-bar" style="height: 24px;">
                                            <?php for ($i = 1; $i <= $days_in_month; $i++):
            $dt = sprintf("%s-%02d", date('Y-m'), $i);
            $dow = (int)date('N', strtotime($dt));
            $is_busy = $agenda_gantt[$pa['id']][$dt] ?? false;
            $is_weekend = ($dow >= 6);
            $seg_class = $is_busy ? 'gantt-seg-busy' : 'gantt-seg-free';
            if ($is_weekend && !$is_busy)
                $seg_class .= ' gantt-seg-weekend';
?>
                                                <div class="gantt-seg <?php echo $seg_class; ?>" title="Dia <?php echo $i; ?>: <?php echo $is_busy ? htmlspecialchars($is_busy) : ($is_weekend ? 'Fim de semana' : 'Livre'); ?>"></div>
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
                    <div style="display:flex; justify-content:center; gap:16px; margin-top:10px; font-size:0.72rem; font-weight:600; color:var(--text-muted);">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#d32f2f;margin-right:4px;vertical-align:middle;"></span>Ocupado</span>
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#2e7d32;margin-right:4px;vertical-align:middle;"></span>Livre</span>
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#2e7d32;opacity:0.5;margin-right:4px;vertical-align:middle;"></span>Fim de Semana</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="analyticModal" class="modal" style="display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);">
            <div class="modal-content" style="max-width: 95%; width: 1400px; height: 90vh; display: flex; flex-direction: column; padding: 0; overflow: hidden; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: 0 20px 50px rgba(0,0,0,0.3);">
                <div class="modal-header" style="padding: 20px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--card-bg);">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-weight: 800; color: var(--primary-red); font-size: 1.2rem;">
                            <div style="width: 38px; height: 38px; background: linear-gradient(135deg, #e53935, #c62828); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 10px rgba(229,57,53,0.3);">
                                <i class="fas fa-chart-line" style="color: #fff; font-size: 1rem;"></i>
                            </div>
                            Análise de Desempenho
                        </h2>
                        <div class="chart-switcher" style="display: flex; background: var(--bg-color); padding: 4px; border-radius: 12px; border: 1px solid var(--border-color); gap: 4px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);">
                            <button onclick="switchModalChart('gantt')" class="chart-switch-btn active" id="modal_btn_gantt">
                                <i class="fas fa-align-left"></i> <span>Gantt</span>
                            </button>
                            <button onclick="switchModalChart('donut')" class="chart-switch-btn" id="modal_btn_donut">
                                <i class="fas fa-chart-pie"></i> <span>Rosca</span>
                            </button>
                            <button onclick="switchModalChart('bar')" class="chart-switch-btn" id="modal_btn_bar">
                                <i class="fas fa-chart-bar"></i> <span>Carga</span>
                            </button>
                        </div>
                    </div>
                    <button class="close-modal" onclick="closeModal('analyticModal')" style="position: static; font-size: 1.1rem; background: var(--bg-color); border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-color); transition: all 0.2s;">
                        <i class="fas fa-times"></i>
                    </button>
                    <style>
                        .chart-switch-btn {
                            padding: 8px 18px; border-radius: 10px; border: none; background: transparent;
                            color: var(--text-muted); cursor: pointer; font-weight: 600; font-size: 0.85rem;
                            transition: all 0.25s cubic-bezier(0.4,0,0.2,1); display: flex; align-items: center; gap: 8px;
                            position: relative; overflow: hidden;
                        }
                        .chart-switch-btn::before {
                            content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, #e53935, #c62828);
                            opacity: 0; transition: opacity 0.25s; border-radius: 10px;
                        }
                        .chart-switch-btn.active {
                            color: #fff; box-shadow: 0 3px 12px rgba(229,57,53,0.3);
                        }
                        .chart-switch-btn.active::before { opacity: 1; }
                        .chart-switch-btn.active i, .chart-switch-btn.active span { position: relative; z-index: 1; }
                        .chart-switch-btn:not(.active) i, .chart-switch-btn:not(.active) span { position: relative; z-index: 1; }
                        .chart-switch-btn:not(.active):hover {
                            background: rgba(0,0,0,0.05); color: var(--text-color);
                        }
                        [data-theme="dark"] .chart-switch-btn:not(.active):hover {
                            background: rgba(255,255,255,0.08);
                        }
                    </style>
                </div>

                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 30px; background: var(--bg-color);">
                    <!-- Gantt Premium Mode -->
                    <div id="modal_view_gantt" class="modal-chart-pane active">
                        <div class="premium-gantt-card">
                            <div class="gantt-header-dates">
                                <div style="width: 220px; padding: 10px 16px; display: flex; align-items: center;">
                                    <span style="font-weight: 800; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Professor</span>
                                </div>
                                <div class="gantt-timeline-header">
                                    <?php for ($i = 1; $i <= $days_in_month; $i++):
    $dt = sprintf("%s-%02d", date('Y-m'), $i);
    $dow = date('N', strtotime($dt));
    $is_weekend = ($dow >= 6);
?>
                                        <?php $is_today_gantt = ($dt === date('Y-m-d')); ?>
                                        <div class="gantt-day-header <?php echo $is_weekend ? 'is-weekend' : ''; ?> <?php echo $is_today_gantt ? 'is-today' : ''; ?>">
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
                                            <?php
    // Today indicator line
    $today_day = (int)date('d');
    if ($today_day >= 1 && $today_day <= $days_in_month) {
        $today_left = (($today_day - 0.5) / $days_in_month) * 100;
        echo "<div class='gantt-today-line' style='left: {$today_left}%;'></div>";
    }
?>
                                        </div>
                                    </div>
                                <?php
endforeach; ?>
                            </div>
                            <div class="gantt-legend-row">
                                <div class="gl-item"><div class="gl-box" style="background: linear-gradient(135deg, #e53935, #ff5252);"></div> Ocupado (Turma atribuída)</div>
                                <div class="gl-item"><div class="gl-box" style="background: var(--card-bg); border: 1px solid var(--border-color);"></div> Disponível</div>
                                <div class="gl-item"><div class="gl-box" style="background: rgba(0,0,0,0.03); border: 1px dashed var(--border-color);"></div> Final de semana</div>
                                <div class="gl-item" style="color: #e53935; font-weight: 700;"><i class="fas fa-map-pin" style="font-size: 0.7rem;"></i> Hoje</div>
                            </div>
                            <?php
// Calculate summary stats
$total_gantt_busy = array_sum(array_column($prof_availability, 'dias_ocupados'));
$total_gantt_free = array_sum(array_column($prof_availability, 'dias_livres'));
$total_gantt_all = $total_gantt_busy + $total_gantt_free;
$avg_occupancy = ($total_gantt_all > 0) ? round(($total_gantt_busy / $total_gantt_all) * 100) : 0;
?>
                            <div class="gantt-summary">
                                <div class="gantt-summary-item">
                                    <div class="gs-num" style="color: var(--primary-red);"><?php echo count($prof_availability); ?></div>
                                    <div class="gs-label">Professores</div>
                                </div>
                                <div class="gantt-summary-item">
                                    <div class="gs-num" style="color: #d32f2f;"><?php echo $total_gantt_busy; ?></div>
                                    <div class="gs-label">Dias Ocupados</div>
                                </div>
                                <div class="gantt-summary-item">
                                    <div class="gs-num" style="color: #2e7d32;"><?php echo $total_gantt_free; ?></div>
                                    <div class="gs-label">Dias Livres</div>
                                </div>
                                <div class="gantt-summary-item">
                                    <div class="gs-num" style="color: #ff8f00;"><?php echo $avg_occupancy; ?>%</div>
                                    <div class="gs-label">Taxa Ocupação</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Donut Mode -->
                    <div id="modal_view_donut" class="modal-chart-pane" style="display: none;">
                        <div style="background: var(--card-bg); border-radius: 16px; padding: 30px; border: 1px solid var(--border-color); box-shadow: 0 6px 30px rgba(0,0,0,0.08);">
                            <div style="text-align: center; margin-bottom: 24px;">
                                <h3 style="font-weight: 800; font-size: 1.1rem; color: var(--text-color); margin: 0;"><i class="fas fa-chart-pie" style="color: var(--primary-red); margin-right: 8px;"></i>Distribuição de Disponibilidade</h3>
                                <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">Relação entre dias ocupados e livres dos professores</p>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center;">
                                <div style="position: relative; max-width: 380px; margin: 0 auto;">
                                    <canvas id="modalDonutChart"></canvas>
                                    <div id="donutCenterText" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                        <span style="font-size: 2.8rem; font-weight: 800; color: var(--primary-red); line-height: 1;">0%</span>
                                        <p style="margin: 4px 0 0; font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">Disponível</p>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-weight: 700; font-size: 0.9rem; margin-bottom: 14px; color: var(--text-color);">Top Professores</div>
                                    <div id="donutLegend" class="donut-legend-grid">
                                        <!-- JS Populated -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bar Mode -->
                    <div id="modal_view_bar" class="modal-chart-pane" style="display: none;">
                        <div style="background: var(--card-bg); border-radius: 16px; padding: 30px; border: 1px solid var(--border-color); box-shadow: 0 6px 30px rgba(0,0,0,0.08);">
                            <div style="text-align: center; margin-bottom: 24px;">
                                <h3 style="font-weight: 800; font-size: 1.1rem; color: var(--text-color); margin: 0;"><i class="fas fa-chart-bar" style="color: #1976d2; margin-right: 8px;"></i>Carga de Trabalho por Professor</h3>
                                <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">Comparação entre dias ocupados e disponíveis no mês atual</p>
                            </div>
                            <div style="height: 480px;">
                                <canvas id="modalBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* Premium Gantt Styles */
            .premium-gantt-card { background: var(--card-bg); border-radius: 16px; box-shadow: 0 6px 30px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid var(--border-color); }
            .gantt-header-dates { display: flex; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 2px solid #dee2e6; position: sticky; top: 0; z-index: 10; }
            [data-theme="dark"] .gantt-header-dates { background: linear-gradient(135deg, #2a2a2a, #1e1e1e); border-bottom-color: #444; }
            .gantt-timeline-header { flex: 1; display: flex; }
            .gantt-day-header { flex: 1; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); border-left: 1px solid rgba(0,0,0,0.06); transition: background 0.15s; }
            .gantt-day-header.is-weekend { background: rgba(0,0,0,0.04); color: #e53935; }
            [data-theme="dark"] .gantt-day-header.is-weekend { background: rgba(255,255,255,0.04); }
            .gantt-day-header.is-today { background: rgba(237,28,36,0.12); color: #e53935; font-weight: 900; position: relative; }
            .gantt-day-header.is-today::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 6px; height: 3px; border-radius: 3px 3px 0 0; background: #e53935; }
            
            .premium-gantt-row { display: flex; border-bottom: 1px solid rgba(0,0,0,0.04); min-height: 64px; transition: background 0.15s; }
            .premium-gantt-row:hover { background: rgba(237,28,36,0.02); }
            [data-theme="dark"] .premium-gantt-row { border-bottom-color: rgba(255,255,255,0.04); }
            [data-theme="dark"] .premium-gantt-row:hover { background: rgba(237,28,36,0.05); }
            .premium-gantt-row:last-child { border-bottom: none; }
            .gantt-prof-info { width: 220px; padding: 12px 16px; display: flex; align-items: center; gap: 12px; border-right: 2px solid rgba(0,0,0,0.06); background: var(--card-bg); flex-shrink: 0; }
            [data-theme="dark"] .gantt-prof-info { border-right-color: rgba(255,255,255,0.06); }
            .prof-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, #e53935, #c62828); color: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; box-shadow: 0 3px 8px rgba(229,57,53,0.3); flex-shrink: 0; }
            .prof-det { display: flex; flex-direction: column; overflow: hidden; gap: 2px; }
            .p-name { font-weight: 700; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-color); }
            .p-spec { font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            
            .gantt-track { flex: 1; position: relative; background: var(--card-bg); min-height: 64px; }
            .gantt-grid-overlay { position: absolute; inset: 0; display: flex; pointer-events: none; }
            .gantt-grid-line { flex: 1; border-right: 1px solid rgba(0,0,0,0.03); height: 100%; }
            [data-theme="dark"] .gantt-grid-line { border-right-color: rgba(255,255,255,0.03); }
            .gantt-grid-line:nth-child(7n+6), .gantt-grid-line:nth-child(7n) { background: rgba(0,0,0,0.015); }
            [data-theme="dark"] .gantt-grid-line:nth-child(7n+6), [data-theme="dark"] .gantt-grid-line:nth-child(7n) { background: rgba(255,255,255,0.015); }
            
            .gantt-task-bar { 
                position: absolute; top: 14px; bottom: 14px; border-radius: 8px; 
                background: linear-gradient(135deg, #e53935 0%, #ff5252 50%, #ff8a80 100%); color: #fff;
                display: flex; align-items: center; padding: 0 12px; font-size: 0.7rem; font-weight: 700;
                box-shadow: 0 3px 12px rgba(229,57,53,0.35), inset 0 1px 0 rgba(255,255,255,0.2); z-index: 2;
                transition: transform 0.2s cubic-bezier(0.4,0,0.2,1), box-shadow 0.2s; cursor: pointer;
                border: 1px solid rgba(255,255,255,0.15);
            }
            .gantt-task-bar:hover { transform: translateY(-2px) scaleY(1.08); z-index: 3; box-shadow: 0 6px 20px rgba(229,57,53,0.4); }
            .bar-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }

            /* Today indicator line */
            .gantt-today-line { position: absolute; top: 0; bottom: 0; width: 2px; background: #e53935; z-index: 5; pointer-events: none; }
            .gantt-today-line::before { content: ''; position: absolute; top: -4px; left: -4px; width: 10px; height: 10px; background: #e53935; border-radius: 50%; }

            /* Modal Pane visibility */
            .modal-chart-pane { height: 100%; }
            .modal-chart-pane { animation: fadeInChart 0.3s ease forwards; }
            @keyframes fadeInChart { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

            /* Donut chart styles */
            .donut-legend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
            .legend-card { background: var(--card-bg); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; transition: transform 0.2s, box-shadow 0.2s; }
            .legend-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
            .l-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
            .l-name { font-weight: 700; font-size: 0.82rem; flex: 1; color: var(--text-color); }
            .l-val { font-weight: 800; color: var(--primary-red); font-size: 0.95rem; }

            /* Gantt legend row */
            .gantt-legend-row { display: flex; align-items: center; justify-content: center; gap: 24px; padding: 14px 20px; background: linear-gradient(135deg, rgba(0,0,0,0.01), rgba(0,0,0,0.03)); border-top: 1px solid rgba(0,0,0,0.05); }
            [data-theme="dark"] .gantt-legend-row { background: linear-gradient(135deg, rgba(255,255,255,0.01), rgba(255,255,255,0.03)); border-top-color: rgba(255,255,255,0.05); }
            .gantt-legend-row .gl-item { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); }
            .gantt-legend-row .gl-box { width: 14px; height: 14px; border-radius: 4px; }

            /* Summary stats row */
            .gantt-summary { display: flex; gap: 16px; padding: 16px 20px; background: var(--bg-color); border-top: 1px solid var(--border-color); }
            .gantt-summary-item { flex: 1; text-align: center; padding: 12px; background: var(--card-bg); border-radius: 10px; border: 1px solid var(--border-color); }
            .gantt-summary-item .gs-num { font-size: 1.4rem; font-weight: 800; }
            .gantt-summary-item .gs-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
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
    document.querySelectorAll('.chart-switch-btn').forEach(el => el.classList.remove('active'));
    
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
                backgroundColor: ['#e53935', '#43a047'],
                hoverBackgroundColor: ['#c62828', '#2e7d32'],
                hoverOffset: 20,
                borderWidth: 3,
                borderColor: 'var(--card-bg)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { weight: 'bold', size: 13 },
                    bodyFont: { size: 12 },
                    padding: 14,
                    cornerRadius: 10,
                    displayColors: true,
                    boxPadding: 6
                }
            },
            cutout: '78%',
            animation: { animateScale: true, animateRotate: true, duration: 800, easing: 'easeOutQuart' }
        }
    });

    const legend = document.getElementById('donutLegend');
    <?php $top_available = array_slice($prof_availability, 0, 6); ?>
    let legendHtml = '';
    <?php foreach ($top_available as $tp): ?>
        legendHtml += `
            <div class="legend-card">
                <div class="l-dot" style="background: <?php echo $tp['perc_livre'] > 50 ? '#43a047' : '#e53935'; ?>;"></div>
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
                { label: 'Ocupado', data: busyData, backgroundColor: '#e53935', borderRadius: 6, borderSkipped: false, barPercentage: 0.7, categoryPercentage: 0.8 },
                { label: 'Livre', data: freeData, backgroundColor: '#43a047', borderRadius: 6, borderSkipped: false, barPercentage: 0.7, categoryPercentage: 0.8 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { 
                    stacked: true, 
                    grid: { display: false },
                    ticks: { font: { weight: 'bold', size: 11 }, maxRotation: 45, minRotation: 25 },
                    border: { display: false }
                },
                y: { 
                    stacked: true, 
                    beginAtZero: true, 
                    grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    ticks: { font: { weight: '600' }, stepSize: 2 },
                    border: { display: false }
                }
            },
            plugins: { 
                legend: { 
                    position: 'top', 
                    labels: { usePointStyle: true, pointStyle: 'rectRounded', font: { weight: 'bold', size: 12 }, padding: 20, boxWidth: 12 } 
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { weight: 'bold', size: 13 },
                    bodyFont: { size: 12 },
                    padding: 14,
                    cornerRadius: 10,
                    displayColors: true,
                    boxPadding: 6
                }
            },
            animation: { duration: 800, easing: 'easeOutQuart' }
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
