<?php
require_once __DIR__ . '/includes/config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
} else {
    header('Location: login.php');
}
exit;
?>