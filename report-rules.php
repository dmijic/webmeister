<?php
require_once 'auth.php';
require_once 'config.php';

// Samo pravila koja uključuju reports
$stmt = $pdo->query("
    SELECT * 
    FROM alert_rules
    WHERE mode IN ('reports','both')
    ORDER BY created_at DESC
");
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Za prikaz broja webova po pravilu
$countWebsitesStmt = $pdo->prepare("
    SELECT COUNT(*) FROM alert_rule_websites WHERE rule_id = ?
");

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
                <h1 class="h3 mb-0"><strong>Report rules</strong></h1>
                <a href="alert-rule-edit.php?type=report" class="btn btn-sm btn-primary">
                    Create report rule
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Configured report rules</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($rules)): ?>
                        <p class="text-muted mb-0">No report rules yet.</p>
                    <?php else: ?>
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Emails</th>
                                    <th>Mode</th>
                                    <th>Frequency</th>
                                    <th>Report period</th>
                                    <th>Include OK</th>
                                    <th>Websites</th>
                                    <th>Active</th>
                                    <th>Last run</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rules as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['name']) ?></td>
                                        <td><?= htmlspecialchars($r['emails']) ?></td>
                                        <td><?= htmlspecialchars($r['mode']) ?></td>
                                        <td><?= htmlspecialchars($r['frequency']) ?></td>
                                        <td><?= htmlspecialchars($r['report_period'] ?? '24h') ?></td>
                                        <td>
                                            <?= (int)$r['include_ok']
                                                ? '<span class="badge bg-success">Yes</span>'
                                                : '<span class="badge bg-secondary">No</span>' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $countWebsitesStmt->execute([$r['id']]);
                                            $wCount = (int)$countWebsitesStmt->fetchColumn();
                                            echo $wCount;
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($r['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $r['last_run_at']
                                                ? htmlspecialchars($r['last_run_at'])
                                                : '<span class="text-muted">Never</span>' ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="alert-rule-edit.php?id=<?= (int)$r['id'] ?>"
                                                class="btn btn-sm btn-outline-secondary">
                                                Edit
                                            </a>
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