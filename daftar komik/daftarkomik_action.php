<?php
session_start();
include("config.php");

// Daftar komik bersifat publik: login tidak wajib.
$username = $_SESSION['username'] ?? null;

$searchQuery = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
}

$genreFilter = [];
if (isset($_GET['genre']) && is_array($_GET['genre'])) {
    $genreFilter = array_filter($_GET['genre'], function($val) {
        return !empty($val);
    });
}

$statusFilter = [];
if (isset($_GET['status']) && is_array($_GET['status'])) {
    $statusFilter = array_filter($_GET['status'], function($val) {
        return !empty($val);
    });
}

$ratingFilter = [];
if (isset($_GET['rating']) && is_array($_GET['rating'])) {
    $ratingFilter = array_values(array_filter(array_map('intval', $_GET['rating']), function($val) {
        return $val >= 1 && $val <= 5;
    }));
}

$sort = $_GET['sort'] ?? 'newest';

$userData = null;

$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];
$types = "";

if (!empty($searchQuery)) {
    $whereConditions[] = "(k.judul LIKE ? OR k.pengarang LIKE ? OR g.nama LIKE ?)";
    $searchTerm = "%" . $searchQuery . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if (!empty($genreFilter)) {
    $placeholders = implode(',', array_fill(0, count($genreFilter), '?'));
    $whereConditions[] = "g.nama IN ($placeholders)";
    foreach ($genreFilter as $genre) {
        $params[] = $genre;
        $types .= "s";
    }
}

if (!empty($statusFilter)) {
    $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
    $whereConditions[] = "k.status IN ($placeholders)";
    foreach ($statusFilter as $status) {
        $params[] = $status;
        $types .= "s";
    }
}

if (!empty($ratingFilter)) {
    $minRating = min($ratingFilter);
    $whereConditions[] = "(SELECT COALESCE(ROUND(AVG(kr.rating), 1), 0) FROM komik_rating kr WHERE kr.komik_id = k.id) >= ?";
    $params[] = $minRating;
    $types .= "i";
}

$whereClause = "";
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

$countSql = "SELECT COUNT(DISTINCT k.id) as total FROM komik k 
             LEFT JOIN komik_genre kg ON k.id = kg.komik_id 
             LEFT JOIN genre g ON kg.genre_id = g.id 
             $whereClause";

$stmtCount = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalKomik = 0;
if ($row = $resultCount->fetch_assoc()) {
    $totalKomik = (int)$row['total'];
}
$stmtCount->close();

$totalPages = ceil($totalKomik / $limit);

$orderBy = "k.created_at DESC"; 
switch ($sort) {
    case 'oldest':
        $orderBy = "k.created_at ASC";
        break;
    case 'title_asc':
        $orderBy = "k.judul ASC";
        break;
    case 'title_desc':
        $orderBy = "k.judul DESC";
        break;
    default: 
        $orderBy = "k.created_at DESC";
        break;
}

$sql = "SELECT DISTINCT k.*,
    COALESCE((
        SELECT GROUP_CONCAT(g2.nama ORDER BY g2.nama SEPARATOR ', ')
        FROM komik_genre kg2
        JOIN genre g2 ON g2.id = kg2.genre_id
        WHERE kg2.komik_id = k.id
    ), '-') AS genre_list,
    COALESCE((SELECT ROUND(AVG(kr.rating), 1) FROM komik_rating kr WHERE kr.komik_id = k.id), 0) AS avg_rating,
    COALESCE((SELECT COUNT(DISTINCT rbv.user_id) FROM riwayat_baca rbv WHERE rbv.komik_id = k.id), 0) AS viewer_count
    FROM komik k 
        LEFT JOIN komik_genre kg ON k.id = kg.komik_id 
        LEFT JOIN genre g ON kg.genre_id = g.id 
        $whereClause 
        ORDER BY $orderBy LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();

$komikData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $komikData[] = $row;
    }
}

$stmt->close();
$conn->close();
?>
