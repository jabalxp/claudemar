<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Only gestors/admins can manage reservations
if (!can_edit()) {
    header('Location: index.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reserva_id = (int)($_POST['reserva_id'] ?? 0);

    if ($action === 'delete' && $reserva_id) {
        // Check ownership — only the creator (or admin) can delete
        $st = $mysqli->prepare("SELECT usuario_id FROM reservas WHERE id = ? AND status = 'ativo'");
        $st->bind_param('i', $reserva_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row && ($row['usuario_id'] == $auth_user_id || $auth_user_role === 'admin')) {
            $del = $mysqli->prepare("DELETE FROM reservas WHERE id = ?");
            $del->bind_param('i', $reserva_id);
            $del->execute();
            $msg_success = 'Reserva removida com sucesso.';
        } else {
            $msg_error = 'Somente quem criou a reserva pode removê-la.';
        }
    }

    if ($action === 'complete' && $reserva_id) {
        $st = $mysqli->prepare("SELECT * FROM reservas WHERE id = ? AND status = 'ativo'");
        $st->bind_param('i', $reserva_id);
        $st->execute();
        $reserva = $st->get_result()->fetch_assoc();
        if ($reserva && ($reserva['usuario_id'] == $auth_user_id || $auth_user_role === 'admin')) {
            $upd = $mysqli->prepare("UPDATE reservas SET status = 'concluido' WHERE id = ?");
            $upd->bind_param('i', $reserva_id);
            $upd->execute();
            $msg_success = 'Reserva marcada como concluída.';
        } else {
            $msg_error = 'Somente quem criou a reserva pode concluí-la.';
        }
    }
}

// Fetch reservations
$status_filter = $_GET['status'] ?? 'ativo';
$owner_filter = $_GET['owner'] ?? 'mine'; // 'mine' or 'others'

$where = "WHERE r.status = ?";
$params = [$status_filter];
$types = 's';

// Filter by ownership: mine vs others
if ($owner_filter === 'others') {
    $where .= " AND r.usuario_id != ?";
    $params[] = $auth_user_id;
    $types .= 'i';
} else {
    // Admin sees all in 'mine' only if they want; gestors see only their own
    if ($auth_user_role !== 'admin' || $owner_filter === 'mine') {
        $where .= " AND r.usuario_id = ?";
        $params[] = $auth_user_id;
        $types .= 'i';
    }
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

// Count for badges
$st_mine = $mysqli->prepare("SELECT COUNT(*) FROM reservas WHERE status = ? AND usuario_id = ?");
$st_mine->bind_param('si', $status_filter, $auth_user_id);
$st_mine->execute();
$count_mine = $st_mine->get_result()->fetch_row()[0];

$st_others = $mysqli->prepare("SELECT COUNT(*) FROM reservas WHERE status = ? AND usuario_id != ?");
$st_others->bind_param('si', $status_filter, $auth_user_id);
$st_others->execute();
$count_others = $st_others->get_result()->fetch_row()[0];

$dow_names = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

include 'includes/header.php';
?>

<style>
    .reserva-card {
        background: var(--card-bg); border-radius: 16px; padding: 20px 25px;
        border: 1px solid var(--border-color); box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        margin-bottom: 16px; position: relative; transition: all 0.2s;
    }
    .reserva-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .reserva-card-active { border-left: 4px solid #1565c0; }
    .reserva-card-concluido { border-left: 4px solid #4caf50; opacity: 0.8; }
    .reserva-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .reserva-prof-name { font-weight: 800; font-size: 1.1rem; color: var(--text-color); }
    .reserva-prof-esp { font-size: 0.82rem; color: var(--text-muted); }
    .reserva-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; font-size: 0.85rem; }
    .reserva-meta-item { display: flex; align-items: center; gap: 6px; }
    .reserva-meta-item i { color: #1565c0; width: 16px; text-align: center; }
    .reserva-dias { display: flex; gap: 6px; flex-wrap: wrap; }
    .reserva-dia-pill {
        display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 8px;
        font-size: 0.78rem; font-weight: 700; background: rgba(21,101,192,0.08);
        color: #1565c0; border: 1px solid rgba(21,101,192,0.15);
    }
    .reserva-actions { display: flex; gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-color); }
    .reserva-btn {
        padding: 8px 18px; border-radius: 8px; border: 1px solid var(--border-color);
        background: var(--bg-color); color: var(--text-color); font-weight: 600;
        font-size: 0.82rem; cursor: pointer; transition: all 0.2s; display: inline-flex;
        align-items: center; gap: 6px;
    }
    .reserva-btn:hover { background: var(--card-bg); }
    .reserva-btn-danger { border-color: #e53935; color: #c62828; }
    .reserva-btn-danger:hover { background: #e53935; color: #fff; }
    .reserva-btn-success { border-color: #2e7d32; color: #1b5e20; }
    .reserva-btn-success:hover { background: #2e7d32; color: #fff; }
    .reserva-btn-primary { border-color: #1565c0; color: #0d47a1; }
    .reserva-btn-primary:hover { background: #1565c0; color: #fff; }

    .status-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
    .status-tab {
        padding: 10px 20px; border-radius: 10px; border: 1px solid var(--border-color);
        background: var(--bg-color); color: var(--text-muted); font-weight: 700;
        font-size: 0.88rem; cursor: pointer; transition: all 0.2s; text-decoration: none;
        display: inline-flex; align-items: center; gap: 8px;
    }
    .status-tab:hover { background: var(--card-bg); }
    .status-tab.active { background: #1565c0; color: #fff; border-color: #0d47a1; }

    .reserva-notas { font-size: 0.82rem; color: var(--text-muted); font-style: italic; margin-top: 8px; padding: 8px 12px; background: var(--bg-color); border-radius: 8px; border-left: 3px solid #1565c0; }
    .reserva-gestor-badge { font-size: 0.72rem; padding: 3px 10px; border-radius: 20px; background: rgba(21,101,192,0.08); color: #1565c0; font-weight: 700; }
    .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
    .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-bookmark" style="color: #1565c0;"></i> Gerenciar Reservas</h2>
        <p style="margin-top: 5px; font-size: 0.88rem; color: var(--text-muted);">
            Visualize, cancele ou conclua reservas de professores
        </p>
    </div>
    <a href="agenda_professores.php" class="reserva-btn reserva-btn-primary" style="text-decoration: none;">
        <i class="fas fa-calendar-check"></i> Agenda Professores
    </a>
</div>

<?php if (!empty($msg_success)): ?>
<div style="padding: 14px 20px; background: rgba(46,125,50,0.08); border-radius: 10px; border-left: 4px solid #2e7d32; margin-bottom: 20px; color: #1b5e20; font-weight: 600;">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg_success); ?>
</div>
<?php endif; ?>

<?php if (!empty($msg_error)): ?>
<div style="padding: 14px 20px; background: rgba(229,57,53,0.08); border-radius: 10px; border-left: 4px solid #e53935; margin-bottom: 20px; color: #c62828; font-weight: 600;">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($msg_error); ?>
</div>
<?php endif; ?>

<div class="status-tabs">
    <a href="?status=ativo&owner=<?php echo $owner_filter; ?>" class="status-tab <?php echo $status_filter === 'ativo' ? 'active' : ''; ?>">
        <i class="fas fa-bookmark"></i> Ativas
    </a>
    <a href="?status=concluido&owner=<?php echo $owner_filter; ?>" class="status-tab <?php echo $status_filter === 'concluido' ? 'active' : ''; ?>">
        <i class="fas fa-check-circle"></i> Concluídas
    </a>
</div>

<div class="status-tabs" style="margin-top: -10px;">
    <a href="?status=<?php echo $status_filter; ?>&owner=mine" class="status-tab <?php echo $owner_filter === 'mine' ? 'active' : ''; ?>" style="<?php echo $owner_filter === 'mine' ? 'background:#2e7d32;border-color:#1b5e20;' : ''; ?>">
        <i class="fas fa-user"></i> Minhas Reservas
        <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.75rem;"><?php echo $count_mine; ?></span>
    </a>
    <a href="?status=<?php echo $status_filter; ?>&owner=others" class="status-tab <?php echo $owner_filter === 'others' ? 'active' : ''; ?>" style="<?php echo $owner_filter === 'others' ? 'background:#e65100;border-color:#bf360c;' : ''; ?>">
        <i class="fas fa-users"></i> Reservados (Outros)
        <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.75rem;"><?php echo $count_others; ?></span>
    </a>
</div>

<?php if (empty($reservas)): ?>
<div class="empty-state">
    <div><i class="fas fa-bookmark"></i></div>
    <h3>Nenhuma reserva <?php echo $status_filter === 'ativo' ? 'ativa' : 'concluída'; ?></h3>
    <p>Para criar uma reserva, vá à <a href="agenda_professores.php" style="color:#1565c0;">Agenda de Professores</a> e ative o <strong>Modo Reserva</strong>.</p>
</div>
<?php else: ?>
    <?php foreach ($reservas as $r): 
        $dias_arr = explode(',', $r['dias_semana']);
        $dias_labels = array_map(function($d) use ($dow_names) { return $dow_names[(int)$d] ?? $d; }, $dias_arr);

        // Count how many actual dates this reservation covers
        $count_dates = 0;
        $cur = new DateTime($r['data_inicio']);
        $end = new DateTime($r['data_fim']);
        $end->modify('+1 day');
        while ($cur < $end) {
            $dow = $cur->format('N');
            if (in_array($dow, $dias_arr)) $count_dates++;
            $cur->modify('+1 day');
        }
    ?>
    <div class="reserva-card <?php echo $r['status'] === 'ativo' ? 'reserva-card-active' : 'reserva-card-concluido'; ?>">
        <div class="reserva-card-header">
            <div>
                <div class="reserva-prof-name">
                    <?php echo htmlspecialchars($r['professor_nome']); ?>
                </div>
                <div class="reserva-prof-esp"><?php echo htmlspecialchars($r['especialidade']); ?></div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php $is_own = ($r['usuario_id'] == $auth_user_id); ?>
                <span class="reserva-gestor-badge" style="<?php echo $is_own ? 'background:rgba(46,125,50,0.1);color:#1b5e20;' : 'background:rgba(230,81,0,0.1);color:#e65100;'; ?>">
                    <i class="fas <?php echo $is_own ? 'fa-user' : 'fa-user-lock'; ?>"></i>
                    <?php echo $is_own ? 'Minha Reserva' : htmlspecialchars($r['gestor_nome']); ?>
                </span>
                <span style="font-size: 0.75rem; color: var(--text-muted);">
                    Criada em <?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?>
                </span>
            </div>
        </div>

        <div class="reserva-meta">
            <div class="reserva-meta-item">
                <i class="fas fa-calendar-alt"></i>
                <strong><?php echo date('d/m/Y', strtotime($r['data_inicio'])); ?></strong> a <strong><?php echo date('d/m/Y', strtotime($r['data_fim'])); ?></strong>
            </div>
            <div class="reserva-meta-item">
                <i class="fas fa-clock"></i>
                <strong><?php echo substr($r['hora_inicio'], 0, 5); ?></strong> – <strong><?php echo substr($r['hora_fim'], 0, 5); ?></strong>
            </div>
            <div class="reserva-meta-item">
                <i class="fas fa-hashtag"></i>
                <strong><?php echo $count_dates; ?></strong> dia(s)
            </div>
        </div>

        <div style="margin-bottom: 8px;">
            <span style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-right: 8px;">Dias:</span>
            <div class="reserva-dias" style="display: inline-flex;">
                <?php foreach ($dias_labels as $dl): ?>
                <span class="reserva-dia-pill"><?php echo $dl; ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($r['notas'])): ?>
        <div class="reserva-notas">
            <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($r['notas']); ?>
        </div>
        <?php endif; ?>

        <?php if ($r['status'] === 'ativo' && $is_own): ?>
        <div class="reserva-actions">
            <a href="agenda_professores.php?search=<?php echo urlencode($r['professor_nome']); ?>&month=<?php echo substr($r['data_inicio'], 0, 7); ?>" class="reserva-btn reserva-btn-primary" style="text-decoration: none;">
                <i class="fas fa-calendar-plus"></i> Concluir Cadastro
            </a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja completar esta reserva?');">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="reserva_id" value="<?php echo $r['id']; ?>">
                <button type="submit" class="reserva-btn reserva-btn-success">
                    <i class="fas fa-check"></i> Marcar Concluída
                </button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja REMOVER esta reserva? O professor ficará disponível novamente.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="reserva_id" value="<?php echo $r['id']; ?>">
                <button type="submit" class="reserva-btn reserva-btn-danger">
                    <i class="fas fa-trash-alt"></i> Desreservar
                </button>
            </form>
        </div>
        <?php elseif ($r['status'] === 'ativo' && !$is_own): ?>
        <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--text-muted);">
            <i class="fas fa-lock" style="color: #e65100;"></i> Somente <strong><?php echo htmlspecialchars($r['gestor_nome']); ?></strong> pode gerenciar esta reserva.
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
