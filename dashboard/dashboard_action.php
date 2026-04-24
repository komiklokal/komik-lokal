<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
]);
session_start();
include("config.php");

if (
    isset($_SESSION['username']) &&
    !empty($_SESSION['username']) &&
    empty($_SESSION['is_guest'])
) {
    $session_timeout = 3600;
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $session_timeout) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: ../login/login.php?reason=timeout");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

$userData = null;
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $sql = "SELECT id, user_nama, user_email, profile_image_blob, profile_image_type FROM user WHERE user_nama = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
    }
    $stmt->close();
}
$searchQuery = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
}
if (empty($searchQuery)) {
    $sqlLatest = "
        SELECT k.*,
               COALESCE((
                   SELECT GROUP_CONCAT(g.nama ORDER BY g.nama SEPARATOR ', ')
                   FROM komik_genre kg
                   JOIN genre g ON g.id = kg.genre_id
                   WHERE kg.komik_id = k.id
               ), '-') AS genre_list,
               COALESCE((SELECT ROUND(AVG(kr.rating), 1) FROM komik_rating kr WHERE kr.komik_id = k.id), 0) AS avg_rating,
               COALESCE((SELECT COUNT(DISTINCT rbv.user_id) FROM riwayat_baca rbv WHERE rbv.komik_id = k.id), 0) AS viewer_count
        FROM komik k
        ORDER BY k.created_at DESC
        LIMIT 10
    ";
    $stmtLatest = $conn->prepare($sqlLatest);
    $stmtLatest->execute();
    $resultLatest = $stmtLatest->get_result();
    $latestKomik = [];
    if ($resultLatest->num_rows > 0) {
        while ($row = $resultLatest->fetch_assoc()) {
            $latestKomik[] = $row;
        }
    }
    $stmtLatest->close();
} else {
    $latestKomik = []; 
}

$rekomendasiKomik = [];
if (empty($searchQuery)) {
    $userId = !empty($userData['id']) ? (int)$userData['id'] : 0;
    $hasHistory = false;
    $kandidatRekomendasi = [];

    if ($userId > 0) {
        $historyCheckStmt = $conn->prepare("SELECT 1 FROM riwayat_baca WHERE user_id = ? LIMIT 1");
        if ($historyCheckStmt) {
            $historyCheckStmt->bind_param("i", $userId);
            $historyCheckStmt->execute();
            $historyCheckRes = $historyCheckStmt->get_result();
            $hasHistory = $historyCheckRes && $historyCheckRes->num_rows > 0;
            $historyCheckStmt->close();
        }
    }

    if ($hasHistory) {
        $sqlRekomendasi = "
            SELECT k.*,
                   COALESCE((
                       SELECT GROUP_CONCAT(g.nama ORDER BY g.nama SEPARATOR ', ')
                       FROM komik_genre kg
                       JOIN genre g ON g.id = kg.genre_id
                       WHERE kg.komik_id = k.id
                   ), '-') AS genre_list,
                   COALESCE((SELECT ROUND(AVG(kr.rating), 1) FROM komik_rating kr WHERE kr.komik_id = k.id), 0) AS avg_rating,
                   COALESCE((SELECT COUNT(DISTINCT rbv.user_id) FROM riwayat_baca rbv WHERE rbv.komik_id = k.id), 0) AS viewer_count,
                   COALESCE(SUM(ug.genre_weight), 0) AS recommendation_score
            FROM komik k
            LEFT JOIN komik_genre kgm ON kgm.komik_id = k.id
            LEFT JOIN (
                SELECT kg2.genre_id, COUNT(*) AS genre_weight
                FROM riwayat_baca rb2
                JOIN komik_genre kg2 ON kg2.komik_id = rb2.komik_id
                WHERE rb2.user_id = ?
                GROUP BY kg2.genre_id
            ) ug ON ug.genre_id = kgm.genre_id
            WHERE k.id NOT IN (
                SELECT rb3.komik_id FROM riwayat_baca rb3 WHERE rb3.user_id = ?
            )
            GROUP BY k.id
            HAVING recommendation_score > 0
            ORDER BY recommendation_score DESC, viewer_count DESC, avg_rating DESC, k.created_at DESC
            LIMIT 50
        ";
        $stmtRekomendasi = $conn->prepare($sqlRekomendasi);
        if ($stmtRekomendasi) {
            $stmtRekomendasi->bind_param("ii", $userId, $userId);
            $stmtRekomendasi->execute();
            $resultRekomendasi = $stmtRekomendasi->get_result();
            if ($resultRekomendasi->num_rows > 0) {
                while ($row = $resultRekomendasi->fetch_assoc()) {
                    $kandidatRekomendasi[] = $row;
                }
            }
            $stmtRekomendasi->close();
        }

        if (!empty($kandidatRekomendasi)) {
            shuffle($kandidatRekomendasi);
            $rekomendasiKomik = array_slice($kandidatRekomendasi, 0, 5);
        }
    }

    if (empty($rekomendasiKomik)) {
        $kandidat = [];
        $sqlRekomendasi = "
            SELECT k.*,
                   COALESCE((
                       SELECT GROUP_CONCAT(g.nama ORDER BY g.nama SEPARATOR ', ')
                       FROM komik_genre kg
                       JOIN genre g ON g.id = kg.genre_id
                       WHERE kg.komik_id = k.id
                   ), '-') AS genre_list,
                   COALESCE((SELECT ROUND(AVG(kr.rating), 1) FROM komik_rating kr WHERE kr.komik_id = k.id), 0) AS avg_rating,
                   COALESCE((SELECT COUNT(DISTINCT rbv.user_id) FROM riwayat_baca rbv WHERE rbv.komik_id = k.id), 0) AS viewer_count
            FROM komik k
            ORDER BY viewer_count DESC, avg_rating DESC, k.created_at DESC
            LIMIT 20
        ";
        $stmtRekomendasi = $conn->prepare($sqlRekomendasi);
        if ($stmtRekomendasi) {
            $stmtRekomendasi->execute();
            $resultRekomendasi = $stmtRekomendasi->get_result();
            if ($resultRekomendasi->num_rows > 0) {
                while ($row = $resultRekomendasi->fetch_assoc()) {
                    $kandidat[] = $row;
                }
            }
            if (!empty($kandidat)) {
                shuffle($kandidat);
                $rekomendasiKomik = array_slice($kandidat, 0, 5);
            }
            $stmtRekomendasi->close();
        }
    }
}

