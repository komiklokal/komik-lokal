<?php
include("config.php");

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_chapter') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['chapter_id']) || empty($_POST['chapter_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Chapter ID tidak diberikan'
        ]);
        exit();
    }
    
    $chapter_id = $_POST['chapter_id'];
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("DELETE FROM chapter_images WHERE chapter_id = ?");
        $stmt->bind_param("i", $chapter_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM chapter WHERE id = ?");
        $stmt->bind_param("i", $chapter_id);
        $result = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($result && $affected_rows > 0) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Chapter beserta semua gambar berhasil dihapus'
            ]);
        } else {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Chapter tidak ditemukan atau sudah dihapus'
            ]);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menghapus chapter: ' . $e->getMessage()
        ]);
    }
    
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $isAjax = false;
    if (isset($_POST['ajax']) && (string)$_POST['ajax'] === '1') {
        $isAjax = true;
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $isAjax = true;
    }
    if (!empty($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        $isAjax = true;
    }

    $komik_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $judul = trim($_POST['judul']);
    $sinopsis = trim($_POST['sinopsis']);
    $status = trim($_POST['status']);
    $genre = isset($_POST['genre']) ? trim($_POST['genre']) : '';

    if (!$komik_id || empty($judul)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
            exit;
        }
        $message = urlencode('Data tidak valid');
        header("Location: editkomik.php?id=" . urlencode((string)($_POST['id'] ?? '')) . "&status=error&message={$message}");
        exit;
    }

    $stmt_current = $conn->prepare("SELECT judul, sinopsis, tipe_gambar, gambar FROM komik WHERE id = ? LIMIT 1");
    if (!$stmt_current) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
            exit;
        }
        $message = urlencode('Terjadi kesalahan server.');
        header("Location: editkomik.php?id={$komik_id}&status=error&title=Gagal&message={$message}");
        exit;
    }
    $stmt_current->bind_param("i", $komik_id);
    $stmt_current->execute();
    $res_current = $stmt_current->get_result();
    $current = $res_current ? $res_current->fetch_assoc() : null;
    $stmt_current->close();
    if (!$current) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Komik tidak ditemukan.']);
            exit;
        }
        $message = urlencode('Komik tidak ditemukan.');
        header("Location: editkomik.php?id={$komik_id}&status=error&title=Gagal&message={$message}");
        exit;
    }

    $changedParts = [];
    $judulChanged = trim((string)($current['judul'] ?? '')) !== $judul;
    $sinopsisChanged = trim((string)($current['sinopsis'] ?? '')) !== $sinopsis;
    if ($judulChanged) $changedParts[] = 'Judul';
    if ($sinopsisChanged) $changedParts[] = 'Sinopsis';

    $newCoverData = null;
    $newCoverType = null;
    $coverChanged = false;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['gambar'];
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        $max_size = 500 * 1024;
        if ((int)($file['size'] ?? 0) > $max_size) {
            $msg = 'Ukuran cover terlalu besar. Maksimal 500KB.';
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(413);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $message = urlencode($msg);
            $title = urlencode('Gagal');
            header("Location: editkomik.php?id={$komik_id}&status=error&title={$title}&message={$message}");
            exit;
        }
        if (!in_array((string)($file['type'] ?? ''), $allowed)) {
            $msg = 'Format gambar tidak didukung.';
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(415);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $message = urlencode($msg);
            $title = urlencode('Gagal');
            header("Location: editkomik.php?id={$komik_id}&status=error&title={$title}&message={$message}");
            exit;
        }
        $newCoverData = base64_encode(file_get_contents($file['tmp_name']));
        $newCoverType = $file['type'];
        $coverChanged = true;
        $changedParts[] = 'Cover';
    }

    $duplicateParts = [];
    if ($judulChanged) {
        $stmt_dup = $conn->prepare("SELECT 1 FROM komik WHERE judul = ? AND id != ? LIMIT 1");
        if ($stmt_dup) {
            $stmt_dup->bind_param("si", $judul, $komik_id);
            $stmt_dup->execute();
            $stmt_dup->store_result();
            if ($stmt_dup->num_rows > 0) $duplicateParts[] = 'Judul';
            $stmt_dup->close();
        }
    }
    if ($sinopsisChanged) {
        $stmt_dup = $conn->prepare("SELECT 1 FROM komik WHERE sinopsis = ? AND id != ? LIMIT 1");
        if ($stmt_dup) {
            $stmt_dup->bind_param("si", $sinopsis, $komik_id);
            $stmt_dup->execute();
            $stmt_dup->store_result();
            if ($stmt_dup->num_rows > 0) $duplicateParts[] = 'Sinopsis';
            $stmt_dup->close();
        }
    }
    if ($coverChanged && $newCoverData !== null) {
        $stmt_dup = $conn->prepare("SELECT 1 FROM komik WHERE gambar = ? AND id != ? LIMIT 1");
        if ($stmt_dup) {
            $stmt_dup->bind_param("si", $newCoverData, $komik_id);
            $stmt_dup->execute();
            $stmt_dup->store_result();
            if ($stmt_dup->num_rows > 0) $duplicateParts[] = 'Cover';
            $stmt_dup->close();
        }
    }

    if (!empty($duplicateParts)) {
        $list = "- " . implode("\n- ", array_values(array_unique($duplicateParts)));
        $msg = "Beberapa data yang Anda masukkan sudah terpakai. Bagian yang sudah terpakai:\n" . $list;
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $msg, 'duplicates' => array_values(array_unique($duplicateParts))]);
            exit;
        }
        $message = urlencode($msg);
        $title = urlencode('Gagal');
        header("Location: editkomik.php?id={$komik_id}&status=error&title={$title}&message={$message}");
        exit;
    }

    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("UPDATE komik SET judul = ?, sinopsis = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssi", $judul, $sinopsis, $status, $komik_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal memperbarui data komik");
        }
        $stmt->close();

        if ($coverChanged && $newCoverData !== null && $newCoverType !== null) {
            $stmt = $conn->prepare("UPDATE komik SET tipe_gambar=?, gambar=? WHERE id=?");
            $stmt->bind_param("ssi", $newCoverType, $newCoverData, $komik_id);
            if (!$stmt->execute()) {
                throw new Exception("Gagal mengupload gambar");
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM komik_genre WHERE komik_id = ?");
        $stmt->bind_param("i", $komik_id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal menghapus genre lama");
        }
        $stmt->close();

        if ($genre !== '') {
            $names = array_map('trim', explode(',', $genre));
            foreach ($names as $name) {
                if ($name === '') continue;
                
                $g = $conn->prepare("SELECT id FROM genre WHERE nama=?");
                $g->bind_param("s", $name);
                $g->execute();
                $res = $g->get_result();
                
                if ($res->num_rows) {
                    $row = $res->fetch_assoc();
                    $genre_id = $row['id'];
                } else {
                    $ins = $conn->prepare("INSERT INTO genre(nama) VALUES(?)");
                    $ins->bind_param("s", $name);
                    if (!$ins->execute()) {
                        throw new Exception("Gagal menambahkan genre baru");
                    }
                    $genre_id = $ins->insert_id;
                    $ins->close();
                }
                $g->close();

                $rg = $conn->prepare("INSERT INTO komik_genre(komik_id,genre_id) VALUES(?,?)");
                $rg->bind_param("ii", $komik_id, $genre_id);
                if (!$rg->execute()) {
                    throw new Exception("Gagal menambahkan relasi genre");
                }
                $rg->close();
            }
        }
        
        $conn->commit();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui']);
            exit;
        }
        $message = urlencode('Data berhasil diperbarui');
        header("Location: editkomik.php?id={$komik_id}&status=success&message={$message}");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
        $message = urlencode('Error: ' . $e->getMessage());
        header("Location: editkomik.php?id={$komik_id}&status=error&message={$message}");
        exit;
    }
}
$conn->close();
?>
