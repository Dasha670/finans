<?php
require 'config.php';

$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_blogger BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

try {
    // 1. Создаем таблицы
    $pdo->exec($sql);
    
    // 2. Гарантируем наличие столбца is_blogger (если таблица уже существовала)
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_blogger'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_blogger BOOLEAN DEFAULT FALSE");
    }
    
    // 3. Создаем администратора
    $adminUsername = 'admin';
    $adminPassword = 'admin123';
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("SELECT 1 FROM admins WHERE username = ?");
    $stmt->execute([$adminUsername]);
    
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)")
            ->execute([$adminUsername, $hashedPassword]);
    }
    
    echo "База данных успешно инициализирована!";
} catch (PDOException $e) {
    die("Ошибка при инициализации базы данных: " . $e->getMessage());
}
?>