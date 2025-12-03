<?php
// ssl-monitor.php
require_once 'auth.php';
require_once 'config.php';

// Dohvati sve active webove (možemo filtrirati samo https)
$sql = "
    SELECT id, name, url, ssl_expires_at, ssl_last_checked_at
    FROM websites
    WHERE status = 'active' AND is_active = 1
    ORDER BY name ASC
";
$stmt = $pdo->query($sql);
$websites = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <h1 class="h3 mb-0"><strong>SSL monitor</strong></h1>
                <button id="checkSslNowBtn" class="btn btn-sm btn-outline-primary">
                    Check SSL now
                </button>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">SSL status for active websites</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($websites)): ?>
                        <p class="text-muted mb-0">No websites found.</p>
                    <?php else: ?>
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>URL</th>
                                    <th>SSL expiry</th>
                                    <th>Days left</th>
                                    <th>Last SSL check</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($websites as $site): ?>
                                    <?php
                                    $url = $site['url'];
                                    $scheme = parse_url($url, PHP_URL_SCHEME);
                                    $isHttps = strtolower((string)$scheme) === 'https';

                                    $expiresAt = null;
                                    $daysLeft = null;
                                    $expired = null;

                                    if ($site['ssl_expires_at']) {
                                        try {
                                            $expiresAt = new DateTime($site['ssl_expires_at']);
                                            $now = new DateTime();
                                            $diff = $now->diff($expiresAt);
                                            $daysLeft = $expiresAt >= $now ? $diff->days : -$diff->days;
                                            $expired = $expiresAt < $now;
                                        } catch (Exception $e) {
                                            $expiresAt = null;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="website-detail.php?id=<?= (int)$site['id'] ?>">
                                                <?= htmlspecialchars($site['name']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                                                <?= htmlspecialchars($url) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($expiresAt): ?>
                                                <?= htmlspecialchars($expiresAt->format('Y-m-d')) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($daysLeft !== null): ?>
                                                <?= $daysLeft ?>
                                            <?php else: ?>
                                                <span class="text-muted">–</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($site['ssl_last_checked_at']): ?>
                                                <?= htmlspecialchars($site['ssl_last_checked_at']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$isHttps): ?>
                                                <span class="badge bg-secondary">Non-HTTPS</span>
                                            <?php elseif (!$expiresAt): ?>
                                                <span class="badge bg-secondary">Not checked</span>
                                            <?php else: ?>
                                                <?php if ($expired): ?>
                                                    <span class="badge bg-danger">
                                                        Expired <?= abs($daysLeft) ?> d ago
                                                    </span>
                                                <?php elseif ($daysLeft <= 7): ?>
                                                    <span class="badge bg-danger">
                                                        Expires in <?= $daysLeft ?> d
                                                    </span>
                                                <?php elseif ($daysLeft <= 30): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        Expires in <?= $daysLeft ?> d
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        Expires in <?= $daysLeft ?> d
                                                    </span>
                                                <?php endif; ?>
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

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const btn = document.getElementById("checkSslNowBtn");
            if (!btn) return;

            btn.addEventListener("click", function() {
                btn.disabled = true;
                const originalText = btn.textContent;
                btn.textContent = "Checking SSL...";

                fetch("check-ssl.php", {
                        method: "POST",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    })
                    .then(res => {
                        console.log("SSL check status:", res.status);
                        return res.json();
                    })
                    .then(data => {
                        console.log("SSL check data:", data);
                        // nakon provjere jednostavno refresha stranicu da povuče nove datume
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error("SSL check error:", err);
                        alert("SSL check failed. See console for details.");
                        btn.disabled = false;
                        btn.textContent = originalText;
                    });
            });
        });
    </script>

    <?php include __DIR__ . '/templates/footer.php'; ?>