<?php
include("config.php");
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($_SESSION['username'])) {
    header("Location: ../login/login.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $judul = $conn->real_escape_string($_POST["judul"]);
    $pengarang = $conn->real_escape_string($_POST["pengarang"]);
    $sinopsis = $conn->real_escape_string($_POST["sinopsis"]);
    $genre_input = explode(",", $_POST["genre"]);
    $status = $conn->real_escape_string($_POST["status"]);
    $username = $_SESSION['username'];
    if (isset($_FILES["cover"]) && $_FILES["cover"]["error"] == 0) {
        $max_image_size = 500 * 1024; // 500KB
        if (isset($_FILES["cover"]["size"]) && (int)$_FILES["cover"]["size"] > $max_image_size) {
            header('Location: buatkomik.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Ukuran cover terlalu besar. Maksimal 500KB.'));
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['cover']['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png'];

        if (!in_array($mimeType, $allowedMimes)) {
            header('Location: buatkomik.php?status=error&message=' . urlencode('Hanya gambar JPEG atau PNG yang diizinkan.'));
            exit();
        }

        $imageInfo = getimagesize($_FILES["cover"]["tmp_name"]);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        if ($width != 1080 || $height != 1920) {
            header('Location: buatkomik.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Ukuran cover harus 1080x1920 pixel'));
            exit;
        }
        $gambar = file_get_contents($_FILES['cover']['tmp_name']);
        $gambar = base64_encode($gambar);
        $tipe_gambar = $_FILES['cover']['type'];
        $usedParts = [];
        if ($judul !== '') {
            $tStmt = $conn->prepare("SELECT 1 FROM komik WHERE judul = ? LIMIT 1");
            if ($tStmt) {
                $tStmt->bind_param("s", $judul);
                if ($tStmt->execute()) {
                    $tRes = $tStmt->get_result();
                    if ($tRes && $tRes->num_rows > 0) {
                        $usedParts[] = 'Judul komik';
                    }
                }
                $tStmt->close();
            }
        }
        if ($sinopsis !== '') {
            $sStmt = $conn->prepare("SELECT 1 FROM komik WHERE sinopsis = ? LIMIT 1");
            if ($sStmt) {
                $sStmt->bind_param("s", $sinopsis);
                if ($sStmt->execute()) {
                    $sRes = $sStmt->get_result();
                    if ($sRes && $sRes->num_rows > 0) {
                        $usedParts[] = 'Sinopsis';
                    }
                }
                $sStmt->close();
            }
        }
        if ($gambar !== '') {
            $cStmt = $conn->prepare("SELECT 1 FROM komik WHERE gambar = ? LIMIT 1");
            if ($cStmt) {
                $cStmt->bind_param("s", $gambar);
                if ($cStmt->execute()) {
                    $cRes = $cStmt->get_result();
                    if ($cRes && $cRes->num_rows > 0) {
                        $usedParts[] = 'Sampul/Cover komik';
                    }
                }
                $cStmt->close();
            }
        }
        $chapterJudulInputRaw = trim($_POST['chapterJudul'] ?? '');
        $chapterJudulInput = $chapterJudulInputRaw !== '' ? $conn->real_escape_string($chapterJudulInputRaw) : '';
        if ($chapterJudulInput !== '') {
            $ctStmt = $conn->prepare("SELECT 1 FROM chapter WHERE judul = ? LIMIT 1");
            if ($ctStmt) {
                $ctStmt->bind_param("s", $chapterJudulInput);
                if ($ctStmt->execute()) {
                    $ctRes = $ctStmt->get_result();
                    if ($ctRes && $ctRes->num_rows > 0) {
                        $usedParts[] = 'Judul chapter';
                    }
                }
                $ctStmt->close();
            }
        }
        $formatIndoNumberList = function(array $numbers): string {
            $numbers = array_values($numbers);
            $count = count($numbers);
            if ($count === 0) return '';
            if ($count === 1) return (string)$numbers[0];
            if ($count === 2) return $numbers[0] . ' dan ' . $numbers[1];
            $last = array_pop($numbers);
            return implode(', ', $numbers) . ', dan ' . $last;
        };
        if (isset($_FILES['chapterImages']) && isset($_FILES['chapterImages']['tmp_name']) && is_array($_FILES['chapterImages']['tmp_name'])) {
            $chapterImages = $_FILES['chapterImages'];
            $invalidMimePages = [];
            $totalMimeCheck = isset($chapterImages['tmp_name']) && is_array($chapterImages['tmp_name']) ? count($chapterImages['tmp_name']) : 0;
            for ($i = 0; $i < $totalMimeCheck; $i++) {
                if (!isset($chapterImages['error'][$i]) || $chapterImages['error'][$i] !== 0) {
                    continue;
                }
                $tmp = $chapterImages['tmp_name'][$i] ?? '';
                if ($tmp === '' || !is_uploaded_file($tmp)) {
                    continue;
                }
                $pageFinfo = finfo_open(FILEINFO_MIME_TYPE);
                $pageMimeType = finfo_file($pageFinfo, $tmp);
                finfo_close($pageFinfo);
                if (!in_array($pageMimeType, $allowedMimes)) {
                    $invalidMimePages[] = $i + 1;
                }
            }
            if (!empty($invalidMimePages)) {
                $uniqueNums = array_values(array_unique($invalidMimePages));
                sort($uniqueNums);
                header('Location: buatkomik.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Format gambar chapter harus JPEG atau PNG pada hal: ' . $formatIndoNumberList($uniqueNums) . '.'));
                exit;
            }

            $oversizePages = [];
            $totalSizeCheck = isset($chapterImages['size']) && is_array($chapterImages['size']) ? count($chapterImages['size']) : 0;
            for ($i = 0; $i < $totalSizeCheck; $i++) {
                if (!isset($chapterImages['error'][$i]) || $chapterImages['error'][$i] !== 0) {
                    continue;
                }
                if ((int)($chapterImages['size'][$i] ?? 0) > $max_image_size) {
                    $oversizePages[] = $i + 1;
                }
            }
            if (!empty($oversizePages)) {
                $uniqueNums = array_values(array_unique($oversizePages));
                sort($uniqueNums);
                header('Location: buatkomik.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Ukuran gambar chapter terlalu besar (maks 500KB) pada hal: ' . $formatIndoNumberList($uniqueNums) . '.'));
                exit;
            }
            $duplicatePageNumbers = [];
            $ciStmt = $conn->prepare("SELECT 1 FROM chapter_images WHERE gambar = ? LIMIT 1");
            if ($ciStmt) {
                $total = count($chapterImages['tmp_name']);
                for ($i = 0; $i < $total; $i++) {
                    if (!isset($chapterImages['error'][$i]) || $chapterImages['error'][$i] !== 0) {
                        continue;
                    }
                    $tmp = $chapterImages['tmp_name'][$i] ?? '';
                    if ($tmp === '' || !is_uploaded_file($tmp)) {
                        continue;
                    }
                    $bytes = file_get_contents($tmp);
                    if ($bytes === false || $bytes === '') {
                        continue;
                    }
                    $null = NULL;
                    $ciStmt->bind_param("b", $null);
                    $ciStmt->send_long_data(0, $bytes);
                    if ($ciStmt->execute()) {
                        $ciStmt->store_result();
                        if ($ciStmt->num_rows > 0) {
                            $duplicatePageNumbers[] = $i + 1;
                        }
                        $ciStmt->free_result();
                    }
                }
                $ciStmt->close();
            }
            if (!empty($duplicatePageNumbers)) {
                $uniqueNums = array_values(array_unique($duplicatePageNumbers));
                sort($uniqueNums);
                $usedParts[] = 'Gambar yang sama pada hal: ' . $formatIndoNumberList($uniqueNums);
            }
        }
        if (!empty($usedParts)) {
            $messageLines = [
                'Beberapa data yang Anda masukkan sudah terpakai.',
                '',
                'Bagian yang sudah terpakai:',
            ];
            foreach (array_values(array_unique($usedParts)) as $part) {
                $messageLines[] = '- ' . $part;
            }
            $message = implode("\n", $messageLines);
            header('Location: buatkomik.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode($message));
            exit;
        }
        $sql = "INSERT INTO komik (judul, pengarang, sinopsis, tipe_gambar, gambar, status, user_nama) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $judul, $pengarang, $sinopsis, $tipe_gambar, $gambar, $status, $username);
        if ($stmt->execute()) {
            $komik_id = $stmt->insert_id;
            foreach ($genre_input as $genre) {
                $genre = trim($genre);
                if (!empty($genre)) {
                    $result = $conn->query("SELECT id FROM genre WHERE nama = '$genre'");
                    if ($result->num_rows == 0) {
                        $conn->query("INSERT INTO genre (nama) VALUES ('$genre')");
                        $genre_id = $conn->insert_id;
                    }
                    else {
                        $row = $result->fetch_assoc();
                        $genre_id = $row['id'];
                    }
                    $conn->query("INSERT INTO komik_genre (komik_id, genre_id) VALUES ('$komik_id', '$genre_id')");
                }
            }
            if (!empty($_POST['chapterJudul']) && isset($_FILES['chapterImages']) && !empty($_FILES['chapterImages']['tmp_name'][0])) {
                $chapterJudul = $conn->real_escape_string($_POST['chapterJudul']);
                $chapterImages = $_FILES['chapterImages'];
                $firstValidIndex = array_search(true, array_map('is_uploaded_file', $chapterImages['tmp_name']));
                if ($firstValidIndex !== false) {
                    $chapterCover = file_get_contents($chapterImages['tmp_name'][$firstValidIndex]);
                    $chapterCoverType = $chapterImages['type'][$firstValidIndex];
                    $sqlChapter = "INSERT INTO chapter (komik_id, judul, cover, tipe_cover, tanggal_rilis, pembaruan_terakhir) 
                                   VALUES (?, ?, ?, ?, NOW(), NOW())";
                    $stmtChapter = $conn->prepare($sqlChapter);
                    $stmtChapter->bind_param("isss", $komik_id, $chapterJudul, $chapterCover, $chapterCoverType);
                    if ($stmtChapter->execute()) {
                        $chapter_id = $stmtChapter->insert_id;
                        foreach ($chapterImages['tmp_name'] as $i => $tmp_name) {
                            if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                                $imgData = file_get_contents($tmp_name);
                                $imgType = $chapterImages['type'][$i];
                                $urutan = $i + 1;
                                $sqlImg = "INSERT INTO chapter_images (chapter_id, gambar, tipe_gambar, urutan) VALUES (?, ?, ?, ?)";
                                $stmtImg = $conn->prepare($sqlImg);
                                $stmtImg->bind_param("issi", $chapter_id, $imgData, $imgType, $urutan);
                                $stmtImg->send_long_data(1, $imgData);
                                $stmtImg->execute();
                            }
                        }
                    }
                }
            }
            header('Location: buatkomik.php?status=success&title=' . urlencode('Berhasil') . '&message=' . urlencode('Komik dan chapter pertama berhasil diupload') . '&redirect=' . urlencode('../creator/creator.php'));
            exit;
        }
        else {
            echo "Error: " . $stmt->error;
        }
    }
    else {
        header('Location: buatkomik.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode('Terjadi kesalahan saat mengunggah cover komik'));
        exit;
    }
}
$conn->close();
?>
