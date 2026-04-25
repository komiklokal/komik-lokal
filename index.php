<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    // Sudah login → ke dashboard
    header("Location: /dashboard/dashboard.php");
} else {
    // Belum login → langsung ke dashboard juga (tanpa cek login)
    header("Location: /dashboard/dashboard.php");
}
exit();
