<?php
// install-db.php - Step 2: create database tables

if (!file_exists(__DIR__ . '/config.php')) {
    // bez configa nema smisla raditi tablice
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/config.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // sve SQL naredbe u jednom nizu
        $sqlStatements = [

            // USERS
            "
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(255) DEFAULT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'admin',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",

            // WEBSITES
            "
            CREATE TABLE IF NOT EXISTS websites (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                url VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                check_interval INT NOT NULL DEFAULT 5,
                verification_token VARCHAR(64) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",

            // CHECKS
            "
            CREATE TABLE IF NOT EXISTS checks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                website_id INT UNSIGNED NOT NULL,
                checked_at DATETIME NOT NULL,
                status_code INT DEFAULT NULL,
                ok TINYINT(1) NOT NULL DEFAULT 0,
                response_time_ms INT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                INDEX idx_checks_site_time (website_id, checked_at),
                CONSTRAINT fk_checks_website
                    FOREIGN KEY (website_id) REFERENCES websites(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",

            // ALERTS
            "
            CREATE TABLE IF NOT EXISTS alerts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                channel_email TINYINT(1) NOT NULL DEFAULT 1,
                channel_slack TINYINT(1) NOT NULL DEFAULT 0,
                channel_discord TINYINT(1) NOT NULL DEFAULT 0,
                email_recipients TEXT DEFAULT NULL,
                slack_webhook_url TEXT DEFAULT NULL,
                discord_webhook_url TEXT DEFAULT NULL,
                frequency_minutes INT NOT NULL DEFAULT 60,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",

            // ALERT_WEBSITES (pivot)
            "
            CREATE TABLE IF NOT EXISTS alert_websites (
                alert_id INT UNSIGNED NOT NULL,
                website_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (alert_id, website_id),
                CONSTRAINT fk_aw_alert
                    FOREIGN KEY (alert_id) REFERENCES alerts(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_aw_site
                    FOREIGN KEY (website_id) REFERENCES websites(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",

            // REPORTS (periodic email/PDF configs)
            "
            CREATE TABLE IF NOT EXISTS reports (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                period VARCHAR(20) NOT NULL DEFAULT 'daily', -- daily, weekly, monthly
                email_recipients TEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_run_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",

            // REPORT_WEBSITES (pivot)
            "
            CREATE TABLE IF NOT EXISTS report_websites (
                report_id INT UNSIGNED NOT NULL,
                website_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (report_id, website_id),
                CONSTRAINT fk_rw_report
                    FOREIGN KEY (report_id) REFERENCES reports(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_rw_site
                    FOREIGN KEY (website_id) REFERENCES websites(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
        ];

        foreach ($sqlStatements as $sql) {
            $pdo->exec($sql);
        }

        $success = true;

        // nakon 1 sekunde vodi na kreiranje admin usera (sljedeći korak)
        header("Refresh: 1; URL=install-admin.php");
    } catch (PDOException $e) {
        $error = "Error while creating tables: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Web Monitor - Installation (Step 2)</title>
    <link rel="stylesheet" href="public/css/app.css">
    <style>
        body {
            background-color: #f4f6f9;
        }

        .install-wrapper {
            max-width: 560px;
            margin: 60px auto;
            background: #fff;
            border-radius: 12px;
            padding: 24px 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .install-title {
            margin-bottom: 10px;
        }

        .install-subtitle {
            margin-bottom: 20px;
            color: #666;
        }

        .form-text {
            font-size: 0.85rem;
            color: #777;
        }

        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="install-wrapper">
        <h1 class="install-title">Web Monitor – Installation</h1>
        <p class="install-subtitle">
            Step 2: Create database tables.
        </p>

        <p>
            In this step the installer will create all required tables:
        </p>
        <ul>
            <li><code>users</code></li>
            <li><code>websites</code></li>
            <li><code>checks</code></li>
            <li><code>alerts</code> &amp; <code>alert_websites</code></li>
            <li><code>reports</code> &amp; <code>report_websites</code></li>
        </ul>

        <?php if ($error): ?>
            <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success mt-3">
                Tables created successfully. Redirecting to admin user setup...
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="post" class="mt-3">
                <p class="form-text mb-3">
                    If the database is empty, it is safe to run this step.
                    If tables already exist, the installer will not overwrite them
                    (it uses <code>CREATE TABLE IF NOT EXISTS</code>).
                </p>
                <button type="submit" class="btn btn-primary">
                    Create tables
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>