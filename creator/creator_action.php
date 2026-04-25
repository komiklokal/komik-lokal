<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include("config.php");
if (!isset($_SESSION['username'])) {
    header("Location: ../dashboard/dashboard.php");
    exit();
}
$username = $_SESSION['username'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo "ID tidak diberikan";
        exit();
    }
    $id = $_POST['id'];
    error_log("ID yang diterima: " . $id);
    $conn->begin_transaction();
    try {
        $sql = "DELETE ci FROM chapter_images ci 
                INNER JOIN chapter c ON ci.chapter_id = c.id 
            WHERE c.komik_id = ? AND c.komik_id IN (SELECT id FROM komik WHERE user_nama = ? OR pengarang = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $id, $username, $username);
        $stmt->execute();
        $stmt->close();
        $sql = "DELETE FROM chapter WHERE komik_id = ? AND komik_id IN (SELECT id FROM komik WHERE user_nama = ? OR pengarang = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $id, $username, $username);
        $stmt->execute();
        $stmt->close();
        $sql = "DELETE FROM komik WHERE id = ? AND (user_nama = ? OR pengarang = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $id, $username, $username);
        $result = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        if ($result && $affected_rows > 0) {
            $conn->commit();
            echo "Komik beserta semua chapter dan gambar berhasil dihapus";
        } else {
            $conn->rollback();
            echo "Gagal menghapus komik: Komik tidak ditemukan atau Anda tidak memiliki akses";
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "Gagal menghapus komik: " . $e->getMessage();
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chapter_count') {
    header('Content-Type: application/json');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }

    $stmt = $conn->prepare("SELECT (SELECT COUNT(*) FROM chapter c WHERE c.komik_id = k.id) AS chapter_count FROM komik k WHERE k.id = ? AND (k.user_nama = ? OR k.pengarang = ?) LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server']);
        exit();
    }
    $stmt->bind_param('iss', $id, $username, $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Komik tidak ditemukan atau Anda tidak memiliki akses']);
        exit();
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'chapter_count' => (int)($row['chapter_count'] ?? 0)]);
    exit();
}
$sql = "SELECT * FROM user WHERE user_nama = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    if (function_exists('is_guest') && is_guest()) {
        $userData = [
            'user_nama' => $username,
            'profile_image_blob' => null,
            'profile_image_type' => null,
        ];
    } else {
        header('Location: ../dashboard/dashboard.php');
        exit();
    }
}
$userData = $userData ?? $result->fetch_assoc();
$stmt->close();

$statusFilter = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'all';
$allowedStatus = ['all', 'ongoing', 'hiatus', 'completed'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}

$sql = "SELECT k.*, (SELECT COUNT(*) FROM chapter c WHERE c.komik_id = k.id) AS chapter_count
    FROM komik k
    WHERE (k.user_nama = ? OR k.pengarang = ?)";

if ($statusFilter !== 'all') {
    $sql .= " AND LOWER(k.status) = ?";
}

$sql .= "
    ORDER BY k.created_at DESC
    LIMIT 12";
$stmt = $conn->prepare($sql);
if ($statusFilter !== 'all') {
    $stmt->bind_param("sss", $username, $username, $statusFilter);
} else {
    $stmt->bind_param("ss", $username, $username);
}
$stmt->execute();
$result = $stmt->get_result();
$komikData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $komikData[] = $row;
    }
}
$stmt->close();
$conn->close();
?>
