<?php
session_start();
include('config.php');
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'not_authenticated']);
    exit();
}
$user = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$csrfProtectedActions = ['post', 'edit', 'delete', 'forward_message'];
if (in_array($action, $csrfProtectedActions, true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): void {
    $tableEsc = $conn->real_escape_string($table);
    $colEsc = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'");
    if ($check && $check->num_rows > 0) return;
    $conn->query("ALTER TABLE `{$tableEsc}` ADD COLUMN `{$column}` {$definition}");
}
function ensureFeedbackBlobSchema(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS feedback_online (
        username VARCHAR(255) NOT NULL,
        group_type VARCHAR(50) NOT NULL DEFAULT 'bug',
        last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        first_visit_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (username, group_type),
        KEY idx_feedback_online_group (group_type),
        KEY idx_feedback_online_last_seen (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    ensureColumnExists($conn, 'feedback_online', 'first_visit_at', 'TIMESTAMP NULL DEFAULT NULL AFTER last_seen');

    $conn->query("CREATE TABLE IF NOT EXISTS feedback_read (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        feedback_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_feedback_read (username, feedback_id),
        KEY idx_feedback_read_user (username),
        KEY idx_feedback_read_feedback (feedback_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS message_replies (
        id INT PRIMARY KEY AUTO_INCREMENT,
        message_id INT NOT NULL,
        reply_to_message_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_to_message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reply (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS message_forwards (
        id INT PRIMARY KEY AUTO_INCREMENT,
        message_id INT NOT NULL,
        forwarded_from_message_id INT NOT NULL,
        forwarded_from_group VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        FOREIGN KEY (forwarded_from_message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        KEY idx_message_id (message_id),
        KEY idx_forwarded_from_id (forwarded_from_message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS feedback_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feedback_id INT NOT NULL,
        image_path VARCHAR(500) NOT NULL DEFAULT '',
        image_blob LONGTEXT NULL,
        image_type VARCHAR(100) NULL,
        urutan INT NOT NULL DEFAULT 1,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
        KEY idx_feedback_images (feedback_id, urutan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ensureColumnExists($conn, 'feedback_images', 'image_blob', 'LONGTEXT NULL AFTER image_path');
    ensureColumnExists($conn, 'feedback_images', 'image_type', 'VARCHAR(100) NULL AFTER image_blob');
    $checkAttachment = $conn->query("SHOW COLUMNS FROM feedback LIKE 'attachment'");
    $afterColumn = ($checkAttachment && $checkAttachment->num_rows > 0) ? 'attachment' : 'komentar';
    ensureColumnExists($conn, 'feedback', 'attachment_blob', "LONGTEXT NULL AFTER `{$afterColumn}`");
    ensureColumnExists($conn, 'feedback', 'attachment_type', 'VARCHAR(100) NULL AFTER attachment_blob');

    ensureColumnExists($conn, 'feedback', 'group_type', "VARCHAR(50) NOT NULL DEFAULT 'bug' AFTER `{$afterColumn}`");
    $checkTanggal = $conn->query("SHOW COLUMNS FROM feedback LIKE 'tanggal'");
    $afterUpdatedAt = ($checkTanggal && $checkTanggal->num_rows > 0) ? 'tanggal' : 'group_type';
    ensureColumnExists($conn, 'feedback', 'updated_at', "TIMESTAMP NULL DEFAULT NULL AFTER `{$afterUpdatedAt}`");
}
function buildDataUrl(?string $mime, ?string $base64): ?string {
    if ($base64 === null) return null;
    if ($base64 === '') return null;

    // Data lama bisa tersimpan sebagai raw binary; normalisasi ke base64 valid.
    $decoded = base64_decode($base64, true);
    if ($decoded === false) {
        $base64 = base64_encode($base64);
    }

    $mime = $mime ? trim($mime) : '';
    if ($mime === '') $mime = 'application/octet-stream';
    return 'data:' . $mime . ';base64,' . $base64;
}

if (!function_exists('formatChatDayLabel')) {
    function formatChatDayLabel(DateTime $date): string
    {
        $today = new DateTime('today');
        $target = (clone $date)->setTime(0, 0, 0);
        $diffDays = (int)$today->diff($target)->format('%r%a');

        if ($diffDays === 0) {
            return 'Hari ini';
        }
        if ($diffDays === -1) {
            return 'Kemarin';
        }

        if ($diffDays >= -6 && $diffDays < 0) {
            $days = [
                'Sunday' => 'Minggu',
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
            ];
            $dayEn = $target->format('l');
            return $days[$dayEn] ?? $target->format('j/n/Y');
        }

        return $target->format('j/n/Y');
    }
}
ensureFeedbackBlobSchema($conn);
if ($action === 'heartbeat') {
    $groupType = $_POST['group_type'] ?? 'bug';
    $stmt = $conn->prepare("
        INSERT INTO feedback_online (username, group_type, last_seen) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            group_type = VALUES(group_type),
            last_seen = NOW()
    ");
    $stmt->bind_param("ss", $user, $groupType);
    $stmt->execute();
    $stmt->close();
    $conn->query("
        INSERT IGNORE INTO feedback_read (username, feedback_id)
        SELECT '$user', id FROM feedback
    ");
    $conn->query("DELETE FROM feedback_online WHERE last_seen < DATE_SUB(NOW(), INTERVAL 15 SECOND)");
    $bugOnline = $conn->query("SELECT COUNT(DISTINCT username) as total FROM feedback_online WHERE group_type = 'bug'")->fetch_assoc()['total'];
    $inspirationOnline = $conn->query("SELECT COUNT(DISTINCT username) as total FROM feedback_online WHERE group_type = 'inspiration'")->fetch_assoc()['total'];
    $generalOnline = $conn->query("SELECT COUNT(DISTINCT username) as total FROM feedback_online WHERE group_type = 'general'")->fetch_assoc()['total'];
    $unreadCount = $conn->query("
        SELECT COUNT(*) as total 
        FROM feedback f
        LEFT JOIN feedback_read fr ON f.id = fr.feedback_id AND fr.username = '$user'
        WHERE fr.id IS NULL AND f.username != '$user'
    ")->fetch_assoc()['total'];
    echo json_encode([
        'success' => true,
        'online' => [
            'bug' => $bugOnline,
            'inspiration' => $inspirationOnline,
            'general' => $generalOnline
        ],
        'unread' => $unreadCount
    ]);
    exit();
}
if ($action === 'leave') {
    $stmt = $conn->prepare("DELETE FROM feedback_online WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit();
}
if ($action === 'create_table') {
    $tables = [];
    $sql1 = "CREATE TABLE IF NOT EXISTS message_replies (
        id INT PRIMARY KEY AUTO_INCREMENT,
        message_id INT NOT NULL,
        reply_to_message_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_to_message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reply (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if ($conn->query($sql1) === TRUE) {
        $tables[] = 'message_replies';
    }
    $sql2 = "CREATE TABLE IF NOT EXISTS message_forwards (
        id INT PRIMARY KEY AUTO_INCREMENT,
        message_id INT NOT NULL,
        forwarded_from_message_id INT NOT NULL,
        forwarded_from_group VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        FOREIGN KEY (forwarded_from_message_id) REFERENCES feedback(id) ON DELETE CASCADE,
        KEY idx_message_id (message_id),
        KEY idx_forwarded_from_id (forwarded_from_message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if ($conn->query($sql2) === TRUE) {
        $tables[] = 'message_forwards';
    }
    if (count($tables) > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Tabel berhasil dibuat: ' . implode(', ', $tables)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $conn->error
        ]);
    }
    exit();
}
if ($action === 'list') {
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    $sort = $_GET['sort'] === 'oldest' ? 'ASC' : 'DESC';
    $filterUser = $_GET['filterUser'] ?? 'all';
    $search = $conn->real_escape_string($_GET['search'] ?? '');
    $where = [];
    if ($filterUser === 'me') {
        $where[] = "username = '" . $conn->real_escape_string($user) . "'";
    }
    if ($search !== '') {
        $where[] = "(komentar LIKE '%$search%')";
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $query = "SELECT f.id, f.username, f.komentar, f.tanggal, f.category, f.rating, f.attachment, (CASE WHEN f.attachment_blob IS NOT NULL AND f.attachment_blob != '' THEN 1 ELSE 0 END) AS has_attachment_blob, u.profile_image_blob, u.user_nama FROM feedback f LEFT JOIN user u ON u.user_nama = f.username $whereSQL ORDER BY f.tanggal $sort LIMIT $limit OFFSET $offset";
    $res = $conn->query($query);
    $items = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['profile_image_blob']) && !empty($row['user_nama'])) {
            $row['profile_image'] = 'edit profile/getImage.php?username=' . urlencode($row['user_nama']);
        } else {
            $row['profile_image'] = null;
        }
        unset($row['profile_image_blob']);
        unset($row['user_nama']);
        if (!empty($row['has_attachment_blob'])) {
            $row['attachment'] = 'feedback_action.php?action=get_attachment&id=' . urlencode($row['id']);
        }
        unset($row['has_attachment_blob']);
        $items[] = $row;
    }
    echo json_encode(['items' => $items]);
    exit();
}
if ($action === 'get_attachment') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }
    $stmt = $conn->prepare("SELECT attachment, attachment_type, attachment_blob FROM feedback WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Attachment tidak ditemukan']);
        exit();
    }
    $row = $res->fetch_assoc();
    $stmt->close();
    $blob = $row['attachment_blob'] ?? '';
    if (is_string($blob) && trim($blob) !== '') {
        $mime = $row['attachment_type'] ?: 'application/octet-stream';
        $bin = base64_decode($blob, true);
        if ($bin === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Attachment rusak']);
            exit();
        }
        header_remove('Content-Type');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="attachment_' . $id . '"');
        echo $bin;
        exit();
    }
    $path = $row['attachment'] ?? '';
    if (is_string($path) && strpos($path, 'uploads/feedback/') === 0) {
        header_remove('Content-Type');
        header('Location: ' . $path);
        exit();
    }
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Attachment tidak ditemukan']);
    exit();
}
if ($action === 'get_image') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        exit('Invalid image id');
    }

    $stmt = $conn->prepare("SELECT image_path, image_type, image_blob FROM feedback_images WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        exit('Failed to prepare image query');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        exit('Image not found');
    }

    $row = $res->fetch_assoc();
    $stmt->close();

    $blob = $row['image_blob'] ?? '';
    if (is_string($blob) && $blob !== '') {
        $mime = $row['image_type'] ?: 'image/jpeg';
        $bin = base64_decode($blob, true);
        if ($bin === false) {
            $bin = $blob;
        }
        header_remove('Content-Type');
        header('Content-Type: ' . $mime);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $bin;
        exit();
    }

    $path = trim((string)($row['image_path'] ?? ''));
    if ($path !== '') {
        if (strpos($path, 'data:') === 0) {
            if (preg_match('/^data:([^;]+);base64,(.*)$/', $path, $m)) {
                $mime = $m[1] ?: 'image/jpeg';
                $bin = base64_decode($m[2], true);
                if ($bin !== false) {
                    header_remove('Content-Type');
                    header('Content-Type: ' . $mime);
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    echo $bin;
                    exit();
                }
            }
        }
        if (preg_match('/^uploads\//', $path)) {
            header_remove('Content-Type');
            header('Location: ' . $path);
            exit();
        }
    }

    http_response_code(404);
    exit('Image source not found');
}
if ($action === 'post') {
    $komentar = trim($_POST['komentar'] ?? '');
    $category = $_POST['category'] ?? 'umum';
    $rating = intval($_POST['rating'] ?? 0);
    if ($komentar === '') {
        echo json_encode(['error' => 'empty_comment']);
        exit();
    }
    $attachmentType = null;
    $attachmentBlob = null;
    $attachmentPath = null; 
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $u = $_FILES['attachment'];
        $attachmentType = (function_exists('mime_content_type') ? @mime_content_type($u['tmp_name']) : null) ?: ($u['type'] ?? null);
        $attachmentBlob = base64_encode(file_get_contents($u['tmp_name']));
    }
    $stmt = $conn->prepare("INSERT INTO feedback (username, komentar, category, rating, attachment, attachment_type, attachment_blob) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssisss', $user, $komentar, $category, $rating, $attachmentPath, $attachmentType, $attachmentBlob);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'db_error', 'msg' => 'Terjadi kesalahan, coba lagi.']);
    }
    $stmt->close();
    exit();
}
if ($action === 'get_images') {
    $messageId = intval($_GET['message_id'] ?? 0);
    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }
    $stmt = $conn->prepare("SELECT id, image_path, image_type, image_blob, urutan FROM feedback_images WHERE feedback_id = ? ORDER BY urutan ASC");
    if (!$stmt) {
        echo json_encode(['success' => true, 'images' => []]);
        exit();
    }
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $hasBlob = isset($row['image_blob']) && $row['image_blob'] !== '';
        $hasPath = isset($row['image_path']) && trim((string)$row['image_path']) !== '';
        if (!$hasBlob && !$hasPath) {
            continue;
        }

        $images[] = [
            'id' => (int)$row['id'],
            'urutan' => (int)$row['urutan'],
            'image_path' => 'feedback_action.php?action=get_image&id=' . (int)$row['id']
        ];
    }
    $json = json_encode(['success' => true, 'images' => $images]);
    if ($json === false) {
        echo json_encode(['success' => true, 'images' => []]);
    } else {
        echo $json;
    }
    $stmt->close();
    exit();
}
if ($action === 'edit') {
    $messageId = intval($_POST['message_id'] ?? 0);
    $newText = trim($_POST['new_text'] ?? '');
    $imagesToDelete = json_decode($_POST['images_to_delete'] ?? '[]', true);
    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID pesan tidak valid']);
        exit();
    }
    $checkStmt = $conn->prepare("SELECT username, komentar FROM feedback WHERE id = ?");
    $checkStmt->bind_param("i", $messageId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        exit();
    }
    $row = $result->fetch_assoc();
    if ($row['username'] !== $user) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengedit pesan ini']);
        exit();
    }
    if (!empty($imagesToDelete) && is_array($imagesToDelete)) {
        foreach ($imagesToDelete as $imageId) {
            $imageId = intval($imageId);
            $imgStmt = $conn->prepare("SELECT image_path FROM feedback_images WHERE id = ? AND feedback_id = ?");
            $imgStmt->bind_param("ii", $imageId, $messageId);
            $imgStmt->execute();
            $imgResult = $imgStmt->get_result();
            if ($imgRow = $imgResult->fetch_assoc()) {
                $delStmt = $conn->prepare("DELETE FROM feedback_images WHERE id = ?");
                $delStmt->bind_param("i", $imageId);
                $delStmt->execute();
                $delStmt->close();
            }
            $imgStmt->close();
        }
    }
    if (!empty($_FILES['new_images'])) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 5 * 1024 * 1024; 
        $maxUrutanStmt = $conn->prepare("SELECT COALESCE(MAX(urutan), 0) as max_urutan FROM feedback_images WHERE feedback_id = ?");
        $maxUrutanStmt->bind_param("i", $messageId);
        $maxUrutanStmt->execute();
        $maxUrutanResult = $maxUrutanStmt->get_result();
        $maxUrutan = $maxUrutanResult->fetch_assoc()['max_urutan'];
        $maxUrutanStmt->close();
        $urutan = $maxUrutan + 1;
        foreach ($_FILES['new_images']['name'] as $key => $name) {
            if ($_FILES['new_images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileType = $_FILES['new_images']['type'][$key];
                $fileSize = $_FILES['new_images']['size'][$key];
                if (!in_array($fileType, $allowedTypes)) {
                    continue;
                }
                if ($fileSize > $maxFileSize) {
                    continue;
                }
                $tmp = $_FILES['new_images']['tmp_name'][$key];
                $imageType = (function_exists('mime_content_type') ? @mime_content_type($tmp) : null) ?: $fileType;
                $imageBlob = base64_encode(file_get_contents($tmp));
                $emptyPath = '';
                $insertImgStmt = $conn->prepare("INSERT INTO feedback_images (feedback_id, image_path, image_type, image_blob, urutan) VALUES (?, ?, ?, ?, ?)");
                $insertImgStmt->bind_param("isssi", $messageId, $emptyPath, $imageType, $imageBlob, $urutan);
                $insertImgStmt->execute();
                $insertImgStmt->close();
                $urutan++;
            }
        }
    }
    $checkImagesStmt = $conn->prepare("SELECT COUNT(*) as img_count FROM feedback_images WHERE feedback_id = ?");
    $checkImagesStmt->bind_param("i", $messageId);
    $checkImagesStmt->execute();
    $imgCountResult = $checkImagesStmt->get_result();
    $imgCount = $imgCountResult->fetch_assoc()['img_count'];
    $checkImagesStmt->close();
    if ($newText === '' && $imgCount == 0) {
        echo json_encode(['success' => false, 'message' => 'Pesan tidak boleh kosong. Minimal ada teks atau gambar.']);
        exit();
    }
    $updateStmt = $conn->prepare("UPDATE feedback SET komentar = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("si", $newText, $messageId);
    if ($updateStmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Pesan berhasil diupdate'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate pesan']);
    }
    $updateStmt->close();
    $checkStmt->close();
    exit();
}
if ($action === 'forward_message') {
    $forwardedFromMessageId = intval($_POST['forwarded_from_message_id'] ?? 0);
    $targetGroup = $_POST['group_type'] ?? '';
    $forwardedFromGroup = $_POST['forwarded_from_group'] ?? '';
    $forwardedFromUsername = $_POST['forwarded_from_username'] ?? '';
    $forwardComment = trim($_POST['forward_comment'] ?? '');
    if ($forwardedFromMessageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID pesan tidak valid']);
        exit();
    }
    $originalStmt = $conn->prepare("SELECT komentar FROM feedback WHERE id = ?");
    $originalStmt->bind_param("i", $forwardedFromMessageId);
    $originalStmt->execute();
    $originalResult = $originalStmt->get_result();
    if ($originalResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pesan asli tidak ditemukan']);
        exit();
    }
    $originalData = $originalResult->fetch_assoc();
    $originalText = $originalData['komentar'];
    $originalStmt->close();
    $finalText = $originalText;
    if ($forwardComment !== '') {
        $finalText = $forwardComment;
    }
    $insertStmt = $conn->prepare("INSERT INTO feedback (username, komentar, group_type) VALUES (?, ?, ?)");
    $insertStmt->bind_param("sss", $user, $finalText, $targetGroup);
    if (!$insertStmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesan']);
        exit();
    }
    $newMessageId = $conn->insert_id;
    $insertStmt->close();
    $imagesStmt = $conn->prepare("SELECT image_path, image_type, image_blob FROM feedback_images WHERE feedback_id = ? ORDER BY urutan ASC");
    $imagesStmt->bind_param("i", $forwardedFromMessageId);
    $imagesStmt->execute();
    $imagesResult = $imagesStmt->get_result();
    if ($imagesResult->num_rows > 0) {
        $insertImageStmt = $conn->prepare("INSERT INTO feedback_images (feedback_id, image_path, image_type, image_blob, urutan) VALUES (?, ?, ?, ?, ?)");
        $urutan = 1;
        while ($imgRow = $imagesResult->fetch_assoc()) {
            $insertImageStmt->bind_param("isssi", $newMessageId, $imgRow['image_path'], $imgRow['image_type'], $imgRow['image_blob'], $urutan);
            $insertImageStmt->execute();
            $urutan++;
        }
        $insertImageStmt->close();
    }
    $imagesStmt->close();
    $forwardStmt = $conn->prepare("INSERT INTO message_forwards (message_id, forwarded_from_message_id, forwarded_from_group) VALUES (?, ?, ?)");
    $forwardStmt->bind_param("iis", $newMessageId, $forwardedFromMessageId, $forwardedFromGroup);
    $forwardStmt->execute();
    $forwardStmt->close();
    echo json_encode(['success' => true, 'message' => 'Pesan berhasil diteruskan']);
    exit();
}
if ($action === 'delete') {
    $messageId = intval($_POST['message_id'] ?? 0);
    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID pesan tidak valid']);
        exit();
    }
    $checkStmt = $conn->prepare("SELECT username FROM feedback WHERE id = ?");
    $checkStmt->bind_param("i", $messageId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        exit();
    }
    $row = $result->fetch_assoc();
    if ($row['username'] !== $user) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus pesan ini']);
        exit();
    }
    $deleteStmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
    $deleteStmt->bind_param("i", $messageId);
    if ($deleteStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pesan berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pesan']);
    }
    $deleteStmt->close();
    $checkStmt->close();
    exit();
}
if ($action === 'get_messages') {
    $groupType = $_GET['group_type'] ?? 'bug';
    if (!in_array($groupType, ['bug', 'inspiration', 'general'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid group type']);
        exit();
    }
    $stmt = $conn->prepare("
        SELECT f.*, u.profile_image_type, u.profile_image_blob 
        FROM feedback f 
        LEFT JOIN user u ON f.username = u.user_nama 
        WHERE f.group_type = ?
        ORDER BY f.tanggal ASC
    ");
    $stmt->bind_param("s", $groupType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'html' => '']);
        $stmt->close();
        exit();
    }
    ob_start();
    $lastDateKey = null;
    while ($row = $result->fetch_assoc()) {
        $messageDate = new DateTime($row['tanggal']);
        $dateKey = $messageDate->format('Y-m-d');
        if ($dateKey !== $lastDateKey) {
            $lastDateKey = $dateKey;
            echo '<div class="chat-date-separator" data-date="' . htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8') . '"><span>' . htmlspecialchars(formatChatDayLabel($messageDate)) . '</span></div>';
        }
        $isCurrentUser = ($row['username'] === $user);
        $messageClass = $isCurrentUser ? 'message-right' : 'message-left';
        $displayName = htmlspecialchars($row['username']);
        $feedbackId = $row['id'];
        echo '<div class="message-wrapper ' . $messageClass . '">';
        if (!$isCurrentUser) {
            echo '<div class="message-avatar">';
            if (!empty($row['profile_image_blob'])) {
                $imageData = base64_encode($row['profile_image_blob']);
                $imageType = $row['profile_image_type'] ?? 'image/jpeg';
                echo '<img src="data:' . $imageType . ';base64,' . $imageData . '" alt="' . $displayName . '">';
            } else {
                echo strtoupper(substr($displayName, 0, 1));
            }
            echo '</div>';
        }
        echo '<div class="message-content">';
        if (!$isCurrentUser) {
            echo '<div class="message-sender">' . $displayName . '</div>';
        } else {
            echo '<div class="message-sender current-user">Anda</div>';
        }
        echo '<div class="message-bubble-container">';
        echo '<div class="message-bubble ' . ($isCurrentUser ? 'user' : 'other') . '" data-message-id="' . $feedbackId . '" data-message-text="' . htmlspecialchars($row['komentar']) . '" data-username="' . $displayName . '">';
        $messageText = trim($row['komentar']);
        if ($messageText !== '') {
            echo nl2br(htmlspecialchars($messageText));
        }
        $imgStmt = $conn->prepare("SELECT id, image_path, image_type, image_blob FROM feedback_images WHERE feedback_id = ? ORDER BY urutan ASC");
        $imgStmt->bind_param("i", $feedbackId);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        if ($imgResult->num_rows > 0) {
            if ($messageText !== '') echo '<br><br>';
            echo '<div class="message-images-grid">';
            while ($imgRow = $imgResult->fetch_assoc()) {
                $src = buildDataUrl($imgRow['image_type'] ?? null, $imgRow['image_blob'] ?? null);
                if (!$src) $src = $imgRow['image_path'];
                $imagePath = htmlspecialchars($src);
                echo '<img src="' . $imagePath . '" class="message-image" data-image-id="' . (int)$imgRow['id'] . '" alt="Uploaded image" onclick="openImageModal(this.src)">';
            }
            echo '</div>';
        }
        $imgStmt->close();
        echo '</div>'; 
        echo '<div class="message-actions">';
        echo '<button class="action-btn" title="Balas" onclick="replyMessage(this)"><i class="fas fa-reply"></i></button>';
        echo '<button class="action-btn" title="Teruskan" onclick="forwardMessage(this)"><i class="fas fa-share"></i></button>';
        echo '<button class="action-btn" title="Reaksi"><i class="far fa-smile"></i></button>';
        echo '<button class="action-btn action-menu-btn" title="Opsi Lainnya" data-is-owner="' . ($isCurrentUser ? '1' : '0') . '">';
        echo '<i class="fas fa-ellipsis-v"></i>';
        echo '</button>';
        echo '</div>';
        echo '<div class="message-dropdown-menu" style="display: none;">';
        echo '<button class="dropdown-item" onclick="copyMessageText(this)">';
        echo '<i class="fas fa-copy"></i> Salin Teks';
        echo '</button>';
        if ($isCurrentUser) {
            echo '<button class="dropdown-item" onclick="editMessage(' . $feedbackId . ', this)">';
            echo '<i class="fas fa-edit"></i> Edit Pesan';
            echo '</button>';
            echo '<button class="dropdown-item delete-item" onclick="deleteMessage(' . $feedbackId . ')">';
            echo '<i class="fas fa-trash"></i> Hapus Pesan';
            echo '</button>';
        }
        echo '</div>';
        echo '</div>'; 
        $timestamp = date('H:i', strtotime($row['tanggal']));
        echo '<div class="message-time">' . $timestamp . '</div>';
        echo '</div>'; 
        echo '</div>'; 
    }
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    $stmt->close();
    exit();
}
if ($action === 'record_visit') {
    $groupType = $_POST['group_type'] ?? '';
    if (!in_array($groupType, ['bug', 'inspiration', 'general'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid group type']);
        exit();
    }
    $stmt = $conn->prepare("
        INSERT INTO feedback_online (username, group_type, last_seen, first_visit_at) 
        VALUES (?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            first_visit_at = IFNULL(first_visit_at, NOW()),
            last_seen = NOW()
    ");
    $stmt->bind_param("ss", $user, $groupType);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record visit']);
    }
    $stmt->close();
    exit();
}
if ($action === 'check_visit') {
    $groupType = $_GET['group_type'] ?? '';
    if (!in_array($groupType, ['bug', 'inspiration', 'general'])) {
        echo json_encode(['visited' => false, 'error' => 'Invalid group type']);
        exit();
    }
    $stmt = $conn->prepare("SELECT first_visit_at FROM feedback_online WHERE username = ? AND group_type = ?");
    $stmt->bind_param("ss", $user, $groupType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $visited = ($row['first_visit_at'] !== null);
    } else {
        $visited = false;
    }
    echo json_encode(['visited' => $visited]);
    $stmt->close();
    exit();
}
if ($action === 'migrate') {
    $migrations = [];
    $errors = [];
    $checkTable = $conn->query("SHOW TABLES LIKE 'feedback_images'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $migrations['feedback_images_create'] = 'skipped';
    } else {
                $sql = "CREATE TABLE IF NOT EXISTS feedback_images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    feedback_id INT NOT NULL,
                    image_path VARCHAR(500) NOT NULL DEFAULT '',
                    image_blob LONGTEXT NULL,
                    image_type VARCHAR(100) NULL,
                    urutan INT NOT NULL DEFAULT 1,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
                    KEY idx_feedback_images (feedback_id, urutan)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if ($conn->query($sql)) {
            $migrations['feedback_images_create'] = 'success';
            $migrateQuery = "SELECT id, komentar FROM feedback WHERE komentar LIKE '%[IMG:%'";
            $result = $conn->query($migrateQuery);
            $migrated = 0;
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $feedbackId = $row['id'];
                    $komentar = $row['komentar'];
                    preg_match_all('/\[IMG:(.*?)\]/', $komentar, $matches);
                    if (!empty($matches[1])) {
                        $urutan = 1;
                        foreach ($matches[1] as $imagePath) {
                            $insertStmt = $conn->prepare("INSERT INTO feedback_images (feedback_id, image_path, urutan) VALUES (?, ?, ?)");
                            $insertStmt->bind_param("isi", $feedbackId, $imagePath, $urutan);
                            $insertStmt->execute();
                            $insertStmt->close();
                            $urutan++;
                            $migrated++;
                        }
                        $cleanText = preg_replace('/\[IMG:.*?\]\n?/', '', $komentar);
                        $cleanText = trim($cleanText);
                        $updateStmt = $conn->prepare("UPDATE feedback SET komentar = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->bind_param("si", $cleanText, $feedbackId);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                }
                $migrations['feedback_images_migrate'] = "success ($migrated images)";
            }
        } else {
            $errors[] = "feedback_images table creation failed";
            $migrations['feedback_images_create'] = 'failed';
        }
    }
    $checkColumn = $conn->query("SHOW COLUMNS FROM feedback_online LIKE 'first_visit_at'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $migrations['first_visit_column'] = 'skipped';
    } else {
        $sql = "ALTER TABLE feedback_online ADD COLUMN first_visit_at TIMESTAMP NULL DEFAULT NULL AFTER last_seen";
        if ($conn->query($sql)) {
            $migrations['first_visit_column'] = 'success';
            $updateSql = "UPDATE feedback_online SET first_visit_at = last_seen WHERE first_visit_at IS NULL";
            if ($conn->query($updateSql)) {
                $rowCount = $conn->affected_rows;
                $migrations['first_visit_update'] = "success ($rowCount records)";
            } else {
                $migrations['first_visit_update'] = 'warning';
            }
        } else {
            $errors[] = "first_visit_at column addition failed";
            $migrations['first_visit_column'] = 'failed';
        }
    }
    $checkGroupType = $conn->query("SHOW COLUMNS FROM feedback LIKE 'group_type'");
    if ($checkGroupType && $checkGroupType->num_rows > 0) {
        $migrations['group_type_column'] = 'skipped';
    } else {
        $checkAttachment = $conn->query("SHOW COLUMNS FROM feedback LIKE 'attachment'");
        $afterColumn = ($checkAttachment && $checkAttachment->num_rows > 0) ? 'attachment' : 'komentar';
        $sql = "ALTER TABLE feedback ADD COLUMN group_type VARCHAR(50) NOT NULL DEFAULT 'bug' AFTER $afterColumn";
        if ($conn->query($sql)) {
            $migrations['group_type_column'] = 'success';
            $updateSql = "UPDATE feedback SET group_type = 'bug' WHERE group_type = ''";
            if ($conn->query($updateSql)) {
                $rowCount = $conn->affected_rows;
                $migrations['group_type_update'] = "success ($rowCount records)";
            } else {
                $migrations['group_type_update'] = 'warning';
            }
            $conn->query("CREATE INDEX IF NOT EXISTS idx_group_type ON feedback(group_type)");
        } else {
            $errors[] = "group_type column addition failed: " . $conn->error;
            $migrations['group_type_column'] = 'failed';
        }
    }
    echo json_encode([
        'success' => empty($errors),
        'migrations' => $migrations,
        'errors' => $errors
    ]);
    exit();
}
if ($action === 'update_old_users') {
    $result = [];
    $updateQuery = "UPDATE feedback_online SET first_visit_at = last_seen WHERE first_visit_at IS NULL";
    if ($conn->query($updateQuery)) {
        $result['updated'] = $conn->affected_rows;
        $result['success'] = true;
        $result['message'] = "Updated {$conn->affected_rows} old users successfully";
    } else {
        $result['success'] = false;
        $result['error'] = $conn->error;
    }
    echo json_encode($result);
    exit();
}
echo json_encode(['error' => 'unknown_action']);
