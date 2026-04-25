<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include("../config.php");

$isLoggedIn = isset($_SESSION['username']) && $_SESSION['username'] !== '';
$username = $isLoggedIn ? $_SESSION['username'] : '';
$currentUserId = 0;
$bookmarks = [];

if ($isLoggedIn) {
    $userStmt = $conn->prepare("SELECT id FROM user WHERE user_nama = ? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param("s", $username);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        if ($userRow = $userRes->fetch_assoc()) {
            $currentUserId = (int)$userRow['id'];
        }
        $userStmt->close();
    }

    if ($currentUserId > 0) {
        $bookmarkStmt = $conn->prepare("SELECT k.id, k.judul, k.gambar, k.tipe_gambar, k.status, k.pengarang
            FROM komik_bookmark kb
            INNER JOIN komik k ON k.id = kb.komik_id
            WHERE kb.user_id = ?
            ORDER BY kb.created_at DESC, kb.id DESC");
        if ($bookmarkStmt) {
            $bookmarkStmt->bind_param("i", $currentUserId);
            $bookmarkStmt->execute();
            $bookmarkRes = $bookmarkStmt->get_result();
            while ($row = $bookmarkRes->fetch_assoc()) {
                $bookmarks[] = $row;
            }
            $bookmarkStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bookmark.css?v=<?php echo @filemtime('bookmark.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../dark-mode.css?v=<?php echo @filemtime('../dark-mode.css') ?: '1'; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="../theme.js?v=<?php echo @filemtime('../theme.js') ?: '1'; ?>"></script>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
    <title>Bookmark - Komik Lokal</title>
</head>
<body>
    <div id="loadingOverlay" aria-hidden="true">
        <div class="loading-card" role="status" aria-live="polite">
            <div class="loading-spinner"></div>
            <div class="loading-title">Memuat Bookmark...</div>
            <div class="loading-sub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>
    <div id="bookmarkContent">
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <i class="fas fa-bookmark"></i>
                <h1>Bookmark</h1>
            </div>
        </div>
    </div>

    <main class="bookmark-page">
        <?php if (!$isLoggedIn): ?>
            <div class="empty-state">
                <i class="fas fa-lock"></i>
                <h2>Login Diperlukan</h2>
                <p>Silakan login untuk melihat komik bookmark Anda.</p>
                <a href="../login/login.php" class="action-btn">Login</a>
            </div>
        <?php else: ?>
            <?php if (!empty($bookmarks)): ?>
                <section class="bookmarks-grid">
                    <?php foreach ($bookmarks as $komik): ?>
                        <article class="bookmark-card">
                            <a class="cover-link" href="../komik/komik.php?id=<?= (int)$komik['id']; ?>">
                                <img src="data:<?= htmlspecialchars($komik['tipe_gambar']); ?>;base64,<?= htmlspecialchars($komik['gambar']); ?>" alt="<?= htmlspecialchars($komik['judul']); ?>">
                                <span class="status-badge <?= strtolower($komik['status']); ?>"><?= htmlspecialchars($komik['status']); ?></span>
                            </a>
                            <div class="card-body">
                                <h3><?= htmlspecialchars($komik['judul']); ?></h3>
                                <p><i class="fas fa-user-edit"></i> <?= htmlspecialchars($komik['pengarang'] ?: '-'); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bookmark"></i>
                    <h2>Belum Ada Bookmark</h2>
                    <p>Tambahkan komik dari halaman detail komik ke bookmark.</p>
                    <a href="../daftar komik/daftarkomik.php" class="action-btn">Cari Komik</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <nav class="bottom-nav">
        <a href="../dashboard/dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="../daftar komik/daftarkomik.php" class="nav-item">
            <i class="fas fa-book"></i>
            <span>Komik</span>
        </a>
        <a href="../profile/profile.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profil</span>
        </a>
    </nav>

    <script>
        function revealBookmark() {
            document.documentElement.classList.remove('page-loading');
        }

        window.addEventListener('load', revealBookmark);
        window.addEventListener('pageshow', revealBookmark);
    </script>
    </div>
</body>
</html>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>