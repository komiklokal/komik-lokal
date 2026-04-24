<?php
session_start();

include("config.php");

// 🔒 Cegah halaman tersimpan di cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 🧹 Proses Logout (jika user klik tombol logout)
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login/login.php");
    exit();
}

if (!isset($_SESSION['username'])) {
    header("Location: dashboard/dashboard.php");
    exit();
}

$username = $_SESSION['username'];
$sql = "SELECT user_nama, profile_image_blob, profile_image_type FROM user WHERE user_nama = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$userData = $userData ?: [
    'user_nama' => $username,
    'profile_image_blob' => null,
    'profile_image_type' => null,
];
$stmt->close();

if (empty($_SESSION['csrf_delete_token'])) {
    $_SESSION['csrf_delete_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun</title>
    <meta name="csrf-delete-token" content="<?= htmlspecialchars($_SESSION['csrf_delete_token'] ?? '') ?>">
    <link rel="stylesheet" href="dark-mode.css?v=<?php echo time(); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="theme.js?v=<?php echo time(); ?>"></script>

    <script>
        document.documentElement.classList.add('page-loading');
    </script>
    <style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: #333;
        line-height: 1.6;
        padding-bottom: 70px;
        margin-top: -25px;
    }

    .navbar {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 1rem;
        color: #2d3748;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .navbar-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        align-items: center;
    }

    .navbar h1 {
        margin: 0;
        font-size: 1.75rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #2d3748;
        font-weight: 600;
    }

    .navbar h1 i {
        font-size: 1.5rem;
        color: #667eea;
    }
    
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

    .page-loading #settingContent {
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

        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .settings-container h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .setting-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            background: #f9f9f9;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: 0.3s;
        }

        .setting-item:hover {
            background: #f1f1ff;
            transform: translateY(-2px);
        }

        .setting-item a {
            text-decoration: none;
            color: #2d3748;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .setting-item i {
            font-size: 1.3rem;
            color: #667eea;
        }

        .setting-item.logout {
            background: linear-gradient(45deg, #c53030, #9c2a2a);
            box-shadow: 0 6px 20px rgba(229, 62, 62, 0.4);
        }

        .setting-item.logout a {
            color: #fff;
        }

        .back-btn {
            display: inline-block;
            margin-top: 1.5rem;
            text-decoration: none;
            background: #444;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: #667eea;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content h3 {
            color: #c53030;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .modal-content p {
            color: #4a5568;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        .btn-delete {
            background: linear-gradient(45deg, #c53030, #9c2a2a);
            color: #fff;
        }

        .btn-delete:hover {
            background: linear-gradient(45deg, #9c2a2a, #7c1f1f);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 48, 48, 0.4);
        }

        [data-theme="dark"] .modal-content {
            background-color: #1e1e1e;
        }

        [data-theme="dark"] .modal-content p {
            color: #a0aec0;
        }

        [data-theme="dark"] .btn-cancel {
            background: #2d3748;
            color: #e2e8f0;
        }

        [data-theme="dark"] .btn-cancel:hover {
            background: #4a5568;
        }

        [data-theme="dark"] body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        }

        [data-theme="dark"] .settings-container {
            background: rgba(30, 30, 30, 0.95);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] .settings-container h2 {
            color: #e2e8f0;
        }

        [data-theme="dark"] .setting-item {
            background: #2d2d2d;
            border: 1px solid #3d3d3d;
        }

        [data-theme="dark"] .setting-item:hover {
            background: #3d3d3d;
        }

        [data-theme="dark"] .setting-item a {
            color: #e2e8f0;
        }

        [data-theme="dark"] .setting-item i {
            color: #7f9cf5;
        }

        [data-theme="dark"] .back-btn {
            background: #2d3748;
            color: #e2e8f0;
        }

        [data-theme="dark"] .back-btn:hover {
            background: #7f9cf5;
        }

        [data-theme="dark"] .navbar {
            background: rgba(30, 30, 30, 0.95);
        }

        [data-theme="dark"] .navbar h1 {
            color: #e2e8f0;
        }

        [data-theme="dark"] .navbar h1 i {
            color: #7f9cf5;
        }

        [data-theme="dark"] .setting-item[style*="background:#ffe5e5"] {
            background: #3d1f1f !important;
        }

        [data-theme="dark"] .setting-item[style*="background:#ffe5e5"] a {
            color: #ff6b6b !important;
        }
    </style>
</head>
<body>

    <div id="loadingOverlay" aria-hidden="true">
        <div class="loading-card" role="status" aria-live="polite">
            <div class="loading-spinner"></div>
            <div class="loading-title" id="pageLoadingTitle">Memuat Setting...</div>
            <div class="loading-sub" id="pageLoadingSub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>

    <div id="settingContent">

    <div class="navbar">
        <div class="navbar-container">
            <h1><i class="fas fa-book-open"></i> Setting</h1>
        </div>
    </div>

<div class="settings-container">
    <h2>⚙️ Pilih Pengaturan</h2>

    <div class="setting-item">
        <a href="feedback/feedback.php"><i class="fas fa-comment-dots"></i> Feedback & Keluhan</a>
    </div>

    <div class="setting-item">
        <a href="bahasa/bahasa.php"><i class="fas fa-language"></i> Bahasa</a>
    </div>

    <div class="setting-item" style="background:#ffe5e5;">
        <a href="#" id="deleteAccountBtn" style="color:#c53030;"><i class="fas fa-trash-alt"></i> Hapus Akun</a>
    </div>

    <div class="setting-item logout">
        <a href="setting.php?logout=true" id="logoutLink"><i class="fas fa-sign-out-alt"></i> Log Out</a>
    </div>

    <a href="profile/profile.php" class="back-btn">⬅️ Kembali ke Profil</a>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Hapus Akun</h3>
        <p>Apakah Anda yakin ingin menghapus akun Anda?<br>
        <strong>Tindakan ini tidak dapat dibatalkan!</strong><br>
        Semua data, komik, dan chapter yang Anda buat akan dihapus permanen.</p>
        <div class="modal-buttons">
            <button class="modal-btn btn-cancel" id="cancelBtn">
                <i class="fas fa-times"></i> Batal
            </button>
            <button class="modal-btn btn-delete" id="confirmDeleteBtn">
                <i class="fas fa-trash-alt"></i> Hapus Akun
            </button>
        </div>
    </div>
</div>

<div id="logoutModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-sign-out-alt"></i> Log Out</h3>
        <p>Apakah Anda ingin keluar?</p>
        <div class="modal-buttons">
            <button class="modal-btn btn-cancel" id="logoutCancelBtn">
                <i class="fas fa-times"></i> Batal
            </button>
            <button class="modal-btn btn-delete" id="logoutConfirmBtn">
                <i class="fas fa-check"></i> Ya
            </button>
        </div>
    </div>
</div>

<div id="statusModal" class="modal">
    <div class="modal-content">
        <h3 id="statusTitle">Informasi</h3>
        <p id="statusMessage">Pesan</p>
        <div class="modal-buttons">
            <button class="modal-btn btn-cancel" id="statusOkBtn">
                <i class="fas fa-check"></i> Mengerti
            </button>
        </div>
    </div>
</div>

<script>
    function revealSettingPage() {
        document.documentElement.classList.remove('page-loading');
    }

    window.addEventListener('load', revealSettingPage);
    window.addEventListener('pageshow', revealSettingPage);

    window.addEventListener("pageshow", function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    const deleteModal = document.getElementById('deleteModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    const logoutLink = document.getElementById('logoutLink');
    const logoutModal = document.getElementById('logoutModal');
    const logoutCancelBtn = document.getElementById('logoutCancelBtn');
    const logoutConfirmBtn = document.getElementById('logoutConfirmBtn');

    const statusModal = document.getElementById('statusModal');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const statusOkBtn = document.getElementById('statusOkBtn');

    function showPageLoading(title, subTitle) {
        try {
            const t = document.getElementById('pageLoadingTitle');
            const s = document.getElementById('pageLoadingSub');
            if (t && title) t.textContent = title;
            if (s && subTitle) s.textContent = subTitle;
        } catch (e) {
        }
        document.documentElement.classList.add('page-loading');
    }

    function hidePageLoading() {
        document.documentElement.classList.remove('page-loading');
    }

    function showStatusModal(title, message) {
        if (!statusModal || !statusTitle || !statusMessage) return;
        statusTitle.textContent = title || 'Informasi';
        statusMessage.textContent = message || '';
        statusModal.style.display = 'block';
    }

    deleteAccountBtn.addEventListener('click', function(e) {
        e.preventDefault();
        deleteModal.style.display = 'block';
    });

    cancelBtn.addEventListener('click', function() {
        deleteModal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
        }

        if (event.target === logoutModal) {
            logoutModal.style.display = 'none';
        }

        if (event.target === statusModal) {
            statusModal.style.display = 'none';
        }
    });

    if (statusOkBtn && statusModal) {
        statusOkBtn.addEventListener('click', function () {
            statusModal.style.display = 'none';
        });
    }

    if (logoutLink && logoutModal && logoutCancelBtn && logoutConfirmBtn) {
        logoutLink.addEventListener('click', function (e) {
            e.preventDefault();
            logoutModal.style.display = 'block';
        });

        logoutCancelBtn.addEventListener('click', function () {
            logoutModal.style.display = 'none';
        });

        logoutConfirmBtn.addEventListener('click', function () {
            showPageLoading('Keluar...', 'Tunggu sesuai koneksi internet Anda');
            window.location.href = logoutLink.getAttribute('href');
        });
    }

    confirmDeleteBtn.addEventListener('click', function() {
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';

        showPageLoading('Menghapus Akun...', 'Tunggu sesuai koneksi internet Anda');

        fetch('hapus_akun_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-delete-token"]').getAttribute('content'))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'login/login.php';
            } else {
                hidePageLoading();
                showStatusModal('Gagal', data.message || 'Gagal menghapus akun.');
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus Akun';
                deleteModal.style.display = 'none';
            }
        })
        .catch(error => {
            hidePageLoading();
            showStatusModal('Gagal', 'Terjadi kesalahan jaringan saat menghapus akun.');
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus Akun';
            deleteModal.style.display = 'none';
        });
    });
</script>

    </div>

</body>
</html>
