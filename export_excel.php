<?php
require_once 'includes/db.php';

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'excel';

// ─────────────────────────────────────────────
// DATA QUERIES
// ─────────────────────────────────────────────

$is_agenda = in_array($tipo, ['agenda', 'agenda_powerbi']);
$is_powerbi = in_array($tipo, ['powerbi', 'agenda_powerbi']);

if ($is_agenda) {
    $stmt = $pdo->query("
        SELECT
            a.data              AS data,
            a.hora_inicio       AS hora_inicio,
            a.hora_fom          AS hora_fim,
            t.nome              AS turma,
            t.turno             AS turno,
            COALESCE(t.cidade, 'Sede') AS cidade,
            c.nome              AS curso,
            p.nome              AS professor,
            p.especialidade     AS especialidade_professor,
            s.nome              AS ambiente_sala,
            s.tipo              AS tipo_sala
        FROM agenda a
        JOIN turmas t ON a.turma_id = t.id
        JOIN cursos c ON t.curso_id = c.id
        JOIN professores p ON a.professor_id = p.id
        JOIN salas s ON a.sala_id = s.id
        ORDER BY a.data ASC, a.hora_inicio ASC
    ");
}
else {
    $stmt = $pdo->query("
        SELECT
            t.id                AS id,
            t.nome              AS turma,
            c.nome              AS curso,
            c.carga_horaria     AS carga_horaria_h,
            t.turno             AS turno,
            COALESCE(t.cidade, 'Sede') AS cidade,
            t.data_inicio       AS data_inicio,
            t.data_fim          AS data_fim,
            DATEDIFF(t.data_fim, t.data_inicio) AS duracao_dias,
            (SELECT COUNT(*) FROM agenda a WHERE a.turma_id = t.id) AS total_aulas_agendadas,
            (SELECT GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ', ')
             FROM agenda a JOIN professores p ON a.professor_id = p.id WHERE a.turma_id = t.id) AS professores,
            (SELECT GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ')
             FROM agenda a JOIN salas s ON a.sala_id = s.id WHERE a.turma_id = t.id) AS ambientes_utilizados
        FROM turmas t
        JOIN cursos c ON t.curso_id = c.id
        ORDER BY t.cidade ASC, t.data_inicio DESC
    ");
}

$data = $stmt->fetchAll();

// If empty, we don't redirect yet; we show the headers.
$has_data = !empty($data);

// ─────────────────────────────────────────────
// HEADER LABELS
// ─────────────────────────────────────────────

$headers_excel = [
    'id' => 'ID',
    'turma' => 'Turma',
    'curso' => 'Curso',
    'carga_horaria_h' => 'Carga Horária (h)',
    'turno' => 'Turno',
    'cidade' => 'Cidade',
    'data_inicio' => 'Data Início',
    'data_fim' => 'Data Fim',
    'duracao_dias' => 'Duração (dias)',
    'total_aulas_agendadas' => 'Total Aulas Agendadas',
    'professores' => 'Professores',
    'ambientes_utilizados' => 'Ambientes Utilizados',
    'data' => 'Data',
    'hora_inicio' => 'Hora Início',
    'hora_fim' => 'Hora Fim',
    'professor' => 'Professor',
    'especialidade_professor' => 'Especialidade',
    'ambiente_sala' => 'Ambiente / Sala',
    'tipo_sala' => 'Tipo Sala',
];

$headers_powerbi = [
    'id' => 'ID',
    'turma' => 'Turma',
    'curso' => 'Curso',
    'carga_horaria_h' => 'Carga_Horaria_Horas',
    'turno' => 'Turno',
    'cidade' => 'Cidade',
    'data_inicio' => 'Data_Inicio',
    'data_fim' => 'Data_Fim',
    'duracao_dias' => 'Duracao_Dias',
    'total_aulas_agendadas' => 'Total_Aulas',
    'professores' => 'Professores',
    'ambientes_utilizados' => 'Ambientes',
    'data' => 'Data',
    'hora_inicio' => 'Hora_Inicio',
    'hora_fim' => 'Hora_Fim',
    'professor' => 'Professor',
    'especialidade_professor' => 'Especialidade',
    'ambiente_sala' => 'Ambiente_Sala',
    'tipo_sala' => 'Tipo_Sala',
];

$headers_map = $is_powerbi ? $headers_powerbi : $headers_excel;

// Determine columns based on headers if data is empty
if ($has_data) {
    $cols = array_keys($data[0]);
}
else {
    $cols = $is_agenda
        ? ['data', 'hora_inicio', 'hora_fim', 'turma', 'turno', 'cidade', 'curso', 'professor', 'especialidade_professor', 'ambiente_sala', 'tipo_sala']
        : ['id', 'turma', 'curso', 'carga_horaria_h', 'turno', 'cidade', 'data_inicio', 'data_fim', 'duracao_dias', 'total_aulas_agendadas', 'professores', 'ambientes_utilizados'];
}

// ─────────────────────────────────────────────
// ROUTE TO GENERATOR
// ─────────────────────────────────────────────

if ($is_powerbi) {
    $filename = $is_agenda ? 'SENAI_Agenda_PowerBI_' . date('Y-m-d') . '.csv' : 'SENAI_Turmas_PowerBI_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

    $header_row = [];
    foreach ($cols as $c)
        $header_row[] = $headers_map[$c] ?? $c;
    fputcsv($out, $header_row, ';');

    if ($has_data) {
        foreach ($data as $row) {
            $line = [];
            foreach ($cols as $c) {
                $v = $row[$c] ?? '';
                if (in_array($c, ['data', 'data_inicio', 'data_fim']) && $v)
                    $v = date('Y-m-d', strtotime($v));
                if (in_array($c, ['hora_inicio', 'hora_fim']) && $v)
                    $v = substr($v, 0, 5);
                $line[] = $v;
            }
            fputcsv($out, $line, ';');
        }
    }
    fclose($out);
    exit;
}

// ── Excel: SpreadsheetML XML → .xls ──
$sheet_name = $is_agenda ? 'Agenda' : 'Turmas';
$filename = $is_agenda ? 'SENAI_Agenda_' . date('Y-m-d') . '.xls' : 'SENAI_Turmas_' . date('Y-m-d') . '.xls';


header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:html="http://www.w3.org/TR/REC-html40">
  <Styles>
    <Style ss:ID="sHeader">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#004A8D"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0056A4"/>
      </Borders>
      <Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#0056A4" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="sDefault"><Alignment ss:Vertical="Center"/><Font ss:FontName="Segoe UI" ss:Size="10"/></Style>
    <Style ss:ID="sEven"><Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/></Style>
    <Style ss:ID="sDate"><NumberFormat ss:Format="Short Date"/></Style>
    <Style ss:ID="sNum"><Alignment ss:Horizontal="Center"/></Style>
    <Style ss:ID="sTime"><Alignment ss:Horizontal="Center"/><Font ss:Color="#D32F2F" ss:Bold="1"/></Style>
  </Styles>
  <Worksheet ss:Name="<?php echo xe($sheet_name); ?>">
    <Table ss:DefaultRowHeight="18">
      <?php foreach ($cols as $c): ?>
      <Column ss:AutoFitWidth="1" ss:Width="100"/>
      <?php
endforeach; ?>
      <Row ss:Height="25" ss:StyleID="sHeader">
        <?php foreach ($cols as $c): ?>
        <Cell><Data ss:Type="String"><?php echo xe($headers_map[$c] ?? $c); ?></Data></Cell>
        <?php
endforeach; ?>
      </Row>
      <?php if ($has_data):
    $numeric_cols = ['id', 'carga_horaria_h', 'duracao_dias', 'total_aulas_agendadas'];
    $time_cols = ['hora_inicio', 'hora_fim'];
    $date_cols = ['data', 'data_inicio', 'data_fim'];
    foreach ($data as $i => $row):
        $row_style = ($i % 2 === 0) ? 'sEven' : '';
?>
      <Row ss:StyleID="sDefault">
        <?php foreach ($cols as $c):
            $v = $row[$c] ?? '';
            $type = 'String';
            $style = $row_style;
            if (in_array($c, $numeric_cols)) {
                $type = 'Number';
                $style .= ' sNum';
            }
            elseif (in_array($c, $date_cols)) {
                $type = 'DateTime';
                $style .= ' sDate';
                $v = $v ? date('Y-m-d\T00:00:00.000', strtotime($v)) : '';
            }
            elseif (in_array($c, $time_cols)) {
                $v = substr($v, 0, 5);
                $style .= ' sTime';
            }
?><Cell ss:StyleID="<?php echo trim($style); ?>"><Data ss:Type="<?php echo $type; ?>"><?php echo xe($v); ?></Data></Cell><?php
        endforeach; ?>
      </Row>
      <?php
    endforeach;
endif; ?>
    </Table>
  </Worksheet>
</Workbook>
<?php exit;
