<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include("config.php");

if (!isset($_GET['username']) || $_GET['username'] === '') {
    header("Location: default-avatar.png");
    exit();
}

$username = (string)$_GET['username'];
$sql = "SELECT profile_image_blob, profile_image_type FROM user WHERE user_nama = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    header("Location: default-avatar.png");
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows <= 0) {
    $stmt->close();
    header("Location: default-avatar.png");
    exit();
}

$stmt->bind_result($imageBlob, $imageType);
$stmt->fetch();
$stmt->close();

if (empty($imageBlob)) {
    header("Location: default-avatar.png");
    exit();
}

$payload = $imageBlob;
$mime = is_string($imageType) ? trim($imageType) : '';

// Support legacy rows stored as full data URL.
if (is_string($imageBlob) && preg_match('/^data:([^;]+);base64,(.*)$/s', $imageBlob, $m)) {
    $decodedDataUrl = base64_decode($m[2], true);
    if ($decodedDataUrl !== false && $decodedDataUrl !== '') {
        $payload = $decodedDataUrl;
        if ($mime === '') {
            $mime = (string)$m[1];
        }
    }
} else {
    // Support legacy rows stored as base64 text only.
    $decoded = base64_decode((string)$imageBlob, true);
    if ($decoded !== false && $decoded !== '') {
        $decodedInfo = @getimagesizefromstring($decoded);
        if ($decodedInfo !== false) {
            $payload = $decoded;
            if ($mime === '' && !empty($decodedInfo['mime'])) {
                $mime = (string)$decodedInfo['mime'];
            }
        }
    }
}

if ($mime === '') {
    $payloadInfo = @getimagesizefromstring((string)$payload);
    if ($payloadInfo !== false && !empty($payloadInfo['mime'])) {
        $mime = (string)$payloadInfo['mime'];
    } else {
        $mime = 'application/octet-stream';
    }
}

// Ensure no extra bytes are sent before image binary.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header("Content-Type: " . $mime);
header("Cache-Control: public, max-age=86400");
header("ETag: " . md5($mime . strlen((string)$payload)));
header("Content-Length: " . strlen((string)$payload));
echo $payload;
exit();
