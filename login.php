<?php

session_start();



$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT *FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if($user && password_verify($password , $user['password'])) {

        $_SESSION['user'] =[
            'id' => $user['id'],
            'name' =>$user['name'],
            'email' => $user['email'],
        ];

        header('Location : products.php');
        exit;

    }else {
        $error = 'Invalid email or password.';
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="stylelogin.css">
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="subtitle">Log in to your account</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label><br>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label><br>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-auth">Log In</button>
            </form>

            <p class="auth-switch">
                Don't have an account? <a href="register.php">Sign up</a>
            </p>
        </div>
    </div>

</body>
</html>