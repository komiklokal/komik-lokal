<?php
include("config.php");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
if (!isset($_SESSION['username'])) {
    header("Location: ../login/login.php");
    exit();
}
$username = $_SESSION['username'];
if (isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    header('Content-Type: application/json');
    $image_id = filter_var($_POST['image_id'], FILTER_VALIDATE_INT);
    if (!$image_id) {
        echo json_encode(['success' => false, 'message' => 'ID gambar tidak valid']);
        exit();
    }
    $stmt = $conn->prepare("
        SELECT hc.id, hc.chapter_id
        FROM chapter_images hc
        JOIN chapter c ON hc.chapter_id = c.id
        JOIN komik k ON c.komik_id = k.id
        WHERE hc.id = ? AND (k.user_nama = ? OR k.pengarang = ?)
    ");
    $stmt->bind_param("iss", $image_id, $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Gambar tidak ditemukan atau bukan milik Anda']);
        exit();
    }
    $row = $result->fetch_assoc();
    $chapter_id = $row['chapter_id'];
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM chapter_images WHERE id = ?");
    $stmt->bind_param("i", $image_id);
    if ($stmt->execute()) {
        $conn->query("SET @count = 0");
        $stmt = $conn->prepare("UPDATE chapter_images SET urutan = (@count := @count + 1) WHERE chapter_id = ? ORDER BY urutan ASC");
        $stmt->bind_param("i", $chapter_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Halaman berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus halaman']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $chapter_id = filter_var($_POST['chapter_id'], FILTER_VALIDATE_INT);
    $komik_id = filter_var($_POST['komik_id'], FILTER_VALIDATE_INT);
    $judul = trim($_POST['judul']);
    $formatNumberList = function(array $numbers): string {
        $numbers = array_values(array_unique(array_map('intval', $numbers)));
        sort($numbers);
        $count = count($numbers);
        if ($count === 0) return '';
        if ($count === 1) return (string)$numbers[0];
        if ($count === 2) return $numbers[0] . ' dan ' . $numbers[1];
        $last = array_pop($numbers);
        return implode(', ', $numbers) . ', dan ' . $last;
    };
    if (!$chapter_id || !$komik_id) {
        header('Location: ../dashboard.php');
        exit;
    }
    $stmt = $conn->prepare("
        SELECT c.id 
        FROM chapter c
        JOIN komik k ON c.komik_id = k.id
        WHERE c.id = ? AND (k.user_nama = ? OR k.pengarang = ?)
    ");
    $stmt->bind_param("iss", $chapter_id, $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        header('Location: ../dashboard.php');
        exit;
    }
    $stmt->close();

    $currentJudul = '';
    $stmt = $conn->prepare("SELECT judul FROM chapter WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $chapter_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $currentJudul = (string)($row['judul'] ?? '');
        }
        $stmt->close();
    }
    $judulChanged = ($currentJudul !== '' && $judul !== $currentJudul);
    if (empty($judul)) {
        header('Location: editchapter.php?id=' . urlencode((string)$chapter_id) . '&status=warning&title=' . urlencode('Tidak Valid') . '&message=' . urlencode('Judul chapter tidak boleh kosong'));
        exit;
    }
    $isDupTitle = false;
    $stmt = $conn->prepare("SELECT 1 FROM chapter WHERE judul = ? AND komik_id != ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("si", $judul, $komik_id);
        $stmt->execute();
        $dupTitleResult = $stmt->get_result();
        $isDupTitle = ($dupTitleResult && $dupTitleResult->num_rows > 0);
        $stmt->close();
    }
    $existingOrder = 0;
    $dupImagePageNumbers = [];
    $oversizePageNumbers = [];
    if (isset($_FILES['chapterImages']) && !empty($_FILES['chapterImages']['name'][0])) {
        $stmt_order = $conn->prepare("SELECT COALESCE(MAX(urutan), 0) as max_order FROM chapter_images WHERE chapter_id = ?");
        if ($stmt_order) {
            $stmt_order->bind_param("i", $chapter_id);
            $stmt_order->execute();
            $res_order = $stmt_order->get_result();
            if ($res_order) {
                $row_order = $res_order->fetch_assoc();
                $existingOrder = (int)($row_order['max_order'] ?? 0);
            }
            $stmt_order->close();
        }
        $files = $_FILES['chapterImages'];
        $total_files = count($files['name']);
        $max_file_size = 500 * 1024;
        $stmt = $conn->prepare("SELECT 1 FROM chapter_images WHERE gambar = ? LIMIT 1");
        if ($stmt) for ($i = 0; $i < $total_files; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            if ((int)($files['size'][$i] ?? 0) > $max_file_size) {
                $oversizePageNumbers[] = $existingOrder + ($i + 1);
                continue;
            }
            $file_type = $files['type'][$i] ?? '';
            if (strpos($file_type, 'image/') !== 0) {
                continue;
            }
            $image_data = @file_get_contents($files['tmp_name'][$i]);
            if ($image_data === false || $image_data === '') {
                continue;
            }
            $null = NULL;
            $stmt->bind_param('b', $null);
            $stmt->send_long_data(0, $image_data);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $dupImagePageNumbers[] = $existingOrder + ($i + 1);
            }
                $stmt->free_result();
            }
        }
        if ($stmt) $stmt->close();
    }
    if ($isDupTitle || count($dupImagePageNumbers) > 0 || (!empty($oversizePageNumbers))) {
        $messages = [];
        if ($isDupTitle) {
            $messages[] = 'Judul chapter sama dengan komik lain.';
        }
        if (count($dupImagePageNumbers) > 0) {
            $messages[] = 'Gambar yang sama pada hal: ' . $formatNumberList($dupImagePageNumbers) . '.';
        }
        if (!empty($oversizePageNumbers)) {
            $unique = array_values(array_unique($oversizePageNumbers));
            sort($unique);
            $messages[] = 'Ukuran gambar terlalu besar (maks 500KB) pada hal: ' . $formatNumberList($unique) . '.';
        }
        header('Location: editchapter.php?id=' . urlencode((string)$chapter_id)
            . '&status=error&title=' . urlencode('Gagal')
            . '&message=' . urlencode(implode("\n", $messages)));
        exit;
    }
    $stmt = $conn->prepare("UPDATE chapter SET judul = ?, pembaruan_terakhir = NOW() WHERE id = ?");
    $stmt->bind_param("si", $judul, $chapter_id);
    if (!$stmt->execute()) {
        header('Location: editchapter.php?id=' . urlencode((string)$chapter_id) . '&status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Gagal mengupdate judul chapter'));
        exit;
    }
    $stmt->close();
    if ($currentJudul === '') {
        $judulChanged = true;
    }

    $insertedImageCount = 0;
    if (isset($_FILES['chapterImages']) && !empty($_FILES['chapterImages']['name'][0])) {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(urutan), 0) as max_order FROM chapter_images WHERE chapter_id = ?");
        $stmt->bind_param("i", $chapter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_order = $row['max_order'];
        $stmt->close();
        $files = $_FILES['chapterImages'];
        $total_files = count($files['name']);
        $max_file_size = 500 * 1024;
        $stmt = $conn->prepare("INSERT INTO chapter_images (chapter_id, tipe_gambar, gambar, urutan) VALUES (?, ?, ?, ?)");
        for ($i = 0; $i < $total_files; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                if ((int)($files['size'][$i] ?? 0) > $max_file_size) {
                    continue;
                }
                $file_type = $files['type'][$i];
                if (strpos($file_type, 'image/') === 0) {
                    $image_data = file_get_contents($files['tmp_name'][$i]);
                    $current_order++;
                    $stmt->bind_param("issi", $chapter_id, $file_type, $image_data, $current_order);
                    if ($stmt->execute()) {
                        $insertedImageCount++;
                    }
                }
            }
        }
        $stmt->close();
    }

    $pagesChanged = ($insertedImageCount > 0);
    if ($judulChanged && $pagesChanged) {
        $successMessage = 'Chapter dan Judul berhasil diupdate!';
    } elseif ($judulChanged) {
        $successMessage = 'Judul Chapter berhasil diupdate!';
    } elseif ($pagesChanged) {
        $successMessage = 'Halaman Chapter berhasil diupdate!';
    } else {
        $successMessage = 'Chapter berhasil diupdate!';
    }

    header('Location: editchapter.php?id=' . urlencode((string)$chapter_id) . '&status=success&title=' . urlencode('Berhasil') . '&message=' . urlencode($successMessage));
    exit;
}
header('Location: ../dashboard.php');
exit;
?>
