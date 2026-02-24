<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Helper for date navigation
$view_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$sala_id = isset($_GET['sala_id']) ? $_GET['sala_id'] : null;

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

$salas = $pdo->query("SELECT id, nome FROM salas ORDER BY nome ASC")->fetchAll();

if ($sala_id) {
    // Fetch agenda for this sala and this week
    $sunday = date('Y-m-d', strtotime("$monday +6 days"));
    $stmt = $pdo->prepare("
        SELECT a.*, t.nome as turma_nome, p.nome as professor_nome, p.cor_agenda 
        FROM agenda a 
        JOIN turmas t ON a.turma_id = t.id 
        JOIN professores p ON a.professor_id = p.id 
        WHERE a.sala_id = ? AND a.data BETWEEN ? AND ?
        ORDER BY a.hora_inicio ASC
    ");
    $stmt->execute([$sala_id, $monday, $sunday]);
    $aulas = $stmt->fetchAll();
    
    // Group by date
    $agenda_data = [];
    foreach ($aulas as $aula) {
        $agenda_data[$aula['data']][] = $aula;
    }
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Agenda de Ambientes (Salas)</h2>
    <div style="display: flex; gap: 10px;">
        <select onchange="window.location.href='?sala_id=' + this.value" style="padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            <option value="">Selecione um Ambiente...</option>
            <?php foreach ($salas as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $sala_id == $s['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($sala_id): ?>
<div class="calendar-controls" style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 20px;">
    <a href="?sala_id=<?php echo $sala_id; ?>&date=<?php echo date('Y-m-d', strtotime("$monday -7 days")); ?>" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color);"><i class="fas fa-chevron-left"></i> Semana Anterior</a>
    <span style="font-weight: 600; font-size: 1.1rem;"><?php echo date('d/m', strtotime($monday)); ?> - <?php echo date('d/m', strtotime($sunday)); ?></span>
    <a href="?sala_id=<?php echo $sala_id; ?>&date=<?php echo date('Y-m-d', strtotime("$monday +7 days")); ?>" class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color);">Próxima Semana <i class="fas fa-chevron-right"></i></a>
</div>

<div class="agenda-trello" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; overflow-x: auto;">
    <?php foreach ($week_days as $date => $info): ?>
    <div class="trello-col" style="min-width: 150px;">
        <div class="col-header" style="text-align: center; margin-bottom: 10px; padding: 10px; background: var(--card-bg); border-radius: 8px; border-bottom: 3px solid #333;">
            <strong><?php echo $info['name']; ?></strong><br>
            <small style="color: var(--text-muted);"><?php echo date('d/m', strtotime($date)); ?></small>
        </div>
        <div class="col-cards" style="display: flex; flex-direction: column; gap: 10px;">
            <?php if (isset($agenda_data[$date])): ?>
                <?php foreach ($agenda_data[$date] as $a): ?>
                <div class="card card-agenda" style="border-left: 5px solid <?php echo $a['cor_agenda']; ?>; padding: 12px; font-size: 0.9rem;">
                    <div style="font-weight: 800; color: <?php echo $a['cor_agenda']; ?>; margin-bottom: 5px;">
                        <?php echo substr($a['hora_inicio'], 0, 5); ?> - <?php echo substr($a['hora_fim'], 0, 5); ?>
                    </div>
                    <div style="font-weight: 600; margin-bottom: 3px;"><?php echo htmlspecialchars($a['turma_nome']); ?></div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($a['professor_nome']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 0.8rem; border: 1px dashed var(--border-color); border-radius: 8px;">
                    Ambiente Livre
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 100px;">
        <i class="fas fa-door-open" style="font-size: 3rem; color: var(--border-color); margin-bottom: 20px;"></i>
        <h3>Selecione um ambiente para visualizar a ocupação semanal.</h3>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
