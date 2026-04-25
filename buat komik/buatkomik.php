<?php
if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}
$includeConfig = __DIR__ . '/config.php';
if (file_exists($includeConfig)) {
  include $includeConfig;
}
$username = $_SESSION['username'] ?? null;
if ($username === null || $username === '') {
  header('Location: ../login/login.php');
  exit();
}

$allGenres = [];
if (isset($conn) && $conn instanceof mysqli) {
  $gStmt = $conn->prepare('SELECT nama FROM genre ORDER BY nama ASC LIMIT 500');
  if ($gStmt && $gStmt->execute()) {
    $gRes = $gStmt->get_result();
    if ($gRes) {
      while ($row = $gRes->fetch_assoc()) {
        if (isset($row['nama']) && $row['nama'] !== '') {
          $allGenres[] = $row['nama'];
        }
      }
    }
    $gStmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="buatkomik.css?v123">
  <link rel="stylesheet" href="../dark-mode.css?v=<?php echo filemtime(__FILE__); ?>">
  <script src="../theme.js?v=<?php echo filemtime(__FILE__); ?>" defer></script>
  <title>Upload Komik</title>
  <script>
    const MAX_IMAGE_BYTES = 500 * 1024; // 500KB

    window.__ALL_GENRES = <?php echo json_encode($allGenres, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
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
    .page-loading #buatKomikContent {
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
      animation: pageSpin 0.9s linear infinite;
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
    @keyframes pageSpin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
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
    .modal-icon.modal-warning {
      color: #ed8936;
      animation: pulse-warning 2s infinite;
    }
    .modal-icon.modal-success {
      color: #48bb78;
    }
    .modal-icon.modal-error {
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

    .preview {
      position: relative;
    }
    .preview-loading {
      position: absolute;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 0.75rem;
      background: rgba(0, 0, 0, 0.35);
      backdrop-filter: blur(6px);
      border-radius: 16px;
      z-index: 5;
      color: #fff;
      text-align: center;
      padding: 1.25rem;
    }
    .preview.loading .preview-loading {
      display: flex;
    }
    .preview.loading img {
      opacity: 0;
    }
    .preview-spinner {
      width: 44px;
      height: 44px;
      border-radius: 999px;
      border: 5px solid rgba(255, 255, 255, 0.35);
      border-top-color: rgba(255, 255, 255, 0.95);
      animation: coverSpin 0.9s linear infinite;
    }
    .preview-loading-text {
      font-weight: 800;
    }
    @keyframes coverSpin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div id="loadingOverlay" aria-hidden="true">
    <div class="loading-card" role="status" aria-live="polite">
      <div class="loading-spinner"></div>
      <div class="loading-title" id="pageLoadingTitle">Memuat Buat Komik...</div>
      <div class="loading-sub" id="pageLoadingSub">Tunggu sesuai koneksi internet Anda</div>
    </div>
  </div>
  <div id="buatKomikContent">
  <div class="navbar">
    <div class="navbar-container">
      <div class="navbar-brand">
        <i class="fas fa-book-open"></i>
        <h1>Buat Komik</h1>
      </div>
    </div>
  </div>
  <form id="buatKomikForm" action="buatkomik_action.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="formType" value="all">
    <div id="comicSection" class="container">
      <div class="form-group">
        <label for="judul">Judul Komik</label>
        <input type="text" id="judul" name="judul" placeholder="Masukkan Judul Komik">
        <div class="error" id="judulError"></div>
      </div>
      <div class="form-group">
        <label for="pengarang">Pengarang Komik</label>
        <input type="text" id="pengarang" name="pengarang" value="<?= htmlspecialchars($username); ?>" placeholder="Masukkan Nama Pengarang">
        <div class="error" id="pengarangError"></div>
      </div>
      <div class="form-group">
        <label for="genre">Genre Komik</label>
        <div class="genre-input-wrapper">
          <input type="text" id="genre" name="genre" placeholder="Masukkan Genre" autocomplete="off">
          <div class="autocomplete-dropdown" id="genreDropdown"></div>
        </div>
        <div class="error" id="genreError"></div>
      </div>
      <div class="form-group">
        <label for="sinopsis">Sinopsis Komik</label>
        <div id="sinopsisEditor" class="sinopsis-editor" contenteditable="true" data-placeholder="Masukkan Sinopsis Komik"></div>
        <input type="hidden" id="sinopsis" name="sinopsis" value="">
        <div class="error" id="sinopsisError"></div>
      </div>
      <div class="form-group">
        <label for="status">Status</label>
        <input type="text" id="status" name="status" value="Ongoing" readonly style="background-color: #f7fafc; cursor: not-allowed; color: #4a5568;">
      </div>
      <div class="preview" id="preview">
        <h2>Preview Sampul</h2>
        <div class="preview-loading" id="coverPreviewLoading" aria-hidden="true">
          <div class="preview-spinner" aria-hidden="true"></div>
          <div class="preview-loading-text">Memuat sampul...</div>
        </div>
        <img id="previewImg" src="" alt="Preview sampul komik">
      </div>
      <div class="form-group">
        <label for="cover">Unggah Sampul Komik</label>
        <div class="file-input-wrapper">
          <input type="file" id="cover" name="cover" accept="image/*">
          <div class="file-input-display" id="coverFileDisplay" tabindex="0">
            <span class="file-input-button"><i class="fas fa-upload"></i></span>
            <span class="file-input-text" id="coverFileText">Drag &amp; drop file di sini atau klik untuk memilih</span>
          </div>
        </div>
        <div class="error" id="coverError"></div>
      </div>

      <div class="navigation-buttons">
        <div></div>
        <button type="button" class="btn-submit" id="toChapterBtn">Selanjutnya</button>
      </div>
    </div>

    <div id="chapterSection" class="container hidden">
      <h2>Chapter Pertama</h2>
      <div class="form-group">
        <label for="chapterJudul">Judul Chapter</label>
        <input type="text" id="chapterJudul" name="chapterJudul" placeholder="Masukkan Judul Chapter">
        <div class="error" id="chapterJudulError"></div>
      </div>

      <div class="form-group">
        <label for="chapterImages">Unggah Gambar Chapter</label>
        <div class="file-input-wrapper">
          <input type="file" id="chapterImages" name="chapterImages[]" accept="image/*" multiple>
          <div class="file-input-display">
            <span class="file-input-button"><i class="fas fa-images"></i></span>
            <span class="file-input-text" id="chapterFileText">Drag &amp; drop file di sini atau klik untuk memilih</span>
          </div>
        </div>
        <div class="error" id="chapterImagesError"></div>
      </div>

      <div class="preview" id="previewChapterImages">
        <h2>Preview Gambar Chapter</h2>
        <div class="preview-scroll-container">
          <div id="previewChapterImagesContainer"></div>
        </div>
        <div class="scroll-nav" id="scrollNav">
          <button type="button" class="scroll-btn" id="scrollUp" aria-label="Scroll ke atas"><i class="fas fa-chevron-up"></i></button>
          <div class="scroll-info" id="scrollInfo"></div>
          <button type="button" class="scroll-btn" id="scrollDown" aria-label="Scroll ke bawah"><i class="fas fa-chevron-down"></i></button>
        </div>
      </div>

      <div class="navigation-buttons">
        <button type="button" class="btn-back" id="backToComicBtn">Kembali</button>
        <button type="submit" class="btn-submit">Upload</button>
      </div>
    </div>
  </form>

    <div class="custom-modal-overlay" id="confirmModal">
      <div class="custom-modal">
          <div class="modal-icon modal-warning"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="modal-title" id="confirmTitle">Konfirmasi</div>
        <div class="modal-message" id="confirmMessage">Apakah Anda yakin?</div>
        <div class="modal-actions">
          <button class="modal-btn modal-btn-cancel" id="confirmCancelBtn">Batal</button>
          <button class="modal-btn modal-btn-confirm" id="confirmOkBtn">Ya</button>
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
    }
    confirmCancelBtn.addEventListener('click', () => {
      confirmModal.classList.remove('active');
      confirmCallback = null;
    });
    confirmOkBtn.addEventListener('click', () => {
      confirmModal.classList.remove('active');
      if (confirmCallback) confirmCallback();
    });
    function showAlert(type, title, message, reload = false, redirectUrl = null) {
      alertTitle.textContent = title;
      alertMessage.textContent = message;
      if (type === 'success') {
          alertIcon.className = 'modal-icon modal-success';
        alertIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
      } else if (type === 'error') {
          alertIcon.className = 'modal-icon modal-error';
        alertIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
      } else {
          alertIcon.className = 'modal-icon modal-warning';
        alertIcon.innerHTML = '<i class="fas fa-info-circle"></i>';
      }
      alertCallback = redirectUrl
        ? () => { window.location.href = redirectUrl; }
        : (reload ? () => location.reload() : null);
      alertModal.classList.add('active');
    }
    alertOkBtn.addEventListener('click', () => {
      alertModal.classList.remove('active');
      if (alertCallback) alertCallback();
    });
    (function handleBuatKomikMessage() {
      const params = new URLSearchParams(window.location.search);
      const status = params.get('status');
      const message = params.get('message');
      const title = params.get('title');
      const redirect = params.get('redirect');
      if (!status || !message) return;
      const modalType = (status === 'success' || status === 'error' || status === 'warning') ? status : 'warning';
      const modalTitle = title || (modalType === 'success' ? 'Berhasil' : (modalType === 'error' ? 'Gagal' : 'Informasi'));
      showAlert(modalType, modalTitle, message, false, redirect);
      try {
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
      } catch (e) {
      }
    })();
    const comicSection = document.getElementById('comicSection');
    const chapterSection = document.getElementById('chapterSection');
    const toChapterBtn = document.getElementById('toChapterBtn');
    const backToComicBtn = document.getElementById('backToComicBtn');
    if (toChapterBtn && comicSection && chapterSection) toChapterBtn.addEventListener('click', function() {
      document.querySelectorAll('.error').forEach(error => error.textContent = '');
      let hasError = false;
      if (document.getElementById('judul').value.trim() === "") {
        document.getElementById('judulError').textContent = "Judul komik harus diisi.";
        hasError = true;
      }
      if (document.getElementById('pengarang').value.trim() === "") {
        document.getElementById('pengarangError').textContent = "Nama pengarang harus diisi.";
        hasError = true;
      }
      if (document.getElementById('genre').value.trim() === "") {
        document.getElementById('genreError').textContent = "Genre komik harus diisi.";
        hasError = true;
      }
      if (document.getElementById('sinopsis').value.trim() === "") {
        document.getElementById('sinopsisError').textContent = "Sinopsis komik harus diisi.";
        hasError = true;
      }
      if (!document.getElementById('cover').files[0]) {
        document.getElementById('coverError').textContent = "Unggah sampul komik.";
        hasError = true;
      }
      if (!hasError) {
        const loadingTitle = document.getElementById('pageLoadingTitle');
        const loadingSub = document.getElementById('pageLoadingSub');
        if (loadingTitle) loadingTitle.textContent = 'Memuat Halaman Selanjutnya...';
        if (loadingSub) loadingSub.textContent = 'Tunggu sesuai koneksi internet Anda';
        document.documentElement.classList.add('page-loading');
        setTimeout(() => {
          comicSection.classList.add('hidden');
          chapterSection.classList.remove('hidden');
          document.documentElement.classList.remove('page-loading');
        }, 400);
      }
    });
    if (backToComicBtn && comicSection && chapterSection) backToComicBtn.addEventListener('click', function() {
      document.querySelectorAll('.error').forEach(error => error.textContent = '');
      chapterSection.classList.add('hidden');
      comicSection.classList.remove('hidden');
    });
    const previewImg = document.getElementById('previewImg');
    const coverFileText = document.getElementById('coverFileText');
    const previewDiv = document.getElementById('preview');
    const coverInputEl = document.getElementById('cover');
    if (coverInputEl && previewImg && coverFileText && previewDiv) coverInputEl.addEventListener('change', function(event) {
      const file = event.target.files[0];
      if (file) {
        const coverErrorEl = document.getElementById('coverError');
        if (file.size > MAX_IMAGE_BYTES) {
          if (coverErrorEl) coverErrorEl.textContent = 'Ukuran cover terlalu besar! Maksimal 500KB.';
          event.target.value = '';
          previewImg.src = '';
          previewDiv.style.display = 'none';
          previewDiv.classList.remove('visible');
          previewDiv.classList.remove('loading');
          coverFileText.textContent = 'Drag & drop file di sini atau klik untuk memilih';
          return;
        }
        if (coverErrorEl) coverErrorEl.textContent = '';
        previewDiv.style.display = 'flex';
        previewDiv.classList.add('visible');
        previewDiv.classList.add('loading');
        previewImg.onload = function() {
          previewDiv.classList.remove('loading');
        };
        previewImg.onerror = function() {
          previewDiv.classList.remove('loading');
        };
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImg.src = e.target.result;
        };
        reader.onerror = function() {
          previewDiv.classList.remove('loading');
        };
        reader.readAsDataURL(file);
        coverFileText.textContent = `${file.name} dipilih`;
      } else {
        previewImg.src = '';
        previewDiv.style.display = 'none'; 
        previewDiv.classList.remove('visible');
        previewDiv.classList.remove('loading');
        coverFileText.textContent = 'Drag & drop file di sini atau klik untuk memilih';
      }
    });
    let selectedChapterImages = [];
    let draggedChapterImageIndex = null;

    const chapterImagesInput = document.getElementById('chapterImages');
    const previewChapterImagesContainer = document.getElementById('previewChapterImagesContainer');
    const previewChapterImagesDiv = document.getElementById('previewChapterImages');
    if (chapterImagesInput && previewChapterImagesContainer && previewChapterImagesDiv) chapterImagesInput.addEventListener('change', function(event) {
      const files = event.target.files;
      const rejected = [];
      for (let i = 0; i < files.length; i++) {
        if (files[i].type.startsWith('image/')) {
          if (files[i].size > MAX_IMAGE_BYTES) {
            rejected.push(i + 1);
            continue;
          }
          selectedChapterImages.push(files[i]);
        }
      }
      const chapterErrEl = document.getElementById('chapterImagesError');
      if (chapterErrEl) {
        chapterErrEl.textContent = rejected.length ? 'Ada gambar yang ukurannya lebih dari 500KB dan tidak dimasukkan.' : '';
      }
      updateFileInputText();
      renderChapterImagePreviews();
    });
    function renderChapterImagePreviews() {
      const container = previewChapterImagesContainer;
      const scrollNav = document.getElementById('scrollNav');
      const scrollInfo = document.getElementById('scrollInfo');
      container.innerHTML = "";
      if (selectedChapterImages.length > 0) {
        previewChapterImagesDiv.style.display = "flex";
        previewChapterImagesDiv.classList.add('visible');
        previewChapterImagesDiv.classList.add('loading');
        if (selectedChapterImages.length > 6) {
          scrollNav.classList.add('active');
          container.classList.add('preview-scroll-wrapper');
          container.style.overflowY = 'auto';
          container.style.maxHeight = '600px';
          scrollInfo.textContent = `Menampilkan 6 dari ${selectedChapterImages.length} gambar`;
        } else {
          scrollNav.classList.remove('active');
          container.classList.remove('preview-scroll-wrapper');
          container.style.overflowY = 'visible';
          container.style.maxHeight = 'none';
        }
      } else {
        previewChapterImagesDiv.style.display = "none";
        previewChapterImagesDiv.classList.remove('visible');
        previewChapterImagesDiv.classList.remove('loading');
        scrollNav.classList.remove('active');
      }
      let pendingReads = selectedChapterImages.length;
      if (pendingReads === 0) {
        previewChapterImagesDiv.classList.remove('loading');
      }
      selectedChapterImages.forEach((file, index) => {
        const previewItem = document.createElement('div');
        previewItem.classList.add('preview-item');
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
          renderChapterImagePreviews();
          updateFileInputText();
        });

        const img = document.createElement('img');
        img.classList.add('chapter-image');
        img.setAttribute('data-aspect', '4-5');

        const controls = document.createElement('div');
        controls.classList.add('preview-image-controls');

        const pageNumber = document.createElement('span');
        pageNumber.classList.add('preview-page-number');
        pageNumber.textContent = `Hal. ${index + 1}`;

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.classList.add('preview-delete-btn');
        deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
        deleteBtn.addEventListener('click', function() {
          const idx = selectedChapterImages.indexOf(file);
          if (idx > -1) selectedChapterImages.splice(idx, 1);
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
          img.src = e.target.result;
          img.onload = function() {
            const aspectRatio = this.naturalWidth / this.naturalHeight;
            if (Math.abs(aspectRatio - 0.8) < 0.1) {
              this.setAttribute('data-optimized', '800x1000');
              console.log(`Optimized display for ${file.name} (${this.naturalWidth}x${this.naturalHeight})`);
            }
          };
        };
        reader.onloadend = function() {
          pendingReads = Math.max(0, pendingReads - 1);
          if (pendingReads === 0) {
            previewChapterImagesDiv.classList.remove('loading');
          }
        };
        reader.readAsDataURL(file);
      });
      setupScrollNavigation();
    }
    function setupScrollNavigation() {
      const container = previewChapterImagesContainer;
      const scrollUp = document.getElementById('scrollUp');
      const scrollDown = document.getElementById('scrollDown');
      const scrollInfo = document.getElementById('scrollInfo');
      if (selectedChapterImages.length <= 6) return;
      const itemHeight = 320; 
      function updateScrollInfo() {
        const scrollPosition = container.scrollTop;
        const maxScroll = container.scrollHeight - container.clientHeight;
        scrollInfo.textContent = `Scroll untuk melihat ${selectedChapterImages.length - 6} gambar lainnya`;
        scrollUp.disabled = scrollPosition <= 0;
        scrollDown.disabled = scrollPosition >= maxScroll;
      }
      scrollUp.onclick = () => {
        container.scrollBy({ top: -itemHeight, behavior: 'smooth' });
        setTimeout(updateScrollInfo, 300);
      };
      scrollDown.onclick = () => {
        container.scrollBy({ top: itemHeight, behavior: 'smooth' });
        setTimeout(updateScrollInfo, 300);
      };
      container.onscroll = updateScrollInfo;
      setTimeout(updateScrollInfo, 100);
    }
    function updateFileInputText() {
      const fileInputText = document.getElementById('chapterFileText')
        || document.querySelector('#chapterSection .file-input-text')
        || document.querySelector('.file-input-text');
      if (selectedChapterImages.length > 0) {
        fileInputText.textContent = `${selectedChapterImages.length} file(s) dipilih`;
      } else {
        fileInputText.textContent = 'Drag & drop file di sini atau klik untuk memilih';
      }
    }
    const comicContainer = document.getElementById('comicSection');
    const chapterContainer = document.getElementById('chapterSection');
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      comicContainer.addEventListener(eventName, preventDefaults, false);
      chapterContainer.addEventListener(eventName, preventDefaults, false);
    });
    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }
    ['dragenter', 'dragover'].forEach(eventName => {
      comicContainer.addEventListener(eventName, highlightComic, false);
      chapterContainer.addEventListener(eventName, highlightChapter, false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
      comicContainer.addEventListener(eventName, unhighlightComic, false);
      chapterContainer.addEventListener(eventName, unhighlightChapter, false);
    });
    function highlightComic(e) {
      comicContainer.style.background = 'rgba(102, 126, 234, 0.1)';
      comicContainer.style.transform = 'scale(1.02)';
    }
    function unhighlightComic(e) {
      comicContainer.style.background = '';
      comicContainer.style.transform = '';
    }
    function highlightChapter(e) {
      chapterContainer.style.background = 'rgba(102, 126, 234, 0.1)';
      chapterContainer.style.transform = 'scale(1.02)';
    }
    function unhighlightChapter(e) {
      chapterContainer.style.background = '';
      chapterContainer.style.transform = '';
    }
    comicContainer.addEventListener('drop', handleCoverDrop, false);
    chapterContainer.addEventListener('drop', handleChapterDrop, false);
    function handleCoverDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      if (files.length > 0 && files[0].type.startsWith('image/')) {
        const coverErrorEl = document.getElementById('coverError');
        if (files[0].size > MAX_IMAGE_BYTES) {
          if (coverErrorEl) coverErrorEl.textContent = 'Ukuran cover terlalu besar! Maksimal 500KB.';
          return;
        }
        if (coverErrorEl) coverErrorEl.textContent = '';
        const coverInput = document.getElementById('cover');
        coverInput.files = files;
        const event = new Event('change', { bubbles: true });
        coverInput.dispatchEvent(event);
      }
    }
    function handleChapterDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      const rejected = [];
      for (let i = 0; i < files.length; i++) {
        if (files[i].type.startsWith('image/')) {
          if (files[i].size > MAX_IMAGE_BYTES) {
            rejected.push(i + 1);
            continue;
          }
          selectedChapterImages.push(files[i]);
        }
      }
      const chapterErrEl = document.getElementById('chapterImagesError');
      if (chapterErrEl) {
        chapterErrEl.textContent = rejected.length ? 'Ada gambar yang ukurannya lebih dari 500KB dan tidak dimasukkan.' : '';
      }
      updateFileInputText();
      renderChapterImagePreviews();
    }
    document.getElementById('buatKomikForm').addEventListener('submit', function(event) {
      if (this.dataset.confirmed === '1') {
        return;
      }
      if (document.getElementById('chapterSection').classList.contains('hidden') === false) {
        document.querySelectorAll('.error').forEach(error => error.textContent = '');
        let hasError = false;
        if (document.getElementById('chapterJudul').value.trim() === "") {
          document.getElementById('chapterJudulError').textContent = "Judul chapter harus diisi.";
          hasError = true;
        }
        if (selectedChapterImages.length === 0) {
          document.getElementById('chapterImagesError').textContent = "Upload minimal 1 gambar chapter.";
          hasError = true;
        }
        if (hasError) {
          event.preventDefault();
          return;
        }
        event.preventDefault();
        showConfirm({
          title: 'Upload Komik?',
          message: 'Pastikan data komik dan chapter sudah benar.',
          okText: 'Ya, Upload',
          cancelText: 'Batal',
          onConfirm: () => {
            if (selectedChapterImages.length > 0) {
              const dt = new DataTransfer();
              selectedChapterImages.forEach(file => {
                dt.items.add(file);
              });
              document.getElementById('chapterImages').files = dt.files;
            }
            const loadingTitle = document.getElementById('pageLoadingTitle');
            const loadingSub = document.getElementById('pageLoadingSub');
            if (loadingTitle) loadingTitle.textContent = 'Mengupload Komik...';
            if (loadingSub) loadingSub.textContent = 'Tunggu sesuai koneksi internet Anda';
            document.documentElement.classList.add('page-loading');
            const submitBtn = document.querySelector('#chapterSection button[type="submit"]');
            if (submitBtn) {
              submitBtn.classList.add('loading');
              submitBtn.textContent = 'Mengupload...';
              submitBtn.disabled = true;
            }
            this.dataset.confirmed = '1';
            if (typeof this.requestSubmit === 'function') {
              this.requestSubmit();
            } else {
              this.submit();
            }
          }
        });
      }
    });
    const coverFileDisplay = document.getElementById('coverFileDisplay');
    const coverInput = document.getElementById('cover');
    if (coverFileDisplay && coverInput) {
      coverFileDisplay.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '#667eea';
        this.style.background = 'rgba(247, 250, 252, 1)';
      });
      coverFileDisplay.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '#cbd5e0';
        this.style.background = 'rgba(247, 250, 252, 0.9)';
      });
      coverFileDisplay.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '#cbd5e0';
        this.style.background = 'rgba(247, 250, 252, 0.9)';
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('image/')) {
          const coverErrorEl = document.getElementById('coverError');
          if (files[0].size > MAX_IMAGE_BYTES) {
            if (coverErrorEl) coverErrorEl.textContent = 'Ukuran cover terlalu besar! Maksimal 500KB.';
            return;
          }
          if (coverErrorEl) coverErrorEl.textContent = '';
          coverInput.files = files;
          const event = new Event('change', { bubbles: true });
          coverInput.dispatchEvent(event);
        }
      });
    }
    let lastGenreValue = '';
    const genreEl = document.getElementById('genre');
    if (genreEl) genreEl.addEventListener('input', function(e) {
        let currentValue = e.target.value;
        let cursorPos = e.target.selectionStart;
        if (currentValue.length > lastGenreValue.length) {
            let addedChar = currentValue[currentValue.length - 1];
            if (addedChar === ' ') {
                let beforeSpace = currentValue.slice(0, -1);
                if (beforeSpace.trim() && !beforeSpace.endsWith(',')) {
                    e.target.value = beforeSpace + ', ';
                    e.target.setSelectionRange(beforeSpace.length + 2, beforeSpace.length + 2);
                }
            }
        }
        lastGenreValue = e.target.value;
        let cleanValue = e.target.value;
        cleanValue = cleanValue.replace(/,\s*,+/g, ', '); 
        cleanValue = cleanValue.replace(/\s{2,}/g, ' '); 
        if (cleanValue !== e.target.value) {
            e.target.value = cleanValue;
            e.target.setSelectionRange(cursorPos, cursorPos);
        }
    });
      if (genreEl) genreEl.addEventListener('keyup', function(e) {
        if (e.key === ' ') {
            let value = e.target.value;
            let cursorPos = e.target.selectionStart;
            if (value.endsWith(' ') && value.trim().length > 0) {
                let beforeSpace = value.slice(0, -1);
                if (!beforeSpace.endsWith(',') && beforeSpace.trim().length > 0) {
                    e.target.value = beforeSpace + ', ';
                    e.target.setSelectionRange(beforeSpace.length + 2, beforeSpace.length + 2);
                }
            }
        }
    });
    if (genreEl) genreEl.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace') {
            let value = e.target.value;
            let cursorPos = e.target.selectionStart;
            if (cursorPos >= 2 && value.substring(cursorPos - 2, cursorPos) === ', ') {
                e.preventDefault();
                e.target.value = value.substring(0, cursorPos - 2) + ' ' + value.substring(cursorPos);
                e.target.setSelectionRange(cursorPos - 1, cursorPos - 1);
            }
        }
    });
    if (genreEl) genreEl.addEventListener('blur', function(e) {
        let value = e.target.value.trim();
        if (value) {
            let genres = value.split(',')
                .map(genre => genre.trim())
                .filter(genre => genre.length > 0);
            genres = [...new Set(genres)];
            e.target.value = genres.join(', ');
        }
    });
    document.getElementById('buatKomikForm').addEventListener('submit', function(e) {
        const genreInput = document.getElementById('genre');
        let value = genreInput.value.trim();
        if (value) {
            let genres = value.split(',')
                .map(genre => genre.trim())
                .filter(genre => genre.length > 0);
            genres = [...new Set(genres)];
            genreInput.value = genres.join(', ');
        }
    });
    let currentGenreIndex = -1;
    let genreDropdownItems = [];
    let genreTimeout = null;
    const genreInput = document.getElementById('genre');
    const genreDropdown = document.getElementById('genreDropdown');
    function getLastGenreWord() {
        const value = genreInput.value;
        const cursorPos = genreInput.selectionStart;
        const beforeCursor = value.substring(0, cursorPos);
        const lastCommaIndex = beforeCursor.lastIndexOf(',');
        const currentWord = beforeCursor.substring(lastCommaIndex + 1).trim();
        return {
            word: currentWord,
            startPos: lastCommaIndex + 1,
            endPos: cursorPos
        };
    }
    function replaceCurrentGenre(selectedGenre) {
        const value = genreInput.value;
        const cursorPos = genreInput.selectionStart;
        const current = getLastGenreWord();
        const beforeWord = value.substring(0, current.startPos);
        const afterCursor = value.substring(cursorPos);
        let newValue = beforeWord.trim();
        if (newValue && !newValue.endsWith(',')) {
            newValue += ', ';
        }
        if (newValue) {
            newValue += selectedGenre + ', ';
        } else {
            newValue = selectedGenre + ', ';
        }
        newValue += afterCursor;
        genreInput.value = newValue;
        const newCursorPos = newValue.length - afterCursor.length;
        genreInput.setSelectionRange(newCursorPos, newCursorPos);
        genreInput.focus();
    }
    function showGenreDropdown(genres) {
        genreDropdown.innerHTML = '';
        genreDropdownItems = [];
        if (genres.length === 0) {
            genreDropdown.classList.remove('show');
            return;
        }
        genres.forEach((genre, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = genre;
            item.addEventListener('click', () => {
                replaceCurrentGenre(genre);
                genreDropdown.classList.remove('show');
            });
            genreDropdown.appendChild(item);
            genreDropdownItems.push(item);
        });
        genreDropdown.classList.add('show');
        currentGenreIndex = -1;
    }
    function hideGenreDropdown() {
        genreDropdown.classList.remove('show');
        currentGenreIndex = -1;
        genreDropdownItems = [];
    }
    function highlightGenreItem(index) {
        genreDropdownItems.forEach((item, i) => {
            if (i === index) {
                item.classList.add('highlighted');
            } else {
                item.classList.remove('highlighted');
            }
        });
    }
    function revealBuatKomik() {
      document.documentElement.classList.remove('page-loading');
    }
    window.addEventListener('load', revealBuatKomik);
    window.addEventListener('pageshow', revealBuatKomik);
    function searchGenres(term) {
        if (term.length < 1) {
            hideGenreDropdown();
            return;
        }
      const localGenres = Array.isArray(window.__ALL_GENRES) ? window.__ALL_GENRES : [];
      const defaultGenres = ['Action', 'Adventure'];
      const getFallbackGenres = (q) => {
        const needle = String(q || '').trim().toLowerCase();
        if (!needle) return [];
        const source = localGenres.length ? localGenres : defaultGenres;
        const out = source.filter(name => String(name).toLowerCase().indexOf(needle) === 0);
        return out.slice(0, 20);
      };
      fetch(`get_genres.php?term=${encodeURIComponent(term)}`)
        .then(response => response.json())
        .then(genres => {
          const list = Array.isArray(genres) ? genres : [];
          showGenreDropdown(list.length ? list : getFallbackGenres(term));
        })
        .catch(error => {
          console.error('Error fetching genres:', error);
          showGenreDropdown(getFallbackGenres(term));
        });
    }
    genreInput.addEventListener('input', function(e) {
        if (genreTimeout) {
            clearTimeout(genreTimeout);
        }
        const current = getLastGenreWord();
        genreTimeout = setTimeout(() => {
            searchGenres(current.word);
        }, 300);
    });
    genreInput.addEventListener('keydown', function(e) {
        if (!genreDropdown.classList.contains('show')) {
            return;
        }
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                currentGenreIndex = Math.min(currentGenreIndex + 1, genreDropdownItems.length - 1);
                highlightGenreItem(currentGenreIndex);
                break;
            case 'ArrowUp':
                e.preventDefault();
                currentGenreIndex = Math.max(currentGenreIndex - 1, -1);
                highlightGenreItem(currentGenreIndex);
                break;
            case 'Enter':
                e.preventDefault();
                if (currentGenreIndex >= 0 && genreDropdownItems[currentGenreIndex]) {
                    const selectedGenre = genreDropdownItems[currentGenreIndex].textContent;
                    replaceCurrentGenre(selectedGenre);
                    hideGenreDropdown();
                }
                break;
            case 'Escape':
                e.preventDefault();
                hideGenreDropdown();
                break;
        }
    });
    document.addEventListener('click', function(e) {
        if (!genreInput.contains(e.target) && !genreDropdown.contains(e.target)) {
            hideGenreDropdown();
        }
    });
    genreInput.addEventListener('blur', function() {
        setTimeout(() => {
            hideGenreDropdown();
        }, 150);
    });
    const sinopsisEditor = document.getElementById('sinopsisEditor');
    const sinopsisInput = document.getElementById('sinopsis');
    if (sinopsisEditor) {
        sinopsisEditor.addEventListener('input', function() {
            sinopsisInput.value = this.textContent;
        });
        document.querySelector('form').addEventListener('submit', function() {
            sinopsisInput.value = sinopsisEditor.textContent;
        });
    }
  </script>
  </div>
</body>
</html>
