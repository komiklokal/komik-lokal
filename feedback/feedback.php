<?php
session_start();
include("config.php");
if (!isset($_SESSION['username'])) {
    header("Location: ../login/login.php");
    exit();
}
$username = $_SESSION['username'];
$csrfToken = csrf_token();
$allowedGroups = ['bug', 'inspiration', 'general'];

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

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): void {
    $tableEsc = $conn->real_escape_string($table);
    $colEsc = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'");
    if ($check && $check->num_rows > 0) return;
    $conn->query("ALTER TABLE `{$tableEsc}` ADD COLUMN `{$column}` {$definition}");
}

function ensureFeedbackChatSchema(mysqli $conn): void {
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
    ensureColumnExists($conn, 'feedback', 'group_type', "VARCHAR(50) NOT NULL DEFAULT 'bug' AFTER `{$afterColumn}`");
    $checkTanggal = $conn->query("SHOW COLUMNS FROM feedback LIKE 'tanggal'");
    $afterUpdatedAt = ($checkTanggal && $checkTanggal->num_rows > 0) ? 'tanggal' : 'group_type';
    ensureColumnExists($conn, 'feedback', 'updated_at', "TIMESTAMP NULL DEFAULT NULL AFTER `{$afterUpdatedAt}`");
}

try {
    ensureFeedbackChatSchema($conn);
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() !== 1142) {
        throw $e;
    }
}
$currentGroup = $_GET['group'] ?? 'bug';
if (!in_array($currentGroup, $allowedGroups, true)) {
    $currentGroup = 'bug';
}
$userProfileQuery = $conn->query("SELECT profile_image_type, profile_image_blob FROM user WHERE user_nama = '$username'");
$userProfile = $userProfileQuery ? $userProfileQuery->fetch_assoc() : null;
$userHasImage = (!empty($userProfile) && !empty($userProfile['profile_image_blob']));
$showWelcome = false;
$hasMessages = $conn->query("SELECT COUNT(*) as total FROM feedback WHERE username = '$username'")->fetch_assoc()['total'];
if ($hasMessages > 0) {
    $showWelcome = false;
    $conn->query("
        INSERT INTO feedback_online (username, group_type, last_seen, first_visit_at) 
        VALUES ('$username', '$currentGroup', NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            first_visit_at = IFNULL(first_visit_at, NOW())
    ");
} else {
    $visitCheck = $conn->query("SELECT first_visit_at FROM feedback_online WHERE username = '$username' AND group_type = '$currentGroup'");
    if ($visitCheck && $visitCheck->num_rows > 0) {
        $visitData = $visitCheck->fetch_assoc();
        if ($visitData['first_visit_at'] === null) {
            $conn->query("UPDATE feedback_online SET first_visit_at = NOW() WHERE username = '$username' AND group_type = '$currentGroup'");
            $showWelcome = true;
        } else {
            $showWelcome = false;
        }
    } else {
        $showWelcome = true;
    }
}
$conn->query("
    INSERT IGNORE INTO feedback_read (username, feedback_id)
    SELECT '$username', id FROM feedback
");
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM user")->fetch_assoc()['total'];
$conn->query("DELETE FROM feedback_online WHERE last_seen < DATE_SUB(NOW(), INTERVAL 15 SECOND)");
$bugOnline = $conn->query("SELECT COUNT(DISTINCT username) as total FROM feedback_online WHERE group_type = 'bug'")->fetch_assoc()['total'];
$inspirationOnline = $conn->query("SELECT COUNT(DISTINCT username) as total FROM feedback_online WHERE group_type = 'inspiration'")->fetch_assoc()['total'];
$generalOnline = $conn->query("SELECT COUNT(DISTINCT username) as total FROM feedback_online WHERE group_type = 'general'")->fetch_assoc()['total'];
$unreadCount = $conn->query("
    SELECT COUNT(*) as total 
    FROM feedback f
    LEFT JOIN feedback_read fr ON f.id = fr.feedback_id AND fr.username = '$username'
    WHERE fr.id IS NULL AND f.username != '$username'
")->fetch_assoc()['total'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['komentar'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.";
    } else {
    $komentar = trim($_POST['komentar']);
    $groupType = $_POST['group_type'] ?? 'bug';
    if (!in_array($groupType, $allowedGroups, true)) {
        $groupType = 'bug';
    }
    $imagePayloads = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize = 5 * 1024 * 1024; 
        foreach ($_FILES['images']['name'] as $key => $name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileInfo = pathinfo($name);
                $extension = strtolower($fileInfo['extension']);
                if (!in_array($extension, $allowedExtensions)) {
                    continue;
                }
                if ($_FILES['images']['size'][$key] > $maxFileSize) {
                    continue;
                }
                $tmp = $_FILES['images']['tmp_name'][$key];
                $mime = (function_exists('mime_content_type') ? @mime_content_type($tmp) : null) ?: ($_FILES['images']['type'][$key] ?? null);
                if (!$mime || strpos($mime, 'image/') !== 0) {
                    continue;
                }
                $base64 = base64_encode(file_get_contents($tmp));
                $imagePayloads[] = ['type' => $mime, 'blob' => $base64];
            }
        }
    }
    if ($komentar !== '' || !empty($imagePayloads)) {
        $stmt = $conn->prepare("INSERT INTO feedback (username, komentar, group_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $komentar, $groupType);
        $stmt->execute();
        $feedbackId = $conn->insert_id;
        $stmt->close();
        if (!empty($imagePayloads)) {
            $imgStmt = $conn->prepare("INSERT INTO feedback_images (feedback_id, image_path, image_type, image_blob, urutan) VALUES (?, ?, ?, ?, ?)");
            $urutan = 1;
            $emptyPath = '';
            foreach ($imagePayloads as $p) {
                $imageType = $p['type'];
                $imageBlob = $p['blob'];
                $imgStmt->bind_param("isssi", $feedbackId, $emptyPath, $imageType, $imageBlob, $urutan);
                $imgStmt->execute();
                $urutan++;
            }
            $imgStmt->close();
        }
        if (isset($_POST['reply_to_message_id']) && is_numeric($_POST['reply_to_message_id'])) {
            $replyToMessageId = intval($_POST['reply_to_message_id']);
            $replyStmt = $conn->prepare("INSERT INTO message_replies (message_id, reply_to_message_id) VALUES (?, ?)");
            $replyStmt->bind_param("ii", $feedbackId, $replyToMessageId);
            $replyStmt->execute();
            $replyStmt->close();
        }
        if (isset($_POST['forwarded_from_message_id']) && is_numeric($_POST['forwarded_from_message_id'])) {
            $forwardedFromMessageId = intval($_POST['forwarded_from_message_id']);
            $forwardedFromGroup = $_POST['forwarded_from_group'] ?? 'bug';
            $forwardStmt = $conn->prepare("INSERT INTO message_forwards (message_id, forwarded_from_message_id, forwarded_from_group) VALUES (?, ?, ?)");
            $forwardStmt->bind_param("iis", $feedbackId, $forwardedFromMessageId, $forwardedFromGroup);
            $forwardStmt->execute();
            $forwardStmt->close();
        }
        header("Location: feedback.php?group=" . urlencode($groupType));
        exit();
    } else {
        $error = "Pesan atau gambar harus diisi!";
    }
    }
}
$feedbackData = $conn->query("
    SELECT f.*, u.profile_image_type, u.profile_image_blob 
    FROM feedback f 
    LEFT JOIN user u ON f.username = u.user_nama 
    WHERE f.group_type = '$currentGroup'
    ORDER BY f.tanggal ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FeedbackHub - Pembahasan Bug</title>
    <link rel="stylesheet" href="feedback.css?v=<?php echo @filemtime('feedback.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../dark-mode.css?v=<?php echo @filemtime('../dark-mode.css') ?: '1'; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="../theme.js?v=<?php echo @filemtime('../theme.js') ?: '1'; ?>"></script>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
    <style>
        #feedbackPageLoadingOverlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            z-index: 20000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .page-loading #feedbackPageLoadingOverlay {
            opacity: 1;
            visibility: visible;
        }
        .page-loading #feedbackContent {
            visibility: hidden;
        }
        .feedback-loading-card {
            width: 90%;
            max-width: 420px;
            background: var(--bg-secondary, #ffffff);
            color: var(--text-primary, #2d3748);
            border-radius: 18px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }
        .feedback-loading-spinner {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            border: 6px solid rgba(148, 163, 184, 0.35);
            border-top-color: var(--accent-color, #667eea);
            margin: 0 auto 1rem;
            animation: feedbackSpin 0.9s linear infinite;
        }
        .feedback-loading-title {
            font-weight: 800;
            font-size: 1.25rem;
            margin-bottom: 0.35rem;
        }
        .feedback-loading-sub {
            color: var(--text-secondary, #4a5568);
            font-weight: 600;
        }
        @keyframes feedbackSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        #feedbackModalOverlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            z-index: 21000;
            padding: 16px;
        }
        #feedbackModalOverlay.is-open {
            display: flex;
        }
        .feedback-modal {
            width: 100%;
            max-width: 460px;
            background: var(--bg-secondary, #ffffff);
            color: var(--text-primary, #2d3748);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .feedback-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
        }
        .feedback-modal-title {
            font-weight: 800;
            font-size: 1.05rem;
            margin: 0;
        }
        .feedback-modal-close {
            appearance: none;
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 10px;
        }
        .feedback-modal-body {
            padding: 16px 18px;
            color: var(--text-secondary, #4a5568);
            font-weight: 600;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .feedback-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 18px 18px;
        }
        .feedback-modal-btn {
            appearance: none;
            border: 0;
            cursor: pointer;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 800;
        }
        .feedback-modal-btn.secondary {
            background: rgba(148, 163, 184, 0.20);
            color: var(--text-primary, #2d3748);
        }
        .feedback-modal-btn.primary {
            background: var(--accent-color, #667eea);
            color: #fff;
        }
    </style>
</head>
<body>
<div id="feedbackPageLoadingOverlay" aria-hidden="true">
    <div class="feedback-loading-card" role="status" aria-live="polite">
        <div class="feedback-loading-spinner"></div>
        <div class="feedback-loading-title">Memuat Feedback...</div>
        <div class="feedback-loading-sub">Tunggu sesuai koneksi internet Anda</div>
    </div>
</div>
<div id="feedbackModalOverlay" aria-hidden="true">
    <div class="feedback-modal" role="dialog" aria-modal="true" aria-labelledby="feedbackModalTitle">
        <div class="feedback-modal-header">
            <h3 class="feedback-modal-title" id="feedbackModalTitle">Info</h3>
            <button type="button" class="feedback-modal-close" onclick="resolveFeedbackModal(false)" aria-label="Tutup">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="feedback-modal-body" id="feedbackModalBody"></div>
        <div class="feedback-modal-actions">
            <button type="button" class="feedback-modal-btn secondary" id="feedbackModalCancel" onclick="resolveFeedbackModal(false)">Batal</button>
            <button type="button" class="feedback-modal-btn primary" id="feedbackModalOk" onclick="resolveFeedbackModal(true)">OK</button>
        </div>
    </div>
</div>
<div id="feedbackContent">
<div class="chat-container">
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-comments"></i>
                <span>FeedbackHub</span>
            </div>
            <button class="sidebar-close" onclick="closeSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="groups-section-title">
            <i class="fas fa-layer-group"></i> Grup Diskusi
        </div>
        <div class="groups-list">
            <div class="group-item <?= $currentGroup === 'bug' ? 'active' : '' ?>" data-group="bug" onclick="switchGroup('bug')">
                <div class="group-icon bug">
                    <i class="fas fa-bug"></i>
                </div>
                <div class="group-info">
                    <div class="group-name">Pembahasan Bug</div>
                    <div class="group-desc">
                        <i class="fas fa-circle online-dot"></i> <?= $bugOnline ?> online
                    </div>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>
            <div class="group-item <?= $currentGroup === 'inspiration' ? 'active' : '' ?>" data-group="inspiration" onclick="switchGroup('inspiration')">
                <div class="group-icon inspiration">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="group-info">
                    <div class="group-name">Pembahasan Inspirasi</div>
                    <div class="group-desc">
                        <i class="fas fa-circle online-dot"></i> <?= $inspirationOnline ?> online
                    </div>
                </div>
            </div>
            <div class="group-item <?= $currentGroup === 'general' ? 'active' : '' ?>" data-group="general" onclick="switchGroup('general')">
                <div class="group-icon general">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="group-info">
                    <div class="group-name">Pembahasan Umum</div>
                    <div class="group-desc">
                        <i class="fas fa-circle online-dot"></i> <?= $generalOnline ?> online
                    </div>
                </div>
            </div>
        </div>
        <div class="user-profile">
            <div class="user-avatar">
                <?php if ($userHasImage): ?>
                    <img src="data:<?= $userProfile['profile_image_type'] ?>;base64,<?= base64_encode($userProfile['profile_image_blob']) ?>" alt="<?= htmlspecialchars($username) ?>">
                <?php else: ?>
                    <?= strtoupper(substr($username, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($username) ?></div>
                <div class="user-status">Online</div>
            </div>
            <a href="../dashboard/dashboard.php" class="settings-icon" title="Kembali ke Profil">
                <i class="fas fa-home"></i>
            </a>
        </div>
    </div>
    <div class="chat-area">
        <div class="chat-header">
            <button class="burger-menu" id="burgerMenu" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="chat-header-info">
                <?php
                $groupIcons = [
                    'bug' => 'fa-bug',
                    'inspiration' => 'fa-lightbulb',
                    'general' => 'fa-comments'
                ];
                $groupTitles = [
                    'bug' => 'Pembahasan Bug',
                    'inspiration' => 'Pembahasan Inspirasi',
                    'general' => 'Pembahasan Umum'
                ];
                $groupOnline = [
                    'bug' => $bugOnline,
                    'inspiration' => $inspirationOnline,
                    'general' => $generalOnline
                ];
                ?>
                <div class="group-icon-large <?= $currentGroup ?>" id="headerIcon">
                    <i class="fas <?= $groupIcons[$currentGroup] ?>"></i>
                </div>
                <div>
                    <h2 id="headerTitle"><?= $groupTitles[$currentGroup] ?></h2>
                    <p id="headerInfo"><i class="fas fa-users"></i> <?= $totalUsers ?> anggota &middot; <i class="fas fa-circle"></i> <span id="onlineCount"><?= $groupOnline[$currentGroup] ?></span> online</p>
                </div>
            </div>
            <div class="chat-header-actions">
                <button class="icon-btn"><i class="fas fa-search"></i></button>
                <button class="icon-btn"><i class="fas fa-user"></i></button>
                <button class="icon-btn"><i class="fas fa-ellipsis-v"></i></button>
            </div>
        </div>
        <div class="welcome-message" id="welcomeMessage" style="display: <?= $showWelcome ? 'block' : 'none' ?>;">
            <?php
            $groupWelcome = [
                'bug' => 'Selamat datang di grup Pembahasan Bug! Silakan laporkan bug yang Anda temui di sini.',
                'inspiration' => 'Selamat datang di grup Pembahasan Inspirasi! Bagikan ide-ide kreatif Anda di sini.',
                'general' => 'Selamat datang di grup Pembahasan Umum! Diskusikan hal-hal umum tentang produk kami di sini.'
            ];
            ?>
            <p><?= $groupWelcome[$currentGroup] ?></p>
        </div>
        <div class="messages-container" id="messagesContainer">
            <?php 
            $messages = [];
            $feedbackData->data_seek(0);
            while ($row = $feedbackData->fetch_assoc()) {
                $messages[] = $row;
            }
            $lastDateKey = null;
            foreach ($messages as $msg): 
                $isCurrentUser = ($msg['username'] === $username);
                $messageClass = $isCurrentUser ? 'message-right' : 'message-left';
                $messageDate = new DateTime($msg['tanggal']);
                $dateKey = $messageDate->format('Y-m-d');
                if ($dateKey !== $lastDateKey):
                    $lastDateKey = $dateKey;
            ?>
                <div class="chat-date-separator" data-date="<?= htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8') ?>">
                    <span><?= htmlspecialchars(formatChatDayLabel($messageDate)) ?></span>
                </div>
            <?php
                endif;
            ?>
                <div class="message-wrapper <?= $messageClass ?>">
                    <?php if (!$isCurrentUser): ?>
                        <div class="message-avatar">
                            <?php if (!empty($msg['profile_image_blob'])): ?>
                                <img src="data:<?= $msg['profile_image_type'] ?>;base64,<?= base64_encode($msg['profile_image_blob']) ?>" alt="<?= htmlspecialchars($msg['username']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($msg['username'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="message-content">
                        <?php if (!$isCurrentUser): ?>
                            <div class="message-sender"><?= htmlspecialchars($msg['username']) ?></div>
                        <?php else: ?>
                            <div class="message-sender current-user">Anda</div>
                        <?php endif; ?>
                        <div class="message-bubble-container">
                            <div class="message-bubble <?= $isCurrentUser ? 'user' : 'other' ?>" data-message-id="<?= $msg['id'] ?>" data-message-text="<?= htmlspecialchars($msg['komentar']) ?>" data-username="<?= htmlspecialchars($msg['username']) ?>">
                                <?php
                                $replyQuery = $conn->prepare("
                                    SELECT r.reply_to_message_id, f.username, f.komentar 
                                    FROM message_replies r 
                                    JOIN feedback f ON r.reply_to_message_id = f.id 
                                    WHERE r.message_id = ?
                                ");
                                $replyQuery->bind_param("i", $msg['id']);
                                $replyQuery->execute();
                                $replyResult = $replyQuery->get_result();
                                if ($replyResult->num_rows > 0) {
                                    $replyData = $replyResult->fetch_assoc();
                                    $replyToUser = htmlspecialchars($replyData['username']);
                                    $replyToMessage = htmlspecialchars($replyData['komentar']);
                                    $replyToMessageId = $replyData['reply_to_message_id'];
                                    $replyPreview = mb_strlen($replyToMessage) > 100 ? mb_substr($replyToMessage, 0, 100) . '...' : $replyToMessage;
                                    echo '<div class="reply-quote" data-reply-to-id="' . $replyToMessageId . '" onclick="scrollToMessage(' . $replyToMessageId . ')">';
                                    echo '<div class="reply-quote-header">';
                                    echo '<i class="fas fa-reply"></i> ' . $replyToUser;
                                    echo '</div>';
                                    echo '<div class="reply-quote-text">' . nl2br($replyPreview) . '</div>';
                                    echo '</div>';
                                }
                                $replyQuery->close();
                                $forwardQuery = $conn->prepare("
                                    SELECT fw.forwarded_from_message_id, fw.forwarded_from_group, f.username, f.komentar 
                                    FROM message_forwards fw 
                                    JOIN feedback f ON fw.forwarded_from_message_id = f.id 
                                    WHERE fw.message_id = ?
                                ");
                                $forwardQuery->bind_param("i", $msg['id']);
                                $forwardQuery->execute();
                                $forwardResult = $forwardQuery->get_result();
                                if ($forwardResult->num_rows > 0) {
                                    $forwardData = $forwardResult->fetch_assoc();
                                    $forwardFromUser = htmlspecialchars($forwardData['username']);
                                    $forwardFromMessageId = $forwardData['forwarded_from_message_id'];
                                    echo '<div class="forward-info" data-forward-from-id="' . $forwardFromMessageId . '">';
                                    echo '<i class="fas fa-share"></i> Diteruskan dari <strong>' . $forwardFromUser . '</strong>';
                                    echo '</div>';
                                }
                                $forwardQuery->close();
                                $messageText = trim($msg['komentar']);
                                if ($messageText !== '') {
                                    echo nl2br(htmlspecialchars($messageText));
                                    if ($msg['updated_at'] !== null) {
                                        echo ' <span class="edited-badge">(edited)</span>';
                                    }
                                }
                                $imagesQuery = $conn->prepare("SELECT id, image_path, image_type, image_blob FROM feedback_images WHERE feedback_id = ? ORDER BY urutan ASC");
                                $imagesQuery->bind_param("i", $msg['id']);
                                $imagesQuery->execute();
                                $imagesResult = $imagesQuery->get_result();
                                $hasImages = $imagesResult->num_rows > 0;
                                if ($hasImages) {
                                    if ($messageText !== '') echo '<br><br>';
                                    echo '<div class="message-images-grid">';
                                    while ($imgRow = $imagesResult->fetch_assoc()) {
                                        $src = '';
                                        if (!empty($imgRow['image_blob'])) {
                                            $mime = $imgRow['image_type'] ?: 'image/jpeg';
                                            $src = 'data:' . $mime . ';base64,' . $imgRow['image_blob'];
                                        } else {
                                            $src = $imgRow['image_path'];
                                        }
                                        echo '<img src="' . htmlspecialchars($src) . '" class="message-image" data-image-id="' . (int)$imgRow['id'] . '" alt="Uploaded image" onclick="openImageModal(this.src)">';
                                    }
                                    echo '</div>';
                                }
                                $imagesQuery->close();
                                ?>
                            </div>
                            <div class="message-reactions"></div>
                            <div class="message-actions">
                                <button class="action-btn" title="Balas" onclick="replyMessage(this)"><i class="fas fa-reply"></i></button>
                                <button class="action-btn" title="Teruskan" onclick="forwardMessage(this)"><i class="fas fa-share"></i></button>
                                <button class="action-btn emoji-reaction-btn" title="Reaksi" onclick="showEmojiReaction(this)"><i class="far fa-smile"></i></button>
                                <button class="action-btn action-menu-btn" title="Opsi Lainnya" data-is-owner="<?= $isCurrentUser ? '1' : '0' ?>">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                            <div class="emoji-reaction-picker" style="display: none;">
                                <button class="emoji-reaction-item" onclick="addReaction(this, '👌')">👌</button>
                                <button class="emoji-reaction-item" onclick="addReaction(this, '👎')">👎</button>
                                <button class="emoji-reaction-item" onclick="addReaction(this, '👍')">👍</button>
                                <button class="emoji-reaction-item" onclick="addReaction(this, '👏')">👏</button>
                                <button class="emoji-reaction-item" onclick="addReaction(this, '🤝')">🤝</button>
                                <button class="emoji-reaction-item" onclick="addReaction(this, '💡')">💡</button>
                            </div>
                            <div class="message-dropdown-menu" style="display: none;">
                                <button class="dropdown-item" onclick="copyMessageText(this)">
                                    <i class="fas fa-copy"></i> Salin Teks
                                </button>
                                <?php if ($isCurrentUser): ?>
                                    <button class="dropdown-item" onclick="editMessage(<?= $msg['id'] ?>, this)">
                                        <i class="fas fa-edit"></i> Edit Pesan
                                    </button>
                                    <button class="dropdown-item delete-item" onclick="deleteMessage(<?= $msg['id'] ?>)">
                                        <i class="fas fa-trash"></i> Hapus Pesan
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="message-time">
                            <?php
                            $date = new DateTime($msg['tanggal']);
                            echo $date->format('H:i');
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="message-input">
            <div id="imagePreviewContainer" style="display: none;"></div>
            <div id="actionNotification" class="action-notification" style="display: none;">
                <div class="action-notification-content">
                    <i class="action-notification-icon"></i>
                    <span class="action-notification-text"></span>
                </div>
                <button class="action-notification-close" onclick="cancelAction()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="messageForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="group_type" id="groupTypeInput" value="<?= $currentGroup ?>">
                <input type="file" id="imageInput" name="images[]" accept="image/*" multiple style="display: none;">
                <button type="button" class="attach-btn" onclick="document.getElementById('imageInput').click()">
                    <i class="fas fa-paperclip"></i>
                </button>
                <button type="button" class="emoji-btn" id="emojiBtn">
                    <i class="fas fa-smile"></i>
                </button>
                <div class="emoji-picker" id="emojiPicker" style="display: none;">
                    <button type="button" class="emoji-item" onclick="insertEmoji('👌')">👌</button>
                    <button type="button" class="emoji-item" onclick="insertEmoji('👎')">👎</button>
                    <button type="button" class="emoji-item" onclick="insertEmoji('👍')">👍</button>
                    <button type="button" class="emoji-item" onclick="insertEmoji('👏')">👏</button>
                    <button type="button" class="emoji-item" onclick="insertEmoji('🤝')">🤝</button>
                    <button type="button" class="emoji-item" onclick="insertEmoji('💡')">💡</button>
                </div>
                <div class="input-wrapper" id="inputWrapper">
                    <input type="text" name="komentar" id="komentarInput" placeholder="Ketik pesan Anda..." autocomplete="off">
                </div>
                <button type="submit" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>
</div>
<script>
    function revealFeedbackPage() {
        document.documentElement.classList.remove('page-loading');
    }
    window.addEventListener('load', revealFeedbackPage);
    window.addEventListener('pageshow', revealFeedbackPage);
</script>
<script>
let originalBugMessages = '';
let currentGroup = '<?= $currentGroup ?>';
const FEEDBACK_CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>';
let heartbeatInterval;
let onlineCounts = {
    bug: <?= $bugOnline ?>,
    inspiration: <?= $inspirationOnline ?>,
    general: <?= $generalOnline ?>
};
let __feedbackModalResolver = null;
function setFeedbackLoadingText(title, sub) {
    const overlay = document.getElementById('feedbackPageLoadingOverlay');
    if (!overlay) return;
    const titleEl = overlay.querySelector('.feedback-loading-title');
    const subEl = overlay.querySelector('.feedback-loading-sub');
    if (titleEl && typeof title === 'string') titleEl.textContent = title;
    if (subEl && typeof sub === 'string') subEl.textContent = sub;
}
function showFeedbackLoading(title = 'Memuat...', sub = 'Tunggu sebentar') {
    setFeedbackLoadingText(title, sub);
    document.documentElement.classList.add('page-loading');
}
function hideFeedbackLoading() {
    document.documentElement.classList.remove('page-loading');
}
function waitTwoFrames() {
    return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
}
function formatSeparatorLabel(dateKey) {
    if (!dateKey) return '';
    const target = new Date(dateKey + 'T00:00:00');
    if (Number.isNaN(target.getTime())) return dateKey;

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const dayMs = 24 * 60 * 60 * 1000;
    const diffDays = Math.round((target.getTime() - today.getTime()) / dayMs);

    if (diffDays === 0) return 'Hari ini';
    if (diffDays === -1) return 'Kemarin';

    if (diffDays >= -6 && diffDays < 0) {
        const names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return names[target.getDay()] || dateKey;
    }

    return `${target.getDate()}/${target.getMonth() + 1}/${target.getFullYear()}`;
}
function refreshDateSeparators() {
    const separators = document.querySelectorAll('.chat-date-separator[data-date]');
    separators.forEach((el) => {
        const labelEl = el.querySelector('span');
        if (!labelEl) return;
        labelEl.textContent = formatSeparatorLabel(el.dataset.date || '');
    });
}
function openFeedbackModal({ title = 'Info', message = '', confirmText = 'OK', cancelText = 'Batal', showCancel = false } = {}) {
    const overlay = document.getElementById('feedbackModalOverlay');
    const titleEl = document.getElementById('feedbackModalTitle');
    const bodyEl = document.getElementById('feedbackModalBody');
    const btnCancel = document.getElementById('feedbackModalCancel');
    const btnOk = document.getElementById('feedbackModalOk');
    if (!overlay || !titleEl || !bodyEl || !btnCancel || !btnOk) return;
    titleEl.textContent = title;
    bodyEl.textContent = message;
    btnOk.textContent = confirmText;
    btnCancel.textContent = cancelText;
    btnCancel.style.display = showCancel ? 'inline-flex' : 'none';
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    setTimeout(() => {
        btnOk.focus();
    }, 0);
}
function closeFeedbackModal() {
    const overlay = document.getElementById('feedbackModalOverlay');
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
}
function resolveFeedbackModal(result) {
    const resolver = __feedbackModalResolver;
    __feedbackModalResolver = null;
    closeFeedbackModal();
    if (typeof resolver === 'function') resolver(!!result);
}
function feedbackAlert(message, title = 'Info') {
    __feedbackModalResolver = null;
    openFeedbackModal({ title, message, confirmText: 'OK', showCancel: false });
}
function feedbackConfirm(message, title = 'Konfirmasi', confirmText = 'Ya', cancelText = 'Batal') {
    return new Promise(resolve => {
        __feedbackModalResolver = resolve;
        openFeedbackModal({ title, message, confirmText, cancelText, showCancel: true });
    });
}

<?php if (!empty($error)): ?>
window.addEventListener('DOMContentLoaded', function() {
    feedbackAlert(<?= json_encode($error) ?>, 'Gagal');
});
<?php endif; ?>
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    const overlay = document.getElementById('feedbackModalOverlay');
    if (overlay && overlay.classList.contains('is-open')) {
        resolveFeedbackModal(false);
    }
});
const __feedbackModalOverlayEl = document.getElementById('feedbackModalOverlay');
if (__feedbackModalOverlayEl) {
    __feedbackModalOverlayEl.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'feedbackModalOverlay') {
            resolveFeedbackModal(false);
        }
    });
}
<?php if ($hasMessages > 0): ?>
let visitedGroups = {
    bug: true,
    inspiration: true,
    general: true
};
<?php else: ?>
let visitedGroups = {
    bug: <?= ($currentGroup === 'bug' && $showWelcome) ? 'false' : 'true' ?>,
    inspiration: <?= ($currentGroup === 'inspiration' && $showWelcome) ? 'false' : 'true' ?>,
    general: <?= ($currentGroup === 'general' && $showWelcome) ? 'false' : 'true' ?>
};
<?php endif; ?>
const messagesContainer = document.getElementById('messagesContainer');
messagesContainer.scrollTop = messagesContainer.scrollHeight;
document.addEventListener('DOMContentLoaded', function() {
    originalBugMessages = messagesContainer.innerHTML;
    startHeartbeat();
    refreshDateSeparators();
    setInterval(refreshDateSeparators, 60000);
    const welcomeMessage = document.getElementById('welcomeMessage');
    if (welcomeMessage && welcomeMessage.style.display !== 'none') {
        setTimeout(() => {
            welcomeMessage.style.display = 'none';
            visitedGroups.bug = true;
        }, 5000);
    } else {
        visitedGroups.bug = true;
    }
});
function sendHeartbeat() {
    const formData = new FormData();
    formData.append('action', 'heartbeat');
    formData.append('group_type', currentGroup);
    fetch('feedback_action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.online) {
            onlineCounts = data.online;
            document.getElementById('onlineCount').textContent = data.online[currentGroup];
            const unreadBadge = document.querySelector('.group-item[data-group="bug"] .unread-badge');
            if (data.unread > 0) {
                if (unreadBadge) {
                    unreadBadge.textContent = data.unread;
                } else {
                    const bugGroup = document.querySelector('.group-item[data-group="bug"]');
                    const badge = document.createElement('span');
                    badge.className = 'unread-badge';
                    badge.textContent = data.unread;
                    bugGroup.appendChild(badge);
                }
            } else {
                if (unreadBadge) {
                    unreadBadge.remove();
                }
            }
        }
    })
}
function startHeartbeat() {
    sendHeartbeat(); 
    heartbeatInterval = setInterval(sendHeartbeat, 5000); 
}
function stopHeartbeat() {
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
    }
    const formData = new FormData();
    formData.append('action', 'leave');
    navigator.sendBeacon('feedback_action.php', formData);
}
window.addEventListener('beforeunload', stopHeartbeat);
window.addEventListener('unload', stopHeartbeat);
document.getElementById('messageForm').addEventListener('submit', function(e) {
    setTimeout(() => {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }, 100);
});
function switchGroup(groupType) {
    if (groupType === currentGroup) {
        closeSidebar();
        return;
    }
    closeSidebar();
    showFeedbackLoading('Membuka Grup...', 'Tunggu sebentar');
    waitTwoFrames().then(() => {
        window.location.href = 'feedback.php?group=' + groupType;
    });
}
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    if (sidebar.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('click', function(e) {
    document.querySelectorAll('.message-dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
    });
    if (e.target.closest('.action-menu-btn')) {
        e.stopPropagation();
        const btn = e.target.closest('.action-menu-btn');
        const container = btn.closest('.message-bubble-container');
        const dropdown = container.querySelector('.message-dropdown-menu');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
});
function copyMessageText(btn) {
    const container = btn.closest('.message-bubble-container');
    const messageText = container.querySelector('.message-bubble').dataset.messageText;
    navigator.clipboard.writeText(messageText).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.closest('.message-dropdown-menu').style.display = 'none';
        }, 1000);
    }).catch(err => {
        feedbackAlert('Gagal menyalin teks. Silakan coba lagi.');
    });
}
function editMessage(messageId, btn) {
    const container = btn.closest('.message-bubble-container');
    const messageBubble = container.querySelector('.message-bubble');
    const currentText = messageBubble.dataset.messageText;
    btn.closest('.message-dropdown-menu').style.display = 'none';
    const editForm = document.createElement('div');
    editForm.className = 'edit-message-form';
    editForm.dataset.messageId = messageId;
    editForm.dataset.imagesToDelete = '[]'; 
    editForm.dataset.newImagesToAdd = '0'; 
    showFeedbackLoading('Memuat Pesan...', 'Mengambil gambar untuk edit');
    waitTwoFrames().then(() => {
        return fetch(`feedback_action.php?action=get_images&message_id=${messageId}`);
    })
        .then(async (response) => {
            const raw = await response.text();
            try {
                return JSON.parse(raw);
            } catch (e) {
                return { success: false, images: [] };
            }
        })
        .then(data => {
            let images = Array.isArray(data.images) ? data.images : [];
            if (images.length === 0) {
                const existingInBubble = Array.from(messageBubble.querySelectorAll('.message-image')).map((imgEl, idx) => ({
                    id: parseInt(imgEl.dataset.imageId || '0', 10) || 0,
                    urutan: idx + 1,
                    image_path: imgEl.getAttribute('src') || ''
                })).filter(item => item.image_path !== '');
                images = existingInBubble;
            }
            let formHTML = '';
            if (images.length > 0) {
                formHTML += `<div class="edit-existing-images" id="editExistingImages_${messageId}">`;
                images.forEach(img => {
                    formHTML += `
                        <div class="edit-image-item" data-image-id="${img.id}">
                            <img src="${img.image_path}" alt="Image ${img.urutan}" class="edit-image-preview">
                            <button type="button" class="remove-edit-image-btn" onclick="markImageForDeletion(${messageId}, ${img.id}, this)" title="Hapus gambar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                });
                formHTML += `</div>`;
            }
            formHTML += `
                <div class="edit-add-images-section" id="editDropZone_${messageId}">
                    <div class="edit-drop-area" id="editDropArea_${messageId}">
                        <label class="add-images-label" id="addImagesLabel_${messageId}">
                            <i class="fas fa-paperclip"></i> Tambah Gambar
                            <input type="file" class="edit-images-input" id="editImagesInput_${messageId}" accept="image/*" multiple onchange="previewNewEditImages(this, ${messageId})">
                        </label>
                        <div class="edit-drop-hint">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>atau seret gambar ke sini</span>
                        </div>
                    </div>
                    <div class="edit-new-images-preview" id="editNewImages_${messageId}"></div>
                </div>
                <textarea class="edit-textarea" placeholder="Ketik pesan Anda...">${currentText}</textarea>
                <div class="edit-actions">
                    <button class="edit-cancel-btn" onclick="cancelEdit(this)">Batal</button>
                    <button class="edit-save-btn" onclick="saveEditWithMultipleImages(${messageId}, this)">Simpan</button>
                </div>
            `;
            editForm.innerHTML = formHTML;
            messageBubble.style.display = 'none';
            container.insertBefore(editForm, messageBubble);
            setupEditDragDrop(messageId);
            hideFeedbackLoading();
        })
        .catch(error => {
            hideFeedbackLoading();
            feedbackAlert('Gagal memuat gambar untuk edit.');
        });
}
function markImageForDeletion(messageId, imageId, btn) {
    const editForm = document.querySelector('.edit-message-form');
    const imagesToDelete = JSON.parse(editForm.dataset.imagesToDelete || '[]');
    if (!imagesToDelete.includes(imageId)) {
        imagesToDelete.push(imageId);
        editForm.dataset.imagesToDelete = JSON.stringify(imagesToDelete);
    }
    const imageItem = btn.closest('.edit-image-item');
    imageItem.style.display = 'none';
}
function previewNewEditImages(input, messageId, droppedFiles = null) {
    const files = droppedFiles || input.files;
    if (files.length === 0) return;
    const previewContainer = document.getElementById(`editNewImages_${messageId}`);
    const editForm = document.querySelector('.edit-message-form');
    if (droppedFiles) {
        const fileInput = document.getElementById(`editImagesInput_${messageId}`);
        const dt = new DataTransfer();
        if (fileInput.files.length > 0) {
            Array.from(fileInput.files).forEach(file => dt.items.add(file));
        }
        Array.from(droppedFiles).forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
    }
    showFeedbackLoading('Memproses Gambar...', 'Membuat preview');
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    const maxSize = 5 * 1024 * 1024; 
    const validFiles = Array.from(files).filter(file => {
        if (!allowedTypes.includes(file.type)) {
            feedbackAlert(`${file.name}: Format tidak didukung.`);
            return false;
        }
        if (file.size > maxSize) {
            feedbackAlert(`${file.name}: Ukuran maksimal 5MB.`);
            return false;
        }
        return true;
    });
    const tasks = [];
    validFiles.forEach((file, validIndex) => {
        const fileSizeKB = (file.size / 1024).toFixed(1);
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        const fileSizeText = file.size > 1024 * 1024 ? `${fileSizeMB} MB` : `${fileSizeKB} KB`;
        const reader = new FileReader();
        tasks.push(new Promise(resolve => {
            reader.onload = function(e) {
                const currentCount = parseInt(editForm.dataset.newImagesToAdd || '0');
                const newIndex = currentCount + validIndex;
                const imageDiv = document.createElement('div');
                imageDiv.className = 'edit-image-item new';
                imageDiv.dataset.newImageIndex = newIndex;
                imageDiv.innerHTML = `
                    <img src="${e.target.result}" alt="New image" class="edit-image-preview">
                    <button type="button" class="remove-edit-image-btn" onclick="removeNewEditImage(${messageId}, ${newIndex}, this)" title="Hapus">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="edit-image-info">
                        <span class="edit-image-name">${file.name}</span>
                        <span class="edit-image-size">${fileSizeText}</span>
                    </div>
                `;
                previewContainer.appendChild(imageDiv);
                resolve();
            };
            reader.onerror = () => resolve();
        }));
        reader.readAsDataURL(file);
    });
    const currentCount = parseInt(editForm.dataset.newImagesToAdd || '0');
    editForm.dataset.newImagesToAdd = (currentCount + validFiles.length).toString();
    Promise.allSettled(tasks).finally(() => {
        hideFeedbackLoading();
    });
}
function removeNewEditImage(messageId, imageIndex, btn) {
    const imageItem = btn.closest('.edit-image-item');
    imageItem.remove();
    const editForm = document.querySelector('.edit-message-form');
    const currentCount = parseInt(editForm.dataset.newImagesToAdd || '0');
    editForm.dataset.newImagesToAdd = Math.max(0, currentCount - 1).toString();
}
function saveEditWithMultipleImages(messageId, btn) {
    const editForm = btn.closest('.edit-message-form');
    const textarea = editForm.querySelector('.edit-textarea');
    const newText = textarea.value.trim();
    const imagesToDelete = JSON.parse(editForm.dataset.imagesToDelete || '[]');
    const fileInput = editForm.querySelector('.edit-images-input');
    const newFiles = fileInput ? fileInput.files : [];
    const existingVisibleImages = editForm.querySelectorAll('.edit-image-item:not(.new):not([style*="display: none"])').length;
    const hasContent = newText !== '' || existingVisibleImages > 0 || newFiles.length > 0;
    if (!hasContent) {
        feedbackAlert('Pesan tidak boleh kosong. Minimal ada teks atau gambar.');
        return;
    }
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';
    const formData = new FormData();
    formData.append('action', 'edit');
    formData.append('csrf_token', FEEDBACK_CSRF_TOKEN);
    formData.append('message_id', messageId);
    formData.append('new_text', newText);
    formData.append('images_to_delete', JSON.stringify(imagesToDelete));
    if (newFiles.length > 0) {
        for (let i = 0; i < newFiles.length; i++) {
            formData.append('new_images[]', newFiles[i]);
        }
    }
    showFeedbackLoading('Menyimpan Perubahan...', 'Tunggu sebentar');
    waitTwoFrames().then(() => fetch('feedback_action.php', {
        method: 'POST',
        body: formData
    }))
    .then(async (response) => {
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (e) {
            const start = raw.indexOf('{');
            const end = raw.lastIndexOf('}');
            if (start !== -1 && end !== -1 && end > start) {
                try {
                    return JSON.parse(raw.slice(start, end + 1));
                } catch (err) {
                }
            } else {
                console.warn('Invalid edit response:', raw);
            }
            return { success: false, message: 'Respons server tidak valid saat menyimpan edit.' };
        }
    })
    .then(data => {
        if (data.success) {
            const notification = document.getElementById('actionNotification');
            notification.style.display = 'none';
            showFeedbackLoading('Memperbarui Tampilan...', 'Sedang memuat ulang');
            location.reload();
        } else {
            hideFeedbackLoading();
            feedbackAlert(data.message || 'Gagal mengedit pesan');
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    })
    .catch(error => {
        hideFeedbackLoading();
        feedbackAlert('Terjadi kesalahan saat mengedit pesan');
        btn.disabled = false;
        btn.textContent = 'Simpan';
    });
}
function cancelEdit(btn) {
    const editForm = btn.closest('.edit-message-form');
    const container = editForm.closest('.message-bubble-container');
    const messageBubble = container.querySelector('.message-bubble');
    editForm.remove();
    messageBubble.style.display = 'block';
}
function setupEditDragDrop(messageId) {
    const dropZone = document.getElementById(`editDropZone_${messageId}`);
    const dropArea = document.getElementById(`editDropArea_${messageId}`);
    if (!dropZone || !dropArea) return;
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => {
            dropArea.classList.add('drag-over');
        }, false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => {
            dropArea.classList.remove('drag-over');
        }, false);
    });
    dropArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
            if (imageFiles.length > 0) {
                const fileInput = document.getElementById(`editImagesInput_${messageId}`);
                previewNewEditImages(fileInput, messageId, imageFiles);
            } else {
                feedbackAlert('Hanya file gambar yang diperbolehkan!');
            }
        }
    }, false);
}
function saveEditWithImage(messageId, btn) {
    const editForm = btn.closest('.edit-message-form');
    const textarea = editForm.querySelector('.edit-textarea');
    let newText = textarea.value.trim();
    const imageAction = editForm.dataset.imageAction;
    const currentImage = editForm.dataset.currentImage;
    const fileInput = editForm.querySelector('.edit-image-input');
    const hasNewFile = fileInput && fileInput.files.length > 0;
    if (newText === '' && imageAction === 'remove' && !hasNewFile) {
        feedbackAlert('Pesan tidak boleh kosong. Minimal ada teks atau gambar.');
        return;
    }
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';
    const formData = new FormData();
    formData.append('action', 'edit');
    formData.append('csrf_token', FEEDBACK_CSRF_TOKEN);
    formData.append('message_id', messageId);
    formData.append('new_text', newText);
    formData.append('image_action', imageAction);
    formData.append('current_image', currentImage);
    if (hasNewFile) {
        formData.append('new_image', fileInput.files[0]);
    }
    showFeedbackLoading('Menyimpan Perubahan...', 'Tunggu sebentar');
    waitTwoFrames().then(() => fetch('feedback_action.php', {
        method: 'POST',
        body: formData
    }))
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const container = editForm.closest('.message-bubble-container');
            const messageBubble = container.querySelector('.message-bubble');
            let displayHTML = '';
            const finalText = data.final_text || newText;
            const textOnly = finalText.replace(/\[IMG:.*?\]/g, '').trim();
            if (textOnly !== '') {
                displayHTML = textOnly.replace(/\n/g, '<br>');
            }
            const imageMatch = finalText.match(/\[IMG:(.*?)\]/);
            if (imageMatch) {
                const imagePath = imageMatch[1];
                if (displayHTML !== '') displayHTML += '<br><br>';
                displayHTML += `<img src="${imagePath}" class="message-image" alt="Uploaded image" onclick="openImageModal(this.src)">`;
            }
            messageBubble.innerHTML = displayHTML;
            messageBubble.dataset.messageText = finalText;
            editForm.remove();
            messageBubble.style.display = 'block';
            if (currentGroup === 'bug') {
                originalBugMessages = messagesContainer.innerHTML;
            }
            hideFeedbackLoading();
        } else {
            hideFeedbackLoading();
            feedbackAlert(data.message || 'Gagal mengedit pesan');
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        hideFeedbackLoading();
        feedbackAlert('Terjadi kesalahan saat mengedit pesan');
        btn.disabled = false;
        btn.textContent = 'Simpan';
    });
}
async function deleteMessage(messageId) {
    const ok = await feedbackConfirm('Apakah Anda yakin ingin menghapus pesan ini?', 'Hapus Pesan', 'Hapus', 'Batal');
    if (!ok) return;
    showFeedbackLoading('Menghapus Pesan...', 'Tunggu sebentar');
    await waitTwoFrames();
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('csrf_token', FEEDBACK_CSRF_TOKEN);
    formData.append('message_id', messageId);
    fetch('feedback_action.php', {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (e) {
            const start = raw.indexOf('{');
            const end = raw.lastIndexOf('}');
            if (start !== -1 && end !== -1 && end > start) {
                try {
                    return JSON.parse(raw.slice(start, end + 1));
                } catch (err) {
                    console.warn('Invalid delete response:', raw);
                }
            } else {
            }
            return { success: false, message: 'Respons server tidak valid saat menghapus pesan.' };
        }
    })
    .then(data => {
        if (data.success) {
            const bubble = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
            const wrapper = bubble ? bubble.closest('.message-wrapper') : null;
            if (wrapper) wrapper.remove();
            hideFeedbackLoading();
        } else {
            hideFeedbackLoading();
            feedbackAlert(data.message || 'Gagal menghapus pesan');
        }
    })
    .catch(error => {
        hideFeedbackLoading();
        feedbackAlert('Terjadi kesalahan saat menghapus pesan');
    });
}
function showEmojiReaction(btn) {
    const container = btn.closest('.message-bubble-container');
    const emojiPicker = container.querySelector('.emoji-reaction-picker');
    document.querySelectorAll('.emoji-reaction-picker').forEach(picker => {
        if (picker !== emojiPicker) {
            picker.style.display = 'none';
        }
    });
    emojiPicker.style.display = emojiPicker.style.display === 'none' ? 'flex' : 'none';
}
function addReaction(btn, emoji) {
    const container = btn.closest('.message-bubble-container');
    const reactionsContainer = container.querySelector('.message-reactions');
    let reactionBtn = Array.from(reactionsContainer.querySelectorAll('.reaction-btn')).find(btn => btn.dataset.emoji === emoji);
    if (reactionBtn) {
        const countSpan = reactionBtn.querySelector('.reaction-count');
        let count = parseInt(countSpan.textContent);
        count++;
        countSpan.textContent = count;
        reactionBtn.classList.add('reaction-active');
    } else {
        reactionBtn = document.createElement('button');
        reactionBtn.className = 'reaction-btn reaction-active';
        reactionBtn.dataset.emoji = emoji;
        reactionBtn.innerHTML = `
            <span class="reaction-emoji">${emoji}</span>
            <span class="reaction-count">1</span>
        `;
        reactionBtn.onclick = function() {
            toggleReaction(this);
        };
        reactionsContainer.appendChild(reactionBtn);
    }
    container.querySelector('.emoji-reaction-picker').style.display = 'none';
}
function toggleReaction(btn) {
    const countSpan = btn.querySelector('.reaction-count');
    let count = parseInt(countSpan.textContent);
    if (btn.classList.contains('reaction-active')) {
        count--;
        if (count === 0) {
            btn.remove();
        } else {
            countSpan.textContent = count;
            btn.classList.remove('reaction-active');
        }
    } else {
        count++;
        countSpan.textContent = count;
        btn.classList.add('reaction-active');
    }
}
function replyMessage(btn) {
    const container = btn.closest('.message-bubble-container');
    const messageBubble = container.querySelector('.message-bubble');
    const messageId = messageBubble.dataset.messageId;
    const messageText = messageBubble.dataset.messageText;
    const username = messageBubble.dataset.username;
    const inputField = document.getElementById('komentarInput');
    showActionNotification('reply', `Membalas pesan dari <strong>${username}</strong>`, messageText.substring(0, 80));
    inputField.focus();
    inputField.dataset.replyToMessageId = messageId;
}
function cancelReply() {
    const replyPreview = document.querySelector('.reply-preview');
    if (replyPreview) {
        replyPreview.remove();
    }
    const inputField = document.querySelector('.message-input input[name="komentar"]');
    delete inputField.dataset.replyToMessageId;
}
function showActionNotification(type, title, preview = '') {
    const notification = document.getElementById('actionNotification');
    const icon = notification.querySelector('.action-notification-icon');
    const text = notification.querySelector('.action-notification-text');
    notification.className = 'action-notification';
    if (type === 'reply') {
        notification.classList.add('reply-mode');
        icon.innerHTML = '<i class="fas fa-reply"></i>';
        text.innerHTML = title;
        if (preview) {
            text.innerHTML += `<br><small style="opacity: 0.8; font-weight: 400;">"${preview}${preview.length >= 80 ? '...' : ''}"</small>`;
        }
    } else if (type === 'forward') {
        notification.classList.add('forward-mode');
        icon.innerHTML = '<i class="fas fa-share"></i>';
        text.innerHTML = title;
    }
    notification.style.display = 'flex';
    notification.dataset.actionType = type;
}
function cancelAction() {
    const notification = document.getElementById('actionNotification');
    const actionType = notification.dataset.actionType;
    notification.style.display = 'none';
    if (actionType === 'reply') {
        const inputField = document.getElementById('komentarInput');
        delete inputField.dataset.replyToMessageId;
    }
}
function forwardMessage(btn) {
    const container = btn.closest('.message-bubble-container');
    const messageBubble = container.querySelector('.message-bubble');
    const messageId = messageBubble.dataset.messageId;
    const messageText = messageBubble.dataset.messageText;
    const username = messageBubble.dataset.username;
    const dialog = document.createElement('div');
    dialog.className = 'forward-dialog-overlay';
    dialog.innerHTML = `
        <div class="forward-dialog">
            <div class="forward-header">
                <h3><i class="fas fa-share"></i> Teruskan Pesan</h3>
                <button class="forward-close" onclick="closeForwardDialog()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="forward-body">
                <div class="forward-message-preview">
                    <strong>Dari: ${username}</strong>
                    <p>${messageText}</p>
                </div>
                <div class="forward-groups">
                    <p>Teruskan ke grup:</p>
                    <label class="forward-group-option">
                        <input type="radio" name="forward_group" value="bug" ${currentGroup === 'bug' ? 'disabled' : ''}>
                        <span><i class="fas fa-bug"></i> Pembahasan Bug</span>
                    </label>
                    <label class="forward-group-option">
                        <input type="radio" name="forward_group" value="inspiration" ${currentGroup === 'inspiration' ? 'disabled' : ''}>
                        <span><i class="fas fa-lightbulb"></i> Pembahasan Inspirasi</span>
                    </label>
                    <label class="forward-group-option">
                        <input type="radio" name="forward_group" value="general" ${currentGroup === 'general' ? 'disabled' : ''}>
                        <span><i class="fas fa-comments"></i> Pembahasan Umum</span>
                    </label>
                </div>
                <div class="forward-comment-section">
                    <label for="forwardComment">Tambahkan komentar (opsional):</label>
                    <textarea id="forwardComment" placeholder="Tulis komentar Anda..." rows="3"></textarea>
                </div>
            </div>
            <div class="forward-footer">
                <button class="forward-cancel-btn" onclick="closeForwardDialog()">Batal</button>
                <button class="forward-send-btn" onclick="sendForwardedMessage(${messageId}, '${username}')">Teruskan</button>
            </div>
        </div>
    `;
    document.body.appendChild(dialog);
}
function closeForwardDialog() {
    const dialog = document.querySelector('.forward-dialog-overlay');
    if (dialog) {
        dialog.remove();
    }
}
function sendForwardedMessage(messageId, originalUsername) {
    const selectedGroup = document.querySelector('input[name="forward_group"]:checked');
    if (!selectedGroup) {
        feedbackAlert('Silakan pilih grup tujuan.');
        return;
    }
    const targetGroup = selectedGroup.value;
    const groupLabels = {
        bug: 'Pembahasan Bug',
        inspiration: 'Pembahasan Inspirasi',
        general: 'Pembahasan Umum'
    };
    const targetGroupLabel = groupLabels[targetGroup] || targetGroup;
    const comment = document.getElementById('forwardComment').value.trim();
    const formData = new FormData();
    formData.append('forward_comment', comment);
    formData.append('group_type', targetGroup);
    formData.append('forwarded_from_message_id', messageId);
    formData.append('forwarded_from_group', currentGroup);
    formData.append('forwarded_from_username', originalUsername);
    formData.append('action', 'forward_message');
    formData.append('csrf_token', FEEDBACK_CSRF_TOKEN);
    showFeedbackLoading('Meneruskan Pesan...', 'Tunggu sebentar');
    waitTwoFrames().then(() => fetch('feedback_action.php', {
        method: 'POST',
        body: formData
    }))
    .then(async (response) => {
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (e) {
            const start = raw.indexOf('{');
            const end = raw.lastIndexOf('}');
            if (start !== -1 && end !== -1 && end > start) {
                try {
                    return JSON.parse(raw.slice(start, end + 1));
                } catch (err) {
                    console.warn('Invalid forward response:', raw);
                }
            } else {
                console.warn('Invalid forward response:', raw);
            }
            return { success: false, message: 'Respons server tidak valid saat meneruskan pesan.' };
        }
    })
    .then(data => {
        closeForwardDialog();
        if (data.success) {
            hideFeedbackLoading();
            __feedbackModalResolver = function () {
                showFeedbackLoading('Memuat ulang...', 'Tunggu sebentar');
                location.reload();
            };
            openFeedbackModal({
                title: 'Berhasil',
                message: `Pesan telah di kirim ke ${targetGroupLabel}.`,
                confirmText: 'OK',
                showCancel: false
            });
        } else {
            hideFeedbackLoading();
            feedbackAlert('Gagal meneruskan pesan: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        hideFeedbackLoading();
        feedbackAlert('Gagal meneruskan pesan.');
    });
}
document.getElementById('messageForm').addEventListener('submit', function(e) {
    const form = this;
    const inputField = form.querySelector('input[name="komentar"]');
    const imageInput = document.getElementById('imageInput');
    const text = (inputField.value || '').trim();
    const hasImages = imageInput && imageInput.files && imageInput.files.length > 0;
    if (!text && !hasImages) {
        e.preventDefault();
        feedbackAlert('Pesan tidak boleh kosong. Minimal ada teks atau gambar.');
        return;
    }
    if (inputField.dataset.replyToMessageId) {
        const replyToMessageId = inputField.dataset.replyToMessageId;
        let hiddenInput = form.querySelector('input[name="reply_to_message_id"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'reply_to_message_id';
            form.appendChild(hiddenInput);
        }
        hiddenInput.value = replyToMessageId;
        delete inputField.dataset.replyToMessageId;
        cancelReply();
    }
    const notification = document.getElementById('actionNotification');
    notification.style.display = 'none';
    e.preventDefault();
    showFeedbackLoading('Mengirim Pesan...', 'Tunggu sebentar');
    waitTwoFrames().then(() => {
        form.submit();
    });
});
document.getElementById('imageInput').addEventListener('change', function(e) {
    const files = e.target.files;
    if (files.length > 0) {
        showFeedbackLoading('Memproses Gambar...', 'Membuat preview');
        waitTwoFrames().then(() => {
            return previewMultipleImages(files);
        }).finally(() => {
            hideFeedbackLoading();
        });
    }
});
async function previewMultipleImages(files) {
    const previewContainer = document.getElementById('imagePreviewContainer');
    previewContainer.innerHTML = '';
    if (files.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }
    previewContainer.style.display = 'block';
    const tasks = [];
    Array.from(files).forEach((file, index) => {
        if (!file.type.startsWith('image/')) {
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            feedbackAlert(`${file.name}: Ukuran maksimal 5MB!`);
            return;
        }
        const fileSizeKB = (file.size / 1024).toFixed(1);
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        const fileSizeText = file.size > 1024 * 1024 ? `${fileSizeMB} MB` : `${fileSizeKB} KB`;
        const reader = new FileReader();
        tasks.push(new Promise(resolve => {
            reader.onload = function(e) {
                const imageWrapper = document.createElement('div');
                imageWrapper.className = 'image-preview-wrapper';
                imageWrapper.dataset.index = index;
                imageWrapper.innerHTML = `
                    <div class="image-preview">
                        <img src="${e.target.result}" alt="Preview ${index + 1}">
                        <button type="button" class="remove-image" onclick="removeSinglePreview(${index})" title="Hapus gambar">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="image-info">
                        <span class="image-filename">${file.name}</span>
                        <span class="image-filesize">${fileSizeText}</span>
                    </div>
                `;
                previewContainer.appendChild(imageWrapper);
                resolve();
            };
            reader.onerror = () => resolve();
        }));
        reader.readAsDataURL(file);
    });
    await Promise.allSettled(tasks);
}
function removeSinglePreview(index) {
    const input = document.getElementById('imageInput');
    const dt = new DataTransfer();
    const files = input.files;
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    input.files = dt.files;
    previewMultipleImages(input.files);
}
function removeImagePreview() {
    const previewContainer = document.getElementById('imagePreviewContainer');
    previewContainer.innerHTML = '';
    previewContainer.style.display = 'none';
    document.getElementById('imageInput').value = '';
}
const inputWrapper = document.getElementById('inputWrapper');
const komentarInput = document.getElementById('komentarInput');
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    inputWrapper.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}
['dragenter', 'dragover'].forEach(eventName => {
    inputWrapper.addEventListener(eventName, highlight, false);
});
['dragleave', 'drop'].forEach(eventName => {
    inputWrapper.addEventListener(eventName, unhighlight, false);
});
function highlight(e) {
    inputWrapper.classList.add('drag-over');
}
function unhighlight(e) {
    inputWrapper.classList.remove('drag-over');
}
inputWrapper.addEventListener('drop', handleDrop, false);
function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files.length > 0) {
        const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
        if (imageFiles.length > 0) {
            const dataTransfer = new DataTransfer();
            imageFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('imageInput').files = dataTransfer.files;
            previewMultipleImages(imageFiles);
        } else {
            feedbackAlert('Hanya file gambar yang diperbolehkan!');
        }
    }
}
document.getElementById('emojiBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    const emojiPicker = document.getElementById('emojiPicker');
    emojiPicker.style.display = emojiPicker.style.display === 'none' ? 'flex' : 'none';
});
document.addEventListener('click', function(e) {
    const emojiPicker = document.getElementById('emojiPicker');
    const emojiBtn = document.getElementById('emojiBtn');
    if (!emojiPicker.contains(e.target) && !emojiBtn.contains(e.target)) {
        emojiPicker.style.display = 'none';
    }
});
function insertEmoji(emoji) {
    const input = document.getElementById('komentarInput');
    const startPos = input.selectionStart;
    const endPos = input.selectionEnd;
    const textBefore = input.value.substring(0, startPos);
    const textAfter = input.value.substring(endPos, input.value.length);
    input.value = textBefore + emoji + textAfter;
    const newCursorPos = startPos + emoji.length;
    input.setSelectionRange(newCursorPos, newCursorPos);
    input.focus();
    document.getElementById('emojiPicker').style.display = 'none';
}
function scrollToMessage(messageId) {
    const messageBubble = document.querySelector(`[data-message-id="${messageId}"]`);
    if (messageBubble) {
        messageBubble.scrollIntoView({ behavior: 'smooth', block: 'center' });
        messageBubble.classList.add('message-highlight');
        setTimeout(() => {
            messageBubble.classList.remove('message-highlight');
        }, 2000);
    }
}
function openImageModal(src) {
    const modal = document.createElement('div');
    modal.className = 'image-modal-overlay';
    modal.innerHTML = `
        <div class="image-modal">
            <button class="image-modal-close" onclick="this.closest('.image-modal-overlay').remove()">
                <i class="fas fa-times"></i>
            </button>
            <img src="${src}" alt="Full size image">
        </div>
    `;
    modal.onclick = function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    };
    document.body.appendChild(modal);
}
window.addEventListener('DOMContentLoaded', function() {
});
async function runDatabaseMigration() {
    const ok = await feedbackConfirm(
        'Menjalankan database migration?\n\nIni akan:\n- Membuat tabel feedback_images (jika belum ada)\n- Memindahkan gambar dari format lama\n- Menambah kolom first_visit_at ke feedback_online\n\nLanjutkan?',
        'Database Migration',
        'Jalankan',
        'Batal'
    );
    if (!ok) return;
    const formData = new FormData();
    formData.append('action', 'migrate');
    try {
        showFeedbackLoading('Menjalankan Migration...', 'Tunggu sebentar');
        await waitTwoFrames();
        const response = await fetch('feedback_action.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        hideFeedbackLoading();
        if (result.success) {
            let message = 'âœ… Migration completed successfully!\n\n';
            message += 'Results:\n';
            for (const [key, value] of Object.entries(result.migrations || {})) {
                const status = (typeof value === 'string' && value.includes('success')) ? 'âœ“' : value === 'skipped' ? 'âŠ˜' : 'âœ—';
                message += `${status} ${key}: ${value}\n`;
            }
            feedbackAlert(message, 'Sukses');
            const reload = await feedbackConfirm('Reload halaman untuk apply perubahan?', 'Reload', 'Reload', 'Nanti');
            if (reload) {
                showFeedbackLoading('Memuat ulang...', 'Tunggu sebentar');
                location.reload();
            }
        } else {
            let message = 'âŒ Migration failed!\n\n';
            if (result.errors && result.errors.length > 0) {
                message += 'Errors:\n' + result.errors.join('\n');
            }
            feedbackAlert(message, 'Gagal');
        }
    } catch (error) {
        hideFeedbackLoading();
        feedbackAlert('Error: ' + (error && error.message ? error.message : 'Unknown error'), 'Error');
        console.error('Migration error:', error);
    }
}
async function updateOldUsers() {
    const ok = await feedbackConfirm(
        'Update semua user lama untuk menandai mereka sudah pernah visit?\n\nIni akan menyembunyikan welcome message untuk user yang sudah pernah login sebelumnya.\n\nLanjutkan?',
        'Update Old Users',
        'Update',
        'Batal'
    );
    if (!ok) return;
    const formData = new FormData();
    formData.append('action', 'update_old_users');
    try {
        showFeedbackLoading('Mengupdate User...', 'Tunggu sebentar');
        await waitTwoFrames();
        const response = await fetch('feedback_action.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        hideFeedbackLoading();
        if (result.success) {
            feedbackAlert(`âœ… Berhasil update ${result.updated} user lama!\n\nMereka tidak akan melihat welcome message lagi.`, 'Sukses');
            showFeedbackLoading('Memuat ulang...', 'Tunggu sebentar');
            location.reload();
        } else {
            feedbackAlert('âŒ Error: ' + (result.error || 'Unknown error'), 'Gagal');
        }
    } catch (error) {
        hideFeedbackLoading();
        feedbackAlert('Error: ' + (error && error.message ? error.message : 'Unknown error'), 'Error');
        console.error('Update error:', error);
    }
}
</script>
</body>
</html>
