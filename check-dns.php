<?php
// check-dns.php
require_once 'auth.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit;
}

// Dohvati sve ACTIVE siteove
$stmt = $pdo->query("
    SELECT id, name, url, last_ip, abuse_score
    FROM websites
    WHERE status = 'active' AND is_active = 1
");
$websites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];

// update u websites
$updateDns = $pdo->prepare("
    UPDATE websites
    SET last_ip = :last_ip,
        dns_last_checked_at = NOW(),
        abuse_score = :abuse_score,
        abuse_last_checked_at = CASE WHEN :abuse_score IS NULL THEN abuse_last_checked_at ELSE NOW() END
    WHERE id = :id
");

// alerts
$insertAlert = $pdo->prepare("
    INSERT INTO alerts (website_id, created_at, type, severity, message, is_read)
    VALUES (:website_id, NOW(), :type, :severity, :message, 0)
");

foreach ($websites as $site) {
    $id        = (int)$site['id'];
    $name      = $site['name'];
    $url       = $site['url'];
    $prevIp    = $site['last_ip'] ?? null;
    $prevScore = $site['abuse_score'] !== null ? (int)$site['abuse_score'] : null;

    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host   = parse_url($url, PHP_URL_HOST);

    if (!$host) {
        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'dns_error',
            ':severity'   => 'warning',
            ':message'    => sprintf('Cannot parse host from URL "%s" (%s).', $name, $url),
        ]);

        $results[] = [
            'website_id' => $id,
            'url'        => $url,
            'checked'    => false,
            'reason'     => 'no_host',
        ];
        continue;
    }

    // --- 1) DNS resolve IP ---
    $ip = null;

    // A record
    $records = @dns_get_record($host, DNS_A);
    if (!empty($records) && isset($records[0]['ip'])) {
        $ip = $records[0]['ip'];
    } else {
        // fallback
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            $ip = null;
        }
    }

    if (!$ip) {
        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'dns_error',
            ':severity'   => 'warning',
            ':message'    => sprintf('DNS lookup failed for "%s" (%s).', $name, $host),
        ]);

        $updateDns->execute([
            ':last_ip'      => null,
            ':abuse_score'  => null,
            ':id'           => $id,
        ]);

        $results[] = [
            'website_id' => $id,
            'url'        => $url,
            'checked'    => false,
            'reason'     => 'dns_fail',
        ];
        continue;
    }

    // --- 2) IP promjena → alert ---
    if (!empty($prevIp) && $prevIp !== $ip) {
        $msg = sprintf(
            'DNS change for "%s": %s → %s',
            $name,
            $prevIp,
            $ip
        );
        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'dns_change',
            ':severity'   => 'warning',
            ':message'    => $msg,
        ]);
    }

    // --- 3) AbuseIPDB check (ako imamo API key) ---
    $abuseScore = null;
    $abuseError = null;

    if (defined('ABUSEIPDB_API_KEY') && ABUSEIPDB_API_KEY) {
        $queryUrl = 'https://api.abuseipdb.com/api/v2/check?ipAddress='
            . urlencode($ip)
            . '&maxAgeInDays=90';

        $ch = curl_init($queryUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Key: ' . ABUSEIPDB_API_KEY,
                'Accept: application/json',
            ],
        ]);

        $res = curl_exec($ch);
        if ($res === false) {
            $abuseError = curl_error($ch);
        } else {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code >= 200 && $code < 300) {
                $json = json_decode($res, true);
                if (isset($json['data']['abuseConfidenceScore'])) {
                    $abuseScore = (int)$json['data']['abuseConfidenceScore'];
                }
            } else {
                $abuseError = "HTTP $code from AbuseIPDB";
            }
        }
        curl_close($ch);

        // ako imamo ozbiljan score → alert
        if ($abuseScore !== null) {
            if ($abuseScore >= 50) {
                $insertAlert->execute([
                    ':website_id' => $id,
                    ':type'       => 'ip_abuse',
                    ':severity'   => 'critical',
                    ':message'    => sprintf(
                        'IP %s for "%s" has high AbuseIPDB score: %d.',
                        $ip,
                        $name,
                        $abuseScore
                    ),
                ]);
            } elseif ($abuseScore >= 10) {
                $insertAlert->execute([
                    ':website_id' => $id,
                    ':type'       => 'ip_abuse',
                    ':severity'   => 'warning',
                    ':message'    => sprintf(
                        'IP %s for "%s" has elevated AbuseIPDB score: %d.',
                        $ip,
                        $name,
                        $abuseScore
                    ),
                ]);
            }
        } elseif ($abuseError) {
            $insertAlert->execute([
                ':website_id' => $id,
                ':type'       => 'ip_abuse_error',
                ':severity'   => 'warning',
                ':message'    => sprintf(
                    'AbuseIPDB check failed for "%s" (%s): %s',
                    $name,
                    $ip,
                    $abuseError
                ),
            ]);
        }
    }

    // --- 4) upiši u websites ---
    $updateDns->execute([
        ':last_ip'      => $ip,
        ':abuse_score'  => $abuseScore,
        ':id'           => $id,
    ]);

    $results[] = [
        'website_id'       => $id,
        'url'              => $url,
        'ip'               => $ip,
        'abuse_score'      => $abuseScore,
        'abuse_error'      => $abuseError,
        'prev_ip'          => $prevIp,
        'prev_abuse_score' => $prevScore,
        'checked'          => true,
    ];
}

echo json_encode(['results' => $results]);
