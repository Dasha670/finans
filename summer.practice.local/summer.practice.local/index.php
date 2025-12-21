<?php
session_start();
require 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? $_SESSION['user_id'] : null;

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n') - 1;
$period = $_GET['period'] ?? 'month';
$year = date('Y');

if ($isLoggedIn) {
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'user_id'")->fetch();
        
        if (!$checkColumn) {
            throw new Exception("Столбец user_id не существует в таблице transactions");
        }

        if ($period === 'month') {
            $income = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'income' AND month = ? AND year = ?");
            $income->execute([$currentUserId, $month, $year]);
            $total_income = $income->fetchColumn() ?: 0;

            $expense = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense' AND month = ? AND year = ?");
            $expense->execute([$currentUserId, $month, $year]);
            $total_expense = $expense->fetchColumn() ?: 0;
        } else {
            $income = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'income' AND year = ?");
            $income->execute([$currentUserId, $year]);
            $total_income = $income->fetchColumn() ?: 0;

            $expense = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense' AND year = ?");
            $expense->execute([$currentUserId, $year]);
            $total_expense = $expense->fetchColumn() ?: 0;
        }

        $savings = $pdo->prepare("
    SELECT 
        (SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'income') - 
        (SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'expense')
");
        $savings->execute([$currentUserId, $currentUserId]);
        $current_savings = $savings->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        die("Ошибка базы данных: " . $e->getMessage());
    } catch (Exception $e) {
        die($e->getMessage());
    }
} else {
    $total_income = 0;
    $total_expense = 0;
    $current_savings = 0;
}

