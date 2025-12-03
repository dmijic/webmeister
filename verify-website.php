<?php
// verify-website.php
require_once 'auth.php';
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid website id.");
}

$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
$stmt->execute([$id]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    die("Website not found.");
}

if ($site['status'] !== 'pending') {
    die("Website is not pending.");
}

if (empty($site['verification_token'])) {
    die("No verification token found. Download token again.");
}

$token = $site['verification_token'];

// Složimo URL do verifikacijske datoteke
$baseUrl = rtrim($site['url'], '/');
$verificationUrl = $baseUrl . "/monitoring-{$token}.txt";

// Pokušaj pročitati
$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 5
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false
    ]
]);

$content = @file_get_contents($verificationUrl, false, $ctx);

if ($content === false) {
    die("Could not read verification file at: " . htmlspecialchars($verificationUrl));
}

if (trim($content) === $token) {
    // Verifikacija uspješna
    $upd = $pdo->prepare("UPDATE websites SET status = 'active' WHERE id = ?");
    $upd->execute([$id]);

    // Po želji: obriši token
    // $upd2 = $pdo->prepare("UPDATE websites SET verification_token = NULL WHERE id = ?");
    // $upd2->execute([$id]);

    header("Location: websites.php?verified=1");
    exit;
} else {
    die("Token in file does not match expected value.");
}
