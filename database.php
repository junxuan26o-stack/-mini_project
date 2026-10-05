<?php
// ============================================================
// database.php — Database connection
// ============================================================
// YOUR TASK: Fill in the values below to connect to your
// MySQL database. Then complete the PDO connection.
// ============================================================

$host   = 'localhost';
$dbname = 'fullstack_shop';
$user   = 'root';
$pass   = '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
?>