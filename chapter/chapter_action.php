<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Simpan session username sebelum config.php menimpa $username
$sessionUsername = $_SESSION['username'] ?? null;
$isLoggedIn = !empty($sessionUsername);

include("config.php");

$user_id = null;
if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT id FROM user WHERE user_nama = ?");
    $stmt->bind_param("s", $sessionUsername);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = (!empty($user) && isset($user['id'])) ? intval($user['id']) : null;
    $stmt->close();
}
$chapter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($chapter_id <= 0) {
    die("ID chapter tidak valid.");
}
$stmt = $conn->prepare("SELECT id, judul, cover, tipe_cover, komik_id FROM chapter WHERE id = ?");
if (!$stmt) {
    error_log('[chapter] Prepare failed: ' . $conn->error);
    die("Terjadi kesalahan. Silakan coba lagi.");
}
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$result = $stmt->get_result();
$chapter = $result->fetch_assoc();
$stmt->close();
if (!$chapter) {
    die("Chapter tidak ditemukan.");
}
if ($isLoggedIn && $user_id) {
    $stmt = $conn->prepare("
        INSERT INTO riwayat_baca (user_id, komik_id, chapter_id, tanggal_baca)
        VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
        tanggal_baca = NOW(),
        chapter_id = VALUES(chapter_id)
    ");
    $stmt->bind_param("iii", $user_id, $chapter['komik_id'], $chapter_id);
    $stmt->execute();
    $stmt->close();
}
$prev = null;
$stmt = $conn->prepare("SELECT id, judul FROM chapter WHERE komik_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ii", $chapter['komik_id'], $chapter_id);
$stmt->execute();
$resultPrev = $stmt->get_result();
if ($resultPrev->num_rows > 0) {
    $prev = $resultPrev->fetch_assoc();
}
$stmt->close();
$next = null;
$stmt = $conn->prepare("SELECT id, judul FROM chapter WHERE komik_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
$stmt->bind_param("ii", $chapter['komik_id'], $chapter_id);
$stmt->execute();
$resultNext = $stmt->get_result();
if ($resultNext->num_rows > 0) {
    $next = $resultNext->fetch_assoc();
}
$stmt->close();
$stmt = $conn->prepare("SELECT gambar, tipe_gambar FROM chapter_images WHERE chapter_id = ? ORDER BY urutan");
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$images_result = $stmt->get_result();
$images = [];
while ($row = $images_result->fetch_assoc()) {
    $images[] = $row;
}
$stmt->close();
$image_count = count($images);
$conn->close();
?>
