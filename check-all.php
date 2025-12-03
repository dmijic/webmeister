<?php
// check-all.php
// Central site check script – used by CRON and by the "Check now" button in UI.

if (PHP_SAPI !== 'cli') {
    // When called from browser, require auth
    require_once 'auth.php';
}
require_once 'config.php';

$isCli = (PHP_SAPI === 'cli');

// force mode: when called from UI with ?force=1
$force = !$isCli && (
    (!empty($_GET['force']) && $_GET['force'] == '1') ||
    (!empty($_POST['force']) && $_POST['force'] == '1')
);

try {
    if ($force) {
        // Manual "Check now" from UI → check all active & verified websites
        $sql = "
            SELECT *
            FROM websites
            WHERE is_active = 1
              AND status = 'active'
        ";
        $stmt = $pdo->query($sql);
    } else {
        // CRON / normal mode → check only those that are due based on check_interval
        // koristimo zadnji zapis iz `checks`, ne stupac checked_at u `websites`
        $sql = "
            SELECT w.*, lc.checked_at AS last_checked_at
            FROM websites w
            LEFT JOIN (
                SELECT website_id, MAX(checked_at) AS checked_at
                FROM checks
                GROUP BY website_id
            ) lc ON lc.website_id = w.id
            WHERE w.is_active = 1
              AND w.status = 'active'
              AND (
                    lc.checked_at IS NULL
                 OR lc.checked_at <= DATE_SUB(NOW(), INTERVAL w.check_interval MINUTE)
              )
        ";
        $stmt = $pdo->query($sql);
    }

    $websites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if ($isCli) {
        fwrite(STDERR, "DB error: " . $e->getMessage() . PHP_EOL);
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Database error: ' . $e->getMessage(),
        ]);
        exit;
    }
}

$results = [];

if (!empty($websites)) {
    // INSERT into checks – websites tablicu više ne diramo
    $insertCheck = $pdo->prepare("
        INSERT INTO checks (
            website_id,
            checked_at,
            status_code,
            ok,
            response_time_ms,
            ip_address,
            error_message
        )
        VALUES (
            :website_id,
            :checked_at,
            :status_code,
            :ok,
            :response_time_ms,
            :ip_address,
            :error_message
        )
    ");

    foreach ($websites as $site) {
        $id  = (int)$site['id'];
        $url = $site['url'];

        $checkedAt = date('Y-m-d H:i:s');
        $statusCode = null;
        $ok         = 0;
        $rtMs       = null;
        $ip         = null;
        $errorMsg   = null;

        // Basic cURL check
        $ch = curl_init();

        // Ako URL nema shemu, default na https
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            $url = 'https://' . $url;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_NOBODY         => false,   // ako želiš samo HEAD, stavi true
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'WebMonitor/1.0 (+https://example.com)',
        ]);

        $start = microtime(true);
        $body  = curl_exec($ch);
        $end   = microtime(true);

        if ($body === false) {
            $errorMsg  = curl_error($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        } else {
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ip         = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
            $ok         = ($statusCode >= 200 && $statusCode < 400) ? 1 : 0;
            $rtMs       = (int)round(($end - $start) * 1000);
        }

        curl_close($ch);

        // Upis u checks
        $insertCheck->execute([
            ':website_id'       => $id,
            ':checked_at'       => $checkedAt,
            ':status_code'      => $statusCode,
            ':ok'               => $ok,
            ':response_time_ms' => $rtMs,
            ':ip_address'       => $ip,
            ':error_message'    => $errorMsg,
        ]);

        $results[] = [
            'id'          => $id,
            'name'        => $site['name'],
            'url'         => $url,
            'ok'          => $ok,
            'status_code' => $statusCode,
            'response_ms' => $rtMs,
            'ip_address'  => $ip,
            'error'       => $errorMsg,
            'checked_at'  => $checkedAt,
        ];
    }
}

// OUTPUT

if ($isCli) {
    // For CRON / CLI usage → simple text output
    echo "Checked " . count($results) . " websites.\n";
    foreach ($results as $r) {
        echo "- [{$r['id']}] {$r['url']} => status {$r['status_code']}, "
            . ($r['ok'] ? 'OK' : 'FAIL')
            . ", {$r['response_ms']} ms\n";
    }
    exit;
}

// For HTTP / AJAX
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'force'   => $force,
    'count'   => count($results),
    'results' => $results,
]);
