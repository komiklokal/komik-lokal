document.addEventListener('DOMContentLoaded', () => {
    const listEl = document.getElementById('feedbackList');
    const tpl = document.getElementById('feedbackTemplate');
    const form = document.getElementById('feedbackForm');
    const komentarInput = document.getElementById('komentarHidden');
    const komentarEditor = document.getElementById('komentarEditor');
    const editorToolbar = document.querySelectorAll('.editor-toolbar .tool');
    const attachmentInput = document.getElementById('attachmentInput');
    const attachmentPreview = document.getElementById('attachmentPreview');
    const submitBtn = document.getElementById('submitBtn');
    const clearBtn = document.getElementById('clearBtn');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const searchBox = document.getElementById('searchBox');
    const sortSelect = document.getElementById('sortSelect');
    const filterUser = document.getElementById('filterUser');

    if (!listEl || !tpl || !form || !komentarInput || !komentarEditor || !attachmentInput || !attachmentPreview || !submitBtn || !clearBtn || !loadMoreBtn || !searchBox || !sortSelect || !filterUser) {
        return;
    }

    let offset = 0;
    const limit = 8;
    function renderItem(item) {
        const node = tpl.content.cloneNode(true);
        node.querySelector('.username').textContent = item.username;
        node.querySelector('.tanggal').textContent = new Date(item.tanggal).toLocaleString();
        node.querySelector('.komentar').innerHTML = escapeHtml(item.komentar).replace(/\n/g, '<br>');
        node.querySelector('.category').textContent = item.category ? item.category : '';
        const ratingOut = node.querySelector('.rating-out');
        if (item.rating) {
            const n = Math.max(0, Math.min(5, parseInt(item.rating, 10) || 0));
            ratingOut.textContent = '★'.repeat(n) + '☆'.repeat(5 - n);
        }
        const att = node.querySelector('.attachment-link');
        if (item.attachment) {
            att.innerHTML = `<a href="${item.attachment}" target="_blank">Lampiran</a>`;
        }
        const avatarImg = node.querySelector('.avatar-img');
        if (avatarImg) {
            if (item.profile_image) {
                avatarImg.src = item.profile_image;
            } else {
                avatarImg.removeAttribute('src');
            }
        }
        return node;
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": "&#39;" }[c]));
    }
    async function loadList(reset = false) {
        try {
            if (reset) {
                offset = 0;
                listEl.innerHTML = '';
                loadMoreBtn.style.display = '';
            }
            const params = new URLSearchParams({ limit, offset, sort: sortSelect.value, filterUser: filterUser.value, search: searchBox.value });
            const res = await fetch('feedback_action.php?action=list&' + params.toString());
            const data = await res.json();
            const items = Array.isArray(data.items) ? data.items : [];

            if (items.length) {
                items.forEach(it => {
                    const node = renderItem(it);
                    listEl.appendChild(node);
                });
                offset += items.length;
                loadMoreBtn.style.display = items.length >= limit ? '' : 'none';
            } else {
                if (offset === 0) listEl.innerHTML = '<div class="no-feedback">Belum ada feedback</div>';
                loadMoreBtn.style.display = 'none';
            }
        } catch (e) {
            if (offset === 0) listEl.innerHTML = '<div class="no-feedback">Gagal memuat feedback</div>';
            loadMoreBtn.style.display = 'none';
        }
    }
    loadList(true);
    loadMoreBtn.addEventListener('click', () => loadList(false));
    [searchBox, sortSelect, filterUser].forEach(el => el.addEventListener('change', () => loadList(true)));
    attachmentInput.addEventListener('change', (e) => {
        attachmentPreview.innerHTML = '';
        const f = e.target.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        if (f.type.startsWith('image/')) {
            const img = document.createElement('img'); img.src = url; img.className = 'preview-img'; attachmentPreview.appendChild(img);
        } else if (f.type.startsWith('video/')) {
            const vid = document.createElement('video'); vid.src = url; vid.controls = true; vid.className = 'preview-vid'; attachmentPreview.appendChild(vid);
        }
    });
    editorToolbar.forEach(btn => {
        btn.addEventListener('click', () => {
            const cmd = btn.getAttribute('data-cmd');
            document.execCommand(cmd, false, null);
            komentarEditor.focus();
        });
    });
    document.querySelectorAll('.stars i').forEach(star => {
        star.addEventListener('click', () => {
            const val = star.getAttribute('data-value');
            const parent = star.closest('.stars');
            parent.setAttribute('data-rating', val);
            parent.querySelectorAll('i').forEach(s => s.classList.toggle('fas', s.getAttribute('data-value') <= val));
        });
    });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        submitBtn.disabled = true;
        const fd = new FormData();
        const text = (komentarEditor.innerText || komentarEditor.textContent || '').trim();
        komentarInput.value = text;
        const csrfInput = form.querySelector('input[name="csrf_token"]');
        if (csrfInput && csrfInput.value) fd.append('csrf_token', csrfInput.value);
        fd.append('komentar', text);
        fd.append('category', document.getElementById('categorySelect').value);
        fd.append('rating', document.querySelector('.stars').getAttribute('data-rating'));
        if (attachmentInput.files[0]) fd.append('attachment', attachmentInput.files[0]);
        fd.append('action', 'post');
        if (!text && !attachmentInput.files[0]) {
            alert('Komentar atau lampiran harus diisi.');
            submitBtn.disabled = false;
            return;
        }
        try {
            const res = await fetch('feedback_action.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                komentarInput.value = '';
                komentarEditor.innerHTML = '';
                attachmentInput.value = '';
                attachmentPreview.innerHTML = '';
                document.querySelector('.stars').setAttribute('data-rating', '0');
                document.querySelectorAll('.stars i').forEach(function (icon) {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                });
                loadList(true);
            } else {
                alert('Gagal mengirim feedback: ' + (json.error || json.msg || 'unknown'));
            }
        } catch (err) {
            alert('Gagal mengirim feedback.');
        } finally {
            submitBtn.disabled = false;
        }
    });
    clearBtn.addEventListener('click', () => {
        komentarInput.value = '';
        komentarEditor.innerHTML = '';
        attachmentInput.value = '';
        attachmentPreview.innerHTML = '';
    });
});
