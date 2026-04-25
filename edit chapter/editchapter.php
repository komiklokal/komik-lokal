<?php
include("config.php");
if (!isset($_SESSION['username'])) {
  header("Location: ../login/login.php");
    exit();
}
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID chapter tidak diberikan'); window.location.href='../dashboard.php';</script>";
    exit();
}
$chapter_id = $_GET['id'];
$username = $_SESSION['username'];
$stmt = $conn->prepare("
    SELECT c.id, c.judul, c.komik_id, k.judul as komik_judul, k.user_nama, k.pengarang
    FROM chapter c
    JOIN komik k ON c.komik_id = k.id
    WHERE c.id = ? AND (k.user_nama = ? OR k.pengarang = ?)
");
$stmt->bind_param("iss", $chapter_id, $username, $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<script>alert('Chapter tidak ditemukan atau bukan milik Anda'); window.location.href='../dashboard.php';</script>";
    exit();
}
$chapter = $result->fetch_assoc();
$stmt->close();
$stmt = $conn->prepare("SELECT id, tipe_gambar, gambar, urutan FROM chapter_images WHERE chapter_id = ? ORDER BY urutan ASC");
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$result = $stmt->get_result();
$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="theme-color" content="#667eea">
  <title>Edit Chapter - <?= htmlspecialchars($chapter['judul']); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="editchapter.css?v=<?php echo filemtime(__FILE__); ?>">
  <link rel="stylesheet" href="../dark-mode.css?v=<?php echo filemtime(__FILE__); ?>">
  <script src="../theme.js?v=<?php echo filemtime(__FILE__); ?>" defer></script>
  <script>
    document.documentElement.classList.add('page-loading');
  </script>
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
    .page-loading #editChapterContent {
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
    .preview-scroll-container {
      position: relative;
    }
    #previewChapterImagesContainer.is-hidden {
      visibility: hidden;
    }
    .preview-loading-overlay {
      position: absolute;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.25);
      backdrop-filter: blur(3px);
      z-index: 5;
      border-radius: 14px;
    }
    .preview-loading-overlay.active {
      display: flex;
    }
    .preview-loading-card {
      background: var(--bg-secondary, #ffffff);
      color: var(--text-primary, #2d3748);
      border-radius: 16px;
      padding: 1.25rem 1.5rem;
      width: min(92%, 360px);
      box-shadow: 0 18px 50px rgba(0, 0, 0, 0.22);
      text-align: center;
    }
    .preview-loading-spinner {
      width: 44px;
      height: 44px;
      border-radius: 999px;
      border: 5px solid rgba(148, 163, 184, 0.35);
      border-top-color: var(--accent-color, #667eea);
      margin: 0 auto 0.75rem;
      animation: spin 0.9s linear infinite;
    }
    .preview-loading-title {
      font-weight: 800;
      margin-bottom: 0.15rem;
    }
    .preview-loading-sub {
      color: var(--text-secondary, #4a5568);
      font-weight: 600;
      font-size: 0.95rem;
    }

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
      z-index: 10000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }
    .custom-modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .custom-modal {
      background: var(--bg-secondary, #ffffff);
      color: var(--text-primary, #2d3748);
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
      color: var(--text-primary, #2d3748);
      margin-bottom: 1rem;
    }
    .modal-message {
      color: var(--text-secondary, #4a5568);
      font-size: 1.1rem;
      margin-bottom: 2rem;
      line-height: 1.6;
      white-space: pre-line;
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
</head>
<body>
  <div id="loadingOverlay" aria-hidden="true">
    <div class="loading-card" role="status" aria-live="polite">
      <div class="loading-spinner"></div>
      <div class="loading-title" id="loadingTitle">Memuat Edit Chapter...</div>
      <div class="loading-sub" id="loadingSub">Tunggu sesuai koneksi internet Anda</div>
    </div>
  </div>
  <div id="editChapterContent">
  <div class="navbar">
    <div class="navbar-container">
      <h1><i class="fas fa-book-open"></i> Komik Lokal</h1>
      <a href="../edit%20komik/editkomik.php?id=<?= $chapter['komik_id']; ?>" class="back-btn">
        <i class="fas fa-arrow-left"></i> Kembali
      </a>
    </div>
  </div>
  <form id="editChapterForm" action="editchapter_action.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="chapter_id" value="<?= htmlspecialchars($chapter_id); ?>">
    <input type="hidden" name="komik_id" value="<?= htmlspecialchars($chapter['komik_id']); ?>">
    <div class="container">
      <div class="chapter-header">
        <h2><i class="fas fa-edit"></i> Edit Chapter</h2>
        <div class="komik-info">
          <i class="fas fa-book"></i>
          <span><?= htmlspecialchars($chapter['komik_judul']); ?></span>
        </div>
      </div>
      <div class="form-group">
        <label for="chapterJudul">
          <i class="fas fa-heading"></i>
          Judul Chapter
        </label>
        <input type="text" 
               id="chapterJudul" 
               name="judul" 
               value="<?= htmlspecialchars($chapter['judul']); ?>" 
               placeholder="Masukkan Judul Chapter" 
               required>
        <div class="error" id="judulError"></div>
      </div>
      <div class="current-images">
        <h3><i class="fas fa-images"></i> Halaman Chapter Saat Ini (<?= count($images); ?> halaman)</h3>
        <div class="images-grid" id="currentImagesGrid">
          <?php foreach ($images as $img): ?>
            <div class="image-item" data-image-id="<?= $img['id']; ?>" data-order="<?= $img['urutan']; ?>">
              <img src="data:<?= htmlspecialchars($img['tipe_gambar']); ?>;base64,<?= base64_encode($img['gambar']); ?>" 
                   alt="Halaman <?= $img['urutan']; ?>">
              <div class="image-controls">
                <span class="page-number">Hal. <?= $img['urutan']; ?></span>
                <button type="button" class="delete-image-btn" onclick="deleteImage(<?= $img['id']; ?>, <?= $img['urutan']; ?>)">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="chapterImages">
          <i class="fas fa-plus-circle"></i>
          Tambah Halaman Baru (opsional)
        </label>
        <div class="file-input-wrapper">
          <input type="file" id="chapterImages" name="chapterImages[]" accept="image/*" multiple>
          <div class="file-input-display" aria-hidden="true">
            <div class="file-input-button"><i class="fas fa-cloud-upload-alt"></i> Pilih Gambar</div>
            <div class="file-input-text" id="fileInputText">Drag & drop file di sini atau klik untuk memilih</div>
          </div>
        </div>
      </div>

      <div class="preview" id="previewChapterImages" style="display:none;">
        <h3><i class="fas fa-eye"></i> Preview Halaman Baru</h3>
        <div class="preview-scroll-container">
          <div class="preview-loading-overlay" id="previewChapterImagesLoading" aria-hidden="true">
            <div class="preview-loading-card" role="status" aria-live="polite">
              <div class="preview-loading-spinner"></div>
              <div class="preview-loading-title">Memuat preview...</div>
              <div class="preview-loading-sub">Tunggu sebentar</div>
            </div>
          </div>
          <div id="previewChapterImagesContainer"></div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-submit">
          <i class="fas fa-save"></i> Simpan Perubahan
        </button>
        <a class="btn-cancel" href="../edit%20komik/editkomik.php?id=<?= $chapter['komik_id']; ?>">
          <i class="fas fa-times"></i> Batal
        </a>
      </div>
    </div>
  </form>

  <div class="custom-modal-overlay" id="confirmModal" aria-hidden="true">
    <div class="custom-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
      <div class="modal-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
      <div class="modal-title" id="confirmTitle">Konfirmasi</div>
      <div class="modal-message" id="confirmMessage">Apakah Anda yakin?</div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-cancel" id="confirmCancelBtn" type="button">Batal</button>
        <button class="modal-btn modal-btn-confirm" id="confirmOkBtn" type="button">Ya</button>
      </div>
    </div>
  </div>
  <div class="custom-modal-overlay" id="alertModal" aria-hidden="true">
    <div class="custom-modal" role="dialog" aria-modal="true" aria-labelledby="alertTitle">
      <div class="modal-icon" id="alertIcon"><i class="fas fa-check-circle"></i></div>
      <div class="modal-title" id="alertTitle">Informasi</div>
      <div class="modal-message" id="alertMessage">Pesan informasi disini.</div>
      <div class="modal-actions">
        <button class="modal-btn modal-btn-ok" id="alertOkBtn" type="button">Mengerti</button>
      </div>
    </div>
  </div>

  <script>
    const confirmModal = document.getElementById('confirmModal');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const confirmOkBtn = document.getElementById('confirmOkBtn');
    const confirmTitle = document.getElementById('confirmTitle');
    const confirmMessage = document.getElementById('confirmMessage');

    const alertModal = document.getElementById('alertModal');
    const alertIcon = document.getElementById('alertIcon');
    const alertTitle = document.getElementById('alertTitle');
    const alertMessage = document.getElementById('alertMessage');
    const alertOkBtn = document.getElementById('alertOkBtn');

    let confirmCallback = null;
    let alertCallback = null;

    function showConfirm(optionsOrCallback) {
      let options = {};
      if (typeof optionsOrCallback === 'function') {
        options.onConfirm = optionsOrCallback;
      } else {
        options = optionsOrCallback || {};
      }

      confirmTitle.textContent = options.title || 'Konfirmasi';
      confirmMessage.textContent = options.message || 'Apakah Anda yakin?';
      confirmCancelBtn.textContent = options.cancelText || 'Batal';
      confirmOkBtn.textContent = options.okText || 'Ya';
      confirmCallback = options.onConfirm || null;

      confirmModal.classList.add('active');
      confirmModal.setAttribute('aria-hidden', 'false');
    }

    confirmCancelBtn.addEventListener('click', () => {
      confirmModal.classList.remove('active');
      confirmModal.setAttribute('aria-hidden', 'true');
      confirmCallback = null;
    });

    confirmOkBtn.addEventListener('click', () => {
      confirmModal.classList.remove('active');
      confirmModal.setAttribute('aria-hidden', 'true');
      if (confirmCallback) confirmCallback();
    });

    function showAlert(type, title, message, reload = false) {
      alertTitle.textContent = title;
      alertMessage.textContent = message;

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
      alertModal.setAttribute('aria-hidden', 'false');
    }

    function showPageLoading(title, subTitle) {
      try {
        const loadingTitle = document.getElementById('loadingTitle');
        const loadingSub = document.getElementById('loadingSub');
        if (loadingTitle && title) loadingTitle.textContent = title;
        if (loadingSub && subTitle) loadingSub.textContent = subTitle;
      } catch (e) {
      }
      document.documentElement.classList.add('page-loading');
    }

    function hidePageLoadingAndShowPendingModal() {
      document.documentElement.classList.remove('page-loading');
      try {
        if (window.__pendingEditChapterModal && !window.__pendingEditChapterModalShown) {
          window.__pendingEditChapterModalShown = true;
          const p = window.__pendingEditChapterModal;
          showAlert(p.type, p.title, p.message, !!p.reload);
        }
      } catch (e) {
      }
    }

    alertOkBtn.addEventListener('click', () => {
      alertModal.classList.remove('active');
      alertModal.setAttribute('aria-hidden', 'true');
      if (alertCallback) alertCallback();
    });

    (function handleEditChapterMessage() {
      const params = new URLSearchParams(window.location.search);
      const status = params.get('status');
      const message = params.get('message');
      const title = params.get('title');
      if (!status || !message) return;

      const modalType = (status === 'success' || status === 'error' || status === 'warning') ? status : 'warning';
      const modalTitle = title || (modalType === 'success' ? 'Berhasil' : (modalType === 'error' ? 'Gagal' : 'Informasi'));
      window.__pendingEditChapterModal = { type: modalType, title: modalTitle, message };

      try {
        const id = params.get('id');
        const cleanUrl = window.location.pathname + (id ? ('?id=' + encodeURIComponent(id)) : '');
        window.history.replaceState({}, document.title, cleanUrl);
      } catch (e) {
      }
    })();

    let selectedChapterImages = [];
    let chapterPreviewRenderToken = 0;
    let isSyncingChapterInput = false;
    let draggedChapterImageIndex = null;
    const existingPageCount = <?php echo (int)count($images); ?>;
    const MAX_IMAGE_BYTES = 500 * 1024;

    const chapterImagesInput = document.getElementById('chapterImages');
    const previewChapterImagesContainer = document.getElementById('previewChapterImagesContainer');
    const previewChapterImagesDiv = document.getElementById('previewChapterImages');
    const previewChapterImagesLoading = document.getElementById('previewChapterImagesLoading');
    const editForm = document.getElementById('editChapterForm');
    const chapterJudulInput = document.getElementById('chapterJudul');
    const submitBtn = document.querySelector('#editChapterForm button[type="submit"]');

    const initialJudul = chapterJudulInput ? chapterJudulInput.value.trim() : '';

    function syncChapterInputFiles() {
      if (!chapterImagesInput) return;
      const dt = new DataTransfer();
      selectedChapterImages.forEach((f) => dt.items.add(f));
      isSyncingChapterInput = true;
      chapterImagesInput.files = dt.files;
      isSyncingChapterInput = false;
    }

    function setChapterPreviewLoading(isLoading) {
      if (!previewChapterImagesLoading || !previewChapterImagesContainer) return;

      if (isLoading) {
        previewChapterImagesLoading.classList.add('active');
        previewChapterImagesLoading.setAttribute('aria-hidden', 'false');
        previewChapterImagesContainer.classList.add('is-hidden');
      } else {
        previewChapterImagesLoading.classList.remove('active');
        previewChapterImagesLoading.setAttribute('aria-hidden', 'true');
        previewChapterImagesContainer.classList.remove('is-hidden');
      }
    }

    function renderChapterImagePreviews() {
      if (!previewChapterImagesContainer || !previewChapterImagesDiv) return;

      const myToken = ++chapterPreviewRenderToken;
      const container = previewChapterImagesContainer;
      container.innerHTML = "";

      if (selectedChapterImages.length > 0) {
        previewChapterImagesDiv.style.display = "block";
      } else {
        previewChapterImagesDiv.style.display = "none";
        setChapterPreviewLoading(false);
        return;
      }

      setChapterPreviewLoading(true);
      const whenAllLoaded = [];

      selectedChapterImages.forEach((file, index) => {
        const donePromise = new Promise((resolve) => {
          const previewItem = document.createElement('div');
          previewItem.classList.add('image-item');
          previewItem.draggable = true;
          previewItem.style.cursor = 'grab';

          previewItem.addEventListener('dragstart', function(e) {
            draggedChapterImageIndex = index;
            previewItem.classList.add('dragging');
            if (e.dataTransfer) {
              e.dataTransfer.effectAllowed = 'move';
              e.dataTransfer.setData('text/plain', String(index));
            }
          });

          previewItem.addEventListener('dragend', function() {
            previewItem.classList.remove('dragging');
            draggedChapterImageIndex = null;
          });

          previewItem.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            previewItem.classList.add('drag-over');
          });

          previewItem.addEventListener('dragleave', function() {
            previewItem.classList.remove('drag-over');
          });

          previewItem.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            previewItem.classList.remove('drag-over');

            if (draggedChapterImageIndex === null) return;
            if (draggedChapterImageIndex === index) return;

            const movedFile = selectedChapterImages.splice(draggedChapterImageIndex, 1)[0];
            selectedChapterImages.splice(index, 0, movedFile);
            syncChapterInputFiles();
            renderChapterImagePreviews();
            updateFileInputText();
          });

          const img = document.createElement('img');

          const controls = document.createElement('div');
          controls.classList.add('image-controls');

          const pageNumber = document.createElement('span');
          pageNumber.classList.add('page-number');
          pageNumber.textContent = `Hal. ${existingPageCount + index + 1}`;

          const deleteBtn = document.createElement('button');
          deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
          deleteBtn.classList.add('delete-image-btn');
          deleteBtn.type = 'button';
          deleteBtn.setAttribute('aria-label', `Hapus halaman ${existingPageCount + index + 1}`);
          deleteBtn.addEventListener('click', function() {
            const idx = selectedChapterImages.indexOf(file);
            if (idx > -1) selectedChapterImages.splice(idx, 1);
            syncChapterInputFiles();
            renderChapterImagePreviews();
            updateFileInputText();
          });

          controls.appendChild(pageNumber);
          controls.appendChild(deleteBtn);
          previewItem.appendChild(img);
          previewItem.appendChild(controls);
          container.appendChild(previewItem);

          const reader = new FileReader();

          reader.onload = function(e) {
            if (myToken !== chapterPreviewRenderToken) return resolve();
            img.onload = () => resolve();
            img.onerror = () => resolve();
            img.src = e.target.result;
          };

          reader.onerror = () => resolve();
          reader.readAsDataURL(file);
        });

        whenAllLoaded.push(donePromise);
      });

      Promise.all(whenAllLoaded)
        .then(() => {
          if (myToken !== chapterPreviewRenderToken) return;
          setChapterPreviewLoading(false);
        })
        .catch(() => {
          if (myToken !== chapterPreviewRenderToken) return;
          setChapterPreviewLoading(false);
        });
    }

    function updateFileInputText() {
      const fileInputText = document.getElementById('fileInputText') || document.querySelector('.file-input-text');
      if (!fileInputText) return;

      if (selectedChapterImages.length > 0) {
        fileInputText.textContent = `${selectedChapterImages.length} file dipilih`;
      } else {
        fileInputText.textContent = 'Drag & drop file di sini atau klik untuk memilih';
      }
    }

    if (chapterImagesInput) {
      chapterImagesInput.addEventListener('change', function(event) {
        if (isSyncingChapterInput) return;
        const files = event.target.files || [];
        let rejected = 0;
        for (let i = 0; i < files.length; i++) {
          if (files[i] && files[i].type && files[i].type.startsWith('image/')) {
            if (files[i].size > MAX_IMAGE_BYTES) {
              rejected++;
              continue;
            }
            selectedChapterImages.push(files[i]);
          }
        }
        if (rejected > 0) {
          showAlert('warning', 'Tidak Valid', 'Ukuran gambar terlalu besar! Maksimal 500KB per file.');
        }
        syncChapterInputFiles();
        updateFileInputText();
        renderChapterImagePreviews();
      });
    }

    function deleteImage(imageId, pageNumber) {
      showConfirm({
        title: 'Hapus Halaman?',
        message: `Apakah Anda yakin ingin menghapus halaman ${pageNumber}?`,
        okText: 'Ya, Hapus',
        cancelText: 'Batal',
        onConfirm: () => {
          window.__pendingEditChapterModalShown = false;
          showPageLoading('Menghapus Halaman...', 'Tunggu sesuai koneksi internet Anda');

          fetch('editchapter_action.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_image&image_id=${encodeURIComponent(String(imageId))}`
          })
          .then(response => response.json())
          .then(data => {
            if (data && data.success) {
              window.__pendingEditChapterModal = { type: 'success', title: 'Berhasil', message: data.message, reload: true };
            } else {
              window.__pendingEditChapterModal = { type: 'error', title: 'Gagal', message: (data && data.message) ? data.message : 'Gagal menghapus halaman', reload: false };
            }
            hidePageLoadingAndShowPendingModal();
          })
          .catch(error => {
            console.error('Error:', error);
            window.__pendingEditChapterModal = { type: 'error', title: 'Gagal', message: 'Terjadi kesalahan saat menghapus gambar', reload: false };
            hidePageLoadingAndShowPendingModal();
          });
        }
      });
    }

    const fileInputWrapper = document.querySelector('.file-input-wrapper');
    if (fileInputWrapper && chapterImagesInput) {
      fileInputWrapper.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileInputWrapper.classList.add('dragover');
      });

      fileInputWrapper.addEventListener('dragleave', () => {
        fileInputWrapper.classList.remove('dragover');
      });

      fileInputWrapper.addEventListener('drop', (e) => {
        e.preventDefault();
        fileInputWrapper.classList.remove('dragover');

        const files = e.dataTransfer && e.dataTransfer.files ? Array.from(e.dataTransfer.files) : [];
        if (files.length === 0) return;

        let rejected = 0;
        files.forEach((f) => {
          if (f && f.type && f.type.startsWith('image/')) {
            if (f.size > MAX_IMAGE_BYTES) {
              rejected++;
              return;
            }
            selectedChapterImages.push(f);
          }
        });

        if (rejected > 0) {
          showAlert('warning', 'Tidak Valid', 'Ukuran gambar terlalu besar! Maksimal 500KB per file.');
        }

        syncChapterInputFiles();
        updateFileInputText();
        renderChapterImagePreviews();
      });
    }

    if (submitBtn && editForm) {
      submitBtn.addEventListener('click', function(e) {
        if (editForm.dataset.confirmed === '1') return;

        const judul = chapterJudulInput ? chapterJudulInput.value.trim() : '';
        if (!judul) {
          e.preventDefault();
          showAlert('warning', 'Tidak Valid', 'Judul chapter tidak boleh kosong');
          return;
        }

        const changed = [];
        if (judul !== initialJudul) changed.push('judul chapter');
        if (selectedChapterImages.length > 0) changed.push('halaman baru');

        if (changed.length === 0) {
          e.preventDefault();
          showAlert('warning', 'Tidak Ada Perubahan', 'Tidak ada perubahan yang perlu disimpan.');
          return;
        }

        e.preventDefault();
        const message = changed.length === 1
          ? `Perubahan pada ${changed[0]} akan disimpan.`
          : `Perubahan pada ${changed[0]} dan ${changed[1]} akan disimpan.`;

        showConfirm({
          title: 'Simpan Perubahan?',
          message,
          okText: 'Ya, Simpan',
          cancelText: 'Batal',
          onConfirm: () => {
            editForm.dataset.confirmed = '1';
            showPageLoading('Menyimpan Perubahan...', 'Tunggu sesuai koneksi internet Anda');

            if (typeof editForm.requestSubmit === 'function') {
              editForm.requestSubmit(submitBtn);
            } else {
              editForm.submit();
            }
          }
        });
      });
    }

    updateFileInputText();
  </script>

  <script>
    function revealEditChapter() {
      hidePageLoadingAndShowPendingModal();
    }
    window.addEventListener('load', revealEditChapter);
    window.addEventListener('pageshow', revealEditChapter);
  </script>

  </div>
</body>
</html>
