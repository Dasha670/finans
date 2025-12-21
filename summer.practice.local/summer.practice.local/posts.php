<?php
session_start();
require 'config.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Добавляем библиотеку для Markdown
require 'Parsedown.php';
$parsedown = new Parsedown();
$parsedown->setSafeMode(true); // Включаем безопасный режим для защиты от XSS

// Получение постов
$stmt = $pdo->query("
    SELECT p.*, u.username 
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC
");
$posts = $stmt->fetchAll();

// Удаление поста (только для автора или админа)
if (isset($_GET['delete'])) {
    $postId = $_GET['delete'];
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        if ($post && ($post['user_id'] == $_SESSION['user_id'] || $_SESSION['is_admin'])) {
            $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$postId]);
            $_SESSION['message'] = "Пост удален";
        }
    }
    
    header("Location: posts.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Финансовые советы</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="style3.css">
</head>
<body class="body-image-left">
<header>
    <div class="nav-menu">
        <button class="nav-toggle">Меню</button>
        <div class="nav-dropdown">
            <a href="index.php">Главная (Доходы/Расходы)</a>
            <a href="savings.php">Мои накопления</a>
            <a href="debts.php">Мои задолженности</a>
            <a href="posts.php">Финансовые советы</a>
        </div>
    </div>
    
    <h1>Посты с финансовыми советами</h1>
    
    <div class="auth-section">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span>Добро пожаловать, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="?logout=1" class="auth-button">Выйти</a>
        <?php else: ?>
            <button id="authButton" class="auth-button">Войти / Регистрация</button>
        <?php endif; ?>
    </div>
</header>

    <!-- Модальное окно авторизации -->
    <div id="authModal" class="modal" style="display: <?= isset($_SESSION['user_id']) ? 'none' : 'flex' ?>;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Авторизация</h2>
            <form id="loginForm" method="post" action="auth.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="login-username">Логин:</label>
                    <input type="text" id="login-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Пароль:</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit" class="action-button">Войти</button>
            </form>

            <div class="divider"></div>

            <h2>Регистрация</h2>
            <form id="registerForm" method="post" action="auth.php">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="reg-username">Логин:</label>
                    <input type="text" id="reg-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="reg-password">Пароль:</label>
                    <input type="password" id="reg-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="reg-password-confirm">Подтвердите пароль:</label>
                    <input type="password" id="reg-password-confirm" name="password_confirm" required>
                </div>
                <button type="submit" class="action-button">Зарегистрироваться</button>
            </form>
        </div>
    </div>

    <main>
        <?php if (isset($_SESSION['is_blogger']) && $_SESSION['is_blogger']): ?>
            <div class="add-post-container">
                <a href="create_post.php" class="add-post-button">Добавить пост</a>
            </div>
        <?php endif; ?>
        <div class="posts-container">
            <?php foreach ($posts as $post): ?>
                <div class="post">
                    <?php if ((isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']) || 
                              (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])): ?>
                        <div class="post-actions">
                            <a href="create_post.php?id=<?= $post['id'] ?>" class="post-action">✏️</a>
                            <a href="posts.php?delete=<?= $post['id'] ?>" class="post-action" 
                               onclick="return confirm('Удалить этот пост?')">🗑️</a>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="post-title"><?= htmlspecialchars($post['title']) ?></h2>
                    <div class="post-content collapsed">
                        <?= $parsedown->text($post['content']) ?>
                    </div>
                    <div class="post-meta">
                        Автор: <?= htmlspecialchars($post['username']) ?> | 
                        Дата: <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Раскрытие/сворачивание постов
        document.querySelectorAll('.post-content').forEach(content => {
            content.addEventListener('click', function() {
                this.classList.toggle('collapsed');
            });
        });

        // Элементы модального окна
        const modal = document.getElementById('authModal');
        const btn = document.getElementById('authButton');
        const span = document.querySelector('.close');
        
        // Открытие модального окна по кнопке
        if (btn) {
            btn.addEventListener('click', function() {
                if (modal) {
                    modal.style.display = "flex";
                    document.body.style.overflow = "hidden";
                }
            });
        }
        
        // Закрытие модального окна
        if (span) {
            span.addEventListener('click', function() {
                if (modal) {
                    modal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });
        }
        
        // Закрытие при клике вне модального окна
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = "none";
                document.body.style.overflow = "auto";
            }
        });
        
        // Валидация формы регистрации
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                const password = document.getElementById('reg-password').value;
                const confirmPassword = document.getElementById('reg-password-confirm').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Пароли не совпадают!');
                }
            });
        }
    </script>
</body>
</html>