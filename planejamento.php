<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';


// Fetch Turmas with Curso Name
$turmas = $mysqli->query("SELECT t.id, t.nome, c.nome as curso_nome, t.data_inicio, t.data_fim, t.turno FROM turmas t JOIN cursos c ON t.curso_id = c.id ORDER BY t.data_inicio DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch Professors
$professores = $mysqli->query("SELECT id, nome FROM professores ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch Rooms
$salas = $mysqli->query("SELECT id, nome FROM salas ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header" style="margin-bottom: 20px;">
    <h2>Planejamento de Turmas</h2>
    <p>Agende as aulas de uma turma automaticamente por período, com detecção de conflitos.</p>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <form id="form-planejamento" action="planejamento_process.php" method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Turma Selection -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Turma para Agendar</label>
                <select name="turma_id" id="turma_select" required style="width: 100%; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                    <option value="">Selecione a turma...</option>
                    <?php foreach ($turmas as $t): ?>
                        <option value="<?php echo $t['id']; ?>" 
                                data-inicio="<?php echo $t['data_inicio']; ?>" 
                                data-fim="<?php echo $t['data_fim']; ?>"
                                data-turno="<?php echo $t['turno']; ?>">
                            <?php echo htmlspecialchars($t['nome']); ?> (<?php echo htmlspecialchars($t['curso_nome']); ?>)
                        </option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <!-- Professors Selection -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Professores Responsáveis (Até 4)</label>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <select name="professor_id" required style="width: 100%; padding: 12px; border-radius: 6px; border: 2px solid var(--primary-red); background: var(--card-bg); color: var(--text-color); font-weight: 600;">
                        <option value="">1º Professor (Obrigatório)...</option>
                        <?php foreach ($professores as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                        <select name="professor_id_2" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color); font-size: 0.9rem;">
                            <option value="">2º Prof. (Opcional)</option>
                            <?php foreach ($professores as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="professor_id_3" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color); font-size: 0.9rem;">
                            <option value="">3º Prof. (Opcional)</option>
                            <?php foreach ($professores as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="professor_id_4" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color); font-size: 0.9rem;">
                            <option value="">4º Prof. (Opcional)</option>
                            <?php foreach ($professores as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Room Selection -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ambiente (Sala)</label>
                <select name="sala_id" required style="width: 100%; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                    <option value="">Selecione a sala...</option>
                    <?php foreach ($salas as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nome']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <!-- Pre-fill based on Turma -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Período do Planejamento</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="date" name="data_inicio" id="data_inicio" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                    <span>até</span>
                    <input type="date" name="data_fim" id="data_fim" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
                </div>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">Dias da Semana</label>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php
$dias = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
foreach ($dias as $num => $nome): ?>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" name="dias_semana[]" value="<?php echo $num; ?>" <?php echo $num <= 5 ? 'checked' : ''; ?>>
                        <?php echo $nome; ?>
                    </label>
                <?php
endforeach; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Horário de Início</label>
                <input type="time" name="hora_inicio" id="hora_inicio" required value="08:00" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Horário de Fim</label>
                <input type="time" name="hora_fim" id="hora_fim" required value="12:00" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color);">
            </div>
        </div>

        <div style="background: rgba(237, 28, 36, 0.05); padding: 15px; border-radius: 8px; border-left: 4px solid var(--primary-red); margin-bottom: 25px;">
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                <i class="fas fa-info-circle"></i> O sistema irá gerar aulas apenas para os dias selecionados dentro do período informado. 
                <strong>Conflitos de Professor ou Sala serão detectados automaticamente.</strong>
            </p>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary btn-lg" style="padding: 12px 30px; font-size: 1.1rem;">
                <i class="fas fa-magic"></i> Gerar Agenda e Validar Conflitos
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('turma_select').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (selected.value) {
        document.getElementById('data_inicio').value = selected.getAttribute('data-inicio');
        document.getElementById('data_fim').value = selected.getAttribute('data-fim');
        
        const turno = selected.getAttribute('data-turno');
        if (turno === 'Matutino') {
            document.getElementById('hora_inicio').value = '08:00';
            document.getElementById('hora_fim').value = '12:00';
        } else if (turno === 'Vespertino') {
            document.getElementById('hora_inicio').value = '13:30';
            document.getElementById('hora_fim').value = '17:30';
        } else if (turno === 'Noturno') {
            document.getElementById('hora_inicio').value = '18:30';
            document.getElementById('hora_fim').value = '22:30';
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
