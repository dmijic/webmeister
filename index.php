<?php
session_start();

// 1. Ako config ne postoji → vodi na installer
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

// 2. Ako postoji config, samo ga učitamo
require_once __DIR__ . '/config.php';

// 3. Ako user NIJE logiran → login
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 4. Ako je user logiran → dashboard
header('Location: dashboard.php');
exit;
