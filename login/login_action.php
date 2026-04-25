<?php
session_start();
include("config.php");

// --- [KEAMANAN] Rate Limiting: Maks 5 percobaan gagal per 15 menit ---
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempts_time'] = time();
}
// Reset counter jika sudah melewati 15 menit
if (time() - $_SESSION['login_attempts_time'] > 900) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempts_time'] = time();
}
if ($_SESSION['login_attempts'] >= 5) {
    $_SESSION['login_error'] = 'rate_limit';
    $_SESSION['login_error_message'] = 'Terlalu banyak percobaan login gagal. Tunggu 15 menit dan coba lagi.';
    header("Location: login.php");
    exit();
}

// --- [KEAMANAN] Validasi CSRF Token ---
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['login_error'] = 'csrf_error';
    $_SESSION['login_error_message'] = 'Permintaan tidak valid. Silakan refresh halaman dan coba lagi.';
    header("Location: login.php");
    exit();
}
// Hapus token setelah digunakan (one-time use)
unset($_SESSION['csrf_token']);

// --- [KEAMANAN] Validasi Input: Tidak Boleh Kosong ---
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'empty_fields';
    $_SESSION['login_error_message'] = 'Username dan password tidak boleh kosong.';
    header("Location: login.php");
    exit();
}

// --- [KEAMANAN] Redirect dikunci ke dashboard internal (mencegah Open Redirect) ---
$redirect = '../dashboard/dashboard.php';

$sql = "SELECT user_nama, user_password FROM user WHERE user_nama = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (isset($_SESSION['reset_passwords'][$username])) {
        $old_password_hash = $_SESSION['reset_passwords'][$username];
        if (password_verify($password, $old_password_hash)) {
            $_SESSION['login_attempts']++;
            $_SESSION['login_error'] = 'password_reset';
            $_SESSION['login_error_message'] = 'Password Anda telah direset sebelumnya. Silakan gunakan password baru Anda.';
            header("Location: login.php");
            exit();
        }
    }
    if (password_verify($password, $row['user_password'])) {
        // Login berhasil: reset counter & regenerate session ID
        $_SESSION['login_attempts'] = 0;
        $_SESSION['username'] = $username;
        if (isset($_SESSION['reset_passwords'][$username])) {
            unset($_SESSION['reset_passwords'][$username]);
        }
        session_regenerate_id(true); // [KEAMANAN] Cegah Session Fixation
        header("Location: " . $redirect);
        exit();
    } else {
        $_SESSION['login_attempts']++;
        $_SESSION['login_error'] = 'password_wrong';
        $_SESSION['login_error_message'] = 'Password anda salah coba masukan kembali password yang benar';
        header("Location: login.php");
        exit();
    }
} else {
    $emailCheckSql = "SELECT user_email FROM user WHERE user_email = ?";
    $emailStmt = $conn->prepare($emailCheckSql);
    $emailStmt->bind_param("s", $username);
    $emailStmt->execute();
    $emailResult = $emailStmt->get_result();
    if ($emailResult->num_rows > 0) {
        $_SESSION['login_attempts']++;
        $_SESSION['login_error'] = 'username_not_found';
        $_SESSION['login_error_message'] = 'Username tidak ditemukan. Silakan coba lagi.';
    } else {
        $_SESSION['login_attempts']++;
        $_SESSION['login_error'] = 'not_registered';
        $_SESSION['login_error_message'] = 'Username tidak ditemukan. Silakan daftar terlebih dahulu.';
    }
    $emailStmt->close();
    header("Location: login.php");
    exit();
}

$stmt->close();
$conn->close();
?>
