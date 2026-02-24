<?php

require_once 'includes/db.php';
include 'includes/header.php';


$id = isset($_GET['id']) ? $_GET['id'] : null;
$prof = ['nome' => '', 'especialidade' => '', 'email' => '', 'cor_agenda' => '#ed1c24'];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM professores WHERE id = ?");
    $stmt->execute([$id]);
    $prof = $stmt->fetch();
    if (!$prof) {
        header("Location: professores.php");
        exit;
    }
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2><?php echo $id ? 'Editar Professor' : 'Novo Professor'; ?></h2>
    <a href="professores.php" class="btn" style="background: #6c757d; color: #fff;"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="professores_process.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome Completo</label>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($prof['nome']); ?>" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Especialidade</label>
            <input type="text" name="especialidade" value="<?php echo htmlspecialchars($prof['especialidade']); ?>" placeholder="Ex: Programação, Elétrica..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">E-mail</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($prof['email']); ?>" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Cor na Agenda</label>
            <input type="color" name="cor_agenda" value="<?php echo $prof['cor_agenda']; ?>" style="width: 100%; height: 40px; padding: 2px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg);">
            <small style="color: var(--text-muted);">Esta cor será usada para identificar as aulas deste professor no calendário.</small>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
