<?php
$host     = getenv('DB_HOST')     ?: 'mysql.railway.internal';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: 'xlKyFgwrlZYACzUskiHKIwypNgDbKcic ';
$dbname   = getenv('DB_NAME')     ?: 'railway';
$port     = (int)(getenv('DB_PORT') ?: 43672 );

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}

if (!$conn->select_db($dbname)) {
    error_log("Failed to select database: " . $dbname);
    die("Database tidak ditemukan.");
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