$days = $period === 'month' ? date('t') : 365;
$avg_income = $total_income / $days;
$avg_expense = $total_expense / $days;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои доходы и расходы</title>
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

        <?php if (!$isLoggedIn): ?>
            <div class="message">Пожалуйста, войдите или зарегистрируйтесь для работы с приложением</div>
        <?php else: ?>
            <form method="post" action="process.php" class="input-section">
                <input type="hidden" name="action" id="action" value="">
                <div class="input-row">
                    <div class="input-column">
                        <label for="income-category">Категория дохода</label>
                        <select id="income-category" name="income-category">
                            <?php
                            $stmt = $pdo->query("SELECT * FROM income_categories ORDER BY 
                            CASE 
                                WHEN slug = 'salary' THEN 0
                                WHEN slug = 'freelance' THEN 1
                                WHEN slug = 'investments' THEN 2
                                ELSE 3
                            END");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="'.$row['slug'].'">'.$row['name'].'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="input-column">
                        <label for="expense-category">Категория расхода</label>
                        <select id="expense-category" name="expense-category">
                            <?php
                            $stmt = $pdo->query("SELECT * FROM expense_categories");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="'.$row['slug'].'">'.$row['name'].'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="input-row">
                    <div class="input-column">
                        <label for="new-income">Новый доход</label>
                        <input type="number" id="new-income" name="new-income" placeholder="Сумма" step="0.01" min="0" required>
                    </div>
                    <div class="input-column">
                        <label for="new-expense">Новая трата</label>
                        <input type="number" id="new-expense" name="new-expense" placeholder="Сумма" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="input-row">
                    <div class="input-column">
                        <button type="button" onclick="submitForm('income')" class="action-button">Добавить доход</button>
                    </div>
                    <div class="input-column">
                        <button type="button" onclick="submitForm('expense')" class="action-button">Добавить трату</button>
                    </div>
                </div>

                <div class="savings">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label for="current-savings">Текущие накопления</label>
                        <a href="history.php" class="history-link">Посмотреть историю</a>
                    </div>
                <input type="text" id="current-savings" name="current-savings" readonly value="<?= number_format($current_savings, 2) ?>">
                </div>
            </form>

            <div class="divider"></div>

            <div class="stats-section">
                <h2>Статистика</h2>
                <form method="get" action="" id="stats-form">
                    <div class="dropdowns">
                        <select id="month-select" name="month">
                            <?php
                            $months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 
                                      'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
                            foreach ($months as $i => $name) {
                                echo '<option value="'.$i.'"'.($i == $month ? ' selected' : '').'>'.$name.'</option>';
                            }
                            ?>
                        </select>
                        <select id="period-select" name="period">
                            <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>За месяц</option>
                            <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>За год</option>
                        </select>
                    </div>
                </form>

                <div class="stats-row">
                    <div class="stats-column">
                        <label for="total-income">Суммарный доход</label>
                        <input type="text" id="total-income" name="total-income" readonly value="<?= number_format($total_income, 2) ?>">
                    </div>
                    <div class="stats-column">
                        <label for="total-expenses">Суммарные траты</label>
                        <input type="text" id="total-expenses" name="total-expenses" readonly value="<?= number_format($total_expense, 2) ?>">
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stats-column">
                        <label for="average-income">Средний доход</label>
                        <input type="text" id="average-income" name="average-income" readonly value="<?= number_format($avg_income, 2) ?>">
                    </div>
                    <div class="stats-column">
                        <label for="average-expenses">Средние траты</label>
                        <input type="text" id="average-expenses" name="average-expenses" readonly value="<?= number_format($avg_expense, 2) ?>">
                    </div>
                </div>
                <div class="charts-section">
                    <div class="charts-row">
                        <div class="chart-container">
                            <canvas id="incomeChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Автоматическое скрытие сообщений
    setTimeout(() => {
        const messages = document.querySelectorAll('.message-popup, .error-popup');
        messages.forEach(msg => {
            if (msg) {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }
        });
    }, 5000);
    
    <?php if ($isLoggedIn): ?>
        initCharts();
    <?php endif; ?>

    function submitForm(type) {
        const form = document.querySelector('form.input-section');
        document.getElementById('action').value = type;
        
        const amountField = type === 'income' ? 'new-income' : 'new-expense';
        const amountInput = document.getElementById(amountField);
        const amount = parseFloat(amountInput.value);
        
        if (isNaN(amount) || amount <= 0) {
            alert('Пожалуйста, введите корректную сумму (больше 0)');
            amountInput.focus();
            return false;
        }
        
        form.submit();
        return true;
    }

    // Назначаем обработчики кнопкам
    document.querySelector('button[onclick="submitForm(\'income\')"]')?.addEventListener('click', function() {
        submitForm('income');
    });
    
    document.querySelector('button[onclick="submitForm(\'expense\')"]')?.addEventListener('click', function() {
        submitForm('expense');
    });

    document.getElementById('month-select')?.addEventListener('change', function() {
        document.getElementById('stats-form').submit();
    });

    document.getElementById('period-select')?.addEventListener('change', function() {
        document.getElementById('stats-form').submit();
    });

    function initCharts() {
    const incomeCtx = document.getElementById('incomeChart').getContext('2d');
    new Chart(incomeCtx, {
        type: 'pie',
        data: {
            labels: ['Зарплата', 'Фриланс', 'Инвестиции', 'Другое'],
            datasets: [{
                data: [
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'income' AND category = 'salary'")->fetchColumn() ?: 0 ?>,
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'income' AND category = 'freelance'")->fetchColumn() ?: 0 ?>,
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'income' AND category = 'investments'")->fetchColumn() ?: 0 ?>,
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'income' AND category = 'other'")->fetchColumn() ?: 0 ?>
                ],
                backgroundColor: ['#36a2eb', '#ffce56', '#4bc0c0', '#9966ff'],
                borderWidth: 1, // Уменьшена толщина обводки
                borderColor: 'rgba(255, 255, 255, 0.5)' // Полупрозрачная белая обводка
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: { 
                    display: true, 
                    text: 'Доходы по категориям',
                    font: {
                        size: 16
                    }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10
                    }
                }
            },
            radius: '90%' // Увеличивает область графика
        }
    });

    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    new Chart(expenseCtx, {
        type: 'pie',
        data: {
            labels: ['Еда', 'Транспорт', 'Коммунальные', 'Развлечения', 'Другое'],
            datasets: [{
                data: [
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'expense' AND category = 'food'")->fetchColumn() ?: 0 ?>,
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'expense' AND category = 'transport'")->fetchColumn() ?: 0 ?>,
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'expense' AND category = 'utilities'")->fetchColumn() ?: 0 ?>,
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'expense' AND category = 'entertainment'")->fetchColumn() ?: 0 ?>,
                    <?= $pdo->query("SELECT SUM(amount) FROM transactions WHERE user_id = $currentUserId AND type = 'expense' AND category = 'other'")->fetchColumn() ?: 0 ?>
                ],
                backgroundColor: ['#ff6384', '#ff9f40', '#8ac24a', '#607d8b', '#e91e63'],
                borderWidth: 1, // Уменьшена толщина обводки
                borderColor: 'rgba(255, 255, 255, 0.5)' // Полупрозрачная белая обводка
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: { 
                    display: true, 
                    text: 'Расходы по категориям',
                    font: {
                        size: 16
                    }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 5
                    }
                }
            },
            radius: '90%' // Увеличивает область графика
        }
    });
    }
});
</script>
<script src="messages.js"></script>
</body>
</html>