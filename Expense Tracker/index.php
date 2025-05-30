<?php
session_start();

// database conection.
$pdo = new PDO('mysql:host=localhost;dbname=family', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function userExists(PDO $pdo, string $username): bool {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return (bool) $stmt->fetch();
}

function getUserByUsername(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getUserExpenses(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY year DESC, month DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addExpense(PDO $pdo, int $userId, string $item, float $price, int $month, int $year): void {
    $stmt = $pdo->prepare("INSERT INTO expenses (user_id, item, price, month, year) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $item, $price, $month, $year]);
}

function deleteExpense(PDO $pdo, int $expenseId, int $userId): void {
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->execute([$expenseId, $userId]);
}

function updateExpense (PDO $pdo, int $expenseId, int $userId, string $item, float $price, int $month, int $year): void {
    $stmt = $pdo->prepare("UPDATE expenses SET item = ?, price = ?, month = ?, year = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$item, $price, $month, $year, $expenseId, $userId]);
}

// logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $reg_username = trim($_POST['reg_username']);
    $reg_password = $_POST['reg_password'];

    if (strlen($reg_username) < 3 || strlen($reg_password) < 5) {
        $register_error = "Username must be at least 3 characters and password 5+ characters.";
    } elseif (userExists($pdo, $reg_username)) {
        $register_error = "This username already exists";
    } else {
        $hashedPassword = password_hash($reg_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$reg_username, $hashedPassword]);
        $register_success = "Registration successful. You can now log in.";
    }
}

// login.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $user = getUserByUsername($pdo, $username);

    if (!$user) {
        $login_error = "User does not exist.";
    } elseif (!password_verify($password, $user['password'])) {
        $login_error = "Incorrect password.";
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: index.php');
        exit();
    }
}

//  add's expense
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $item = trim($_POST['item']);
    $price = floatval($_POST['price']);
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);

    if ($month < 1 || $month > 12) {
        $expense_error = "Please enter a valid month (1-12).";
    } elseif ($price <= 0) {
        $expense_error = "Please enter a valid price.";
    } else {
        addExpense($pdo, $_SESSION['user_id'], $item, $price, $month, $year);
        $expense_success = "Expense added successfully.";
    }
}

//  Delete expense
if (isset($_SESSION['user_id']) && isset($_GET['delete'])) {
    deleteExpense($pdo, intval($_GET['delete']), $_SESSION['user_id']);
    header("Location: index.php");
    exit();
}

// edit expense.
if (isset($_SESSION['user_id']) && isset($_POST['edit_expense_submit'])) {
    $edit_id = intval($_POST['edit_id']);
    $item = trim($_POST['edit_item']);
    $price = floatval($_POST['edit_price']);
    $month = intval($_POST['edit_month']);
    $year = intval($_POST['edit_year']);

    updateExpense($pdo, $edit_id, $_SESSION['user_id'], $item, $price, $month, $year);
    $edit_success = "Expense updated successfully.";
}

// Fetch expenses.
$expenses = [];
if (isset($_SESSION['user_id'])) {
    $expenses = getUserExpenses($pdo, $_SESSION['user_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Tracker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: white;
            color: black;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: whitesmoke;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(87, 79, 79, 0.1);
        }
        h2, h3 {
            color: gray;
        }
        form {
            margin-bottom: 25px;
        }
        input[type="text"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background: blue;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: skyblue;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th {
            background-color: white;
            text-align: left;
            padding: 15px;
        }
        td {
            padding: 10px;
        }
        .logout {
            float: right;
            text-decoration: none;
            color: red;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .error {
            background-color: rgb(246, 213, 216);
            color: red;
        }
        .success {
            background-color: white;
            color: green;
        }
    </style>
    <script>
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this expense?")) {
                window.location.href = "?delete=" + id;
            }
        }
        function showEditForm(id, item, price, month, year) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_item').value = item;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_month').value = month;
            document.getElementById('edit_year').value = year;
            document.getElementById('edit-form').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</head>
<body>
<div class="container">
<?php if (!isset($_SESSION['user_id'])): ?>
    <h2>Login</h2>
    <?php if (isset($login_error)) echo "<div class='message error'>$login_error</div>"; ?>
    <?php if (isset($register_success)) echo "<div class='message success'>$register_success</div>"; ?>
    <form method="POST" action="index.php">
        <label>Username:</label>
        <input type="text" name="username" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <button type="submit" name="login">Login</button>
    </form>

    <h2>Register</h2>
    <?php if (isset($register_error)) echo "<div class='message error'>$register_error</div>"; ?>
    <form method="POST" action="index.php">
        <label>Username:</label>
        <input type="text" name="reg_username" required>
        <label>Password:</label>
        <input type="password" name="reg_password" required>
        <button type="submit" name="register">Register</button>
    </form>

<?php else: ?>
    <h2>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
    <a class="logout" href="?logout=true">Logout</a>

    <h3>Add Expense</h3>
    <?php if (isset($expense_error)) echo "<div class='message error'>$expense_error</div>"; ?>
    <?php if (isset($expense_success)) echo "<div class='message success'>$expense_success</div>"; ?>
    <form method="POST" action="index.php">
        <label>Item</label>
        <input type="text" name="item" required>
        <label>Price</label>
        <input type="number" step="0.01" name="price" required>
        <label>Month (1-12)</label>
        <input type="number" name="month" min="1" max="12" required>
        <label>Year</label>
        <input type="number" name="year" required>
        <button type="submit" name="add_expense">Add Expense</button>
    </form>

    <h3>Edit Expense</h3>
    <?php if (isset($edit_success)) echo "<div class='message success'>$edit_success</div>"; ?>
    <form method="POST" id="edit-form" action="index.php">
        <input type="hidden" name="edit_id" id="edit_id">
        <label>Item</label>
        <input type="text" name="edit_item" id="edit_item" required>
        <label>Price</label>
        <input type="number" step="0.01" name="edit_price" id="edit_price" required>
        <label>Month (1-12)</label>
        <input type="number" name="edit_month" id="edit_month" min="1" max="12" required>
        <label>Year</label>
        <input type="number" name="edit_year" id="edit_year" required>
        <button type="submit" name="edit_expense_submit">Update Expense</button>
    </form>

    <h3>Your Expenses</h3>
    <table>
        <thead>
        <tr>
            <th>Item</th>
            <th>Price</th>
            <th>Month</th>
            <th>Year</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $expense): ?>
            <tr>
                <td><?= htmlspecialchars($expense['item']) ?></td>
                <td>R<?= number_format($expense['price'], 2) ?></td>
                <td><?= htmlspecialchars($expense['month']) ?></td>
                <td><?= htmlspecialchars($expense['year']) ?></td>
                <td>
                    <button onclick="showEditForm(<?= $expense['id'] ?>, '<?= htmlspecialchars($expense['item'], ENT_QUOTES) ?>', <?= $expense['price'] ?>, <?= $expense['month'] ?>, <?= $expense['year'] ?>)">Edit</button>
                    <button onclick="confirmDelete(<?= $expense['id'] ?>)">Delete</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
</body>
</html>