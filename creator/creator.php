<?php include("creator_action.php"); ?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="creator.css">
  <link rel="stylesheet" href="../dark-mode.css?v=<?php echo filemtime(__FILE__); ?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <script src="../theme.js?v=<?php echo filemtime(__FILE__); ?>"></script>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
  <title>Creator - Komik Lokal</title>
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
        .page-loading #creatorContent {
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
            <div class="loading-title" id="pageLoadingTitle">Memuat Creator...</div>
            <div class="loading-sub" id="pageLoadingSub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>
    <div id="creatorContent">
  <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
              <i class="fas fa-book-open"></i>
            <h1>Creator</h1>
        </div>
      </div>
  </div>
  <div class="container">
        <a href="../buat komik/buatkomik.php" class="action-card">
      <i class="fas fa-image"></i>
      <span>Buat Komik</span>
    </a>
    <a href="../upload chapter/uploadchapter.php" class="action-card">
      <i class="fas fa-upload"></i>
      <span>Upload Chapter</span>
    </a>
  </div>
  <section class="comics-section">
        <?php
            $statusNow = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'all';
            $statusLabelMap = [
                'all' => 'Semua',
                'ongoing' => 'On Going',
                'hiatus' => 'Hiatus',
                'completed' => 'Completed',
            ];
            if (!isset($statusLabelMap[$statusNow])) {
                $statusNow = 'all';
            }
        ?>
        <h2><?= htmlspecialchars($statusLabelMap[$statusNow]); ?></h2>
        <div class="creator-status-filter">
            <a href="creator.php?status=all" class="creator-filter-btn <?= $statusNow === 'all' ? 'active' : '' ?>">Semua</a>
            <a href="creator.php?status=ongoing" class="creator-filter-btn <?= $statusNow === 'ongoing' ? 'active' : '' ?>">On Going</a>
            <a href="creator.php?status=hiatus" class="creator-filter-btn <?= $statusNow === 'hiatus' ? 'active' : '' ?>">Hiatus</a>
            <a href="creator.php?status=completed" class="creator-filter-btn <?= $statusNow === 'completed' ? 'active' : '' ?>">Completed</a>
        </div>
    <div class="comics-list">
      <?php if (!empty($komikData)): ?>
        <?php foreach ($komikData as $komik): ?>
          <div class="comic">
              <div class="comic-content">
                <div class="comic-image">
                  <img src="data:<?= htmlspecialchars($komik['tipe_gambar']); ?>;base64,<?= htmlspecialchars($komik['gambar']); ?>" 
                       alt="<?= htmlspecialchars($komik['judul']); ?>">
                  <div class="actions">
                                        <a href="../edit komik/editkomik.php?id=<?= $komik['id']; ?>">
                      <img src="../icon/edit.png" alt="Edit" class="icon-pencil">
                    </a>
                                        <a href="#" class="delete-comic-link" data-komik-id="<?= (int)$komik['id']; ?>" onclick="return deleteComic(event, <?= (int)$komik['id']; ?>)" data-chapter-count="<?= (int)($komik['chapter_count'] ?? 0); ?>">
                        <img src="../icon/delete.png" alt="Hapus" class="icon-trash">
                    </a>
                  </div>
                  <?php
        $rawStatus = strtolower(trim($komik['status'] ?? ''));
        $statusClass = preg_replace('/[^a-z0-9_-]+/', '-', $rawStatus);
?>
                  <div class="status status-<?= htmlspecialchars($statusClass); ?>"><?= htmlspecialchars($komik['status']); ?></div>
                </div>
                  <div class="comic-info">
                  <div class="judul-komik"><?= htmlspecialchars($komik['judul']); ?></div>
                </div>
              </div>
          </div>
        <?php
    endforeach; ?>
      <?php
endif; ?>
    </div>
