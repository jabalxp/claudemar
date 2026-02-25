<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';


$id = isset($_GET['id']) ? $_GET['id'] : null;
$turma = ['nome' => '', 'curso_id' => '', 'data_inicio' => '', 'data_fim' => '', 'turno' => 'Matutino', 'cidade' => '', 'vagas' => '', 'horario' => '', 'dias_semana' => '', 'docente1' => '', 'docente2' => '', 'docente3' => '', 'docente4' => '', 'ambiente' => '', 'local_turma' => ''];

if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM turmas WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $turma = $stmt->get_result()->fetch_assoc();
    if (!$turma) {
        header("Location: turmas.php");
        exit;
    }
}

// Fetch courses for dropdown
$cursos = $mysqli->query("SELECT id, nome FROM cursos ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
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
                <?php
endforeach; ?>
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

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Turno Definido</label>
                <select name="turno" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                    <option value="" <?php echo empty($turma['turno']) ? 'selected' : ''; ?>>Auto (derivar do horário)</option>
                    <option value="Matutino" <?php echo ($turma['turno'] ?? '') == 'Matutino' ? 'selected' : ''; ?>>Matutino</option>
                    <option value="Vespertino" <?php echo ($turma['turno'] ?? '') == 'Vespertino' ? 'selected' : ''; ?>>Vespertino</option>
                    <option value="Noturno" <?php echo ($turma['turno'] ?? '') == 'Noturno' ? 'selected' : ''; ?>>Noturno</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Cidade / Local</label>
                <input type="text" name="cidade" value="<?php echo htmlspecialchars($turma['cidade'] ?? ''); ?>" placeholder="Ex: Votuporanga" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Vagas</label>
                <input type="number" name="vagas" value="<?php echo $turma['vagas'] ?? ''; ?>" min="0" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Horário</label>
                <input type="text" name="horario" value="<?php echo htmlspecialchars($turma['horario'] ?? ''); ?>" placeholder="Ex: 08h às 17h" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Dias da Semana</label>
                <input type="text" name="dias_semana" value="<?php echo htmlspecialchars($turma['dias_semana'] ?? ''); ?>" placeholder="Ex: 2ª, 3ª E 4º" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Docente 1</label>
                <input type="text" name="docente1" value="<?php echo htmlspecialchars($turma['docente1'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Docente 2</label>
                <input type="text" name="docente2" value="<?php echo htmlspecialchars($turma['docente2'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Docente 3</label>
                <input type="text" name="docente3" value="<?php echo htmlspecialchars($turma['docente3'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Docente 4</label>
                <input type="text" name="docente4" value="<?php echo htmlspecialchars($turma['docente4'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ambiente</label>
                <input type="text" name="ambiente" value="<?php echo htmlspecialchars($turma['ambiente'] ?? ''); ?>" placeholder="Ex: Sala 2 (C1)" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Local</label>
                <input type="text" name="local_turma" value="<?php echo htmlspecialchars($turma['local_turma'] ?? ''); ?>" placeholder="Ex: CFP850" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary">Salvar Turma</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
