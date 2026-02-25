<?php 
require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php'; 

$cursos = $mysqli->query("SELECT * FROM cursos ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Gestão de Cursos</h2>
    <a href="cursos_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Curso</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nome do Curso</th>
                <th>Carga Horária</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($cursos)): ?>
                <tr>
                    <td colspan="3" style="text-align: center;">Nenhum curso cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($cursos as $curso): ?>
                <tr>
                    <td><?php echo htmlspecialchars($curso['nome']); ?></td>
                    <td><?php echo $curso['carga_horaria']; ?> horas</td>
                    <td>
                        <a href="cursos_form.php?id=<?php echo $curso['id']; ?>" class="btn" style="padding: 5px 10px; background: #ffc107; color: #000;"><i class="fas fa-edit"></i></a>
                        <?php if (can_delete()): ?>
                        <a href="cursos_process.php?action=delete&id=<?php echo $curso['id']; ?>" class="btn" style="padding: 5px 10px; background: #dc3545; color: #fff;" onclick="return confirm('Tem certeza que deseja excluir?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
