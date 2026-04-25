<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include("config.php");

$interactionMessage = '';
$interactionType = '';
$showRatingPopup = false;
$showBookmarkPopup = false;
$showOwnerRatingBlockedPopup = false;
$showCommentPopup = false;
$commentPopupNotice = '';
$commentPopupNoticeType = '';
$isLoggedIn = isset($_SESSION['username']) && $_SESSION['username'] !== '';
$currentUserId = 0;

if (isset($_SESSION['komik_flash']) && is_array($_SESSION['komik_flash'])) {
    $flashData = $_SESSION['komik_flash'];
    $interactionMessage = isset($flashData['message']) ? (string)$flashData['message'] : '';
    $interactionType = isset($flashData['type']) ? (string)$flashData['type'] : '';
    $showRatingPopup = !empty($flashData['show_rating_popup']);
    $showBookmarkPopup = !empty($flashData['show_bookmark_popup']);
    $showOwnerRatingBlockedPopup = !empty($flashData['show_owner_rating_blocked_popup']);
    $showCommentPopup = !empty($flashData['show_comment_popup']);
    $commentPopupNotice = isset($flashData['comment_popup_notice']) ? (string)$flashData['comment_popup_notice'] : '';
    $commentPopupNoticeType = isset($flashData['comment_popup_notice_type']) ? (string)$flashData['comment_popup_notice_type'] : '';
    unset($_SESSION['komik_flash']);
}

if ($isLoggedIn) {
    $currentUsername = $_SESSION['username'];
    $userStmt = $conn->prepare("SELECT id FROM user WHERE user_nama = ? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param("s", $currentUsername);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        if ($userRow = $userRes->fetch_assoc()) {
            $currentUserId = (int)$userRow['id'];
        }
        $userStmt->close();
    }
}

$avgRating = 0.0;
$ratingCount = 0;
$userRating = 0;
$bookmarkCount = 0;
$viewerCount = 0;
$commentCount = 0;
$isBookmarked = false;
$comments = [];

