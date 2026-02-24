<?php
require_once 'includes/db.php';
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
        bottom: -25px;
        font-size: 0.7rem;
        color: var(--text-muted);
        font-weight: 700;
        width: 100%;
        text-align: center;
    }

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
        <h2 id="timeline_prof_name" style="margin-bottom: 5px;">Timeline</h2>
        <p style="color: var(--text-muted); margin-bottom: 30px;">
           <span style="display:inline-block; width:15px; height:15px; background:#ff1c1c; border-radius:3px; vertical-align:middle;"></span> Ocupado 
           <span style="display:inline-block; width:15px; height:15px; background:#2e7d32; border-radius:3px; margin-left:20px; vertical-align:middle;"></span> Livre (Clique para selecionar período)
        </p>
        
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
const daysInMonth = <?php echo json_encode($days_array); ?>;
const currentMonthLabel = "<?php echo date('F Y', strtotime($start_month)); ?>";

function openTimelineModal(profId, profNome) {
    const modal = document.getElementById('timelineModal');
    document.getElementById('timeline_prof_name').innerText = `${profNome} (${currentMonthLabel})`;
    
    renderTimelineBar(profId, profNome);
    modal.style.display = 'block';
}

function renderTimelineBar(profId, profNome) {
    const container = document.getElementById('timeline_render_area');
    const busyDays = profBusyData[profId] || {};
    
    let html = `<div class="timeline-bar">`;
    
    daysInMonth.forEach(d => {
        const isBusy = busyDays[d.full];
        const weekendClass = d.is_weekend ? 'bar-segment-weekend' : '';
        const statusClass = isBusy ? 'bar-segment-busy' : 'bar-segment-free';
        
        html += `
            <div class="bar-segment ${statusClass} ${weekendClass}" 
                 ${!isBusy ? `onclick="openScheduleModal(${profId}, '${profNome}', '${d.full}')"` : ''}
                 title="${isBusy ? 'OCUPADO: ' + isBusy : 'LIVRE: Clique para agendar'}">
                ${d.day}
                <div class="bar-day-label">${d.day}</div>
            </div>`;
    });
    
    html += `</div>`;
    container.innerHTML = html;
}

function openScheduleModal(profId, profNome, date) {
    document.getElementById('form_prof_id').value = profId;
    document.getElementById('form_date_start').value = date;
    document.getElementById('form_date_end').value = date; // Inicia com o mesmo dia
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
