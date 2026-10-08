<?php
require_once __DIR__ . '/../auth/roles.php';
require_role(['admin', 'agent']);          // user 不能进来

require_once __DIR__ . '/../config/database.php';

$me      = current_user();
$my_id   = (int)$me['id'];
$isAdmin = ($me['role'] === 'admin');

// ============================================================
// 修改 / 删除用户 —— 只有 admin 做得到 (agent 就算硬发请求也会被挡住)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isAdmin) {
        $_SESSION['flash'] = ['error', 'Agents have view-only access.'];
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'update' && $id > 0) {
        $email   = trim($_POST['email'] ?? '');
        $role    = $_POST['role'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        // 不能把自己降级,不然就没有 admin 了
        if ($id === $my_id) {
            $role = 'admin';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['error', 'Please enter a valid email.'];
        } elseif (!in_array($role, VALID_ROLES, true)) {
            $_SESSION['flash'] = ['error', 'Invalid role.'];
        } elseif ($newPass !== '' && strlen($newPass) < 8) {
            $_SESSION['flash'] = ['error', 'New password must be at least 8 characters.'];
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $stmt->execute([$email, $id]);

            if ($stmt->fetch()) {
                $_SESSION['flash'] = ['error', 'That email is already used by another account.'];
            } else {
                if ($newPass !== '') {
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$email, $role, password_hash($newPass, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$email, $role, $id]);
                }

                if ($id === $my_id) {
                    $_SESSION['user']['email'] = $email;
                }
                $_SESSION['flash'] = ['ok', 'User updated.'];
            }
        }

    } elseif ($action === 'delete' && $id > 0) {

        if ($id === $my_id) {
            $_SESSION['flash'] = ['error', 'You cannot delete your own account.'];
        } else {
            // 连这个用户的资料一起删
            $pdo->beginTransaction();
            foreach (['transactions', 'budgets', 'categories'] as $table) {
                $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$id]);
            }
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $pdo->commit();

            $_SESSION['flash'] = ['ok', 'User deleted.'];
        }
    }

    header('Location: users.php');
    exit;
}

// ---------- 提示信息 (显示一次就消失) ----------
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---------- 用户列表 + 每个人的收入 / 支出 ----------
$users = $pdo->query(
    "SELECT u.id, u.email, u.role,
            COALESCE(SUM(CASE WHEN t.type = 'income'  THEN t.amount END), 0) AS income,
            COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount END), 0) AS expenses
     FROM users u
     LEFT JOIN transactions t ON t.user_id = u.id
     GROUP BY u.id, u.email, u.role
     ORDER BY FIELD(u.role, 'admin', 'agent', 'user'), u.id"
)->fetchAll();

function money($n) {
    return ($n < 0 ? '-' : '') . '$' . number_format(abs($n), 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link rel="stylesheet" href="categories.css">
    <link rel="stylesheet" href="roles.css">
</head>
<body class="cat-page">

    <header class="cat-navbar">
        <h1 class="cat-logo">DailyDime</h1>

        <ul class="cat-nav">
            <li>
                <a href="users.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
                    </svg>Users
                </a>
            </li>
        </ul>

        <div class="cat-logout" style="display:flex; align-items:center; gap:1rem;">
            <span class="rl-badge rl-<?= htmlspecialchars($me['role']) ?>"><?= htmlspecialchars($me['role']) ?></span>
            <a href="../verification/logout.php" title="Log out">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/>
                    <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
                </svg>
            </a>
        </div>
    </header>

    <section class="cat-header">
        <div>
            <h1 class="cat-title">Users</h1>
            <p class="cat-sub">
                <?= $isAdmin ? 'Manage user accounts and roles.' : 'View users and their data.' ?>
            </p>
        </div>
    </section>

    <?php if (!$isAdmin): ?>
        <p class="rl-note">You are signed in as an agent. You can view everything, but you cannot change anything.</p>
    <?php endif; ?>

    <?php if ($flash): ?>
        <p class="rl-flash <?= $flash[0] === 'ok' ? 'rl-flash-ok' : 'rl-flash-error' ?>">
            <?= htmlspecialchars($flash[1]) ?>
        </p>
    <?php endif; ?>

    <section class="rl-list">
        <?php foreach ($users as $u):
            $isSelf = ((int)$u['id'] === $my_id);
        ?>
            <div class="rl-row">
                <div class="rl-who">
                    <div class="rl-email">
                        <?= htmlspecialchars($u['email']) ?>
                        <?= $isSelf ? '(you)' : '' ?>
                    </div>
                    <span class="rl-badge rl-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span>
                    <div class="rl-meta">
                        Income <?= money((float)$u['income']) ?>
                        · Expenses <?= money((float)$u['expenses']) ?>
                        · Balance <?= money((float)$u['income'] - (float)$u['expenses']) ?>
                    </div>
                </div>

                <div class="rl-actions">
                    <!-- admin 和 agent 都能看 -->
                    <a class="rl-btn" href="user_view.php?id=<?= (int)$u['id'] ?>">View</a>

                    <?php if ($isAdmin): ?>
                        <button type="button" class="rl-btn edit-btn"
                                data-id="<?= (int)$u['id'] ?>"
                                data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                                data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES) ?>"
                                data-self="<?= $isSelf ? '1' : '0' ?>">Edit</button>

                        <?php if (!$isSelf): ?>
                            <form method="POST" action="users.php"
                                  onsubmit="return confirm('Delete this user and all their data?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="cat-delete-btn">Delete</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if ($isAdmin): ?>
    <!-- 弹窗:Edit User (只有 admin 看得到) -->
    <div class="cat-overlay" id="edit-modal">
        <div class="cat-modal">
            <h2>Edit User</h2>

            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-id">

                <label for="edit-email">Email</label>
                <input type="email" id="edit-email" name="email" required>

                <label for="edit-role">Role</label>
                <select id="edit-role" name="role">
                    <option value="user">User</option>
                    <option value="agent">Agent</option>
                    <option value="admin">Admin</option>
                </select>
                <p class="rl-hint" id="self-hint" style="display:none;">You cannot change your own role.</p>

                <label for="edit-pass">New Password</label>
                <input type="password" id="edit-pass" name="new_password" minlength="8" placeholder="Leave empty to keep the current one" autocomplete="new-password">

                <div class="cat-modal-buttons">
                    <button type="button" class="cat-cancel-btn" id="cancel-edit">Cancel</button>
                    <button type="submit" class="cat-create-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById("edit-modal");

        document.querySelectorAll(".edit-btn").forEach(function (btn) {
            btn.addEventListener("click", function () {
                document.getElementById("edit-id").value    = btn.dataset.id;
                document.getElementById("edit-email").value = btn.dataset.email;
                document.getElementById("edit-role").value  = btn.dataset.role;
                document.getElementById("edit-pass").value  = "";

                const isSelf = btn.dataset.self === "1";
                document.getElementById("edit-role").disabled = isSelf;
                document.getElementById("self-hint").style.display = isSelf ? "block" : "none";

                modal.classList.add("show");
            });
        });

        document.getElementById("cancel-edit").addEventListener("click", function () {
            modal.classList.remove("show");
        });

        modal.addEventListener("click", function (e) {
            if (e.target === modal) {
                modal.classList.remove("show");
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>