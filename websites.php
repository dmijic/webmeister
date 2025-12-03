<?php
// websites.php
require_once 'auth.php';
require_once 'config.php';

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';

// Uzmemo sve webove + zadnji check za svaki (lijevi join sa subqueryjem)
$sql = "
    SELECT w.id, w.name, w.url, w.is_active, w.status,
           c.status_code, c.ok, c.checked_at, c.response_time_ms
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
    ORDER BY w.name ASC
";

$stmt = $pdo->query($sql);
$websites = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <h1 class="h3 mb-0"><strong>All websites</strong></h1>

                <div class="btns-container">
                    <button id="checkNowBtn" class="btn btn-primary">
                        Check now
                    </button>

                    <a href="website-edit.php" class="btn btn-success">
                        + Add new website
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Monitored websites</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>URL</th>
                                <th>Last status</th>
                                <th>Last check</th>
                                <th>Response time</th>
                                <th>Active</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($websites as $site):
                                $isOk = isset($site['ok']) ? (int)$site['ok'] === 1 : null;
                            ?>
                                <tr data-site-id="<?= (int)$site['id'] ?>">
                                    <td><?= htmlspecialchars($site['name']) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank">
                                            <?= htmlspecialchars($site['url']) ?>
                                        </a>
                                    </td>

                                    <td class="status-cell">
                                        <?php if ($isOk === null): ?>
                                            <span class="badge bg-secondary">No data</span>
                                        <?php elseif ($isOk): ?>
                                            <span class="badge bg-success">
                                                <?= htmlspecialchars($site['status_code'] ?? 'OK') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <?= htmlspecialchars($site['status_code'] ?? 'DOWN') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="checked-at-cell">
                                        <?php if (!empty($site['checked_at'])): ?>
                                            <?= htmlspecialchars($site['checked_at']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="response-time-cell">
                                        <?php if (!empty($site['response_time_ms'])): ?>
                                            <?= (int)$site['response_time_ms'] ?> ms
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($site['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($site['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending verification</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($site['status']) ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end">
                                        <a href="website-detail.php?id=<?= (int)$site['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                        <a href="website-edit.php?id=<?= (int)$site['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            Edit
                                        </a>

                                        <?php if ($site['status'] === 'pending'): ?>
                                            <a href="download-verification.php?id=<?= (int)$site['id'] ?>" class="btn btn-sm btn-outline-info">
                                                Download token
                                            </a>
                                            <a href="verify-website.php?id=<?= (int)$site['id'] ?>" class="btn btn-sm btn-outline-success">
                                                Verify
                                            </a>
                                        <?php endif; ?>
                                    </td>

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
            const btn = document.getElementById("checkNowBtn");
            if (!btn) return;

            const COOLDOWN_SECONDS = 60;
            let cooldownTimer = null;
            let isRunning = false;

            function setButtonState(disabled, label) {
                btn.disabled = disabled;
                btn.textContent = label;
            }

            function startCooldown() {
                isRunning = true;
                let remaining = COOLDOWN_SECONDS;

                setButtonState(true, "Checking...");

                // nakon kratkog delay-a prebacimo na countdown
                setTimeout(() => {
                    setButtonState(true, `Next check in ${remaining}s`);

                    cooldownTimer = setInterval(() => {
                        remaining--;
                        if (remaining <= 0) {
                            clearInterval(cooldownTimer);
                            cooldownTimer = null;
                            isRunning = false;
                            setButtonState(false, "Check now");
                        } else {
                            setButtonState(true, `Next check in ${remaining}s`);
                        }
                    }, 1000);
                }, 300);
            }

            function showLoadingForAll() {
                document.querySelectorAll(".status-cell").forEach(td => {
                    td.innerHTML = `
                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                        <span class="ms-1">Checking...</span>
                    `;
                });
            }

            function updateRow(result) {
                const row = document.querySelector(`tr[data-site-id="${result.id}"]`);
                if (!row) return;

                const statusCell = row.querySelector(".status-cell");
                const checkedAtCell = row.querySelector(".checked-at-cell");
                const rtCell = row.querySelector(".response-time-cell");

                if (statusCell) {
                    if (result.ok) {
                        statusCell.innerHTML = `
                            <span class="badge bg-success">${result.status_code ?? 'OK'}</span>
                        `;
                    } else {
                        statusCell.innerHTML = `
                            <span class="badge bg-danger">${result.status_code ?? 'DOWN'}</span>
                        `;
                    }
                }

                if (checkedAtCell && result.checked_at) {
                    checkedAtCell.textContent = result.checked_at;
                }

                if (rtCell) {
                    if (result.response_ms !== null && result.response_ms !== undefined) {
                        rtCell.textContent = `${result.response_ms} ms`;
                    } else {
                        rtCell.innerHTML = '<span class="text-muted">–</span>';
                    }
                }
            }

            btn.addEventListener("click", function() {
                if (isRunning) return;

                startCooldown();
                showLoadingForAll();

                fetch("check-all.php?force=1", {
                        method: "POST",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    })
                    .then(res => {
                        console.log("fetch status:", res.status);
                        if (!res.ok) {
                            throw new Error("HTTP status " + res.status);
                        }
                        return res.json();
                    })
                    .then(data => {
                        console.log("fetch data:", data);
                        if (data && Array.isArray(data.results)) {
                            data.results.forEach(updateRow);
                        } else {
                            console.error("Unexpected response", data);
                        }
                    })
                    .catch(err => {
                        console.error("Fetch error:", err);
                        // u slučaju greške vrati gumb u normalno stanje
                        if (cooldownTimer) {
                            clearInterval(cooldownTimer);
                            cooldownTimer = null;
                        }
                        isRunning = false;
                        setButtonState(false, "Check now");
                    });
            });
        });
    </script>

    <?php include __DIR__ . '/templates/footer.php'; ?>