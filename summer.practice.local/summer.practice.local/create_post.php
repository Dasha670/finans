<?php
session_start();
require 'config.php';

// Проверка прав на публикацию
if (!isset($_SESSION['user_id']) || !$_SESSION['is_blogger']) {
    header("Location: posts.php");
    exit();
}

$postId = $_GET['id'] ?? null;
$post = null;

// Редактирование существующего поста
if ($postId) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$postId, $_SESSION['user_id']]);
    $post = $stmt->fetch();
    
    if (!$post) {
        header("Location: posts.php");
        exit();
    }
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title)) {
        $_SESSION['error'] = "Заголовок не может быть пустым";
    } else {
        try {
            if ($postId) {
                // Обновление поста
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $postId]);
                $_SESSION['message'] = "Пост успешно обновлен";
            } else {
                // Создание нового поста
                $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $title, $content]);
                $_SESSION['message'] = "Пост успешно создан";
            }
            
            header("Location: posts.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $postId ? 'Редактировать пост' : 'Создать пост' ?></title>
    <link rel="stylesheet" href="style4.css">
</head>
<body>
    <header>
        <h1><?= $postId ? 'Редактировать пост' : 'Создать пост' ?></h1>
        <div class="auth-section">
            <span>Автор: <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="posts.php" class="auth-button">Назад к постам</a>
        </div>
    </header>

    <main>
        <form class="post-form" method="post">
            <div class="form-group">
                <label for="title">Заголовок:</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="content">Содержание:</label>
                <textarea id="content" name="content" required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
            </div>
            
            <div class="post-actions">
                <button type="submit" class="action-button"><?= $postId ? 'Обновить' : 'Опубликовать' ?></button>
                <a href="posts.php" class="action-button secondary">Отмена</a>
            </div>
        </form>
    </main>
</body>
</html>