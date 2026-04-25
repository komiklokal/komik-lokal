<?php
header('Content-Type: application/json; charset=utf-8');
include("config.php");

$term = '';
if (isset($_GET['term'])) {
    $term = trim((string)$_GET['term']);
}

$genres = [];
if ($term !== '') {
    $sql = "SELECT nama FROM genre WHERE LOWER(nama) LIKE ? ORDER BY nama ASC LIMIT 20";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $like = strtolower($term) . '%';
        $stmt->bind_param('s', $like);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $genres[] = $row['nama'];
                }
            }
        }
        $stmt->close();
    }
}

if ($term !== '' && empty($genres)) {
    $defaults = ['Action', 'Adventure'];
    $needle = strtolower($term);
    foreach ($defaults as $name) {
        if ($needle !== '' && strpos(strtolower($name), $needle) === 0) {
            $genres[] = $name;
        }
    }
}

$conn->close();
echo json_encode($genres);
exit;
?>
