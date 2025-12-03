<?php
// install-admin.php (Step 3)
session_start();

if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/config.php';

// ako već postoji barem jedan user → preskačemo i idemo na login/dashboard
$check = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($check > 0) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = false;
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $confirm === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {

            // bcrypt hash
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, created_at)
                VALUES (?, ?, ?, 'admin', NOW())
            ");
            $stmt->execute([$username, $email, $hash]);

            $success = true;

            // auto login admin
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'admin';

            header("Refresh: 1; URL=dashboard.php");
        } catch (PDOException $e) {
            $error = "Error creating admin: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Installation – Create Admin</title>
    <link rel="stylesheet" href="public/css/app.css">
    <style>
        body {
            background-color: #eef1f6;
            font-family: system-ui, sans-serif;
        }

        .wrap {
            max-width: 450px;
            margin: 70px auto;
            background: #fff;
            padding: 28px 32px;
            border-radius: 12px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
        }

        .wrap h1 {
            font-size: 1.6rem;
            margin-bottom: 6px;
        }

        .wrap p {
            color: #666;
            margin-bottom: 20px;
        }

        .form-control {
            margin-bottom: 14px;
            border-radius: 8px;
        }

        .btn {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
        }
    </style>
</head>

<body>

    <div class="wrap">
        <h1>Administrator Setup</h1>
        <p>Create the first administrator account for Web Monitor.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Admin created! Redirecting to dashboard...
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="post">

                <input type="text"
                    name="username"
                    class="form-control"
                    placeholder="Username"
                    required
                    value="<?= htmlspecialchars($username) ?>">

                <input type="email"
                    name="email"
                    class="form-control"
                    placeholder="Email"
                    required
                    value="<?= htmlspecialchars($email) ?>">

                <input type="password"
                    name="password"
                    class="form-control"
                    placeholder="Password (min. 6 chars)"
                    required>

                <input type="password"
                    name="confirm"
                    class="form-control"
                    placeholder="Repeat password"
                    required>

                <button type="submit" class="btn btn-primary">
                    Create admin and continue
                </button>
            </form>
        <?php endif; ?>

    </div>

</body>

</html>