function getPreferredTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        return savedTheme;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);

    const themeToggle = document.querySelector('.theme-toggle i');
    if (themeToggle) {
        themeToggle.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

function ensureThemeLoadingOverlay() {
    if (document.getElementById('themeToggleLoadingOverlay')) return;

    const style = document.createElement('style');
    style.id = 'themeToggleLoadingStyles';
    style.textContent = `
        #themeToggleLoadingOverlay{
            position:fixed;
            inset:0;
            display:flex;
            align-items:center;
            justify-content:center;
            background:rgba(0,0,0,.45);
            backdrop-filter:blur(6px);
            z-index:25000;
            opacity:0;
            visibility:hidden;
            transition:opacity .15s ease,visibility .15s ease;
        }
        #themeToggleLoadingOverlay.show{opacity:1;visibility:visible;}
        .theme-toggle-loading-card{
            width:90%;
            max-width:420px;
            background:var(--bg-secondary,#ffffff);
            color:var(--text-primary,#2d3748);
            border-radius:18px;
            padding:2rem;
            text-align:center;
            box-shadow:0 20px 60px rgba(0,0,0,.25);
        }
        .theme-toggle-loading-spinner{
            width:56px;
            height:56px;
            border-radius:999px;
            border:6px solid rgba(148,163,184,.35);
            border-top-color:var(--accent-color,#667eea);
            margin:0 auto 1rem;
            animation:themeToggleSpin .9s linear infinite;
        }
        .theme-toggle-loading-title{font-weight:800;font-size:1.25rem;margin-bottom:.35rem;}
        .theme-toggle-loading-sub{color:var(--text-secondary,#4a5568);font-weight:600;}
        @keyframes themeToggleSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
    `;
    document.head.appendChild(style);

    const overlay = document.createElement('div');
    overlay.id = 'themeToggleLoadingOverlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
        <div class="theme-toggle-loading-card" role="status" aria-live="polite">
            <div class="theme-toggle-loading-spinner"></div>
            <div class="theme-toggle-loading-title">Mengubah Tema...</div>
            <div class="theme-toggle-loading-sub">Tunggu sesuai koneksi internet Anda</div>
        </div>
    `;
    document.body.appendChild(overlay);
}

function showThemeLoading() {
    ensureThemeLoadingOverlay();
    const overlay = document.getElementById('themeToggleLoadingOverlay');
    if (!overlay) return;
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
}

function hideThemeLoading() {
    const overlay = document.getElementById('themeToggleLoadingOverlay');
    if (!overlay) return;
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
}

function toggleTheme(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (window.__themeToggleBusy) return;
    window.__themeToggleBusy = true;

    const start = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    const minDurationMs = 250;

    showThemeLoading();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);

            requestAnimationFrame(() => {
                const now = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
                const elapsed = now - start;
                const remaining = Math.max(0, minDurationMs - elapsed);
                setTimeout(() => {
                    hideThemeLoading();
                    window.__themeToggleBusy = false;
                }, remaining);
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setTheme(getPreferredTheme());
    
    const toggleButton = document.querySelector('.theme-toggle');
    if (toggleButton) {
        toggleButton.innerHTML = `<i class="${getPreferredTheme() === 'dark' ? 'fas fa-sun' : 'fas fa-moon'}"></i>`;
        toggleButton.addEventListener('click', toggleTheme);
    }
    
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme')) {
            setTheme(e.matches ? 'dark' : 'light');
        }
    });
});
