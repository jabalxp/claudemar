<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'completo';

// ─────────────────────────────────────────────
// PowerBI CSV export (mantém compatibilidade)
// ─────────────────────────────────────────────
$is_powerbi = in_array($tipo, ['powerbi', 'agenda_powerbi']);

if ($is_powerbi) {
    $is_agenda = ($tipo === 'agenda_powerbi');

    if ($is_agenda) {
        $result = $mysqli->query("
            SELECT a.data, a.hora_inicio, a.hora_fim, t.nome AS turma, t.turno,
                   COALESCE(t.cidade,'Sede') AS cidade, c.nome AS curso,
                   p.nome AS professor, p.especialidade, p.carga_horaria_contratual,
                   s.nome AS ambiente_sala, s.tipo AS tipo_sala
            FROM agenda a
            JOIN turmas t ON a.turma_id = t.id JOIN cursos c ON t.curso_id = c.id
            JOIN professores p ON a.professor_id = p.id JOIN salas s ON a.sala_id = s.id
            ORDER BY a.data, a.hora_inicio
        ");
        $filename = 'SENAI_Agenda_PowerBI_' . date('Y-m-d') . '.csv';
    } else {
        $result = $mysqli->query("
            SELECT t.id, t.nome AS turma, c.nome AS curso, c.carga_horaria,
                   t.turno, COALESCE(t.cidade,'Sede') AS cidade,
                   t.data_inicio, t.data_fim
            FROM turmas t JOIN cursos c ON t.curso_id = c.id ORDER BY t.id
        ");
        $filename = 'SENAI_Turmas_PowerBI_' . date('Y-m-d') . '.csv';
    }

    $data = $result->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    if (!empty($data)) {
        fputcsv($out, array_keys($data[0]), ';');
        foreach ($data as $row) fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

// ─────────────────────────────────────────────
// Excel SpreadsheetML – Multi-sheet workbook
// ─────────────────────────────────────────────

// ── Queries ──
$docentes = $mysqli->query("
    SELECT id, nome, especialidade AS area,
           carga_horaria_contratual AS ch_max,
           COALESCE(cidade, '') AS cidade
    FROM professores ORDER BY nome
")->fetch_all(MYSQLI_ASSOC);

$ambientes = $mysqli->query("
    SELECT id, nome, tipo, COALESCE(area, '') AS area,
           COALESCE(cidade, '') AS cidade, capacidade
    FROM salas ORDER BY nome
")->fetch_all(MYSQLI_ASSOC);

$cursos_data = $mysqli->query("
    SELECT id, nome, COALESCE(tipo, '') AS tipo,
           COALESCE(area, '') AS area, carga_horaria
    FROM cursos ORDER BY nome
")->fetch_all(MYSQLI_ASSOC);

$turmas_data = $mysqli->query("
    SELECT t.id,
           c.nome AS curso,
           COALESCE(c.tipo, '') AS tipo_curso,
           t.nome AS sigla,
           c.carga_horaria,
           t.vagas,
           COALESCE(c.tipo, '') AS tipo2,
           t.data_inicio,
           t.data_fim,
           COALESCE(t.horario, '') AS horario,
           COALESCE(t.dias_semana, '') AS dias_semana,
           COALESCE(t.docente1, '') AS docente1,
           COALESCE(t.docente2, '') AS docente2,
           COALESCE(t.docente3, '') AS docente3,
           COALESCE(t.docente4, '') AS docente4,
           COALESCE(t.ambiente, '') AS ambiente,
           COALESCE(t.local_turma, t.cidade, '') AS local_turma
    FROM turmas t
    JOIN cursos c ON t.curso_id = c.id
    ORDER BY t.id
")->fetch_all(MYSQLI_ASSOC);

$agenda_data = $mysqli->query("
    SELECT t.nome AS turma,
           COALESCE(p1.nome, '') AS docente1,
           COALESCE(p2.nome, '') AS docente2,
           COALESCE(p3.nome, '') AS docente3,
           COALESCE(p4.nome, '') AS docente4,
           s.nome AS ambiente,
           a.data,
           a.hora_inicio,
           a.hora_fim
    FROM agenda a
    JOIN turmas t ON a.turma_id = t.id
    JOIN professores p1 ON a.professor_id = p1.id
    LEFT JOIN professores p2 ON a.professor_id_2 = p2.id
    LEFT JOIN professores p3 ON a.professor_id_3 = p3.id
    LEFT JOIN professores p4 ON a.professor_id_4 = p4.id
    JOIN salas s ON a.sala_id = s.id
    ORDER BY a.data, a.hora_inicio
")->fetch_all(MYSQLI_ASSOC);

// DADOS: distinct areas
$dados_areas = array_column($mysqli->query("
    SELECT DISTINCT val FROM (
        SELECT especialidade AS val FROM professores WHERE especialidade IS NOT NULL AND especialidade != ''
        UNION
        SELECT area AS val FROM cursos WHERE area IS NOT NULL AND area != ''
        UNION
        SELECT area AS val FROM salas WHERE area IS NOT NULL AND area != ''
    ) AS areas ORDER BY val
")->fetch_all(MYSQLI_NUM), 0);

// ── Day-of-week helper ──
$dow_pt = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];

// ── Output ──
$filename = 'Controle_de_Ocupacao_' . date('Y-m-d') . '.xls';
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
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0056A4"/>
        <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0056A4"/>
      </Borders>
      <Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11" ss:FontName="Segoe UI"/>
      <Interior ss:Color="#0056A4" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="sDefault">
      <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="0"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:FontName="Segoe UI" ss:Size="10"/>
    </Style>
    <Style ss:ID="sEven">
      <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="0"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:FontName="Segoe UI" ss:Size="10"/>
      <Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="sDate">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:FontName="Segoe UI" ss:Size="10"/>
      <NumberFormat ss:Format="dd/mm/yyyy"/>
    </Style>
    <Style ss:ID="sDateEven">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:FontName="Segoe UI" ss:Size="10"/>
      <Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/>
      <NumberFormat ss:Format="dd/mm/yyyy"/>
    </Style>
    <Style ss:ID="sNum">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:FontName="Segoe UI" ss:Size="10"/>
    </Style>
    <Style ss:ID="sNumEven">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:FontName="Segoe UI" ss:Size="10"/>
      <Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="sTime">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:Color="#D32F2F" ss:Bold="1" ss:FontName="Segoe UI" ss:Size="10"/>
    </Style>
    <Style ss:ID="sTimeEven">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
        <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      </Borders>
      <Font ss:Color="#D32F2F" ss:Bold="1" ss:FontName="Segoe UI" ss:Size="10"/>
      <Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/>
    </Style>
  </Styles>

  <!-- ═══════════════════════════════════════ DOCENTES ═══════════════════════════════════════ -->
  <Worksheet ss:Name="DOCENTES">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="50"/>
      <Column ss:AutoFitWidth="1" ss:Width="280"/>
      <Column ss:AutoFitWidth="1" ss:Width="220"/>
      <Column ss:AutoFitWidth="1" ss:Width="130"/>
      <Column ss:AutoFitWidth="1" ss:Width="150"/>
      <Row ss:Height="25" ss:StyleID="sHeader">
        <Cell><Data ss:Type="String">ID</Data></Cell>
        <Cell><Data ss:Type="String">Nome</Data></Cell>
        <Cell><Data ss:Type="String">Área</Data></Cell>
        <Cell><Data ss:Type="String">Carga Horária Máx</Data></Cell>
        <Cell><Data ss:Type="String">Cidade</Data></Cell>
      </Row>
<?php foreach ($docentes as $i => $r):
    $s = ($i % 2 === 0) ? 'sEven' : 'sDefault';
    $sn = ($i % 2 === 0) ? 'sNumEven' : 'sNum';
?>
      <Row ss:StyleID="<?php echo $s; ?>">
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['id']; ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['nome']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['area']); ?></Data></Cell>
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['ch_max']; ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['cidade']); ?></Data></Cell>
      </Row>
<?php endforeach; ?>
    </Table>
  </Worksheet>

  <!-- ═══════════════════════════════════════ AMBIENTES ═══════════════════════════════════════ -->
  <Worksheet ss:Name="AMBIENTES">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="50"/>
      <Column ss:AutoFitWidth="1" ss:Width="220"/>
      <Column ss:AutoFitWidth="1" ss:Width="120"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="150"/>
      <Column ss:AutoFitWidth="1" ss:Width="100"/>
      <Row ss:Height="25" ss:StyleID="sHeader">
        <Cell><Data ss:Type="String">ID</Data></Cell>
        <Cell><Data ss:Type="String">Nome</Data></Cell>
        <Cell><Data ss:Type="String">Tipo</Data></Cell>
        <Cell><Data ss:Type="String">Área</Data></Cell>
        <Cell><Data ss:Type="String">Cidade</Data></Cell>
        <Cell><Data ss:Type="String">Capacidade</Data></Cell>
      </Row>
<?php foreach ($ambientes as $i => $r):
    $s = ($i % 2 === 0) ? 'sEven' : 'sDefault';
    $sn = ($i % 2 === 0) ? 'sNumEven' : 'sNum';
?>
      <Row ss:StyleID="<?php echo $s; ?>">
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['id']; ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['nome']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['tipo']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['area']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['cidade']); ?></Data></Cell>
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['capacidade']; ?></Data></Cell>
      </Row>
<?php endforeach; ?>
    </Table>
  </Worksheet>

  <!-- ═══════════════════════════════════════ CURSOS ═══════════════════════════════════════ -->
  <Worksheet ss:Name="CURSOS">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="50"/>
      <Column ss:AutoFitWidth="1" ss:Width="320"/>
      <Column ss:AutoFitWidth="1" ss:Width="80"/>
      <Column ss:AutoFitWidth="1" ss:Width="220"/>
      <Column ss:AutoFitWidth="1" ss:Width="120"/>
      <Row ss:Height="25" ss:StyleID="sHeader">
        <Cell><Data ss:Type="String">ID</Data></Cell>
        <Cell><Data ss:Type="String">Nome</Data></Cell>
        <Cell><Data ss:Type="String">Tipo</Data></Cell>
        <Cell><Data ss:Type="String">Área</Data></Cell>
        <Cell><Data ss:Type="String">Carga Horária</Data></Cell>
      </Row>
<?php foreach ($cursos_data as $i => $r):
    $s = ($i % 2 === 0) ? 'sEven' : 'sDefault';
    $sn = ($i % 2 === 0) ? 'sNumEven' : 'sNum';
?>
      <Row ss:StyleID="<?php echo $s; ?>">
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['id']; ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['nome']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['tipo']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['area']); ?></Data></Cell>
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['carga_horaria']; ?></Data></Cell>
      </Row>
<?php endforeach; ?>
    </Table>
  </Worksheet>

  <!-- ═══════════════════════════════════════ TURMAS ═══════════════════════════════════════ -->
  <Worksheet ss:Name="TURMAS">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="60"/>
      <Column ss:AutoFitWidth="1" ss:Width="280"/>
      <Column ss:AutoFitWidth="1" ss:Width="60"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="100"/>
      <Column ss:AutoFitWidth="1" ss:Width="60"/>
      <Column ss:AutoFitWidth="1" ss:Width="60"/>
      <Column ss:AutoFitWidth="1" ss:Width="100"/>
      <Column ss:AutoFitWidth="1" ss:Width="100"/>
      <Column ss:AutoFitWidth="1" ss:Width="120"/>
      <Column ss:AutoFitWidth="1" ss:Width="120"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="150"/>
      <Column ss:AutoFitWidth="1" ss:Width="150"/>
      <Column ss:AutoFitWidth="1" ss:Width="150"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="100"/>
      <Row ss:Height="25" ss:StyleID="sHeader">
        <Cell><Data ss:Type="String">ID Turma</Data></Cell>
        <Cell><Data ss:Type="String">Curso</Data></Cell>
        <Cell><Data ss:Type="String">Tipo</Data></Cell>
        <Cell><Data ss:Type="String">SIGLA DA TURMA</Data></Cell>
        <Cell><Data ss:Type="String">Carga Horária</Data></Cell>
        <Cell><Data ss:Type="String">Vagas</Data></Cell>
        <Cell><Data ss:Type="String">Tipo</Data></Cell>
        <Cell><Data ss:Type="String">Data Início</Data></Cell>
        <Cell><Data ss:Type="String">Data Fim</Data></Cell>
        <Cell><Data ss:Type="String">Horário</Data></Cell>
        <Cell><Data ss:Type="String">Dias Semana</Data></Cell>
        <Cell><Data ss:Type="String">Docente 1</Data></Cell>
        <Cell><Data ss:Type="String">Docente 2</Data></Cell>
        <Cell><Data ss:Type="String">Docente 3</Data></Cell>
        <Cell><Data ss:Type="String">Docente 4</Data></Cell>
        <Cell><Data ss:Type="String">Ambiente</Data></Cell>
        <Cell><Data ss:Type="String">Local</Data></Cell>
      </Row>
<?php foreach ($turmas_data as $i => $r):
    $s  = ($i % 2 === 0) ? 'sEven' : 'sDefault';
    $sn = ($i % 2 === 0) ? 'sNumEven' : 'sNum';
    $sd = ($i % 2 === 0) ? 'sDateEven' : 'sDate';
    $di = $r['data_inicio'] ? date('Y-m-d\T00:00:00.000', strtotime($r['data_inicio'])) : '';
    $df = $r['data_fim']    ? date('Y-m-d\T00:00:00.000', strtotime($r['data_fim']))    : '';
?>
      <Row ss:StyleID="<?php echo $s; ?>">
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['id']; ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['curso']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['tipo_curso']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['sigla']); ?></Data></Cell>
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['carga_horaria']; ?></Data></Cell>
        <Cell ss:StyleID="<?php echo $sn; ?>"><Data ss:Type="Number"><?php echo (int)$r['vagas']; ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['tipo2']); ?></Data></Cell>
<?php if ($di): ?>
        <Cell ss:StyleID="<?php echo $sd; ?>"><Data ss:Type="DateTime"><?php echo $di; ?></Data></Cell>
<?php else: ?>
        <Cell ss:StyleID="<?php echo $sd; ?>"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
<?php if ($df): ?>
        <Cell ss:StyleID="<?php echo $sd; ?>"><Data ss:Type="DateTime"><?php echo $df; ?></Data></Cell>
<?php else: ?>
        <Cell ss:StyleID="<?php echo $sd; ?>"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
        <Cell><Data ss:Type="String"><?php echo xe($r['horario']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['dias_semana']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente1']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente2']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente3']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente4']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['ambiente']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['local_turma']); ?></Data></Cell>
      </Row>
<?php endforeach; ?>
    </Table>
  </Worksheet>

  <!-- ═══════════════════════════════════════ AGENDA ═══════════════════════════════════════ -->
  <Worksheet ss:Name="AGENDA">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="220"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="180"/>
      <Column ss:AutoFitWidth="1" ss:Width="100"/>
      <Column ss:AutoFitWidth="1" ss:Width="120"/>
      <Column ss:AutoFitWidth="1" ss:Width="90"/>
      <Column ss:AutoFitWidth="1" ss:Width="90"/>
      <Row ss:Height="25" ss:StyleID="sHeader">
        <Cell><Data ss:Type="String">Turma</Data></Cell>
        <Cell><Data ss:Type="String">Docente 1</Data></Cell>
        <Cell><Data ss:Type="String">Docente 2</Data></Cell>
        <Cell><Data ss:Type="String">Docente 3</Data></Cell>
        <Cell><Data ss:Type="String">Docente 4</Data></Cell>
        <Cell><Data ss:Type="String">Ambiente</Data></Cell>
        <Cell><Data ss:Type="String">Data</Data></Cell>
        <Cell><Data ss:Type="String">Dia Semana</Data></Cell>
        <Cell><Data ss:Type="String">Hora Início</Data></Cell>
        <Cell><Data ss:Type="String">Hora Fim</Data></Cell>
      </Row>
<?php foreach ($agenda_data as $i => $r):
    $s  = ($i % 2 === 0) ? 'sEven' : 'sDefault';
    $sd = ($i % 2 === 0) ? 'sDateEven' : 'sDate';
    $st = ($i % 2 === 0) ? 'sTimeEven' : 'sTime';
    $dv = $r['data'] ? date('Y-m-d\T00:00:00.000', strtotime($r['data'])) : '';
    $dia_semana = $r['data'] ? $dow_pt[(int)date('w', strtotime($r['data']))] : '';
?>
      <Row ss:StyleID="<?php echo $s; ?>">
        <Cell><Data ss:Type="String"><?php echo xe($r['turma']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente1']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente2']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente3']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['docente4']); ?></Data></Cell>
        <Cell><Data ss:Type="String"><?php echo xe($r['ambiente']); ?></Data></Cell>
<?php if ($dv): ?>
        <Cell ss:StyleID="<?php echo $sd; ?>"><Data ss:Type="DateTime"><?php echo $dv; ?></Data></Cell>
<?php else: ?>
        <Cell><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
        <Cell><Data ss:Type="String"><?php echo xe($dia_semana); ?></Data></Cell>
        <Cell ss:StyleID="<?php echo $st; ?>"><Data ss:Type="String"><?php echo xe(substr($r['hora_inicio'], 0, 5)); ?></Data></Cell>
        <Cell ss:StyleID="<?php echo $st; ?>"><Data ss:Type="String"><?php echo xe(substr($r['hora_fim'], 0, 5)); ?></Data></Cell>
      </Row>
<?php endforeach; ?>
    </Table>
  </Worksheet>

  <!-- ═══════════════════════════════════════ GRADE_VISUAL ═══════════════════════════════════════ -->
  <Worksheet ss:Name="GRADE_VISUAL">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="200"/>
      <Row ss:Height="25" ss:StyleID="sHeader">
        <Cell><Data ss:Type="String">GRADE_VISUAL</Data></Cell>
      </Row>
    </Table>
  </Worksheet>

  <!-- ═══════════════════════════════════════ SIMULADOR ═══════════════════════════════════════ -->
  <Worksheet ss:Name="SIMULADOR">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="200"/>
    </Table>
  </Worksheet>

  <!-- ═══════════════════════════════════════ DADOS ═══════════════════════════════════════ -->
  <Worksheet ss:Name="DADOS">
    <Table ss:DefaultRowHeight="20">
      <Column ss:AutoFitWidth="1" ss:Width="280"/>
<?php foreach ($dados_areas as $i => $area):
    $s = ($i % 2 === 0) ? 'sEven' : 'sDefault';
?>
      <Row ss:StyleID="<?php echo $s; ?>">
        <Cell><Data ss:Type="String"><?php echo xe($area); ?></Data></Cell>
      </Row>
<?php endforeach; ?>
    </Table>
  </Worksheet>

</Workbook>
<?php exit;
