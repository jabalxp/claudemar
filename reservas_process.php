<?php
/**
 * reservas_process.php — AJAX backend for the reservation system
 * Handles: create, delete, complete (convert to agenda), list, check conflicts
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── CREATE RESERVATION ──
if ($action === 'create') {
    if (!can_edit()) {
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }

    $professor_id = (int)$_POST['professor_id'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $dias_semana = $_POST['dias_semana']; // e.g. "1,3,5"
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];
    $notas = $_POST['notas'] ?? '';
    $usuario_id = $auth_user_id;

    if (!$professor_id || !$data_inicio || !$data_fim || !$dias_semana || !$hora_inicio || !$hora_fim) {
        echo json_encode(['ok' => false, 'error' => 'Dados incompletos.']);
        exit;
    }

    // Check if another gestor already reserved this professor for conflicting dates/days/times
    $dias_arr = explode(',', $dias_semana);
    $conflict = checkReservationConflict($mysqli, $professor_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim, $usuario_id);
    if ($conflict) {
        echo json_encode(['ok' => false, 'error' => $conflict]);
        exit;
    }

    // Also check against existing agenda entries
    $agendaConflict = checkAgendaConflictForReservation($mysqli, $professor_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim);
    if ($agendaConflict) {
        echo json_encode(['ok' => false, 'error' => $agendaConflict]);
        exit;
    }

    $st = $mysqli->prepare("INSERT INTO reservas (professor_id, usuario_id, data_inicio, data_fim, dias_semana, hora_inicio, hora_fim, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $st->bind_param('iissssss', $professor_id, $usuario_id, $data_inicio, $data_fim, $dias_semana, $hora_inicio, $hora_fim, $notas);
    $st->execute();

    echo json_encode(['ok' => true, 'id' => $mysqli->insert_id, 'msg' => 'Professor reservado com sucesso!']);
    exit;
}

// ── DELETE (UNRESERVE) ──
if ($action === 'delete') {
    if (!can_edit()) {
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }

    $reserva_id = (int)$_POST['reserva_id'];

    // Only the gestor who created it (or admin) can delete
    $st = $mysqli->prepare("SELECT usuario_id FROM reservas WHERE id = ? AND status = 'ativo'");
    $st->bind_param('i', $reserva_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Reserva não encontrada ou já concluída.']);
        exit;
    }

    if ($row['usuario_id'] !== $auth_user_id && $auth_user_role !== 'admin') {
        echo json_encode(['ok' => false, 'error' => 'Apenas quem criou a reserva (ou admin) pode excluí-la.']);
        exit;
    }

    $st2 = $mysqli->prepare("DELETE FROM reservas WHERE id = ?");
    $st2->bind_param('i', $reserva_id);
    $st2->execute();

    echo json_encode(['ok' => true, 'msg' => 'Reserva removida com sucesso.']);
    exit;
}

// ── COMPLETE RESERVATION (mark as concluido) ──
if ($action === 'complete') {
    if (!can_edit()) {
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }

    $reserva_id = (int)$_POST['reserva_id'];

    $st = $mysqli->prepare("SELECT * FROM reservas WHERE id = ? AND status = 'ativo'");
    $st->bind_param('i', $reserva_id);
    $st->execute();
    $reserva = $st->get_result()->fetch_assoc();

    if (!$reserva) {
        echo json_encode(['ok' => false, 'error' => 'Reserva não encontrada.']);
        exit;
    }

    if ($reserva['usuario_id'] !== $auth_user_id && $auth_user_role !== 'admin') {
        echo json_encode(['ok' => false, 'error' => 'Apenas quem criou a reserva (ou admin) pode concluí-la.']);
        exit;
    }

    $st2 = $mysqli->prepare("UPDATE reservas SET status = 'concluido' WHERE id = ?");
    $st2->bind_param('i', $reserva_id);
    $st2->execute();

    echo json_encode(['ok' => true, 'msg' => 'Reserva marcada como concluída.']);
    exit;
}

// ── LIST RESERVAS (for the management page or AJAX) ──
if ($action === 'list') {
    $status_filter = $_GET['status'] ?? 'ativo';
    $prof_filter = (int)($_GET['professor_id'] ?? 0);

    $where = "WHERE r.status = ?";
    $params = [$status_filter];
    $types = 's';

    if ($prof_filter) {
        $where .= " AND r.professor_id = ?";
        $params[] = $prof_filter;
        $types .= 'i';
    }

    // If gestor (not admin), only show their own reservations
    if ($auth_user_role === 'gestor') {
        $where .= " AND r.usuario_id = ?";
        $params[] = $auth_user_id;
        $types .= 'i';
    }

    $st = $mysqli->prepare("
        SELECT r.*, p.nome as professor_nome, p.especialidade, p.cor_agenda,
               u.nome as gestor_nome
        FROM reservas r
        JOIN professores p ON r.professor_id = p.id
        JOIN usuarios u ON r.usuario_id = u.id
        $where
        ORDER BY r.created_at DESC
    ");
    $st->bind_param($types, ...$params);
    $st->execute();
    $reservas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['ok' => true, 'reservas' => $reservas]);
    exit;
}

// ── CHECK RESERVATIONS FOR A PROFESSOR (used by agenda views) ──
if ($action === 'check_professor') {
    $prof_id = (int)$_GET['professor_id'];
    $month = $_GET['month'] ?? date('Y-m');
    $f_day = $month . '-01';
    $l_day = date('Y-m-t', strtotime($f_day));

    $st = $mysqli->prepare("
        SELECT r.*, u.nome as gestor_nome
        FROM reservas r
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.professor_id = ? AND r.status = 'ativo'
        AND r.data_inicio <= ? AND r.data_fim >= ?
    ");
    $st->bind_param('iss', $prof_id, $l_day, $f_day);
    $st->execute();
    $reservas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build a map of date => reservation info
    $reserved_dates = [];
    foreach ($reservas as $r) {
        $dias_arr = explode(',', $r['dias_semana']);
        $cur = new DateTime(max($r['data_inicio'], $f_day));
        $end = new DateTime(min($r['data_fim'], $l_day));
        $end->modify('+1 day');
        while ($cur < $end) {
            $dow = $cur->format('N'); // 1=Mon...7=Sun
            if (in_array($dow, $dias_arr)) {
                $d = $cur->format('Y-m-d');
                $reserved_dates[$d] = [
                    'reserva_id' => $r['id'],
                    'gestor' => $r['gestor_nome'],
                    'hora_inicio' => $r['hora_inicio'],
                    'hora_fim' => $r['hora_fim'],
                    'own' => ($r['usuario_id'] == $auth_user_id)
                ];
            }
            $cur->modify('+1 day');
        }
    }

    echo json_encode(['ok' => true, 'reserved' => $reserved_dates]);
    exit;
}

// ── HELPER FUNCTIONS ──

/**
 * Check if another gestor already reserved this professor for overlapping dates/days/times
 */
