<?php
header('Content-Type: application/json; charset=utf-8');
include('config.php');

$term = '';
if (isset($_GET['term'])) {
    $term = trim($_GET['term']);
}

if ($term !== '') {
    $sql = "SELECT nama FROM genre WHERE nama LIKE ? ORDER BY nama LIMIT 20";
    $stmt = $conn->prepare($sql);
    $like = '%' . $term . '%';
    $stmt->bind_param('s', $like);
} else {
    $sql = "SELECT nama FROM genre ORDER BY nama";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
$genres = [];
while ($row = $result->fetch_assoc()) {
    $genres[] = $row['nama'];
}

$stmt->close();
$conn->close();

echo json_encode($genres);
exit();
