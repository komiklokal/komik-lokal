<?php
// [KEAMANAN] Include dipindah ke atas agar session_start() berjalan sebelum HTML output
include("dashboard_action.php");
// [KEAMANAN] HTTP Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
// [KEAMANAN] Content Security Policy — batasi sumber resource yang diizinkan
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
    "font-src https://cdnjs.cloudflare.com; " .
    "img-src 'self' data:; " .
    "connect-src 'self';"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    
    <link rel="stylesheet" href="dashboard.css?v=<?php echo @filemtime('dashboard.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../dark-mode.css?v=<?php echo @filemtime('../dark-mode.css') ?: '1'; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="../theme.js?v=<?php echo @filemtime('../theme.js') ?: '1'; ?>"></script>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
    <title>Komik Lokal - Dashboard</title>
    <style>
        #loadingOverlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .page-loading #loadingOverlay {
            opacity: 1;
            visibility: visible;
        }
        .page-loading #dashboardContent {
            visibility: hidden;
        }
        .loading-card {
            width: 90%;
            max-width: 420px;
            background: var(--bg-secondary, #ffffff);
            color: var(--text-primary, #2d3748);
            border-radius: 18px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }
        .loading-spinner {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            border: 6px solid rgba(148, 163, 184, 0.35);
            border-top-color: var(--accent-color, #667eea);
            margin: 0 auto 1rem;
            animation: spin 0.9s linear infinite;
        }
        .loading-title {
            font-weight: 800;
            font-size: 1.25rem;
            margin-bottom: 0.35rem;
        }
        .loading-sub {
            color: var(--text-secondary, #4a5568);
            font-weight: 600;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div id="loadingOverlay" aria-hidden="true">
        <div class="loading-card" role="status" aria-live="polite">
            <div class="loading-spinner"></div>
            <div class="loading-title">Memuat Dashboard...</div>
            <div class="loading-sub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>
    <div id="dashboardContent">
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <i class="fas fa-book-open"></i>
                <h1>Komik Lokal</h1>
            </div>
            <form method="GET" action="dashboard.php" class="search-form">
                <div class="search-container">
                    <input type="text" 
                           class="search-input" 
                           name="search" 
                           placeholder="Cari komik, pengarang, atau genre..." 
                           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <button class="search-button" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($searchQuery)): ?>
    <section class="latest-section">
        <div class="section-header">
            <h2><i class="fas fa-star"></i> Terbaru</h2>
        </div>
        <div class="horizontal-scroll">
            <div class="latest-container">
                <?php if (!empty($latestKomik)): ?>
                    <?php foreach ($latestKomik as $komik): ?>
                        <div class="comic-card">
                            <div class="comic-cover">
                                <a href="../komik/komik.php?id=<?= $komik['id']; ?>">
                                     <img src="cover.php?id=<?= (int)$komik['id']; ?>" 
                                         alt="<?= htmlspecialchars($komik['judul']); ?>">
                                </a>
                                <span class="status-badge <?php $__status = strtolower($komik['status'] ?? ''); echo in_array($__status, ['ongoing','completed','hiatus']) ? htmlspecialchars($__status) : 'unknown'; ?>">
                                    <?= htmlspecialchars($komik['status']); ?>
                                </span>
                                <div class="comic-meta">
                                    <p><i class="fas fa-user-edit"></i> <?= htmlspecialchars($komik['pengarang'] ?? '-'); ?></p>
                                    <p><i class="fas fa-tags"></i> <?= htmlspecialchars($komik['genre_list'] ?? '-'); ?></p>
                                    <p><i class="fas fa-star"></i> Rating: <?= number_format((float)($komik['avg_rating'] ?? 0), 1); ?>/5</p>
                                    <p><i class="fas fa-eye"></i> Dilihat: <?= (int)($komik['viewer_count'] ?? 0); ?> orang</p>
                                </div>
                            </div>
                            <div class="comic-info">
                                <h3><?= htmlspecialchars(strlen($komik['judul']) > 20 ? substr($komik['judul'], 0, 20) . '...' : $komik['judul']); ?></h3>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-content">
                        <i class="fas fa-book-open"></i>
                        <p>Tidak ada komik terbaru</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    <section class="comics-section">
        <div class="section-header">
            <h2><i class="fas fa-thumbs-up"></i> Rekomendasi</h2>
        </div>
        <div class="comics-grid">
            <?php if (!empty($rekomendasiKomik)): ?>
                <?php foreach ($rekomendasiKomik as $komik): ?>
                    <div class="comic-card">
                        <div class="comic-cover">
                            <a href="../komik/komik.php?id=<?= $komik['id']; ?>">
                                  <img src="cover.php?id=<?= (int)$komik['id']; ?>" 
                                     alt="<?= htmlspecialchars($komik['judul']); ?>">
                            </a>
                            <span class="status-badge <?php $__status = strtolower($komik['status'] ?? ''); echo in_array($__status, ['ongoing','completed','hiatus']) ? htmlspecialchars($__status) : 'unknown'; ?>">
                                <?= htmlspecialchars($komik['status']); ?>
                            </span>
                            <div class="comic-meta">
                                <p><i class="fas fa-user-edit"></i> <?= htmlspecialchars($komik['pengarang'] ?? '-'); ?></p>
                                <p><i class="fas fa-tags"></i> <?= htmlspecialchars($komik['genre_list'] ?? '-'); ?></p>
                                <p><i class="fas fa-star"></i> Rating: <?= number_format((float)($komik['avg_rating'] ?? 0), 1); ?>/5</p>
                                <p><i class="fas fa-eye"></i> Dilihat: <?= (int)($komik['viewer_count'] ?? 0); ?> orang</p>
                            </div>
                        </div>
                        <div class="comic-info">
                            <h3><?= htmlspecialchars($komik['judul']); ?></h3>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content">
                    <i class="fas fa-lightbulb"></i>
                    <p>Belum ada rekomendasi komik</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php if (!empty($userData)): ?>
    <section class="comics-section">
        <div class="section-header">
            <h2><i class="fas fa-history"></i> Riwayat Baca</h2>
            <a href="../riwayat baca/riwayat.php" class="view-all">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="comics-grid">
            <?php if (!empty($riwayatBacaKomik)): ?>
                <?php foreach ($riwayatBacaKomik as $komik): ?>
                    <div class="comic-card">
                        <div class="comic-cover">
                            <a href="../komik/komik.php?id=<?= $komik['id']; ?>">
                                  <img src="cover.php?id=<?= (int)$komik['id']; ?>"
                                     alt="<?= htmlspecialchars($komik['judul']); ?>">
                            </a>
                            <span class="status-badge <?php $__status = strtolower($komik['status'] ?? ''); echo in_array($__status, ['ongoing','completed','hiatus']) ? htmlspecialchars($__status) : 'unknown'; ?>">
                                <?= htmlspecialchars($komik['status']); ?>
                            </span>
                            <div class="comic-meta">
                                <p><i class="fas fa-user-edit"></i> <?= htmlspecialchars($komik['pengarang'] ?? '-'); ?></p>
                                <p><i class="fas fa-tags"></i> <?= htmlspecialchars($komik['genre_list'] ?? '-'); ?></p>
                                <p><i class="fas fa-star"></i> Rating: <?= number_format((float)($komik['avg_rating'] ?? 0), 1); ?>/5</p>
                                <p><i class="fas fa-eye"></i> Dilihat: <?= (int)($komik['viewer_count'] ?? 0); ?> orang</p>
                            </div>
                        </div>
                        <div class="comic-info">
                            <h3><?= htmlspecialchars($komik['judul']); ?></h3>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content">
                    <i class="fas fa-book-open"></i>
                    <p>Belum ada riwayat baca</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
    <section class="comics-section">
        <div class="section-header">
            <h2><i class="fas fa-books"></i> Komik</h2>
            <a href="../daftar komik/daftarkomik.php" class="view-all">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="comics-grid">
            <?php if (!empty($komikData)): ?>
                <?php foreach ($komikData as $komik): ?>
                    <div class="comic-card">
                        <div class="comic-cover">
                            <a href="../komik/komik.php?id=<?= $komik['id']; ?>">
                                  <img src="cover.php?id=<?= (int)$komik['id']; ?>" 
                                     alt="<?= htmlspecialchars($komik['judul']); ?>">
                            </a>
                            <span class="status-badge <?php $__status = strtolower($komik['status'] ?? ''); echo in_array($__status, ['ongoing','completed','hiatus']) ? htmlspecialchars($__status) : 'unknown'; ?>">
                                <?= htmlspecialchars($komik['status']); ?>
                            </span>
                            <div class="comic-meta">
                                <p><i class="fas fa-user-edit"></i> <?= htmlspecialchars($komik['pengarang'] ?? '-'); ?></p>
                                <p><i class="fas fa-tags"></i> <?= htmlspecialchars($komik['genre_list'] ?? '-'); ?></p>
                                <p><i class="fas fa-star"></i> Rating: <?= number_format((float)($komik['avg_rating'] ?? 0), 1); ?>/5</p>
                                <p><i class="fas fa-eye"></i> Dilihat: <?= (int)($komik['viewer_count'] ?? 0); ?> orang</p>
                            </div>
                        </div>
                        <div class="comic-info">
                            <h3><?= htmlspecialchars($komik['judul']); ?></h3>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content">
                    <i class="fas fa-search"></i>
                    <p>Tidak ada hasil pencarian</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <ul class="pagination-list">
                    <?php if ($page > 1): ?>
                        <li>
                            <a href="dashboard.php?page=<?= $page - 1 ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="pagination-link">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li>
                            <a href="dashboard.php?page=<?= $i ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
                               class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li>
                            <a href="dashboard.php?page=<?= $page + 1 ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="pagination-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </section>
    <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="../daftar%20komik/daftarkomik.php" class="nav-item">
            <i class="fas fa-book"></i>
            <span>Komik</span>
        </a>
        <a href="../profile/profile.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profil</span>
        </a>
    </nav>
    </div>
    <script>
        function revealDashboard() {
            document.documentElement.classList.remove('page-loading');
        }
        window.addEventListener('load', revealDashboard);
        window.addEventListener('pageshow', revealDashboard);
        (function initSearchLoading() {
            const searchForm = document.querySelector('form.search-form');
            if (!searchForm) return;
            searchForm.addEventListener('submit', () => {
                document.documentElement.classList.add('page-loading');
            });
        })();
    </script>
</body>
</html>