</section>
<style>
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}
.custom-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}
.custom-modal {
    background: white;
    padding: 2.5rem;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 450px;
    transform: translateY(30px) scale(0.95);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.8);
}
.custom-modal-overlay.active .custom-modal {
    transform: translateY(0) scale(1);
    opacity: 1;
}
.modal-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
}
.modal-icon.warning {
    color: #ed8936;
    animation: pulse-warning 2s infinite;
}
.modal-icon.success {
    color: #48bb78;
}
.modal-icon.error {
    color: #e53e3e;
}
.modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 1rem;
}
.modal-message {
    color: #4a5568;
    font-size: 1.1rem;
    margin-bottom: 2rem;
    line-height: 1.6;
}
.modal-message ul {
    text-align: left;
    margin: 1rem auto;
    max-width: 80%;
    padding-left: 2rem;
    color: #e53e3e;
    font-weight: 500;
}
.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}
.modal-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    flex: 1;
}
.modal-btn-cancel {
    background: #edf2f7;
    color: #4a5568;
}
.modal-btn-cancel:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}
.modal-btn-confirm {
    background: linear-gradient(45deg, #e53e3e, #c53030);
    color: white;
    box-shadow: 0 4px 15px rgba(229, 62, 62, 0.3);
}
.modal-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(229, 62, 62, 0.4);
}
.modal-btn-ok {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    max-width: 200px;
}
.modal-btn-ok:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}
@keyframes pulse-warning {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
</style>
<div class="custom-modal-overlay" id="confirmModal">
    <div class="custom-modal">
        <div class="modal-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="modal-title" id="confirmTitle">Hapus Komik?</div>
        <div class="modal-message">
            <span id="confirmDescription">Tindakan ini tidak dapat dibatalkan. Menghapus komik ini juga akan:</span>
            <ul id="confirmEffectsList">
                <li>Menghapus semua chapter</li>
                <li>Menghapus semua gambar</li>
            </ul>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" id="confirmCancelBtn">Batal</button>
            <button class="modal-btn modal-btn-confirm" id="confirmOkBtn">Ya, Hapus</button>
        </div>
    </div>
</div>
<div class="custom-modal-overlay" id="alertModal">
    <div class="custom-modal">
        <div class="modal-icon" id="alertIcon"><i class="fas fa-check-circle"></i></div>
        <div class="modal-title" id="alertTitle">Informasi</div>
        <div class="modal-message" id="alertMessage">Pesan informasi disini.</div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-ok" id="alertOkBtn">Mengerti</button>
        </div>
    </div>
</div>
<script>
const confirmModal = document.getElementById('confirmModal');
const confirmCancelBtn = document.getElementById('confirmCancelBtn');
const confirmOkBtn = document.getElementById('confirmOkBtn');
const confirmTitle = document.getElementById('confirmTitle');
const confirmDescription = document.getElementById('confirmDescription');
const confirmEffectsList = document.getElementById('confirmEffectsList');
const alertModal = document.getElementById('alertModal');
const alertIcon = document.getElementById('alertIcon');
const alertTitle = document.getElementById('alertTitle');
const alertMessage = document.getElementById('alertMessage');
const alertOkBtn = document.getElementById('alertOkBtn');
let confirmCallback = null;
let alertCallback = null;
function showConfirm(callback) {
    confirmCallback = callback;
    confirmModal.classList.add('active');
}
function setDeleteComicConfirmContent(chapterCount) {
    if (!confirmTitle || !confirmDescription || !confirmEffectsList) return;
    confirmTitle.textContent = 'Hapus Komik?';
    confirmDescription.textContent = 'Tindakan ini tidak dapat dibatalkan. Menghapus komik ini juga akan:';
    const count = Number.isFinite(chapterCount) ? chapterCount : parseInt(String(chapterCount || '0'), 10);
    const hasChapters = (count || 0) > 0;
    confirmEffectsList.innerHTML = hasChapters
        ? '<li>Menghapus semua chapter</li><li>Menghapus semua gambar</li>'
        : '<li>Menghapus semua gambar</li>';
}
confirmCancelBtn.addEventListener('click', () => {
    confirmModal.classList.remove('active');
    confirmCallback = null;
});
confirmOkBtn.addEventListener('click', () => {
    confirmModal.classList.remove('active');
    if (confirmCallback) confirmCallback();
});
function showAlert(type, title, message, reload = false) {
    alertTitle.textContent = title;
    alertMessage.innerHTML = message;
    if (type === 'success') {
        alertIcon.className = 'modal-icon success';
        alertIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
    } else if (type === 'error') {
        alertIcon.className = 'modal-icon error';
        alertIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
    } else {
        alertIcon.className = 'modal-icon warning';
        alertIcon.innerHTML = '<i class="fas fa-info-circle"></i>';
    }
    alertCallback = reload ? () => location.reload() : null;
    alertModal.classList.add('active');
}
alertOkBtn.addEventListener('click', () => {
    alertModal.classList.remove('active');
    if (alertCallback) {
        alertCallback();
    }
});
function deleteComic(e, id) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    console.log("ID yang akan dihapus:", id);
    const deleteLink = document.querySelector(`.delete-comic-link[data-komik-id="${id}"]`);

    const fallbackCount = deleteLink ? parseInt(deleteLink.getAttribute('data-chapter-count') || '0', 10) : 0;
    const safeFallbackCount = Number.isFinite(fallbackCount) ? fallbackCount : 0;

    let didProceed = false;
    const proceedToConfirm = (liveCount) => {
        if (didProceed) return;
        didProceed = true;

        const count = Number.isFinite(liveCount) ? liveCount : safeFallbackCount;
        setDeleteComicConfirmContent(count);
        if (deleteLink) {
            deleteLink.setAttribute('data-chapter-count', String(count));
        }
        showConfirm(() => {
        const deleteBtn = deleteLink ? deleteLink.querySelector('img') : null;
        if (deleteBtn) {
            deleteBtn.style.opacity = '0.5';
            deleteBtn.style.animation = 'pulse 1s infinite';
        }
        try {
            const loadingTitle = document.getElementById('pageLoadingTitle');
            const loadingSub = document.getElementById('pageLoadingSub');
            if (loadingTitle) loadingTitle.textContent = 'Menghapus Komik...';
            if (loadingSub) loadingSub.textContent = 'Tunggu sesuai koneksi internet Anda';
        } catch (e) {
        }
        document.documentElement.classList.add('page-loading');
        const showResultAfterLoading = (type, title, message, reload) => {
            document.documentElement.classList.remove('page-loading');
            setTimeout(() => {
                showAlert(type, title, message, reload);
            }, 0);
        };
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "creator.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (deleteBtn) {
                    deleteBtn.style.opacity = '1';
                    deleteBtn.style.animation = '';
                }
                if (xhr.status === 200) {
                    console.log("Respon dari server:", xhr.responseText);
                    if (xhr.responseText.includes("berhasil")) {
                        const comicElement = deleteLink ? deleteLink.closest('.comic') : null;
                        if (comicElement) {
                            comicElement.style.transform = 'scale(0.8)';
                            comicElement.style.opacity = '0.5';
                            setTimeout(() => {
                                showResultAfterLoading('success', 'Berhasil', 'Komik berhasil dihapus!', true);
                            }, 300);
                        } else {
                            showResultAfterLoading('success', 'Berhasil', 'Komik berhasil dihapus!', true);
                        }
                    } else {
                        showResultAfterLoading('error', 'Gagal', 'Terjadi kesalahan saat menghapus komik.', false);
                    }
                } else {
                    showResultAfterLoading('error', 'Kesalahan', 'Terjadi kesalahan server. Silakan coba lagi.', false);
                }
            }
        };
        xhr.onerror = function() {
            showResultAfterLoading('error', 'Koneksi Bermasalah', 'Periksa koneksi internet Anda dan coba lagi.', false);
            if (deleteBtn) {
                deleteBtn.style.opacity = '1';
                deleteBtn.style.animation = '';
            }
        };
        xhr.send("action=delete&id=" + id);
        });
    };

    const xhrCount = new XMLHttpRequest();
    xhrCount.open('POST', 'creator.php', true);
    xhrCount.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhrCount.onreadystatechange = function () {
        if (xhrCount.readyState !== 4) return;
        if (xhrCount.status === 200) {
            try {
                const data = JSON.parse(xhrCount.responseText);
                if (data && data.success) {
                    proceedToConfirm(parseInt(String(data.chapter_count || '0'), 10) || 0);
                    return;
                }
            } catch (err) {
            }
        }
        proceedToConfirm(safeFallbackCount);
    };
    xhrCount.onerror = function () {
        proceedToConfirm(safeFallbackCount);
    };
    xhrCount.send('action=chapter_count&id=' + encodeURIComponent(String(id)));
}
document.addEventListener('DOMContentLoaded', function() {
    const comics = document.querySelectorAll('.comic');
    comics.forEach((comic, index) => {
        comic.style.opacity = '0';
        comic.style.transform = 'translateY(30px)';
        setTimeout(() => {
            comic.style.opacity = '1';
            comic.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
function revealCreator() {
    document.documentElement.classList.remove('page-loading');
}
window.addEventListener('load', revealCreator);
window.addEventListener('pageshow', revealCreator);
</script>
  </div>
</body>
</html>
