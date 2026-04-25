<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="register.css?v=<?php echo filemtime(__FILE__); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Register - Komik Lokal</title>
    <script>
        document.documentElement.classList.add('page-loading');
    </script>
</head>
<body>
    <div id="registerContent">
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
    </div>
    <div class="container">
        <div class="register-wrapper intro-mode" id="registerWrapper">
            <div class="register-form">
                <button type="button" class="form-back-btn" id="registerFormBackBtn" aria-label="Kembali ke ilustrasi" title="Kembali ke ilustrasi">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="form-header">
                    <div class="header-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h2>Buat Akun Baru</h2>
                    <p>Bergabunglah dan nikmati ribuan komik gratis</p>
                </div>
                <form action="register_action.php" method="POST" class="register-email">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" placeholder="Username" name="username" id="username" required autocomplete="username">
                        <label>Username</label>
                    </div>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <input type="email" placeholder="Email" name="email" id="email" required autocomplete="email">
                        <label>Email</label>
                    </div>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" placeholder="Password" name="password" id="password" required autocomplete="new-password">
                        <label>Password</label>
                        <div class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </div>
                    </div>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" placeholder="Konfirmasi Password" name="password2" id="password2" required autocomplete="new-password">
                        <label>Konfirmasi Password</label>
                        <div class="toggle-password" onclick="togglePassword('password2')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </div>
                    </div>
                    <button type="submit" name="button" class="btn-register">
                        <span>Daftar Sekarang</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <div class="divider">
                        <span>atau</span>
                    </div>
                    <div class="login-section">
                        <p>Sudah punya akun?</p>
                        <a href="../login/login.php" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            Login Sekarang
                        </a>
                    </div>
                </form>
            </div>
            <div class="register-illustration">
                <button type="button" class="illustration-next-btn" id="registerIllustrationNextBtn" aria-label="Lanjut ke form register" title="Lanjut ke form register">
                    <i class="fas fa-arrow-right"></i>
                </button>
                <div class="illustration-content">
                    <div class="logo-circle">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h1>Selamat Datang!</h1>
                    <p>Daftar sekarang dan dapatkan akses ke ribuan komik menarik secara gratis</p>
                    <div class="benefits-list">
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-infinity"></i>
                            </div>
                            <div class="benefit-text">
                                <h4>Gratis Selamanya</h4>
                                <p>Tidak ada biaya tersembunyi</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="benefit-text">
                                <h4>Ribuan Koleksi</h4>
                                <p>Komik dari berbagai genre</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="benefit-text">
                                <h4>Komunitas Aktif</h4>
                                <p>Diskusi dengan sesama pembaca</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="benefit-text">
                                <h4>Akses Dimana Saja</h4>
                                <p>Baca di semua perangkat</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
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
        .page-loading #registerContent {
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
            white-space: pre-line;
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
    <div class="loading-overlay" id="loadingOverlay" aria-hidden="true">
        <div class="loading-card" role="status" aria-live="polite">
            <div class="loading-spinner"></div>
            <div class="loading-title">Memproses pendaftaran...</div>
            <div class="loading-sub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    </div>
    <div class="custom-modal-overlay" id="confirmModal">
        <div class="custom-modal">
            <div class="modal-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
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
        (function initIntroRegisterPane() {
            const wrapper = document.getElementById('registerWrapper');
            const nextBtn = document.getElementById('registerIllustrationNextBtn');
            const backBtn = document.getElementById('registerFormBackBtn');
            const formPane = document.querySelector('.register-form');
            if (!wrapper || !nextBtn || !backBtn || !formPane) return;

            let isTurning = false;
            const turnDuration = 580;
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function syncBackButtonOffset() {
                const offset = wrapper.classList.contains('form-mode') ? formPane.scrollTop : 0;
                backBtn.style.setProperty('--back-follow-offset', offset + 'px');
            }

            function switchToForm() {
                wrapper.classList.remove('intro-mode');
                wrapper.classList.add('form-mode');
                syncBackButtonOffset();
                const usernameInput = document.querySelector('.register-form input[name="username"]');
                if (usernameInput) {
                    setTimeout(() => usernameInput.focus(), 120);
                }
            }

            function switchToIntro() {
                wrapper.classList.remove('form-mode');
                wrapper.classList.add('intro-mode');
                syncBackButtonOffset();
            }

            formPane.addEventListener('scroll', syncBackButtonOffset, { passive: true });
            syncBackButtonOffset();

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

        (function initRegisterLoading() {
            const form = document.querySelector('form.register-email');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const submitBtn = document.querySelector('.btn-register');
            function hideLoading() {
                if (document.documentElement.classList.contains('page-loading')) return;
                if (loadingOverlay) loadingOverlay.classList.remove('active');
                if (submitBtn) submitBtn.disabled = false;
            }
            document.addEventListener('DOMContentLoaded', hideLoading);
            window.addEventListener('pageshow', hideLoading);
            if (!form || !loadingOverlay || !submitBtn) return;
            form.addEventListener('submit', (e) => {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                submitBtn.disabled = true;
                loadingOverlay.classList.add('active');
            });
        })();
        (function initRegisterPageReveal() {
            function reveal() {
                document.documentElement.classList.remove('page-loading');
            }
            window.addEventListener('load', reveal);
            window.addEventListener('pageshow', reveal);
        })();
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
        function showAlert(type, title, message, redirectUrl = null) {
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
            alertCallback = redirectUrl ? () => {
                window.location.href = redirectUrl;
            } : null;
            alertModal.classList.add('active');
        }
        alertOkBtn.addEventListener('click', () => {
            alertModal.classList.remove('active');
            if (alertCallback) alertCallback();
        });
        (function handleRegisterMessage() {
            const params = new URLSearchParams(window.location.search);
            const status = params.get('status');
            const message = params.get('message');
            const title = params.get('title');
            const redirect = params.get('redirect');
            if (!status || !message) return;
            const modalType = (status === 'success' || status === 'error' || status === 'warning') ? status : 'warning';
            const modalTitle = title || (modalType === 'success' ? 'Berhasil' : (modalType === 'error' ? 'Gagal' : 'Informasi'));
            showAlert(modalType, modalTitle, message, redirect);
            try {
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            } catch (e) {
            }
        })();
        function togglePassword(inputId) {
            const password = document.getElementById(inputId);
            const iconNumber = inputId === 'password' ? '1' : '2';
            const toggleIcon = document.getElementById('toggleIcon' + iconNumber);
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
            if (input.value !== '') {
                input.parentElement.classList.add('focused');
            }
        });
        const passwordInput = document.getElementById('password');
        const password2Input = document.getElementById('password2');
        password2Input.addEventListener('input', function() {
            if (this.value !== '' && this.value !== passwordInput.value) {
                this.setCustomValidity('Password tidak cocok');
            } else {
                this.setCustomValidity('');
            }
        });
        passwordInput.addEventListener('input', function() {
            if (password2Input.value !== '') {
                if (password2Input.value !== this.value) {
                    password2Input.setCustomValidity('Password tidak cocok');
                } else {
                    password2Input.setCustomValidity('');
                }
            }
        });
    </script>
</body>
</html>
