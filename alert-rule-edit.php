<?php
require_once 'auth.php';
require_once 'config.php';

// Svi aktivni webovi za checkbox listu
$sitesStmt = $pdo->query("
    SELECT id, name, url
    FROM websites
    WHERE status = 'active' AND is_active = 1
    ORDER BY name ASC
");
$allSites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);

// tip konteksta: alert ili report (za novi rule)
$typeContext = $_GET['type'] ?? null;

// default mode ovisno o kontekstu
$defaultMode = 'alerts';
if ($typeContext === 'report') {
    $defaultMode = 'reports';
}

// Default vrijednosti
$rule = [
    'id'            => null,
    'name'          => '',
    'emails'        => '',
    'mode'          => $defaultMode,
    'frequency'     => 'immediate',
    'report_period' => '24h',
    'alert_types'   => [],
    'severities'    => [],
    'include_ok'    => 1,
    'is_active'     => 1
];

$selectedSites = [];
$error = null;

// EDIT MODE – ako postoji ?id, prepiše default vrijednosti
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM alert_rules WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $rule['id']            = $row['id'];
        $rule['name']          = $row['name'];
        $rule['emails']        = $row['emails'];
        $rule['mode']          = $row['mode'] ?? $defaultMode;
        $rule['frequency']     = $row['frequency'] ?? 'immediate';
        $rule['report_period'] = $row['report_period'] ?? '24h';
        $rule['alert_types']   = $row['alert_types'] ? explode(',', $row['alert_types']) : [];
        $rule['severities']    = $row['severities'] ? explode(',', $row['severities']) : [];
        $rule['include_ok']    = (int)($row['include_ok'] ?? 1);
        $rule['is_active']     = (int)$row['is_active'];

        // websites za ovo pravilo
        $wstmt = $pdo->prepare("
            SELECT website_id 
            FROM alert_rule_websites 
            WHERE rule_id = ?
        ");
        $wstmt->execute([$id]);
        $selectedSites = array_map('intval', array_column($wstmt->fetchAll(PDO::FETCH_ASSOC), 'website_id'));
    }
}

