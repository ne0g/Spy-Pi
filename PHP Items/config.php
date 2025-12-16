<?php
// config.php

session_start();

// Load creds from Elastic Beanstalk env vars
$VALID_USERNAME = getenv('SPYPI_USERNAME');
$VALID_PASSWORD = getenv('SPYPI_PASSWORD');

// Helper code to check if user is logged in
function is_logged_in(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Enforces login, if not logged in, then redirect to login page
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}


