<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Helper for date navigation
$view_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$prof_id = isset($_GET['prof_id']) ? $_GET['prof_id'] : null;

// Get Monday of the week
$monday = date('Y-m-d', strtotime('monday this week', strtotime($view_date)));
$week_days = [];
for($i=0; $i<6; $i++) {
    $date = date('Y-m-d', strtotime("$monday +$i days"));
    $week_days[$date] = [
        'name' => ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$i],
        'date' => $date
    ];
}

$professores = $pdo->query("SELECT id, nome FROM professores ORDER BY nome ASC")->fetchAll();

if ($prof_id) {
    // Fetch agenda for this prof and this week
    $sunday = date('Y-m-d', strtotime("$monday +6 days"));
    $stmt = $pdo->prepare("
        SELECT a.*, t.nome as turma_nome, s.nome as sala_nome 
        FROM agenda a 
        JOIN turmas t ON a.turma_id = t.id 
        JOIN salas s ON a.sala_id = s.id 
        WHERE a.professor_id = ? AND a.data BETWEEN ? AND ?
        ORDER BY a.hora_inicio ASC
    ");
    $stmt->execute([$prof_id, $monday, $sunday]);
    $aulas = $stmt->fetchAll();
    
    // Group by date
    $agenda_data = [];
    foreach ($aulas as $aula) {
        $agenda_data[$aula['data']][] = $aula;
    }
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Agenda de Professores</h2>
    <div style="display: flex; gap: 10px;">
        <select onchange="window.location.href='?prof_id=' + this.value" style="padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            <option value="">Selecione um Professor...</option>
            <?php foreach ($professores as $p): ?>
                <option value="<?php echo $p['id']; ?>" <?php echo $prof_id == $p['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($prof_id): ?>
<div class="calendar-controls" style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 20px;">
    <a href="?prof_id=<?php echo $prof_id; ?>&date=<?php echo date('Y-m-d', strtotime("$monday -7 days")); ?>" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color);"><i class="fas fa-chevron-left"></i> Semana Anterior</a>
    <span style="font-weight: 600; font-size: 1.1rem;"><?php echo date('d/m', strtotime($monday)); ?> - <?php echo date('d/m', strtotime($sunday)); ?></span>
    <a href="?prof_id=<?php echo $prof_id; ?>&date=<?php echo date('Y-m-d', strtotime("$monday +7 days")); ?>" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color);">Próxima Semana <i class="fas fa-chevron-right"></i></a>
</div>

<div class="agenda-trello" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; overflow-x: auto;">
    <?php foreach ($week_days as $date => $info): ?>
    <div class="trello-col" style="min-width: 150px;">
        <div class="col-header" style="text-align: center; margin-bottom: 10px; padding: 10px; background: var(--card-bg); border-radius: 8px; border-bottom: 3px solid var(--primary-red);">
            <strong><?php echo $info['name']; ?></strong><br>
            <small style="color: var(--text-muted);"><?php echo date('d/m', strtotime($date)); ?></small>
        </div>
        <div class="col-cards" style="display: flex; flex-direction: column; gap: 10px;">
            <?php if (isset($agenda_data[$date])): ?>
                <?php foreach ($agenda_data[$date] as $a): ?>
                <div class="card card-agenda" style="border-left: 5px solid var(--primary-red); padding: 12px; font-size: 0.9rem;">
                    <div style="font-weight: 800; color: var(--primary-red); margin-bottom: 5px;">
                        <?php echo substr($a['hora_inicio'], 0, 5); ?> - <?php echo substr($a['hora_fim'], 0, 5); ?>
                    </div>
                    <div style="font-weight: 600; margin-bottom: 3px;"><?php echo htmlspecialchars($a['turma_nome']); ?></div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">
                        <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($a['sala_nome']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 0.8rem; border: 1px dashed var(--border-color); border-radius: 8px;">
                    Sem aulas
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 100px;">
        <i class="fas fa-search" style="font-size: 3rem; color: var(--border-color); margin-bottom: 20px;"></i>
        <h3>Selecione um professor para visualizar a agenda semanal.</h3>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
