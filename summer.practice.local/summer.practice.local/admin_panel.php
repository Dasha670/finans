<?php
session_start();
require 'config.php';

// Проверка авторизации администратора
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Проверяем существование столбца is_blogger
$columnExists = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_blogger'")->fetch();
if (!$columnExists) {
    die("Ошибка: в таблице users отсутствует столбец is_blogger");
}

// Удаление пользователя
if (isset($_GET['delete'])) {
    try {
        // Запрещаем удаление самого себя
        if ($_GET['delete'] == $_SESSION['admin_id']) {
            $_SESSION['message'] = "Вы не можете удалить себя";
            header("Location: admin_panel.php");
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['message'] = "Пользователь успешно удален";
        header("Location: admin_panel.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['message'] = "Ошибка при удалении пользователя: " . $e->getMessage();
        header("Location: admin_panel.php");
        exit();
    }
}

// Изменение прав пользователей
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $isBlogger = isset($_POST['is_blogger']) ? 1 : 0;
    $pdo->prepare("UPDATE users SET is_blogger = ? WHERE id = ?")
        ->execute([$isBlogger, $_POST['user_id']]);
}

// Получаем список пользователей
$users = $pdo->query("
    SELECT id, username, is_blogger 
    FROM users 
    ORDER BY created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Панель администратора</title>
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <h1>Управление правами на публикацию</h1>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message"><?= $_SESSION['message'] ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <table class="admin-table">
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Права на публикацию</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?= $user['id'] ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="checkbox" name="is_blogger" 
                           <?= $user['is_blogger'] ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                </form>
            </td>
            <td>
                <a href="?delete=<?= $user['id'] ?>" 
                   onclick="return confirm('Удалить пользователя?')">Удалить</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <a href="admin_logout.php" class="logout-btn">Выйти</a>
</body>
</html>