function checkReservationConflict($mysqli, $professor_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim, $usuario_id) {
    $st = $mysqli->prepare("
        SELECT r.*, u.nome as gestor_nome
        FROM reservas r
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.professor_id = ? AND r.status = 'ativo'
        AND r.usuario_id != ?
        AND r.data_inicio <= ? AND r.data_fim >= ?
        AND (r.hora_inicio < ? AND r.hora_fim > ?)
    ");
    $st->bind_param('iissss', $professor_id, $usuario_id, $data_fim, $data_inicio, $hora_fim, $hora_inicio);
    $st->execute();
    $conflicts = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($conflicts as $c) {
        $c_dias = explode(',', $c['dias_semana']);
        $overlap_dias = array_intersect($dias_arr, $c_dias);
        if (!empty($overlap_dias)) {
            $dow_names = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
            $overlap_names = array_map(function ($d) use ($dow_names) {
                return $dow_names[$d] ?? $d;
            }, $overlap_dias);
            return "Conflito: Gestor \"{$c['gestor_nome']}\" já reservou este professor em " . implode(', ', $overlap_names) .
                " ({$c['data_inicio']} a {$c['data_fim']}, {$c['hora_inicio']}-{$c['hora_fim']}).";
        }
    }

    return null; // No conflict
}

/**
 * Check if the professor already has agenda entries that conflict with the reservation
 */
function checkAgendaConflictForReservation($mysqli, $professor_id, $data_inicio, $data_fim, $dias_arr, $hora_inicio, $hora_fim) {
    // Map our weekday numbers to MySQL DAYOFWEEK
    // Our: 1=Mon, 2=Tue ... 6=Sat → MySQL: 2=Mon, 3=Tue ... 7=Sat
    $mysql_dows = array_map(function ($d) { return $d + 1; }, $dias_arr);
    $dows_str = implode(',', $mysql_dows);

    $st = $mysqli->prepare("
        SELECT COUNT(*) as cnt
        FROM agenda a
        WHERE (a.professor_id = ? OR a.professor_id_2 = ? OR a.professor_id_3 = ? OR a.professor_id_4 = ?)
        AND a.data BETWEEN ? AND ?
        AND DAYOFWEEK(a.data) IN ($dows_str)
        AND (a.hora_inicio < ? AND a.hora_fim > ?)
    ");
    $st->bind_param('iiiissss', $professor_id, $professor_id, $professor_id, $professor_id, $data_inicio, $data_fim, $hora_fim, $hora_inicio);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if ($row['cnt'] > 0) {
        return "O professor já possui {$row['cnt']} aula(s) agendada(s) que conflitam com este horário no período selecionado.";
    }

    return null;
}
