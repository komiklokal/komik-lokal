<?php
$host = "localhost";
$username = "komiklokal_user";
$password = "K0m1kL0k4l#2026";
$dbname = "komiklokal"; 

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
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
