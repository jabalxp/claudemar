<?php 
require_once 'includes/db.php';
include 'includes/header.php'; 

$stmt = $pdo->query("
    SELECT t.*, c.nome as curso_nome 
    FROM turmas t 
    JOIN cursos c ON t.curso_id = c.id 
    ORDER BY t.data_inicio DESC
");
$turmas = $stmt->fetchAll();
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Gestão de Turmas</h2>
    <a href="turmas_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Turma</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Turma</th>
                <th>Curso</th>
                <th>Período</th>
                <th>Turno</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($turmas)): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Nenhuma turma cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($turmas as $t): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($t['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($t['curso_nome']); ?></td>
                    <td>
                        <?php echo date('d/m/Y', strtotime($t['data_inicio'])); ?> - 
                        <?php echo date('d/m/Y', strtotime($t['data_fim'])); ?>
                    </td>
                    <td>
                        <span class="badge" style="padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; background: var(--bg-color); border: 1px solid var(--border-color);">
                            <?php echo $t['turno']; ?>
                        </span>
                    </td>
                    <td>
                        <a href="turmas_form.php?id=<?php echo $t['id']; ?>" class="btn" style="padding: 5px 10px; background: #ffc107; color: #000;"><i class="fas fa-edit"></i></a>
                        <a href="turmas_process.php?action=delete&id=<?php echo $t['id']; ?>" class="btn" style="padding: 5px 10px; background: #dc3545; color: #fff;" onclick="return confirm('Tem certeza que deseja excluir?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
