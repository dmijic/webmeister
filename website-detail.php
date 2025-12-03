<?php
// website-detail.php
require_once 'auth.php';
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid website id.");
}

// 1) website info
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
$stmt->execute([$id]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$website) {
    die("Website not found.");
}

// 2) zadnjih 100 checkova
$stmt = $pdo->prepare("
    SELECT checked_at, status_code, ok, response_time_ms, ip_address, error_message
    FROM checks
    WHERE website_id = ?
    ORDER BY checked_at DESC
    LIMIT 100
");
$stmt->execute([$id]);
$checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// za graf: uzlazno po vremenu
$checksForChart = array_reverse($checks);

$labels = [];
$responseTimes = [];

foreach ($checksForChart as $c) {
    $labels[]        = $c['checked_at'];
    $responseTimes[] = $c['response_time_ms'] !== null ? (int)$c['response_time_ms'] : null;
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
                <li class="nav-item">
                    <span class="nav-link">
                        Logged in as <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong>
                    </span>
                </li>
            </ul>
        </div>
    </nav>

    <main class="content">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">
                    <strong><?= htmlspecialchars($website['name']) ?></strong>
                    <small class="text-muted" style="font-size: 0.8em;">
                        (<?= htmlspecialchars($website['url']) ?>)
                    </small>
                </h1>
                <a href="website-edit.php?id=<?= (int)$website['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    Edit website
                </a>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Summary</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>URL:</strong>
                                <a href="<?= htmlspecialchars($website['url']) ?>" target="_blank">
                                    <?= htmlspecialchars($website['url']) ?>
                                </a>
                            </p>
                            <p><strong>Status:</strong>
                                <?php $status = $website['status'] ?? null; ?>
                                <?php if ($status === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif ($status === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending verification</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($status ?? 'unknown') ?></span>
                                <?php endif; ?>
                            </p>

                            <!-- NEW: check interval info -->
                            <p>
                                <strong>Check interval:</strong>
                                <?php
                                $interval = isset($website['check_interval'])
                                    ? (int)$website['check_interval']
                                    : 5;
                                ?>
                                Every <?= $interval ?> minute<?= $interval > 1 ? 's' : '' ?>
                            </p>
                            <!-- END NEW -->

                            <?php if (!empty($checks)):
                                $last = $checks[0];
                                $isOk = (int)$last['ok'] === 1;
                            ?>
                                <p><strong>Last status:</strong>
                                    <?php if ($isOk): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($last['status_code'] ?? 'OK') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= htmlspecialchars($last['status_code'] ?? 'DOWN') ?></span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Last check:</strong> <?= htmlspecialchars($last['checked_at']) ?></p>
                                <?php if ($last['ip_address']): ?>
                                    <p><strong>IP:</strong> <?= htmlspecialchars($last['ip_address']) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No checks yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Response time (last checks)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart chart-sm">
                                <canvas id="chart-response-time"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- tablica checkova -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Last checks</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Status</th>
                                <th>OK</th>
                                <th>Response time</th>
                                <th>IP</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checks as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['checked_at']) ?></td>
                                    <td><?= htmlspecialchars($c['status_code'] ?? '') ?></td>
                                    <td>
                                        <?php if ((int)$c['ok'] === 1): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">FAIL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['response_time_ms'] !== null): ?>
                                            <?= (int)$c['response_time_ms'] ?> ms
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($c['ip_address'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['error_message'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const labels = <?= json_encode($labels) ?>;
            const responseTimes = <?= json_encode($responseTimes) ?>;
            const canvas = document.getElementById("chart-response-time");
            if (!canvas) return;

            const ctx = canvas.getContext("2d");

            new Chart(ctx, {
                type: "line",
                data: {
                    labels: labels,
                    datasets: [{
                        label: "Response time (ms)",
                        data: responseTimes,
                        fill: false,
                        borderColor: window.theme.primary,
                        tension: 0.1,
                        pointRadius: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    scales: {
                        x: {
                            title: {
                                display: false
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: "ms"
                            }
                        }
                    }
                }
            });
        });
    </script>

    <?php include __DIR__ . '/templates/footer.php'; ?>