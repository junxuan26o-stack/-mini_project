<?php
require_once __DIR__ . '/../auth/roles.php';
require_role(['admin', 'agent']);          // user 不能进来

require_once __DIR__ . '/../config/database.php';

$me = current_user();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$target = $stmt->fetch();

if (!$target) {
    header('Location: users.php');
    exit;
}

// ---------- 总数 ----------
$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'income'  THEN amount END), 0) AS inc,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS exp
     FROM transactions WHERE user_id = ?"
);
$stmt->execute([$id]);
$tot     = $stmt->fetch();
$income  = (float)$tot['inc'];
$expense = (float)$tot['exp'];
$balance = $income - $expense;

// ---------- 最近的 transactions ----------
$stmt = $pdo->prepare(
    "SELECT t.type, t.amount, t.description, t.transaction_date, c.name AS category_name
     FROM transactions t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ?
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT 50"
);
$stmt->execute([$id]);
$transactions = $stmt->fetchAll();

// ---------- categories ----------
$stmt = $pdo->prepare("SELECT name, hidden FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$id]);
$categories = $stmt->fetchAll();

// ---------- 这个月的 budget ----------
$month = date('Y-m');
$stmt = $pdo->prepare(
    "SELECT c.name, b.amount AS budget, COALESCE(SUM(t.amount), 0) AS spent
     FROM budgets b
     JOIN categories c ON c.id = b.category_id
     LEFT JOIN transactions t
            ON t.category_id = b.category_id
           AND t.user_id = b.user_id
           AND t.type = 'expense'
           AND DATE_FORMAT(t.transaction_date, '%Y-%m') = b.month
     WHERE b.user_id = ? AND b.month = ?
     GROUP BY b.id, b.amount, c.name
     ORDER BY c.name"
);
$stmt->execute([$id, $month]);
$budgets = $stmt->fetchAll();

function money($n) {
    return ($n < 0 ? '-' : '') . '$' . number_format(abs($n), 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User</title>
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
            <h1 class="cat-title" style="font-size:2rem; word-break:break-all;">
                <?= htmlspecialchars($target['email']) ?>
            </h1>
            <p class="cat-sub">
                <span class="rl-badge rl-<?= htmlspecialchars($target['role']) ?>"><?= htmlspecialchars($target['role']) ?></span>
                &nbsp;View only
            </p>
        </div>
        <a class="cat-add-btn" href="users.php" style="text-decoration:none; color:#3d211a;">&larr; Back</a>
    </section>

    <!-- 总数 -->
    <section class="rl-summary">
        <div class="rl-card">
            <small>Total Income</small>
            <strong class="rl-green"><?= money($income) ?></strong>
        </div>
        <div class="rl-card">
            <small>Total Expenses</small>
            <strong class="rl-red"><?= money($expense) ?></strong>
        </div>
        <div class="rl-card">
            <small>Balance</small>
            <strong><?= money($balance) ?></strong>
        </div>
    </section>

    <!-- Budget -->
    <section class="rl-section">
        <h2>Budget (<?= htmlspecialchars(date('F Y')) ?>)</h2>
        <?php if (count($budgets) === 0): ?>
            <p class="rl-empty">No budget set for this month.</p>
        <?php else: ?>
            <?php foreach ($budgets as $b):
                $over = (float)$b['spent'] > (float)$b['budget'];
            ?>
                <div class="rl-item">
                    <span><?= htmlspecialchars($b['name']) ?></span>
                    <span>
                        <span class="<?= $over ? 'rl-red' : '' ?>"><?= money((float)$b['spent']) ?></span>
                        / <?= money((float)$b['budget']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- Categories -->
    <section class="rl-section">
        <h2>Categories</h2>
        <?php if (count($categories) === 0): ?>
            <p class="rl-empty">No categories.</p>
        <?php else: ?>
            <?php foreach ($categories as $c): ?>
                <div class="rl-item">
                    <span><?= htmlspecialchars($c['name']) ?></span>
                    <?php if ($c['hidden']): ?><small>Hidden from tracker</small><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- Transactions -->
    <section class="rl-section">
        <h2>Recent Transactions</h2>
        <?php if (count($transactions) === 0): ?>
            <p class="rl-empty">No transactions.</p>
        <?php else: ?>
            <?php foreach ($transactions as $t): ?>
                <div class="rl-item">
                    <div>
                        <?= htmlspecialchars($t['description'] !== '' ? $t['description'] : ucfirst($t['type'])) ?>
                        <small>
                            <?= htmlspecialchars($t['transaction_date']) ?>
                            · <?= htmlspecialchars($t['category_name'] ?? 'No category') ?>
                        </small>
                    </div>
                    <strong class="<?= $t['type'] === 'income' ? 'rl-green' : 'rl-red' ?>">
                        <?= $t['type'] === 'income' ? '+' : '-' ?>$<?= number_format((float)$t['amount'], 2) ?>
                    </strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

</body>
</html>