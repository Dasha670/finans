<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_panel.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Вход для администратора</title>
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <h1>Вход в панель администратора</h1>
    
    <?php if (isset($_SESSION['admin_login_error'])): ?>
        <div class="error"><?= $_SESSION['admin_login_error'] ?></div>
        <?php unset($_SESSION['admin_login_error']); ?>
    <?php endif; ?>
    
    <form method="POST" action="admin_auth.php">
        <div class="string">
            <label>Логин:</label>
            <input type="text" name="username" required>
        </div>
        <div class="string">
            <label>Пароль:</label>
            <input type="password" name="password" required>
        </div>
        <div class="bb">
        <button type="submit">Войти</button>
        </div>
    </form>
</body>
</html>