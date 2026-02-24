<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $turma_id = $_POST['turma_id'];
    $professor_id = $_POST['professor_id'];
    $sala_id = $_POST['sala_id'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $dias_semana = isset($_POST['dias_semana']) ? $_POST['dias_semana'] : [];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];

    if (empty($dias_semana)) {
        die("Erro: Selecione ao menos um dia da semana.");
    }

    $conflitos = [];
    $aulas_para_inserir = [];

    // Iterate through dates
    $current_date = new DateTime($data_inicio);
    $end_date = new DateTime($data_fim);
    $end_date->modify('+1 day'); // Include the last day

    while ($current_date < $end_date) {
        $date_str = $current_date->format('Y-m-d');
        $dow = $current_date->format('N'); // 1 (Mon) to 7 (Sun)

        if (in_array($dow, $dias_semana)) {
            // Check for Professor Conflict
            $stmt_prof = $pdo->prepare("
                SELECT a.*, t.nome as turma_nome 
                FROM agenda a 
                JOIN turmas t ON a.turma_id = t.id
                WHERE a.professor_id = ? 
                AND a.data = ? 
                AND (a.hora_inicio < ? AND a.hora_fim > ?)
            ");
            $stmt_prof->execute([$professor_id, $date_str, $hora_fim, $hora_inicio]);
            $conf_prof = $stmt_prof->fetch();

            if ($conf_prof) {
                $conflitos[] = "Conflito de Professor em $date_str: Professor já alocado na Turma {$conf_prof['turma_nome']}.";
            }

            // Check for Room Conflict
            $stmt_sala = $pdo->prepare("
                SELECT a.*, t.nome as turma_nome 
                FROM agenda a 
                JOIN turmas t ON a.turma_id = t.id
                WHERE a.sala_id = ? 
                AND a.data = ? 
                AND (a.hora_inicio < ? AND a.hora_fim > ?)
            ");
            $stmt_sala->execute([$sala_id, $date_str, $hora_fim, $hora_inicio]);
            $conf_sala = $stmt_sala->fetch();

            if ($conf_sala) {
                $conflitos[] = "Conflito de Sala em $date_str: Sala já ocupada pela Turma {$conf_sala['turma_nome']}.";
            }

            if (empty($conflitos)) {
                $aulas_para_inserir[] = [
                    'turma_id' => $turma_id,
                    'professor_id' => $professor_id,
                    'sala_id' => $sala_id,
                    'data' => $date_str,
                    'hora_inicio' => $hora_inicio,
                    'hora_fim' => $hora_fim
                ];
            }
        }
        $current_date->modify('+1 day');
    }

    if (!empty($conflitos)) {
        include 'includes/header.php';
?>
        <div class="page-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Falha no Planejamento</h2>
            <p>Foram detectados conflitos que impedem a geração automática da agenda.</p>
        </div>

        <div class="card" style="border-top: 5px solid var(--primary-red);">
            <div style="margin-bottom: 20px;">
                <h3 style="color: var(--primary-red); margin-bottom: 15px;">Conflitos Encontrados (<?php echo count($conflitos); ?>)</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($conflitos as $c): ?>
                        <div style="background: rgba(237, 28, 36, 0.05); padding: 12px 15px; border-radius: 6px; border-left: 3px solid var(--primary-red); display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-calendar-times" style="color: var(--primary-red);"></i>
                            <span><?php echo htmlspecialchars($c); ?></span>
                        </div>
                    <?php
        endforeach; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href='planejamento.php' class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Voltar e Corrigir
                </a>
            </div>
        </div>
        <?php
        include 'includes/footer.php';
        exit;
    }

    // No conflicts, proceed with Insertion using Transaction
    try {
        $pdo->beginTransaction();

        $stmt_insert = $pdo->prepare("INSERT INTO agenda (turma_id, professor_id, sala_id, data, hora_inicio, hora_fim) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($aulas_para_inserir as $aula) {
            $stmt_insert->execute([
                $aula['turma_id'],
                $aula['professor_id'],
                $aula['sala_id'],
                $aula['data'],
                $aula['hora_inicio'],
                $aula['hora_fim']
            ]);
        }

        $pdo->commit();
        header("Location: index.php?msg=agenda_generated&count=" . count($aulas_para_inserir));
    }
    catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        include 'includes/header.php';
?>
        <div class="card">
            <h3 style="color: var(--primary-red);">Erro ao salvar agenda</h3>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href='planejamento.php' class="btn btn-primary">Voltar</a>
        </div>
        <?php
        include 'includes/footer.php';
    }
}
?>
