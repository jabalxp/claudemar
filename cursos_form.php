<?php

require_once 'includes/db.php';
include 'includes/header.php';


$id = isset($_GET['id']) ? $_GET['id'] : null;
$curso = ['nome' => '', 'carga_horaria' => ''];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->execute([$id]);
    $curso = $stmt->fetch();
    if (!$curso) {
        header("Location: cursos.php");
        exit;
    }
}
?>

<div class="page-header" style="margin-bottom: 20px;">
    <h2><?php echo $id ? 'Editar Curso' : 'Novo Curso'; ?></h2>
    <a href="cursos.php" class="btn" style="background: #6c757d; color: #fff;"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="cursos_process.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Curso</label>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($curso['nome']); ?>" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Carga Horária (Total de Horas)</label>
            <input type="number" name="carga_horaria" value="<?php echo $curso['carga_horaria']; ?>" required min="1" step="1" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
