<?php include("komik_action.php"); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="komik.css?v=<?php echo @filemtime('komik.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../dark-mode.css?v=<?php echo @filemtime('../dark-mode.css') ?: '1'; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="../theme.js?v=<?php echo @filemtime('../theme.js') ?: '1'; ?>"></script>
    <title><?= htmlspecialchars($komik['judul']); ?> - Komik Lokal</title>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <i class="fas fa-book-open"></i>
                <h1>Komik</h1>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="komik-header">
            <div class="cover-komik">
                <img src="data:<?= htmlspecialchars($komik['tipe_gambar']); ?>;base64,<?= htmlspecialchars($komik['gambar']); ?>" alt="<?= htmlspecialchars($komik['judul']); ?>">
                <div class="cover-stats">
                    <div class="cover-stat-item">
                        <i class="fas fa-star"></i>
                        Rating keseluruhan: <?= number_format((float)$avgRating, 1); ?>/5 (<?= (int)$ratingCount; ?>)
                    </div>
                    <div class="cover-stat-item">
                        <i class="fas fa-eye"></i>
                        Dilihat oleh <?= (int)$viewerCount; ?> orang
                    </div>
                </div>
                <div class="bookmark-section">
                    <?php if ($isLoggedIn): ?>
                        <form method="POST" action="komik.php?id=<?= (int)$komik_id; ?>" class="bookmark-form">
                            <input type="hidden" name="action" value="toggle_bookmark">
                            <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                            <button type="submit" class="bookmark-btn <?= $isBookmarked ? 'active' : ''; ?>">
                                <i class="fas fa-bookmark"></i>
                                <?= $isBookmarked ? 'Tersimpan' : 'Bookmark'; ?> (<?= (int)$bookmarkCount; ?>)
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="../login/login.php" class="bookmark-btn guest">
                            <i class="fas fa-bookmark"></i>
                            Login untuk Bookmark
                        </a>
                    <?php endif; ?>
                </div>
                <button type="button" class="comment-open-btn" id="openCommentPopupBtn">
                    <i class="fas fa-comments"></i>
                    Komentar (<?= (int)$commentCount; ?>)
                </button>
            </div>
            <div class="info-komik">
                <h1><?= htmlspecialchars($komik['judul']); ?></h1>
                <p class="sinopsis"><?= htmlspecialchars($komik['sinopsis']); ?></p>
                <?php if (!empty($interactionMessage) && !($interactionType === 'success' && !empty($showRatingPopup)) && !($interactionType === 'success' && !empty($showBookmarkPopup)) && empty($showOwnerRatingBlockedPopup)): ?>
                    <div class="interaction-message <?= $interactionType === 'success' ? 'success' : 'error'; ?>">
                        <?= htmlspecialchars($interactionMessage); ?>
                    </div>
                <?php endif; ?>
                <table class="table-info">
                    <tr>
                        <td>
                            <label>
                                <i class="fas fa-calendar"></i>
                                Tanggal Rilis
                            </label>
                        </td>
                        <td>
                            <span class="date-display">
                                <?php 
                                if (!empty($chapterDates['tanggal_rilis']) && $chapterDates['tanggal_rilis'] !== '0000-00-00 00:00:00') {
                                    $tanggal = new DateTime($chapterDates['tanggal_rilis']);
                                    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                    echo $tanggal->format('j') . ' ' . $bulan[$tanggal->format('n') - 1] . ' ' . $tanggal->format('Y');
                                } else {
                                    echo 'Tanggal rilis tidak tersedia';
                                }
                                ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <i class="fas fa-circle-info"></i>
                                Status
                            </label>
                        </td>
                        <td>
                            <span class="status-badge <?= strtolower($komik['status']) ?>">
                                <?= htmlspecialchars($komik['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <i class="fas fa-user"></i>
                                Pengarang
                            </label>
                        </td>
                        <td>
                            <span class="author-name"><?= htmlspecialchars($komik['pengarang']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <i class="fas fa-clock"></i>
                                Pembaruan Terakhir
                            </label>
                        </td>
                        <td>
                            <span class="last-update">
                                <?php 
                                if (!empty($chapterDates['pembaruan_terakhir']) && $chapterDates['pembaruan_terakhir'] !== '0000-00-00 00:00:00') {
                                    $tanggal = new DateTime($chapterDates['pembaruan_terakhir']);
                                    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                    echo $tanggal->format('j') . ' ' . $bulan[$tanggal->format('n') - 1] . ' ' . $tanggal->format('Y');
                                } else {
                                    echo 'Pembaruan terakhir tidak tersedia';
                                }
                                ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <i class="fas fa-tags"></i>
                                Genre
                            </label>
                        </td>
                        <td>
                            <div class="genre-display">
                                <?php if (!empty($genre)): ?>
                                    <?php foreach (array_unique(array_map('htmlspecialchars', $genre)) as $g): ?>
                                        <span class="genre-tag"><?= $g ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="no-genre">Tidak ada genre</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <i class="fas fa-star"></i>
                                Rating
                            </label>
                        </td>
                        <td>
                            <div class="rating-section">
                                <div class="rating-summary">
                                    <?php if ($isLoggedIn): ?>
                                        <span class="rating-value"><?= number_format((float)$userRating, 1); ?></span>
                                        <span class="rating-text">/5 <?= $userRating > 0 ? 'rating Anda' : 'belum Anda beri rating'; ?></span>
                                    <?php else: ?>
                                        <span class="rating-value">0.0</span>
                                        <span class="rating-text">/5 belum dirating</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isLoggedIn): ?>
                                    <form method="POST" action="komik.php?id=<?= (int)$komik_id; ?>" class="rating-form">
                                        <input type="hidden" name="action" value="set_rating">
                                        <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                                        <div class="rating-stars-container">
                                            <div class="rating-stars <?= ((int)$userRating > 0) ? 'rated' : 'unrated'; ?>">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <button type="submit" name="rating" value="<?= $i; ?>" class="star-btn <?= $i <= (int)$userRating ? 'selected' : ''; ?>" title="Beri rating <?= $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <a href="../login/login.php" class="rating-login-link">Login untuk memberi rating</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="chapter">
        <div class="chapter-list">
            <h2><i class="fas fa-list-ol"></i> Chapter Terbaru</h2>
            <div class="chapter-container">
                <?php if (!empty($chapters)) :?>
                    <?php foreach ($chapters as $chapter) :?>
                        <div class="chapter-item">
                            <a href="../chapter/chapter.php?id=<?= $chapter['id']; ?>">
                                <h3>
                                    <i class="fas fa-book-open"></i>
                                    Chapter <?= htmlspecialchars($chapter['nomor']); ?>: <?= htmlspecialchars($chapter['judul']); ?>
                                </h3>
                                <span class="chapter-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= !empty($chapter['tanggal_rilis']) ? htmlspecialchars(date('Y-m-d', strtotime($chapter['tanggal_rilis']))) : 'Tanggal rilis tidak tersedia'; ?>
                                </span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else :?>
                    <div class="no-chapters">
                        <i class="fas fa-folder-open"></i>
                        <p>Belum ada chapter tersedia</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="ratingPopupOverlay" class="rating-popup-overlay" aria-hidden="true">
        <div class="rating-popup" role="dialog" aria-modal="true" aria-labelledby="ratingPopupTitle" aria-describedby="ratingPopupText">
            <div class="rating-popup-icon <?= !empty($showOwnerRatingBlockedPopup) ? 'warning' : 'success'; ?>">
                <i class="fas <?= !empty($showOwnerRatingBlockedPopup) ? 'fa-circle-exclamation' : 'fa-star'; ?>"></i>
            </div>
            <h3 id="ratingPopupTitle"><?= !empty($showOwnerRatingBlockedPopup) ? 'Peringatan' : 'Berhasil'; ?></h3>
            <p id="ratingPopupText"><?= !empty($showOwnerRatingBlockedPopup) ? 'anda tidak memboleh rating komik anda sendiri' : 'Anda telah me rating komik ini'; ?></p>
            <button type="button" id="ratingPopupOk" class="rating-popup-ok">OK</button>
        </div>
    </div>

    <div id="bookmarkPopupOverlay" class="rating-popup-overlay" aria-hidden="true">
        <div class="rating-popup" role="dialog" aria-modal="true" aria-labelledby="bookmarkPopupTitle" aria-describedby="bookmarkPopupText">
            <div class="rating-popup-icon success">
                <i class="fas fa-bookmark"></i>
            </div>
            <h3 id="bookmarkPopupTitle">Berhasil</h3>
            <p id="bookmarkPopupText"><?= !empty($interactionMessage) ? htmlspecialchars($interactionMessage) : 'komik ini telah tersimpan di bookmark'; ?></p>
            <button type="button" id="bookmarkPopupOk" class="rating-popup-ok">OK</button>
        </div>
    </div>

    <div id="commentPopupOverlay" class="comment-popup-overlay" aria-hidden="true">
        <div class="comment-popup" role="dialog" aria-modal="true" aria-labelledby="commentPopupTitle">
            <div class="comment-popup-header">
                <h3 id="commentPopupTitle"><i class="fas fa-comments"></i> Komentar</h3>
                <button type="button" class="comment-popup-close" id="closeCommentPopupBtn" aria-label="Tutup">&times;</button>
            </div>

            <?php if (!empty($commentPopupNotice)): ?>
                <div class="comment-popup-notice <?= $commentPopupNoticeType === 'success' ? 'success' : 'error'; ?>">
                    <?= htmlspecialchars($commentPopupNotice); ?>
                </div>
            <?php endif; ?>

            <div class="comment-list">
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php $isMyComment = $isLoggedIn && (int)$currentUserId === (int)$comment['user_id']; ?>
                        <?php $isOwnerComment = (!empty($komik['user_nama']) && strcasecmp((string)$comment['username'], (string)$komik['user_nama']) === 0)
                            || (!empty($komik['pengarang']) && strcasecmp((string)$comment['username'], (string)$komik['pengarang']) === 0); ?>
                        <?php $commentDisplayName = $isOwnerComment ? 'Pemilik' : (string)$comment['username']; ?>
                        <div class="comment-message-wrapper <?= $isMyComment ? 'message-right' : 'message-left'; ?>">
                            <?php if (!$isMyComment): ?>
                                <div class="comment-avatar"><?= strtoupper(substr($commentDisplayName, 0, 1)); ?></div>
                            <?php endif; ?>

                            <div class="comment-message-content">
                                <div class="comment-item-head">
                                    <span class="comment-user"><?= htmlspecialchars($commentDisplayName); ?></span>
                                    <span class="comment-time"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></span>
                                </div>

                                <div class="comment-bubble-container" id="commentBubbleWrap<?= (int)$comment['id']; ?>">
                                    <?php if ($isMyComment): ?>
                                        <button type="button" class="comment-more-btn" data-comment-id="<?= (int)$comment['id']; ?>" aria-label="Aksi komentar" title="Aksi komentar">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    <?php endif; ?>
                                    <div class="comment-bubble <?= $isMyComment ? 'user' : 'other'; ?>">
                                        <p class="comment-text" id="commentText<?= (int)$comment['id']; ?>"><?= nl2br(htmlspecialchars($comment['komentar'])); ?></p>
                                    </div>

                                    <?php if ($isMyComment): ?>
                                        <div class="comment-actions-menu" id="commentActions<?= (int)$comment['id']; ?>">
                                            <button type="button" class="comment-action-btn edit" data-comment-id="<?= (int)$comment['id']; ?>">
                                                <i class="fas fa-pen"></i> Edit
                                            </button>
                                            <form method="POST" action="komik.php?id=<?= (int)$komik_id; ?>" class="comment-delete-form">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                                                <input type="hidden" name="comment_id" value="<?= (int)$comment['id']; ?>">
                                                <button type="submit" class="comment-action-btn delete">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isMyComment): ?>
                                    <form method="POST" action="komik.php?id=<?= (int)$komik_id; ?>" class="comment-edit-form" id="commentEditForm<?= (int)$comment['id']; ?>" style="display:none;">
                                        <input type="hidden" name="action" value="edit_comment">
                                        <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                                        <input type="hidden" name="comment_id" value="<?= (int)$comment['id']; ?>">
                                        <textarea name="comment_text" rows="3" maxlength="800"><?= htmlspecialchars($comment['komentar']); ?></textarea>
                                        <div class="comment-edit-actions">
                                            <button type="submit" class="comment-action-btn save">
                                                <i class="fas fa-check"></i> Simpan
                                            </button>
                                            <button type="button" class="comment-action-btn cancel" data-comment-id="<?= (int)$comment['id']; ?>">
                                                <i class="fas fa-xmark"></i> Batal
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="comment-empty">Belum ada komentar.</div>
                <?php endif; ?>
            </div>

            <div class="comment-form-wrap">
                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="komik.php?id=<?= (int)$komik_id; ?>" class="comment-form">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="komik_id" value="<?= (int)$komik_id; ?>">
                        <textarea name="comment_text" rows="3" maxlength="800" placeholder="Tulis komentar Anda..."></textarea>
                        <button type="submit" class="comment-submit-btn">
                            <i class="fas fa-paper-plane"></i> Kirim Komentar
                        </button>
                    </form>
                <?php else: ?>
                    <a href="../login/login.php" class="comment-login-link">Login untuk menulis komentar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="actionLoadingOverlay" class="action-loading-overlay" aria-hidden="true">
        <div class="action-loading-card" role="status" aria-live="polite">
            <div class="action-loading-spinner"></div>
            <p>Memproses...</p>
        </div>
    </div>

    <script>
        (function initActionLoading() {
            const actionOverlay = document.getElementById('actionLoadingOverlay');
            if (!actionOverlay) return;

            window.showKomikActionLoading = function () {
                actionOverlay.classList.add('show');
                actionOverlay.setAttribute('aria-hidden', 'false');
            };

            window.hideKomikActionLoading = function () {
                actionOverlay.classList.remove('show');
                actionOverlay.setAttribute('aria-hidden', 'true');
            };

            document.querySelectorAll('.rating-form, .bookmark-form, .comment-form, .comment-edit-form, .comment-delete-form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    window.showKomikActionLoading();
                });
            });
        })();

        (function initRatingPopup() {
            const overlay = document.getElementById('ratingPopupOverlay');
            const okBtn = document.getElementById('ratingPopupOk');
            if (!overlay || !okBtn) return;

            function closePopup() {
                overlay.classList.remove('show');
                overlay.setAttribute('aria-hidden', 'true');
            }

            okBtn.addEventListener('click', closePopup);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closePopup();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePopup();
            });

            <?php if (!empty($showRatingPopup) || !empty($showOwnerRatingBlockedPopup)): ?>
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
            <?php endif; ?>
        })();

        (function initBookmarkPopup() {
            const overlay = document.getElementById('bookmarkPopupOverlay');
            const okBtn = document.getElementById('bookmarkPopupOk');
            if (!overlay || !okBtn) return;

            function closePopup() {
                overlay.classList.remove('show');
                overlay.setAttribute('aria-hidden', 'true');
            }

            okBtn.addEventListener('click', closePopup);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closePopup();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePopup();
            });

            <?php if (!empty($showBookmarkPopup)): ?>
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
            <?php endif; ?>
        })();

        (function initCommentPopup() {
            const overlay = document.getElementById('commentPopupOverlay');
            const openBtn = document.getElementById('openCommentPopupBtn');
            const closeBtn = document.getElementById('closeCommentPopupBtn');
            if (!overlay || !openBtn || !closeBtn) return;

            function openPopup() {
                overlay.classList.add('show');
                overlay.setAttribute('aria-hidden', 'false');
            }

            function closePopup() {
                overlay.classList.remove('show');
                overlay.setAttribute('aria-hidden', 'true');
            }

            openBtn.addEventListener('click', function () {
                if (typeof window.showKomikActionLoading === 'function') {
                    window.showKomikActionLoading();
                }
                setTimeout(function () {
                    openPopup();
                    if (typeof window.hideKomikActionLoading === 'function') {
                        window.hideKomikActionLoading();
                    }
                }, 260);
            });
            closeBtn.addEventListener('click', closePopup);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closePopup();
            });

            <?php if (!empty($showCommentPopup)): ?>
            openPopup();
            <?php endif; ?>

            overlay.addEventListener('click', function (e) {
                const moreBtn = e.target.closest('.comment-more-btn');
                if (moreBtn) {
                    const id = moreBtn.getAttribute('data-comment-id');
                    const menu = document.getElementById('commentActions' + id);
                    const currentlyOpen = menu && menu.classList.contains('show');

                    overlay.querySelectorAll('.comment-actions-menu.show').forEach(function (el) {
                        el.classList.remove('show');
                    });

                    if (menu && !currentlyOpen) {
                        menu.classList.add('show');
                    }
                    return;
                }

                if (!e.target.closest('.comment-actions-menu')) {
                    overlay.querySelectorAll('.comment-actions-menu.show').forEach(function (el) {
                        el.classList.remove('show');
                    });
                }

                const editBtn = e.target.closest('.comment-action-btn.edit');
                if (editBtn) {
                    const id = editBtn.getAttribute('data-comment-id');
                    const form = document.getElementById('commentEditForm' + id);
                    const actions = document.getElementById('commentActions' + id);
                    const bubble = document.getElementById('commentBubbleWrap' + id);
                    const text = document.getElementById('commentText' + id);
                    if (form) form.style.display = '';
                    if (actions) actions.classList.remove('show');
                    if (bubble) bubble.style.display = 'none';
                    if (text) text.style.display = 'none';
                    return;
                }

                const cancelBtn = e.target.closest('.comment-action-btn.cancel');
                if (cancelBtn) {
                    const id = cancelBtn.getAttribute('data-comment-id');
                    const form = document.getElementById('commentEditForm' + id);
                    const actions = document.getElementById('commentActions' + id);
                    const bubble = document.getElementById('commentBubbleWrap' + id);
                    const text = document.getElementById('commentText' + id);
                    if (form) form.style.display = 'none';
                    if (actions) actions.style.display = '';
                    if (bubble) bubble.style.display = '';
                    if (text) text.style.display = '';
                }
            });
        })();
    </script>

</body>
</html>
