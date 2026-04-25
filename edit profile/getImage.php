<?php
ini_set('display_errors', 0);
error_reporting(0);
include("config.php");
if (empty($_GET['username'])) {
    http_response_code(400);
    exit("Username tidak diberikan");
}
$username = $_GET['username'];
$sql = "SELECT profile_image_blob, profile_image_type 
        FROM user 
        WHERE user_nama = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit("Prepare failed: ". $conn->error);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($blob, $type);
if ($stmt->fetch()) {
    if ($blob === null) {
        http_response_code(404);
        exit("Tidak ada gambar untuk user ini");
    }
    header("Content-Type: " . $type);
    header("Content-Length: " . strlen($blob));
    echo $blob;
    $stmt->close();
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    exit;
} else {
    http_response_code(404);
    exit("User tidak ditemukan");
}
$stmt->close();
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
