<?php
session_start();
require 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    header("Location: index.php");
    exit();
}

$pageTitle = "Мои задолженности";
$userId = $_SESSION['user_id'];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Обработка форм
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_debt') {
            $type = $_POST['debt_type'] ?? '';
            $amount = (float)($_POST['debt_amount'] ?? 0);
            
            if (empty($type)) {
                throw new Exception("Не выбран тип задолженности");
            }
            
            if ($amount <= 0) {
                throw new Exception("Сумма задолженности должна быть больше 0");
            }
            
            $stmt = $pdo->prepare("INSERT INTO debts (user_id, type, amount) 
                                  VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE amount = ?");
            $stmt->execute([$userId, $type, $amount, $amount]);
            
            $_SESSION['message'] = "Задолженность успешно добавлена/обновлена";
            header("Location: debts.php");
            exit();
            
        } elseif ($action === 'add_payment') {
            $debtId = (int)($_POST['debt_id'] ?? 0);
            $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
            
            if ($debtId <= 0) {
                throw new Exception("Не выбрана задолженность");
            }
            
            if ($paymentAmount <= 0) {
                throw new Exception("Сумма платежа должна быть больше 0");
            }
            
            // Начинаем транзакцию
            $pdo->beginTransaction();
            
            try {
                // Добавляем платеж
                $stmt = $pdo->prepare("INSERT INTO payments (user_id, debt_id, amount) 
                                      VALUES (?, ?, ?)");
                $stmt->execute([$userId, $debtId, $paymentAmount]);
                
                // Проверяем, погашена ли задолженность
                $debtInfo = $pdo->prepare("
                    SELECT d.amount, SUM(p.amount) as total_paid 
                    FROM debts d
                    LEFT JOIN payments p ON p.debt_id = d.id
                    WHERE d.id = ? AND d.user_id = ?
                    GROUP BY d.id
                ");
                $debtInfo->execute([$debtId, $userId]);
                $debt = $debtInfo->fetch(PDO::FETCH_ASSOC);
                
                if ($debt && ($debt['total_paid'] >= $debt['amount'])) {
                    $updateStmt = $pdo->prepare("UPDATE debts SET is_paid = 1 WHERE id = ?");
                    $updateStmt->execute([$debtId]);
                }
                
                $pdo->commit();
                $_SESSION['message'] = "Платеж успешно добавлен";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
            header("Location: debts.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: debts.php");
        exit();
    }
}

// Получение списка задолженностей и платежей
try {
    // Активные задолженности
    // Замените запрос на этот:
    $debtsQuery = $pdo->prepare("
    SELECT 
        d.id,
        d.user_id,
        d.type,
        d.amount,
        d.created_at,
        d.is_paid,
        COALESCE(SUM(p.amount), 0) as total_paid,
        GREATEST(0, d.amount - COALESCE(SUM(p.amount), 0)) as remaining,
        CASE WHEN (d.amount <= COALESCE(SUM(p.amount), 0)) THEN 1 ELSE 0 END as is_fully_paid
    FROM debts d
    LEFT JOIN payments p ON p.debt_id = d.id
    WHERE d.user_id = ?
    GROUP BY d.id, d.user_id, d.type, d.amount, d.created_at, d.is_paid
    ORDER BY is_fully_paid, d.created_at DESC
");
    $debtsQuery->execute([$userId]);
    $debts = $debtsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Список для выбора при добавлении платежа
    $activeDebtsQuery = $pdo->prepare("
        SELECT * FROM debts 
        WHERE user_id = ? AND is_paid = 0
        ORDER BY type
    ");
    $activeDebtsQuery->execute([$userId]);
    $activeDebts = $activeDebtsQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
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
    
    <h1><?= $pageTitle ?></h1>
    
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
            <h2>Добавить новую задолженность</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_debt">
                <div class="input-row">
                    <div class="input-column">
                        <label for="debt-type">Тип задолженности:</label>
                        <select id="debt-type" name="debt_type" required>
                            <option value="">-- Выберите тип --</option>
                            <option value="credit">Кредит</option>
                            <option value="mortgage">Ипотека</option>
                            <option value="microcredit">Микрозайм</option>
                            <option value="credit_card">Кредитные карты</option>
                            <option value="installment">Рассрочка</option>
                            <option value="alimony">Алименты</option>
                            <option value="taxes">Налоги</option>
                            <option value="traffic_fines">Штрафы ГИБДД</option>
                            <option value="rent">Аренда</option>
                        </select>
                    </div>
                    <div class="input-column">
                        <label for="debt-amount">Сумма задолженности:</label>
                        <input type="number" id="debt-amount" name="debt_amount" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="input-row">
                    <div class="input-column">
                        <button type="submit" class="action-button">Добавить задолженность</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="input-section">
            <h2>Добавить погашение</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_payment">
                <div class="input-row">
                    <div class="input-column">
                        <label for="payment-debt">Задолженность:</label>
                        <select id="payment-debt" name="debt_id" required>
                            <option value="">-- Выберите задолженность --</option>
                            <?php foreach ($activeDebts as $debt): ?>
                                <option value="<?= $debt['id'] ?>">
                                    <?= htmlspecialchars(getDebtTypeName($debt['type'])) ?> 
                                    (Остаток: <?= number_format($debt['amount'] - getDebtPaidAmount($pdo, $debt['id']), 2) ?> руб.)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-column">
                        <label for="payment-amount">Сумма погашения:</label>
                        <input type="number" id="payment-amount" name="payment_amount" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="input-row">
                    <div class="input-column">
                        <button type="submit" class="action-button">Добавить погашение</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($debts)): ?>
        <div class="history-section">
            <h2>Мои задолженности</h2>
            <table>
                <thead>
                    <tr>
                        <th>Дата создания</th>
                        <th>Тип</th>
                        <th>Сумма</th>
                        <th>Погашено</th>
                        <th>Остаток</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debts as $debt): ?>
                    <tr>
                        <td><?= date('d.m.Y H:i', strtotime($debt['created_at'])) ?></td>
                        <td><?= htmlspecialchars(getDebtTypeName($debt['type'])) ?></td>
                        <td><?= number_format($debt['amount'], 2) ?> руб.</td>
                        <td><?= number_format($debt['total_paid'], 2) ?> руб.</td>
                        <td>
    <?= number_format($debt['remaining'], 2) ?> руб.
</td>
<td>
    <?php if ($debt['is_fully_paid'] || $debt['remaining'] <= 0): ?>
        <span class="status-paid">Погашена</span>
    <?php else: ?>
        <span class="status-active">Активна</span>
    <?php endif; ?>
</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
    <script src="messages.js"></script>
</body>
</html>

<?php
function getDebtTypeName($type) {
    $types = [
        'credit' => 'Кредит',
        'mortgage' => 'Ипотека',
        'microcredit' => 'Микрозайм',
        'credit_card' => 'Кредитные карты',
        'installment' => 'Рассрочка',
        'alimony' => 'Алименты',
        'taxes' => 'Налоги',
        'traffic_fines' => 'Штрафы ГИБДД',
        'rent' => 'Аренда'
    ];
    return $types[$type] ?? $type;
}

function getDebtPaidAmount($pdo, $debtId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE debt_id = ?");
    $stmt->execute([$debtId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}
?>