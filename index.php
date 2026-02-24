<?php 
require_once 'includes/db.php';
include 'includes/header.php'; 

// Fetch stats
$count_prof = $pdo->query("SELECT COUNT(*) FROM professores")->fetchColumn();
$count_salas = $pdo->query("SELECT COUNT(*) FROM salas")->fetchColumn();
$count_turmas = $pdo->query("SELECT COUNT(*) FROM turmas")->fetchColumn();
$count_aulas = $pdo->query("SELECT COUNT(*) FROM agenda")->fetchColumn();

// Fetch today's classes
$today = date('Y-m-d');
$stmt_today = $pdo->prepare("
    SELECT a.*, t.nome as turma_nome, p.nome as professor_nome, s.nome as sala_nome 
    FROM agenda a 
    JOIN turmas t ON a.turma_id = t.id 
    JOIN professores p ON a.professor_id = p.id 
    JOIN salas s ON a.sala_id = s.id 
    WHERE a.data = ? 
    ORDER BY a.hora_inicio ASC
");
$stmt_today->execute([$today]);
$aulas_hoje = $stmt_today->fetchAll();
?>

<div class="dashboard-home">
    <div class="card-grid">
        <div class="card">
            <h4><i class="fas fa-chalkboard-teacher"></i> <?php echo $count_prof; ?> Professores</h4>
            <p>Docentes cadastrados no sistema.</p>
            <a href="professores.php" class="btn btn-primary" style="display:inline-block; margin-top:10px;">Gerenciar</a>
        </div>
        <div class="card">
            <h4><i class="fas fa-door-open"></i> <?php echo $count_salas; ?> Ambientes</h4>
            <p>Salas, labs e oficinas disponíveis.</p>
            <a href="salas.php" class="btn btn-primary" style="display:inline-block; margin-top:10px;">Gerenciar</a>
        </div>
        <div class="card">
            <h4><i class="fas fa-users"></i> <?php echo $count_turmas; ?> Turmas</h4>
            <p>Turmas ativas e planejamentos.</p>
            <a href="turmas.php" class="btn btn-primary" style="display:inline-block; margin-top:10px;">Gerenciar</a>
        </div>
        <div class="card" style="border-left: 5px solid #28a745;">
            <h4><i class="fas fa-calendar-alt"></i> <?php echo $count_aulas; ?> Aulas</h4>
            <p>Total de registros na agenda.</p>
            <a href="planejamento.php" class="btn btn-primary" style="display:inline-block; margin-top:10px;">Novo Planejamento</a>
        </div>
    </div>

    <div class="table-container">
        <h3 style="margin-bottom: 20px;"><i class="fas fa-clock"></i> Próximas Aulas (Hoje - <?php echo date('d/m/Y'); ?>)</h3>
        <table>
            <thead>
                <tr>
                    <th>Horário</th>
                    <th>Turma</th>
                    <th>Professor</th>
                    <th>Sala</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($aulas_hoje)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">Não há aulas agendadas para hoje.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($aulas_hoje as $a): ?>
                    <tr>
                        <td style="font-weight: 800; color: var(--primary-red);"><?php echo substr($a['hora_inicio'], 0, 5); ?> - <?php echo substr($a['hora_fim'], 0, 5); ?></td>
                        <td><strong><?php echo htmlspecialchars($a['turma_nome']); ?></strong></td>
                        <td><?php echo htmlspecialchars($a['professor_nome']); ?></td>
                        <td><span class="badge" style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 4px;"><?php echo htmlspecialchars($a['sala_nome']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
