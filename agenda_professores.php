<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';

// --- AJAX HANDLER FOR WEEKDAY BLOCKING (Turno-Aware) ---
if (isset($_GET['ajax_weekday_check'])) {
    $prof_id = (int)$_GET['prof_id'];
    $date_start = $_GET['date_start'];
    $date_end = $_GET['date_end'];
    $hora_inicio = $_GET['hora_inicio'];
    $hora_fim = $_GET['hora_fim'];

    // Get ALL classes for this professor in the date range (no time filter)
    $st = $mysqli->prepare("
        SELECT DAYOFWEEK(a.data) as dow, a.data, a.hora_inicio, a.hora_fim
        FROM agenda a
        WHERE (a.professor_id = ? OR a.professor_id_2 = ? OR a.professor_id_3 = ? OR a.professor_id_4 = ?)
        AND a.data BETWEEN ? AND ?
    ");
    $st->bind_param('iiiiss', $prof_id, $prof_id, $prof_id, $prof_id, $date_start, $date_end);
    $st->execute();
    $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    // MySQL DAYOFWEEK: 1=Sun, 2=Mon, 3=Tue, 4=Wed, 5=Thu, 6=Fri, 7=Sat
    // Our checkboxes: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
    $date_turnos = []; // dow => date => ['M'=>bool, 'T'=>bool, 'N'=>bool]
    $blocked = []; // dow => count of time-conflicting entries

    foreach ($results as $row) {
        $mysql_dow = (int)$row['dow'];
        if ($mysql_dow < 2)
            continue; // Skip Sunday
        $our_dow = $mysql_dow - 1; // 2→1, 3→2, 4→3, 5→4, 6→5, 7→6

        $hi = $row['hora_inicio'];
        $hf = $row['hora_fim'];
        $dt = $row['data'];

        // Track turno per date per weekday
        if (!isset($date_turnos[$our_dow][$dt])) {
            $date_turnos[$our_dow][$dt] = ['M' => false, 'T' => false, 'N' => false];
        }
        if ($hi < '12:00:00')
            $date_turnos[$our_dow][$dt]['M'] = true;
        if ($hi < '18:00:00' && $hf > '12:00:00')
            $date_turnos[$our_dow][$dt]['T'] = true;
        if ($hf > '18:00:00' || $hi >= '18:00:00')
            $date_turnos[$our_dow][$dt]['N'] = true;

        // Check time overlap with selected hours
        if ($hi < $hora_fim && $hf > $hora_inicio) {
            if (!isset($blocked[$our_dow]))
                $blocked[$our_dow] = 0;
            $blocked[$our_dow]++;
        }
    }

    // Aggregate turno counts per weekday
    $turnos = [];
    foreach ($date_turnos as $dow => $dates) {
        $turnos[$dow] = ['M' => 0, 'T' => 0, 'N' => 0, 'total' => count($dates)];
        foreach ($dates as $dt => $t) {
            if ($t['M'])
                $turnos[$dow]['M']++;
            if ($t['T'])
                $turnos[$dow]['T']++;
            if ($t['N'])
                $turnos[$dow]['N']++;
        }
    }

    // Count total occurrences of each weekday in the date range (even if no classes)
    $total_datas_por_dow = [];
    $cur = new DateTime($date_start);
    $end = new DateTime($date_end);
    $end->modify('+1 day');
    while ($cur < $end) {
        $mysql_dow = (int)$cur->format('w') + 1; // PHP: 0=Sun → MySQL: 1=Sun
        if ($mysql_dow >= 2) { // Skip Sunday
            $our_dow = $mysql_dow - 1;
            if (!isset($total_datas_por_dow[$our_dow]))
                $total_datas_por_dow[$our_dow] = 0;
            $total_datas_por_dow[$our_dow]++;
        }
        $cur->modify('+1 day');
    }

    header('Content-Type: application/json');
    echo json_encode([
        'blocked' => $blocked,
        'turnos' => $turnos,
        'total_datas_por_dow' => $total_datas_por_dow
    ]);
    exit;
}

// --- AJAX HANDLER FOR PROFESSORS BY SPECIALTY ---
if (isset($_GET['ajax_profs_by_specialty'])) {
    $especialidade = $_GET['especialidade'];
    $st = $mysqli->prepare("SELECT id, nome FROM professores WHERE especialidade = ? ORDER BY nome ASC");
    $st->bind_param('s', $especialidade);
    $st->execute();
    $profs = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($profs);
    exit;
}

// --- AJAX HANDLER FOR TIMELINE DATA ---
if (isset($_GET['ajax_availability'])) {
    $prof_id = (int)$_GET['prof_id'];
    $month = $_GET['month']; // Y-m
    $f_day = $month . '-01';
    $l_day = date('Y-m-t', strtotime($f_day));

    $st = $mysqli->prepare("
        SELECT a.data, t.nome as turma_nome, a.hora_inicio, a.hora_fim
        FROM agenda a 
        JOIN turmas t ON a.turma_id = t.id
        WHERE (a.professor_id = ? OR a.professor_id_2 = ? OR a.professor_id_3 = ? OR a.professor_id_4 = ?) 
        AND a.data BETWEEN ? AND ?
    ");
    $st->bind_param('iiiiss', $prof_id, $prof_id, $prof_id, $prof_id, $f_day, $l_day);
    $st->execute();
    $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    $busy = [];
    $turnos = [];
    foreach ($results as $row) {
        $busy[$row['data']] = $row['turma_nome'];
        if (!isset($turnos[$row['data']]))
            $turnos[$row['data']] = ['M' => false, 'T' => false, 'N' => false];
        $hi = $row['hora_inicio'];
        $hf = $row['hora_fim'];
        if ($hi < '12:00:00')
            $turnos[$row['data']]['M'] = true;
        if ($hi < '18:00:00' && $hf > '12:00:00')
            $turnos[$row['data']]['T'] = true;
        if ($hf > '18:00:00' || $hi >= '18:00:00')
            $turnos[$row['data']]['N'] = true;
    }

    header('Content-Type: application/json');
    echo json_encode(['busy' => $busy, 'turnos' => $turnos]);
    exit;
}

include 'includes/header.php';

// Filtros e Parâmetros da Tabela
$search_name = isset($_GET['search']) ? $_GET['search'] : '';
$filter_especialidade = isset($_GET['especialidade']) ? $_GET['especialidade'] : '';
$ordem_disp = isset($_GET['ordem_disp']) ? $_GET['ordem_disp'] : 'mais';
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'timeline'; // Mode: timeline, blocks, calendar, semestral
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ($view_mode == 'calendar') ? 1 : 10; // Calendar view shows 1 prof at a time for focus
$offset = ($page - 1) * $limit;

$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$first_day = date('Y-m-01', strtotime($current_month));
$last_day = date('Y-m-t', strtotime($current_month));
$days_in_month = date('t', strtotime($current_month));

// Fetch distinct specialties for filter dropdown
$especialidades_result = $mysqli->query("SELECT DISTINCT especialidade FROM professores WHERE especialidade IS NOT NULL AND especialidade != '' ORDER BY especialidade ASC");
$especialidades = $especialidades_result->fetch_all(MYSQLI_ASSOC);

// SQL para Professores com busca e ordenação por disponibilidade
$where_search = $search_name ? "AND nome LIKE ?" : "";
$where_especialidade = $filter_especialidade ? "AND p.especialidade = ?" : "";
$params_search = $search_name ? ["%$search_name%"] : [];

$query_base = "
    FROM professores p 
    LEFT JOIN (
        SELECT COUNT(*) as total_aulas, pid FROM (
            SELECT professor_id as pid, data FROM agenda WHERE data BETWEEN '$first_day' AND '$last_day'
            UNION ALL
            SELECT professor_id_2 as pid, data FROM agenda WHERE data BETWEEN '$first_day' AND '$last_day' AND professor_id_2 IS NOT NULL
            UNION ALL
            SELECT professor_id_3 as pid, data FROM agenda WHERE data BETWEEN '$first_day' AND '$last_day' AND professor_id_3 IS NOT NULL
            UNION ALL
            SELECT professor_id_4 as pid, data FROM agenda WHERE data BETWEEN '$first_day' AND '$last_day' AND professor_id_4 IS NOT NULL
        ) all_p GROUP BY pid
    ) a ON p.id = a.pid
    WHERE 1=1 $where_search $where_especialidade
";

// Build bind params for count query
$bind_types_count = '';
$bind_vals_count = [];
if ($search_name) {
    $bind_types_count .= 's';
    $bind_vals_count[] = $params_search[0];
}
if ($filter_especialidade) {
    $bind_types_count .= 's';
    $bind_vals_count[] = $filter_especialidade;
}

$total_profs = $mysqli->prepare("SELECT COUNT(*) $query_base");
if (!empty($bind_vals_count)) {
    $total_profs->bind_param($bind_types_count, ...$bind_vals_count);
}
$total_profs->execute();
$total_count = $total_profs->get_result()->fetch_row()[0];

$sort_sql = $ordem_disp == 'mais' ? "ORDER BY COALESCE(a.total_aulas, 0) ASC" : "ORDER BY COALESCE(a.total_aulas, 0) DESC";

$stmt_profs = $mysqli->prepare("
    SELECT p.*, COALESCE(a.total_aulas, 0) as total_aulas
    $query_base
    $sort_sql
    LIMIT $limit OFFSET $offset
");
if (!empty($bind_vals_count)) {
    $stmt_profs->bind_param($bind_types_count, ...$bind_vals_count);
}
$stmt_profs->execute();
$professores = $stmt_profs->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar aulas dos professores para as barras horizontais
$prof_ids = array_column($professores, 'id');
$agenda_data = [];
$turno_detail = [];
$turno_summary = [];
if (!empty($prof_ids)) {
    $placeholders = implode(',', array_fill(0, count($prof_ids), '?'));
    $stmt_agenda = $mysqli->prepare("
        SELECT a.data, t.nome as turma_nome, a.hora_inicio, a.hora_fim,
               a.professor_id, a.professor_id_2, a.professor_id_3, a.professor_id_4
        FROM agenda a
        JOIN turmas t ON a.turma_id = t.id
        WHERE (a.professor_id IN ($placeholders) OR a.professor_id_2 IN ($placeholders) OR a.professor_id_3 IN ($placeholders) OR a.professor_id_4 IN ($placeholders)) 
        AND a.data BETWEEN ? AND ?
    ");
    $params_agenda = array_merge($prof_ids, $prof_ids, $prof_ids, $prof_ids, [$first_day, $last_day]);
    $types_agenda = str_repeat('i', count($prof_ids) * 4) . 'ss';
    $stmt_agenda->bind_param($types_agenda, ...$params_agenda);
    $stmt_agenda->execute();
    $results_agenda = $stmt_agenda->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($results_agenda as $row) {
        $agenda_data[$row['professor_id']][$row['data']] = $row['turma_nome'];
        if (!empty($row['professor_id_2'])) {
            $agenda_data[$row['professor_id_2']][$row['data']] = $row['turma_nome'];
        }
        if (!empty($row['professor_id_3'])) {
            $agenda_data[$row['professor_id_3']][$row['data']] = $row['turma_nome'];
        }
        if (!empty($row['professor_id_4'])) {
            $agenda_data[$row['professor_id_4']][$row['data']] = $row['turma_nome'];
        }
    }

    // Build turno data per professor per date for conflict indicators
    // A class spanning multiple turnos (e.g. 07:30-17:30) counts as both Manhã AND Tarde
    foreach ($results_agenda as $row) {
        $pids = array_filter([$row['professor_id'], $row['professor_id_2'], $row['professor_id_3'], $row['professor_id_4']]);
        $dt = $row['data'];
        $hi = $row['hora_inicio'];
        $hf = $row['hora_fim'];

        foreach ($pids as $pid) {
            // Only process if the professor is in the current page's list
            if (!in_array($pid, $prof_ids))
                continue;

            if (!isset($turno_detail[$pid][$dt]))
                $turno_detail[$pid][$dt] = ['M' => false, 'T' => false, 'N' => false];
            if (!isset($turno_summary[$pid]))
                $turno_summary[$pid] = ['M' => 0, 'T' => 0, 'N' => 0];

            if ($hi < '12:00:00') {
                if (!$turno_detail[$pid][$dt]['M'])
                    $turno_summary[$pid]['M']++;
                $turno_detail[$pid][$dt]['M'] = $row['turma_nome'];
            }
            if ($hi < '18:00:00' && $hf > '12:00:00') {
                if (!$turno_detail[$pid][$dt]['T'])
                    $turno_summary[$pid]['T']++;
                $turno_detail[$pid][$dt]['T'] = $row['turma_nome'];
            }
            if ($hf > '18:00:00' || $hi >= '18:00:00') {
                if (!$turno_detail[$pid][$dt]['N'])
                    $turno_summary[$pid]['N']++;
                $turno_detail[$pid][$dt]['N'] = $row['turma_nome'];
            }
        }
    }
}

// Data para os selects dos agendadores (Modais)
$turmas_select = $mysqli->query("SELECT t.id, t.nome, c.nome as curso_nome FROM turmas t JOIN cursos c ON t.curso_id = c.id ORDER BY t.nome ASC")->fetch_all(MYSQLI_ASSOC);
$salas_select = $mysqli->query("SELECT id, nome FROM salas ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$all_profs = $mysqli->query("SELECT id, nome, especialidade FROM professores ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$all_especialidades_modal = $mysqli->query("SELECT DISTINCT especialidade FROM professores WHERE especialidade IS NOT NULL AND especialidade != '' ORDER BY especialidade ASC")->fetch_all(MYSQLI_ASSOC);
?>

<style>

    /* Tabela de Disponibilidade */
    .filter-header {
        background: var(--card-bg); border-radius: 16px; padding: 20px 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid var(--border-color);
        margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
    }
    .search-group { display: flex; gap: 10px; align-items: center; }
    .search-group input, .search-group select {
        padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); font-weight: 600;
    }
    .table-container { background: var(--card-bg); border-radius: 16px; padding: 25px; border: 1px solid var(--border-color); overflow-x: auto; }
    .prof-row { margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; }
    .prof-row:last-child { border-bottom: none; }
    .prof-info-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .prof-name { font-weight: 800; font-size: 1.1rem; color: var(--text-color); cursor: pointer; transition: color 0.2s; }
    .prof-name:hover { color: var(--primary-red); }
    .prof-spec { font-size: 0.85rem; color: var(--text-muted); }

    .timeline-bar-wrapper {
        margin-top: 10px; height: 45px; display: flex; border-radius: 8px; overflow: hidden;
        border: 1px solid var(--border-color); box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
    }
    .bar-seg { flex: 1; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; position: relative; border-right: 1px solid rgba(0,0,0,0.05); transition: transform 0.2s, filter 0.2s; cursor: pointer; }
    .bar-seg:last-child { border-right: none; }
    .bar-seg:hover { filter: brightness(1.2); transform: scaleY(1.1); z-index: 5; }
    .bar-seg-busy { background: #ff1c1c; color: #fff; }
    .bar-seg-partial { background: #f9a825; color: #fff; cursor: pointer; }
    .bar-seg-partial:hover { background: #f57f17; }
    .bar-seg-free { background: #2e7d32; color: #fff; }
    .bar-seg-weekend { background-color: #f5f5f5; color: #bbb; background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0,0,0,0.02) 5px, rgba(0,0,0,0.02) 10px); }
    .bar-seg-sunday { background-color: #ffcdd2; color: #b71c1c; cursor: not-allowed; opacity: 0.7; }

    .pagination { display: flex; justify-content: center; align-items: center; gap: 20px; margin-top: 30px; }
    .btn-nav { width: 45px; height: 45px; border-radius: 50%; border: 1px solid var(--border-color); background: var(--card-bg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; text-decoration: none; color: var(--text-color); }
    .btn-nav:hover:not(.disabled) { background: var(--primary-red); color: #fff; border-color: var(--primary-red); }
    .btn-nav.disabled { opacity: 0.3; cursor: not-allowed; }

    /* Modal Styles */
    .calendar-container { margin-top: 25px; padding: 20px; background: var(--bg-color); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: inset 0 2px 10px rgba(0,0,0,0.02); }
    .calendar-header-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-bottom: 15px; text-align: center; font-weight: 800; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; }
    .calendar-day { aspect-ratio: 1 / 1; min-height: 90px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 12px; border: 1px solid var(--border-color); position: relative; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 6px rgba(0,0,0,0.03); }
    .calendar-day-busy { background: #ff1c1c; color: #fff; cursor: not-allowed; border-color: #d32f2f; }
    .calendar-day-free { background: #2e7d32; color: #fff; border-color: #1b5e20; }
    .calendar-day-free:hover { filter: brightness(1.1); transform: translateY(-3px) scale(1.02); z-index: 2; box-shadow: 0 8px 20px rgba(46, 125, 50, 0.3); }
    .calendar-day-partial { background: #f9a825; color: #fff; cursor: pointer; border-color: #f57f17; }
    .calendar-day-partial:hover { filter: brightness(1.1); transform: translateY(-3px) scale(1.02); z-index: 2; box-shadow: 0 8px 20px rgba(249, 168, 37, 0.4); }
    .calendar-day-empty { visibility: hidden; }
    .calendar-day-weekend { background-color: #f8f9fa !important; color: #adb5bd !important; border-style: dashed; background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0,0,0,0.02) 5px, rgba(0,0,0,0.02) 10px); }
    .calendar-day-busy.calendar-day-weekend { background-color: #ffcdd2 !important; color: #b71c1c !important; opacity: 0.95; }
    .calendar-day-weekendd { background-color: #ffebee !important; color: #e53935 !important; border-color: #ffcdd2; }
    .day-number { font-size: 1.4rem; font-weight: 800; }
    .day-status-label { font-size: 0.65rem; margin-top: 6px; opacity: 0.8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    
    .timeline-legend { display: flex; justify-content: center; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; font-size: 0.85rem; }
    .legend-item { display: flex; align-items: center; gap: 8px; }
    .legend-box { width: 16px; height: 16px; border-radius: 3px; border: 1px solid rgba(0,0,0,0.1); }
    
    .month-nav { display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 20px; }
    .month-btn { background: var(--bg-color); border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
    .month-btn:hover { background: var(--primary-red); color: #fff; border-color: var(--primary-red); }

    /* Blocos Contínuos Style */
    .blocks-bar-wrapper {
        margin-top: 10px; display: flex; border-radius: 10px; overflow: hidden;
        border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.06); min-height: 50px;
    }
    .block-seg {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 8px 6px; position: relative; transition: filter 0.2s, transform 0.2s;
        border-right: 2px solid rgba(255,255,255,0.3); cursor: default; min-width: 0;
    }
    .block-seg:last-child { border-right: none; }
    .block-seg:hover { filter: brightness(1.15); transform: scaleY(1.05); z-index: 5; }
    .block-seg-free { background: #2e7d32; color: #fff; cursor: pointer; }
    .block-seg-free:hover { box-shadow: inset 0 0 15px rgba(0,0,0,0.15); }
    .block-seg-busy { background: #d32f2f; color: #fff; }
    .block-seg-partial { background: #f9a825; color: #fff; cursor: pointer; }
    .block-seg-partial:hover { box-shadow: inset 0 0 15px rgba(0,0,0,0.15); }
    .block-seg-sunday { background: #ffcdd2; color: #b71c1c; opacity: 0.8; }
    .block-range { font-size: 0.85rem; font-weight: 800; line-height: 1; white-space: nowrap; }
    .block-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; opacity: 0.85; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; letter-spacing: 0.3px; }
    
    .view-selector { display: flex; background: var(--bg-color); padding: 5px; border-radius: 10px; border: 1px solid var(--border-color); }
    .view-btn { padding: 8px 15px; border-radius: 8px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; text-decoration: none; display: flex; align-items: center; gap: 8px; }
    .view-btn.active { background: var(--card-bg); color: var(--primary-red); shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .view-btn:hover:not(.active) { background: rgba(0,0,0,0.05); }

    /* Turno Conflict Indicators */
    .turno-bar-wrapper {
        display: flex; margin-top: 6px; border-radius: 8px; overflow: hidden;
        border: 1px solid var(--border-color); background: var(--bg-color); height: 28px;
    }
    .turno-day-col { flex: 1; display: flex; flex-direction: column; gap: 1px; padding: 2px 0; border-right: 1px solid rgba(0,0,0,0.04); }
    .turno-day-col:last-child { border-right: none; }
    .turno-dot { flex: 1; min-height: 6px; border-radius: 1px; }
    .turno-occ { background: #e53935; }
    .turno-avl { background: #4caf50; }
    .turno-blk { background: #e0e0e0; }
    .turno-partial { background: #f9a825; }
    .turno-wknd { background: #9c9c9c; }
    .turno-labels { display: flex; justify-content: space-between; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-top: 4px; padding: 0 4px; }

    .turno-summary { display: flex; gap: 10px; margin: 10px 0; flex-wrap: wrap; }
    .turno-pill { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
    .turno-pill-active { background: rgba(229,57,53,0.1); color: #c62828; border: 1px solid rgba(229,57,53,0.2); }
    .turno-pill-free { background: rgba(46,125,50,0.08); color: #2e7d32; border: 1px solid rgba(46,125,50,0.15); }
    .turno-pill-blocked { background: rgba(255,152,0,0.1); color: #e65100; border: 1px solid rgba(255,152,0,0.2); }

    /* Weekday Card Blocking UI */
    .weekday-card {
        background: var(--card-bg); border-radius: 10px; padding: 10px 8px; border: 2px solid var(--border-color);
        text-align: center; transition: all 0.25s ease; cursor: pointer; position: relative; min-width: 0;
    }
    .weekday-card:hover { border-color: #4caf50; box-shadow: 0 2px 8px rgba(76,175,80,0.15); }
    .weekday-card.wc-blocked {
        border-color: #e53935; background: rgba(229,57,53,0.04); opacity: 0.7;
    }
    .weekday-card.wc-blocked:hover { border-color: #b71c1c; box-shadow: 0 2px 8px rgba(229,57,53,0.15); }
    .weekday-card.wc-partial-block {
        border-color: #f9a825; background: rgba(249,168,37,0.04);
    }
    .weekday-card .wc-day-name { font-weight: 800; font-size: 0.88rem; margin: 4px 0 6px; }
    .weekday-card .wc-turno-row {
        display: flex; justify-content: center; gap: 4px; margin-top: 4px;
    }
    .weekday-card .wc-turno-badge {
        display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px;
        border-radius: 10px; font-size: 0.62rem; font-weight: 700; line-height: 1.2;
    }
    .wc-turno-badge.wt-occupied { background: #e53935; color: #fff; }
    .wc-turno-badge.wt-free { background: #e8f5e9; color: #2e7d32; }
    .wc-turno-badge.wt-conflict { background: #ff6f00; color: #fff; animation: pulse-conflict 1.5s ease-in-out infinite; }
    .weekday-card .wc-lock-icon {
        position: absolute; top: 4px; right: 6px; font-size: 0.65rem; color: #e53935; opacity: 0;
        transition: opacity 0.2s;
    }
    .weekday-card.wc-blocked .wc-lock-icon { opacity: 1; }
    .weekday-card .wc-count {
        font-size: 0.6rem; color: var(--text-muted); margin-top: 3px; font-weight: 600;
    }
    @keyframes pulse-conflict {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    /* Semestral Mini-Calendar */
    .sem-month-box { background: var(--bg-color); border-radius: 12px; padding: 10px 12px; border: 1px solid var(--border-color); transition: box-shadow 0.2s; }
    .sem-month-box:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .sem-month-header { display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; font-weight: 800; color: var(--text-color); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; padding: 0 2px; }
    .sem-month-stats { font-size: 0.68rem; font-weight: 700; }
    .sem-day-headers { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; text-align: center; font-size: 0.55rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 3px; }
    .sem-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
    .sem-day { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 0.62rem; font-weight: 700; border-radius: 4px; cursor: default; transition: all 0.15s; min-height: 20px; }
    .sem-day-empty { visibility: hidden; }
    .sem-day-free { background: #e8f5e9; color: #2e7d32; cursor: pointer; }
    .sem-day-free:hover { background: #2e7d32; color: #fff; transform: scale(1.15); z-index: 2; box-shadow: 0 2px 6px rgba(46,125,50,0.4); }
    .sem-day-busy { background: #e53935; color: #fff; }
    .sem-day-busy:hover { filter: brightness(1.1); }
    .sem-day-partial { background: #f9a825; color: #fff; cursor: pointer; }
    .sem-day-partial:hover { background: #f57f17; color: #fff; transform: scale(1.15); z-index: 2; }
    .sem-day-sunday { background: #f5f5f5; color: #bbb; }
    .sem-day-weekend { background: #fff8e1; color: #f57f17; cursor: pointer; }
    .sem-day-weekend:hover { background: #f57f17; color: #fff; }

    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
    .modal-content { background: var(--card-bg); margin: 3% auto; padding: 35px; border-radius: 20px; box-shadow: 0 10px 50px rgba(0,0,0,0.3); border: 1px solid var(--border-color); position: relative; }
    .close-modal { position: absolute; top: 20px; right: 25px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); transition: color 0.2s; }
    .close-modal:hover { color: var(--primary-red); }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-calendar-check"></i> Agenda de Professores</h2>
        <div class="view-selector" style="margin-top: 10px;">
            <a href="?view_mode=timeline&month=<?php echo $current_month; ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>" class="view-btn <?php echo $view_mode == 'timeline' ? 'active' : ''; ?>">
                <i class="fas fa-grip-lines"></i> Timeline
            </a>
            <a href="?view_mode=blocks&month=<?php echo $current_month; ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>" class="view-btn <?php echo $view_mode == 'blocks' ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Blocos
            </a>
            <a href="?view_mode=calendar&month=<?php echo $current_month; ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>" class="view-btn <?php echo $view_mode == 'calendar' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Calendário
            </a>
            <a href="?view_mode=semestral&month=<?php echo $current_month; ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>" class="view-btn <?php echo $view_mode == 'semestral' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i> Semestral
            </a>
        </div>
    </div>
</div>

<?php
$prev_month = date('Y-m', strtotime($current_month . '-01 -1 month'));
$next_month = date('Y-m', strtotime($current_month . '-01 +1 month'));
$months_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$m_num = (int)date('m', strtotime($current_month . '-01'));
$m_year = date('Y', strtotime($current_month . '-01'));
$month_label = $months_pt[$m_num] . ' ' . $m_year;

// Semestral navigation: jump 6 months instead of 1
if ($view_mode == 'semestral') {
    $prev_month = date('Y-m', strtotime($current_month . '-01 -6 months'));
    $next_month = date('Y-m', strtotime($current_month . '-01 +6 months'));
    $sem_num = ($m_num <= 6) ? 1 : 2;
    $month_label = $sem_num . 'º Semestre ' . $m_year . ' (Jan–Jun)';
    if ($sem_num == 2)
        $month_label = '2º Semestre ' . $m_year . ' (Jul–Dez)';
}
?>

<div class="filter-header">
    <form method="GET" class="search-group" id="filter_form">
        <input type="hidden" name="view_mode" value="<?php echo $view_mode; ?>">
        <input type="hidden" name="month" value="<?php echo $current_month; ?>">

        <div style="display: flex; align-items: center; gap: 8px; background: var(--bg-color); border: 1px solid var(--border-color); padding: 2px 10px; border-radius: 8px;">
            <i class="fas fa-search" style="opacity: 0.5;"></i>
            <input type="text" name="search" placeholder="Buscar por professor..." value="<?php echo htmlspecialchars($search_name); ?>" onchange="this.form.submit()" style="border:none; background:transparent; padding: 8px 5px;">
        </div>

        <select name="especialidade" onchange="this.form.submit()" style="min-width: 180px;">
            <option value="">Todas Especialidades</option>
            <?php foreach ($especialidades as $esp): ?>
                <option value="<?php echo htmlspecialchars($esp['especialidade']); ?>" <?php echo $filter_especialidade == $esp['especialidade'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($esp['especialidade']); ?>
                </option>
            <?php
endforeach; ?>
        </select>
        
        <select name="ordem_disp" onchange="this.form.submit()">
            <option value="mais" <?php echo $ordem_disp == 'mais' ? 'selected' : ''; ?>>Mais Disponíveis</option>
            <option value="menos" <?php echo $ordem_disp == 'menos' ? 'selected' : ''; ?>>Mais Ocupados</option>
        </select>
    </form>

    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="font-size: 0.85rem; font-weight: 600; opacity: 0.7;">
            Exibindo <?php echo count($professores); ?> de <?php echo $total_count; ?> professores
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="?view_mode=<?php echo $view_mode; ?>&month=<?php echo $prev_month; ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&page=<?php echo $page; ?>" 
               class="month-btn" title="<?php echo $view_mode == 'semestral' ? 'Semestre anterior' : 'Mês anterior'; ?>" style="width:34px; height:34px; text-decoration:none; color:var(--text-color);">
                <i class="fas fa-chevron-left" style="font-size:0.75rem;"></i>
            </a>
            <span style="font-weight: 800; font-size: 0.95rem; min-width: <?php echo $view_mode == 'semestral' ? '220px' : '140px'; ?>; text-align: center; text-transform: capitalize; color: var(--text-color);">
                <?php echo $month_label; ?>
            </span>
            <a href="?view_mode=<?php echo $view_mode; ?>&month=<?php echo $next_month; ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&page=<?php echo $page; ?>" 
               class="month-btn" title="<?php echo $view_mode == 'semestral' ? 'Próximo semestre' : 'Próximo mês'; ?>" style="width:34px; height:34px; text-decoration:none; color:var(--text-color);">
                <i class="fas fa-chevron-right" style="font-size:0.75rem;"></i>
            </a>
        </div>
    </div>
</div>

<div class="table-container">
    <?php if ($view_mode == 'semestral'):
    $cur_m_lbl = (int)date('m', strtotime($current_month . '-01'));
    $cur_y_lbl = date('Y', strtotime($current_month . '-01'));
    $sem_lbl = ($cur_m_lbl <= 6) ? '1º Semestre (Jan – Jun)' : '2º Semestre (Jul – Dez)';
?>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--border-color);">
            <div style="display:flex; align-items:center; gap:10px;">
                <i class="fas fa-calendar-week" style="color: var(--primary-red); font-size: 1.1rem;"></i>
                <span style="font-weight: 800; font-size: 1.05rem;"><?php echo $sem_lbl . ' — ' . $cur_y_lbl; ?></span>
            </div>
            <div style="display: flex; gap: 16px; font-size: 0.75rem; font-weight: 700;">
                <span style="display:flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:2px; background:#e8f5e9; border:1px solid #c8e6c9; display:inline-block;"></span> Livre</span>
                <span style="display:flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:2px; background:#f9a825; display:inline-block;"></span> Parcial</span>
                <span style="display:flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:2px; background:#e53935; display:inline-block;"></span> Ocupado</span>
                <span style="display:flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:2px; background:#fff8e1; border:1px solid #ffe082; display:inline-block;"></span> Sábado</span>
                <span style="display:flex; align-items:center; gap:5px;"><span style="width:10px; height:10px; border-radius:2px; background:#f5f5f5; border:1px solid #e0e0e0; display:inline-block;"></span> Domingo</span>
                <span style="display:flex; align-items:center; gap:5px;"><i class="fas fa-mouse-pointer" style="font-size:0.6rem; color:var(--primary-red);"></i> Clique p/ agendar</span>
            </div>
        </div>
    <?php
endif; ?>
    <?php if (empty($professores)): ?>
        <p style="text-align:center; padding: 50px; opacity:0.5;">Nenhum professor encontrado.</p>
    <?php
else: ?>
        <?php foreach ($professores as $p): ?>
            <?php if ($view_mode == 'calendar'): ?>
                <!-- FULL CALENDAR VIEW (INLINE) -->
                <div class="prof-row" style="border:none;">
                    <div class="prof-info-header">
                        <div class="prof-name"><?php echo htmlspecialchars($p['nome']); ?> <span class="prof-spec"> · <?php echo htmlspecialchars($p['especialidade']); ?></span></div>
                        <div style="font-size: 0.9rem; font-weight: 700;">Calendário de <?php echo date('m/Y', strtotime($current_month)); ?></div>
                    </div>
                    <div id="inline_calendar_<?php echo $p['id']; ?>">
                        <!-- This will be rendered by the same renderCalendarView JS function but called on page load -->
                    </div>
                </div>
            <?php
        elseif ($view_mode == 'semestral'): ?>
                <!-- SEMESTRAL VIEW - compact 6 months per professor -->
                <?php
            $cur_m = (int)date('m', strtotime($current_month . '-01'));
            $cur_y = (int)date('Y', strtotime($current_month . '-01'));
            $semester_start = ($cur_m <= 6) ? 1 : 7;
            $semester_end = $semester_start + 5;
            $semester_label = ($semester_start == 1) ? '1º Semestre' : '2º Semestre';

            $months_pt_sem = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];

            $sem_first = sprintf("%04d-%02d-01", $cur_y, $semester_start);
            $sem_last = date('Y-m-t', strtotime(sprintf("%04d-%02d-01", $cur_y, $semester_end)));
            $stmt_sem = $mysqli->prepare("
                        SELECT a.data, t.nome as turma_nome, a.hora_inicio, a.hora_fim
                        FROM agenda a 
                        JOIN turmas t ON a.turma_id = t.id
                        WHERE (a.professor_id = ? OR a.professor_id_2 = ? OR a.professor_id_3 = ? OR a.professor_id_4 = ?) 
                        AND a.data BETWEEN ? AND ?
                    ");
            $p_id_sem = $p['id'];
            $stmt_sem->bind_param('iiiiss', $p_id_sem, $p_id_sem, $p_id_sem, $p_id_sem, $sem_first, $sem_last);
            $stmt_sem->execute();
            $sem_results = $stmt_sem->get_result()->fetch_all(MYSQLI_ASSOC);
            $sem_busy = [];
            $sem_turno = [];
            foreach ($sem_results as $row) {
                $sem_busy[$row['data']] = $row['turma_nome'];
                if (!isset($sem_turno[$row['data']]))
                    $sem_turno[$row['data']] = ['M' => false, 'T' => false, 'N' => false];
                $hi = $row['hora_inicio'];
                $hf = $row['hora_fim'];
                if ($hi < '12:00:00')
                    $sem_turno[$row['data']]['M'] = true;
                if ($hi < '18:00:00' && $hf > '12:00:00')
                    $sem_turno[$row['data']]['T'] = true;
                if ($hf > '18:00:00' || $hi >= '18:00:00')
                    $sem_turno[$row['data']]['N'] = true;
            }

            // Calculate total stats
            $total_sem_free = 0;
            $total_sem_busy = 0;
            for ($m = $semester_start; $m <= $semester_end; $m++) {
                $ms = sprintf("%04d-%02d", $cur_y, $m);
                $dc = (int)date('t', strtotime($ms . '-01'));
                for ($dd = 1; $dd <= $dc; $dd++) {
                    $ddt = sprintf("%s-%02d", $ms, $dd);
                    $ddow = date('N', strtotime($ddt));
                    if ($ddow < 7 && !isset($sem_busy[$ddt]))
                        $total_sem_free++;
                    if (isset($sem_busy[$ddt]))
                        $total_sem_busy++;
                }
            }
            $total_sem_days = $total_sem_free + $total_sem_busy;
            $perc_free = ($total_sem_days > 0) ? round(($total_sem_free / $total_sem_days) * 100) : 0;
?>
                <div class="prof-row" style="padding-bottom: 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 10px;">
                        <div onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')" style="cursor:pointer; display:flex; align-items:center; gap:10px; flex: 1; min-width: 0;">
                            <div style="width:32px; height:32px; background: linear-gradient(135deg, #e53935, #c62828); color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem; flex-shrink:0;"><?php echo mb_substr($p['nome'], 0, 1); ?></div>
                            <div style="min-width:0;">
                                <div style="font-weight:800; font-size:0.95rem; color:var(--text-color); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($p['nome']); ?></div>
                                <div style="font-size:0.72rem; color:var(--text-muted);"><?php echo htmlspecialchars($p['especialidade']); ?></div>
                            </div>
                        </div>
                        <div style="display:flex; gap:12px; font-size:0.75rem; font-weight:700; flex-shrink:0;">
                            <span style="color:#2e7d32;" title="Dias livres no semestre"><i class="fas fa-check-circle"></i> <?php echo $total_sem_free; ?></span>
                            <span style="color:#d32f2f;" title="Dias ocupados no semestre"><i class="fas fa-times-circle"></i> <?php echo $total_sem_busy; ?></span>
                            <span style="color:var(--text-muted);" title="Disponibilidade"><?php echo $perc_free; ?>%</span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        <?php for ($m = $semester_start; $m <= $semester_end; $m++):
                $month_str = sprintf("%04d-%02d", $cur_y, $m);
                $days_count = (int)date('t', strtotime($month_str . '-01'));
                $first_dow = (int)date('w', strtotime($month_str . '-01')); // 0=Sun

                // Count free/busy for this month
                $m_free = 0;
                $m_busy = 0;
                for ($dd = 1; $dd <= $days_count; $dd++) {
                    $ddt = sprintf("%s-%02d", $month_str, $dd);
                    $ddow = (int)date('N', strtotime($ddt));
                    if ($ddow < 7 && !isset($sem_busy[$ddt]))
                        $m_free++;
                    if (isset($sem_busy[$ddt]))
                        $m_busy++;
                }
?>
                        <div class="sem-month-box">
                            <div class="sem-month-header">
                                <span><?php echo $months_pt_sem[$m]; ?></span>
                                <span class="sem-month-stats">
                                    <span style="color:#2e7d32;"><?php echo $m_free; ?></span>/<span style="color:#d32f2f;"><?php echo $m_busy; ?></span>
                                </span>
                            </div>
                            <div class="sem-day-headers">
                                <span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>
                            </div>
                            <div class="sem-calendar-grid">
                                <?php for ($e = 0; $e < $first_dow; $e++): ?>
                                    <div class="sem-day sem-day-empty"></div>
                                <?php
                endfor; ?>
                                <?php for ($dd = 1; $dd <= $days_count; $dd++):
                    $ddt = sprintf("%s-%02d", $month_str, $dd);
                    $ddow = (int)date('N', strtotime($ddt));
                    $is_sunday = ($ddow == 7);
                    $is_saturday = ($ddow == 6);
                    $is_busy = isset($sem_busy[$ddt]);

                    // Check partial (has turnos but not all blocked)
                    $st = isset($sem_turno[$ddt]) ? $sem_turno[$ddt] : null;
                    $s_full = $st && ((($st['M'] ? 1 : 0) + ($st['T'] ? 1 : 0) + ($st['N'] ? 1 : 0)) >= 2);
                    $is_partial_sem = $is_busy && !$s_full;

                    $cell_class = 'sem-day-free';
                    if ($is_sunday)
                        $cell_class = 'sem-day-sunday';
                    elseif ($is_busy && $is_partial_sem)
                        $cell_class = 'sem-day-partial';
                    elseif ($is_busy)
                        $cell_class = 'sem-day-busy';
                    elseif ($is_saturday)
                        $cell_class = 'sem-day-weekend';

                    $tip = $dd . ' ' . $months_pt_sem[$m] . ' - ' . ($is_busy ? htmlspecialchars($sem_busy[$ddt]) . ($is_partial_sem ? ' (parcial)' : '') : ($is_sunday ? 'Domingo' : 'Livre'));
                    $clickable = (!$is_sunday && (!$is_busy || $is_partial_sem));
?>
                                    <div class="sem-day <?php echo $cell_class; ?>"
                                         title="<?php echo $tip; ?>"
                                         <?php if ($clickable): ?>onclick="openScheduleModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>', '<?php echo $ddt; ?>')"<?php
                    endif; ?>>
                                        <?php echo $dd; ?>
                                    </div>
                                <?php
                endfor; ?>
                            </div>
                        </div>
                        <?php
            endfor; ?>
                    </div>
                </div>
            <?php
        elseif ($view_mode == 'blocks'): ?>
                <!-- BLOCOS CONTÍNUOS VIEW -->
                <div class="prof-row">
                    <div class="prof-info-header">
                        <div class="prof-name" onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')">
                            <?php echo htmlspecialchars($p['nome']); ?>
                            <span class="prof-spec"> · <?php echo htmlspecialchars($p['especialidade']); ?></span>
                        </div>
                        <div style="font-size: 0.85rem; font-weight: 700;">
                        <?php
            $livres = 0;
            for ($d = 1; $d <= $days_in_month; $d++) {
                $dt = sprintf("%s-%02d", $current_month, $d);
                $dow = date('N', strtotime($dt));
                if ($dow < 7 && !isset($agenda_data[$p['id']][$dt]))
                    $livres++;
            }
?>
                        <span style="color: #2e7d32;"><?php echo $livres; ?> dias livres</span>
                        <button class="btn-nav" style="width: 30px; height: 30px; display: inline-flex; margin-left:10px;" onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')" title="Ver Calendário do Mês">
                            <i class="fas fa-calendar-alt" style="font-size: 0.9rem;"></i>
                        </button>
                    </div>
                </div>

                <?php
            $_ts = isset($turno_summary[$p['id']]) ? $turno_summary[$p['id']] : ['M' => 0, 'T' => 0, 'N' => 0];
            $_nblk = 0;
            $_mblk = 0;
            if (isset($turno_detail[$p['id']])) {
                foreach ($turno_detail[$p['id']] as $_td) {
                    if ($_td['M'] && !$_td['N'])
                        $_nblk++;
                    if ($_td['N'] && !$_td['M'])
                        $_mblk++;
                }
            }
            $_pills = [
                ['icon' => 'fa-sun', 'label' => 'Manhã', 'count' => $_ts['M'], 'blocked' => $_mblk],
                ['icon' => 'fa-cloud-sun', 'label' => 'Tarde', 'count' => $_ts['T'], 'blocked' => 0],
                ['icon' => 'fa-moon', 'label' => 'Noite', 'count' => $_ts['N'], 'blocked' => $_nblk],
            ];
?>
                <div class="turno-summary">
                    <?php foreach ($_pills as $_pl):
                $_pc = $_pl['count'] > 0 ? 'turno-pill-active' : ($_pl['blocked'] > 0 ? 'turno-pill-blocked' : 'turno-pill-free');
?>
                    <span class="turno-pill <?php echo $_pc; ?>">
                        <i class="fas <?php echo $_pl['icon']; ?>"></i> <?php echo $_pl['label']; ?>
                        <?php if ($_pl['count'] > 0): ?><strong><?php echo $_pl['count']; ?>d</strong>
                        <?php
                elseif ($_pl['blocked'] > 0): ?><i class="fas fa-lock" style="font-size:0.55rem;"></i> <?php echo $_pl['blocked']; ?>d bloq.
                        <?php
                else: ?>Livre<?php
                endif; ?>
                    </span>
                    <?php
            endforeach; ?>
                </div>

                <?php
            // Build continuous blocks
            $blocks = [];
            $cur_block = null;
            for ($i = 1; $i <= $days_in_month; $i++) {
                $dt = sprintf("%s-%02d", $current_month, $i);
                $dow = date('N', strtotime($dt));
                $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;
                $td_info2 = isset($turno_detail[$p['id']][$dt]) ? $turno_detail[$p['id']][$dt] : null;
                $b_m = $td_info2 && $td_info2['M'];
                $b_t = $td_info2 && $td_info2['T'];
                $b_n = $td_info2 && $td_info2['N'];
                $b_full = (($b_m ? 1 : 0) + ($b_t ? 1 : 0) + ($b_n ? 1 : 0)) >= 2;
                $b_partial = $is_busy && !$b_full;

                if ($dow == 7) {
                    $status = 'sunday';
                    $label = 'Bloqueado';
                }
                elseif ($is_busy && $b_partial) {
                    $status = 'partial:' . $is_busy;
                    $label = $is_busy . ' (parcial)';
                }
                elseif ($is_busy) {
                    $status = 'busy:' . $is_busy;
                    $label = $is_busy;
                }
                else {
                    $status = 'free';
                    $label = 'Livre';
                }

                if ($cur_block && $cur_block['status'] === $status) {
                    $cur_block['end'] = $i;
                    $cur_block['count']++;
                }
                else {
                    if ($cur_block)
                        $blocks[] = $cur_block;
                    $cur_block = ['start' => $i, 'end' => $i, 'status' => $status, 'label' => $label, 'count' => 1];
                }
            }
            if ($cur_block)
                $blocks[] = $cur_block;
?>
                <div class="blocks-bar-wrapper">
                    <?php foreach ($blocks as $block):
                $range_text = ($block['start'] == $block['end']) ? $block['start'] : $block['start'] . '–' . $block['end'];
                if (strpos($block['status'], 'busy:') === 0) {
                    $bclass = 'block-seg-busy';
                }
                elseif (strpos($block['status'], 'partial:') === 0) {
                    $bclass = 'block-seg-partial';
                }
                elseif ($block['status'] === 'sunday') {
                    $bclass = 'block-seg-sunday';
                }
                else {
                    $bclass = 'block-seg-free';
                }
                $first_dt = sprintf("%s-%02d", $current_month, $block['start']);
                $is_clickable = ($block['status'] === 'free' || strpos($block['status'], 'partial:') === 0);
?>
                        <div class="block-seg <?php echo $bclass; ?>" style="flex: <?php echo $block['count']; ?>;" title="<?php echo $range_text; ?>: <?php echo htmlspecialchars($block['label']); ?>"
                             <?php if ($is_clickable): ?>onclick="openScheduleModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>', '<?php echo $first_dt; ?>')"<?php
                endif; ?>>
                            <span class="block-range"><?php echo $range_text; ?></span>
                            <span class="block-label"><?php echo htmlspecialchars($block['label']); ?></span>
                        </div>
                    <?php
            endforeach; ?>
                </div>

                <!-- Turno per-day indicator bar for Blocks view -->
                <div class="turno-bar-wrapper" title="Indicador de Turnos: Manhã / Tarde / Noite">
                    <?php for ($ti = 1; $ti <= $days_in_month; $ti++):
                $tdt = sprintf("%s-%02d", $current_month, $ti);
                $tdow = (int)date('N', strtotime($tdt));
                $ttd = isset($turno_detail[$p['id']][$tdt]) ? $turno_detail[$p['id']][$tdt] : null;
                $thm = $ttd && $ttd['M'];
                $tht = $ttd && $ttd['T'];
                $thn = $ttd && $ttd['N'];
                $tmb = $thn && !$thm;
                $tnb = $thm && !$thn;
                $twk = ($tdow >= 6);
?>
                    <div class="turno-day-col">
                        <div class="turno-dot <?php echo $twk ? 'turno-wknd' : ($thm ? 'turno-occ' : ($tmb ? 'turno-blk' : 'turno-avl')); ?>" title="Dia <?php echo $ti; ?> Manhã: <?php echo $thm ? htmlspecialchars($ttd['M']) : ($tmb ? 'Bloqueado' : 'Livre'); ?>"></div>
                        <div class="turno-dot <?php echo $twk ? 'turno-wknd' : ($tht ? 'turno-occ' : 'turno-avl'); ?>" title="Dia <?php echo $ti; ?> Tarde: <?php echo $tht ? htmlspecialchars($ttd['T']) : 'Livre'; ?>"></div>
                        <div class="turno-dot <?php echo $twk ? 'turno-wknd' : ($thn ? 'turno-occ' : ($tnb ? 'turno-blk' : 'turno-avl')); ?>" title="Dia <?php echo $ti; ?> Noite: <?php echo $thn ? htmlspecialchars($ttd['N']) : ($tnb ? 'Bloqueado' : 'Livre'); ?>"></div>
                    </div>
                    <?php
            endfor; ?>
                </div>
                <div class="turno-labels">
                    <span>☀ Manhã · ☁ Tarde · ☽ Noite</span>
                    <span style="display:flex;gap:8px;">
                        <span><span style="display:inline-block;width:8px;height:8px;background:#4caf50;border-radius:1px;"></span> Livre</span>
                        <span><span style="display:inline-block;width:8px;height:8px;background:#f9a825;border-radius:1px;"></span> Parcial</span>
                        <span><span style="display:inline-block;width:8px;height:8px;background:#e53935;border-radius:1px;"></span> Ocup.</span>
                        <span><span style="display:inline-block;width:8px;height:8px;background:#e0e0e0;border-radius:1px;"></span> Bloq.</span>
                    </span>
                </div>

            </div>

            <?php
        else: ?>
                <!-- TIMELINE VIEW -->
                <div class="prof-row">
                    <div class="prof-info-header">
                        <div class="prof-name" onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')">
                            <?php echo htmlspecialchars($p['nome']); ?>
                            <span class="prof-spec"> · <?php echo htmlspecialchars($p['especialidade']); ?></span>
                        </div>
                        <div style="font-size: 0.85rem; font-weight: 700;">
                        <?php
            $livres = 0;
            for ($d = 1; $d <= $days_in_month; $d++) {
                $dt = sprintf("%s-%02d", $current_month, $d);
                $dow = date('N', strtotime($dt));
                if ($dow < 7 && !isset($agenda_data[$p['id']][$dt]))
                    $livres++;
            }
?>
                        <span style="color: #2e7d32;"><?php echo $livres; ?> dias livres</span>
                        <button class="btn-nav" style="width: 30px; height: 30px; display: inline-flex; margin-left:10px;" onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')" title="Ver Calendário do Mês">
                            <i class="fas fa-calendar-alt" style="font-size: 0.9rem;"></i>
                        </button>
                    </div>
                </div>

                <?php
            $_ts2 = isset($turno_summary[$p['id']]) ? $turno_summary[$p['id']] : ['M' => 0, 'T' => 0, 'N' => 0];
            $_nblk2 = 0;
            $_mblk2 = 0;
            if (isset($turno_detail[$p['id']])) {
                foreach ($turno_detail[$p['id']] as $_td2) {
                    if ($_td2['M'] && !$_td2['N'])
                        $_nblk2++;
                    if ($_td2['N'] && !$_td2['M'])
                        $_mblk2++;
                }
            }
            $_pills2 = [
                ['icon' => 'fa-sun', 'label' => 'Manhã', 'count' => $_ts2['M'], 'blocked' => $_mblk2],
                ['icon' => 'fa-cloud-sun', 'label' => 'Tarde', 'count' => $_ts2['T'], 'blocked' => 0],
                ['icon' => 'fa-moon', 'label' => 'Noite', 'count' => $_ts2['N'], 'blocked' => $_nblk2],
            ];
?>
                <div class="turno-summary">
                    <?php foreach ($_pills2 as $_pl2):
                $_pc2 = $_pl2['count'] > 0 ? 'turno-pill-active' : ($_pl2['blocked'] > 0 ? 'turno-pill-blocked' : 'turno-pill-free');
?>
                    <span class="turno-pill <?php echo $_pc2; ?>">
                        <i class="fas <?php echo $_pl2['icon']; ?>"></i> <?php echo $_pl2['label']; ?>
                        <?php if ($_pl2['count'] > 0): ?><strong><?php echo $_pl2['count']; ?>d</strong>
                        <?php
                elseif ($_pl2['blocked'] > 0): ?><i class="fas fa-lock" style="font-size:0.55rem;"></i> <?php echo $_pl2['blocked']; ?>d bloq.
                        <?php
                else: ?>Livre<?php
                endif; ?>
                    </span>
                    <?php
            endforeach; ?>
                </div>
                
                <div class="timeline-bar-wrapper">
                    <?php for ($i = 1; $i <= $days_in_month; $i++):
                $dt = sprintf("%s-%02d", $current_month, $i);
                $dow = date('N', strtotime($dt));
                $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;
                $td_info = isset($turno_detail[$p['id']][$dt]) ? $turno_detail[$p['id']][$dt] : null;

                // Determine if partially occupied (has some turnos free)
                $has_m = $td_info && $td_info['M'];
                $has_t = $td_info && $td_info['T'];
                $has_n = $td_info && $td_info['N'];
                $all_full = (($has_m ? 1 : 0) + ($has_t ? 1 : 0) + ($has_n ? 1 : 0)) >= 2;
                $is_partial = $is_busy && !$all_full;

                $class = "bar-seg-free";
                $title = "Livre: " . $i;
                $onclick = "onclick=\"openScheduleModal({$p['id']}, '" . addslashes($p['nome']) . "', '{$dt}')\"";

                if ($dow == 7) {
                    $class = "bar-seg-sunday";
                    $title = "DOMINGO: " . $i;
                    $onclick = "";
                }
                elseif ($is_busy && $is_partial) {
                    $class = "bar-seg-partial";
                    $turnos_str = ($has_m ? 'M' : '') . ($has_t ? 'T' : '') . ($has_n ? 'N' : '');
                    $title = "PARCIAL [$turnos_str]: $is_busy (Dia $i) — Clique p/ agendar outro turno";
                // still clickable
                }
                elseif ($is_busy) {
                    $class = "bar-seg-busy";
                    $title = "OCUPADO: " . $is_busy;
                    $onclick = "";
                }
                elseif ($dow == 6) {
                    $class = "bar-seg-weekend";
                    $title = "Sábado: " . $i;
                }
?>
                        <div class="bar-seg <?php echo $class; ?>" title="<?php echo $title; ?>" <?php echo $onclick; ?>>
                            <?php echo $i; ?>
                        </div>
                    <?php
            endfor; ?>
                </div>

                <!-- Turno per-day indicator bar (Manhã / Tarde / Noite) -->
                <div class="turno-bar-wrapper" title="Indicador de Turnos: Manhã / Tarde / Noite">
                    <?php for ($ti = 1; $ti <= $days_in_month; $ti++):
                $tdt = sprintf("%s-%02d", $current_month, $ti);
                $tdow = (int)date('N', strtotime($tdt));
                $ttd = isset($turno_detail[$p['id']][$tdt]) ? $turno_detail[$p['id']][$tdt] : null;
                $thm = $ttd && $ttd['M'];
                $tht = $ttd && $ttd['T'];
                $thn = $ttd && $ttd['N'];
                $tmb = $thn && !$thm;
                $tnb = $thm && !$thn;
                $twk = ($tdow >= 6);
?>
                    <div class="turno-day-col">
                        <div class="turno-dot <?php echo $twk ? 'turno-wknd' : ($thm ? 'turno-occ' : ($tmb ? 'turno-blk' : 'turno-avl')); ?>" title="Dia <?php echo $ti; ?> Manhã: <?php echo $thm ? htmlspecialchars($ttd['M']) : ($tmb ? 'Bloqueado' : 'Livre'); ?>"></div>
                        <div class="turno-dot <?php echo $twk ? 'turno-wknd' : ($tht ? 'turno-occ' : 'turno-avl'); ?>" title="Dia <?php echo $ti; ?> Tarde: <?php echo $tht ? htmlspecialchars($ttd['T']) : 'Livre'; ?>"></div>
                        <div class="turno-dot <?php echo $twk ? 'turno-wknd' : ($thn ? 'turno-occ' : ($tnb ? 'turno-blk' : 'turno-avl')); ?>" title="Dia <?php echo $ti; ?> Noite: <?php echo $thn ? htmlspecialchars($ttd['N']) : ($tnb ? 'Bloqueado' : 'Livre'); ?>"></div>
                    </div>
                    <?php
            endfor; ?>
                </div>
                <div class="turno-labels">
                    <span>☀ Manhã · ☁ Tarde · ☽ Noite</span>
                    <span style="display:flex;gap:8px;">
                        <span><span style="display:inline-block;width:8px;height:8px;background:#4caf50;border-radius:1px;"></span> Livre</span>
                        <span><span style="display:inline-block;width:8px;height:8px;background:#f9a825;border-radius:1px;"></span> Parcial</span>
                        <span><span style="display:inline-block;width:8px;height:8px;background:#e53935;border-radius:1px;"></span> Ocup.</span>
                        <span><span style="display:inline-block;width:8px;height:8px;background:#e0e0e0;border-radius:1px;"></span> Bloq.</span>
                    </span>
                </div>

            </div>
            <?php
        endif; ?>
        <?php
    endforeach; ?>
    <?php
endif; ?>
</div>

<!-- Pagination / Seta Navegação -->
<?php

$total_pages = ceil($total_count / $limit);
if ($total_pages > 1):

?>
<div class="pagination">
    <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?>" 
       class="btn-nav <?php echo $page <= 1 ? 'disabled' : ''; ?>">
        <i class="fas fa-chevron-left"></i>
    </a>
    <span style="font-weight: 700;">Página <?php echo $page; ?> de <?php echo $total_pages; ?></span>
    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?>" 
       class="btn-nav <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
        <i class="fas fa-chevron-right"></i>
    </a>
</div>
<?php
endif; ?>

<!-- MODAL 1: Timeline em Barra -->
<div id="timelineModal" class="modal">
    <div class="modal-content" style="max-width: 95%; width: 1200px;">
        <span class="close-modal" onclick="closeModal('timelineModal')">&times;</span>
        
        <div class="month-nav">
            <button class="month-btn" id="prev_month_btn"><i class="fas fa-chevron-left"></i></button>
            <h2 id="timeline_prof_name" style="margin: 0; min-width: 280px;">Timeline</h2>
            <button class="month-btn" id="next_month_btn"><i class="fas fa-chevron-right"></i></button>
        </div>

        <div class="timeline-legend">
            <div class="legend-item"><div class="legend-box" style="background:#2e7d32;"></div><span>Livre</span></div>
            <div class="legend-item"><div class="legend-box" style="background:#f9a825;"></div><span>Parcial (clicável)</span></div>
            <div class="legend-item"><div class="legend-box" style="background:#ff1c1c;"></div><span>Ocupado</span></div>
            <div class="legend-item"><div class="legend-box calendar-day-weekend" style="border:1px solid #ccc;"></div><span>Sábado (Livre)</span></div>
            <div class="legend-item"><div class="legend-box calendar-day-weekendd" style="background:#ffcdd2;"></div><span>Domingo (Bloqueado)</span></div>
            <div class="legend-item" style="margin-left:10px;">
                <span style="display:flex;gap:2px;">
                    <span style="width:10px;height:6px;border-radius:2px;background:#e53935;display:block;"></span>
                    <span style="width:10px;height:6px;border-radius:2px;background:#4caf50;display:block;"></span>
                    <span style="width:10px;height:6px;border-radius:2px;background:#e0e0e0;display:block;"></span>
                </span>
                <span>Turnos (M·T·N)</span>
            </div>
        </div>
        
        <div id="calendar_render_area">
            <!-- Calendar will be injected here by JS -->
        </div>
        
        <p style="margin-top: 50px; font-size: 0.85rem; opacity: 0.6;">
            <i class="fas fa-info-circle"></i> Passe o mouse para ver detalhes e clique nas áreas verdes para agendar.
        </p>
    </div>
</div>

<!-- MODAL 2: Agendamento Multi-Dia -->
<div id="scheduleModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <span class="close-modal" onclick="closeModal('scheduleModal')">&times;</span>
        <h2 style="margin-bottom: 15px;"><i class="fas fa-calendar-plus" style="color: var(--primary-red);"></i> Agendar Período</h2>
        <div style="background: rgba(46, 125, 50, 0.05); padding: 15px; border-radius: 10px; border-left: 4px solid #2e7d32; margin-bottom: 25px;">
            <p id="schedule_info" style="font-weight: 700; color: #2e7d32;"></p>
        </div>
        
        <form action="planejamento_process.php" method="POST">
            <input type="hidden" name="is_quick" value="1">

            <!-- Filtro por Especialidade + Professores Cascata -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 0.95rem;">
                    <i class="fas fa-chalkboard-teacher" style="color: var(--primary-red);"></i> Professores Responsáveis
                </label>

                <!-- Filtro Especialidade no Modal -->
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 3px;">
                        <i class="fas fa-filter"></i> Filtrar por Especialidade
                    </label>
                    <select id="modal_especialidade_filter" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); font-weight: 600;">
                        <option value="">Todas as Especialidades</option>
                        <?php foreach ($all_especialidades_modal as $esp_m): ?>
                            <option value="<?php echo htmlspecialchars($esp_m['especialidade']); ?>">
                                <?php echo htmlspecialchars($esp_m['especialidade']); ?>
                            </option>
                        <?php
endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 600; color: var(--primary-red); display: block; margin-bottom: 3px;">1º Professor (Obrigatório)</label>
                        <select name="professor_id" id="form_prof_id" required class="modal-prof-select" style="width: 100%; padding: 10px; border-radius: 8px; border: 2px solid var(--primary-red); background: var(--bg-color); color: var(--text-color); font-weight: 600;">
                            <option value="">Selecione...</option>
                            <?php foreach ($all_profs as $ap): ?>
                                <option value="<?php echo $ap['id']; ?>" data-especialidade="<?php echo htmlspecialchars($ap['especialidade']); ?>"><?php echo htmlspecialchars($ap['nome']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 3px;">2º Professor (Opcional)</label>
                        <select name="professor_id_2" class="modal-prof-select" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                            <option value="">— Nenhum —</option>
                            <?php foreach ($all_profs as $ap): ?>
                                <option value="<?php echo $ap['id']; ?>" data-especialidade="<?php echo htmlspecialchars($ap['especialidade']); ?>"><?php echo htmlspecialchars($ap['nome']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 3px;">3º Professor (Opcional)</label>
                        <select name="professor_id_3" class="modal-prof-select" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                            <option value="">— Nenhum —</option>
                            <?php foreach ($all_profs as $ap): ?>
                                <option value="<?php echo $ap['id']; ?>" data-especialidade="<?php echo htmlspecialchars($ap['especialidade']); ?>"><?php echo htmlspecialchars($ap['nome']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 3px;">4º Professor (Opcional)</label>
                        <select name="professor_id_4" class="modal-prof-select" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                            <option value="">— Nenhum —</option>
                            <?php foreach ($all_profs as $ap): ?>
                                <option value="<?php echo $ap['id']; ?>" data-especialidade="<?php echo htmlspecialchars($ap['especialidade']); ?>"><?php echo htmlspecialchars($ap['nome']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Data Inicial</label>
                    <input type="date" name="data_inicio" id="form_date_start" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Data Final</label>
                    <input type="date" name="data_fim" id="form_date_end" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 0.95rem;">
                    <i class="fas fa-calendar-week" style="color: var(--primary-red);"></i> Dias da Semana
                    <span style="font-size: 0.75rem; font-weight: 500; color: var(--text-muted); margin-left: 6px;">Selecione um ou mais</span>
                </label>
                <div id="weekday_checkboxes" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; background: var(--bg-color); padding: 12px; border-radius: 10px;">
                    <?php
$wk_days = [
    1 => ['name' => 'Seg', 'full' => 'Segunda', 'checked' => true],
    2 => ['name' => 'Ter', 'full' => 'Terça', 'checked' => true],
    3 => ['name' => 'Qua', 'full' => 'Quarta', 'checked' => true],
    4 => ['name' => 'Qui', 'full' => 'Quinta', 'checked' => true],
    5 => ['name' => 'Sex', 'full' => 'Sexta', 'checked' => true],
    6 => ['name' => 'Sáb', 'full' => 'Sábado', 'checked' => false],
];
foreach ($wk_days as $wd_num => $wd_info): ?>
                    <div id="weekday_card_<?php echo $wd_num; ?>" class="weekday-card" onclick="toggleWeekdayCard(<?php echo $wd_num; ?>)">
                        <i class="fas fa-lock wc-lock-icon"></i>
                        <input type="checkbox" name="dias_semana[]" id="weekday_<?php echo $wd_num; ?>" value="<?php echo $wd_num; ?>" <?php echo $wd_info['checked'] ? 'checked' : ''; ?> style="margin: 0; cursor: pointer;" onclick="event.stopPropagation();">
                        <div class="wc-day-name"><?php echo $wd_info['name']; ?></div>
                        <div id="weekday_turno_<?php echo $wd_num; ?>" class="wc-turno-row">
                            <!-- Turno badges will be injected by JS -->
                        </div>
                        <div id="weekday_count_<?php echo $wd_num; ?>" class="wc-count"></div>
                    </div>
                    <?php
endforeach; ?>
                </div>
                <div id="weekday_blocking_info" style="display:none; margin-top: 10px; padding: 12px 16px; background: rgba(255, 152, 0, 0.06); border-radius: 10px; border-left: 4px solid #f9a825; font-size: 0.82rem; color: #e65100;">
                    <i class="fas fa-info-circle"></i> <span id="weekday_blocking_text"></span>
                </div>
                <div style="display:flex; gap: 12px; margin-top: 8px; font-size: 0.72rem; font-weight: 600; color: var(--text-muted); justify-content: center;">
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#e53935;"></span> Ocupado</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ff6f00;"></span> Conflito c/ horário</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#4caf50;"></span> Livre</span>
                    <span><i class="fas fa-lock" style="font-size:0.6rem; color:#e53935;"></i> Bloqueado</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Turma / Curso</label>
                    <select name="turma_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                        <option value="">Selecione...</option>
                        <?php foreach ($turmas_select as $t): ?>
                            <option value="<?php echo $t['id']; ?>">
                                <?php echo htmlspecialchars($t['nome']); ?>
                            </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ambiente (Sala)</label>
                    <select name="sala_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                        <option value="">Selecione...</option>
                        <?php foreach ($salas_select as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nome']); ?></option>
                        <?php
endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Horário Início</label>
                    <input type="time" name="hora_inicio" id="form_hora_inicio" value="08:00" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Horário Fim</label>
                    <input type="time" name="hora_fim" id="form_hora_fim" value="12:00" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                </div>
            </div>

            <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <button type="button" class="btn" onclick="closeModal('scheduleModal')" style="padding: 12px 25px; margin-right: 10px;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="padding: 12px 35px; background: #2e7d32; border-color: #1b5e20;">
                    <i class="fas fa-check-double"></i> Confirmar Agendamento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const currentMonthStart = "<?php echo $current_month; ?>";
let currentProfId = null;
let currentProfNome = "";
let currentViewMonth = "<?php echo $current_month; ?>";

function openTimelineModal(profId, profNome) {
    currentProfId = profId;
    currentProfNome = profNome;
    // Inicializar modal com o mês atual da tabela
    currentViewMonth = currentMonthStart;
    
    updateModalTitle();
    fetchNewAvailability(); // Agora chama AJAX corretamente
    document.getElementById('timelineModal').style.display = 'block';
}

function updateModalTitle() {
    const dateObj = new Date(currentViewMonth + "-01T00:00:00");
    const monthName = dateObj.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
    document.getElementById('timeline_prof_name').innerHTML = `<div style="font-size:0.9rem; opacity:0.7;">${currentProfNome}</div><div style="text-transform:capitalize;">${monthName}</div>`;
}

document.getElementById('prev_month_btn').onclick = () => changeMonth(-1);
document.getElementById('next_month_btn').onclick = () => changeMonth(1);

function changeMonth(delta) {
    let [year, month] = currentViewMonth.split('-').map(Number);
    month += delta;
    if (month > 12) { month = 1; year++; }
    if (month < 1) { month = 12; year--; }
    currentViewMonth = `${year}-${String(month).padStart(2, '0')}`;
    
    updateModalTitle();
    fetchNewAvailability();
}

async function fetchNewAvailability() {
    const container = document.getElementById('calendar_render_area');
    if (!container) return;
    container.style.opacity = '0.5';
    
    try {
        const response = await fetch(`?ajax_availability=1&prof_id=${currentProfId}&month=${currentViewMonth}`);
        const data = await response.json();
        renderCalendarView(currentProfId, currentProfNome, currentViewMonth, data.busy, 'calendar_render_area', data.turnos || {});
    } catch (e) {
        console.error(e);
        alert("Erro ao carregar dados de disponibilidade.");
    } finally {
        container.style.opacity = '1';
    }
}

function renderCalendarView(profId, profNome, monthStr, busyDays, targetContainerId = 'calendar_render_area', turnoData = {}) {
    const container = document.getElementById(targetContainerId);
    
    const date = new Date(monthStr + "-01T00:00:00");
    const firstDayOfWeek = date.getDay();
    const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    
    const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    
    let html = `
        <div class="calendar-container">
            <div class="calendar-header-grid">
                ${dayNames.map(d => `<div>${d}</div>`).join('')}
            </div>
            <div class="calendar-grid">
    `;
    
    for (let i = 0; i < firstDayOfWeek; i++) {
        html += `<div class="calendar-day calendar-day-empty"></div>`;
    }
    
    for (let i = 1; i <= daysInMonth; i++) {
        const dStr = `${monthStr}-${String(i).padStart(2, '0')}`;
        const dObj = new Date(dStr + "T00:00:00");
        const dow = dObj.getDay();
        const isBusy = busyDays[dStr];
        const turno = turnoData[dStr] || null;
        
        const isSunday = (dow === 0);
        const isSaturday = (dow === 6);
        const isWeekend = isSunday || isSaturday;
        
        // Check partial: has some turnos but not fully blocked
        const hasM = turno && turno.M;
        const hasT = turno && turno.T;
        const hasN = turno && turno.N;
        const allFull = ((hasM?1:0) + (hasT?1:0) + (hasN?1:0)) >= 2;
        const isPartial = isBusy && !allFull;
        
        let statusClass, weekendClass = '', tooltip = '', statusLabel = 'Livre', clickable = false;
        
        if (isSunday) {
            statusClass = 'calendar-day-busy';
            weekendClass = 'calendar-day-weekendd';
            tooltip = '(DOMINGO)';
            statusLabel = 'Bloqueado';
        } else if (isBusy && isPartial) {
            statusClass = 'calendar-day-partial';
            weekendClass = '';
            const tStr = (hasM ? '☀' : '') + (hasT ? '☁' : '') + (hasN ? '☽' : '');
            tooltip = 'PARCIAL [' + tStr + ']: ' + isBusy + ' — Clique p/ agendar outro turno';
            statusLabel = 'Parcial';
            clickable = true;
        } else if (isBusy) {
            statusClass = 'calendar-day-busy';
            weekendClass = isWeekend ? 'calendar-day-weekend' : '';
            tooltip = 'OCUPADO: ' + isBusy;
            statusLabel = 'Ocupado';
        } else {
            statusClass = 'calendar-day-free';
            weekendClass = isSaturday ? 'calendar-day-weekend' : '';
            tooltip = 'LIVRE: Clique para agendar';
            clickable = true;
        }
        
        // Turno indicator dots for this day
        let turnoHtml = '';
        if (!isSunday && isBusy && turno) {
            const mColor = hasM ? '#e53935' : (hasN ? '#e0e0e0' : '#4caf50');
            const tColor = hasT ? '#e53935' : '#4caf50';
            const nColor = hasN ? '#e53935' : (hasM ? '#e0e0e0' : '#4caf50');
            turnoHtml = `<div style="display:flex;gap:3px;margin-top:4px;">
                <span title="Manhã: ${hasM ? 'Ocupado' : (hasN ? 'Bloqueado' : 'Livre')}" style="width:10px;height:6px;border-radius:2px;background:${mColor};"></span>
                <span title="Tarde: ${hasT ? 'Ocupado' : 'Livre'}" style="width:10px;height:6px;border-radius:2px;background:${tColor};"></span>
                <span title="Noite: ${hasN ? 'Ocupado' : (hasM ? 'Bloqueado' : 'Livre')}" style="width:10px;height:6px;border-radius:2px;background:${nColor};"></span>
            </div>`;
        }
        
        html += `
            <div class="calendar-day ${statusClass} ${weekendClass}" 
                 ${clickable ? `onclick="openScheduleModal(${profId}, '${profNome}', '${dStr}')"` : ''}
                 title="${tooltip}">
                <div class="day-number">${i}</div>
                <div class="day-status-label">${statusLabel}</div>
                ${turnoHtml}
            </div>`;
    }
    
    html += `</div></div>`;
    container.innerHTML = html;
}

function openScheduleModal(profId, profNome, date) {
    document.getElementById('form_prof_id').value = profId;
    
    // Calculate the week (Monday–Saturday) containing the clicked date
    const clickedDate = new Date(date + 'T00:00:00');
    let dow = clickedDate.getDay(); // 0=Sun, 1=Mon, ..., 6=Sat
    if (dow === 0) dow = 7; // Treat Sunday as 7
    
    // Monday of this week
    const monday = new Date(clickedDate);
    monday.setDate(clickedDate.getDate() - (dow - 1));
    
    // Saturday of this week
    const saturday = new Date(monday);
    saturday.setDate(monday.getDate() + 5);
    
    const formatDate = (d) => d.toISOString().split('T')[0];
    const weekStart = formatDate(monday);
    const weekEnd = formatDate(saturday);
    
    document.getElementById('form_date_start').value = weekStart;
    document.getElementById('form_date_end').value = weekEnd;
    document.getElementById('schedule_info').innerHTML = `<i class="fas fa-user"></i> Professor: ${profNome} <br> <i class="fas fa-calendar-alt"></i> Semana: ${monday.toLocaleDateString('pt-BR')} – ${saturday.toLocaleDateString('pt-BR')} <small>(clicou em ${clickedDate.toLocaleDateString('pt-BR')})</small>`;
    document.getElementById('scheduleModal').style.display = 'block';
    
    // Reset weekday checkboxes
    resetWeekdayCheckboxes();
    // Trigger blocking check for the whole week
    checkWeekdayBlocking();
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}

// ==============================
// SPECIALTY CASCADE FILTER (Modal)
// ==============================
document.getElementById('modal_especialidade_filter').addEventListener('change', function() {
    const selectedEsp = this.value;
    const allSelects = document.querySelectorAll('.modal-prof-select');
    
    allSelects.forEach(select => {
        const currentVal = select.value;
        const options = select.querySelectorAll('option');
        
        options.forEach(opt => {
            if (!opt.value) return; // Skip "Selecione..." / "Nenhum"
            const optEsp = opt.getAttribute('data-especialidade') || '';
            
            if (selectedEsp === '' || optEsp === selectedEsp) {
                opt.style.display = '';
                opt.disabled = false;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
                // If this was selected, clear it
                if (opt.value === currentVal) {
                    select.value = '';
                }
            }
        });
    });
});

// ==============================
// WEEKDAY BLOCKING LOGIC (Enhanced with Turno Indicators)
// ==============================
const dayNames = {1: 'Segunda', 2: 'Terça', 3: 'Quarta', 4: 'Quinta', 5: 'Sexta', 6: 'Sábado'};
const turnoLabels = {M: '☀ M', T: '☁ T', N: '☽ N'};
const turnoFullLabels = {M: 'Manhã', T: 'Tarde', N: 'Noite'};

function toggleWeekdayCard(dayNum) {
    const cb = document.getElementById('weekday_' + dayNum);
    if (cb && !cb.disabled) {
        cb.checked = !cb.checked;
    }
}

function resetWeekdayCheckboxes() {
    for (let d = 1; d <= 6; d++) {
        const cb = document.getElementById('weekday_' + d);
        const card = document.getElementById('weekday_card_' + d);
        const turnoEl = document.getElementById('weekday_turno_' + d);
        const countEl = document.getElementById('weekday_count_' + d);
        if (cb) {
            cb.disabled = false;
            cb.checked = (d <= 5); // Default: Mon-Fri checked
        }
        if (card) {
            card.classList.remove('wc-blocked', 'wc-partial-block');
            card.title = '';
        }
        if (turnoEl) turnoEl.innerHTML = '';
        if (countEl) countEl.textContent = '';
    }
    document.getElementById('weekday_blocking_info').style.display = 'none';
}

// Determine which turno the selected time falls into
function getSelectedTurno(horaInicio, horaFim) {
    const result = {M: false, T: false, N: false};
    if (horaInicio < '12:00') result.M = true;
    if (horaInicio < '18:00' && horaFim > '12:00') result.T = true;
    if (horaFim > '18:00' || horaInicio >= '18:00') result.N = true;
    return result;
}

let weekdayCheckTimeout = null;

async function checkWeekdayBlocking() {
    const profId = document.getElementById('form_prof_id').value;
    const dateStart = document.getElementById('form_date_start').value;
    const dateEnd = document.getElementById('form_date_end').value;
    const horaInicio = document.getElementById('form_hora_inicio').value;
    const horaFim = document.getElementById('form_hora_fim').value;
    
    // Need all fields to check
    if (!profId || !dateStart || !dateEnd || !horaInicio || !horaFim) {
        resetWeekdayCheckboxes();
        return;
    }
    
    const selectedTurno = getSelectedTurno(horaInicio, horaFim);
    
    try {
        const url = `?ajax_weekday_check=1&prof_id=${profId}&date_start=${dateStart}&date_end=${dateEnd}&hora_inicio=${horaInicio}&hora_fim=${horaFim}`;
        const response = await fetch(url);
        const data = await response.json();
        const blocked = data.blocked || {};
        const turnos = data.turnos || {};
        const totalDatas = data.total_datas_por_dow || {};
        
        let blockedNames = [];
        let partialNames = [];
        
        for (let d = 1; d <= 6; d++) {
            const cb = document.getElementById('weekday_' + d);
            const card = document.getElementById('weekday_card_' + d);
            const turnoEl = document.getElementById('weekday_turno_' + d);
            const countEl = document.getElementById('weekday_count_' + d);
            const turnoData = turnos[d] || null;
            
            // Reset card state
            card.classList.remove('wc-blocked', 'wc-partial-block');
            
            // === TURNO-AWARE BLOCKING LOGIC ===
            // Check if the SELECTED turno conflicts with occupied turnos for this weekday
            let turnoConflict = false;
            let occupiedTurnos = [];
            let freeTurnos = [];
            let conflictingTurnos = [];
            
            if (turnoData) {
                ['M', 'T', 'N'].forEach(t => {
                    const count = turnoData[t] || 0;
                    if (count > 0) {
                        occupiedTurnos.push(turnoFullLabels[t]);
                        // Does this occupied turno conflict with what the user selected?
                        if (selectedTurno[t]) {
                            turnoConflict = true;
                            conflictingTurnos.push(turnoFullLabels[t]);
                        }
                    } else {
                        freeTurnos.push(turnoFullLabels[t]);
                    }
                });
            }
            
            // Build turno badges HTML
            let turnoHtml = '';
            if (turnoData) {
                ['M', 'T', 'N'].forEach(t => {
                    const count = turnoData[t] || 0;
                    if (count > 0) {
                        const isConflict = selectedTurno[t];
                        const badgeClass = isConflict ? 'wt-conflict' : 'wt-occupied';
                        turnoHtml += `<span class="wc-turno-badge ${badgeClass}" title="${turnoFullLabels[t]}: ${count} dia(s) ocupado(s)${isConflict ? ' — CONFLITO com horário selecionado' : ''}">${turnoLabels[t]} ${count}d</span>`;
                    } else {
                        turnoHtml += `<span class="wc-turno-badge wt-free" title="${turnoFullLabels[t]}: Livre">${turnoLabels[t]}</span>`;
                    }
                });
                countEl.textContent = `${turnoData.total} dia(s) com aula`;
            } else {
                turnoHtml = '<span class="wc-turno-badge wt-free">Livre</span>';
                countEl.textContent = '';
            }
            
            turnoEl.innerHTML = turnoHtml;
            
            // === DECISION: block or not ===
            if (turnoConflict) {
                // The selected turno overlaps with an occupied turno → BLOCK this weekday
                cb.disabled = true;
                cb.checked = false;
                card.classList.add('wc-blocked');
                
                // Check if there are free turnos the user could switch to
                const switchableTurnos = freeTurnos.filter(t => t !== ''); 
                if (switchableTurnos.length > 0) {
                    card.title = `${dayNames[d]} bloqueada — turno ${conflictingTurnos.join(' e ')} ocupado(s). Mude para ${switchableTurnos.join(' ou ')} para desbloquear.`;
                    partialNames.push(dayNames[d]);
                } else {
                    card.title = `${dayNames[d]} totalmente ocupada — ${occupiedTurnos.join(', ')} com aulas.`;
                }
                blockedNames.push(dayNames[d]);
            } else if (turnoData && turnoData.total > 0) {
                // Has classes but NOT in the selected turno — show info, keep enabled
                cb.disabled = false;
                card.classList.add('wc-partial-block');
                card.title = `${dayNames[d]} tem aulas em ${occupiedTurnos.join(' e ')}, mas o turno selecionado (${horaInicio}–${horaFim}) está livre.`;
            } else {
                // No classes at all — fully free
                cb.disabled = false;
                card.title = `${dayNames[d]} totalmente livre.`;
            }
        }
        
        const infoDiv = document.getElementById('weekday_blocking_info');
        if (blockedNames.length > 0) {
            let msg = `<strong>Bloqueados (${blockedNames.length}):</strong> ${blockedNames.join(', ')} — turno ${horaInicio}–${horaFim} ocupado.`;
            if (partialNames.length > 0) {
                msg += `<br><i class="fas fa-lightbulb" style="color:#f9a825;"></i> <strong>Dica:</strong> ${partialNames.join(', ')} ${partialNames.length > 1 ? 'têm' : 'tem'} turnos livres. Altere o horário para desbloquear.`;
            }
            document.getElementById('weekday_blocking_text').innerHTML = msg;
            infoDiv.style.display = 'block';
        } else {
            infoDiv.style.display = 'none';
        }
    } catch(e) {
        console.error('Erro ao verificar bloqueio de dias:', e);
    }
}

function scheduleWeekdayCheck() {
    clearTimeout(weekdayCheckTimeout);
    weekdayCheckTimeout = setTimeout(checkWeekdayBlocking, 300);
}

// Attach listeners to the relevant fields
document.getElementById('form_prof_id').addEventListener('change', scheduleWeekdayCheck);
document.getElementById('form_date_start').addEventListener('change', scheduleWeekdayCheck);
document.getElementById('form_date_end').addEventListener('change', scheduleWeekdayCheck);
document.getElementById('form_hora_inicio').addEventListener('change', scheduleWeekdayCheck);
document.getElementById('form_hora_fim').addEventListener('change', scheduleWeekdayCheck);
// Also listen for input events on time fields for immediate feedback
document.getElementById('form_hora_inicio').addEventListener('input', scheduleWeekdayCheck);
document.getElementById('form_hora_fim').addEventListener('input', scheduleWeekdayCheck);

// Inicialização automática para modo Calendário Inline
document.addEventListener('DOMContentLoaded', () => {
    const viewMode = "<?php echo $view_mode; ?>";
    if (viewMode === 'calendar') {
        const profId = "<?php echo !empty($professores) ? $professores[0]['id'] : ''; ?>";
        const profNome = "<?php echo !empty($professores) ? addslashes($professores[0]['nome']) : ''; ?>";
        if (profId) {
            currentProfId = profId;
            currentProfNome = profNome;
            // Usar os dados já carregados no PHP para o professor atual
            const busyData = <?php echo !empty($professores) ? json_encode($agenda_data[$professores[0]['id']] ?? []) : '{}'; ?>;
            const turnoData = <?php
if (!empty($professores)) {
    $pid0 = $professores[0]['id'];
    echo json_encode($turno_detail[$pid0] ?? []);
}
else {
    echo '{}';
}
?>;
            renderCalendarView(profId, profNome, currentMonthStart, busyData, 'inline_calendar_' + profId, turnoData);
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
