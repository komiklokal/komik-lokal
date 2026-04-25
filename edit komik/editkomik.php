<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include("config.php");

function formatTanggalIndonesia($date) {
    if (empty($date) || $date == '0000-00-00') {
        return 'Tidak tersedia';
    }
    
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $timestamp = strtotime($date);
    $hari = date('d', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID tidak diberikan'); window.location.href='index.php';</script>";
    exit();
}

$komik_id = $_GET['id'];

if (!is_numeric($komik_id)) {
    echo "<script>alert('ID tidak valid'); window.location.href='index.php';</script>";
    exit();
}

$komik_id = (int)$komik_id;
$isLoggedIn = isset($_SESSION['username']) && $_SESSION['username'] !== '';
$currentUsername = $isLoggedIn ? (string)$_SESSION['username'] : '';
$currentUserId = 0;
$commentCount = 0;
$comments = [];
$commentNotice = isset($_GET['comment_message']) ? (string)$_GET['comment_message'] : '';
$commentNoticeType = isset($_GET['comment_status']) ? (string)$_GET['comment_status'] : '';

if ($isLoggedIn) {
    $userStmt = $conn->prepare("SELECT id FROM user WHERE user_nama = ? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param("s", $currentUsername);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userRow = $userResult->fetch_assoc()) {
            $currentUserId = (int)$userRow['id'];
        }
        $userStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add_comment', 'edit_comment', 'delete_comment'], true)) {
    $postKomikId = isset($_POST['komik_id']) ? (int)$_POST['komik_id'] : 0;
    $redirectBase = 'editkomik.php?id=' . urlencode((string)$komik_id);

    if ($postKomikId !== $komik_id) {
        header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Permintaan komentar tidak valid.'));
        exit();
    }

    if (!$isLoggedIn || $currentUserId <= 0) {
        header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Akses komentar tidak tersedia.'));
        exit();
    }

    $action = (string)$_POST['action'];
    if ($action === 'add_comment') {
        $commentText = isset($_POST['comment_text']) ? trim((string)$_POST['comment_text']) : '';
        if ($commentText === '') {
            header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Komentar tidak boleh kosong.'));
            exit();
        }
        if (mb_strlen($commentText) > 800) {
            header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Komentar terlalu panjang. Maksimal 800 karakter.'));
            exit();
        }

        $insertCommentStmt = $conn->prepare("INSERT INTO komik_comment (user_id, komik_id, username, komentar) VALUES (?, ?, ?, ?)");
        $insertCommentStmt->bind_param("iiss", $currentUserId, $komik_id, $currentUsername, $commentText);
        $insertCommentStmt->execute();
        $insertCommentStmt->close();

        header('Location: ' . $redirectBase . '&comment_status=success&comment_message=' . urlencode('Komentar berhasil ditambahkan.'));
        exit();
    }

    if ($action === 'edit_comment') {
        $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
        $commentText = isset($_POST['comment_text']) ? trim((string)$_POST['comment_text']) : '';
        if ($commentId <= 0 || $commentText === '') {
            header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Komentar tidak valid.'));
            exit();
        }
        if (mb_strlen($commentText) > 800) {
            header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Komentar terlalu panjang. Maksimal 800 karakter.'));
            exit();
        }

        $updateCommentStmt = $conn->prepare("UPDATE komik_comment SET komentar = ? WHERE id = ? AND user_id = ? AND komik_id = ?");
        $updateCommentStmt->bind_param("siii", $commentText, $commentId, $currentUserId, $komik_id);
        $updateCommentStmt->execute();
        $affected = $updateCommentStmt->affected_rows;
        $updateCommentStmt->close();

        if ($affected > 0) {
            header('Location: ' . $redirectBase . '&comment_status=success&comment_message=' . urlencode('Komentar berhasil diperbarui.'));
        } else {
            header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Komentar tidak ditemukan atau tidak memiliki akses.'));
        }
        exit();
    }

    if ($action === 'delete_comment') {
        $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
        if ($commentId <= 0) {
            header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Komentar tidak valid.'));
            exit();
        }

        $deleteCommentStmt = $conn->prepare("DELETE FROM komik_comment WHERE id = ? AND user_id = ? AND komik_id = ?");
        $deleteCommentStmt->bind_param("iii", $commentId, $currentUserId, $komik_id);
        $deleteCommentStmt->execute();
        $affected = $deleteCommentStmt->affected_rows;
        $deleteCommentStmt->close();

        if ($affected > 0) {
            header('Location: ' . $redirectBase . '&comment_status=success&comment_message=' . urlencode('Komentar berhasil dihapus.'));
        } else {
            header('Location: ' . $redirectBase . '&comment_status=error&comment_message=' . urlencode('Komentar tidak ditemukan atau tidak memiliki akses.'));
        }
        exit();
    }
}

$stmt = $conn->prepare("SELECT judul, user_nama, pengarang, sinopsis, tipe_gambar, gambar, status FROM komik WHERE id = ?");
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Komik tidak ditemukan'); window.location.href='index.php';</script>";
    exit();
}

$komik = $result->fetch_assoc();
$stmt->close();

$sql = "SELECT g.nama FROM genre g JOIN komik_genre kg ON g.id = kg.genre_id WHERE kg.komik_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$result = $stmt->get_result();

$genre = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $genre[] = $row['nama'];
    }
}

$stmt->close();

$stmt = $conn->prepare("SELECT DATE(MIN(tanggal_rilis)) AS tanggal_rilis, DATE(MAX(pembaruan_terakhir)) AS pembaruan_terakhir FROM chapter WHERE komik_id = ?");
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$resultDates = $stmt->get_result();
$chapterDates = $resultDates->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT id, judul, DATE(tanggal_rilis) AS tanggal_rilis FROM chapter WHERE komik_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$result = $stmt->get_result();

$chapters = [];
$nomor_urut = 1;
while ($row = $result->fetch_assoc()) {
    $row['nomor'] = $nomor_urut++;
    $chapters[] = $row;
}

$stmt->close();

