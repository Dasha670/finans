<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // Проверяем существование таблицы users
        $tableExists = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
        
        if (!$tableExists) {
            $pdo->exec("CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                is_blogger BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }

        if ($action === 'register') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $passwordConfirm = $_POST['password_confirm'];
            
            if (empty($username) || empty($password)) {
                throw new Exception("Все поля обязательны для заполнения");
            }
            
            if (strlen($username) < 3) {
                throw new Exception("Логин должен содержать минимум 3 символа");
            }
            
            if (strlen($password) < 6) {
                throw new Exception("Пароль должен содержать минимум 6 символов");
            }
            
            if ($password !== $passwordConfirm) {
                throw new Exception("Пароли не совпадают");
            }
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                throw new Exception("Пользователь с таким логином уже существует");
            }
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashedPassword]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            
            $_SESSION['message'] = "Регистрация прошла успешно!";
            
            // Редирект после регистрации
            $redirect_url = $_POST['redirect_url'] ?? 'index.php';
            header("Location: " . validateRedirectUrl($redirect_url));
            exit();
        }
        elseif ($action === 'login') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            if (empty($username) || empty($password)) {
                throw new Exception("Все поля обязательны для заполнения");
            }
            
            $stmt = $pdo->prepare("SELECT id, username, password, is_blogger FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("Неверный логин или пароль");
            }
            
            if (!password_verify($password, $user['password'])) {
                throw new Exception("Неверный логин или пароль");
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_blogger'] = $user['is_blogger'];
            
            $_SESSION['message'] = "Добро пожаловать, " . htmlspecialchars($user['username']) . "!";
            
            // Редирект после входа
            $redirect_url = $_POST['redirect_url'] ?? 'index.php';
            header("Location: " . validateRedirectUrl($redirect_url));
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Если не POST-запрос или что-то пошло не так
header("Location: index.php");
exit();

/**
 * Валидация URL для редиректа
 */
function validateRedirectUrl($url) {
    // Разрешаем только относительные пути или пути в рамках текущего домена
    if (empty($url) || strpos($url, 'http') === 0) {
        return 'index.php';
    }
    
    // Удаляем возможные параметры, которые могут быть опасны
    $clean_url = parse_url($url, PHP_URL_PATH);
    
    // Если URL не начинается с /, добавляем его
    if (strpos($clean_url, '/') !== 0) {
        $clean_url = '/' . $clean_url;
    }
    
    // Проверяем существование файла (опционально)
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $clean_url)) {
        return 'index.php';
    }
    
    return $clean_url;
}
?>