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

// Date range for the timeline (default: current month)
$start_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$first_day = date('Y-m-01', strtotime($start_month));
$last_day = date('Y-m-t', strtotime($start_month));

$days_in_month = date('t', strtotime($start_month));
$days_array = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $date = sprintf("%s-%02d", $start_month, $i);
    $day_of_week = date('w', strtotime($date));
    if ($day_of_week == 0)
        continue; // Skip Sundays
    $days_array[] = [
        'full' => $date,
        'day' => $i,
        'is_weekend' => ($day_of_week == 6) // Only Saturday remains
    ];
}

// Fetch Professors
$professores = $pdo->query("SELECT id, nome, especialidade FROM professores ORDER BY nome ASC")->fetchAll();

// Fetch all classes for the current month
$stmt = $pdo->prepare("
    SELECT a.*, p.id as professor_id, t.nome as turma_nome 
    FROM agenda a 
    JOIN professores p ON a.professor_id = p.id
    JOIN turmas t ON a.turma_id = t.id
    WHERE a.data BETWEEN ? AND ?
");
$stmt->execute([$first_day, $last_day]);
$aulas = $stmt->fetchAll();

// Map classes to professors for JS
$prof_data = [];
foreach ($aulas as $aula) {
    if (!isset($prof_data[$aula['professor_id']]))
        $prof_data[$aula['professor_id']] = [];
    $prof_data[$aula['professor_id']][$aula['data']] = $aula['turma_nome'];
}

// Data for modals
$turmas_select = $pdo->query("SELECT t.id, t.nome, c.nome as curso_nome FROM turmas t JOIN cursos c ON t.curso_id = c.id ORDER BY t.nome ASC")->fetchAll();
$salas_select = $pdo->query("SELECT id, nome FROM salas ORDER BY nome ASC")->fetchAll();
?>

<style>
    .prof-list-card {
        background: var(--card-bg);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    .prof-list-table { width: 100%; border-collapse: collapse; }
    .prof-list-table th, .prof-list-table td { padding: 15px 20px; border-bottom: 1px solid var(--border-color); text-align: left; }
    .prof-list-table tr:hover { background: rgba(0,0,0,0.02); }
    
    .btn-view {
        background: #2e7d32; color: #fff; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;
        transition: all 0.2s;
    }
    .btn-view:hover { background: #1b5e20; transform: translateY(-2px); }

    /* Timeline Modal Style: BAR STYLE */
    .timeline-modal-content {
        max-width: 95% !important;
        width: 1200px !important;
    }
    .timeline-bar-container {
        margin-top: 30px;
        padding: 20px 0;
    }
    .timeline-bar {
        height: 60px;
        width: 100%;
        display: flex;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid var(--border-color);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .bar-segment {
        flex: 1;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 800;
        position: relative;
        transition: all 0.2s;
        border-right: 1px solid rgba(255,255,255,0.1);
    }
    .bar-segment:last-child { border-right: none; }
    .bar-segment-busy { background: #ff1c1c; color: #fff; cursor: not-allowed; }
    .bar-segment-free { background: #2e7d32; color: #fff; cursor: pointer; }
    .bar-segment-free:hover { filter: brightness(1.2); transform: scaleY(1.05); }
    .bar-segment-weekend { opacity: 0.5; }
    
    .bar-day-label {
        position: absolute;
        bottom: -28px;
        font-size: 0.65rem;
        color: var(--text-muted);
        font-weight: 700;
        width: 100%;
        text-align: center;
        line-height: 1.1;
    }
    .bar-day-name {
        display: block;
        font-size: 0.55rem;
        text-transform: uppercase;
        opacity: 0.8;
    }
    .bar-segment-weekend{ 
        background-color: #f0f0f0 !important; 
        color: #999 !important; 
        background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0,0,0,0.03) 5px, rgba(0,0,0,0.03) 10px);
    }
    .bar-segment-weekendd{ 
        background-color: #ffcdd2 !important; 
        color: #999 !important; 
        background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0,0,0,0.03) 5px, rgba(0,0,0,0.03) 10px);
    }
    .bar-segment-busy.bar-segment-weekend { background-color: #ffcdd2 !important; color: #b71c1c !important; opacity: 0.8; }
    .bar-segment-free.bar-segment-weekend { opacity: 1; }

    /* Legend Refinement */
    .timeline-legend {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 25px;
        flex-wrap: wrap;
        font-size: 0.85rem;
    }
    .legend-item { display: flex; align-items: center; gap: 8px; }
    .legend-box { width: 16px; height: 16px; border-radius: 3px; border: 1px solid rgba(0,0,0,0.1); }

    .month-nav {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        margin-bottom: 20px;
    }
    .month-btn {
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .month-btn:hover { background: var(--primary-red); color: #fff; border-color: var(--primary-red); }

    /* Modal Base Styles */
    .modal {
        display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
    }
    .modal-content {
        background: var(--card-bg); margin: 3% auto; padding: 35px; border-radius: 20px;
        box-shadow: 0 10px 50px rgba(0,0,0,0.3); border: 1px solid var(--border-color); position: relative;
    }
    .close-modal { position: absolute; top: 20px; right: 25px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); transition: color 0.2s; }
    .close-modal:hover { color: var(--primary-red); }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-users-viewfinder"></i> Timeline de Professores</h2>
        <p>Visão de barra horizontal estilo Power BI.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <label>Mês:</label>
        <input type="month" id="month_selector" value="<?php echo $start_month; ?>" onchange="window.location.href='?month=' + this.value" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color); font-weight: 600;">
    </div>
</div>

<div class="prof-list-card">
    <table class="prof-list-table">
        <thead style="background: var(--bg-color);">
            <tr>
                <th>Nome do Professor</th>
                <th>Especialidade</th>
                <th style="text-align: right;">Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($professores as $p): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($p['nome']); ?></strong></td>
                <td><span style="opacity: 0.7;"><?php echo htmlspecialchars($p['especialidade']); ?></span></td>
                <td style="text-align: right;">
                    <button class="btn-view" onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')">
                        <i class="fas fa-chart-bar"></i> Ver Barra de Disponibilidade
                    </button>
                </td>
            </tr>
            <?php
endforeach; ?>
        </tbody>
    </table>
</div>

<!-- MODAL 1: Timeline em Barra -->
<div id="timelineModal" class="modal">
    <div class="modal-content timeline-modal-content" style="text-align: center;">
        <span class="close-modal" onclick="closeModal('timelineModal')">&times;</span>
        
        <div class="month-nav">
            <button class="month-btn" id="prev_month_btn"><i class="fas fa-chevron-left"></i></button>
            <h2 id="timeline_prof_name" style="margin: 0; min-width: 280px;">Timeline</h2>
            <button class="month-btn" id="next_month_btn"><i class="fas fa-chevron-right"></i></button>
        </div>

        <div class="timeline-legend">
            <div class="legend-item"><div class="legend-box" style="background:#2e7d32;"></div><span>Livre</span></div>
            <div class="legend-item"><div class="legend-box" style="background:#ff1c1c;"></div><span>Ocupado</span></div>
            <div class="legend-item"><div class="legend-box bar-segment-weekend" style="border:1px solid #ccc;"></div><span>Sábado (Livre)</span></div>
            <div class="legend-item"><div class="legend-box bar-segment-weekendd" style="background:#ffcdd2;"></div><span>Domingo (Bloqueado)</span></div>
        </div>
        
        <div class="timeline-bar-container" id="timeline_render_area">
            <!-- Continuous bar will be injected here by JS -->
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
const profBusyData = <?php echo json_encode($prof_data); ?>;
let currentProfId = null;
let currentProfNome = "";
let currentViewMonth = "<?php echo $start_month; ?>";

function openTimelineModal(profId, profNome) {
    currentProfId = profId;
    currentProfNome = profNome;
    currentViewMonth = document.getElementById('month_selector').value;
    
    updateModalTitle();
    renderTimelineBar(currentProfId, currentProfNome, currentViewMonth, profBusyData[currentProfId] || {});
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
    const container = document.getElementById('timeline_render_area');
    container.style.opacity = '0.5';
    
    try {
        const response = await fetch(`?ajax_availability=1&prof_id=${currentProfId}&month=${currentViewMonth}`);
        const data = await response.json();
        renderTimelineBar(currentProfId, currentProfNome, currentViewMonth, data.busy);
    } catch (e) {
        console.error(e);
        alert("Erro ao carregar dados.");
    } finally {
        container.style.opacity = '1';
    }
}

function renderTimelineBar(profId, profNome, monthStr, busyDays) {
    const container = document.getElementById('timeline_render_area');
    
    const date = new Date(monthStr + "-01T00:00:00");
    const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    
    const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    
    let html = `<div class="timeline-bar">`;
    
    for (let i = 1; i <= daysInMonth; i++) {
        const dStr = `${monthStr}-${String(i).padStart(2, '0')}`;
        const dObj = new Date(dStr + "T00:00:00");
        const dow = dObj.getDay();
        const isBusy = busyDays[dStr];
        
        const isSunday = (dow === 0);
        const isSaturday = (dow === 6);
        const isWeekend = isSunday || isSaturday;
        
        // Determinar estado final: Se for domingo, é BLOQUEADO independente de ter aula ou não
        const statusClass = (isBusy || isSunday) ? 'bar-segment-busy' : 'bar-segment-free';
        const weekendClass = isWeekend ? 'bar-segment-weekend' : '';
        
        // Título/Tooltip
        let tooltip = '';
        if (isSunday) {
            tooltip = '(DOMINGO)';
        } else if (isBusy) {
            tooltip = 'OCUPADO: ' + isBusy;
        } else {
            tooltip = 'LIVRE: Clique para agendar';
        }
        
        html += `
            <div class="bar-segment ${statusClass} ${weekendClass}" 
                 ${(!isBusy && !isSunday) ? `onclick="openScheduleModal(${profId}, '${profNome}', '${dStr}')"` : ''}
                 title="${tooltip}">
                ${i}
                <div class="bar-day-label">
                    <span class="bar-day-name">${dayNames[dow]}</span>
                    ${i}
                </div>
            </div>`;
    }
    
    html += `</div>`;
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
</script>

<?php include 'includes/footer.php'; ?>
