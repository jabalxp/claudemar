<?php 
require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php'; 

// Fetch all rooms
$salas = $mysqli->query("SELECT * FROM salas ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Gestão de Ambientes (Salas)</h2>
    <a href="salas_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Ambiente</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Capacidade</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($salas)): ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Nenhum ambiente cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($salas as $sala): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sala['nome']); ?></td>
                    <td>
                        <span class="badge" style="padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; background: var(--bg-color); border: 1px solid var(--border-color);">
                            <?php echo $sala['tipo']; ?>
                        </span>
                    </td>
                    <td><?php echo $sala['capacidade']; ?> alunos</td>
                    <td>
                        <a href="salas_form.php?id=<?php echo $sala['id']; ?>" class="btn" style="padding: 5px 10px; background: #ffc107; color: #000;"><i class="fas fa-edit"></i></a>
                        <?php if (can_delete()): ?>
                        <a href="salas_process.php?action=delete&id=<?php echo $sala['id']; ?>" class="btn" style="padding: 5px 10px; background: #dc3545; color: #fff;" onclick="return confirm('Tem certeza que deseja excluir?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
