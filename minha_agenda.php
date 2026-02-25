<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// This page is for professors only (but admin/gestor are redirected to index by auth.php professor logic)
// Actually professors ARE the only ones directed here, but let's also allow admin to preview
$professor_id = $_SESSION['professor_id'] ?? null;

// AJAX handler for this professor's timeline
if (isset($_GET['ajax_minha_agenda'])) {
    header('Content-Type: application/json');

    if (!$professor_id) {
        echo json_encode(['error' => 'Nenhum professor vinculado à sua conta.', 'aulas' => []]);
        exit;
    }

    $month = $_GET['month'] ?? date('Y-m');
    $f_day = $month . '-01';
    $l_day = date('Y-m-t', strtotime($f_day));

    $st = $mysqli->prepare("
        SELECT a.data, a.hora_inicio, a.hora_fim,
               t.nome AS turma, c.nome AS curso,
               s.nome AS sala
        FROM agenda a
        JOIN turmas t ON a.turma_id = t.id
        LEFT JOIN cursos c ON t.curso_id = c.id
        JOIN salas s ON a.sala_id = s.id
        WHERE (a.professor_id = ? OR a.professor_id_2 = ? OR a.professor_id_3 = ? OR a.professor_id_4 = ?)
          AND a.data BETWEEN ? AND ?
        ORDER BY a.data, a.hora_inicio
    ");
    $st->bind_param('iiiiss', $professor_id, $professor_id, $professor_id, $professor_id, $f_day, $l_day);
    $st->execute();
    $aulas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate totals
    $total_horas = 0;
    foreach ($aulas as $a) {
        $hi = strtotime($a['hora_inicio']);
        $hf = strtotime($a['hora_fim']);
        if ($hi && $hf && $hf > $hi) {
            $total_horas += ($hf - $hi) / 3600;
        }
    }

    echo json_encode([
        'aulas' => $aulas,
        'total_horas' => round($total_horas, 1),
        'total_aulas' => count($aulas)
    ]);
    exit;
}

include 'includes/header.php';

// Get professor info
$prof_info = null;
if ($professor_id) {
    $sp = $mysqli->prepare("SELECT id, nome, especialidade, carga_horaria_contratual, cor_agenda FROM professores WHERE id = ?");
    $sp->bind_param('i', $professor_id);
    $sp->execute();
    $prof_info = $sp->get_result()->fetch_assoc();
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-calendar-alt"></i> Minha Agenda</h2>
        <?php if ($prof_info): ?>
            <p>Professor: <strong><?php echo htmlspecialchars($prof_info['nome']); ?></strong>
            <?php if ($prof_info['especialidade']): ?>
                — <?php echo htmlspecialchars($prof_info['especialidade']); ?>
            <?php endif; ?>
            </p>
        <?php else: ?>
            <p style="color: #dc3545;">Sua conta não está vinculada a nenhum professor. Contate o administrador.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (!$professor_id): ?>
<div class="card" style="border-left: 5px solid #dc3545; padding: 20px;">
    <h3><i class="fas fa-exclamation-triangle"></i> Conta não vinculada</h3>
    <p>Para visualizar sua agenda, o administrador precisa vincular sua conta a um professor cadastrado no sistema.</p>
</div>
<?php else: ?>

<!-- Month navigation -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <button onclick="changeMonth(-1)" class="btn btn-primary" style="padding: 8px 15px;"><i class="fas fa-chevron-left"></i></button>
        <h3 id="month_label" style="margin: 0;"></h3>
        <button onclick="changeMonth(1)" class="btn btn-primary" style="padding: 8px 15px;"><i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<!-- Stats cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2rem; font-weight: 800; color: var(--primary-red);" id="stat_aulas">0</div>
        <div style="font-size: 0.85rem; color: var(--text-muted);">Aulas no mês</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2rem; font-weight: 800; color: #0d6efd;" id="stat_horas">0</div>
        <div style="font-size: 0.85rem; color: var(--text-muted);">Horas no mês</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2rem; font-weight: 800; color: #198754;" id="stat_ch_max"><?php echo (int)$prof_info['carga_horaria_contratual']; ?></div>
        <div style="font-size: 0.85rem; color: var(--text-muted);">CH Contratual (h/mês)</div>
    </div>
</div>

<!-- Schedule table -->
<div class="card">
    <h3 style="margin-bottom: 15px;"><i class="fas fa-list"></i> Aulas do Mês</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; font-size: 0.88rem;" id="aulas_table">
            <thead>
                <tr>
                    <th style="padding: 10px;">Data</th>
                    <th style="padding: 10px;">Dia</th>
                    <th style="padding: 10px;">Horário</th>
                    <th style="padding: 10px;">Turma</th>
                    <th style="padding: 10px;">Curso</th>
                    <th style="padding: 10px;">Sala</th>
                </tr>
            </thead>
            <tbody id="aulas_body">
                <tr><td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">Carregando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
let currentDate = new Date();

function getMonthStr() {
    return currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');
}

function updateLabel() {
    const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    document.getElementById('month_label').textContent = months[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    loadAgenda();
}

function loadAgenda() {
    updateLabel();
    const month = getMonthStr();

    fetch('minha_agenda.php?ajax_minha_agenda=1&month=' + month)
        .then(r => r.json())
        .then(data => {
            if (data.error && !data.aulas) {
                document.getElementById('aulas_body').innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#dc3545;">' + data.error + '</td></tr>';
                return;
            }

            document.getElementById('stat_aulas').textContent = data.total_aulas;
            document.getElementById('stat_horas').textContent = data.total_horas + 'h';

            const tbody = document.getElementById('aulas_body');
            if (!data.aulas.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Nenhuma aula neste mês.</td></tr>';
                return;
            }

            let html = '';
            data.aulas.forEach(a => {
                const dt = new Date(a.data + 'T00:00:00');
                const dia = diasSemana[dt.getDay()];
                const dataFmt = a.data.split('-').reverse().join('/');
                const hi = a.hora_inicio.substring(0, 5);
                const hf = a.hora_fim.substring(0, 5);
                html += '<tr>';
                html += '<td style="padding:8px 10px; font-weight:600;">' + dataFmt + '</td>';
                html += '<td style="padding:8px 10px;">' + dia + '</td>';
                html += '<td style="padding:8px 10px;"><span style="color: var(--primary-red); font-weight:700;">' + hi + ' - ' + hf + '</span></td>';
                html += '<td style="padding:8px 10px;">' + (a.turma || '') + '</td>';
                html += '<td style="padding:8px 10px;">' + (a.curso || '') + '</td>';
                html += '<td style="padding:8px 10px;">' + (a.sala || '') + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('aulas_body').innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#dc3545;">Erro ao carregar agenda.</td></tr>';
        });
}

// Initial load
loadAgenda();
</script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
