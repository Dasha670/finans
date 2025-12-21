<?php
session_start();
require 'config.php';

// Проверка авторизации
$isLoggedIn = isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    header("Location: index.php");
    exit();
}

$pageTitle = "Мои накопления";
$userId = $_SESSION['user_id'];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Получаем активную цель накоплений
try {
    $goalQuery = $pdo->prepare("
        SELECT id, target_amount, current_amount, is_completed 
        FROM savings_goals 
        WHERE user_id = ? AND is_completed = 0
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $goalQuery->execute([$userId]);
    $activeGoal = $goalQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'set_goal') {
            $targetAmount = (float)$_POST['target_amount'];
            
            if ($targetAmount <= 0) {
                throw new Exception("Цель должна быть больше 0");
            }
            
            // Создаем новую цель
            $stmt = $pdo->prepare("
                INSERT INTO savings_goals (user_id, target_amount)
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $targetAmount]);
            
            $_SESSION['message'] = "Новая цель накоплений установлена";
            header("Location: savings.php");
            exit();
            
        } elseif ($action === 'add_deposit') {
            if (!$activeGoal) {
                throw new Exception("Сначала установите цель накоплений");
            }
            
            $depositAmount = (float)$_POST['deposit_amount'];
            
            if ($depositAmount <= 0) {
                throw new Exception("Сумма пополнения должна быть больше 0");
            }
            
            // Обновляем текущую цель
            $newAmount = $activeGoal['current_amount'] + $depositAmount;
            $isCompleted = ($newAmount >= $activeGoal['target_amount']) ? 1 : 0;
            
            $updateStmt = $pdo->prepare("
                UPDATE savings_goals 
                SET current_amount = ?, 
                    is_completed = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $newAmount,
                $isCompleted,
                $activeGoal['id']
            ]);
            
            // Добавляем транзакцию в историю накоплений
            $transactionStmt = $pdo->prepare("
                INSERT INTO savings_transactions (user_id, goal_id, amount)
                VALUES (?, ?, ?)
            ");
            $transactionStmt->execute([$userId, $activeGoal['id'], $depositAmount]);
            
            $_SESSION['message'] = "Пополнение успешно добавлено" . 
                ($isCompleted ? ". Цель достигнута!" : "");
            header("Location: savings.php");
            exit();
        } elseif ($action === 'complete_goal') {
            if (!$activeGoal) {
                throw new Exception("Нет активной цели для завершения");
            }
            
            $completeStmt = $pdo->prepare("
                UPDATE savings_goals 
                SET is_completed = 1 
                WHERE id = ?
            ");
            $completeStmt->execute([$activeGoal['id']]);
            
            $_SESSION['message'] = "Цель накоплений завершена";
            header("Location: savings.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: savings.php");
        exit();
    }
}

// Получаем историю накоплений
try {
    $historyQuery = $pdo->prepare("
        SELECT st.amount, st.date, sg.target_amount
        FROM savings_transactions st
        JOIN savings_goals sg ON st.goal_id = sg.id
        WHERE st.user_id = ?
        ORDER BY st.date DESC
        LIMIT 10
    ");
    $historyQuery->execute([$userId]);
    $history = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои накопления</title>
    <link rel="stylesheet" href="styles.css">
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


    <!-- Модальное окно авторизации -->
    <div id="authModal" class="modal" style="display: <?= $isLoggedIn ? 'none' : 'flex' ?>;">
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
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-popup"><?= $_SESSION['message'] ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-popup"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="input-section">
            <h2>Моя цель</h2>
            
            <?php if ($activeGoal): ?>
                <div class="goal-info">
                    <div class="goal-row">
                        <span class="goal-label">Цель:</span>
                        <span class="goal-value"><?= number_format($activeGoal['target_amount'], 2) ?> руб.</span>
                    </div>
                    <div class="goal-row">
                        <span class="goal-label">Накоплено:</span>
                        <span class="goal-value"><?= number_format($activeGoal['current_amount'], 2) ?> руб.</span>
                    </div>
                    <div class="goal-row">
                        <span class="goal-label">Осталось:</span>
                        <span class="goal-value"><?= number_format($activeGoal['target_amount'] - $activeGoal['current_amount'], 2) ?> руб.</span>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= min(100, ($activeGoal['current_amount'] / $activeGoal['target_amount']) * 100) ?>%"></div>
                    </div>
                    <div class="progress-text">
                        <?= round(min(100, ($activeGoal['current_amount'] / $activeGoal['target_amount']) * 100)) ?>%
                    </div>
                </div>
                
                <form method="post" class="deposit-form">
                    <input type="hidden" name="action" value="add_deposit">
                    <div class="deposit-row">
                        <div class="deposit-input">
                            <label for="deposit_amount">Сумма пополнения:</label>
                            <input type="number" id="deposit_amount" name="deposit_amount" step="0.01" min="0.01" required>
                        </div>
                        <div class="deposit-button">
                            <button type="submit" class="action-button">Пополнить</button>
                        </div>
                    </div>
                </form>
                
                <form method="post" class="complete-form">
                    <input type="hidden" name="action" value="complete_goal">
                    <button type="submit" class="action-button secondary">Завершить цель</button>
                </form>
            <?php else: ?>
                <p class="no-goal">У вас нет активной цели накоплений</p>
                
                <form method="post" class="set-goal-form">
                    <input type="hidden" name="action" value="set_goal">
                    <div class="input-row">
                        <div class="input-column">
                            <label for="target_amount">Новая цель:</label>
                            <select id="goal-select" onchange="updateGoalInput()">
                                <option value="">-- Выберите сумму --</option>
                                <option value="500">500 руб.</option>
                                <option value="1000">1,000 руб.</option>
                                <option value="5000">5,000 руб.</option>
                                <option value="10000">10,000 руб.</option>
                                <option value="50000">50,000 руб.</option>
                                <option value="100000">100,000 руб.</option>
                                <option value="custom">Другая сумма</option>
                            </select>
                            <input type="number" id="target_amount" name="target_amount" step="0.01" min="0.01" required style="margin-top: 10px;">
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-column">
                            <button type="submit" class="action-button">Установить цель</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($history)): ?>
        <div class="history-section">
            <h2>История пополнений</h2>
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Сумма</th>
                        <th>Цель</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                    <tr>
                        <td><?= date('d.m.Y H:i', strtotime($item['date'])) ?></td>
                        <td><?= number_format($item['amount'], 2) ?> руб.</td>
                        <td><?= number_format($item['target_amount'], 2) ?> руб.</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>

    <script>
        function updateGoalInput() {
            const select = document.getElementById('goal-select');
            const input = document.getElementById('target_amount');
            
            if (select.value === 'custom') {
                input.value = '';
                input.style.display = 'block';
            } else if (select.value) {
                input.value = select.value;
                input.style.display = 'none';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateGoalInput();
        });
    </script>
<script src="messages.js"></script>
</body>
</html>