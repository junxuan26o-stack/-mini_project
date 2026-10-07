<?php
session_start();

// 没登录就送回登录页
if (!isset($_SESSION['user'])) {
    header('Location: ../verification/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user']['id'];

// ---------- 选的月份(格式 2026-10),没选就用这个月 ----------
$month = $_POST['month'] ?? $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

// ---------- 新增 / 删除 budget ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $user_id]);

    } else {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $amount      = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);

        if ($category_id > 0 && $amount !== false && $amount > 0 && $amount < 100000000) {

            // category 必须是这个用户自己的
            $check = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
            $check->execute([$category_id, $user_id]);

            if ($check->fetch()) {
                // 同一个 category 同一个月已经有预算 → 直接更新金额
                $stmt = $pdo->prepare(
                    "INSERT INTO budgets (user_id, category_id, month, amount)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE amount = VALUES(amount)"
                );
                $stmt->execute([$user_id, $category_id, $month, round($amount, 2)]);
            }
        }
    }

    header('Location: budget.php?month=' . urlencode($month));
    exit;
}

// ---------- 读取这个月每个 category 的预算和已花费 ----------
$stmt = $pdo->prepare(
    "SELECT b.id, b.amount AS budget, c.name,
            COALESCE(SUM(t.amount), 0) AS spent
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
$stmt->execute([$user_id, $month]);
$items = $stmt->fetchAll();

$totalBudgeted = 0;
$totalSpent    = 0;
foreach ($items as $it) {
    $totalBudgeted += (float)$it['budget'];
    $totalSpent    += (float)$it['spent'];
}
$remaining = $totalBudgeted - $totalSpent;
$usage     = $totalBudgeted > 0 ? round($totalSpent / $totalBudgeted * 100) : 0;
$isOver    = $totalSpent > $totalBudgeted;

// 弹窗里的 category 下拉
$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

function money($n) {
    return ($n < 0 ? '-' : '') . '$' . number_format(abs($n), 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget</title>
    <link rel="stylesheet" href="categories.css">
    <link rel="stylesheet" href="budget.css">
</head>
<body class="cat-page">

    <!-- 导航栏 -->
    <header class="cat-navbar">
        <h1 class="cat-logo">DailyDime</h1>

        <ul class="cat-nav">
            <li>
                <a href="dashboard.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z"/>
                    </svg>Dashboard
                </a>
            </li>
            <li>
                <a href="tracker.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M4 10.781c.148 1.667 1.513 2.85 3.591 3.003V15h1.043v-1.216c2.27-.179 3.678-1.438 3.678-3.3 0-1.59-.947-2.51-2.956-3.028l-.722-.187V3.467c1.122.11 1.879.714 2.07 1.616h1.47c-.166-1.6-1.54-2.748-3.54-2.875V1H7.591v1.233c-1.939.23-3.27 1.472-3.27 3.156 0 1.454.966 2.483 2.661 2.917l.61.162v4.031c-1.149-.17-1.94-.8-2.131-1.718zm3.391-3.836c-1.043-.263-1.6-.825-1.6-1.616 0-.944.704-1.641 1.8-1.828v3.495l-.2-.05zm1.591 1.872c1.287.323 1.852.859 1.852 1.769 0 1.097-.826 1.828-2.2 1.939V8.73z"/>
                    </svg>Spending Tracker
                </a>
            </li>
            <li>
                <a href="budget.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M12 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                        <path d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5zm0 4a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5z"/>
                    </svg>Budget
                </a>
            </li>
            <li>
                <a href="categories.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3m4 3a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M5.5 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m.5 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3"/>
                        <path d="M16 8c0 3.15-1.866 2.585-3.567 2.07C11.42 9.763 10.465 9.473 10 10c-.603.683-.475 1.819-.351 2.92C9.826 14.495 9.996 16 8 16a8 8 0 1 1 8-8m-8 7c.611 0 .654-.171.655-.176.078-.146.124-.464.07-1.119-.014-.168-.037-.37-.061-.591-.052-.464-.112-1.005-.118-1.462-.01-.707.083-1.61.704-2.314.369-.417.845-.578 1.272-.618.404-.038.812.026 1.16.104.343.077.702.186 1.025.284l.028.008c.346.105.658.199.953.266.653.148.904.083.991.024C14.717 9.38 15 9.161 15 8a7 7 0 1 0-7 7"/>
                    </svg>Categories
                </a>
            </li>
        </ul>

        <div class="cat-logout">
            <a href="../verification/logout.php" title="Log out">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/>
                    <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
                </svg>
            </a>
        </div>
    </header>

    <!-- 标题 + Add Budget Item 按钮 -->
    <section class="cat-header">
        <div>
            <h1 class="cat-title">Budget</h1>
            <p class="cat-sub">Set a monthly limit for each category.</p>
        </div>
        <button type="button" class="cat-add-btn" id="open-budget">+ Add Budget Item</button>
    </section>

    <!-- Monthly Budget Summary -->
    <section class="bd-summary">
        <div class="bd-summary-top">
            <h2>Monthly Budget Summary</h2>

            <div class="bd-controls">


                <span class="bd-badge <?= $isOver ? 'bd-over' : 'bd-under' ?>">
                    <?= $isOver ? 'OVER BUDGET' : 'UNDER BUDGET' ?>
                </span>
            </div>
        </div>

        <div class="bd-stats">
            <div class="bd-stat">
                <small>Total Budgeted</small>
                <strong><?= money($totalBudgeted) ?></strong>
            </div>
            <div class="bd-stat">
                <small>Total Spent</small>
                <strong class="bd-red"><?= money($totalSpent) ?></strong>
            </div>
            <div class="bd-stat">
                <small>Remaining</small>
                <strong class="<?= $remaining < 0 ? 'bd-red' : 'bd-green' ?>">
                    <?= ($remaining >= 0 ? '+' : '') . money($remaining) ?>
                </strong>
            </div>
            <div class="bd-stat">
                <small>Budget Usage</small>
                <strong><?= $usage ?>%</strong>
            </div>
        </div>
    </section>

    <!-- 每个 category 的预算 -->
    <?php if (count($items) === 0): ?>

        <p class="bd-empty">
            <?php if (count($categories) === 0): ?>
                You need a category first. <a href="categories.php">Add a category</a>, then come back to set a budget.
            <?php else: ?>
                No budget for this month yet. Press "+ Add Budget Item" to add one.
            <?php endif; ?>
        </p>

    <?php else: ?>

        <section class="bd-list">
            <?php foreach ($items as $it):
                $budget = (float)$it['budget'];
                $spent  = (float)$it['spent'];
                $pct    = $budget > 0 ? min(100, round($spent / $budget * 100)) : 0;
                $over   = $spent > $budget;
            ?>
                <div class="bd-item">
                    <div class="bd-item-top">
                        <span class="bd-item-name"><?= htmlspecialchars($it['name']) ?></span>

                        <div class="bd-item-right">
                            <span class="bd-item-nums">
                                <span class="<?= $over ? 'bd-red' : '' ?>"><?= money($spent) ?></span>
                                / <?= money($budget) ?>
                            </span>

                            <form method="POST" action="budget.php">
                                <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                                <input type="hidden" name="delete_id" value="<?= (int)$it['id'] ?>">
                                <button type="submit" class="cat-delete-btn">Delete</button>
                            </form>
                        </div>
                    </div>

                    <div class="bd-bar">
                        <div class="bd-bar-fill <?= $over ? 'bd-bar-over' : '' ?>" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

    <?php endif; ?>

    <!-- 弹窗:Add Budget Item -->
    <div class="cat-overlay" id="budget-modal">
        <div class="cat-modal">
            <h2>Add Budget Item</h2>

            <form method="POST" action="budget.php">
                <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">

                <label for="bd-category">Category</label>
                <select id="bd-category" name="category_id" required>
                    <?php if (count($categories) === 0): ?>
                        <option value="">No categories yet</option>
                    <?php else: ?>
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <label for="bd-amount">Monthly Budget</label>
                <input type="number" id="bd-amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>

                <p class="bd-hint">
                    For <?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?>.
                    Adding the same category again will update its amount.
                </p>

                <div class="cat-modal-buttons">
                    <button type="button" class="cat-cancel-btn" id="cancel-budget">Cancel</button>
                    <button type="submit" class="cat-create-btn">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById("budget-modal");

        document.getElementById("open-budget").addEventListener("click", function () {
            modal.classList.add("show");
        });

        document.getElementById("cancel-budget").addEventListener("click", function () {
            modal.classList.remove("show");
        });

        modal.addEventListener("click", function (e) {
            if (e.target === modal) {
                modal.classList.remove("show");
            }
        });
    </script>
</body>
</html>