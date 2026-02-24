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
            a.hora_fim          AS hora_fim,
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

if (empty($data)) {
    header("Location: index.php?msg=nodata");
    exit;
}

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
$cols = array_keys($data[0]);

// ─────────────────────────────────────────────
// ROUTE TO GENERATOR
// ─────────────────────────────────────────────

if ($is_powerbi) {
    // ── Power BI: robust CSV (semicolon, ISO dates, WITH BOM for Excel/PBI compatibility in BR) ──
    $filename = $is_agenda
        ? 'SENAI_Agenda_PowerBI_' . date('Y-m-d') . '.csv'
        : 'SENAI_Turmas_PowerBI_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $out = fopen('php://output', 'w');

    // Add BOM for UTF-8 (Excel and Power BI in BR pattern love this)
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    $header_row = [];
    foreach ($cols as $c) {
        $header_row[] = $headers_map[$c] ?? $c;
    }
    // Using semicolon (;) as delimiter is safer for Brazilian regional settings
    fputcsv($out, $header_row, ';');

    // Data rows
    foreach ($data as $row) {
        $line = [];
        foreach ($cols as $c) {
            $v = $row[$c] ?? '';
            if (in_array($c, ['data', 'data_inicio', 'data_fim']) && $v) {
                $v = date('Y-m-d', strtotime($v)); // ISO for Power BI
            }
            if (in_array($c, ['hora_inicio', 'hora_fim']) && $v) {
                $v = substr($v, 0, 5);
            }
            $line[] = $v;
        }
        fputcsv($out, $line, ';');
    }

    fclose($out);
    exit;
}

// ─────────────────────────────────────────────
// COLUMN WIDTHS (Excel)
// ─────────────────────────────────────────────
$col_widths = [
    'id' => 50,
    'turma' => 120,
    'curso' => 200,
    'carga_horaria_h' => 90,
    'turno' => 90,
    'cidade' => 130,
    'data_inicio' => 90,
    'data_fim' => 90,
    'duracao_dias' => 80,
    'total_aulas_agendadas' => 90,
    'professores' => 200,
    'ambientes_utilizados' => 160,
    'data' => 90,
    'hora_inicio' => 80,
    'hora_fim' => 80,
    'professor' => 170,
    'especialidade_professor' => 160,
    'ambiente_sala' => 140,
    'tipo_sala' => 100,
];

// ── Excel: SpreadsheetML XML → .xls (beautifully formatted) ──
$sheet_name = $is_agenda ? 'Agenda SENAI' : 'Turmas SENAI';
$filename = $is_agenda
    ? 'SENAI_Agenda_' . date('Y-m-d') . '.xls'
    : 'SENAI_Turmas_' . date('Y-m-d') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Helper to escape XML special chars
function xe($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:html="http://www.w3.org/TR/REC-html40">

  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
    <Title><?php echo xe($sheet_name); ?></Title>
    <Author>SENAI Gestão Escolar</Author>
    <Created><?php echo date('Y-m-d\TH:i:s\Z'); ?></Created>
  </DocumentProperties>

  <Styles>
    <!-- Header style: modern blue SENAI, white bold text, centered -->
    <Style ss:ID="sHeader">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#004A8D"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0056A4"/>
      </Borders>
      <Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#0056A4" ss:Pattern="Solid"/>
    </Style>
    <!-- Row even: very light gray -->
    <Style ss:ID="sEven">
      <Alignment ss:Vertical="Center" ss:WrapText="0"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:Color="#333333" ss:Size="10" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/>
    </Style>
    <!-- Row odd: white -->
    <Style ss:ID="sOdd">
      <Alignment ss:Vertical="Center" ss:WrapText="0"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EEEEEE"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EEEEEE"/>
      </Borders>
      <Font ss:Color="#333333" ss:Size="10" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>
    </Style>
    <!-- Number style -->
    <Style ss:ID="sNum">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:Color="#333333" ss:Size="10" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/>
    </Style>
    <!-- City badge style: blue tint -->
    <Style ss:ID="sCity">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DAE1E7"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DAE1E7"/>
      </Borders>
      <Font ss:Color="#004A8D" ss:Bold="1" ss:Size="10" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#E6F0F8" ss:Pattern="Solid"/>
    </Style>
    <!-- Time style: centered -->
    <Style ss:ID="sTime">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:Color="#D32F2F" ss:Bold="1" ss:Size="10" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#FFEBEE" ss:Pattern="Solid"/>
    </Style>
  </Styles>

  <Worksheet ss:Name="<?php echo xe($sheet_name); ?>">
    <Table ss:DefaultRowHeight="16">

      <?php // Column widths
foreach ($cols as $c):
    $w = $col_widths[$c] ?? 100;
?>
      <Column ss:AutoFitWidth="0" ss:Width="<?php echo $w; ?>"/>
      <?php
endforeach; ?>

      <!-- Header Row -->
      <Row ss:Height="24">
        <?php foreach ($cols as $c): ?>
        <Cell ss:StyleID="sHeader">
          <Data ss:Type="String"><?php echo xe($headers_map[$c] ?? $c); ?></Data>
        </Cell>
        <?php
endforeach; ?>
      </Row>

      <?php
$numeric_cols = ['id', 'carga_horaria_h', 'duracao_dias', 'total_aulas_agendadas'];
$time_cols = ['hora_inicio', 'hora_fim'];
$city_cols = ['cidade'];
$date_cols = ['data', 'data_inicio', 'data_fim'];

$row_i = 0;
foreach ($data as $row):
    $style_base = ($row_i % 2 === 0) ? 'sEven' : 'sOdd';
    $row_i++;
?>
      <Row ss:Height="16">
        <?php foreach ($cols as $c):
        $v = $row[$c] ?? '';

        // Format dates
        if (in_array($c, $date_cols) && $v) {
            $v = date('d/m/Y', strtotime($v));
        }
        // Format times
        if (in_array($c, $time_cols) && $v) {
            $v = substr($v, 0, 5);
        }

        // Pick style
        if (in_array($c, $time_cols)) {
            $style = 'sTime';
        }
        elseif (in_array($c, $city_cols)) {
            $style = 'sCity';
        }
        elseif (in_array($c, $numeric_cols)) {
            $style = 'sNum';
        }
        else {
            $style = $style_base;
        }

        $type = in_array($c, $numeric_cols) ? 'Number' : 'String';
?>
        <Cell ss:StyleID="<?php echo $style; ?>">
          <Data ss:Type="<?php echo $type; ?>"><?php echo xe($v); ?></Data>
        </Cell>
        <?php
    endforeach; ?>
      </Row>
      <?php
endforeach; ?>

    </Table>

    <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
      <FreezePanes/>
      <FrozenNoSplit/>
      <SplitHorizontal>1</SplitHorizontal>
      <TopRowBottomPane>1</TopRowBottomPane>
      <ActivePane>2</ActivePane>
    </WorksheetOptions>

  </Worksheet>
</Workbook>
<?php
exit;
