<?php

require_once 'includes/db.php';

// --- AJAX HANDLER FOR TIMELINE DATA ---
if (isset($_GET['ajax_availability'])) {
    $prof_id = (int)$_GET['prof_id'];
    $month = $_GET['month']; // Y-m
    $f_day = $month . '-01';
    $l_day = date('Y-m-t', strtotime($f_day));

    $st = $pdo->prepare("
        SELECT a.data, t.nome as turma_nome 
        FROM agenda a 
        JOIN turmas t ON a.turma_id = t.id
        WHERE a.professor_id = ? AND a.data BETWEEN ? AND ?
    ");
    $st->execute([$prof_id, $f_day, $l_day]);
    $results = $st->fetchAll();

    $busy = [];
    foreach ($results as $row) {
        $busy[$row['data']] = $row['turma_nome'];
    }

    header('Content-Type: application/json');
    echo json_encode(['busy' => $busy]);
    exit;
}

include 'includes/header.php';

// Filtros e Parâmetros da Tabela
$search_name = isset($_GET['search']) ? $_GET['search'] : '';
$ordem_disp = isset($_GET['ordem_disp']) ? $_GET['ordem_disp'] : 'mais';
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'timeline'; // Mode: timeline, blocks, calendar
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ($view_mode == 'calendar') ? 1 : 10; // Calendar view shows 1 prof at a time for focus
$offset = ($page - 1) * $limit;

$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$first_day = date('Y-m-01', strtotime($current_month));
$last_day = date('Y-m-t', strtotime($current_month));
$days_in_month = date('t', strtotime($current_month));

// SQL para Professores com busca e ordenação por disponibilidade
$where_search = $search_name ? "AND nome LIKE ?" : "";
$params_search = $search_name ? ["%$search_name%"] : [];

$query_base = "
    FROM professores p 
    LEFT JOIN (
        SELECT professor_id, COUNT(*) as total_aulas 
        FROM agenda 
        WHERE data BETWEEN '$first_day' AND '$last_day'
        GROUP BY professor_id
    ) a ON p.id = a.professor_id
    WHERE 1=1 $where_search
";

$total_profs = $pdo->prepare("SELECT COUNT(*) $query_base");
$total_profs->execute($params_search);
$total_count = $total_profs->fetchColumn();

$sort_sql = $ordem_disp == 'mais' ? "ORDER BY COALESCE(a.total_aulas, 0) ASC" : "ORDER BY COALESCE(a.total_aulas, 0) DESC";

$stmt_profs = $pdo->prepare("
    SELECT p.*, COALESCE(a.total_aulas, 0) as total_aulas
    $query_base
    $sort_sql
    LIMIT $limit OFFSET $offset
");
$stmt_profs->execute($params_search);
$professores = $stmt_profs->fetchAll();

// Buscar aulas dos professores para as barras horizontais
$prof_ids = array_column($professores, 'id');
$agenda_data = [];
if (!empty($prof_ids)) {
    $placeholders = implode(',', array_fill(0, count($prof_ids), '?'));
    $stmt_agenda = $pdo->prepare("
        SELECT a.data, a.professor_id, t.nome as turma_nome
        FROM agenda a
        JOIN turmas t ON a.turma_id = t.id
        WHERE a.professor_id IN ($placeholders) AND a.data BETWEEN ? AND ?
    ");
    $stmt_agenda->execute(array_merge($prof_ids, [$first_day, $last_day]));
    $results_agenda = $stmt_agenda->fetchAll();
    foreach ($results_agenda as $row) {
        $agenda_data[$row['professor_id']][$row['data']] = $row['turma_nome'];
    }
}

// Data para os selects dos agendadores (Modais)
$turmas_select = $pdo->query("SELECT t.id, t.nome, c.nome as curso_nome FROM turmas t JOIN cursos c ON t.curso_id = c.id ORDER BY t.nome ASC")->fetchAll();
$salas_select = $pdo->query("SELECT id, nome FROM salas ORDER BY nome ASC")->fetchAll();
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
    .block-seg-sunday { background: #ffcdd2; color: #b71c1c; opacity: 0.8; }
    .block-range { font-size: 0.85rem; font-weight: 800; line-height: 1; white-space: nowrap; }
    .block-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; opacity: 0.85; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; letter-spacing: 0.3px; }
    
    .view-selector { display: flex; background: var(--bg-color); padding: 5px; border-radius: 10px; border: 1px solid var(--border-color); }
    .view-btn { padding: 8px 15px; border-radius: 8px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; text-decoration: none; display: flex; align-items: center; gap: 8px; }
    .view-btn.active { background: var(--card-bg); color: var(--primary-red); shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .view-btn:hover:not(.active) { background: rgba(0,0,0,0.05); }

    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
    .modal-content { background: var(--card-bg); margin: 3% auto; padding: 35px; border-radius: 20px; box-shadow: 0 10px 50px rgba(0,0,0,0.3); border: 1px solid var(--border-color); position: relative; }
    .close-modal { position: absolute; top: 20px; right: 25px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); transition: color 0.2s; }
    .close-modal:hover { color: var(--primary-red); }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-calendar-check"></i> Agenda de Professores</h2>
        <div class="view-selector" style="margin-top: 10px;">
            <a href="?view_mode=timeline&month=<?php echo $current_month; ?>&search=<?php echo urlencode($search_name); ?>" class="view-btn <?php echo $view_mode == 'timeline' ? 'active' : ''; ?>">
                <i class="fas fa-grip-lines"></i> Timeline
            </a>
            <a href="?view_mode=blocks&month=<?php echo $current_month; ?>&search=<?php echo urlencode($search_name); ?>" class="view-btn <?php echo $view_mode == 'blocks' ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Blocos
            </a>
            <a href="?view_mode=calendar&month=<?php echo $current_month; ?>&search=<?php echo urlencode($search_name); ?>" class="view-btn <?php echo $view_mode == 'calendar' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Calendário
            </a>
        </div>
    </div>
    <div style="display:flex; gap:10px;">
        <p style="margin:0; font-size: 0.85rem; opacity: 0.7; font-weight: 600; text-align: right;">
            <?php echo $view_mode == 'calendar' ? 'Navegue entre os professores para ver agendas individuais' : 'Clique no nome para detalhes mensais'; ?><br>
            <span style="font-size: 0.75rem; opacity: 0.5;">Filtre por mês ao lado</span>
        </p>
        <input type="month" value="<?php echo $current_month; ?>" onchange="let url = new URL(window.location.href); url.searchParams.set('month', this.value); window.location.href=url.href;" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--card-bg); font-weight: 600; color: var(--text-color);">
    </div>
</div>

<div class="filter-header">
    <form method="GET" class="search-group" id="filter_form">
        <input type="hidden" name="view_mode" value="<?php echo $view_mode; ?>">
        <div style="display: flex; align-items: center; gap: 8px; background: var(--bg-color); border: 1px solid var(--border-color); padding: 2px 10px; border-radius: 8px;">
            <i class="fas fa-calendar-alt" style="opacity: 0.5;"></i>
            <input type="month" name="month" value="<?php echo $current_month; ?>" onchange="this.form.submit()" style="border:none; background:transparent; padding: 8px 5px; font-weight: 700;">
        </div>

        <div style="display: flex; align-items: center; gap: 8px; background: var(--bg-color); border: 1px solid var(--border-color); padding: 2px 10px; border-radius: 8px;">
            <i class="fas fa-search" style="opacity: 0.5;"></i>
            <input type="text" name="search" placeholder="Buscar por professor..." value="<?php echo htmlspecialchars($search_name); ?>" onchange="this.form.submit()" style="border:none; background:transparent; padding: 8px 5px;">
        </div>
        
        <select name="ordem_disp" onchange="this.form.submit()">
            <option value="mais" <?php echo $ordem_disp == 'mais' ? 'selected' : ''; ?>>Mais Disponíveis</option>
            <option value="menos" <?php echo $ordem_disp == 'menos' ? 'selected' : ''; ?>>Mais Ocupados</option>
        </select>
    </form>
    
    <div style="font-size: 0.85rem; font-weight: 600; opacity: 0.7;">
        Exibindo <?php echo count($professores); ?> de <?php echo $total_count; ?> professores
    </div>
</div>

<div class="table-container">
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
            <?php elseif ($view_mode == 'blocks'): ?>
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
                // Build continuous blocks
                $blocks = [];
                $cur_block = null;
                for ($i = 1; $i <= $days_in_month; $i++) {
                    $dt = sprintf("%s-%02d", $current_month, $i);
                    $dow = date('N', strtotime($dt));
                    $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;

                    if ($dow == 7) {
                        $status = 'sunday';
                        $label = 'Bloqueado';
                    } elseif ($is_busy) {
                        $status = 'busy:' . $is_busy;
                        $label = $is_busy;
                    } else {
                        $status = 'free';
                        $label = 'Livre';
                    }

                    if ($cur_block && $cur_block['status'] === $status) {
                        $cur_block['end'] = $i;
                        $cur_block['count']++;
                    } else {
                        if ($cur_block) $blocks[] = $cur_block;
                        $cur_block = ['start' => $i, 'end' => $i, 'status' => $status, 'label' => $label, 'count' => 1];
                    }
                }
                if ($cur_block) $blocks[] = $cur_block;
                ?>
                <div class="blocks-bar-wrapper">
                    <?php foreach ($blocks as $block):
                        $range_text = ($block['start'] == $block['end']) ? $block['start'] : $block['start'] . '–' . $block['end'];
                        if (strpos($block['status'], 'busy:') === 0) {
                            $bclass = 'block-seg-busy';
                        } elseif ($block['status'] === 'sunday') {
                            $bclass = 'block-seg-sunday';
                        } else {
                            $bclass = 'block-seg-free';
                        }
                        $first_dt = sprintf("%s-%02d", $current_month, $block['start']);
                    ?>
                        <div class="block-seg <?php echo $bclass; ?>" style="flex: <?php echo $block['count']; ?>;" title="<?php echo $range_text; ?>: <?php echo htmlspecialchars($block['label']); ?>"
                             <?php if ($block['status'] === 'free'): ?>onclick="openScheduleModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>', '<?php echo $first_dt; ?>')"<?php endif; ?>>
                            <span class="block-range"><?php echo $range_text; ?></span>
                            <span class="block-label"><?php echo htmlspecialchars($block['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php else: ?>
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
                
                <div class="timeline-bar-wrapper">
                    <?php for ($i = 1; $i <= $days_in_month; $i++):
                $dt = sprintf("%s-%02d", $current_month, $i);
                $dow = date('N', strtotime($dt));
                $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;

                $class = "bar-seg-free";
                $title = "Livre: " . $i;
                $onclick = "onclick=\"openScheduleModal({$p['id']}, '" . addslashes($p['nome']) . "', '{$dt}')\"";

                if ($dow == 7) {
                    $class = "bar-seg-sunday";
                    $title = "DOMINGO: " . $i;
                    $onclick = "";
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
    <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search_name); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?>" 
       class="btn-nav <?php echo $page <= 1 ? 'disabled' : ''; ?>">
        <i class="fas fa-chevron-left"></i>
    </a>
    <span style="font-weight: 700;">Página <?php echo $page; ?> de <?php echo $total_pages; ?></span>
    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search_name); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?>" 
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
            <div class="legend-item"><div class="legend-box" style="background:#ff1c1c;"></div><span>Ocupado</span></div>
            <div class="legend-item"><div class="legend-box calendar-day-weekend" style="border:1px solid #ccc;"></div><span>Sábado (Livre)</span></div>
            <div class="legend-item"><div class="legend-box calendar-day-weekendd" style="background:#ffcdd2;"></div><span>Domingo (Bloqueado)</span></div>
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
    <div class="modal-content" style="max-width: 600px;">
        <span class="close-modal" onclick="closeModal('scheduleModal')">&times;</span>
        <h2 style="margin-bottom: 15px;"><i class="fas fa-calendar-plus" style="color: var(--primary-red);"></i> Agendar Período</h2>
        <div style="background: rgba(46, 125, 50, 0.05); padding: 15px; border-radius: 10px; border-left: 4px solid #2e7d32; margin-bottom: 25px;">
            <p id="schedule_info" style="font-weight: 700; color: #2e7d32;"></p>
        </div>
        
        <form action="planejamento_process.php" method="POST">
            <input type="hidden" name="professor_id" id="form_prof_id">
            <input type="hidden" name="is_quick" value="1">

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
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Dias da Semana (Selecione um ou mais)</label>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; background: var(--bg-color); padding: 10px; border-radius: 8px;">
                    <label style="cursor:pointer;"><input type="checkbox" name="dias_semana[]" value="1" checked> Seg</label> |
                    <label style="cursor:pointer;"><input type="checkbox" name="dias_semana[]" value="2" checked> Ter</label> |
                    <label style="cursor:pointer;"><input type="checkbox" name="dias_semana[]" value="3" checked> Qua</label> |
                    <label style="cursor:pointer;"><input type="checkbox" name="dias_semana[]" value="4" checked> Qui</label> |
                    <label style="cursor:pointer;"><input type="checkbox" name="dias_semana[]" value="5" checked> Sex</label> |
                    <label style="cursor:pointer;"><input type="checkbox" name="dias_semana[]" value="6"> Sáb</label>
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
                    <input type="time" name="hora_inicio" value="08:00" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Horário Fim</label>
                    <input type="time" name="hora_fim" value="12:00" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color);">
                </div>
            </div>

            <div style="text-align: right; border-top: 1px solid var(--border-color); pt-20">
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
        renderCalendarView(currentProfId, currentProfNome, currentViewMonth, data.busy);
    } catch (e) {
        console.error(e);
        alert("Erro ao carregar dados de disponibilidade.");
    } finally {
        container.style.opacity = '1';
    }
}

