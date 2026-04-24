<?php
ob_start();
include('config.php');

$komikId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($komikId <= 0) {
    http_response_code(404);
    exit();
}

$stmt = $conn->prepare('SELECT tipe_gambar, gambar FROM komik WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    exit();
}

$stmt->bind_param('i', $komikId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row || empty($row['gambar'])) {
    http_response_code(404);
    exit();
}

$mimeType = !empty($row['tipe_gambar']) ? (string)$row['tipe_gambar'] : 'image/jpeg';
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowedMimes, true)) {
    $mimeType = 'image/jpeg';
}

$rawImage = (string)$row['gambar'];
$imageData = base64_decode($rawImage, true);
if ($imageData === false || $imageData === '') {
    $normalizedBase64 = preg_replace('/\s+/', '', $rawImage);
    $imageData = base64_decode($normalizedBase64, true);
}
if ($imageData === false || $imageData === '') {
    $imageData = $rawImage;
}

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: ' . $mimeType);
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($imageData));
echo $imageData;

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