// Obrada forme (create / update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name       = trim($_POST['name'] ?? '');
    $emails     = trim($_POST['emails'] ?? '');
    $mode       = $_POST['mode'] ?? $defaultMode;
    $frequency  = $_POST['frequency'] ?? 'immediate';
    $reportPeriod = $_POST['report_period'] ?? '24h';
    $types      = $_POST['alert_types'] ?? [];
    $severities = $_POST['severities'] ?? [];
    $includeOk  = isset($_POST['include_ok']) ? 1 : 0;
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $sites      = $_POST['websites'] ?? [];

    $typesStr = !empty($types) ? implode(',', $types) : null;
    $sevStr   = !empty($severities) ? implode(',', $severities) : null;
    $now      = date('Y-m-d H:i:s');

    if ($name === '' || $emails === '') {
        $error = "Name and emails are required.";
    } else {
        // imamo li postojeće pravilo?
        if (!empty($rule['id'])) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE alert_rules
                SET name = ?, emails = ?, mode = ?, frequency = ?, report_period = ?,
                    alert_types = ?, severities = ?, include_ok = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                $emails,
                $mode,
                $frequency,
                $reportPeriod,
                $typesStr,
                $sevStr,
                $includeOk,
                $isActive,
                $rule['id']
            ]);
            $ruleId = (int)$rule['id'];

            // pobriši stara povezivanja webova
            $pdo->prepare("DELETE FROM alert_rule_websites WHERE rule_id = ?")
                ->execute([$ruleId]);
        } else {
            // INSERT
            $stmt = $pdo->prepare("
                INSERT INTO alert_rules 
                    (name, emails, mode, frequency, report_period, alert_types, severities, include_ok, created_at, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $emails,
                $mode,
                $frequency,
                $reportPeriod,
                $typesStr,
                $sevStr,
                $includeOk,
                $now,
                $isActive
            ]);
            $ruleId = (int)$pdo->lastInsertId();
        }

        // upiši nova povezivanja s webovima
        if (!empty($sites)) {
            $ws = $pdo->prepare("INSERT INTO alert_rule_websites (rule_id, website_id) VALUES (?, ?)");
            foreach ($sites as $sid) {
                $ws->execute([$ruleId, (int)$sid]);
            }
        }

        // redirect natrag na odgovarajući listing
        if ($mode === 'reports') {
            header("Location: report-rules.php");
        } elseif ($mode === 'alerts') {
            header("Location: alert-rules.php");
        } else {
            // both – možeš odvesti gdje više voliš, ja ću na alert-rules
            header("Location: alert-rules.php");
        }
        exit;
    }

    // Ako ima error, ažuriraj $rule i $selectedSites da forma ostane popunjena
    $rule['name']          = $name;
    $rule['emails']        = $emails;
    $rule['mode']          = $mode;
    $rule['frequency']     = $frequency;
    $rule['report_period'] = $reportPeriod;
    $rule['alert_types']   = $types;
    $rule['severities']    = $severities;
    $rule['include_ok']    = $includeOk;
    $rule['is_active']     = $isActive;
    $selectedSites         = array_map('intval', $sites);
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
                    <a class="nav-link" href="<?= ($rule['mode'] === 'reports' ? 'report-rules.php' : 'alert-rules.php') ?>">
                        &larr; Back to <?= $rule['mode'] === 'reports' ? 'Report rules' : 'Alert rules' ?>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3">
                <?php if ($rule['id']): ?>
                    Edit rule
                <?php else: ?>
                    <?= $typeContext === 'report' ? 'Create report rule' : 'Create alert rule' ?>
                <?php endif; ?>
            </h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">

                <!-- NAME -->
                <div class="mb-3">
                    <label class="form-label">Rule name</label>
                    <input type="text" name="name" class="form-control"
                        value="<?= htmlspecialchars($rule['name']) ?>" required>
                </div>

                <!-- EMAILS -->
                <div class="mb-3">
                    <label class="form-label">Emails (comma separated)</label>
                    <textarea name="emails" rows="2" class="form-control" required><?= htmlspecialchars($rule['emails']) ?></textarea>
                    <div class="form-text">Example: admin@example.com, support@example.com</div>
                </div>

                <!-- MODE -->
                <div class="mb-3">
                    <label class="form-label">Mode</label>
                    <select name="mode" class="form-select">
                        <option value="alerts" <?= $rule['mode'] === 'alerts'  ? 'selected' : '' ?>>Alerts only</option>
                        <option value="reports" <?= $rule['mode'] === 'reports' ? 'selected' : '' ?>>Reports only</option>
                        <option value="both" <?= $rule['mode'] === 'both'    ? 'selected' : '' ?>>Alerts + reports</option>
                    </select>
                    <div class="form-text">
                        Alerts = šalje kad se dogodi event. Reports = periodični PDF izvještaj.
                    </div>
                </div>

                <!-- FREQUENCY -->
                <div class="mb-3">
                    <label class="form-label">Send frequency</label>
                    <select name="frequency" class="form-select">
                        <?php foreach (['immediate', 'hourly', 'daily', 'weekly'] as $f): ?>
                            <option value="<?= $f ?>" <?= $rule['frequency'] === $f ? 'selected' : '' ?>>
                                <?= ucfirst($f) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Koristi se i za alerts (npr. grupiranje) i za reports (npr. dnevni/tjedni).
                    </div>
                </div>

                <!-- REPORT PERIOD -->
                <div class="mb-3">
                    <label class="form-label">Report period (for PDF)</label>
                    <select name="report_period" class="form-select">
                        <option value="24h" <?= $rule['report_period'] === '24h' ? 'selected' : '' ?>>Last 24 hours</option>
                        <option value="7d" <?= $rule['report_period'] === '7d'  ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30d" <?= $rule['report_period'] === '30d' ? 'selected' : '' ?>>Last 30 days</option>
                    </select>
                    <div class="form-text">
                        Koristi se kad je mode = Reports ili Alerts + Reports.
                    </div>
                </div>

                <!-- ALERT TYPES -->
                <div class="mb-3">
                    <label class="form-label">Trigger on event types</label>
                    <?php
                    $typesList = ['down', 'slow', 'ssl_expiry', 'dns_change', 'ip_abuse'];
                    foreach ($typesList as $t):
                    ?>
                        <div class="form-check">
                            <input type="checkbox"
                                class="form-check-input"
                                name="alert_types[]"
                                value="<?= $t ?>"
                                <?= in_array($t, $rule['alert_types']) ? 'checked' : '' ?>>
                            <label class="form-check-label"><?= strtoupper($t) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-text">Ostavi prazno za sve tipove.</div>
                </div>

                <!-- SEVERITIES -->
                <div class="mb-3">
                    <label class="form-label">Trigger on severities</label>
                    <?php
                    $sevList = ['critical', 'warning', 'info'];
                    foreach ($sevList as $s):
                    ?>
                        <div class="form-check">
                            <input type="checkbox"
                                class="form-check-input"
                                name="severities[]"
                                value="<?= $s ?>"
                                <?= in_array($s, $rule['severities']) ? 'checked' : '' ?>>
                            <label class="form-check-label"><?= ucfirst($s) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-text">Ostavi prazno za sve severities.</div>
                </div>

                <!-- INCLUDE OK -->
                <div class="mb-3 form-check">
                    <input type="checkbox" name="include_ok" class="form-check-input"
                        <?= $rule['include_ok'] ? 'checked' : '' ?>>
                    <label class="form-check-label">
                        Include OK status in reports (always send PDF even if no incidents)
                    </label>
                </div>

                <!-- WEBSITES -->
                <div class="mb-3">
                    <label class="form-label">Websites for this rule</label>
                    <div class="border p-2 rounded" style="max-height:300px;overflow:auto;">
                        <?php foreach ($allSites as $s): ?>
                            <div class="form-check">
                                <input type="checkbox"
                                    class="form-check-input"
                                    name="websites[]"
                                    value="<?= (int)$s['id'] ?>"
                                    <?= in_array((int)$s['id'], $selectedSites) ? 'checked' : '' ?>>
                                <label class="form-check-label">
                                    <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['url']) ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">Odaberi webove koji pripadaju ovom pravilu.</div>
                </div>

                <!-- ACTIVE -->
                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_active" class="form-check-input"
                        <?= $rule['is_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label">Rule active</label>
                </div>

                <!-- BUTTONS -->
                <div class="text-end">
                    <a href="<?= ($rule['mode'] === 'reports' ? 'report-rules.php' : 'alert-rules.php') ?>"
                        class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?= $rule['id'] ? "Save changes" : "Create rule" ?>
                    </button>
                </div>

            </form>

        </div>
    </main>

    <?php include __DIR__ . '/templates/footer.php'; ?>