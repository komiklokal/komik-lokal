<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'verify_username') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? ''); // [KEAMANAN] Wajib verifikasi email

    if (empty($username) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username dan email tidak boleh kosong'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // [KEAMANAN] Cocokkan username DAN email sekaligus
        $stmt = $conn->prepare("SELECT user_nama FROM user WHERE user_nama = ? AND user_email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Simpan di session agar update_password tidak bisa diakses langsung
            $_SESSION['verified_reset_username'] = $username;
            echo json_encode([
                'success'  => true,
                'username' => $username,
                'message'  => 'Identitas terverifikasi'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            // Pesan generik — tidak bocorkan apakah username atau email yang salah
            echo json_encode(['success' => false, 'message' => 'Username atau email tidak cocok'], JSON_UNESCAPED_UNICODE);
        }

        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database'], JSON_UNESCAPED_UNICODE);
        error_log('Database error in forgot_password_action.php (verify): ' . $e->getMessage());
    }

} elseif ($action === 'update_password') {
    if (!isset($_SESSION['reset_attempts'])) {
        $_SESSION['reset_attempts'] = 0;
        $_SESSION['reset_attempts_time'] = time();
    }
    if (time() - $_SESSION['reset_attempts_time'] > 3600) {
        $_SESSION['reset_attempts'] = 0;
        $_SESSION['reset_attempts_time'] = time();
    }
    if ($_SESSION['reset_attempts'] >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Terlalu banyak percobaan reset password. Tunggu 1 jam dan coba lagi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['reset_attempts']++;

    // [KEAMANAN] Pastikan sudah melewati step verifikasi identitas
    if (empty($_SESSION['verified_reset_username'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Verifikasi identitas diperlukan sebelum reset password'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $username     = $_SESSION['verified_reset_username']; // Ambil dari session, bukan dari POST
    $new_password = $_POST['new_password'] ?? '';

    if (empty($username) || empty($new_password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE user SET user_password = ? WHERE user_nama = ?");
        $stmt->bind_param("ss", $hashed_password, $username);

        if ($stmt->execute()) {
            // [KEAMANAN] Hapus token verifikasi setelah berhasil (tidak bisa dipakai ulang)
            unset($_SESSION['verified_reset_username']);
            echo json_encode(['success' => true, 'message' => 'Password berhasil direset'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mereset password'], JSON_UNESCAPED_UNICODE);
        }

        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database'], JSON_UNESCAPED_UNICODE);
        error_log('Database error in forgot_password_action.php: ' . $e->getMessage());
    }

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action tidak valid'], JSON_UNESCAPED_UNICODE);
}

if (isset($conn) && $conn) {
    $conn->close();
}
?>