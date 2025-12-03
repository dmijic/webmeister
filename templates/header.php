<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Website monitoring dashboard">
    <meta name="author" content="Dario">
    <meta name="keywords" content="monitoring, uptime, dashboard">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="public/img/icons/icon-48x48.png" />

    <title>Website Monitoring Dashboard</title>

    <link href="public/css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
</head>

<body>
    <div class="wrapper">