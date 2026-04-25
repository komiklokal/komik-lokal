<?php
$db_host = "shuttle.proxy.rlwy.net";
$db_port = 43672;
$db_user = "root";
$db_pass = "xlKyFgwrlZYACzUskiHKIwypNgDbKcic";
$db_name = "railway";
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}
if (!defined('ALLOW_GUEST_ACCESS')) {
    define('ALLOW_GUEST_ACCESS', false);
}
if (!function_exists('auth_bootstrap')) {
    function auth_bootstrap()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (defined('ALLOW_GUEST_ACCESS') && ALLOW_GUEST_ACCESS) {
            if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
                $_SESSION['username'] = 'Guest';
                $_SESSION['is_guest'] = true;
            }
        }
    }
}
if (!function_exists('is_guest')) {
    function is_guest()
    {
        return !empty($_SESSION['is_guest']);
    }
}
auth_bootstrap();
?>
