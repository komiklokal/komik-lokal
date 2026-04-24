<?php
session_start();

include("config.php");
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
    exit();
}

$username = $_SESSION['username'];

// [KEAMANAN] Validasi CSRF Token
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_delete_token']) ||
    !hash_equals($_SESSION['csrf_delete_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid (CSRF).']);
    exit();
}
// Hapus token setelah validasi (one-time use)
unset($_SESSION['csrf_delete_token']);

if (function_exists('is_guest') && is_guest()) {
    echo json_encode(['success' => false, 'message' => 'Guest mode: akun tidak bisa dihapus']);
    exit();
}

try {
    $conn->begin_transaction();
    
    $stmt = $conn->prepare("SELECT id FROM user WHERE user_nama = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $stmt->close();
    
    if (!$user_id) {
        throw new Exception("User tidak ditemukan");
    }
    
    $sql = "DELETE FROM riwayat_baca WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    $sql = "DELETE FROM feedback WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT id FROM komik WHERE user_nama = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $komik_ids = [];
    while ($row = $result->fetch_assoc()) {
        $komik_ids[] = $row['id'];
    }
    $stmt->close();
    
    if (!empty($komik_ids)) {
        $placeholders = implode(',', array_fill(0, count($komik_ids), '?'));
        $sql = "DELETE FROM chapter WHERE komik_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($komik_ids));
        $stmt->bind_param($types, ...$komik_ids);
        $stmt->execute();
        $stmt->close();
    }
    
    if (!empty($komik_ids)) {
        $placeholders = implode(',', array_fill(0, count($komik_ids), '?'));
        $sql = "DELETE FROM komik_genre WHERE komik_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($komik_ids));
        $stmt->bind_param($types, ...$komik_ids);
        $stmt->execute();
        $stmt->close();
    }
    
    $sql = "DELETE FROM komik WHERE user_nama = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
    
    $sql = "DELETE FROM user WHERE user_nama = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    session_unset();
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Akun berhasil dihapus']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus akun: ' . $e->getMessage()]);
}

$conn->close();
?>
