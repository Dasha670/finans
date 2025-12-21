<?php
session_start();
require 'config.php';

// Проверка авторизации
$isLoggedIn = isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    header("Location: index.php");
    exit();
}

$pageTitle = "История операций";
$userId = $_SESSION['user_id'];
$typeFilter = $_GET['type'] ?? '';

try {
    $sql = "
        SELECT t.*, 
               IF(t.type = 'income', i.name, e.name) as category_name
        FROM transactions t
        LEFT JOIN income_categories i ON t.type = 'income' AND t.category = i.slug
        LEFT JOIN expense_categories e ON t.type = 'expense' AND t.category = e.slug
        WHERE t.user_id = ?
    ";
    
    $params = [$userId];
    
    if ($typeFilter === 'income' || $typeFilter === 'expense') {
        $sql .= " AND t.type = ?";
        $params[] = $typeFilter;
    }
    
    $sql .= " ORDER BY t.date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История операций</title>
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="history_styles.css">
</head>
<body class="body-image-left">
<header>
    <h1><?= $pageTitle ?? 'Мои доходы и расходы' ?></h1>
    
    <div class="auth-section">
        <?php if ($isLoggedIn): ?>
            <span>Добро пожаловать, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="?logout=1" class="auth-button">Выйти</a>
        <?php else: ?>
            <button id="authButton" class="auth-button">Войти / Регистрация</button>
        <?php endif; ?>
    </div>
</header>

    <main>
        <div class="history-section">
            <a href="index.php" class="back-button">← Назад</a>
            <h2>Все операции</h2>
            
            <div class="history-filters">
                <form method="get" class="filter-form">
                    <select name="type" onchange="this.form.submit()">
                        <option value="">Все операции</option>
                        <option value="income" <?= ($_GET['type'] ?? '') === 'income' ? 'selected' : '' ?>>Доходы</option>
                        <option value="expense" <?= ($_GET['type'] ?? '') === 'expense' ? 'selected' : '' ?>>Расходы</option>
                    </select>
                </form>
            </div>

            <div class="transactions-list">
                <?php if (empty($transactions)): ?>
                    <p class="no-transactions">Нет операций для отображения</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Тип</th>
                                <th>Категория</th>
                                <th>Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="<?= $transaction['type'] === 'income' ? 'income' : 'expense' ?>">
                                    <td><?= date('d.m.Y H:i', strtotime($transaction['date'])) ?></td>
                                    <td><?= $transaction['type'] === 'income' ? 'Доход' : 'Расход' ?></td>
                                    <td><?= htmlspecialchars($transaction['category_name']) ?></td>
                                    <td class="amount"><?= number_format($transaction['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="messages.js"></script>
</body>
</html>