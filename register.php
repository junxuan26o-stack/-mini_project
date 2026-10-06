<?php

session_start();

require_once __DIR__ . '../config/database.php';

$error = '';

if($_SERVER['REQUEST_METHOD'] ==='POST'){
  $email = $_POST['email'];
  $password = $_POST['password'];

  $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
  $stmt->execute([$email]);
  $existingUser = $stmt->fetch();

  if($existingUser){
    $error = 'This email is already registered.';
  }else{
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (email, password)');
    $stmt->execute([$name, $email, $hashedPassword]);

    $_SESSION['user'] = [
      'id' =>$pdo->lastInsertId(),
      'email' => $email,
    ];

    header('Location; products.php');
    exit;
  }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="stylelogin.css">
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">
            <h1>Create account</h1>
            <p class="subtitle">Sign up to start shopping</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-auth">Create Account</button>
            </form>

            <p class="auth-switch">
                Already have an account? <a href="login.php">Log in</a>
            </p>
        </div>
    </div>

</body>
</html>