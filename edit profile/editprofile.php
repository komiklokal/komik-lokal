<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include("config.php");
if (!isset($_SESSION['username'])) {
    header("Location: ../dashboard/dashboard.php");
    exit();
}
$username = $_SESSION['username'];
$userData = null;
if (!(function_exists('is_guest') && is_guest())) {
    $sql = "SELECT * FROM user WHERE user_nama = ?";
    $stmt = $conn->prepare($sql);
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
$successMessage = "";
$errorMessage = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('is_guest') && is_guest()) {
        $newUsername = trim($_POST['username'] ?? '');
        if ($newUsername !== '') {
            $_SESSION['username'] = $username = $newUsername;
        }
        header('Location: editprofile.php?status=warning&title=' . urlencode('Informasi') . '&message=' . urlencode('Guest mode: perubahan hanya tersimpan di sesi (tidak ke database).') . '&redirect=' . urlencode('../profile/profile.php'));
        exit();
    }
    $newUsername = trim($_POST['username'] ?? '');
    $profileImage = $_FILES['profile_image'] ?? null;
    if ($newUsername !== '' && $newUsername !== $username) {
        if (empty($newUsername)) {
            $errorMessage .= ($errorMessage ? ' ' : '') . "Username tidak boleh kosong.";
        } else {
            $checkQuery = "SELECT * FROM user WHERE user_nama = ? AND user_nama != ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ss", $newUsername, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errorMessage .= ($errorMessage ? ' ' : '') . "Username sudah digunakan. Gunakan username lain.";
            } else {
                $oldUsername = $username;
                $updateQuery = "UPDATE user SET user_nama = ? WHERE user_nama = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ss", $newUsername, $oldUsername);
                if ($stmt->execute()) {
                    $successMessage .= ($successMessage ? ' ' : '') . "Username berhasil diperbarui.";
                    $_SESSION['username'] = $username = $newUsername;
                    $update_pengarang = "UPDATE komik SET pengarang = ? WHERE pengarang = ?";
                    $stmt_pengarang = $conn->prepare($update_pengarang);
                    $stmt_pengarang->bind_param("ss", $newUsername, $oldUsername);
                    if (!$stmt_pengarang->execute()) {
                        $errorMessage .= ($errorMessage ? ' ' : '') . "Gagal memperbarui nama pengarang di komik.";
                    }
                    $stmt_pengarang->close();
                } else {
                    $errorMessage .= ($errorMessage ? ' ' : '') . "Gagal memperbarui username.";
                }
                $stmt->close();
            }
        }
    }
    if (isset($profileImage) && isset($profileImage['error']) && $profileImage['error'] === UPLOAD_ERR_OK) {
        $maxProfileImageBytes = 500 * 1024;
        $profileSize = (int)($profileImage['size'] ?? 0);
        if ($profileSize > $maxProfileImageBytes) {
            $errorMessage .= ($errorMessage ? ' ' : '') . "Ukuran gambar terlalu besar. Maksimal 500KB.";
        } else {
            $tmp = (string)($profileImage['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                $errorMessage .= ($errorMessage ? ' ' : '') . "Upload gambar tidak valid.";
            } else {
                $fileType = @mime_content_type($tmp);
                if (!$fileType || strpos($fileType, 'image/') !== 0) {
                    $errorMessage .= ($errorMessage ? ' ' : '') . "Format gambar tidak didukung.";
                } else {
                    $fileData = file_get_contents($tmp);
                    if ($fileData === false || $fileData === '') {
                        $errorMessage .= ($errorMessage ? ' ' : '') . "Gagal membaca file gambar.";
                    } else {
                        $updateBlobQuery = "UPDATE user SET profile_image_blob = ?, profile_image_type = ? WHERE user_nama = ?";
                        $stmt = $conn->prepare($updateBlobQuery);
                        $null = NULL;
                        $stmt->bind_param("bss", $null, $fileType, $username);
                        $stmt->send_long_data(0, $fileData);
                        if ($stmt->execute()) {
                            $successMessage .= ($successMessage ? ' ' : '') . "Gambar profil berhasil diperbarui.";
                        } else {
                            $errorMessage .= ($errorMessage ? ' ' : '') . "Gagal memperbarui gambar profil.";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
    if (!empty($successMessage)) {
        header('Location: editprofile.php?status=success&title=' . urlencode('Berhasil') . '&message=' . urlencode($successMessage) . '&redirect=' . urlencode('../profile/profile.php'));
    } elseif (!empty($errorMessage)) {
        header('Location: editprofile.php?status=error&title=' . urlencode('Gagal') . '&message=' . urlencode($errorMessage));
    } else {
        header('Location: editprofile.php?status=warning&title=' . urlencode('Informasi') . '&message=' . urlencode('Tidak ada perubahan yang disimpan.'));
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile - Komik Lokal</title>
  <link rel="stylesheet" href="editprofile.css">
  <link rel="stylesheet" href="../dark-mode.css?v=<?php echo time(); ?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <script src="../theme.js?v=<?php echo time(); ?>"></script>
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
        .page-loading #editProfileContent {
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
            white-space: pre-line;
        }
        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .modal-button {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            min-width: 120px;
        }
        .modal-button.primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        .modal-button.secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        .modal-button.danger {
            background: linear-gradient(45deg, #e53e3e, #c53030);
            color: white;
        }
        .modal-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        @keyframes pulse-warning {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
    </style>
</head>
<body>
        <div id="loadingOverlay" aria-hidden="true">
                <div class="loading-card" role="status" aria-live="polite">
                        <div class="loading-spinner"></div>
                <div class="loading-title" id="loadingTitle">Memuat Edit Profile...</div>
                <div class="loading-sub" id="loadingSub">Tunggu sesuai koneksi internet Anda</div>
                </div>
        </div>
        <div id="editProfileContent">
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <i class="fas fa-book-open"></i>
                <h1>Edit Profile</h1>
            </div>
        </div>
    </div>
    <div class="main-container">
        <div class="profile-card">
            <div class="card-header">
                <i class="fas fa-user-edit"></i>
                <h2>Edit Profile Anda</h2>
                <p>Kelola informasi akun Anda</p>
            </div>
            <form method="POST" enctype="multipart/form-data" class="profile-form">
                <div class="image-upload-section">
                    <div class="current-image">
                        <?php if (!empty($userData['profile_image_blob'])): ?>
                            <img id="current-profile" 
                                 src="../getImage.php?username=<?= urlencode($userData['user_nama']); ?>&v=<?= time(); ?>" 
                                 alt="Current Profile" 
                                 class="profile-avatar"
                                   onerror="this.onerror=null; this.style.display='none'; this.insertAdjacentHTML('beforebegin','<div class=&quot;default-avatar&quot;><i class=&quot;fas fa-user&quot;></i></div>');">
                        <?php else: ?>
                            <div class="default-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="avatar-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div class="upload-controls">
                        <label for="profile_image" class="upload-btn">
                            <i class="fas fa-upload"></i>
                            Pilih Gambar Baru
                        </label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*" hidden>
                        <div class="upload-hint">JPG / PNG / GIF (maks. 500KB)</div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($userData['user_nama']); ?>" required>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="btn-cancel" onclick="window.location.href='../profile/profile.php'">
                        <i class="fas fa-arrow-left"></i> Batal
                        </button>
                        <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                    </form>
                </div>
                </div>
        <div class="custom-modal-overlay" id="confirmModal">
            <div class="custom-modal">
                <div class="modal-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="modal-title" id="confirmTitle">Konfirmasi</div>
                <div class="modal-message" id="confirmMessage">Apakah Anda yakin?</div>
                <div class="modal-actions">
                    <button class="modal-button secondary" id="confirmCancel">Batal</button>
                    <button class="modal-button danger" id="confirmOk">Ya</button>
                </div>
            </div>
        </div>
        <div class="custom-modal-overlay" id="alertModal">
            <div class="custom-modal">
                <div class="modal-icon" id="alertIcon"><i class="fas fa-check-circle"></i></div>
                <div class="modal-title" id="alertTitle">Informasi</div>
                <div class="modal-message" id="alertMessage">Pesan informasi disini.</div>
                <div class="modal-actions">
                    <button class="modal-button primary" id="alertOk">Mengerti</button>
                </div>
            </div>
        </div>
        <script>
            const confirmModal = document.getElementById('confirmModal');
            const confirmTitle = document.getElementById('confirmTitle');
            const confirmMessage = document.getElementById('confirmMessage');
            const confirmCancel = document.getElementById('confirmCancel');
            const confirmOk = document.getElementById('confirmOk');
            const alertModal = document.getElementById('alertModal');
            const alertIcon = document.getElementById('alertIcon');
            const alertTitle = document.getElementById('alertTitle');
            const alertMessage = document.getElementById('alertMessage');
            const alertOk = document.getElementById('alertOk');
            let confirmCallback = null;
            function showConfirm({ title = 'Konfirmasi', message = 'Apakah Anda yakin?', okText = 'Ya', cancelText = 'Batal', onConfirm }) {
                confirmTitle.textContent = title;
                confirmMessage.textContent = message;
                confirmOk.textContent = okText;
                confirmCancel.textContent = cancelText;
                confirmCallback = typeof onConfirm === 'function' ? onConfirm : null;
                confirmModal.classList.add('active');
            }
            function closeConfirm() {
                confirmModal.classList.remove('active');
                confirmCallback = null;
            }
            confirmCancel.addEventListener('click', closeConfirm);
            confirmOk.addEventListener('click', function() {
                if (confirmCallback) confirmCallback();
                closeConfirm();
            });
            confirmModal.addEventListener('click', function(e) {
                if (e.target === confirmModal) closeConfirm();
            });
            let alertRedirectUrl = null;
            function showAlert(type, title, message, reload = false, redirectUrl = null) {
                alertTitle.textContent = title;
                alertMessage.textContent = message;
                alertRedirectUrl = redirectUrl;
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
                alertModal.classList.add('active');
                alertOk.onclick = function() {
                    alertModal.classList.remove('active');
                    if (alertRedirectUrl) {
                        window.location.href = alertRedirectUrl;
                        return;
                    }
                    if (reload) window.location.reload();
                };
            }
            document.addEventListener('DOMContentLoaded', function() {
                const params = new URLSearchParams(window.location.search);
                const status = params.get('status');
                const title = params.get('title');
                const message = params.get('message');
                const redirect = params.get('redirect');
                if (status && message) {
                    const modalType = (status === 'success' || status === 'error' || status === 'warning') ? status : 'warning';
                    const modalTitle = title || (modalType === 'success' ? 'Berhasil' : (modalType === 'error' ? 'Gagal' : 'Informasi'));
                    showAlert(modalType, modalTitle, message, false, redirect);
                    params.delete('status');
                    params.delete('title');
                    params.delete('message');
                    params.delete('redirect');
                    const newQuery = params.toString();
                    const newUrl = window.location.pathname + (newQuery ? ('?' + newQuery) : '');
                    window.history.replaceState({}, document.title, newUrl);
                }
            });
        </script>
    <script>
        (function initAvatarPicker() {
            const currentImage = document.querySelector('.current-image');
            const fileInput = document.getElementById('profile_image');
            if (!currentImage || !fileInput) return;

            function applyFileToInputAndPreview(file) {
                if (!file) return;
                if (file.size > 500 * 1024) {
                    showAlert('warning', 'Tidak Valid', 'Ukuran file terlalu besar! Maksimal 500KB.');
                    fileInput.value = '';
                    return;
                }
                if (!file.type || !file.type.startsWith('image/')) {
                    showAlert('warning', 'Tidak Valid', 'Format file tidak didukung! Gunakan JPG, PNG, atau GIF.');
                    fileInput.value = '';
                    return;
                }

                try {
                    if (typeof DataTransfer !== 'undefined') {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        fileInput.files = dt.files;
                    }
                } catch (e) {
                }

                const reader = new FileReader();
                reader.onload = function (ev) {
                    currentImage.innerHTML = `
                        <img src="${ev.target.result}" alt="Preview Image" class="profile-avatar">
                        <div class="avatar-overlay">
                            <i class="fas fa-times" onclick="resetImage()"></i>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }

            currentImage.addEventListener('click', function () {
                fileInput.click();
            });

            fileInput.addEventListener('change', function (e) {
                const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                applyFileToInputAndPreview(file);
            });

            function prevent(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(function (evt) {
                currentImage.addEventListener(evt, function (e) {
                    prevent(e);
                    currentImage.classList.add('drag-over');
                });
            });

            ['dragleave', 'dragend', 'drop'].forEach(function (evt) {
                currentImage.addEventListener(evt, function (e) {
                    prevent(e);
                    currentImage.classList.remove('drag-over');
                });
            });

            currentImage.addEventListener('drop', function (e) {
                const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0] ? e.dataTransfer.files[0] : null;
                if (!file) return;
                applyFileToInputAndPreview(file);
            });
        })();
        function resetImage() {
            const currentImage = document.querySelector('.current-image');
            const fileInput = document.getElementById('profile_image');
            fileInput.value = '';
            currentImage.innerHTML = `
                <?php if (!empty($userData['profile_image_blob'])): ?>
                    <img id="current-profile" 
                         src="../getImage.php?username=<?= urlencode($userData['user_nama']); ?>&v=<?= time(); ?>" 
                         alt="Current Profile" 
                         class="profile-avatar">
                <?php else: ?>
                    <div class="default-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <div class="avatar-overlay">
                    <i class="fas fa-camera"></i>
                </div>
            `;
        }
        document.querySelector('.profile-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            if (username.length < 3) {
                e.preventDefault();
                showAlert('warning', 'Tidak Valid', 'Username minimal 3 karakter!');
                return;
            }
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                e.preventDefault();
                showAlert('warning', 'Tidak Valid', 'Username hanya boleh menggunakan huruf, angka, dan underscore!');
                return;
            }
            const loadingTitle = document.getElementById('loadingTitle');
            const loadingSub = document.getElementById('loadingSub');
            if (loadingTitle) loadingTitle.textContent = 'Menyimpan Perubahan...';
            if (loadingSub) loadingSub.textContent = 'Tunggu sesuai koneksi internet Anda';
            document.documentElement.classList.add('page-loading');
            const submitBtn = this.querySelector('.btn-save');
            const cancelBtn = this.querySelector('.btn-cancel');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            submitBtn.disabled = true;
            cancelBtn.disabled = true;
            setTimeout(() => {
            }, 100);
        });
        document.addEventListener('DOMContentLoaded', function() {
            const profileCard = document.querySelector('.profile-card');
            setTimeout(() => {
                profileCard.style.opacity = '1';
                profileCard.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
    <script>
        function revealEditProfile() {
            document.documentElement.classList.remove('page-loading');
        }
        window.addEventListener('load', revealEditProfile);
        window.addEventListener('pageshow', revealEditProfile);
    </script>
    </div>
</body>
</html>
