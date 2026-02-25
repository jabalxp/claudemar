<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';


$id = isset($_GET['id']) ? $_GET['id'] : null;
$curso = ['nome' => '', 'carga_horaria' => '', 'tipo' => '', 'area' => ''];

if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc();
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

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tipo</label>
                <input type="text" name="tipo" value="<?php echo htmlspecialchars($curso['tipo'] ?? ''); ?>" placeholder="Ex: FIC, Técnico" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Área</label>
                <input type="text" name="area" value="<?php echo htmlspecialchars($curso['area'] ?? ''); ?>" placeholder="Ex: AUTOMOTIVA" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Carga Horária (h)</label>
                <input type="number" name="carga_horaria" value="<?php echo $curso['carga_horaria']; ?>" required min="1" step="1" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
