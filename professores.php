<?php 
require_once 'includes/db.php';
include 'includes/header.php'; 

// Fetch all professors
$stmt = $pdo->query("SELECT * FROM professores ORDER BY nome ASC");
$professores = $stmt->fetchAll();
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Gestão de Professores</h2>
    <a href="professores_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Professor</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Especialidade</th>
                <th>E-mail</th>
                <th>Cor na Agenda</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($professores)): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Nenhum professor cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($professores as $prof): ?>
                <tr>
                    <td><?php echo htmlspecialchars($prof['nome']); ?></td>
                    <td><?php echo htmlspecialchars($prof['especialidade']); ?></td>
                    <td><?php echo htmlspecialchars($prof['email']); ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="width: 20px; height: 20px; border-radius: 4px; background-color: <?php echo $prof['cor_agenda']; ?>; border: 1px solid #ddd;"></span>
                            <?php echo $prof['cor_agenda']; ?>
                        </div>
                    </td>
                    <td>
                        <a href="professores_form.php?id=<?php echo $prof['id']; ?>" class="btn" style="padding: 5px 10px; background: #ffc107; color: #000;"><i class="fas fa-edit"></i></a>
                        <a href="professores_process.php?action=delete&id=<?php echo $prof['id']; ?>" class="btn" style="padding: 5px 10px; background: #dc3545; color: #fff;" onclick="return confirm('Tem certeza que deseja excluir?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
