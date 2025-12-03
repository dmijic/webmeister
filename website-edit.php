<?php
// website-edit.php
require_once 'auth.php';
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

// default values
$name = '';
$url  = '';
$check_interval_minutes = 5; // default 5 min

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
    $stmt->execute([$id]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        die("Website not found.");
    }

    $name = $site['name'];
    $url  = $site['url'];

    // iz baze čitamo stupac check_interval
    $check_interval_minutes = isset($site['check_interval'])
        ? (int)$site['check_interval']
        : 5;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $url  = trim($_POST['url'] ?? '');
    $check_interval_minutes = isset($_POST['check_interval_minutes'])
        ? max(1, (int)$_POST['check_interval_minutes'])
        : 5;

    if ($name === '' || $url === '') {
        $error = "Name and URL are required.";
    } else {
        if ($editing) {
            $stmt = $pdo->prepare("
                UPDATE websites 
                SET name = ?, url = ?, check_interval = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                $url,
                $check_interval_minutes, // zapisujemo u check_interval
                $id
            ]);
            $success = "Website updated.";
        } else {
            // new site → pending + token NULL do prvog "Download verification"
            $stmt = $pdo->prepare("
                INSERT INTO websites (name, url, check_interval, is_active, status, created_at)
                VALUES (?, ?, ?, 1, 'pending', NOW())
            ");
            $stmt->execute([
                $name,
                $url,
                $check_interval_minutes // zapisujemo u check_interval
            ]);

            $newId = (int)$pdo->lastInsertId();
            header("Location: websites.php?created=1");
            exit;
        }
    }
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>

<div class="main">
    <nav class="navbar navbar-expand navbar-light navbar-bg">
        <a class="sidebar-toggle js-sidebar-toggle">
            <i class="hamburger align-self-center"></i>
        </a>
        <div class="navbar-collapse collapse">
            <ul class="navbar-nav navbar-align">
                <li class="nav-item">
                    <a class="nav-link" href="websites.php">&larr; Back to All websites</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="content">
        <div class="container-fluid p-0">
            <h1 class="h3 mb-3">
                <?= $editing ? 'Edit website' : 'Add new website' ?>
            </h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required
                                value="<?= htmlspecialchars($name) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">URL</label>
                            <input type="url" name="url" class="form-control" required
                                placeholder="https://example.com"
                                value="<?= htmlspecialchars($url) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Check interval</label>
                            <select name="check_interval_minutes" class="form-select" required>
                                <?php
                                $intervals = [1, 5, 10, 15, 30, 60];
                                foreach ($intervals as $i):
                                ?>
                                    <option value="<?= $i ?>"
                                        <?= (int)$check_interval_minutes === $i ? 'selected' : '' ?>>
                                        Every <?= $i ?> minute<?= $i > 1 ? 's' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                How often do you want to ping this website?
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?= $editing ? 'Save changes' : 'Create website (pending)' ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($editing && isset($site['status']) && $site['status'] === 'pending'): ?>
                <div class="alert alert-info mt-3">
                    This website is <strong>pending verification</strong>.<br>
                    Use <em>Download token</em> on the All websites screen to verify ownership.
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include __DIR__ . '/templates/footer.php'; ?>