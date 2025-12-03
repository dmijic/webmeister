<?php
// check-ssl.php
require_once 'auth.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit;
}

// Dohvati sve ACTIVE siteove
$stmt = $pdo->query("
    SELECT id, name, url
    FROM websites
    WHERE status = 'active' AND is_active = 1
");
$websites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepared statements
$updateSsl = $pdo->prepare("
    UPDATE websites
    SET ssl_expires_at = :ssl_expires_at,
        ssl_last_checked_at = NOW()
    WHERE id = :id
");

$insertAlert = $pdo->prepare("
    INSERT INTO alerts (website_id, created_at, type, severity, message, is_read)
    VALUES (:website_id, NOW(), :type, :severity, :message, 0)
");

$results = [];

foreach ($websites as $site) {
    $id   = (int)$site['id'];
    $name = $site['name'];
    $url  = $site['url'];

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (strtolower($scheme) !== 'https') {
        // Ne radimo SSL check za non-https
        $results[] = [
            'website_id' => $id,
            'url'        => $url,
            'checked'    => false,
            'reason'     => 'non_https',
        ];
        continue;
    }

    $host = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT) ?: 443;

    if (!$host) {
        $msg = sprintf('Cannot parse host for "%s" (%s).', $name, $url);
        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'ssl_error',
            ':severity'   => 'warning',
            ':message'    => $msg,
        ]);

        $results[] = [
            'website_id' => $id,
            'url'        => $url,
            'checked'    => false,
            'reason'     => 'no_host',
        ];
        continue;
    }

    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $client = @stream_socket_client(
        "ssl://{$host}:{$port}",
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$client) {
        $msg = sprintf(
            'SSL check failed for "%s" (%s): %s (%d)',
            $name,
            $url,
            $errstr,
            $errno
        );

        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'ssl_error',
            ':severity'   => 'warning',
            ':message'    => $msg,
        ]);

        $results[] = [
            'website_id' => $id,
            'url'        => $url,
            'checked'    => false,
            'reason'     => 'connect_error',
            'error'      => $errstr,
        ];
        continue;
    }

    $params = stream_context_get_params($client);
    fclose($client);

    if (empty($params['options']['ssl']['peer_certificate'])) {
        $msg = sprintf(
            'No peer certificate for "%s" (%s).',
            $name,
            $url
        );

        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'ssl_error',
            ':severity'   => 'warning',
            ':message'    => $msg,
        ]);

        $results[] = [
            'website_id' => $id,
            'url'        => $url,
            'checked'    => false,
            'reason'     => 'no_cert',
        ];
        continue;
    }

    $cert = $params['options']['ssl']['peer_certificate'];
    $certInfo = openssl_x509_parse($cert);

    if (!$certInfo || empty($certInfo['validTo_time_t'])) {
        $msg = sprintf(
            'Could not read certificate validity for "%s" (%s).',
            $name,
            $url
        );

        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'ssl_error',
            ':severity'   => 'warning',
            ':message'    => $msg,
        ]);

        $results[] = [
            'website_id' => $id,
            'url'        => $url,
            'checked'    => false,
            'reason'     => 'no_validTo',
        ];
        continue;
    }

    $validTo = (int)$certInfo['validTo_time_t'];
    $expiresAt = (new DateTime())->setTimestamp($validTo);
    $now = new DateTime();

    $diff = $now->diff($expiresAt);
    // ako je u budućnosti: +days, ako je u prošlosti: -days
    $daysLeft = $expiresAt >= $now ? $diff->days : -$diff->days;
    $expired  = $expiresAt < $now;

    // upiši u websites
    $updateSsl->execute([
        ':ssl_expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ':id'             => $id,
    ]);

    // alerti
    if ($expired) {
        $msg = sprintf(
            'SSL certificate for "%s" (%s) has EXPIRED %d days ago (%s).',
            $name,
            $url,
            abs($daysLeft),
            $expiresAt->format('Y-m-d')
        );

        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'ssl_expired',
            ':severity'   => 'critical',
            ':message'    => $msg,
        ]);
    } elseif ($daysLeft <= 7) {
        $msg = sprintf(
            'SSL certificate for "%s" (%s) will expire in %d days (%s).',
            $name,
            $url,
            $daysLeft,
            $expiresAt->format('Y-m-d')
        );

        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'ssl_expiry',
            ':severity'   => 'critical',
            ':message'    => $msg,
        ]);
    } elseif ($daysLeft <= 30) {
        $msg = sprintf(
            'SSL certificate for "%s" (%s) will expire in %d days (%s).',
            $name,
            $url,
            $daysLeft,
            $expiresAt->format('Y-m-d')
        );

        $insertAlert->execute([
            ':website_id' => $id,
            ':type'       => 'ssl_expiry',
            ':severity'   => 'warning',
            ':message'    => $msg,
        ]);
    }

    $results[] = [
        'website_id' => $id,
        'url'        => $url,
        'checked'    => true,
        'expired'    => $expired,
        'days_left'  => $daysLeft,
        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
    ];
}

echo json_encode(['results' => $results]);
