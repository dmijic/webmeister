<?php
require_once 'auth.php';
require_once 'config.php';

// 1) Osnovne brojke o webovima
$totalStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM websites");
$totalWebsites = (int)$totalStmt->fetchColumn();

$statusStmt = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM websites
    GROUP BY status
");
$activeCount  = 0;
$pendingCount = 0;
$otherCount   = 0;
foreach ($statusStmt as $row) {
    if ($row['status'] === 'active') {
        $activeCount = (int)$row['cnt'];
    } elseif ($row['status'] === 'pending') {
        $pendingCount = (int)$row['cnt'];
    } else {
        $otherCount += (int)$row['cnt'];
    }
}

// 2) Online / offline (zadnji check po svakoj aktivnoj stranici)
$onlineCount  = 0;
$offlineCount = 0;

$sqlLastCheckPerSite = "
    SELECT w.id, w.name, c.ok
    FROM websites w
    LEFT JOIN (
        SELECT c1.*
        FROM checks c1
        JOIN (
            SELECT website_id, MAX(checked_at) AS max_checked_at
            FROM checks
            GROUP BY website_id
        ) latest
          ON latest.website_id = c1.website_id
         AND latest.max_checked_at = c1.checked_at
    ) c ON c.website_id = w.id
    WHERE w.status = 'active'
";
$stmtLast = $pdo->query($sqlLastCheckPerSite);
foreach ($stmtLast as $row) {
    if ($row['ok'] === null) {
        continue; // bez podataka
    }
    if ((int)$row['ok'] === 1) {
        $onlineCount++;
    } else {
        $offlineCount++;
    }
}

