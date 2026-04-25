<?php
include("config.php");

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_GET['term'])) {
    $term = trim($_GET['term']);
    
    if (!empty($term) && strlen($term) >= 1) {
        $term = $conn->real_escape_string($term);
        $sql = "SELECT nama FROM genre WHERE nama LIKE '$term%' ORDER BY nama ASC LIMIT 10";
        $result = $conn->query($sql);
        
        $genres = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $genres[] = $row['nama'];
            }
        }
        
        echo json_encode($genres);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}

$conn->close();
?>