$riwayatBacaKomik = [];
if (empty($searchQuery) && !empty($userData) && !empty($userData['id'])) {
    $userId = (int)$userData['id'];
    $sqlRiwayat = "
        SELECT k.*, MAX(rb.tanggal_baca) AS terakhir_dibaca,
               COALESCE((
                   SELECT GROUP_CONCAT(g.nama ORDER BY g.nama SEPARATOR ', ')
                   FROM komik_genre kg
                   JOIN genre g ON g.id = kg.genre_id
                   WHERE kg.komik_id = k.id
             ), '-') AS genre_list,
             COALESCE((SELECT ROUND(AVG(kr.rating), 1) FROM komik_rating kr WHERE kr.komik_id = k.id), 0) AS avg_rating,
             COALESCE((SELECT COUNT(DISTINCT rbv.user_id) FROM riwayat_baca rbv WHERE rbv.komik_id = k.id), 0) AS viewer_count
        FROM riwayat_baca rb
        JOIN komik k ON rb.komik_id = k.id
        WHERE rb.user_id = ?
        GROUP BY k.id
        ORDER BY terakhir_dibaca DESC
        LIMIT 5
    ";
    $stmtRiwayat = $conn->prepare($sqlRiwayat);
    if ($stmtRiwayat) {
        $stmtRiwayat->bind_param("i", $userId);
        $stmtRiwayat->execute();
        $resultRiwayat = $stmtRiwayat->get_result();
        while ($row = $resultRiwayat->fetch_assoc()) {
            $riwayatBacaKomik[] = $row;
        }
        $stmtRiwayat->close();
    }
}
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;
$sort = $_GET['sort'] ?? 'newest';
$allowedOrderBy = [
    'newest'     => 'k.created_at DESC',
    'oldest'     => 'k.created_at ASC',
    'title_asc'  => 'k.judul ASC',
    'title_desc' => 'k.judul DESC',
];
$orderBy = $allowedOrderBy[$sort] ?? 'k.created_at DESC';
$orderByOuter = str_replace('k.', 'b.', $orderBy);
$totalKomik = 0;
if (!empty($searchQuery)) {
    $search = $searchQuery;
    $countSql = "SELECT COUNT(*) as total
                 FROM (
                     SELECT k.id
                     FROM komik k
                     WHERE k.judul LIKE ?
                        OR k.pengarang LIKE ?
                        OR EXISTS (
                            SELECT 1
                            FROM komik_genre kg
                            JOIN genre g ON g.id = kg.genre_id
                            WHERE kg.komik_id = k.id AND g.nama LIKE ?
                        )
                     ORDER BY k.created_at DESC
                     LIMIT 50
                 ) limited_search";
    $stmtCount = $conn->prepare($countSql);
    $searchTerm = "%{$search}%";
    $stmtCount->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    if ($row = $resultCount->fetch_assoc()) {
         $totalKomik = (int)$row['total'];
    }
    $stmtCount->close();
} else {
    $countSql = "SELECT COUNT(*) as total FROM komik";
    $resultCount = $conn->query($countSql);
    if ($resultCount) {
        $row = $resultCount->fetch_assoc();
        $totalKomik = (int)$row['total'];
    }
}
$totalPages = ceil($totalKomik / $limit);
if (!empty($searchQuery)) {
    $sql = "SELECT b.*, g.nama AS genre_nama,
                   COALESCE(kr.avg_rating, 0) AS avg_rating,
                   COALESCE(rv.viewer_count, 0) AS viewer_count
            FROM (
                SELECT limited_k.*
                FROM (
                    SELECT k.*
                    FROM komik k
                    WHERE k.judul LIKE ?
                       OR k.pengarang LIKE ?
                       OR EXISTS (
                            SELECT 1
                            FROM komik_genre kgx
                            JOIN genre gx ON gx.id = kgx.genre_id
                            WHERE kgx.komik_id = k.id AND gx.nama LIKE ?
                       )
                    ORDER BY $orderBy
                    LIMIT 50
                ) limited_k
                LIMIT ? OFFSET ?
            ) b
            LEFT JOIN komik_genre kg ON b.id = kg.komik_id
            LEFT JOIN genre g ON g.id = kg.genre_id
            LEFT JOIN (
                SELECT komik_id, ROUND(AVG(rating), 1) AS avg_rating
                FROM komik_rating
                GROUP BY komik_id
            ) kr ON kr.komik_id = b.id
            LEFT JOIN (
                SELECT komik_id, COUNT(DISTINCT user_id) AS viewer_count
                FROM riwayat_baca
                GROUP BY komik_id
            ) rv ON rv.komik_id = b.id
            ORDER BY $orderByOuter, g.nama ASC";
    $stmt = $conn->prepare($sql);
    $search = $searchQuery;
    $searchTerm = "%{$search}%";
    $stmt->bind_param("sssii", $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
} else {
    $sql = "
        SELECT b.*, g.nama AS genre_nama,
               COALESCE(kr.avg_rating, 0) AS avg_rating,
               COALESCE(rv.viewer_count, 0) AS viewer_count
        FROM (
            SELECT k.*
            FROM komik k
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ) b
        LEFT JOIN komik_genre kg ON b.id = kg.komik_id
        LEFT JOIN genre g ON g.id = kg.genre_id
        LEFT JOIN (
            SELECT komik_id, ROUND(AVG(rating), 1) AS avg_rating
            FROM komik_rating
            GROUP BY komik_id
        ) kr ON kr.komik_id = b.id
        LEFT JOIN (
            SELECT komik_id, COUNT(DISTINCT user_id) AS viewer_count
            FROM riwayat_baca
            GROUP BY komik_id
        ) rv ON rv.komik_id = b.id
        ORDER BY $orderByOuter, g.nama ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$komikData = [];
$komikDataMap = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $komikId = (int)$row['id'];
        $genreName = isset($row['genre_nama']) ? (string)$row['genre_nama'] : '';
        if (!isset($komikDataMap[$komikId])) {
            $row['genre_list'] = '-';
            unset($row['genre_nama']);
            $row['__genres'] = [];
            $komikDataMap[$komikId] = $row;
        }
        if ($genreName !== '') {
            $komikDataMap[$komikId]['__genres'][$genreName] = true;
        }
    }
}

foreach ($komikDataMap as $item) {
    if (!empty($item['__genres'])) {
        $genres = array_keys($item['__genres']);
        $item['genre_list'] = implode(', ', $genres);
    }
    unset($item['__genres']);
    $komikData[] = $item;
}

if (!empty($searchQuery)) {
    $rekomendasiKomik = array_slice($komikData, 0, 5);
}

$stmt->close();
$conn->close();
?>
