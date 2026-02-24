<?php
require_once 'includes/db.php';
include 'includes/header.php';

// ────────────────────────────────────────────────────────
// HELPER: Robust Date Parsing
// ────────────────────────────────────────────────────────
function parseExcelDate($v)
{
    if (!$v)
        return null;
    $v = trim((string)$v);

    // dd/mm/yyyy
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // yyyy-mm-dd (PBI or ISO)
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    // Excel Serial Number (SheetJS might return it if raw:true)
    if (is_numeric($v) && $v > 30000 && $v < 60000) {
        $unix = ($v - 25569) * 86400;
        return date('Y-m-d', $unix);
    }
    return null;
}

// ────────────────────────────────────────────────────────
// DATA IMPORT LOGIC
// ────────────────────────────────────────────────────────
$import_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_data'])) {
    $import_type = $_POST['import_type'];
    $json_data = json_decode($_POST['import_data'], true);

    $success = 0;
    $errors = [];

    foreach ($json_data as $i => $row) {
        // Normalize keys: trim + lowercase
        $r = [];
        foreach ($row as $k => $v) {
            $r[strtolower(trim($k))] = trim((string)$v);
        }

        try {
            if ($import_type === 'professores') {
                $nome = $r['nome'] ?? $r['professor'] ?? '';
                $esp = $r['especialidade'] ?? $r['especialidade_professor'] ?? '';
                if (!$nome)
                    throw new Exception("Nome vazio na linha " . ($i + 2));
                $pdo->prepare("INSERT INTO professores (nome, especialidade) VALUES (?, ?)")
                    ->execute([$nome, $esp]);

            }
            elseif ($import_type === 'salas') {
                $nome = $r['nome'] ?? $r['ambiente / sala'] ?? $r['ambiente'] ?? $r['sala'] ?? '';
                $tipo = $r['tipo'] ?? $r['tipo sala'] ?? $r['tipo_sala'] ?? 'Teórica';
                $cap = (int)($r['capacidade'] ?? 0);
                if (!$nome)
                    throw new Exception("Nome vazio na linha " . ($i + 2));
                $pdo->prepare("INSERT INTO salas (nome, tipo, capacidade) VALUES (?, ?, ?)")
                    ->execute([$nome, $tipo, $cap]);

            }
            elseif ($import_type === 'cursos') {
                $nome = $r['nome'] ?? $r['curso'] ?? '';
                $ch = (int)($r['carga horária (h)'] ?? $r['carga_horaria_horas'] ?? $r['carga_horaria'] ?? 0);
                if (!$nome)
                    throw new Exception("Nome vazio na linha " . ($i + 2));
                $pdo->prepare("INSERT INTO cursos (nome, carga_horaria) VALUES (?, ?)")
                    ->execute([$nome, $ch]);

            }
            elseif ($import_type === 'turmas') {
                $nome = $r['turma'] ?? $r['nome'] ?? '';
                $cnome = $r['curso'] ?? '';
                $turno = $r['turno'] ?? 'Matutino';
                $cidade = $r['cidade'] ?? '';
                $di = parseExcelDate($r['data início'] ?? $r['data_inicio'] ?? '');
                $df = parseExcelDate($r['data fim'] ?? $r['data_fim'] ?? '');

                if (!$nome)
                    throw new Exception("Turma não identificada na linha " . ($i + 2));
                if (!$cnome)
                    throw new Exception("Curso não identificado na linha " . ($i + 2));
                if (!$di || !$df)
                    throw new Exception("Datas inválidas na linha " . ($i + 2));

                $sc = $pdo->prepare("SELECT id FROM cursos WHERE nome = ?");
                $sc->execute([$cnome]);
                $curso_id = $sc->fetchColumn();
                if (!$curso_id)
                    throw new Exception("Curso \"$cnome\" não encontrado (Linha " . ($i + 2) . ")");

                $pdo->prepare("INSERT INTO turmas (nome, curso_id, turno, cidade, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$nome, $curso_id, $turno, ($cidade ?: 'Sede'), $di, $df]);

            }
            elseif ($import_type === 'agenda') {
                $tname = $r['turma'] ?? '';
                $pname = $r['professor'] ?? '';
                $sname = $r['ambiente / sala'] ?? $r['sala'] ?? $r['ambiente'] ?? '';
                $data_val = parseExcelDate($r['data'] ?? '');
                $hi = $r['hora início'] ?? $r['hora_inicio'] ?? '';
                $hf = $r['hora fim'] ?? $r['hora_fim'] ?? '';

                if (!$tname || !$pname || !$sname || !$data_val || !$hi || !$hf) {
                    throw new Exception("Dados incompletos na linha " . ($i + 2));
                }

                $st = $pdo->prepare("SELECT id FROM turmas WHERE nome = ?");
                $st->execute([$tname]);
                $turma_id = $st->fetchColumn();

                $sp = $pdo->prepare("SELECT id FROM professores WHERE nome = ?");
                $sp->execute([$pname]);
                $prof_id = $sp->fetchColumn();

                $ss = $pdo->prepare("SELECT id FROM salas WHERE nome = ?");
                $ss->execute([$sname]);
                $sala_id = $ss->fetchColumn();

                if (!$turma_id)
                    throw new Exception("Turma \"$tname\" não existe (Linha " . ($i + 2) . ")");
                if (!$prof_id)
                    throw new Exception("Professor \"$pname\" não existe (Linha " . ($i + 2) . ")");
                if (!$sala_id)
                    throw new Exception("Sala \"$sname\" não existe (Linha " . ($i + 2) . ")");

                $pdo->prepare("INSERT INTO agenda (turma_id, professor_id, sala_id, data, hora_inicio, hora_fim) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$turma_id, $prof_id, $sala_id, $data_val, $hi, $hf]);
            }
            $success++;
        }
        catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    $import_result = ['success' => $success, 'errors' => $errors, 'type' => $import_type];
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-file-import"></i> Sistema de Importação</h2>
        <p>Arraste arquivos e importe registros para o banco de dados.</p>
    </div>
    <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Home</a>
</div>

<?php if ($import_result): ?>
<div class="card" style="border-left: 5px solid <?php echo empty($import_result['errors']) ? '#28a745' : '#ffc107'; ?>; margin-bottom: 30px;">
    <h3><i class="fas fa-info-circle"></i> Resultado</h3>
    <p>Sucesso: <strong><?php echo (int)$import_result['success']; ?></strong> | Erros: <strong><?php echo count($import_result['errors']); ?></strong></p>
    <?php if (!empty($import_result['errors'])): ?>
        <div style="max-height: 150px; overflow: auto; background: #fff3cd; padding: 10px; margin-top: 10px; font-size: 0.8rem;">
            <?php foreach ($import_result['errors'] as $err)
            echo "<div>• " . xe($err) . "</div>"; ?>
        </div>
    <?php
    endif; ?>
</div>
<?php
endif; ?>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <label style="font-weight: 700; display: block; margin-bottom: 8px;">1. Tipo de Dado</label>
        <select id="import_type" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); font-weight: 600;">
            <option value="agenda">Agenda (Datas, Horários, Professores, Salas)</option>
            <option value="turmas">Turmas (Nome, Curso, Turno, etc)</option>
            <option value="professores">Professores (Nome, Especialidade)</option>
            <option value="salas">Ambientes (Nome, Tipo, Capacidade)</option>
            <option value="cursos">Cursos (Nome, CH)</option>
        </select>
    </div>

    <div id="drop_zone" style="border: 2px dashed var(--primary-red); border-radius: 15px; padding: 50px 20px; text-align: center; cursor: pointer; background: rgba(237,28,36,0.01); transition: 0.3s;">
        <i class="fas fa-cloud-upload-alt" style="font-size: 3.5rem; color: var(--primary-red); margin-bottom: 15px;"></i>
        <h3>Arraste o Excel ou CSV aqui</h3>
        <p style="color:var(--text-muted);">Formatos aceitos: .xlsx, .xls, .csv</p>
        <input type="file" id="file_input" accept=".xlsx,.xls,.cvs" style="display: none;">
    </div>

    <div id="preview_area" style="display: none; margin-top: 30px;">
        <h3 style="margin-bottom: 15px;">Prévia dos Dados (<span id="count_label">0</span>)</h3>
        <div style="max-height: 350px; overflow: auto; border: 1px solid var(--border-color); border-radius: 8px;">
            <table id="preview_table" style="font-size: 0.8rem; min-width: 100%;">
                <thead style="position: sticky; top:0; background: var(--bg-color); z-index: 5;">
                    <tr id="preview_header"></tr>
                </thead>
                <tbody id="preview_body"></tbody>
            </table>
        </div>
        
        <form method="POST" id="confirm_form" style="margin-top: 25px; text-align: right;">
            <input type="hidden" name="import_type" id="form_import_type">
            <input type="hidden" name="import_data" id="form_import_data">
            <button type="button" class="btn" onclick="location.reload()" style="background:var(--bg-color);">Limpar</button>
            <button type="submit" class="btn btn-primary" style="padding: 12px 35px;">Confirmar Importação</button>
        </form>
    </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
<script>
const dropZone = document.getElementById('drop_zone');
const fileInput = document.getElementById('file_input');

dropZone.onclick = () => fileInput.click();
dropZone.ondragover = e => { e.preventDefault(); dropZone.style.background = 'rgba(237,28,36,0.08)'; };
dropZone.ondragleave = () => dropZone.style.background = 'rgba(237,28,36,0.01)';
dropZone.ondrop = e => { e.preventDefault(); dropZone.style.background = 'rgba(237,28,36,0.01)'; handleFile(e.dataTransfer.files[0]); };
fileInput.onchange = e => handleFile(e.target.files[0]);

function handleFile(file) {
    if(!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        try {
            const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array', cellDates: true });
            const ws = wb.Sheets[wb.SheetNames[0]];
            const data = XLSX.utils.sheet_to_json(ws, { raw: false, dateNF: 'dd/mm/yyyy' });
            if(!data.length) return alert("Arquivo sem dados!");
            renderPreview(data);
        } catch(err) {
            console.error(err);
            alert("Erro ao ler arquivo. Verifique o formato.");
        }
    };
    reader.readAsArrayBuffer(file);
}

function renderPreview(data) {
    const header = document.getElementById('preview_header');
    const body = document.getElementById('preview_body');
    const cols = Object.keys(data[0]);
    
    header.innerHTML = ''; body.innerHTML = '';
    cols.forEach(c => { const th = document.createElement('th'); th.textContent = c; header.appendChild(th); });
    
    data.slice(0, 50).forEach(row => {
        const tr = document.createElement('tr');
        cols.forEach(c => { const td = document.createElement('td'); td.textContent = row[c] || ''; tr.appendChild(td); });
        body.appendChild(tr);
    });
    
    document.getElementById('count_label').textContent = data.length + " registros";
    document.getElementById('form_import_type').value = document.getElementById('import_type').value;
    document.getElementById('form_import_data').value = JSON.stringify(data);
    document.getElementById('preview_area').style.display = 'block';
    dropZone.innerHTML = `<i class="fas fa-check-circle" style="font-size:3rem; color:#28a745;"></i><h3>Arquivo pronto!</h3><p>${data.length} registros detectados.</p>`;
}
</script>

<?php include 'includes/footer.php'; ?>
