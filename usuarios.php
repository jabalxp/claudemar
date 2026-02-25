<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Only admin and gestor can access user management
require_role(['admin', 'gestor']);

// ── AJAX: Delete user (admin only) ──
if (isset($_GET['delete_id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($auth_user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas admin pode remover usuários']);
        exit;
    }
    $del_id = (int)$_GET['delete_id'];
    // Prevent deleting yourself
    if ($del_id === $auth_user_id) {
        header('Location: usuarios.php?msg=erro_proprio');
        exit;
    }
    $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $del_id);
    $stmt->execute();
    header('Location: usuarios.php?msg=removido');
    exit;
}

// ── AJAX: Reset password (admin only) ──
if (isset($_GET['reset_id'])) {
    if ($auth_user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas admin pode redefinir senhas']);
        exit;
    }
    $reset_id = (int)$_GET['reset_id'];
    $hash = password_hash('senaisp', PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare("UPDATE usuarios SET senha = ?, obrigar_troca_senha = 1 WHERE id = ?");
    $stmt->bind_param('si', $hash, $reset_id);
    $stmt->execute();
    header('Location: usuarios.php?msg=senha_resetada');
    exit;
}

include 'includes/header.php';

// ── Messages ──
$msg = $_GET['msg'] ?? '';
$msgs_map = [
    'criado'        => ['success', 'Usuário criado com sucesso!'],
    'atualizado'    => ['success', 'Usuário atualizado com sucesso!'],
    'removido'      => ['success', 'Usuário removido com sucesso!'],
    'senha_resetada'=> ['success', 'Senha redefinida para "senaisp". O usuário será obrigado a trocar no próximo login.'],
    'erro_proprio'  => ['error', 'Você não pode remover seu próprio usuário.'],
    'sem_permissao' => ['error', 'Você não tem permissão para essa ação.'],
];

// ── Get all users ──
$usuarios = $mysqli->query("
    SELECT u.*, p.nome AS professor_nome
    FROM usuarios u
    LEFT JOIN professores p ON u.professor_id = p.id
    ORDER BY u.role, u.nome
")->fetch_all(MYSQLI_ASSOC);

// ── Get professors for dropdown ──
$professores = $mysqli->query("SELECT id, nome FROM professores ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

$role_labels = [
    'admin'     => ['Admin', '#dc3545'],
    'gestor'    => ['Gestor', '#0d6efd'],
    'professor' => ['Professor', '#198754'],
];
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2><i class="fas fa-users-cog"></i> Gerenciamento de Usuários</h2>
        <p>Crie e gerencie acessos ao sistema.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Home</a>
        <button onclick="openModal()" class="btn btn-primary" style="background: #28a745;"><i class="fas fa-plus"></i> Novo Usuário</button>
    </div>
</div>

<?php if ($msg && isset($msgs_map[$msg])): ?>
<div class="card" style="border-left: 5px solid <?php echo $msgs_map[$msg][0] === 'success' ? '#28a745' : '#dc3545'; ?>; margin-bottom: 20px; padding: 12px 18px;">
    <i class="fas <?php echo $msgs_map[$msg][0] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($msgs_map[$msg][1]); ?>
</div>
<?php endif; ?>

<div class="card">
    <div style="overflow-x: auto;">
        <table style="width: 100%; font-size: 0.88rem;">
            <thead>
                <tr>
                    <th style="padding: 10px;">ID</th>
                    <th style="padding: 10px;">Nome</th>
                    <th style="padding: 10px;">E-mail</th>
                    <th style="padding: 10px;">Perfil</th>
                    <th style="padding: 10px;">Professor Vinculado</th>
                    <th style="padding: 10px;">Troca Senha</th>
                    <th style="padding: 10px;">Criado em</th>
                    <th style="padding: 10px;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u):
                $rl = $role_labels[$u['role']] ?? ['?', '#999'];
            ?>
                <tr>
                    <td style="padding: 8px 10px; text-align: center;"><?php echo (int)$u['id']; ?></td>
                    <td style="padding: 8px 10px; font-weight: 600;"><?php echo htmlspecialchars($u['nome']); ?></td>
                    <td style="padding: 8px 10px;"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td style="padding: 8px 10px; text-align: center;">
                        <span style="background: <?php echo $rl[1]; ?>20; color: <?php echo $rl[1]; ?>; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 0.78rem;">
                            <?php echo $rl[0]; ?>
                        </span>
                    </td>
                    <td style="padding: 8px 10px;"><?php echo $u['professor_nome'] ? htmlspecialchars($u['professor_nome']) : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                    <td style="padding: 8px 10px; text-align: center;">
                        <?php echo $u['obrigar_troca_senha'] ? '<i class="fas fa-exclamation-triangle" style="color: #ffc107;" title="Pendente"></i>' : '<i class="fas fa-check" style="color: #28a745;" title="Ok"></i>'; ?>
                    </td>
                    <td style="padding: 8px 10px; font-size: 0.8rem; color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                    <td style="padding: 8px 10px; white-space: nowrap;">
                        <?php if ($auth_user_role === 'admin'): ?>
                            <button onclick='openModal(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="btn" style="padding: 5px 10px; font-size: 0.78rem; background: #0d6efd; color: #fff; border: none; border-radius: 6px; cursor: pointer;" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="usuarios.php?reset_id=<?php echo (int)$u['id']; ?>" onclick="return confirm('Redefinir senha para senaisp?')" class="btn" style="padding: 5px 10px; font-size: 0.78rem; background: #ffc107; color: #333; border: none; border-radius: 6px; text-decoration: none;" title="Resetar Senha">
                                <i class="fas fa-key"></i>
                            </a>
                            <?php if ((int)$u['id'] !== $auth_user_id): ?>
                            <a href="usuarios.php?delete_id=<?php echo (int)$u['id']; ?>" onclick="return confirm('Remover este usuário?')" class="btn" style="padding: 5px 10px; font-size: 0.78rem; background: #dc3545; color: #fff; border: none; border-radius: 6px; text-decoration: none;" title="Remover">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════ Modal Create/Edit User ═══════ -->
<div id="userModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background: var(--card-bg, #fff); border-radius: 16px; padding: 30px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 15px 50px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Novo Usuário</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:1.3rem; cursor:pointer; color: var(--text-muted);">&times;</button>
        </div>
        <form method="POST" action="usuarios_process.php" id="userForm">
            <input type="hidden" name="id" id="f_id" value="">
            <input type="hidden" name="action" id="f_action" value="create">

            <div style="margin-bottom: 14px;">
                <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.85rem;">Nome *</label>
                <input type="text" name="nome" id="f_nome" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-color); color: var(--text-color); box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 14px;">
                <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.85rem;">E-mail *</label>
                <input type="email" name="email" id="f_email" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-color); color: var(--text-color); box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 14px;">
                <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.85rem;">Perfil *</label>
                <select name="role" id="f_role" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-color); color: var(--text-color); box-sizing: border-box;">
                    <?php if ($auth_user_role === 'admin'): ?>
                        <option value="admin">Admin</option>
                        <option value="gestor">Gestor</option>
                    <?php endif; ?>
                    <option value="professor">Professor</option>
                </select>
            </div>
            <div style="margin-bottom: 14px;" id="professor_link_group">
                <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.85rem;">Vincular Professor (para perfil Professor)</label>
                <select name="professor_id" id="f_professor_id" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-color); color: var(--text-color); box-sizing: border-box;">
                    <option value="">— Nenhum —</option>
                    <?php foreach ($professores as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="padding: 10px; background: #fff8e1; border-radius: 8px; margin-bottom: 18px; font-size: 0.82rem; color: #e65100; border: 1px solid #ffe082;" id="senha_info">
                <i class="fas fa-info-circle"></i> A senha inicial será <strong>"senaisp"</strong>. O usuário será obrigado a trocar no primeiro login.
            </div>
            <div style="text-align: right; gap: 10px; display: flex; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" class="btn" style="padding: 10px 20px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 25px;"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(user) {
    const modal = document.getElementById('userModal');
    modal.style.display = 'flex';
    document.getElementById('f_id').value = '';
    document.getElementById('f_action').value = 'create';
    document.getElementById('f_nome').value = '';
    document.getElementById('f_email').value = '';
    document.getElementById('f_role').value = '<?php echo $auth_user_role === "admin" ? "gestor" : "professor"; ?>';
    document.getElementById('f_professor_id').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Novo Usuário';
    document.getElementById('senha_info').style.display = 'block';

    if (user && typeof user === 'object') {
        document.getElementById('f_id').value = user.id;
        document.getElementById('f_action').value = 'update';
        document.getElementById('f_nome').value = user.nome;
        document.getElementById('f_email').value = user.email;
        document.getElementById('f_role').value = user.role;
        document.getElementById('f_professor_id').value = user.professor_id || '';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Editar Usuário';
        document.getElementById('senha_info').style.display = 'none';
    }
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

// Click outside modal to close
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include 'includes/footer.php'; ?>