// 3) Prosječan response time zadnjih 24h
$avgStmt = $pdo->query("
    SELECT AVG(response_time_ms) AS avg_rt
    FROM checks
    WHERE checked_at >= (NOW() - INTERVAL 1 DAY)
      AND response_time_ms IS NOT NULL
");
$avgRt = (int)($avgStmt->fetchColumn() ?? 0);

// 4) Uptime zadnjih 24h (svi checkovi)
$uptimePercent24 = 0;
$uptimeStmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS ok_count,
        COUNT(*) AS total_count
    FROM checks
    WHERE checked_at >= (NOW() - INTERVAL 1 DAY)
");
$uptRow = $uptimeStmt->fetch(PDO::FETCH_ASSOC);
if ($uptRow && (int)$uptRow['total_count'] > 0) {
    $uptimePercent24 = round(
        ((int)$uptRow['ok_count'] / (int)$uptRow['total_count']) * 100,
        1
    );
}

// 5) Podaci za graf – zadnjih 7 dana, prosječni response time i uptime
$labels7   = [];
$avgRt7    = [];
$uptime7   = [];

$stats7Stmt = $pdo->query("
    SELECT 
        DATE(checked_at) AS day,
        AVG(response_time_ms) AS avg_rt,
        SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS ok_count,
        COUNT(*) AS total_count
    FROM checks
    WHERE checked_at >= (CURDATE() - INTERVAL 6 DAY)
    GROUP BY DATE(checked_at)
    ORDER BY day ASC
");

while ($r = $stats7Stmt->fetch(PDO::FETCH_ASSOC)) {
    $labels7[] = $r['day']; // npr. 2025-12-03
    $avgRt7[]  = $r['avg_rt'] !== null ? round((float)$r['avg_rt'], 0) : null;

    if ((int)$r['total_count'] > 0) {
        $uptime7[] = round(((int)$r['ok_count'] / (int)$r['total_count']) * 100, 1);
    } else {
        $uptime7[] = null;
    }
}

// 6) Zadnjih 10 checkova za tablicu
$latestChecksStmt = $pdo->query("
    SELECT 
        c.checked_at,
        c.status_code,
        c.ok,
        c.response_time_ms,
        c.ip_address,
        c.error_message,
        w.name AS website_name,
        w.url  AS website_url
    FROM checks c
    JOIN websites w ON w.id = c.website_id
    ORDER BY c.checked_at DESC
    LIMIT 10
");
$latestChecks = $latestChecksStmt->fetchAll(PDO::FETCH_ASSOC);

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

            <h1 class="h3 mb-3">
                <strong>Monitoring</strong> Dashboard
            </h1>

            <!-- Gornji KPI cards -->
            <div class="row">
                <!-- Total websites -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col mt-0">
                                    <h5 class="card-title">Total websites</h5>
                                </div>
                                <div class="col-auto">
                                    <div class="stat text-primary">
                                        <i class="align-middle" data-feather="globe"></i>
                                    </div>
                                </div>
                            </div>
                            <h1 class="mt-1 mb-3"><?= $totalWebsites ?></h1>
                            <div class="mb-0">
                                <span class="text-success me-2">Active: <?= $activeCount ?></span>
                                <span class="text-warning me-2">Pending: <?= $pendingCount ?></span>
                                <?php if ($otherCount > 0): ?>
                                    <span class="text-muted">Other: <?= $otherCount ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Online / Offline -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col mt-0">
                                    <h5 class="card-title">Status (now)</h5>
                                </div>
                                <div class="col-auto">
                                    <div class="stat text-primary">
                                        <i class="align-middle" data-feather="activity"></i>
                                    </div>
                                </div>
                            </div>
                            <h1 class="mt-1 mb-3">
                                <?= $onlineCount ?>/<?= $activeCount ?>
                            </h1>
                            <div class="mb-0">
                                <span class="text-success me-2">Online: <?= $onlineCount ?></span>
                                <span class="text-danger me-2">Offline: <?= $offlineCount ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Avg response (24h) -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col mt-0">
                                    <h5 class="card-title">Avg response (24h)</h5>
                                </div>
                                <div class="col-auto">
                                    <div class="stat text-primary">
                                        <i class="align-middle" data-feather="clock"></i>
                                    </div>
                                </div>
                            </div>
                            <h1 class="mt-1 mb-3"><?= $avgRt ?> ms</h1>
                            <div class="mb-0">
                                <span class="text-muted">
                                    Based on all checks in the last 24 hours.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Uptime (24h) -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col mt-0">
                                    <h5 class="card-title">Uptime (24h)</h5>
                                </div>
                                <div class="col-auto">
                                    <div class="stat text-primary">
                                        <i class="align-middle" data-feather="check-circle"></i>
                                    </div>
                                </div>
                            </div>
                            <h1 class="mt-1 mb-3">
                                <?= $uptimePercent24 ?>%
                            </h1>
                            <div class="mb-0">
                                <span class="text-muted">All monitored checks in the last 24 hours.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafovi -->
            <div class="row">
                <!-- Line chart: avg response + uptime 7d -->
                <div class="col-xl-8 col-xxl-9 d-flex">
                    <div class="card flex-fill w-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Performance (last 7 days)</h5>
                        </div>
                        <div class="card-body py-3">
                            <div class="chart chart-sm" style="height: 260px;">
                                <canvas id="chart-7d-response"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie chart: current status -->
                <div class="col-xl-4 col-xxl-3 d-flex">
                    <div class="card flex-fill w-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Status overview</h5>
                        </div>
                        <div class="card-body d-flex">
                            <div class="align-self-center w-100">
                                <div class="py-3">
                                    <div class="chart chart-xs">
                                        <canvas id="chart-status-pie"></canvas>
                                    </div>
                                </div>
                                <table class="table mb-0">
                                    <tbody>
                                        <tr>
                                            <td>Online</td>
                                            <td class="text-end"><?= $onlineCount ?></td>
                                        </tr>
                                        <tr>
                                            <td>Offline</td>
                                            <td class="text-end"><?= $offlineCount ?></td>
                                        </tr>
                                        <tr>
                                            <td>No data</td>
                                            <td class="text-end">
                                                <?= max(0, $activeCount - ($onlineCount + $offlineCount)) ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <small class="text-muted d-block mt-2">
                                    Based on the latest check for each active website.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zadnjih 10 checkova -->
            <div class="row">
                <div class="col-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Latest checks</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($latestChecks)): ?>
                                <p class="text-muted mb-0">No checks recorded yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover my-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Website</th>
                                                <th>Status code</th>
                                                <th>OK</th>
                                                <th>Response time</th>
                                                <th>IP</th>
                                                <th>Error</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($latestChecks as $c): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($c['checked_at']) ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($c['website_name']) ?></strong><br>
                                                        <a href="<?= htmlspecialchars($c['website_url']) ?>" target="_blank">
                                                            <small><?= htmlspecialchars($c['website_url']) ?></small>
                                                        </a>
                                                    </td>
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
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($c['error_message'] ?? '') ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Podaci iz PHP-a
            const labels7 = <?= json_encode($labels7) ?>;
            const avgRt7 = <?= json_encode($avgRt7) ?>;
            const uptime7 = <?= json_encode($uptime7) ?>;

            // Line chart: avg response + uptime % po danu
            const canvasLine = document.getElementById("chart-7d-response");
            if (canvasLine && labels7.length > 0) {
                const ctx = canvasLine.getContext("2d");

                new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: labels7,
                        datasets: [{
                                label: "Avg response (ms)",
                                data: avgRt7,
                                borderColor: window.theme.primary,
                                backgroundColor: "rgba(0,0,0,0)",
                                tension: 0.2,
                                yAxisID: 'y'
                            },
                            {
                                label: "Uptime (%)",
                                data: uptime7,
                                borderColor: window.theme.success,
                                backgroundColor: "rgba(0,0,0,0)",
                                borderDash: [4, 4],
                                tension: 0.2,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: true
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: false
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: "Response time (ms)"
                                },
                                beginAtZero: true
                            },
                            y1: {
                                position: 'right',
                                title: {
                                    display: true,
                                    text: "Uptime (%)"
                                },
                                min: 0,
                                max: 100,
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            }

            // Pie / doughnut chart: status overview
            const statusData = {
                online: <?= (int)$onlineCount ?>,
                offline: <?= (int)$offlineCount ?>,
                noData: <?= max(0, $activeCount - ($onlineCount + $offlineCount)) ?>
            };

            const canvasPie = document.getElementById("chart-status-pie");
            if (canvasPie) {
                const ctxPie = canvasPie.getContext("2d");

                new Chart(ctxPie, {
                    type: "doughnut",
                    data: {
                        labels: ["Online", "Offline", "No data"],
                        datasets: [{
                            data: [
                                statusData.online,
                                statusData.offline,
                                statusData.noData
                            ],
                            backgroundColor: [
                                window.theme.success,
                                window.theme.danger,
                                window.theme.warning
                            ],
                            borderWidth: 5
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: "70%",
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        });
    </script>

    <?php include __DIR__ . '/templates/footer.php'; ?>