<?php
require_once "db.php";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (strlen($username) < 3) $errors[] = "Username must be at least 3 characters.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $password_confirm) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        // Check duplicate email or username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
        $stmt->execute(['email' => $email, 'username' => $username]);

        if ($stmt->fetch()) {
            $errors[] = "Email or Username already exists.";
        } else {
            // Insert user into database
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $password_hash
            ]);

            header("Location: index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Page background and centering */
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        /* Container box */
        .container {
            background: #fff;
            padding: 30px 25px;
            border-radius: 12px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
            width: 360px;
            max-width: 90%;
            box-sizing: border-box;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        /* Input fields */
        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }

        /* Register button */
        button {
            width: 100%;
            padding: 12px;
            background: #2575fc;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 5px;
        }

        button:hover {
            background: #1e63d7;
        }

        /* Error message */
        .error {
            background-color: #ffe0e0;
            color: #d8000c;
            padding: 10px;
            border: 1px solid #ffb3b3;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        /* Login link */
        .link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .link a {
            color: #2575fc;
            text-decoration: none;
            font-weight: bold;
        }

        .link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Create Account</h2>

        <?php if ($errors): ?>
            <div class="error">
                <?php foreach ($errors as $err) echo htmlspecialchars($err) . "<br>"; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="password_confirm" placeholder="Confirm Password" required>
            <button type="submit">Register</button>
        </form>
        <div class="link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</body>

</html>