<?php
require_once 'auth.php';
require_once 'config.php';

// mark as read (GET param)
if (isset($_GET['mark']) && ctype_digit($_GET['mark'])) {
    $alertId = (int)$_GET['mark'];
    $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
    $stmt->execute([$alertId]);
    header("Location: alerts.php");
    exit;
}

// mark all as read
if (isset($_GET['mark_all']) && $_GET['mark_all'] === '1') {
    $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1");
    $stmt->execute();
    header("Location: alerts.php");
    exit;
}

// dohvat alertova (zadnjih 100)
$stmt = $pdo->query("
    SELECT a.*, w.name AS website_name, w.url AS website_url
    FROM alerts a
    JOIN websites w ON a.website_id = w.id
    ORDER BY a.created_at DESC
    LIMIT 100
");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <span class="nav-link">
                        Logged in as <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Log out</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="content">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0"><strong>Alerts &amp; logs</strong></h1>
                <a href="alerts.php?mark_all=1" class="btn btn-sm btn-outline-secondary">
                    Mark all as read
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Latest alerts</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($alerts)): ?>
                        <p class="text-muted mb-0">No alerts yet.</p>
                    <?php else: ?>
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Website</th>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Message</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $a): ?>
                                    <tr class="<?= $a['is_read'] ? 'text-muted' : '' ?>">
                                        <td><?= htmlspecialchars($a['created_at']) ?></td>
                                        <td>
                                            <a href="website-detail.php?id=<?= (int)$a['website_id'] ?>">
                                                <?= htmlspecialchars($a['website_name']) ?>
                                            </a>
                                            <br>
                                            <small class="text-muted">
                                                <a href="<?= htmlspecialchars($a['website_url']) ?>" target="_blank">
                                                    <?= htmlspecialchars($a['website_url']) ?>
                                                </a>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($a['type'] === 'down'): ?>
                                                <span class="badge bg-danger">Down</span>
                                            <?php elseif ($a['type'] === 'up'): ?>
                                                <span class="badge bg-success">Up</span>
                                            <?php elseif ($a['type'] === 'slow'): ?>
                                                <span class="badge bg-warning text-dark">Slow</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($a['type']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($a['severity'] === 'critical'): ?>
                                                <span class="badge bg-danger">Critical</span>
                                            <?php elseif ($a['severity'] === 'warning'): ?>
                                                <span class="badge bg-warning text-dark">Warning</span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark">Info</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 400px;">
                                            <?= nl2br(htmlspecialchars($a['message'])) ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!$a['is_read']): ?>
                                                <a href="alerts.php?mark=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    Mark as read
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Read</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/templates/footer.php'; ?>