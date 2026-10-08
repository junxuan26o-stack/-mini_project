

<?php

require_once __DIR__ . '/../auth/roles.php';
require_role(['user']);
require_once __DIR__ . '/../config/database.php';
$user_id = $_SESSION['user']['id'];

$user_id = $_SESSION['user']['id'];

// ---------- 新增 / 删除 transaction ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $user_id]);

    } else {
        $type        = $_POST['type'] ?? '';
        $amount      = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
        $date        = $_POST['date'] ?? date('Y-m-d');
        $description = trim($_POST['description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);

        $dateOk = DateTime::createFromFormat('Y-m-d', $date) !== false;

        if (in_array($type, ['income', 'expense'], true)
            && $amount !== false && $amount > 0 && $amount < 100000000
            && $dateOk) {

            // category 必须是这个用户自己的
            if ($category_id > 0) {
                $check = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
                $check->execute([$category_id, $user_id]);
                if (!$check->fetch()) {
                    $category_id = 0;
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO transactions (user_id, type, amount, category_id, description, transaction_date)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $user_id,
                $type,
                round($amount, 2),
                $category_id > 0 ? $category_id : null,
                $description,
                $date
            ]);
        }
    }

    header('Location: tracker.php');
    exit;
}

// ---------- 读取数据 ----------
$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expenses
     FROM transactions
     WHERE user_id = ?"
);
$stmt->execute([$user_id]);
$totals = $stmt->fetch();

$totalIncome   = (float)$totals['total_income'];
$totalExpenses = (float)$totals['total_expenses'];
$balance       = $totalIncome - $totalExpenses;

$stmt = $pdo->prepare(
    "SELECT t.*, c.name AS category_name
     FROM transactions t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ?
     ORDER BY t.transaction_date DESC, t.id DESC"
);
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

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
    <title>Spending Tracker</title>
    <link rel="stylesheet" href="categories.css">
    <link rel="stylesheet" href="tracker.css">
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

    <!-- 标题 + Add Transaction 按钮 -->
    <section class="cat-header">
        <div>
            <h1 class="cat-title">Spending Tracker</h1>
            <p class="cat-sub">Record your income and expenses.</p>
        </div>
        <button type="button" class="cat-add-btn" id="open-transaction">+ Add Transaction</button>
    </section>

    <!-- 总数 -->
    <section class="tr-summary">
        <div class="tr-card">
            <small>Total Income</small>
            <strong class="tr-income"><?= money($totalIncome) ?></strong>
        </div>
        <div class="tr-card">
            <small>Total Expenses</small>
            <strong class="tr-expense"><?= money($totalExpenses) ?></strong>
        </div>
        <div class="tr-card">
            <small>Balance</small>
            <strong><?= money($balance) ?></strong>
        </div>
    </section>

    <!-- 交易列表 -->
    <?php if (count($transactions) === 0): ?>

        <p class="tr-empty">No transactions yet. Press "+ Add Transaction" to add your first one.</p>

    <?php else: ?>

        <section class="tr-list">
            <?php foreach ($transactions as $t): ?>
                <div class="tr-item">
                    <div class="tr-info">
                        <div class="tr-desc">
                            <?= htmlspecialchars($t['description'] !== '' ? $t['description'] : ucfirst($t['type'])) ?>
                        </div>
                        <div class="tr-meta">
                            <?= htmlspecialchars($t['transaction_date']) ?>
                            · <?= htmlspecialchars($t['category_name'] ?? 'No category') ?>
                        </div>
                    </div>

                    <div class="tr-right">
                        <span class="tr-amount <?= $t['type'] === 'income' ? 'tr-income' : 'tr-expense' ?>">
                            <?= $t['type'] === 'income' ? '+' : '-' ?>$<?= number_format((float)$t['amount'], 2) ?>
                        </span>

                        <form method="POST" action="tracker.php">
                            <input type="hidden" name="delete_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="cat-delete-btn">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

    <?php endif; ?>

    <!-- 弹窗:Add Transaction -->
    <div class="cat-overlay" id="transaction-modal">
        <div class="cat-modal">
            <h2>Add Transaction</h2>

            <form method="POST" action="tracker.php">

                <div class="tr-type">
                    <input type="radio" name="type" id="type-income" value="income" checked>
                    <label for="type-income">Income</label>

                    <input type="radio" name="type" id="type-expense" value="expense">
                    <label for="type-expense">Expense</label>
                </div>

                <label for="tr-amount">Amount</label>
                <input type="number" id="tr-amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>

                <label for="tr-category">Category</label>
                <select id="tr-category" name="category_id">
                    <option value="0">No category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="tr-description">Description</label>
                <input type="text" id="tr-description" name="description" maxlength="255" placeholder="e.g. Lunch, Salary">

                <label for="tr-date">Date</label>
                <input type="date" id="tr-date" name="date" value="<?= date('Y-m-d') ?>" required>

                <div class="cat-modal-buttons">
                    <button type="button" class="cat-cancel-btn" id="cancel-transaction">Cancel</button>
                    <button type="submit" class="cat-create-btn">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById("transaction-modal");

        document.getElementById("open-transaction").addEventListener("click", function () {
            modal.classList.add("show");
            document.getElementById("tr-amount").focus();
        });

        document.getElementById("cancel-transaction").addEventListener("click", function () {
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