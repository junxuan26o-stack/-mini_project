<?php
require_once __DIR__ . '/../config/database.php';
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
?>