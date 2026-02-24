<?php 
require_once 'includes/db.php';
include 'includes/header.php'; 

$id = isset($_GET['id']) ? $_GET['id'] : null;
$turma = ['nome' => '', 'curso_id' => '', 'data_inicio' => '', 'data_fim' => '', 'turno' => 'Matutino'];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ?");
    $stmt->execute([$id]);
    $turma = $stmt->fetch();
    if (!$turma) {
        header("Location: turmas.php");
        exit;
    }
}

// Fetch courses for dropdown
$stmt_cursos = $pdo->query("SELECT id, nome FROM cursos ORDER BY nome ASC");
$cursos = $stmt_cursos->fetchAll();
?>

<div class="page-header" style="margin-bottom: 20px;">
    <h2><?php echo $id ? 'Editar Turma' : 'Nova Turma'; ?></h2>
    <a href="turmas.php" class="btn" style="background: #6c757d; color: #fff;"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <form action="turmas_process.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Código da Turma / Nome</label>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($turma['nome']); ?>" required placeholder="Ex: DES-2024-M" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Curso</label>
            <select name="curso_id" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                <option value="">Selecione um curso...</option>
                <?php foreach ($cursos as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $turma['curso_id'] == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Data Início</label>
                <input type="date" name="data_inicio" value="<?php echo $turma['data_inicio']; ?>" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Data Fim</label>
                <input type="date" name="data_fim" value="<?php echo $turma['data_fim']; ?>" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Turno Definado</label>
            <select name="turno" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                <option value="Matutino" <?php echo $turma['turno'] == 'Matutino' ? 'selected' : ''; ?>>Matutino</option>
                <option value="Vespertino" <?php echo $turma['turno'] == 'Vespertino' ? 'selected' : ''; ?>>Vespertino</option>
                <option value="Noturno" <?php echo $turma['turno'] == 'Noturno' ? 'selected' : ''; ?>>Noturno</option>
            </select>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary">Salvar Turma</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
