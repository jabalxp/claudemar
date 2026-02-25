<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $turma_id = $_POST['turma_id'];
    $prof_ids = array_filter([
        $_POST['professor_id'],
        $_POST['professor_id_2'] ?? null,
        $_POST['professor_id_3'] ?? null,
        $_POST['professor_id_4'] ?? null
    ]);
    $sala_id = $_POST['sala_id'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $dias_semana = isset($_POST['dias_semana']) ? $_POST['dias_semana'] : [];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];

    if (empty($dias_semana)) {
        die("Erro: Selecione ao menos um dia da semana.");
    }
    if (empty($prof_ids)) {
        die("Erro: Selecione ao menos um professor.");
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
            // Check for Professor Conflict (same time overlap) for ALL professors
            foreach ($prof_ids as $pid) {
                $stmt_prof = $mysqli->prepare("
                    SELECT a.*, t.nome as turma_nome, p.nome as professor_nome
                    FROM agenda a 
                    JOIN turmas t ON a.turma_id = t.id
                    JOIN professores p ON (p.id = a.professor_id OR p.id = a.professor_id_2 OR p.id = a.professor_id_3 OR p.id = a.professor_id_4)
                    WHERE (a.professor_id = ? OR a.professor_id_2 = ? OR a.professor_id_3 = ? OR a.professor_id_4 = ?)
                    AND p.id = ?
                    AND a.data = ? 
                    AND (a.hora_inicio < ? AND a.hora_fim > ?)
                ");
                $stmt_prof->bind_param('iiiiisss', $pid, $pid, $pid, $pid, $pid, $date_str, $hora_fim, $hora_inicio);
                $stmt_prof->execute();
                $conf_prof = $stmt_prof->get_result()->fetch_assoc();

                if ($conf_prof) {
                    $conflitos[] = "Conflito: Professor {$conf_prof['professor_nome']} em $date_str já está na Turma {$conf_prof['turma_nome']}.";
                }

                // Check for Turno Conflict (Manhã ↔ Noite rule) for EACH professor
                $new_hi = $hora_inicio;
                $new_hf = $hora_fim;
                $new_is_manha = ($new_hi < '12:00');
                $new_is_noite = ($new_hf > '18:00' || $new_hi >= '18:00');

                $stmt_turno = $mysqli->prepare("
                    SELECT a.hora_inicio, a.hora_fim, t.nome as turma_nome, p.nome as professor_nome
                    FROM agenda a
                    JOIN turmas t ON a.turma_id = t.id
                    JOIN professores p ON (p.id = a.professor_id OR p.id = a.professor_id_2 OR p.id = a.professor_id_3 OR p.id = a.professor_id_4)
                    WHERE (a.professor_id = ? OR a.professor_id_2 = ? OR a.professor_id_3 = ? OR a.professor_id_4 = ?)
                    AND p.id = ?
                    AND a.data = ?
                ");
                $stmt_turno->bind_param('iiiiis', $pid, $pid, $pid, $pid, $pid, $date_str);
                $stmt_turno->execute();
                $existing_classes = $stmt_turno->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($existing_classes as $ec) {
                    $ex_hi = $ec['hora_inicio'];
                    $ex_hf = $ec['hora_fim'];
                    $ex_is_manha = ($ex_hi < '12:00');
                    $ex_is_noite = ($ex_hf > '18:00' || $ex_hi >= '18:00');

                    if ($ex_is_manha && $new_is_noite) {
                        $conflitos[] = "Conflito Turno: Professor {$ec['professor_nome']} em $date_str aula de manhã ({$ec['turma_nome']}), não pode noite.";
                    }
                    if ($ex_is_noite && $new_is_manha) {
                        $conflitos[] = "Conflito Turno: Professor {$ec['professor_nome']} em $date_str aula à noite ({$ec['turma_nome']}), não pode manhã.";
                    }
                }
            }

            // Check for Room Conflict
            $stmt_sala = $mysqli->prepare("
                SELECT a.*, t.nome as turma_nome 
                FROM agenda a 
                JOIN turmas t ON a.turma_id = t.id
                WHERE a.sala_id = ? 
                AND a.data = ? 
                AND (a.hora_inicio < ? AND a.hora_fim > ?)
            ");
            $stmt_sala->bind_param('isss', $sala_id, $date_str, $hora_fim, $hora_inicio);
            $stmt_sala->execute();
            $conf_sala = $stmt_sala->get_result()->fetch_assoc();

            if ($conf_sala) {
                $conflitos[] = "Conflito Sala em $date_str: Sala já ocupada pela Turma {$conf_sala['turma_nome']}.";
            }

            if (empty($conflitos)) {
                $aulas_para_inserir[] = [
                    'turma_id' => $turma_id,
                    'professor_id' => $prof_ids[0] ?? null,
                    'professor_id_2' => $prof_ids[1] ?? null,
                    'professor_id_3' => $prof_ids[2] ?? null,
                    'professor_id_4' => $prof_ids[3] ?? null,
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
    $in_transaction = false;
    try {
        $mysqli->begin_transaction();
        $in_transaction = true;

        $stmt_insert = $mysqli->prepare("INSERT INTO agenda (turma_id, professor_id, professor_id_2, professor_id_3, professor_id_4, sala_id, data, hora_inicio, hora_fim) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($aulas_para_inserir as $aula) {
            $stmt_insert->bind_param('iiiiiisss', $aula['turma_id'], $aula['professor_id'], $aula['professor_id_2'], $aula['professor_id_3'], $aula['professor_id_4'], $aula['sala_id'], $aula['data'], $aula['hora_inicio'], $aula['hora_fim']);
            $stmt_insert->execute();
        }

        $mysqli->commit();
        $in_transaction = false;
        header("Location: index.php?msg=agenda_generated&count=" . count($aulas_para_inserir));
    }
    catch (Exception $e) {
        if ($in_transaction) {
            $mysqli->rollback();
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
