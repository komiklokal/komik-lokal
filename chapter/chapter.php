<?php include("chapter_action.php"); ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="chapter.css?v=<?php echo filemtime(__FILE__); ?>">
  <link rel="stylesheet" href="../dark-mode.css?v=<?php echo filemtime(__FILE__); ?>">
  <script src="../theme.js?v=<?php echo filemtime(__FILE__); ?>"></script>
  <script>
    document.documentElement.classList.add('page-loading');
  </script>
  <title><?= htmlspecialchars($chapter['judul']); ?> - Komik Lokal</title>
  <style>
    #chapterLoadingOverlay {
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
    .page-loading #chapterLoadingOverlay {
      opacity: 1;
      visibility: visible;
    }
    .page-loading #chapterContent {
      visibility: hidden;
    }
    .chapter-loading-card {
      width: 90%;
      max-width: 420px;
      background: var(--bg-secondary, #ffffff);
      color: var(--text-primary, #2d3748);
      border-radius: 18px;
      padding: 2rem;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    }
    .chapter-loading-spinner {
      width: 56px;
      height: 56px;
      border-radius: 999px;
      border: 6px solid rgba(148, 163, 184, 0.35);
      border-top-color: var(--accent-color, #667eea);
      margin: 0 auto 1rem;
      animation: chapterSpin 0.9s linear infinite;
    }
    .chapter-loading-title {
      font-weight: 800;
      font-size: 1.25rem;
      margin-bottom: 0.35rem;
    }
    .chapter-loading-sub {
      color: var(--text-secondary, #4a5568);
      font-weight: 600;
    }
    @keyframes chapterSpin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div id="chapterLoadingOverlay" aria-hidden="true">
    <div class="chapter-loading-card" role="status" aria-live="polite">
      <div class="chapter-loading-spinner"></div>
      <div class="chapter-loading-title">Memuat Chapter...</div>
      <div class="chapter-loading-sub">Tunggu sesuai koneksi internet Anda</div>
    </div>
  </div>
  <div id="chapterContent">
  <header class="chapter-header">
    <div class="header-content">
      <a href="../komik.php?id=<?= $chapter['komik_id']; ?>" class="back-btn">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div class="chapter-info">
        <h1 class="chapter-title"><?= htmlspecialchars($chapter['judul']); ?></h1>
        <div class="chapter-meta">
          <span class="page-counter">
            <i class="fas fa-images"></i>
            <span id="currentPage">1</span> / <span id="totalPages"><?= count($images); ?></span>
          </span>
        </div>
      </div>
      <div class="header-actions">
        <button class="fullscreen-btn" onclick="toggleFullscreen()" title="Fullscreen">
          <i class="fas fa-expand"></i>
        </button>
        <button class="settings-btn" onclick="toggleSettings()" title="Pengaturan">
          <i class="fas fa-cog"></i>
        </button>
      </div>
    </div>
    <div class="reading-progress">
      <div class="progress-bar" id="progressBar"></div>
    </div>
  </header>
  <div class="settings-panel" id="settingsPanel">
    <div class="settings-content">
      <h3><i class="fas fa-sliders-h"></i> Pengaturan Pembacaan</h3>
      <div class="setting-item">
        <label>
          <i class="fas fa-search-plus"></i>
          Ukuran Gambar
        </label>
        <input type="range" id="imageScale" min="50" max="100" value="100" 
               oninput="setImageScale(this.value)">
        <span id="scaleValue">100%</span>
      </div>
      <div class="setting-item" id="scrollSpeedContainer" style="display: none;">
        <label>
          <i class="fas fa-tachometer-alt"></i>
          Kecepatan Scroll
        </label>
        <input type="range" id="scrollSpeed" min="1" max="10" value="4" 
               oninput="setScrollSpeed(this.value)">
        <span id="speedValue">Normal</span>
      </div>
    </div>
  </div>
  <div class="container" id="chapterContainer">
    <div class="chapter-images" id="chapterImages" data-mode="vertical">
      <?php if (!empty($images)): ?>
        <?php foreach ($images as $index => $row): ?>
          <div class="image-wrapper" data-page="<?= $index + 1; ?>">
            <img src="data:<?= htmlspecialchars($row['tipe_gambar']); ?>;base64,<?= base64_encode($row['gambar']); ?>" 
                 alt="Halaman <?= $index + 1; ?>"
                 class="chapter-image"
                 data-page="<?= $index + 1; ?>">
            <div class="page-number"><?= $index + 1; ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-images">
          <i class="fas fa-image"></i>
          <p>Tidak ada gambar untuk chapter ini.</p>
        </div>
      <?php endif; ?>
      <div class="fullscreen-page-nav" id="fullscreenPageNav" style="display: none;">
        <button class="fullscreen-nav-btn prev-page-btn" onclick="previousPage()">
          <i class="fas fa-chevron-left"></i>
        </button>
        <button class="fullscreen-nav-btn next-page-btn" onclick="nextPage()">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
    <div class="quick-nav" id="quickNav">
      <button class="quick-nav-btn" onclick="scrollToTop()" title="Ke Atas">
        <i class="fas fa-arrow-up"></i>
      </button>
      <button class="quick-nav-btn" onclick="scrollToBottom()" title="Ke Bawah">
        <i class="fas fa-arrow-down"></i>
      </button>
    </div>
    <div class="navigation">
      <div class="nav-container">
        <?php if ($prev): ?>
          <a href="chapter.php?id=<?= $prev['id']; ?>" class="nav-btn prev-btn">
            <i class="fas fa-chevron-left"></i>
            <div class="nav-text">
              <span class="nav-label">Previous</span>
              <span class="nav-title"><?= htmlspecialchars($prev['judul']); ?></span>
            </div>
          </a>
        <?php else: ?>
          <div class="nav-btn disabled">
            <i class="fas fa-chevron-left"></i>
            <div class="nav-text">
              <span class="nav-label">No Previous Chapter</span>
            </div>
          </div>
        <?php endif; ?>
        <a href="../komik/komik.php?id=<?= $chapter['komik_id']; ?>" class="nav-btn home-btn">
          <i class="fas fa-list"></i>
          <span>Chapter List</span>
        </a>
        <?php if ($next): ?>
          <a href="chapter.php?id=<?= $next['id']; ?>" class="nav-btn next-btn">
            <div class="nav-text">
              <span class="nav-label">Next</span>
              <span class="nav-title"><?= htmlspecialchars($next['judul']); ?></span>
            </div>
            <i class="fas fa-chevron-right"></i>
          </a>
        <?php else: ?>
          <div class="nav-btn disabled">
            <div class="nav-text">
              <span class="nav-label">No Next Chapter</span>
            </div>
            <i class="fas fa-chevron-right"></i>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="bottom-controls">
    <button class="control-btn" onclick="previousPage()">
      <i class="fas fa-chevron-left"></i>
    </button>
    <button class="control-btn" onclick="toggleSettings()">
      <i class="fas fa-cog"></i>
    </button>
    <button class="control-btn" onclick="nextPage()">
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>
  </div>
  <script>
    (function () {
      const images = Array.from(document.querySelectorAll('img.chapter-image'));
      function revealChapter() {
        document.documentElement.classList.remove('page-loading');
      }
      if (images.length === 0) {
        revealChapter();
        return;
      }
      let remaining = images.length;
      const onOneDone = () => {
        remaining -= 1;
        if (remaining <= 0) revealChapter();
      };
      images.forEach((img) => {
        if (img.complete && img.naturalWidth > 0) {
          onOneDone();
          return;
        }
        img.addEventListener('load', onOneDone, { once: true });
        img.addEventListener('error', onOneDone, { once: true });
      });
      setTimeout(revealChapter, 15000);
      window.addEventListener('pageshow', () => {
        const allComplete = images.every(i => i.complete);
        if (allComplete) revealChapter();
      });
    })();
  </script>
  <script>
    let currentPage = 1;
    const totalPages = <?= count($images); ?>;
    let readingMode = 'vertical';
    let autoScrollInterval = null;
    let scrollSpeed = 4;
    function updateProgress() {
      const container = document.getElementById('chapterImages');
      const images = container.querySelectorAll('.image-wrapper');
      const isHorizontal = readingMode === 'horizontal';
      images.forEach((img, index) => {
        const rect = img.getBoundingClientRect();
        const windowHeight = window.innerHeight;
        const windowWidth = window.innerWidth;
        if (isHorizontal) {
          if (rect.left >= 0 && rect.left < windowWidth / 2) {
            currentPage = index + 1;
          }
        } else {
          if (rect.top >= 0 && rect.top < windowHeight / 2) {
            currentPage = index + 1;
          }
        }
      });
      document.getElementById('currentPage').textContent = currentPage;
      const progress = (currentPage / totalPages) * 100;
      document.getElementById('progressBar').style.width = progress + '%';
      updateNavigationButtons();
      const scrolled = window.pageYOffset || document.documentElement.scrollTop;
      const quickNav = document.getElementById('quickNav');
      if (scrolled > 500) {
        quickNav.classList.add('show');
      } else {
        quickNav.classList.remove('show');
      }
    }
    function setReadingMode(mode) {
      readingMode = mode;
      const container = document.getElementById('chapterImages');
      const oldMode = container.getAttribute('data-mode');
      container.setAttribute('data-mode', mode);
      const modeButtons = document.querySelectorAll('.mode-btn');
      modeButtons.forEach(btn => {
        btn.classList.remove('active');
      });
      const activeModeBtn = document.querySelector(`.mode-btn[data-mode="${mode}"]`);
      if (activeModeBtn) {
        activeModeBtn.classList.add('active');
      }
      document.querySelectorAll('.chapter-image').forEach(img => {
        img.style.transform = '';
      });
      const currentScale = document.getElementById('imageScale').value;
      setTimeout(() => {
        setImageScale(currentScale);
      }, 100);
      if (mode === 'horizontal') {
        container.scrollIntoView({ behavior: 'smooth' });
      }
      if (autoScrollInterval) {
        const autoScrollEl = document.getElementById('autoScroll');
        if (autoScrollEl) autoScrollEl.checked = false;
        clearInterval(autoScrollInterval);
        autoScrollInterval = null;
      }
    }
    function setImageScale(value) {
      document.getElementById('scaleValue').textContent = value + '%';
      const scaleDecimal = value / 100;
      const images = document.querySelectorAll('.chapter-image');
      images.forEach(img => {
        img.style.transform = `scale(${scaleDecimal})`;
        img.style.transformOrigin = 'center center';
      });
      const wrappers = document.querySelectorAll('.image-wrapper');
      wrappers.forEach(wrapper => {
        wrapper.style.transform = `scale(${scaleDecimal})`;
        wrapper.style.transformOrigin = 'center center';
        wrapper.style.margin = value < 100 ? '1rem auto' : '0 auto';
      });
    }
    function toggleAutoScroll(enabled) {
      const speedContainer = document.getElementById('scrollSpeedContainer');
      if (enabled) {
        speedContainer.style.display = 'block';
        const pixelsPerInterval = scrollSpeed * 0.5;
        autoScrollInterval = setInterval(() => {
          if (readingMode === 'horizontal') {
            document.getElementById('chapterImages').scrollBy(pixelsPerInterval, 0);
          } else {
            window.scrollBy(0, pixelsPerInterval);
          }
        }, 50);
      } else {
        speedContainer.style.display = 'none';
        if (autoScrollInterval) {
          clearInterval(autoScrollInterval);
          autoScrollInterval = null;
        }
      }
    }
    function setScrollSpeed(value) {
      scrollSpeed = parseInt(value);
      const speedLabels = {
        1: 'Sangat Lambat',
        2: 'Lambat',
        3: 'Agak Lambat',
        4: 'Normal',
        5: 'Agak Cepat',
        6: 'Cepat',
        7: 'Lebih Cepat',
        8: 'Sangat Cepat',
        9: 'Super Cepat',
        10: 'Maksimal'
      };
      document.getElementById('speedValue').textContent = speedLabels[value] || 'Normal';
      if (autoScrollInterval) {
        const autoScrollEl = document.getElementById('autoScroll');
        const isChecked = autoScrollEl ? autoScrollEl.checked : true;
        toggleAutoScroll(false);
        toggleAutoScroll(isChecked);
      }
    }
    function toggleSettings() {
      const panel = document.getElementById('settingsPanel');
      panel.classList.toggle('show');
    }
    function toggleFullscreen() {
      const chapterContainer = document.getElementById('chapterImages');
      const fullscreenPageNav = document.getElementById('fullscreenPageNav');
      if (!document.fullscreenElement) {
        chapterContainer.requestFullscreen();
        document.querySelector('.fullscreen-btn i').classList.replace('fa-expand', 'fa-compress');
        fullscreenPageNav.style.display = 'flex';
        readingMode = 'horizontal';
        chapterContainer.setAttribute('data-mode', 'horizontal');
        setTimeout(() => {
          const currentImageWrapper = document.querySelector(`[data-page="${currentPage}"]`);
          if (currentImageWrapper) {
            currentImageWrapper.scrollIntoView({ behavior: 'auto', block: 'start' });
          }
          updateNavigationButtons();
        }, 100);
      } else {
        document.exitFullscreen();
        document.querySelector('.fullscreen-btn i').classList.replace('fa-compress', 'fa-expand');
        fullscreenPageNav.style.display = 'none';
        readingMode = 'vertical';
        chapterContainer.setAttribute('data-mode', 'vertical');
      }
    }
    document.addEventListener('fullscreenchange', () => {
      const chapterContainer = document.getElementById('chapterImages');
      const fullscreenPageNav = document.getElementById('fullscreenPageNav');
      if (!document.fullscreenElement) {
        readingMode = 'vertical';
        chapterContainer.setAttribute('data-mode', 'vertical');
        fullscreenPageNav.style.display = 'none';
        setTimeout(() => {
          const currentImageWrapper = document.querySelector(`[data-page="${currentPage}"]`);
          if (currentImageWrapper) {
            currentImageWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }, 100);
      }
    });
    function updateNavigationButtons() {
      if (!document.fullscreenElement) return;
      const prevBtn = document.querySelector('.prev-page-btn');
      const nextBtn = document.querySelector('.next-page-btn');
      if (prevBtn) {
        prevBtn.disabled = currentPage <= 1;
      }
      if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
      }
    }
    function scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function scrollToBottom() {
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }
    function previousPage() {
      if (currentPage > 1) {
        const targetImage = document.querySelector(`.image-wrapper[data-page="${currentPage - 1}"]`);
        if (targetImage) {
          if (document.fullscreenElement && readingMode === 'horizontal') {
            const container = document.getElementById('chapterImages');
            container.scrollBy({
              left: -window.innerWidth,
              behavior: 'smooth'
            });
          } else {
            targetImage.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          currentPage--;
          updateProgress();
          updateNavigationButtons();
        }
      }
    }
    function nextPage() {
      if (currentPage < totalPages) {
        const targetImage = document.querySelector(`.image-wrapper[data-page="${currentPage + 1}"]`);
        if (targetImage) {
          if (document.fullscreenElement && readingMode === 'horizontal') {
            const container = document.getElementById('chapterImages');
            container.scrollBy({
              left: window.innerWidth,
              behavior: 'smooth'
            });
          } else {
            targetImage.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          currentPage++;
          updateProgress();
          updateNavigationButtons();
        }
      }
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'd') nextPage();
      if (e.key === 'ArrowLeft' || e.key === 'a') previousPage();
      if (e.key === 'f') toggleFullscreen();
      if (e.key === 's') toggleSettings();
      if (e.key === 'Escape') {
        document.getElementById('settingsPanel').classList.remove('show');
      }
    });
    window.addEventListener('scroll', updateProgress);
    const chapterImagesContainer = document.getElementById('chapterImages');
    chapterImagesContainer.addEventListener('scroll', () => {
      updateProgress();
      if (document.fullscreenElement && readingMode === 'horizontal') {
        const scrollLeft = chapterImagesContainer.scrollLeft;
        const pageWidth = window.innerWidth;
        const newPage = Math.round(scrollLeft / pageWidth) + 1;
        if (newPage !== currentPage && newPage >= 1 && newPage <= totalPages) {
          currentPage = newPage;
          updateProgress();
          updateNavigationButtons();
        }
      }
    });
    window.addEventListener('load', updateProgress);
    let headerTimeout;
    const header = document.querySelector('.chapter-header');
    function showHeader() {
      header.classList.remove('hidden');
      clearTimeout(headerTimeout);
      headerTimeout = setTimeout(() => {
        header.classList.add('hidden');
      }, 3000);
    }
    document.addEventListener('mousemove', (e) => {
      if (e.clientY < 100) {
        showHeader();
      } else {
        if (!header.classList.contains('hidden')) {
          clearTimeout(headerTimeout);
          headerTimeout = setTimeout(() => {
            header.classList.add('hidden');
          }, 1000);
        }
      }
    });
    showHeader();
    document.addEventListener('click', (e) => {
      const panel = document.getElementById('settingsPanel');
      const settingsBtn = document.querySelector('.settings-btn');
      if (!panel.contains(e.target) && !settingsBtn.contains(e.target)) {
        panel.classList.remove('show');
      }
    });
    document.getElementById('totalPages').textContent = totalPages;
    setImageScale(100);
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('fullscreen')) {
      setTimeout(() => {
        toggleFullscreen();
      }, 500);
    }
  </script>
</body>
</html>