function renderCalendarView(profId, profNome, monthStr, busyDays, targetContainerId = 'calendar_render_area') {
    const container = document.getElementById(targetContainerId);
    
    const date = new Date(monthStr + "-01T00:00:00");
    const firstDayOfWeek = date.getDay(); // 0 = Domingo, 1 = Segunda...
    const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    
    const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    
    let html = `
        <div class="calendar-container">
            <div class="calendar-header-grid">
                ${dayNames.map(d => `<div>${d}</div>`).join('')}
            </div>
            <div class="calendar-grid">
    `;
    
    // Células vazias iniciais
    for (let i = 0; i < firstDayOfWeek; i++) {
        html += `<div class="calendar-day calendar-day-empty"></div>`;
    }
    
    // Dias do mês
    for (let i = 1; i <= daysInMonth; i++) {
        const dStr = `${monthStr}-${String(i).padStart(2, '0')}`;
        const dObj = new Date(dStr + "T00:00:00");
        const dow = dObj.getDay();
        const isBusy = busyDays[dStr];
        
        const isSunday = (dow === 0);
        const isSaturday = (dow === 6);
        const isWeekend = isSunday || isSaturday;
        
        const statusClass = (isBusy || isSunday) ? 'calendar-day-busy' : 'calendar-day-free';
        const weekendClass = isWeekend ? (isSunday ? 'calendar-day-weekendd' : 'calendar-day-weekend') : '';
        
        let tooltip = '';
        let statusLabel = 'Livre';
        
        if (isSunday) {
            tooltip = '(DOMINGO)';
            statusLabel = 'Bloqueado';
        } else if (isBusy) {
            tooltip = 'OCUPADO: ' + isBusy;
            statusLabel = 'Ocupado';
        } else {
            tooltip = 'LIVRE: Clique para agendar';
        }
        
        html += `
            <div class="calendar-day ${statusClass} ${weekendClass}" 
                 ${(!isBusy && !isSunday) ? `onclick="openScheduleModal(${profId}, '${profNome}', '${dStr}')"` : ''}
                 title="${tooltip}">
                <div class="day-number">${i}</div>
                <div class="day-status-label">${statusLabel}</div>
            </div>`;
    }
    
    html += `</div></div>`;
    container.innerHTML = html;
}

function openScheduleModal(profId, profNome, date) {
    document.getElementById('form_prof_id').value = profId;
    document.getElementById('form_date_start').value = date;
    document.getElementById('form_date_end').value = date;
    document.getElementById('schedule_info').innerHTML = `<i class="fas fa-user"></i> Professor: ${profNome} <br> <i class="fas fa-calendar-alt"></i> Data Inicial selecionada: ${new Date(date + 'T00:00:00').toLocaleDateString('pt-BR')}`;
    document.getElementById('scheduleModal').style.display = 'block';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}

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
            renderCalendarView(profId, profNome, currentMonthStart, busyData, 'inline_calendar_' + profId);
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
