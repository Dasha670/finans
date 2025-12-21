<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Для выполнения действия требуется авторизация';
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['user_id'];
    
    try {
        if ($action === 'income') {
            $amount = (float)($_POST['new-income'] ?? 0);
            $category = $_POST['income-category'] ?? '';
            
            if ($amount <= 0) {
                throw new Exception("Сумма дохода должна быть больше 0");
            }
            
            if (empty($category)) {
                throw new Exception("Не выбрана категория дохода");
            }
            
            $date = new DateTime();
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, category, date, month, year) 
                                  VALUES (?, 'income', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $amount,
                $category,
                $date->format('Y-m-d H:i:s'),
                $date->format('n') - 1,
                $date->format('Y')
            ]);
            
            $_SESSION['message'] = "Доход успешно добавлен";
        }
        elseif ($action === 'expense') {
            $amount = (float)($_POST['new-expense'] ?? 0);
            $category = $_POST['expense-category'] ?? '';
            
            if ($amount <= 0) {
                throw new Exception("Сумма расхода должна быть больше 0");
            }
            
            if (empty($category)) {
                throw new Exception("Не выбрана категория расхода");
            }
            
            $date = new DateTime();
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, category, date, month, year) 
                                  VALUES (?, 'expense', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $amount,
                $category,
                $date->format('Y-m-d H:i:s'),
                $date->format('n') - 1,
                $date->format('Y')
            ]);
            
            $_SESSION['message'] = "Расход успешно добавлен";
        } else {
            throw new Exception("Неизвестное действие");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

header("Location: index.php");
exit();
?>