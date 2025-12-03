<?php
// download-verification.php
require_once 'auth.php';
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid website id.");
}

$stmt = $pdo->prepare("SELECT name, url, status, verification_token FROM websites WHERE id = ?");
$stmt->execute([$id]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    die("Website not found.");
}

if ($site['status'] !== 'pending') {
    die("Website is not in pending status.");
}

// Ako nema tokena, generiraj jedan i spremi
if (empty($site['verification_token'])) {
    $token = bin2hex(random_bytes(16)); // 32-znak HEX
    $upd = $pdo->prepare("UPDATE websites SET verification_token = ? WHERE id = ?");
    $upd->execute([$token, $id]);
} else {
    $token = $site['verification_token'];
}

$filename = "monitoring-{$token}.txt";

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo $token;
