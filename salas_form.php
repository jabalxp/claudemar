<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';


$id = isset($_GET['id']) ? $_GET['id'] : null;
$sala = ['nome' => '', 'tipo' => 'Sala', 'area' => '', 'cidade' => '', 'capacidade' => ''];

if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM salas WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sala = $stmt->get_result()->fetch_assoc();
    if (!$sala) {
        header("Location: salas.php");
        exit;
    }
}
?>

<div class="page-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h2><?php echo $id ? 'Editar Ambiente' : 'Novo Ambiente'; ?></h2>
    <a href="salas.php" class="btn" style="background: #6c757d; color: #fff;"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="salas_process.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Ambiente</label>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($sala['nome']); ?>" required placeholder="Ex: Sala 01, Laboratório de Informática..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tipo de Ambiente</label>
                <select name="tipo" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                    <option value="Sala" <?php echo $sala['tipo'] == 'Sala' ? 'selected' : ''; ?>>Sala</option>
                    <option value="Laboratório" <?php echo $sala['tipo'] == 'Laboratório' ? 'selected' : ''; ?>>Laboratório</option>
                    <option value="Oficina" <?php echo $sala['tipo'] == 'Oficina' ? 'selected' : ''; ?>>Oficina</option>
                    <option value="Teórica" <?php echo $sala['tipo'] == 'Teórica' ? 'selected' : ''; ?>>Teórica</option>
                    <option value="Prática" <?php echo $sala['tipo'] == 'Prática' ? 'selected' : ''; ?>>Prática</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Capacidade (Alunos)</label>
                <input type="number" name="capacidade" value="<?php echo $sala['capacidade']; ?>" required min="1" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Área</label>
                <input type="text" name="area" value="<?php echo htmlspecialchars($sala['area'] ?? ''); ?>" placeholder="Ex: Informática, Metalmecânica..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Cidade</label>
                <input type="text" name="cidade" value="<?php echo htmlspecialchars($sala['cidade'] ?? ''); ?>" placeholder="Ex: Votuporanga" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
