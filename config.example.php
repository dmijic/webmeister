<?php
$host = 'localhost';
$db   = 'YOUR DATABASE NAME HERE';
$user = 'YOUR DATABASE USERNAME HERE';
$pass = 'YOUR DATABASE PASSWORD HERE';
$charset = 'YOUR DATABASE CHARSET HERE';


// AbuseIPDB API
define('ABUSEIPDB_API_KEY', 'YOUR API KEY HERE');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage());
}

session_start();
