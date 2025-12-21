<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Добавим отладочный вывод (удалите в продакшене)
    error_log("Attempting login for admin: " . $username);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            error_log("Admin found, verifying password...");
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                header("Location: admin_panel.php");
                exit();
            }
        } else {
            // Задержка при неудачной попытке входа
            sleep(2);
        }
        
        // Если дошли сюда - авторизация не удалась
        $_SESSION['admin_login_error'] = "Неверные учетные данные";
        header("Location: admin_login.php");
        exit();
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['admin_login_error'] = "Ошибка сервера";
        header("Location: admin_login.php");
        exit();
    }
}
?>