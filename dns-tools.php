<?php
require_once 'auth.php';
require_once 'config.php';

$sql = "
    SELECT id, name, url, last_ip, dns_last_checked_at, abuse_score, abuse_last_checked_at
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
                <h1 class="h3 mb-0"><strong>DNS &amp; IP tools</strong></h1>
                <button id="checkDnsNowBtn" class="btn btn-sm btn-outline-primary">
                    Check DNS &amp; IP now
                </button>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Current IP &amp; abuse score</h5>
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
                                    <th>IP address</th>
                                    <th>DNS last check</th>
                                    <th>Abuse score</th>
                                    <th>Abuse last check</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($websites as $site): ?>
                                    <tr>
                                        <td>
                                            <a href="website-detail.php?id=<?= (int)$site['id'] ?>">
                                                <?= htmlspecialchars($site['name']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank">
                                                <?= htmlspecialchars($site['url']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?= $site['last_ip'] ? htmlspecialchars($site['last_ip']) : '<span class="text-muted">Unknown</span>' ?>
                                        </td>
                                        <td>
                                            <?= $site['dns_last_checked_at'] ? htmlspecialchars($site['dns_last_checked_at']) : '<span class="text-muted">Never</span>' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $score = $site['abuse_score'] !== null ? (int)$site['abuse_score'] : null;
                                            if ($score === null): ?>
                                                <span class="badge bg-secondary">Unknown</span>
                                            <?php elseif ($score === 0): ?>
                                                <span class="badge bg-success">0</span>
                                            <?php elseif ($score <= 10): ?>
                                                <span class="badge bg-success"><?= $score ?></span>
                                            <?php elseif ($score <= 50): ?>
                                                <span class="badge bg-warning text-dark"><?= $score ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?= $score ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $site['abuse_last_checked_at'] ? htmlspecialchars($site['abuse_last_checked_at']) : '<span class="text-muted">Never</span>' ?>
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
            const btn = document.getElementById("checkDnsNowBtn");
            if (!btn) return;

            btn.addEventListener("click", function() {
                btn.disabled = true;
                const originalText = btn.textContent;
                btn.textContent = "Checking DNS & IP...";

                fetch("check-dns.php", {
                        method: "POST",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    })
                    .then(res => {
                        console.log("DNS check status:", res.status);
                        return res.json();
                    })
                    .then(data => {
                        console.log("DNS check data:", data);
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error("DNS check error:", err);
                        alert("DNS check failed. See console for details.");
                        btn.disabled = false;
                        btn.textContent = originalText;
                    });
            });
        });
    </script>

    <?php include __DIR__ . '/templates/footer.php'; ?>