$avgRating = 0.0;
$ratingCount = 0;
$bookmarkCount = 0;
$viewerCount = 0;

$stmt = $conn->prepare("SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS rating_count FROM komik_rating WHERE komik_id = ?");
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$ratingStatsResult = $stmt->get_result();
if ($ratingStatsRow = $ratingStatsResult->fetch_assoc()) {
    $avgRating = (float)$ratingStatsRow['avg_rating'];
    $ratingCount = (int)$ratingStatsRow['rating_count'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS bookmark_count FROM komik_bookmark WHERE komik_id = ?");
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$bookmarkStatsResult = $stmt->get_result();
if ($bookmarkStatsRow = $bookmarkStatsResult->fetch_assoc()) {
    $bookmarkCount = (int)$bookmarkStatsRow['bookmark_count'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) AS viewer_count FROM riwayat_baca WHERE komik_id = ?");
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$viewerStatsResult = $stmt->get_result();
if ($viewerStatsRow = $viewerStatsResult->fetch_assoc()) {
    $viewerCount = (int)$viewerStatsRow['viewer_count'];
}
$stmt->close();

$commentCountStmt = $conn->prepare("SELECT COUNT(*) AS comment_count FROM komik_comment WHERE komik_id = ?");
$commentCountStmt->bind_param("i", $komik_id);
$commentCountStmt->execute();
$commentCountResult = $commentCountStmt->get_result();
if ($commentCountRow = $commentCountResult->fetch_assoc()) {
    $commentCount = (int)$commentCountRow['comment_count'];
}
$commentCountStmt->close();

$commentListStmt = $conn->prepare("SELECT id, user_id, username, komentar, created_at FROM komik_comment WHERE komik_id = ? ORDER BY created_at ASC, id ASC LIMIT 100");
$commentListStmt->bind_param("i", $komik_id);
$commentListStmt->execute();
$commentListResult = $commentListStmt->get_result();
while ($commentRow = $commentListResult->fetch_assoc()) {
    $comments[] = $commentRow;
}
$commentListStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#667eea">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="editkomik.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="editkomik_new.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../dark-mode.css?v=<?php echo time(); ?>">
    <script src="editkomik.js?v=<?php echo time(); ?>" defer></script>
    <script src="editkomik_status.js?v=<?php echo time(); ?>" defer></script>
    <script src="editkomik_genres.js?v=<?php echo time(); ?>" defer></script>
    <script src="../theme.js?v=<?php echo time(); ?>" defer></script>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
    <title>Edit Komik - Komik Lokal</title>
    <style>
        #loadingOverlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .page-loading #loadingOverlay {
            opacity: 1;
            visibility: visible;
        }
        .page-loading #editKomikContent {
            visibility: hidden;
        }
        .loading-card {
            width: 90%;
            max-width: 420px;
            background: var(--bg-secondary, #ffffff);
            color: var(--text-primary, #2d3748);
            border-radius: 18px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }
        .loading-spinner {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            border: 6px solid rgba(148, 163, 184, 0.35);
            border-top-color: var(--accent-color, #667eea);
            margin: 0 auto 1rem;
            animation: pageSpin 0.9s linear infinite;
        }
        .loading-title {
            font-weight: 800;
            font-size: 1.25rem;
            margin-bottom: 0.35rem;
        }
        .loading-sub {
            color: var(--text-secondary, #4a5568);
            font-weight: 600;
        }
        @keyframes pageSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .custom-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .custom-modal {
            background: white;
            padding: 2.25rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 450px;
            transform: translateY(30px) scale(0.95);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .custom-modal-overlay.active .custom-modal {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        .modal-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        .modal-icon.warning {
            color: #ed8936;
            animation: pulse-warning 2s infinite;
        }
        .modal-icon.success { color: #48bb78; }
        .modal-icon.error { color: #e53e3e; }
        .modal-title {
            font-size: 1.45rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.85rem;
        }
        .modal-message {
            color: #4a5568;
            font-size: 1.05rem;
            margin-bottom: 1.75rem;
            line-height: 1.6;
            white-space: pre-line;
        }
        .modal-message ul {
            text-align: left;
            margin: 1rem auto;
            max-width: 80%;
            padding-left: 2rem;
            color: #e53e3e;
            font-weight: 500;
        }
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .modal-btn {
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            flex: 1;
        }
        .modal-btn-cancel {
            background: #edf2f7;
            color: #4a5568;
        }
        .modal-btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        .modal-btn-confirm {
            background: linear-gradient(45deg, #e53e3e, #c53030);
            color: white;
            box-shadow: 0 4px 15px rgba(229, 62, 62, 0.3);
        }
        .modal-btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 62, 62, 0.4);
        }
        .modal-btn-ok {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            max-width: 200px;
        }
        .modal-btn-ok:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        @keyframes pulse-warning {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .comment-open-btn {
            width: 100%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            border: none;
            border-radius: 10px;
            padding: 0.65rem 0.9rem;
            font-weight: 700;
            background: linear-gradient(45deg, #4299e1, #667eea);
            color: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .comment-open-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(66, 153, 225, 0.25);
        }

        .comment-popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .comment-popup-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .comment-popup {
            width: 100%;
            max-width: 560px;
            max-height: min(80vh, 680px);
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .comment-popup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .comment-popup-header h3 {
            margin: 0;
            font-size: 1.05rem;
            color: #2d3748;
        }
        .comment-popup-close {
            border: none;
            background: transparent;
            color: #718096;
            font-size: 1.3rem;
            cursor: pointer;
            line-height: 1;
        }

        .comment-popup-notice {
            margin: 0.75rem 1rem 0;
            padding: 0.55rem 0.7rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .comment-popup-notice.success {
            background: #e6fffa;
            color: #2f855a;
            border: 1px solid #b2f5ea;
        }
        .comment-popup-notice.error {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        .comment-list {
            padding: 0.95rem 0.95rem 0.8rem;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }
        .comment-message-wrapper {
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            max-width: 88%;
        }
        .comment-message-wrapper.message-left {
            align-self: flex-start;
        }
        .comment-message-wrapper.message-right {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .comment-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .comment-message-content {
            display: flex;
            flex-direction: column;
            gap: 0.28rem;
            min-width: 0;
        }

        .comment-item-head {
            display: flex;
            justify-content: space-between;
            gap: 0.6rem;
            margin-bottom: 0.12rem;
        }
        .comment-user {
            font-weight: 600;
            color: #64748b;
            font-size: 0.78rem;
        }
        .comment-time {
            color: #94a3b8;
            font-size: 0.72rem;
        }

        .comment-bubble-container { position: relative; }
        .comment-bubble {
            padding: 0.6rem 0.75rem;
            border-radius: 16px;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .comment-more-btn {
            position: absolute;
            top: 0.28rem;
            right: 0.3rem;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.16);
            color: #f8fafc;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.18s ease, background 0.18s ease;
        }
        .comment-message-wrapper.message-right .comment-bubble-container:hover .comment-more-btn {
            opacity: 1;
            visibility: visible;
        }
        .comment-more-btn:hover {
            background: rgba(15, 23, 42, 0.3);
        }

        .comment-actions-menu {
            position: absolute;
            top: 2rem;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.2);
            padding: 0.3rem;
            min-width: 122px;
            display: none;
            z-index: 5;
        }
        .comment-actions-menu.show { display: block; }

        .comment-message-wrapper.message-left .comment-bubble {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top-left-radius: 6px;
            color: #334155;
        }
        .comment-message-wrapper.message-right .comment-bubble {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border-top-right-radius: 6px;
            color: #fff;
        }

        .comment-action-btn {
            width: 100%;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.55rem;
            font-size: 0.76rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.3rem;
        }
        .comment-actions-menu .comment-action-btn + .comment-delete-form {
            margin-top: 0.2rem;
        }
        .comment-action-btn.edit {
            background: #e2e8f0;
            color: #334155;
        }
        .comment-action-btn.delete {
            background: #ffe4e6;
            color: #be123c;
        }
        .comment-action-btn.save {
            background: #dcfce7;
            color: #166534;
        }
        .comment-action-btn.cancel {
            background: #e5e7eb;
            color: #374151;
        }

        .comment-edit-form {
            margin-top: 0.55rem;
        }
        .comment-edit-form textarea,
        .comment-form textarea {
            width: 100%;
            border: 1px solid #dbe3ef;
            border-radius: 14px;
            padding: 0.65rem 0.8rem;
            resize: vertical;
            min-height: 72px;
        }
        .comment-edit-actions {
            margin-top: 0.45rem;
            display: flex;
            gap: 0.4rem;
        }

        .comment-empty {
            color: #718096;
            text-align: center;
            padding: 1.35rem 0;
        }

        .comment-form-wrap {
            padding: 0.8rem 1rem 1rem;
            border-top: 1px solid #e2e8f0;
        }
        .comment-submit-btn {
            margin-top: 0.55rem;
            border: none;
            border-radius: 999px;
            padding: 0.55rem 0.85rem;
            font-weight: 700;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: #fff;
            cursor: pointer;
        }
        .comment-login-link {
            display: inline-block;
            color: #2b6cb0;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
        }

        [data-theme="dark"] .comment-open-btn {
            background: linear-gradient(45deg, #3b82f6, #6366f1);
        }
        [data-theme="dark"] .comment-popup {
            background: #1f2937;
        }
        [data-theme="dark"] .comment-popup-header {
            border-bottom-color: #374151;
        }
        [data-theme="dark"] .comment-popup-header h3 {
            color: #f7fafc;
        }
        [data-theme="dark"] .comment-popup-close {
            color: #cbd5e0;
        }
        [data-theme="dark"] .comment-popup-notice.success {
            background: #1c4532;
            border-color: #276749;
            color: #9ae6b4;
        }
        [data-theme="dark"] .comment-popup-notice.error {
            background: #742a2a;
            border-color: #9b2c2c;
            color: #feb2b2;
        }
        [data-theme="dark"] .comment-user {
            color: #94a3b8;
        }
        [data-theme="dark"] .comment-time,
        [data-theme="dark"] .comment-empty {
            color: #9ca3af;
        }
        [data-theme="dark"] .comment-text {
            color: #cbd5e0;
        }
        [data-theme="dark"] .comment-avatar {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        [data-theme="dark"] .comment-message-wrapper.message-left .comment-bubble {
            background: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }
        [data-theme="dark"] .comment-message-wrapper.message-right .comment-bubble {
            background: linear-gradient(135deg, #059669, #047857);
            color: #f0fdf4;
        }
        [data-theme="dark"] .comment-more-btn {
            background: rgba(15, 23, 42, 0.45);
            color: #e2e8f0;
        }
        [data-theme="dark"] .comment-more-btn:hover {
            background: rgba(15, 23, 42, 0.68);
        }
        [data-theme="dark"] .comment-actions-menu {
            background: #111827;
            border-color: #374151;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45);
        }
        [data-theme="dark"] .comment-action-btn.edit {
            background: #334155;
            color: #cbd5e1;
        }
        [data-theme="dark"] .comment-action-btn.delete {
            background: #4c1d2c;
            color: #fecdd3;
        }
        [data-theme="dark"] .comment-action-btn.save {
            background: #14532d;
            color: #bbf7d0;
        }
        [data-theme="dark"] .comment-action-btn.cancel {
            background: #374151;
            color: #e5e7eb;
        }
        [data-theme="dark"] .comment-form-wrap {
            border-top-color: #374151;
        }
        [data-theme="dark"] .comment-form textarea,
        [data-theme="dark"] .comment-edit-form textarea {
            background: #111827;
            border-color: #4b5563;
            color: #f7fafc;
        }
        [data-theme="dark"] .comment-login-link {
            color: #90cdf4;
        }
    </style>
</head>
<body>

    <div id="loadingOverlay" aria-hidden="true">
        <div class="loading-card" role="status" aria-live="polite">
            <div class="loading-spinner"></div>
            <div class="loading-title" id="pageLoadingTitle">Memuat Edit Komik...</div>
            <div class="loading-sub" id="pageLoadingSub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>

    <div id="editKomikContent">

    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <i class="fas fa-book-open"></i>
                <h1>Edit Komik</h1>
            </div>
        </div>
    </div>

    <div class="container">
        <form action="editkomik_action.php" method="POST" enctype="multipart/form-data">
            <div class="komik-header">
                <div class="cover-komik">
                    <div class="image-preview">
                        <?php if (!empty($komik['gambar'])): ?>
                            <img src="data:<?= htmlspecialchars($komik['tipe_gambar']); ?>;base64,<?= htmlspecialchars($komik['gambar']); ?>" 
                                 alt="<?= htmlspecialchars($komik['judul']); ?>" 
                                 class="cover-preview">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-image"></i>
                                <p>No cover image</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="file-upload">
                        <label for="gambar" class="file-upload-label">
                            <i class="fas fa-upload"></i>
                            <span>Pilih Cover Baru</span>
                        </label>
                        <input type="file" id="gambar" name="gambar" accept="image/*">
                    </div>
                    <div class="komik-stats-panel">
                        <div class="komik-stat-item">
                            <i class="fas fa-star"></i>
                            <div>
                                <strong><?= number_format((float)$avgRating, 1); ?>/5</strong>
                                <span>Dari <?= (int)$ratingCount; ?> rating</span>
                            </div>
                        </div>
                        <div class="komik-stat-item">
                            <i class="fas fa-bookmark"></i>
                            <div>
                                <strong><?= (int)$bookmarkCount; ?></strong>
                                <span>Total bookmark</span>
                            </div>
                        </div>
                        <div class="komik-stat-item">
                            <i class="fas fa-eye"></i>
                            <div>
                                <strong><?= (int)$viewerCount; ?></strong>
                                <span>Orang telah melihat</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="comment-open-btn" id="openCommentPopupBtn">
                        <i class="fas fa-comments"></i>
                        Komentar (<?= (int)$commentCount; ?>)
                    </button>
                </div>
                <div class="info-komik">
                    <div class="form-group">
                        <label for="judul">
                            <i class="fas fa-heading"></i>
                            Judul Komik
                        </label>
                        <input type="text" id="judul" name="judul" value="<?= htmlspecialchars($komik['judul']); ?>" placeholder="Masukkan judul komik">
                    </div>
                    
                    <div class="form-group">
                        <label for="sinopsis">
                            <i class="fas fa-align-left"></i>
                            Sinopsis
                        </label>
                        <div id="sinopsisEditor" class="sinopsis-editor" contenteditable="true" data-placeholder="Tulis sinopsis komik di sini..."><?= htmlspecialchars($komik['sinopsis']); ?></div>
                        <input type="hidden" id="sinopsis" name="sinopsis" value="<?= htmlspecialchars($komik['sinopsis']); ?>">
                    </div>
                    
                    <table class="table-info">
                        <tr>
                            <td>
                                <label for="tanggal_rilis">
                                    <i class="fas fa-calendar"></i>
                                    Tanggal Rilis
                                </label>
                            </td>
                            <td>
                                <span class="date-display"><?= formatTanggalIndonesia($chapterDates['tanggal_rilis'] ?? null); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="status">
                                    <i class="fas fa-circle-info"></i>
                                    Status
                                </label>
                            </td>
                            <td>
                                <div class="status-input-container">
                                    <select id="status" name="status" class="status-select" onchange="this.nextElementSibling.className = 'status-badge ' + this.value.toLowerCase()">
                                        <option value="Ongoing" <?= ($komik['status'] == 'Ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                        <option value="Hiatus" <?= ($komik['status'] == 'Hiatus') ? 'selected' : ''; ?>>Hiatus</option>
                                        <option value="Completed" <?= ($komik['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                    <span class="status-badge <?= strtolower($komik['status']); ?>">
                                        <?= htmlspecialchars($komik['status']); ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label>
                                    <i class="fas fa-user"></i>
                                    Pengarang
                                </label>
                            </td>
                            <td>
                                <span class="author-name"><?= htmlspecialchars($komik['pengarang']); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label>
                                    <i class="fas fa-clock"></i>
                                    Pembaruan Terakhir
                                </label>
                            </td>
                            <td>
                                <span class="last-update"><?= formatTanggalIndonesia($chapterDates['pembaruan_terakhir'] ?? null); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="genre">
                                    <i class="fas fa-tags"></i>
                                    Genre
                                </label>
                            </td>
                            <td>
                                <div class="genre-input-container">
                                    <div class="current-genres">
                                        <?php if (!empty($genre)): ?>
                                            <?php foreach (array_unique(array_map('htmlspecialchars', $genre)) as $g): ?>
                                                <span class="genre-tag" onclick="removeGenre(this, '<?= $g ?>')">
                                                    <?= $g ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="genre-input-wrapper">
                                        <input type="text" 
                                               id="genre" 
                                               name="genre" 
                                               value="<?= !empty($genre) ? implode(', ', array_unique(array_map('htmlspecialchars', $genre))) : ''; ?>" 
                                               placeholder="Ketik untuk mencari genre"
                                               autocomplete="off">
                                        <div class="autocomplete-dropdown" id="genreDropdown"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="form-actions">
                        <button type="submit" class="save-button">
                            <i class="fas fa-save"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                    
                    <input type="hidden" name="id" value="<?= htmlspecialchars($komik_id); ?>">
                </div>
            </div>
        </form>
    </div>

    <div class="chapter">
        <div class="chapter-list">
            <div class="chapter-header">
                <h2>
                    <i class="fas fa-list-ol"></i>
                    Chapter Terbaru
                </h2>
                <a href="../upload%20chapter/uploadchapter.php?comic_id=<?= urlencode((string)$komik_id); ?>" class="add-chapter-btn">
                    <i class="fas fa-plus"></i>
                    Tambah Chapter
                </a>
            </div>
            <div class="chapter-container">
                <?php if (!empty($chapters)) : ?>
                    <?php foreach ($chapters as $chapter) : ?>
                        <div class="chapter-item">
                            <div class="chapter-content">
                                <a href="../chapter/chapter.php?id=<?= $chapter['id']; ?>" class="chapter-link">
                                    <h3>
                                        <i class="fas fa-book-open"></i>
                                        Chapter <?= htmlspecialchars($chapter['nomor']); ?>: <?= htmlspecialchars($chapter['judul']); ?>
                                    </h3>
                                    <span class="chapter-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?= formatTanggalIndonesia($chapter['tanggal_rilis']); ?>
                                    </span>
                                </a>
                                <div class="chapter-actions">
                                    <button class="edit-btn" 
                                            onclick="window.location.href='../edit chapter/editchapter.php?id=<?= $chapter['id']; ?>'">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-btn" 
                                            onclick="deleteChapter(<?= $chapter['id']; ?>, '<?= htmlspecialchars($chapter['judul']); ?>')" 
                                            title="Hapus Chapter">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="no-chapters">
                        <i class="fas fa-folder-open"></i>
                        <p>Belum ada chapter tersedia</p>
                        <a href="../upload%20chapter/uploadchapter.php?comic_id=<?= urlencode((string)$komik_id); ?>" class="add-first-chapter">
                            <i class="fas fa-plus"></i>
                            Tambah Chapter Pertama
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="commentPopupOverlay" class="comment-popup-overlay" aria-hidden="true">
        <div class="comment-popup" role="dialog" aria-modal="true" aria-labelledby="commentPopupTitle">
            <div class="comment-popup-header">
                <h3 id="commentPopupTitle"><i class="fas fa-comments"></i> Komentar</h3>
                <button type="button" class="comment-popup-close" id="closeCommentPopupBtn" aria-label="Tutup">&times;</button>
            </div>

            <?php if ($commentNotice !== ''): ?>
                <div class="comment-popup-notice <?= $commentNoticeType === 'success' ? 'success' : 'error'; ?>">
                    <?= htmlspecialchars($commentNotice); ?>
                </div>
            <?php endif; ?>

            <div class="comment-list">
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php $isMyComment = $isLoggedIn && (int)$currentUserId === (int)$comment['user_id']; ?>
                        <?php $isOwnerComment = (!empty($komik['user_nama']) && strcasecmp((string)$comment['username'], (string)$komik['user_nama']) === 0)
                            || (!empty($komik['pengarang']) && strcasecmp((string)$comment['username'], (string)$komik['pengarang']) === 0); ?>
                        <?php $commentDisplayName = $isOwnerComment ? 'Pemilik' : (string)$comment['username']; ?>
                        <div class="comment-message-wrapper <?= $isMyComment ? 'message-right' : 'message-left'; ?>">
                            <?php if (!$isMyComment): ?>
                                <div class="comment-avatar"><?= strtoupper(substr($commentDisplayName, 0, 1)); ?></div>
                            <?php endif; ?>

                            <div class="comment-message-content">
                                <div class="comment-item-head">
                                    <span class="comment-user"><?= htmlspecialchars($commentDisplayName); ?></span>
                                    <span class="comment-time"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></span>
                                </div>

                                <div class="comment-bubble-container" id="commentBubbleWrap<?= (int)$comment['id']; ?>">
                                    <?php if ($isMyComment): ?>
                                        <button type="button" class="comment-more-btn" data-comment-id="<?= (int)$comment['id']; ?>" aria-label="Aksi komentar" title="Aksi komentar">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    <?php endif; ?>

                                    <div class="comment-bubble <?= $isMyComment ? 'user' : 'other'; ?>">
                                        <p class="comment-text" id="commentText<?= (int)$comment['id']; ?>"><?= nl2br(htmlspecialchars($comment['komentar'])); ?></p>
                                    </div>

                                    <?php if ($isMyComment): ?>
                                        <div class="comment-actions-menu" id="commentActions<?= (int)$comment['id']; ?>">
                                            <button type="button" class="comment-action-btn edit" data-comment-id="<?= (int)$comment['id']; ?>">
                                                <i class="fas fa-pen"></i> Edit
                                            </button>
                                            <form method="POST" action="editkomik.php?id=<?= (int)$komik_id; ?>" class="comment-delete-form">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                                                <input type="hidden" name="comment_id" value="<?= (int)$comment['id']; ?>">
                                                <button type="submit" class="comment-action-btn delete">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isMyComment): ?>
                                    <form method="POST" action="editkomik.php?id=<?= (int)$komik_id; ?>" class="comment-edit-form" id="commentEditForm<?= (int)$comment['id']; ?>">
                                        <input type="hidden" name="action" value="edit_comment">
                                        <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                                        <input type="hidden" name="comment_id" value="<?= (int)$comment['id']; ?>">
                                        <textarea name="comment_text" rows="3" maxlength="800"><?= htmlspecialchars($comment['komentar']); ?></textarea>
                                        <div class="comment-edit-actions">
                                            <button type="submit" class="comment-action-btn save"><i class="fas fa-check"></i> Simpan</button>
                                            <button type="button" class="comment-action-btn cancel" data-comment-id="<?= (int)$comment['id']; ?>"><i class="fas fa-xmark"></i> Batal</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="comment-empty">Belum ada komentar.</div>
                <?php endif; ?>
            </div>

            <div class="comment-form-wrap">
                <form method="POST" action="editkomik.php?id=<?= (int)$komik_id; ?>" class="comment-form">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                    <textarea name="comment_text" rows="3" maxlength="800" placeholder="Tulis komentar Anda..."></textarea>
                    <button type="submit" class="comment-submit-btn">
                        <i class="fas fa-paper-plane"></i> Kirim Komentar
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    </div>

    <div class="custom-modal-overlay" id="confirmModal" aria-hidden="true">
        <div class="custom-modal">
            <div class="modal-icon warning" id="confirmIcon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="modal-title" id="confirmTitle">Konfirmasi</div>
            <div class="modal-message" id="confirmMessage">Apakah Anda yakin?</div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" id="confirmCancelBtn">Batal</button>
                <button type="button" class="modal-btn modal-btn-confirm" id="confirmOkBtn">Ya</button>
            </div>
        </div>
    </div>
    <div class="custom-modal-overlay" id="alertModal" aria-hidden="true">
        <div class="custom-modal">
            <div class="modal-icon" id="alertIcon"><i class="fas fa-check-circle"></i></div>
            <div class="modal-title" id="alertTitle">Informasi</div>
            <div class="modal-message" id="alertMessage">Pesan informasi disini.</div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-ok" id="alertOkBtn">Mengerti</button>
            </div>
        </div>
    </div>

    <script>
        function revealEditKomik() {
            document.documentElement.classList.remove('page-loading');
        }
        window.addEventListener('load', revealEditKomik);
        window.addEventListener('pageshow', revealEditKomik);

        const confirmModal = document.getElementById('confirmModal');
        const confirmCancelBtn = document.getElementById('confirmCancelBtn');
        const confirmOkBtn = document.getElementById('confirmOkBtn');
        const confirmTitle = document.getElementById('confirmTitle');
        const confirmMessage = document.getElementById('confirmMessage');
        const alertModal = document.getElementById('alertModal');
        const alertIcon = document.getElementById('alertIcon');
        const alertTitle = document.getElementById('alertTitle');
        const alertMessage = document.getElementById('alertMessage');
        const alertOkBtn = document.getElementById('alertOkBtn');
        let confirmCallback = null;
        let alertCallback = null;
        let alertRedirectUrl = null;

        function showConfirm(optionsOrCallback) {
            if (!confirmModal) {
                const ok = window.confirm('Apakah Anda yakin?');
                if (ok) {
                    if (typeof optionsOrCallback === 'function') optionsOrCallback();
                    if (optionsOrCallback && typeof optionsOrCallback.onConfirm === 'function') optionsOrCallback.onConfirm();
                }
                return;
            }
            let options = {};
            if (typeof optionsOrCallback === 'function') {
                options.onConfirm = optionsOrCallback;
            } else {
                options = optionsOrCallback || {};
            }
            confirmTitle.textContent = options.title || 'Konfirmasi';
            confirmMessage.textContent = options.message || 'Apakah Anda yakin?';
            confirmCancelBtn.textContent = options.cancelText || 'Batal';
            confirmOkBtn.textContent = options.okText || 'Ya';
            confirmCallback = options.onConfirm || null;
            confirmModal.classList.add('active');
        }
        if (confirmCancelBtn) {
            confirmCancelBtn.addEventListener('click', () => {
                confirmModal.classList.remove('active');
                confirmCallback = null;
            });
        }
        if (confirmOkBtn) {
            confirmOkBtn.addEventListener('click', () => {
                confirmModal.classList.remove('active');
                if (confirmCallback) confirmCallback();
            });
        }

        function showAlert(type, title, message, reload = false, redirectUrl = null) {
            if (!alertModal) {
                window.alert(String(message || ''));
                if (redirectUrl) window.location.href = redirectUrl;
                else if (reload) window.location.reload();
                return;
            }
            alertTitle.textContent = title || 'Informasi';
            alertMessage.textContent = message || '';
            alertRedirectUrl = redirectUrl;
            if (type === 'success') {
                alertIcon.className = 'modal-icon success';
                alertIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
            } else if (type === 'error') {
                alertIcon.className = 'modal-icon error';
                alertIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
            } else {
                alertIcon.className = 'modal-icon warning';
                alertIcon.innerHTML = '<i class="fas fa-info-circle"></i>';
            }
            alertCallback = reload ? () => window.location.reload() : null;
            alertModal.classList.add('active');
        }
        if (alertOkBtn) {
            alertOkBtn.addEventListener('click', () => {
                alertModal.classList.remove('active');
                if (alertRedirectUrl) {
                    window.location.href = alertRedirectUrl;
                    return;
                }
                if (alertCallback) alertCallback();
            });
        }

        function setPageLoading(isLoading, titleText, subText) {
            const titleEl = document.getElementById('pageLoadingTitle');
            const subEl = document.getElementById('pageLoadingSub');
            if (titleEl && titleText) titleEl.textContent = titleText;
            if (subEl && subText) subEl.textContent = subText;
            if (isLoading) document.documentElement.classList.add('page-loading');
            else document.documentElement.classList.remove('page-loading');
        }

        (function captureEditKomikMessage() {
            try {
                const params = new URLSearchParams(window.location.search);
                const status = params.get('status');
                const message = params.get('message');
                const title = params.get('title');
                if (!status || !message) return;
                const modalType = (status === 'success' || status === 'error' || status === 'warning') ? status : 'warning';
                const modalTitle = title || (modalType === 'success' ? 'Berhasil' : (modalType === 'error' ? 'Gagal' : 'Informasi'));
                window.addEventListener('load', () => {
                    showAlert(modalType, modalTitle, message, false);
                    params.delete('status');
                    params.delete('message');
                    params.delete('title');
                    const qs = params.toString();
                    const newUrl = window.location.pathname + (qs ? ('?' + qs) : '');
                    window.history.replaceState({}, document.title, newUrl);
                });
            } catch (e) {
            }
        })();
    </script>

    <script>
        (function initCommentPopup() {
            const overlay = document.getElementById('commentPopupOverlay');
            const openBtn = document.getElementById('openCommentPopupBtn');
            const closeBtn = document.getElementById('closeCommentPopupBtn');
            if (!overlay || !openBtn || !closeBtn) return;

            function openPopup() {
                overlay.classList.add('show');
                overlay.setAttribute('aria-hidden', 'false');
            }

            function closePopup() {
                overlay.classList.remove('show');
                overlay.setAttribute('aria-hidden', 'true');
            }

            openBtn.addEventListener('click', openPopup);
            closeBtn.addEventListener('click', closePopup);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closePopup();
            });

            <?php if ($commentNotice !== ''): ?>
            openPopup();
            <?php endif; ?>

            overlay.addEventListener('click', function (e) {
                const moreBtn = e.target.closest('.comment-more-btn');
                if (moreBtn) {
                    const id = moreBtn.getAttribute('data-comment-id');
                    const menu = document.getElementById('commentActions' + id);
                    const currentlyOpen = menu && menu.classList.contains('show');

                    overlay.querySelectorAll('.comment-actions-menu.show').forEach(function (el) {
                        el.classList.remove('show');
                    });

                    if (menu && !currentlyOpen) {
                        menu.classList.add('show');
                    }
                    return;
                }

                if (!e.target.closest('.comment-actions-menu')) {
                    overlay.querySelectorAll('.comment-actions-menu.show').forEach(function (el) {
                        el.classList.remove('show');
                    });
                }

                const editBtn = e.target.closest('.comment-action-btn.edit');
                if (editBtn) {
                    const id = editBtn.getAttribute('data-comment-id');
                    const form = document.getElementById('commentEditForm' + id);
                    const actions = document.getElementById('commentActions' + id);
                    const bubble = document.getElementById('commentBubbleWrap' + id);
                    const text = document.getElementById('commentText' + id);
                    if (form) form.style.display = '';
                    if (actions) actions.classList.remove('show');
                    if (bubble) bubble.style.display = 'none';
                    if (text) text.style.display = 'none';
                    return;
                }

                const cancelBtn = e.target.closest('.comment-action-btn.cancel');
                if (cancelBtn) {
                    const id = cancelBtn.getAttribute('data-comment-id');
                    const form = document.getElementById('commentEditForm' + id);
                    const actions = document.getElementById('commentActions' + id);
                    const bubble = document.getElementById('commentBubbleWrap' + id);
                    const text = document.getElementById('commentText' + id);
                    if (form) form.style.display = 'none';
                    if (actions) actions.style.display = '';
                    if (bubble) bubble.style.display = '';
                    if (text) text.style.display = '';
                }
            });
        })();

        document.querySelectorAll('.comment-delete-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                showConfirm({
                    title: 'Hapus Komentar?',
                    message: 'Komentar yang dihapus tidak bisa dikembalikan.',
                    okText: 'Ya, Hapus',
                    cancelText: 'Batal',
                    onConfirm: () => form.submit()
                });
            });
        });

        document.getElementById('gambar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const coverImg = document.querySelector('.cover-komik img');
            
            if (file) {
                if (file.size > 500 * 1024) {
                    showAlert('warning', 'Tidak Valid', 'Ukuran cover terlalu besar! Maksimal 500KB.');
                    e.target.value = '';
                    return;
                }
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        if (coverImg) {
                            coverImg.src = e.target.result;
                            coverImg.style.display = 'block';
                        } else {
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.alt = 'Preview Gambar';
                            newImg.style.maxWidth = '200px';
                            newImg.style.marginBottom = '10px';
                            newImg.style.borderRadius = '12px';
                            newImg.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.1)';
                            
                            const fileInput = document.getElementById('gambar');
                            fileInput.parentNode.insertBefore(newImg, fileInput);
                        }
                    };
                    
                    reader.readAsDataURL(file);
                } else {
                    showAlert('warning', 'File Tidak Valid', 'File yang dipilih bukan gambar. Silakan pilih file gambar (JPG, PNG, GIF, dll.)');
                    e.target.value = '';
                }
            }
        });

        let lastValue = '';
        
        document.getElementById('genre').addEventListener('input', function(e) {
            let currentValue = e.target.value;
            let cursorPos = e.target.selectionStart;
            
            if (currentValue.length > lastValue.length) {
                let addedChar = currentValue[currentValue.length - 1];
                
                if (addedChar === ' ') {
                    let beforeSpace = currentValue.slice(0, -1);
                    
                    if (beforeSpace.trim() && !beforeSpace.endsWith(',')) {
                        e.target.value = beforeSpace + ', ';
                        e.target.setSelectionRange(beforeSpace.length + 2, beforeSpace.length + 2);
                    }
                }
            }
            
            lastValue = e.target.value;
            
            let cleanValue = e.target.value;
            cleanValue = cleanValue.replace(/,\s*,+/g, ', ');
            cleanValue = cleanValue.replace(/\s{2,}/g, ' ');
            
            if (cleanValue !== e.target.value) {
                e.target.value = cleanValue;
                e.target.setSelectionRange(cursorPos, cursorPos);
            }
        });

        document.getElementById('genre').addEventListener('keyup', function(e) {
            if (e.key === ' ') {
                let value = e.target.value;
                let cursorPos = e.target.selectionStart;
                
                if (value.endsWith(' ') && value.trim().length > 0) {
                    let beforeSpace = value.slice(0, -1);
                    
                    if (!beforeSpace.endsWith(',') && beforeSpace.trim().length > 0) {
                        e.target.value = beforeSpace + ', ';
                        e.target.setSelectionRange(beforeSpace.length + 2, beforeSpace.length + 2);
                    }
                }
            }
        });

        document.getElementById('genre').addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                let value = e.target.value;
                let cursorPos = e.target.selectionStart;
                
                if (cursorPos >= 2 && value.substring(cursorPos - 2, cursorPos) === ', ') {
                    e.preventDefault();
                    e.target.value = value.substring(0, cursorPos - 2) + ' ' + value.substring(cursorPos);
                    e.target.setSelectionRange(cursorPos - 1, cursorPos - 1);
                }
            }
        });

        document.getElementById('genre').addEventListener('blur', function(e) {
            let value = e.target.value.trim();
            
            if (value) {
                let genres = value.split(',')
                    .map(genre => genre.trim())
                    .filter(genre => genre.length > 0);
                
                genres = [...new Set(genres)];
                
                e.target.value = genres.join(', ');
            }
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const genreInput = document.getElementById('genre');
            let value = genreInput.value.trim();
            
            if (value) {
                let genres = value.split(',')
                    .map(genre => genre.trim())
                    .filter(genre => genre.length > 0);
                
                genres = [...new Set(genres)];
                
                genreInput.value = genres.join(', ');
            }
        });

        function deleteChapter(chapterId, chapterTitle) {
            showConfirm({
                title: 'Hapus Chapter?',
                message: `Apakah Anda yakin ingin menghapus chapter "${chapterTitle}"?\n\nPeringatan: Semua gambar dalam chapter ini juga akan dihapus secara permanen!`,
                okText: 'Ya, Hapus',
                cancelText: 'Batal',
                onConfirm: () => {
                    setPageLoading(true, 'Menghapus Chapter...', 'Tunggu sesuai koneksi internet Anda');
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "editkomik_action.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            setPageLoading(false);
                            if (xhr.status !== 200) {
                                showAlert('error', 'Gagal', 'Terjadi kesalahan saat menghapus chapter.');
                                return;
                            }
                            console.log("Respon dari server:", xhr.responseText);
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    showAlert('success', 'Berhasil', response.message, true);
                                } else {
                                    showAlert('error', 'Gagal', response.message);
                                }
                            } catch (e) {
                                if (xhr.responseText.includes("berhasil")) {
                                    showAlert('success', 'Berhasil', xhr.responseText, true);
                                } else {
                                    showAlert('error', 'Gagal', xhr.responseText);
                                }
                            }
                        }
                    };
                    xhr.send("action=delete_chapter&chapter_id=" + chapterId);
                }
            });
        }

        const sinopsisEditor = document.getElementById('sinopsisEditor');
        const sinopsisInput = document.getElementById('sinopsis');

        if (sinopsisEditor) {
            sinopsisEditor.addEventListener('input', function() {
                sinopsisInput.value = this.textContent;
            });

            document.querySelector('form').addEventListener('submit', function() {
                sinopsisInput.value = sinopsisEditor.textContent;
            });
        }

        (function attachSaveConfirmAndLoading() {
            const form = document.querySelector('.container form');
            if (!form) return;

            function normalizeGenreKey(value) {
                const raw = String(value || '').trim();
                if (!raw) return '';
                const parts = raw
                    .split(',')
                    .map(s => s.trim())
                    .filter(Boolean)
                    .map(s => s.toLowerCase());
                const unique = Array.from(new Set(parts));
                unique.sort();
                return unique.join('|');
            }

            function getCurrentEditState() {
                const judulEl = document.getElementById('judul');
                const statusEl = document.getElementById('status');
                const genreEl = document.getElementById('genre');
                const sinopsisText = sinopsisEditor ? String(sinopsisEditor.textContent || '').trim() : '';
                return {
                    judul: judulEl ? String(judulEl.value || '').trim() : '',
                    sinopsis: sinopsisText,
                    status: statusEl ? String(statusEl.value || '').trim() : '',
                    genreKey: normalizeGenreKey(genreEl ? genreEl.value : '')
                };
            }

            const initialState = getCurrentEditState();

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                showConfirm({
                    title: 'Simpan Perubahan?',
                    message: 'Pastikan data yang diubah sudah benar.',
                    okText: 'Ya, Simpan',
                    cancelText: 'Batal',
                    onConfirm: async () => {
                        try {
                            const changedFields = [];
                            const currentState = getCurrentEditState();
                            if (currentState.judul !== initialState.judul) changedFields.push('Judul');
                            if (currentState.sinopsis !== initialState.sinopsis) changedFields.push('Sinopsis');
                            if (currentState.status !== initialState.status) changedFields.push('Status');
                            if (currentState.genreKey !== initialState.genreKey) changedFields.push('Genre');
                            const coverInput = document.getElementById('gambar');
                            if (coverInput && coverInput.files && coverInput.files.length > 0) changedFields.push('Cover');

                            if (changedFields.length === 0) {
                                showAlert('warning', 'Tidak Ada Perubahan', 'Tidak ada perubahan yang perlu disimpan.');
                                return;
                            }

                            const saveBtn = form.querySelector('.save-button');
                            if (saveBtn) {
                                saveBtn.disabled = true;
                                saveBtn.style.opacity = '0.75';
                                saveBtn.style.cursor = 'not-allowed';
                            }

                            const genreInput = document.getElementById('genre');
                            if (genreInput) {
                                let value = genreInput.value.trim();
                                if (value) {
                                    let genres = value.split(',').map(g => g.trim()).filter(g => g.length > 0);
                                    genres = [...new Set(genres)];
                                    genreInput.value = genres.join(', ');
                                }
                            }
                            if (sinopsisEditor && sinopsisInput) {
                                sinopsisInput.value = sinopsisEditor.textContent;
                            }

                            setPageLoading(true, 'Menyimpan Perubahan...', 'Tunggu sesuai koneksi internet Anda');
                            const formData = new FormData(form);
                            formData.append('ajax', '1');

                            const res = await fetch(form.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                }
                            });

                            let payload = null;
                            const rawText = await res.text();
                            try {
                                payload = JSON.parse(rawText);
                            } catch (_) {
                                throw new Error(rawText || 'Terjadi kesalahan saat menyimpan perubahan.');
                            }

                            setPageLoading(false);
                            if (!res.ok || !payload || payload.success !== true) {
                                const msg = (payload && payload.message) ? payload.message : 'Gagal menyimpan perubahan.';
                                showAlert('error', 'Gagal', msg);
                                return;
                            }

                            const changesText = 'Data berhasil diperbarui:\n- ' + changedFields.join('\n- ');
                            showAlert('success', 'Berhasil', changesText, true);
                        } catch (err) {
                            console.error(err);
                            setPageLoading(false);
                            showAlert('error', 'Gagal', (err && err.message) ? err.message : 'Terjadi kesalahan saat menyimpan perubahan.');
                        } finally {
                            const saveBtn = form.querySelector('.save-button');
                            if (saveBtn) {
                                saveBtn.disabled = false;
                                saveBtn.style.opacity = '';
                                saveBtn.style.cursor = '';
                            }
                        }
                    }
                });
            });
        })();
    </script>

</body>
</html>
