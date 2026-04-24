<?php include("daftarkomik_action.php"); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="daftarkomik.css?v=<?php echo @filemtime('daftarkomik.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../dark-mode.css?v=<?php echo @filemtime('../dark-mode.css') ?: '1'; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="../theme.js?v=<?php echo @filemtime('../theme.js') ?: '1'; ?>"></script>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
    <title>Daftar Komik - Komik Lokal</title>

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

        .page-loading #daftarkomikContent {
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
            <div class="loading-title">Memuat Daftar Komik...</div>
            <div class="loading-sub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>

    <div id="daftarkomikContent">
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <i class="fas fa-book-open"></i>
                <h1>Daftar Komik</h1>
            </div>
            <form method="GET" action="daftarkomik.php" class="search-form">
                <?php
                include('config.php');
                $genres = [];
                $gStmt = $conn->prepare("SELECT nama FROM genre ORDER BY nama");
                if ($gStmt->execute()) {
                    $gRes = $gStmt->get_result();
                    while ($gRow = $gRes->fetch_assoc()) {
                        $genres[] = $gRow['nama'];
                    }
                }
                $gStmt->close();
                
                $statuses = ['Ongoing', 'Hiatus', 'Completed'];

                $isAllGenreChecked = true;
                if (isset($_GET['genre']) && is_array($_GET['genre'])) {
                    foreach ((array)$_GET['genre'] as $gv) {
                        if (trim((string)$gv) !== '') {
                            $isAllGenreChecked = false;
                            break;
                        }
                    }
                }

                $isAllStatusChecked = true;
                if (isset($_GET['status']) && is_array($_GET['status'])) {
                    foreach ((array)$_GET['status'] as $sv) {
                        if (trim((string)$sv) !== '') {
                            $isAllStatusChecked = false;
                            break;
                        }
                    }
                }

                $ratingOptions = [4, 3, 2, 1];
                $isAllRatingChecked = true;
                if (isset($_GET['rating']) && is_array($_GET['rating'])) {
                    foreach ((array)$_GET['rating'] as $rv) {
                        if (trim((string)$rv) !== '') {
                            $isAllRatingChecked = false;
                            break;
                        }
                    }
                }
                ?>
                <div class="filter-container">
                    <button type="button" class="filter-btn" id="filterBtn">
                        <i class="fas fa-filter"></i>
                        <span>Filter</span>
                    </button>
                    
                    <div class="filter-dropdown" id="filterDropdown">
                        <div class="filter-sections-wrapper">
                            <div class="filter-section">
                                <h4 class="filter-header" data-toggle="sort">
                                    <span><i class="fas fa-sort"></i> Urutkan</span>
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </h4>
                                <div class="filter-options" id="sortOptions" style="display: none;">
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="newest" <?= (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'checked' : ''; ?>>
                                        <span>Terbaru</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="oldest" <?= (isset($_GET['sort']) && $_GET['sort'] == 'oldest') ? 'checked' : ''; ?>>
                                        <span>Terlama</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="title_asc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'title_asc') ? 'checked' : ''; ?>>
                                        <span>Judul A-Z</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="title_desc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'title_desc') ? 'checked' : ''; ?>>
                                        <span>Judul Z-A</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="filter-section">
                                <h4 class="filter-header" data-toggle="genre">
                                    <span><i class="fas fa-tags"></i> Genre</span>
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </h4>
                                <div class="filter-options" id="genreOptions" style="display: none;">
                                    <label class="filter-option">
                                        <input type="checkbox" name="genre[]" value="" <?= $isAllGenreChecked ? 'checked' : '' ?>>
                                        <span>Semua Genre</span>
                                    </label>
                                    <?php foreach ($genres as $g): ?>
                                        <label class="filter-option">
                                            <input type="checkbox" name="genre[]" value="<?= htmlspecialchars($g) ?>" 
                                                   <?= (isset($_GET['genre']) && in_array($g, (array)$_GET['genre'])) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($g) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="filter-section">
                                <h4 class="filter-header" data-toggle="status">
                                    <span><i class="fas fa-circle-info"></i> Status</span>
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </h4>
                                <div class="filter-options" id="statusOptions" style="display: none;">
                                    <label class="filter-option">
                                        <input type="checkbox" name="status[]" value="" <?= $isAllStatusChecked ? 'checked' : '' ?>>
                                        <span>Semua Status</span>
                                    </label>
                                    <?php foreach ($statuses as $s): ?>
                                        <label class="filter-option">
                                            <input type="checkbox" name="status[]" value="<?= htmlspecialchars($s) ?>" 
                                                   <?= (isset($_GET['status']) && in_array($s, (array)$_GET['status'])) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($s) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="filter-section">
                                <h4 class="filter-header" data-toggle="rating">
                                    <span><i class="fas fa-star"></i> Rating</span>
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </h4>
                                <div class="filter-options" id="ratingOptions" style="display: none;">
                                    <label class="filter-option">
                                        <input type="checkbox" name="rating[]" value="" <?= $isAllRatingChecked ? 'checked' : '' ?>>
                                        <span>Semua Rating</span>
                                    </label>
                                    <?php foreach ($ratingOptions as $r): ?>
                                        <label class="filter-option">
                                            <input type="checkbox" name="rating[]" value="<?= $r ?>" 
                                                   <?= (isset($_GET['rating']) && in_array((string)$r, array_map('strval', (array)$_GET['rating']), true)) ? 'checked' : '' ?>>
                                            <span><?= $r ?> ke atas</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="button" class="btn-reset" id="resetBtn">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="submit" class="btn-apply">
                                <i class="fas fa-check"></i> Terapkan
                            </button>
                        </div>
                    </div>
                </div>
                
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
  
    <section class="comics-section">
        <div class="section-header">
            <h2><i class="fas fa-book"></i> Daftar Komik</h2>
        </div>
        <div class="comics-grid">
            <?php if (!empty($komikData)): ?>
                <?php foreach ($komikData as $komik): ?>
                    <div class="comic-card">
                        <div class="comic-cover">
                            <a href="../komik/komik.php?id=<?= $komik['id']; ?>">
                                <img src="data:<?= htmlspecialchars($komik['tipe_gambar']); ?>;base64,<?= htmlspecialchars($komik['gambar']); ?>" 
                                     alt="<?= htmlspecialchars($komik['judul']); ?>">
                            </a>
                            <span class="status-badge <?= strtolower($komik['status']) ?>">
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
            <div class="pagination-container">
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <div class="pagination-item">
                            <a href="daftarkomik.php?page=<?= $page - 1 ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="pagination-link">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <div class="pagination-item <?= $i == $page ? 'active' : '' ?>">
                            <a href="daftarkomik.php?page=<?= $i ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
                               class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        </div>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <div class="pagination-item">
                            <a href="daftarkomik.php?page=<?= $page + 1 ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="pagination-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </section>

    <nav class="bottom-nav">
        <a href="../dashboard/dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="daftarkomik.php" class="nav-item">
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
        function revealDaftarKomik() {
            document.documentElement.classList.remove('page-loading');
        }

        window.addEventListener('load', revealDaftarKomik);
        window.addEventListener('pageshow', revealDaftarKomik);

        (function initDaftarKomikLoadingTriggers() {
            const searchForm = document.querySelector('form.search-form');
            if (searchForm) {
                searchForm.addEventListener('submit', () => {
                    document.documentElement.classList.add('page-loading');
                });
            }

            const applyBtn = document.querySelector('.btn-apply');
            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    document.documentElement.classList.add('page-loading');
                });
            }

            const resetBtn = document.getElementById('resetBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    document.documentElement.classList.add('page-loading');
                });
            }

            document.addEventListener('click', (e) => {
                const link = e.target && e.target.closest ? e.target.closest('a.pagination-link') : null;
                if (!link) return;
                document.documentElement.classList.add('page-loading');
            });
        })();
    </script>
    <script src="daftarkomik_genre.js?v=1"></script>
</body>
</html>
