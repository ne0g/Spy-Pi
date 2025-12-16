<?php
// login.php
require __DIR__ . '/config.php';

global $VALID_USERNAME, $VALID_PASSWORD;

// If logged in, just go straight through to recordings.
if (is_logged_in()) {
    header('Location: recordings.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === $VALID_USERNAME && $pass === $VALID_PASSWORD) {
        session_regenerate_id(true); // prevent session fixation
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $user;
        header('Location: recordings.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Spy-Pi Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container auth-box">
    <h1 class="center">Spy-Pi Login</h1>
    <p class="subtitle center">Sign in to view uploaded recordings.</p>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="center">
        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            autocomplete="username"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <input type="submit" value="Login">
    </form>
</div>
</body>
</html>
