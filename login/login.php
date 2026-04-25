<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <link rel="stylesheet" href="login.css?v=<?php echo filemtime(__FILE__); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" media="all">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <title>Login - Komik Lokal</title>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
    <style>
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .page-loading .loading-overlay {
            opacity: 1;
            visibility: visible;
        }
        .page-loading #loginContent {
            visibility: hidden;
        }
        .loading-card {
            width: 90%;
            max-width: 420px;
            background: #fff;
            border-radius: 18px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }
        .loading-spinner {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            border: 6px solid #e2e8f0;
            border-top-color: #667eea;
            margin: 0 auto 1rem;
            animation: spin 0.9s linear infinite;
        }
        .loading-title {
            font-weight: 800;
            font-size: 1.25rem;
            color: #2d3748;
            margin-bottom: 0.35rem;
        }
        .loading-sub {
            color: #4a5568;
            font-weight: 600;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $hasError = false;
    $errorType = '';
    $errorMessage = '';
    $hasSuccess = false;
    $successMessage = '';
    if (isset($_SESSION['login_error'])) {
        $hasError = true;
        $errorType = $_SESSION['login_error'];
        $errorMessage = $_SESSION['login_error_message'];
        unset($_SESSION['login_error']);
        unset($_SESSION['login_error_message']);
    }
    if (isset($_SESSION['reset_success'])) {
        $hasSuccess = true;
        $successMessage = $_SESSION['reset_success_message'];
        unset($_SESSION['reset_success']);
        unset($_SESSION['reset_success_message']);
    }
    ?>
    <div id="loginContent">
    <div class="background">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="bubble bubble-1"></div>
        <div class="bubble bubble-2"></div>
        <div class="bubble bubble-3"></div>
        <div class="bubble bubble-4"></div>
        <div class="bubble bubble-5"></div>
    </div>
    <div class="container">
        <div class="login-wrapper intro-mode" id="loginWrapper">
            <div class="login-illustration">
                <div class="illustration-content">
                    <div class="logo-circle">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h1>Welcome Back!</h1>
                    <p>Masuk untuk melanjutkan petualangan membaca komik favoritmu</p>
                    <div class="feature-list">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Ribuan koleksi komik</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Baca dimana saja</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="illustration-next-btn" id="illustrationNextBtn" aria-label="Lanjut ke form login" title="Lanjut ke form login">
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
            <div class="login-form">
                <button type="button" class="form-back-btn" id="formBackBtn" aria-label="Kembali ke ilustrasi" title="Kembali ke ilustrasi">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="form-header">
                    <h2>Login</h2>
                    <p>Silakan masuk ke akun Anda</p>
                </div>
                <form action="login_action.php" method="POST" class="login-email">
                    <input type="hidden" name="redirect" value="../dashboard/dashboard.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" placeholder="Username" name="username" required autocomplete="username">
                        <label>Username</label>
                    </div>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" placeholder="Password" name="password" id="password" required autocomplete="current-password">
                        <label>Password</label>
                        <div class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </div>
                    </div>
                    <div class="form-options">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember">
                            <span class="checkmark"></span>
                            <span class="checkbox-label">Ingat saya</span>
                        </label>
                        <a href="#" class="forgot-password" onclick="openResetModal(event)">Lupa password?</a>
                    </div>
                    <button type="submit" name="submit" class="btn-login">
                        <span>Login</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <div class="divider">
                        <span>atau</span>
                    </div>
                    <div class="register-section">
                        <p>Belum punya akun?</p>
                        <a href="../register/register.php" class="btn-register">
                            <i class="fas fa-user-plus"></i>
                            Daftar Sekarang
                        </a>
                    </div>
                    <div class="info-notice">
                        <button type="button" class="info-btn" onclick="showInfo()">
                            <i class="fas fa-info-circle"></i>
                            <span>Pemberitahuan Penting</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
    <div class="modal-overlay" id="modalOverlay" onclick="closeModal()">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <h3>Pemberitahuan Penting</h3>
                <div class="modal-content">
                    <div class="notice-item">
                        <i class="fas fa-book-reader"></i>
                        <p><strong>Pengguna Baru:</strong> Silakan kunjungi <strong>Buku Petunjuk</strong> yang ada di menu Profile (ikon tiga garis) untuk panduan lengkap.</p>
                    </div>
                    <div class="notice-item notice-feedback">
                        <i class="fas fa-comments"></i>
                        <p><strong>Feedback:</strong> Jika Anda mempunyai masalah atau pertanyaan, silakan pergi ke <strong>Setting</strong> dan buka menu <strong>Feedback</strong><strong>Feedback di website ini berbeda!</strong> Kami menyediakan grup pembahasan tentang berbagai topik terkait Komik Lokal.</p>
                    </div>
                    <div class="group-list">
                        <h4>Tiga Grup Sementara Yang Tersedia:</h4>
                        <div class="notice-item group-item">
                            <i class="fas fa-bug"></i>
                            <div>
                                <p><strong>1. Grup Bugs</strong></p>
                                <p class="group-desc">Pembahasan tentang bugs pada website Komik Lokal. Anda bisa mengirim pesan dan gambar, kedepannya juga bisa mengirim video.</p>
                            </div>
                        </div>
                        <div class="notice-item group-item">
                            <i class="fas fa-lightbulb"></i>
                            <div>
                                <p><strong>2. Grup Inspirasi</strong></p>
                                <p class="group-desc">Sampaikan keinginan Anda untuk menambah fitur yang menurut Anda diperlukan. Kedepannya bisa mengirim template atau framework.</p>
                            </div>
                        </div>
                        <div class="notice-item group-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <p><strong>3. Grup Umum</strong></p>
                                <p class="group-desc">Pembahasan bebas tentang apa saja, seperti komik yang sedang viral atau topik lainnya.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-ok" onclick="closeModal()">
                    <i class="fas fa-check"></i>
                    Mengerti
                </button>
            </div>
        </div>
    </div>
    <div class="modal-overlay error-modal" id="errorModal" onclick="closeErrorModal()">
        <div class="modal-container error-container" onclick="event.stopPropagation()">
            <div class="modal-header error-header">
                <div class="modal-icon error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <button class="modal-close" onclick="closeErrorModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <h3 id="errorTitle">Kesalahan Login</h3>
                <div class="modal-content">
                    <div class="notice-item error-message">
                        <i class="fas fa-info-circle"></i>
                        <p id="errorText"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="errorFooter">
                <button class="btn-modal-ok" onclick="closeErrorModal()">
                    <i class="fas fa-redo"></i>
                    Coba Lagi
                </button>
            </div>
        </div>
    </div>
    <div class="modal-overlay success-modal" id="successModal" onclick="closeSuccessModal()">
        <div class="modal-container success-container" onclick="event.stopPropagation()">
            <div class="modal-header success-header">
                <div class="modal-icon success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <button class="modal-close" onclick="closeSuccessModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <h3>Berhasil!</h3>
                <div class="modal-content">
                    <div class="notice-item success-message">
                        <i class="fas fa-info-circle"></i>
                        <p id="successText"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-ok success-btn" onclick="closeSuccessModal()">
                    <i class="fas fa-arrow-right"></i>
                    Lanjut
                </button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="resetModal" onclick="closeResetModal()">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-key"></i>
                </div>
                <button class="modal-close" onclick="closeResetModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                	<h3>Reset Password</h3>
                <p style="margin-bottom: 20px; color: #666;">Masukkan username dan email terdaftar Anda</p>
                <div class="modal-content">
                    <form id="resetPasswordForm" onsubmit="handleResetPassword(event)">
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" placeholder="Username" id="resetUsername" name="reset_username" required>
                            <label>Username</label>
                        </div>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <input type="email" placeholder="Email Terdaftar" id="resetEmail" name="reset_email" required>
                            <label>Email Terdaftar</label>
                        </div>
                        <div id="newPasswordSection" style="display: none;">
                            <div class="input-group">
                                <div class="input-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <input type="password" placeholder="Password Baru" id="resetNewPassword" name="reset_new_password">
                                <label>Password Baru</label>
                                <div class="toggle-password" onclick="toggleResetPassword('resetNewPassword', 'resetToggleIcon1')">
                                    <i class="fas fa-eye" id="resetToggleIcon1"></i>
                                </div>
                            </div>
                            <div class="input-group">
                                <div class="input-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <input type="password" placeholder="Konfirmasi Password" id="resetConfirmPassword" name="reset_confirm_password">
                                <label>Konfirmasi Password</label>
                                <div class="toggle-password" onclick="toggleResetPassword('resetConfirmPassword', 'resetToggleIcon2')">
                                    <i class="fas fa-eye" id="resetToggleIcon2"></i>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-cancel" onclick="closeResetModal()" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; margin-right: 10px;">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button class="btn-modal-ok" id="resetSubmitBtn" onclick="submitResetPassword()">
                    <i class="fas fa-arrow-right"></i> Lanjutkan
                </button>
            </div>
        </div>
    </div>
    <div class="loading-overlay" id="loadingOverlay" aria-hidden="true">
        <div class="loading-card" role="status" aria-live="polite">
            <div class="loading-spinner"></div>
            <div class="loading-title">Memproses login...</div>
            <div class="loading-sub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>
    <script>
        (function initIntroLoginPane() {
            const wrapper = document.getElementById('loginWrapper');
            const nextBtn = document.getElementById('illustrationNextBtn');
            const backBtn = document.getElementById('formBackBtn');
            if (!wrapper || !nextBtn || !backBtn) return;

            let isTurning = false;
            const turnDuration = 580;
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function switchToForm() {
                wrapper.classList.remove('intro-mode');
                wrapper.classList.add('form-mode');
                const usernameInput = document.querySelector('.login-form input[name="username"]');
                if (usernameInput) {
                    setTimeout(() => usernameInput.focus(), 120);
                }
            }

            function switchToIntro() {
                wrapper.classList.remove('form-mode');
                wrapper.classList.add('intro-mode');
            }

            nextBtn.addEventListener('click', function() {
                if (isTurning || !wrapper.classList.contains('intro-mode')) {
                    return;
                }

                if (prefersReducedMotion) {
                    switchToForm();
                    return;
                }

                isTurning = true;
                wrapper.classList.add('turning-to-form');
                setTimeout(() => {
                    wrapper.classList.remove('turning-to-form');
                    switchToForm();
                    isTurning = false;
                }, turnDuration);
            });

            backBtn.addEventListener('click', function() {
                if (isTurning || !wrapper.classList.contains('form-mode')) {
                    return;
                }

                if (prefersReducedMotion) {
                    switchToIntro();
                    return;
                }

                isTurning = true;
                wrapper.classList.add('turning-to-intro');
                setTimeout(() => {
                    wrapper.classList.remove('turning-to-intro');
                    switchToIntro();
                    isTurning = false;
                }, turnDuration);
            });
        })();

        (function initLoginLoading() {
            const form = document.querySelector('form.login-email');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const submitBtn = document.querySelector('.btn-login');
            function hideLoading() {
                if (document.documentElement.classList.contains('page-loading')) return;
                if (loadingOverlay) loadingOverlay.classList.remove('active');
                if (submitBtn) submitBtn.disabled = false;
            }
            document.addEventListener('DOMContentLoaded', hideLoading);
            window.addEventListener('pageshow', hideLoading);
            if (!form || !loadingOverlay || !submitBtn) return;
            form.addEventListener('submit', () => {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                submitBtn.disabled = true;
                loadingOverlay.classList.add('active');
            });
        })();
        (function initLoginPageReveal() {
            function reveal() {
                document.documentElement.classList.remove('page-loading');
            }
            window.addEventListener('load', reveal);
            window.addEventListener('pageshow', reveal);
        })();
        function togglePassword() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        function showInfo() {
            const modal = document.getElementById('modalOverlay');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            const modal = document.getElementById('modalOverlay');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        function closeErrorModal() {
            const modal = document.getElementById('errorModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        function showSuccessModal(message) {
            const modal = document.getElementById('successModal');
            const successText = document.getElementById('successText');
            successText.textContent = message;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        let resetStep = 1;
        let foundUsername = null;
        function openResetModal(e) {
            e.preventDefault();
            const modal = document.getElementById('resetModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            resetStep = 1;
            foundUsername = null;
            document.getElementById('newPasswordSection').style.display = 'none';
            document.getElementById('resetPasswordForm').reset();
            if (document.getElementById('resetEmail')) {
                document.getElementById('resetEmail').disabled = false;
            }
        }
        function closeResetModal() {
            const modal = document.getElementById('resetModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
            resetStep = 1;
            foundUsername = null;
            document.getElementById('newPasswordSection').style.display = 'none';
            document.getElementById('resetPasswordForm').reset();
            if (document.getElementById('resetEmail')) {
                document.getElementById('resetEmail').disabled = false;
            }
        }
        function toggleResetPassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        function handleResetPassword(e) {
            e.preventDefault();
            submitResetPassword();
        }
        function submitResetPassword() {
            const username = document.getElementById('resetUsername').value.trim();
            const email    = document.getElementById('resetEmail') ? document.getElementById('resetEmail').value.trim() : '';
            const submitBtn = document.getElementById('resetSubmitBtn');
            if (resetStep === 1) {
                if (!username || !email) {
                    alert('Silakan masukkan username dan email Anda');
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memverifikasi...';
                const formData = new FormData();
                formData.append('action', 'verify_username');
                formData.append('username', username);
                formData.append('email', email);
                fetch('../forgot_password_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        foundUsername = username;
                        resetStep = 2;
                        document.getElementById('newPasswordSection').style.display = 'block';
                        document.getElementById('resetUsername').disabled = true;
                        if (document.getElementById('resetEmail')) {
                            document.getElementById('resetEmail').disabled = true;
                        }
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> Reset Password';
                        submitBtn.disabled = false;
                        document.getElementById('resetNewPassword').focus();
                    } else {
                        alert(data.message || 'Username atau email tidak cocok');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Lanjutkan';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Lanjutkan';
                });
            } else if (resetStep === 2) {
                const newPassword = document.getElementById('resetNewPassword').value.trim();
                const confirmPassword = document.getElementById('resetConfirmPassword').value.trim();
                if (!newPassword || !confirmPassword) {
                    alert('Silakan masukkan password baru');
                    return;
                }
                if (newPassword !== confirmPassword) {
                    alert('Password tidak cocok');
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                const formData = new FormData();
                formData.append('action', 'update_password');
                formData.append('username', foundUsername);
                formData.append('new_password', newPassword);
                fetch('../forgot_password_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeResetModal();
                        showSuccessModal('Password berhasil direset! Silakan login dengan password baru.');
                    } else {
                        alert(data.message || 'Gagal mereset password');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> Reset Password';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Reset Password';
                });
            }
        }
        function showErrorModal(errorType, errorMessage) {
            const modal = document.getElementById('errorModal');
            const errorText = document.getElementById('errorText');
            const errorFooter = document.getElementById('errorFooter');
            const errorIcon = document.querySelector('.error-icon i');
            errorText.textContent = errorMessage;
            if (errorType === 'not_registered') {
                errorIcon.className = 'fas fa-user-slash';
                errorFooter.innerHTML = `
                    <button class="btn-modal-ok btn-register-error" onclick="window.location.href='register.php'">
                        <i class="fas fa-user-plus"></i>
                        Daftar Sekarang
                    </button>
                    <button class="btn-modal-cancel" onclick="closeErrorModal()">
                        <i class="fas fa-times"></i>
                        Tutup
                    </button>
                `;
            } else if (errorType === 'password_wrong') {
                errorIcon.className = 'fas fa-lock';
            } else {
                errorIcon.className = 'fas fa-exclamation-circle';
            }
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeErrorModal();
                closeSuccessModal();
                closeResetModal();
            }
        });
        const inputs = document.querySelectorAll('.input-group input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
        <?php if ($hasError): ?>
        window.addEventListener('DOMContentLoaded', function() {
            showErrorModal(<?= json_encode($errorType) ?>, <?= json_encode($errorMessage) ?>);
        });
        <?php endif; ?>
        <?php if ($hasSuccess): ?>
        window.addEventListener('DOMContentLoaded', function() {
            showSuccessModal(<?= json_encode($successMessage) ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