function getChaptersByKomikId($komik_id) {
    global $conn;
    $chapters = [];

    $stmt = $conn->prepare("SELECT id, judul, tanggal_rilis, pembaruan_terakhir FROM chapter WHERE komik_id = ?");
    $stmt->bind_param("i", $komik_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $counter = 1;
    while ($row = $result->fetch_assoc()) {
        $row['nomor'] = $counter++;
        $chapters[] = $row;
    }

    $stmt->close();
    return $chapters;
}

if (isset($_GET['id'])) {
    $komik_id = (int)$_GET['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $handledPostAction = false;
        $postKomikId = isset($_POST['komik_id']) ? (int)$_POST['komik_id'] : 0;
        if ($postKomikId !== $komik_id || $komik_id <= 0) {
            $interactionMessage = 'Permintaan tidak valid.';
            $interactionType = 'error';
            $handledPostAction = true;
        } elseif (!$isLoggedIn || $currentUserId <= 0) {
            $interactionMessage = 'Silakan login terlebih dahulu.';
            $interactionType = 'error';
            if ($_POST['action'] === 'add_comment' || $_POST['action'] === 'edit_comment' || $_POST['action'] === 'delete_comment') {
                $showCommentPopup = true;
                $commentPopupNotice = 'Silakan login untuk mengirim komentar.';
                $commentPopupNoticeType = 'error';
            }
            $handledPostAction = true;
        } elseif ($_POST['action'] === 'toggle_bookmark') {
            $checkStmt = $conn->prepare("SELECT id FROM komik_bookmark WHERE user_id = ? AND komik_id = ? LIMIT 1");
            $checkStmt->bind_param("ii", $currentUserId, $komik_id);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();
            $existing = $checkRes->fetch_assoc();
            $checkStmt->close();

            if ($existing) {
                $delStmt = $conn->prepare("DELETE FROM komik_bookmark WHERE user_id = ? AND komik_id = ?");
                $delStmt->bind_param("ii", $currentUserId, $komik_id);
                $delStmt->execute();
                $delStmt->close();
                $interactionMessage = 'komik ini telah di hapus dari halaman bookmark';
                $interactionType = 'success';
                $showBookmarkPopup = true;
            } else {
                $insStmt = $conn->prepare("INSERT INTO komik_bookmark (user_id, komik_id) VALUES (?, ?)");
                $insStmt->bind_param("ii", $currentUserId, $komik_id);
                $insStmt->execute();
                $insStmt->close();
                $interactionMessage = 'komik ini telah tersimpan di bookmark';
                $interactionType = 'success';
                $showBookmarkPopup = true;
            }
            $handledPostAction = true;
        } elseif ($_POST['action'] === 'set_rating') {
            $ratingValue = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
            if ($ratingValue < 1 || $ratingValue > 5) {
                $interactionMessage = 'Nilai rating tidak valid.';
                $interactionType = 'error';
            } else {
                $ownerStmt = $conn->prepare("SELECT id FROM komik WHERE id = ? AND (user_nama = ? OR pengarang = ?) LIMIT 1");
                $ownerStmt->bind_param("iss", $komik_id, $currentUsername, $currentUsername);
                $ownerStmt->execute();
                $ownerRes = $ownerStmt->get_result();
                $isOwner = $ownerRes && $ownerRes->num_rows > 0;
                $ownerStmt->close();

                if ($isOwner) {
                    $interactionMessage = 'anda tidak memboleh rating komik anda sendiri';
                    $interactionType = 'error';
                    $showOwnerRatingBlockedPopup = true;
                } else {
                    $rateStmt = $conn->prepare("INSERT INTO komik_rating (user_id, komik_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
                    $rateStmt->bind_param("iii", $currentUserId, $komik_id, $ratingValue);
                    $rateStmt->execute();
                    $rateStmt->close();
                    $interactionMessage = 'Rating berhasil disimpan.';
                    $interactionType = 'success';
                    $showRatingPopup = true;
                }
            }
            $handledPostAction = true;
        } elseif ($_POST['action'] === 'add_comment') {
            $commentText = isset($_POST['comment_text']) ? trim((string)$_POST['comment_text']) : '';
            if ($commentText === '') {
                $interactionMessage = 'Komentar tidak boleh kosong.';
                $interactionType = 'error';
                $showCommentPopup = true;
                $commentPopupNotice = $interactionMessage;
                $commentPopupNoticeType = 'error';
            } elseif (mb_strlen($commentText) > 800) {
                $interactionMessage = 'Komentar terlalu panjang. Maksimal 800 karakter.';
                $interactionType = 'error';
                $showCommentPopup = true;
                $commentPopupNotice = $interactionMessage;
                $commentPopupNoticeType = 'error';
            } else {
                $insertCommentStmt = $conn->prepare("INSERT INTO komik_comment (user_id, komik_id, username, komentar) VALUES (?, ?, ?, ?)");
                $insertCommentStmt->bind_param("iiss", $currentUserId, $komik_id, $currentUsername, $commentText);
                $insertCommentStmt->execute();
                $insertCommentStmt->close();

                $interactionMessage = 'Komentar berhasil ditambahkan.';
                $interactionType = 'success';
                $showCommentPopup = true;
                $commentPopupNotice = $interactionMessage;
                $commentPopupNoticeType = 'success';
            }
            $handledPostAction = true;
        } elseif ($_POST['action'] === 'edit_comment') {
            $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
            $commentText = isset($_POST['comment_text']) ? trim((string)$_POST['comment_text']) : '';

            if ($commentId <= 0) {
                $interactionMessage = 'Komentar tidak valid.';
                $interactionType = 'error';
                $showCommentPopup = true;
                $commentPopupNotice = $interactionMessage;
                $commentPopupNoticeType = 'error';
            } elseif ($commentText === '') {
                $interactionMessage = 'Komentar tidak boleh kosong.';
                $interactionType = 'error';
                $showCommentPopup = true;
                $commentPopupNotice = $interactionMessage;
                $commentPopupNoticeType = 'error';
            } elseif (mb_strlen($commentText) > 800) {
                $interactionMessage = 'Komentar terlalu panjang. Maksimal 800 karakter.';
                $interactionType = 'error';
                $showCommentPopup = true;
                $commentPopupNotice = $interactionMessage;
                $commentPopupNoticeType = 'error';
            } else {
                $updateCommentStmt = $conn->prepare("UPDATE komik_comment SET komentar = ? WHERE id = ? AND user_id = ? AND komik_id = ?");
                $updateCommentStmt->bind_param("siii", $commentText, $commentId, $currentUserId, $komik_id);
                $updateCommentStmt->execute();
                $affected = $updateCommentStmt->affected_rows;
                $updateCommentStmt->close();

                if ($affected > 0) {
                    $interactionMessage = 'Komentar berhasil diperbarui.';
                    $interactionType = 'success';
                    $showCommentPopup = true;
                    $commentPopupNotice = $interactionMessage;
                    $commentPopupNoticeType = 'success';
                } else {
                    $checkOwnCommentStmt = $conn->prepare("SELECT id FROM komik_comment WHERE id = ? AND user_id = ? AND komik_id = ? LIMIT 1");
                    $checkOwnCommentStmt->bind_param("iii", $commentId, $currentUserId, $komik_id);
                    $checkOwnCommentStmt->execute();
                    $checkOwnCommentRes = $checkOwnCommentStmt->get_result();
                    $exists = $checkOwnCommentRes && $checkOwnCommentRes->num_rows > 0;
                    $checkOwnCommentStmt->close();

                    if ($exists) {
                        $interactionMessage = 'Komentar tidak berubah.';
                        $interactionType = 'success';
                        $showCommentPopup = true;
                        $commentPopupNotice = $interactionMessage;
                        $commentPopupNoticeType = 'success';
                    } else {
                        $interactionMessage = 'Komentar tidak ditemukan atau tidak memiliki akses.';
                        $interactionType = 'error';
                        $showCommentPopup = true;
                        $commentPopupNotice = $interactionMessage;
                        $commentPopupNoticeType = 'error';
                    }
                }
            }
            $handledPostAction = true;
        } elseif ($_POST['action'] === 'delete_comment') {
            $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;

            if ($commentId <= 0) {
                $interactionMessage = 'Komentar tidak valid.';
                $interactionType = 'error';
                $showCommentPopup = true;
                $commentPopupNotice = $interactionMessage;
                $commentPopupNoticeType = 'error';
            } else {
                $deleteCommentStmt = $conn->prepare("DELETE FROM komik_comment WHERE id = ? AND user_id = ? AND komik_id = ?");
                $deleteCommentStmt->bind_param("iii", $commentId, $currentUserId, $komik_id);
                $deleteCommentStmt->execute();
                $affected = $deleteCommentStmt->affected_rows;
                $deleteCommentStmt->close();

                if ($affected > 0) {
                    $interactionMessage = 'Komentar berhasil dihapus.';
                    $interactionType = 'success';
                    $showCommentPopup = true;
                    $commentPopupNotice = $interactionMessage;
                    $commentPopupNoticeType = 'success';
                } else {
                    $interactionMessage = 'Komentar tidak ditemukan atau tidak memiliki akses.';
                    $interactionType = 'error';
                    $showCommentPopup = true;
                    $commentPopupNotice = $interactionMessage;
                    $commentPopupNoticeType = 'error';
                }
            }
            $handledPostAction = true;
        }

        if ($handledPostAction) {
            $_SESSION['komik_flash'] = [
                'message' => $interactionMessage,
                'type' => $interactionType,
                'show_rating_popup' => $showRatingPopup,
                'show_bookmark_popup' => $showBookmarkPopup,
                'show_owner_rating_blocked_popup' => $showOwnerRatingBlockedPopup,
                'show_comment_popup' => $showCommentPopup,
                'comment_popup_notice' => $commentPopupNotice,
                'comment_popup_notice_type' => $commentPopupNoticeType,
            ];
            header('Location: komik.php?id=' . $komik_id);
            exit();
        }
    }

    $stmt = $conn->prepare("SELECT judul, user_nama, pengarang, sinopsis, tipe_gambar, gambar, status FROM komik WHERE id = ?");
    $stmt->bind_param("i", $komik_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $komik = $result->fetch_assoc();
    } else {
        echo "<script>alert('Komik tidak ditemukan'); window.location.href='index.php';</script>";
        exit();
    }

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
    $chapters = getChaptersByKomikId($komik_id);

    $ratingSummaryStmt = $conn->prepare("SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS rating_count FROM komik_rating WHERE komik_id = ?");
    $ratingSummaryStmt->bind_param("i", $komik_id);
    $ratingSummaryStmt->execute();
    $ratingSummaryRes = $ratingSummaryStmt->get_result();
    if ($ratingSummaryRow = $ratingSummaryRes->fetch_assoc()) {
        $avgRating = (float)$ratingSummaryRow['avg_rating'];
        $ratingCount = (int)$ratingSummaryRow['rating_count'];
    }
    $ratingSummaryStmt->close();

    $bookmarkCountStmt = $conn->prepare("SELECT COUNT(*) AS bookmark_count FROM komik_bookmark WHERE komik_id = ?");
    $bookmarkCountStmt->bind_param("i", $komik_id);
    $bookmarkCountStmt->execute();
    $bookmarkCountRes = $bookmarkCountStmt->get_result();
    if ($bookmarkCountRow = $bookmarkCountRes->fetch_assoc()) {
        $bookmarkCount = (int)$bookmarkCountRow['bookmark_count'];
    }
    $bookmarkCountStmt->close();

    $viewerCountStmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) AS viewer_count FROM riwayat_baca WHERE komik_id = ?");
    $viewerCountStmt->bind_param("i", $komik_id);
    $viewerCountStmt->execute();
    $viewerCountRes = $viewerCountStmt->get_result();
    if ($viewerCountRow = $viewerCountRes->fetch_assoc()) {
        $viewerCount = (int)$viewerCountRow['viewer_count'];
    }
    $viewerCountStmt->close();

    $commentCountStmt = $conn->prepare("SELECT COUNT(*) AS comment_count FROM komik_comment WHERE komik_id = ?");
    $commentCountStmt->bind_param("i", $komik_id);
    $commentCountStmt->execute();
    $commentCountRes = $commentCountStmt->get_result();
    if ($commentCountRow = $commentCountRes->fetch_assoc()) {
        $commentCount = (int)$commentCountRow['comment_count'];
    }
    $commentCountStmt->close();

    $commentListStmt = $conn->prepare("SELECT id, user_id, username, komentar, created_at FROM komik_comment WHERE komik_id = ? ORDER BY created_at ASC, id ASC LIMIT 100");
    $commentListStmt->bind_param("i", $komik_id);
    $commentListStmt->execute();
    $commentListRes = $commentListStmt->get_result();
    while ($commentRow = $commentListRes->fetch_assoc()) {
        $comments[] = $commentRow;
    }
    $commentListStmt->close();

    if ($isLoggedIn && $currentUserId > 0) {
        $userRatingStmt = $conn->prepare("SELECT rating FROM komik_rating WHERE user_id = ? AND komik_id = ? LIMIT 1");
        $userRatingStmt->bind_param("ii", $currentUserId, $komik_id);
        $userRatingStmt->execute();
        $userRatingRes = $userRatingStmt->get_result();
        if ($userRatingRow = $userRatingRes->fetch_assoc()) {
            $userRating = (int)$userRatingRow['rating'];
        }
        $userRatingStmt->close();

        $bookmarkStmt = $conn->prepare("SELECT id FROM komik_bookmark WHERE user_id = ? AND komik_id = ? LIMIT 1");
        $bookmarkStmt->bind_param("ii", $currentUserId, $komik_id);
        $bookmarkStmt->execute();
        $bookmarkRes = $bookmarkStmt->get_result();
        $isBookmarked = $bookmarkRes && $bookmarkRes->num_rows > 0;
        $bookmarkStmt->close();
    }
} else {
    echo "<script>alert('ID tidak diberikan'); window.location.href='komik.php';</script>";
    exit();
}

$stmt = $conn->prepare("SELECT MIN(tanggal_rilis) AS tanggal_rilis, MAX(pembaruan_terakhir) AS pembaruan_terakhir FROM chapter WHERE komik_id = ?");
$stmt->bind_param("i", $komik_id);
$stmt->execute();
$resultDates = $stmt->get_result();
$chapterDates = $resultDates->fetch_assoc();
$stmt->close();

$conn->close();
?>