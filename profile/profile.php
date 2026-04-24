<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include("config.php");
$isLoggedIn = isset($_SESSION['username']);
$username = $isLoggedIn ? $_SESSION['username'] : null;
$userData = null;
if ($isLoggedIn) {
    $sql = "SELECT user_nama, profile_image_blob, profile_image_type FROM user WHERE user_nama = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $stmt->close();
    }
    $userData = $userData ?: [
        'user_nama' => $username,
        'profile_image_blob' => null,
        'profile_image_type' => null,
    ];
}
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="profile.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../dark-mode.css?v=<?php echo time(); ?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <script src="../theme.js?v=<?php echo time(); ?>"></script>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
  <title>Profile</title>
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
        .page-loading #profileContent {
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
            <div class="loading-title">Memuat Profile...</div>
            <div class="loading-sub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>
    <div id="profileContent">
  <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <i class="fas fa-book-open"></i>
                <h1>Profile</h1>
            </div>
            <button class="theme-toggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </div>
  <?php if (!$isLoggedIn): ?>
  <div class="login-required">
      <div class="login-prompt-card">
          <h2>Login Required</h2>
          <p>Silakan login untuk mengakses fitur profil, creator, dan lainnya</p>
          <div class="login-buttons">
              <a href="../login/login.php" class="btn-login">
                  <i class="fas fa-sign-in-alt"></i>
                  Login
              </a>
              <a href="../register/register.php" class="btn-register">
                  <i class="fas fa-user-plus"></i>
                  Daftar
              </a>
          </div>
      </div>
  </div>
  <?php
else: ?>
  <div class="profile">
      <div class="profile-picture">
          <?php if (!empty($userData['profile_image_blob'])): ?>
              <img src="../getImage.php?username=<?= urlencode($userData['user_nama']); ?>&v=<?= time(); ?>" 
                   alt="Profile Picture"
                   onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=\'default-avatar\'><i class=\'fas fa-user\'></i></div>';">
          <?php
    else: ?>
              <div class="default-avatar">
                  <i class="fas fa-user"></i>
              </div>
          <?php
    endif; ?>
      </div>
      <div class="profile-details">
          <h1 class="profile-username"><?= htmlspecialchars($userData['user_nama']); ?></h1>
          <div class="profile-actions">
              <button id="edit-profile-btn"><a href="../edit%20profile/editprofile.php">Edit Profil</a></button>
          </div>
      </div>
  </div>
  <div class="container-profile">
        <p class="container" id="setting-container">
            <a href="../creator/creator.php" class="action-card">
                <i class="fas fa-pen"></i>
                <span>Creator</span>
            </a>
        </p>
        <p class="container" id="setting-container">
            <a href="../riwayat baca/riwayat.php" class="action-card">
                <i class="fas fa-history"></i>
                <span>Riwayat Baca</span>
            </a>
        </p>
        <p class="container" id="setting-container">
            <a href="../Bookmark/bookmark.php" class="action-card">
                <i class="fas fa-bookmark"></i>
                <span>Bookmark</span>
            </a>
        </p>
        <p class="container" id="setting-container">
            <a href="../setting.php" class="action-card">
                <i class="fas fa-gear"></i>
                <span>Setting</span>
            </a>
        </p>
  </div>
  <?php
endif; ?>
    <nav class="bottom-nav">
        <a href="../dashboard/dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="../daftar komik/daftarkomik.php" class="nav-item">
            <i class="fas fa-book"></i>
            <span>Komik</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profil</span>
        </a>
    </nav>
        <script>
            function revealProfile() {
                document.documentElement.classList.remove('page-loading');
            }
            window.addEventListener('load', revealProfile);
            window.addEventListener('pageshow', revealProfile);
        </script>
    </div>
</body>
</html>
