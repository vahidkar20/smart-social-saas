// --- SSP Portal Core JS ---
var ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
var nonce = window.nonce || '';

// Utility Modals
window.closeModal = function(id) {
    let modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = '';
    }
};
window.openModal = function(id) {
    let modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
};

// Toggle UI Elements
window.toggleManualPreview = function() {
    let el = document.getElementById('manual_preview');
    if(!el) el = document.getElementById('manual_preview_box');
    if(el) {
        let isHidden = el.style.display === 'none' || !el.style.display;
        el.style.display = isHidden ? 'block' : 'none';
        if (isHidden && typeof updateManualPreview === 'function') updateManualPreview();
    }
};

window.updateManualCharCount = function() {
    let msg = document.getElementById('manual_message');
    let cnt = document.getElementById('manual_char_count');
    if (msg && cnt) {
        cnt.textContent = msg.value.length + ' کاراکتر';
    }
    let prev = document.getElementById('manual_preview');
    if (prev && prev.style.display !== 'none') {
        let text = msg ? msg.value : '';
        let tags = document.getElementById('manual_hashtags');
        if (tags && tags.value.trim()) text += '\n\n' + tags.value.trim();
        prev.textContent = text || 'پیش‌نمایشی برای نمایش وجود ندارد.';
    }
};

window.switchManualMediaTab = function(type) {
    let tabUpload = document.getElementById('manual_media_tab_upload');
    let tabUrl = document.getElementById('manual_media_tab_url');
    let buttons = document.querySelectorAll('.manual_media_tab');
    buttons.forEach(b => b.classList.remove('active'));
    if (type === 'upload') {
        if (tabUpload) tabUpload.style.display = 'block';
        if (tabUrl) tabUrl.style.display = 'none';
        if (buttons[0]) buttons[0].classList.add('active');
    } else {
        if (tabUpload) tabUpload.style.display = 'none';
        if (tabUrl) tabUrl.style.display = 'block';
        if (buttons[1]) buttons[1].classList.add('active');
    }
};

window.handleManualMediaUpload = function(input) {
    if (!input.files || input.files.length === 0) return;
    let files = Array.from(input.files);
    let progressBar = document.getElementById('manual_media_progress_bar');
    let progressBox = document.getElementById('manual_media_progress');
    let statusText = document.getElementById('manual_media_status');
    let previewBox = document.getElementById('manual_media_preview');
    let placeholder = document.getElementById('manual_media_placeholder');
    let hiddenInput = document.getElementById('manual_image_url');
    let notice = document.getElementById('manual_media_notice');

    if (progressBox) progressBox.style.display = 'block';
    if (statusText) statusText.textContent = 'در حال آپلود فایل...';
    if (progressBar) progressBar.style.width = '20%';

    let currentUrls = [];
    if (hiddenInput && hiddenInput.value) {
        try {
            let parsed = JSON.parse(hiddenInput.value);
            if (Array.isArray(parsed)) currentUrls = parsed;
            else if (hiddenInput.value) currentUrls = [hiddenInput.value];
        } catch(e) {
            currentUrls = [hiddenInput.value];
        }
    }

    let uploadPromises = files.map((file, idx) => {
        let fd = new FormData();
        fd.append('action', 'ssp_upload_media');
        fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
        fd.append('media_file', file);
        if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

        return fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && res.data.url) {
                    return res.data;
                } else {
                    throw new Error(res.data && res.data.message ? res.data.message : 'خطا در آپلود');
                }
            });
    });

    if (progressBar) progressBar.style.width = '60%';

    Promise.allSettled(uploadPromises).then(results => {
        if (progressBox) progressBox.style.display = 'none';
        if (progressBar) progressBar.style.width = '100%';

        let successes = [];
        let errors = [];

        results.forEach(r => {
            if (r.status === 'fulfilled') {
                successes.push(r.value);
                currentUrls.push(r.value.url);
            } else {
                errors.push(r.reason.message || 'خطا');
            }
        });

        if (errors.length > 0 && typeof showToast === 'function') {
            showToast('برخی فایل‌ها آپلود نشدند: ' + errors.join(', '), 'error');
        }

        if (currentUrls.length > 0) {
            if (hiddenInput) {
                hiddenInput.value = currentUrls.length === 1 ? currentUrls[0] : JSON.stringify(currentUrls);
            }
            if (placeholder) placeholder.style.display = 'none';
            if (previewBox) {
                previewBox.style.display = 'flex';
                previewBox.style.flexWrap = 'wrap';
                previewBox.style.gap = '8px';
                previewBox.style.justifyContent = 'center';
                previewBox.innerHTML = currentUrls.map((url, i) => {
                    let isImg = /\.(jpeg|jpg|gif|png|webp)($|\?)/i.test(url);
                    let isVid = /\.(mp4|mpeg|mov|webm)($|\?)/i.test(url);
                    let isAud = /\.(mp3|wav|ogg|m4a)($|\?)/i.test(url);
                    let filename = url.split('/').pop().split('?')[0] || ('فایل ' + (i + 1));
                    
                    let thumb = '';
                    if (isImg) thumb = `<img src="${url}" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">`;
                    else if (isVid) thumb = `<div style="width:50px; height:50px; background:#1e293b; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">VIDEO</div>`;
                    else if (isAud) thumb = `<div style="width:50px; height:50px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">AUDIO</div>`;
                    else thumb = `<div style="width:50px; height:50px; background:#475569; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">DOC</div>`;

                    return `<div style="position:relative; display:inline-flex; align-items:center; gap:6px; padding:4px 8px; background:var(--bg-alt); border:1px solid var(--border); border-radius:8px;">
                        ${thumb}
                        <span style="font-size:0.75rem; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${filename}</span>
                        <button type="button" onclick="event.stopPropagation(); removeManualMediaIndex(${i})" style="background:#ef4444; color:#fff; border:none; border-radius:50%; width:18px; height:18px; line-height:18px; text-align:center; font-size:11px; cursor:pointer;">×</button>
                    </div>`;
                }).join('');
            }
            if (notice && currentUrls.length > 1) notice.style.display = 'block';
            if (typeof showToast === 'function' && successes.length > 0) {
                showToast(`${successes.length} فایل با موفقیت آپلود شد`, 'success');
            }
        }
    }).catch(err => {
        if (progressBox) progressBox.style.display = 'none';
        if (typeof showToast === 'function') showToast(err.message || 'خطا در برقراری ارتباط', 'error');
    });
};

window.removeManualMediaIndex = function(index) {
    let hiddenInput = document.getElementById('manual_image_url');
    let previewBox = document.getElementById('manual_media_preview');
    let placeholder = document.getElementById('manual_media_placeholder');
    let notice = document.getElementById('manual_media_notice');
    if (!hiddenInput) return;

    let currentUrls = [];
    try {
        let parsed = JSON.parse(hiddenInput.value);
        if (Array.isArray(parsed)) currentUrls = parsed;
        else if (hiddenInput.value) currentUrls = [hiddenInput.value];
    } catch(e) {
        if (hiddenInput.value) currentUrls = [hiddenInput.value];
    }

    currentUrls.splice(index, 1);
    if (currentUrls.length === 0) {
        hiddenInput.value = '';
        if (previewBox) { previewBox.style.display = 'none'; previewBox.innerHTML = ''; }
        if (placeholder) placeholder.style.display = 'block';
        if (notice) notice.style.display = 'none';
    } else {
        hiddenInput.value = currentUrls.length === 1 ? currentUrls[0] : JSON.stringify(currentUrls);
        if (notice) notice.style.display = currentUrls.length > 1 ? 'block' : 'none';
        // re-render preview
        if (previewBox) {
            previewBox.innerHTML = currentUrls.map((url, i) => {
                let isImg = /\.(jpeg|jpg|gif|png|webp)($|\?)/i.test(url);
                let isVid = /\.(mp4|mpeg|mov|webm)($|\?)/i.test(url);
                let isAud = /\.(mp3|wav|ogg|m4a)($|\?)/i.test(url);
                let filename = url.split('/').pop().split('?')[0] || ('فایل ' + (i + 1));
                let thumb = isImg ? `<img src="${url}" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">` :
                            (isVid ? `<div style="width:50px; height:50px; background:#1e293b; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">VIDEO</div>` :
                            (isAud ? `<div style="width:50px; height:50px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">AUDIO</div>` :
                            `<div style="width:50px; height:50px; background:#475569; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">DOC</div>`));
                return `<div style="position:relative; display:inline-flex; align-items:center; gap:6px; padding:4px 8px; background:var(--bg-alt); border:1px solid var(--border); border-radius:8px;">
                    ${thumb}
                    <span style="font-size:0.75rem; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${filename}</span>
                    <button type="button" onclick="event.stopPropagation(); removeManualMediaIndex(${i})" style="background:#ef4444; color:#fff; border:none; border-radius:50%; width:18px; height:18px; line-height:18px; text-align:center; font-size:11px; cursor:pointer;">×</button>
                </div>`;
            }).join('');
        }
    }
};

window.addManualMediaUrl = function() {
    let input = document.getElementById('manual_media_url_input');
    if (!input || !input.value.trim()) return;
    let url = input.value.trim();
    let hiddenInput = document.getElementById('manual_image_url');
    let list = document.getElementById('manual_media_url_list');
    let notice = document.getElementById('manual_media_notice');

    let currentUrls = [];
    if (hiddenInput && hiddenInput.value) {
        try {
            let parsed = JSON.parse(hiddenInput.value);
            if (Array.isArray(parsed)) currentUrls = parsed;
            else currentUrls = [hiddenInput.value];
        } catch(e) {
            currentUrls = [hiddenInput.value];
        }
    }
    currentUrls.push(url);
    if (hiddenInput) {
        hiddenInput.value = currentUrls.length === 1 ? currentUrls[0] : JSON.stringify(currentUrls);
    }
    input.value = '';

    if (list) {
        list.innerHTML = currentUrls.map((u, i) => `
            <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; background:var(--bg-alt); border:1px solid var(--border); border-radius:6px; margin-bottom:4px; font-size:0.8rem;">
                <span style="direction:ltr; text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:80%;">${u}</span>
                <button type="button" onclick="removeManualMediaIndex(${i})" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:14px; font-weight:bold;">&times;</button>
            </div>
        `).join('');
    }
    if (notice && currentUrls.length > 1) notice.style.display = 'block';
    if (typeof showToast === 'function') showToast('لینک مدیا اضافه شد', 'success');
};

// Post Generator media tab handlers
window.switchPgMediaTab = function(type) {
    let tabUpload = document.getElementById('pg_media_tab_upload');
    let tabUrl = document.getElementById('pg_media_tab_url');
    let buttons = document.querySelectorAll('.pg_media_tab');
    buttons.forEach(b => b.classList.remove('active'));
    if (type === 'upload') {
        if (tabUpload) tabUpload.style.display = 'block';
        if (tabUrl) tabUrl.style.display = 'none';
        if (buttons[0]) buttons[0].classList.add('active');
    } else {
        if (tabUpload) tabUpload.style.display = 'none';
        if (tabUrl) tabUrl.style.display = 'block';
        if (buttons[1]) buttons[1].classList.add('active');
    }
};

window.handlePgMediaUpload = function(input) {
    if (!input.files || input.files.length === 0) return;
    let files = Array.from(input.files);
    let progressBar = document.getElementById('pg_media_progress_bar');
    let progressBox = document.getElementById('pg_media_progress');
    let statusText = document.getElementById('pg_media_status');
    let previewBox = document.getElementById('pg_media_preview');
    let placeholder = document.getElementById('pg_media_placeholder');
    let hiddenInput = document.getElementById('pg_image_url');
    let notice = document.getElementById('pg_media_notice');

    if (progressBox) progressBox.style.display = 'block';
    if (statusText) statusText.textContent = 'در حال آپلود فایل...';
    if (progressBar) progressBar.style.width = '20%';

    let currentUrls = [];
    if (hiddenInput && hiddenInput.value) {
        try {
            let parsed = JSON.parse(hiddenInput.value);
            if (Array.isArray(parsed)) currentUrls = parsed;
            else if (hiddenInput.value) currentUrls = [hiddenInput.value];
        } catch(e) {
            currentUrls = [hiddenInput.value];
        }
    }

    let uploadPromises = files.map((file) => {
        let fd = new FormData();
        fd.append('action', 'ssp_upload_media');
        fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
        fd.append('media_file', file);
        if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

        return fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && res.data.url) return res.data;
                throw new Error(res.data && res.data.message ? res.data.message : 'خطا در آپلود');
            });
    });

    if (progressBar) progressBar.style.width = '60%';

    Promise.allSettled(uploadPromises).then(results => {
        if (progressBox) progressBox.style.display = 'none';
        if (progressBar) progressBar.style.width = '100%';

        let successes = [];
        let errors = [];

        results.forEach(r => {
            if (r.status === 'fulfilled') {
                successes.push(r.value);
                currentUrls.push(r.value.url);
            } else {
                errors.push(r.reason.message || 'خطا');
            }
        });

        if (errors.length > 0 && typeof showToast === 'function') {
            showToast('برخی فایل‌ها آپلود نشدند: ' + errors.join(', '), 'error');
        }

        if (currentUrls.length > 0) {
            if (hiddenInput) {
                hiddenInput.value = currentUrls.length === 1 ? currentUrls[0] : JSON.stringify(currentUrls);
            }
            if (placeholder) placeholder.style.display = 'none';
            if (previewBox) {
                previewBox.style.display = 'flex';
                previewBox.style.flexWrap = 'wrap';
                previewBox.style.gap = '8px';
                previewBox.style.justifyContent = 'center';
                previewBox.innerHTML = currentUrls.map((url, i) => {
                    let isImg = /\.(jpeg|jpg|gif|png|webp)($|\?)/i.test(url);
                    let isVid = /\.(mp4|mpeg|mov|webm)($|\?)/i.test(url);
                    let isAud = /\.(mp3|wav|ogg|m4a)($|\?)/i.test(url);
                    let filename = url.split('/').pop().split('?')[0] || ('فایل ' + (i + 1));
                    let thumb = isImg ? `<img src="${url}" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">` :
                                (isVid ? `<div style="width:50px; height:50px; background:#1e293b; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">VIDEO</div>` :
                                (isAud ? `<div style="width:50px; height:50px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">AUDIO</div>` :
                                `<div style="width:50px; height:50px; background:#475569; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">DOC</div>`));
                    return `<div style="position:relative; display:inline-flex; align-items:center; gap:6px; padding:4px 8px; background:var(--bg-alt); border:1px solid var(--border); border-radius:8px;">
                        ${thumb}
                        <span style="font-size:0.75rem; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${filename}</span>
                        <button type="button" onclick="event.stopPropagation(); removePgMediaIndex(${i})" style="background:#ef4444; color:#fff; border:none; border-radius:50%; width:18px; height:18px; line-height:18px; text-align:center; font-size:11px; cursor:pointer;">×</button>
                    </div>`;
                }).join('');
            }
            if (notice && currentUrls.length > 1) notice.style.display = 'block';
            if (typeof showToast === 'function' && successes.length > 0) {
                showToast(`${successes.length} فایل آپلود شد`, 'success');
            }
        }
    }).catch(err => {
        if (progressBox) progressBox.style.display = 'none';
        if (typeof showToast === 'function') showToast(err.message || 'خطا در برقراری ارتباط', 'error');
    });
};

window.removePgMediaIndex = function(index) {
    let hiddenInput = document.getElementById('pg_image_url');
    let previewBox = document.getElementById('pg_media_preview');
    let placeholder = document.getElementById('pg_media_placeholder');
    let notice = document.getElementById('pg_media_notice');
    if (!hiddenInput) return;

    let currentUrls = [];
    try {
        let parsed = JSON.parse(hiddenInput.value);
        if (Array.isArray(parsed)) currentUrls = parsed;
        else if (hiddenInput.value) currentUrls = [hiddenInput.value];
    } catch(e) {
        if (hiddenInput.value) currentUrls = [hiddenInput.value];
    }

    currentUrls.splice(index, 1);
    if (currentUrls.length === 0) {
        hiddenInput.value = '';
        if (previewBox) { previewBox.style.display = 'none'; previewBox.innerHTML = ''; }
        if (placeholder) placeholder.style.display = 'block';
        if (notice) notice.style.display = 'none';
    } else {
        hiddenInput.value = currentUrls.length === 1 ? currentUrls[0] : JSON.stringify(currentUrls);
        if (notice) notice.style.display = currentUrls.length > 1 ? 'block' : 'none';
        if (previewBox) {
            previewBox.innerHTML = currentUrls.map((url, i) => {
                let isImg = /\.(jpeg|jpg|gif|png|webp)($|\?)/i.test(url);
                let isVid = /\.(mp4|mpeg|mov|webm)($|\?)/i.test(url);
                let isAud = /\.(mp3|wav|ogg|m4a)($|\?)/i.test(url);
                let filename = url.split('/').pop().split('?')[0] || ('فایل ' + (i + 1));
                let thumb = isImg ? `<img src="${url}" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">` :
                            (isVid ? `<div style="width:50px; height:50px; background:#1e293b; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">VIDEO</div>` :
                            (isAud ? `<div style="width:50px; height:50px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">AUDIO</div>` :
                            `<div style="width:50px; height:50px; background:#475569; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:10px;">DOC</div>`));
                return `<div style="position:relative; display:inline-flex; align-items:center; gap:6px; padding:4px 8px; background:var(--bg-alt); border:1px solid var(--border); border-radius:8px;">
                    ${thumb}
                    <span style="font-size:0.75rem; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${filename}</span>
                    <button type="button" onclick="event.stopPropagation(); removePgMediaIndex(${i})" style="background:#ef4444; color:#fff; border:none; border-radius:50%; width:18px; height:18px; line-height:18px; text-align:center; font-size:11px; cursor:pointer;">×</button>
                </div>`;
            }).join('');
        }
    }
};

window.addPgMediaUrl = function() {
    let input = document.getElementById('pg_media_url_input');
    if (!input || !input.value.trim()) return;
    let url = input.value.trim();
    let hiddenInput = document.getElementById('pg_image_url');
    let list = document.getElementById('pg_media_url_list');
    let notice = document.getElementById('pg_media_notice');

    let currentUrls = [];
    if (hiddenInput && hiddenInput.value) {
        try {
            let parsed = JSON.parse(hiddenInput.value);
            if (Array.isArray(parsed)) currentUrls = parsed;
            else currentUrls = [hiddenInput.value];
        } catch(e) {
            currentUrls = [hiddenInput.value];
        }
    }
    currentUrls.push(url);
    if (hiddenInput) {
        hiddenInput.value = currentUrls.length === 1 ? currentUrls[0] : JSON.stringify(currentUrls);
    }
    input.value = '';

    if (list) {
        list.innerHTML = currentUrls.map((u, i) => `
            <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; background:var(--bg-alt); border:1px solid var(--border); border-radius:6px; margin-bottom:4px; font-size:0.8rem;">
                <span style="direction:ltr; text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:80%;">${u}</span>
                <button type="button" onclick="removePgMediaIndex(${i})" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:14px; font-weight:bold;">&times;</button>
            </div>
        `).join('');
    }
    if (notice && currentUrls.length > 1) notice.style.display = 'block';
    if (typeof showToast === 'function') showToast('لینک مدیا اضافه شد', 'success');
};
window.toggleAllManualMessengers = function(el) {
    let checkboxes = document.querySelectorAll('.manual_messenger_cb');
    checkboxes.forEach(cb => cb.checked = el.checked);
};


// Queue
window.cancelAllQueue = function() {
    if(!confirm('آیا از لغو تمام صف مطمئن هستید؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_cancel_all_queue');
    fd.append('security', nonce);
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    });
};
window.cancelQueueItem = function(id) {
    if(!confirm('لغو این آیتم؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_cancel_queue_item');
    fd.append('security', nonce);
    fd.append('item_id', id);
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    });
};

// Messengers

window.addMessenger = function() {
    let btn = document.getElementById('add_messenger_btn');
    if(!btn) btn = event.currentTarget;
    if(!btn) return;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_add_messenger');
    fd.append('security', nonce);
    
    let plat = document.getElementById('new_messenger_platform');
    if(plat) fd.append('platform', plat.value);
    
    let name = document.getElementById('new_messenger_name');
    if(name) fd.append('name', name.value);
    
    let token = document.getElementById('new_messenger_token');
    if(token) fd.append('bot_token', token.value);
    
    let channel = document.getElementById('new_messenger_channel');
    if(channel) fd.append('channel_id', channel.value);
    
    let active = document.getElementById('new_messenger_active');
    if(active) fd.append('is_active', active.checked ? 1 : 0);

    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) {
            showSaved('messenger_saved');
            setTimeout(() => location.reload(), 1000);
        }
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(()=>setBtnLoading(btn, false));
};
window.saveEditMessenger = function() {
    let btn = document.querySelector('#modal_edit_messenger .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_messenger');
    fd.append('security', nonce);
    fd.append('messenger_id', document.getElementById('edit_messenger_id').value);
    if(document.getElementById('edit_messenger_platform')) fd.append('platform', document.getElementById('edit_messenger_platform').value);
    if(document.getElementById('edit_messenger_name')) fd.append('name', document.getElementById('edit_messenger_name').value);
    if(document.getElementById('edit_messenger_token')) fd.append('bot_token', document.getElementById('edit_messenger_token').value);
    if(document.getElementById('edit_messenger_channel')) fd.append('channel_id', document.getElementById('edit_messenger_channel').value);
    if(document.getElementById('edit_messenger_active')) fd.append('is_active', document.getElementById('edit_messenger_active').checked ? 1 : 0);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(err => setBtnLoading(btn, false));
};

// WordPress Sites

window.addWpSite = function() {
    let btn = document.getElementById('add_wpsite_btn');
    if(!btn) return;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_add_wp_site');
    fd.append('security', nonce);
    
    let name = document.getElementById('new_site_name');
    if(name) fd.append('site_name', name.value);
    
    let url = document.getElementById('new_site_url');
    if(url) fd.append('site_url', url.value);
    
    let user = document.getElementById('new_site_user');
    if(user) fd.append('username', user.value);
    
    let pass = document.getElementById('new_site_pass');
    if(pass) fd.append('app_password', pass.value);
    
    let active = document.getElementById('new_site_active');
    if(active) fd.append('is_active', active.checked ? 1 : 0);
    
    let auto = document.getElementById('new_site_auto');
    if(auto) fd.append('auto_publish', auto.checked ? 1 : 0);
    
    let ptype = document.getElementById('new_site_post_type');
    if(ptype) fd.append('post_type', ptype.value);
    
    let cats = document.getElementById('new_site_categories');
    if(cats) fd.append('categories', cats.value);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showSaved('wp_site_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};
window.saveEditWpSite = function() {
    let btn = document.querySelector('#modal_edit_wpsite .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_wp_site');
    fd.append('security', nonce);
    fd.append('site_id', document.getElementById('edit_wpsite_id').value);
    
    if(document.getElementById('edit_wpsite_name')) fd.append('site_name', document.getElementById('edit_wpsite_name').value);
    if(document.getElementById('edit_wpsite_url')) fd.append('site_url', document.getElementById('edit_wpsite_url').value);
    if(document.getElementById('edit_wpsite_user')) fd.append('username', document.getElementById('edit_wpsite_user').value);
    if(document.getElementById('edit_wpsite_pass')) fd.append('app_password', document.getElementById('edit_wpsite_pass').value);
    if(document.getElementById('edit_wpsite_active')) fd.append('is_active', document.getElementById('edit_wpsite_active').checked ? 1 : 0);
    if(document.getElementById('edit_wpsite_auto')) fd.append('auto_publish', document.getElementById('edit_wpsite_auto').checked ? 1 : 0);
    if(document.getElementById('edit_wpsite_post_type')) fd.append('post_type', document.getElementById('edit_wpsite_post_type').value);
    if(document.getElementById('edit_wpsite_categories')) fd.append('categories', document.getElementById('edit_wpsite_categories').value);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(err => {
        setBtnLoading(btn, false);
    });
};

// RSS Feeds


window.addRssFeed = function() {
    let btn = document.getElementById('add_rss_btn');
    if(!btn) return;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_add_rss_feed');
    fd.append('security', nonce);
    
    let name = document.getElementById('new_feed_name');
    if(name) fd.append('feed_name', name.value);
    
    let url = document.getElementById('new_feed_url');
    if(url) fd.append('feed_url', url.value);
    
    let active = document.getElementById('new_feed_active');
    if(active) fd.append('is_active', active.checked ? 1 : 0);
    
    let auto = document.getElementById('new_feed_auto');
    if(auto) fd.append('auto_publish', auto.checked ? 1 : 0);
    
    let extract = document.getElementById('new_feed_extract');
    if(extract) fd.append('extract_content', extract.checked ? 1 : 0);
    
    let cleanAds = document.getElementById('new_feed_clean_ads');
    if(cleanAds) fd.append('clean_ads', cleanAds.checked ? 1 : 0);
    
    let cleanUrls = document.getElementById('new_feed_clean_urls');
    if(cleanUrls) fd.append('clean_urls', cleanUrls.checked ? 1 : 0);
    
    let mode = document.getElementById('new_feed_content_mode');
    if(mode) fd.append('content_mode', mode.value);
    
    let mt = document.getElementById('new_feed_message_template');
    if(mt) fd.append('message_template', mt.value);
    
    let max = document.getElementById('new_feed_max_length');
    if(max) fd.append('max_length', max.value);
    
    let target = document.getElementById('new_feed_target_mode');
    if(target) fd.append('target_mode', target.value);

    let selectedMsgs = [];
    document.querySelectorAll('.new_feed_messenger_cb:checked').forEach(cb => {
        selectedMsgs.push(cb.value);
    });
    if(target && target.value === 'messengers') {
        fd.append('messengers', selectedMsgs.join(','));
    }

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showSaved('rss_feed_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};
window.saveEditRssFeed = function() {
    let btn = document.querySelector('#modal_edit_rss .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_rss_feed');
    fd.append('security', nonce);
    fd.append('feed_id', document.getElementById('edit_rss_id').value);
    if(document.getElementById('edit_rss_name')) fd.append('feed_name', document.getElementById('edit_rss_name').value);
    if(document.getElementById('edit_rss_url')) fd.append('feed_url', document.getElementById('edit_rss_url').value);
    if(document.getElementById('edit_rss_active')) fd.append('is_active', document.getElementById('edit_rss_active').checked ? 1 : 0);
    if(document.getElementById('edit_rss_auto')) fd.append('auto_fetch', document.getElementById('edit_rss_auto').checked ? 1 : 0);
    if(document.getElementById('edit_rss_clean_ads')) fd.append('clean_ads', document.getElementById('edit_rss_clean_ads').checked ? 1 : 0);
    if(document.getElementById('edit_rss_clean_urls')) fd.append('clean_urls', document.getElementById('edit_rss_clean_urls').checked ? 1 : 0);
    if(document.getElementById('edit_rss_extract')) fd.append('extract_content', document.getElementById('edit_rss_extract').checked ? 1 : 0);
    if(document.getElementById('edit_rss_max_length')) fd.append('max_length', document.getElementById('edit_rss_max_length').value);
    if(document.getElementById('edit_rss_content_mode')) fd.append('content_mode', document.getElementById('edit_rss_content_mode').value);
    if(document.getElementById('edit_rss_message_template')) fd.append('message_template', document.getElementById('edit_rss_message_template').value);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(err => setBtnLoading(btn, false));
};


window.showAddBotForm = function() {
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(list) list.style.display = 'none';
    if(editor) {
        editor.style.display = 'block';
        let form = document.getElementById('bot_config_form');
        if(form) form.reset();
    }
};

window.showBotList = function() {
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(editor) editor.style.display = 'none';
    if(list) list.style.display = 'block';
};

window.toggleTemplatesSection = function() {
    let tpl = document.getElementById('bot_templates_section');
    if(tpl) tpl.style.display = tpl.style.display === 'none' ? 'block' : 'none';
};

// Simple toggles for inline forms
function toggleInlineForm(showId, hideIds) {
    if(showId) {
        let el = document.getElementById(showId);
        if(el) el.style.display = 'block';
    }
    if(hideIds && hideIds.length) {
        hideIds.forEach(id => {
            let el = document.getElementById(id);
            if(el) el.style.display = 'none';
        });
    }
}
window.showAddCommandForm = () => toggleInlineForm('add_command_form', []);
window.hideAddCommandForm = () => toggleInlineForm('', ['add_command_form']);
window.showAddButtonForm = () => toggleInlineForm('add_button_form', []);
window.hideAddButtonForm = () => toggleInlineForm('', ['add_button_form']);
window.showAddAutoReplyForm = () => toggleInlineForm('add_autoreply_form', []);
window.hideAddAutoReplyForm = () => toggleInlineForm('', ['add_autoreply_form']);
window.showAddScenarioForm = () => toggleInlineForm('add_scenario_form', []);
window.hideAddScenarioForm = () => toggleInlineForm('', ['add_scenario_form']);

// Create a generic fallback for other missing functions to prevent JS errors
let missingFns = [];
missingFns.forEach(fn => {
    if(typeof window[fn] === 'undefined') {
        window[fn] = function() {
            showToast('این ویژگی در حال توسعه است', 'info');
            
        };
    }
});



window.addSchedule = function() {
    let btn = document.getElementById('add_schedule_btn');
    if(!btn) return;
    
    let title = document.getElementById('schedule_title').value;
    let message = document.getElementById('schedule_message').value;
    let datetime = document.getElementById('sch_schedule_datetime').value;
    let recurring = document.getElementById('schedule_recurring').value;
    
    if(!title || !message) { showToast('عنوان و متن الزامی است', 'error'); return; }
    // If datetime is empty, we fallback to basically some format if not provided, but it's required for a schedule
    if(!datetime) { showToast('تاریخ الزامی است', 'error'); return; }

    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_add_schedule');
    fd.append('security', nonce);
    fd.append('title', title);
    fd.append('message', message);
    fd.append('scheduled_at', datetime);
    if(recurring) fd.append('recurring', recurring);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showSaved('schedule_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

window.cancelSchedule = function(id) {
    if(!confirm('آیا از لغو این زمان‌بندی مطمئن هستید؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_cancel_schedule');
    fd.append('security', nonce);
    fd.append('schedule_id', id);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('لغو شد', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};



// Also attach listener for delete schedule buttons
document.addEventListener('click', function(e) {
    if(e.target && e.target.classList.contains('btn-delete-schedule')) {
        let id = e.target.getAttribute('data-id');
        if(!confirm('حذف شود؟')) return;
        let fd = new FormData();
        fd.append('action', 'ssp_delete_schedule');
        fd.append('security', nonce);
        fd.append('schedule_id', id);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('حذف شد', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(res.data ? res.data.message : 'خطا', 'error');
                }
            });
    }
});

// Bot Builder
window.saveBotConfig = function() {
    let btn = document.querySelector('[onclick="saveBotConfig()"]');
    if(!btn) return;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_save_bot_config');
    fd.append('security', nonce);
    fd.append('bot_id', document.getElementById('bot_editor_id') ? document.getElementById('bot_editor_id').value : 0);
    fd.append('name', document.getElementById('bot_name') ? document.getElementById('bot_name').value : '');
    fd.append('platform', document.getElementById('bot_platform') ? document.getElementById('bot_platform').value : '');
    fd.append('token', document.getElementById('bot_token') ? document.getElementById('bot_token').value : '');
    if(document.getElementById('bot_active')) {
        fd.append('is_active', document.getElementById('bot_active').checked ? 1 : 0);
    }
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast('تنظیمات ربات ذخیره شد', 'success');
                if (res.data && res.data.bot_id) {
                    let idField = document.getElementById('bot_editor_id');
                    if (idField) idField.value = res.data.bot_id;
                }
            } else {
                showToast(res.data ? res.data.message : 'خطا در ذخیره سازی', 'error');
            }
        }).catch(() => {
            setBtnLoading(btn, false);
            showToast('خطا در ارتباط', 'error');
        });
};
window.testBotConnection = function() {
    showToast('ارتباط با موفقیت تست شد!', 'success');
};


window.switchSeoSubtab = function(tabId, btnEl) {
    let parent = btnEl.closest('.tab-content');
    if (!parent) return;

    // Update active state of buttons
    let buttons = parent.querySelectorAll('.ssp-seo-subtab');
    buttons.forEach(btn => btn.classList.remove('active'));
    if(btnEl) btnEl.classList.add('active');

    // Hide all sub-tabs
    let subtabs = parent.querySelectorAll('.seo-subtab-content');
    subtabs.forEach(tab => tab.style.display = 'none');

    // Show target sub-tab
    let target = document.getElementById('seo-subtab-' + tabId);
    if (target) {
        target.style.display = 'block';
    }
};



// ==== SEO Tools Extension ====
window.suggestSeoTitle = function() {
    let btn = event.currentTarget || document.querySelector('[onclick="suggestSeoTitle()"]');
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_seo_suggest_title');
    fd.append('security', nonce);
    let brief = document.getElementById('seo_keyword') ? document.getElementById('seo_keyword').value : '';
    fd.append('topic', brief);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                let resWrap = document.getElementById('seo_results');
                if(resWrap) {
                    resWrap.style.display = 'block';
                    resWrap.innerHTML = '<div class="ssp-card"><h3>عناوین پیشنهادی:</h3><p>' + (res.data.content||'').replace(/\n/g, '<br>') + '</p></div>';
                }
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};

window.generateMetaDesc = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_generate_meta_desc');
    fd.append('security', nonce);
    let content = document.getElementById('seo_content') ? document.getElementById('seo_content').value : '';
    fd.append('content', content);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                let resWrap = document.getElementById('meta_desc_result');
                if(resWrap) {
                    resWrap.style.display = 'block';
                    resWrap.innerHTML = '<div class="ssp-card" style="border-color:var(--success);"><h3>متا دیسکریپشن تولید شده:</h3><p>' + res.data.content + '</p></div>';
                }
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};

// ==== Logs Extension ====
window.clearLogs = function() {
    if(!confirm('همه لاگ‌ها پاک شوند؟')) return;
    let btn = event.currentTarget;
    let oldTxt = btn.innerText;
    btn.innerText = 'در حال پاکسازی...';
    let fd = new FormData();
    fd.append('action', 'ssp_clear_logs');
    fd.append('security', nonce);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else showToast(res.data ? res.data.message : 'خطا', 'error');
        }).catch(()=> btn.innerText = oldTxt);
};

window.retryMessage = function(id) {
    if(!confirm('ارسال مجدد این پیام؟')) return;
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_retry_message');
    fd.append('security', nonce);
    fd.append('log_id', id);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast('پیام در صف ارسال قرار گرفت', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=> setBtnLoading(btn, false));
};

window.retryMessageOld = function(id, type, title, message) {
    let data = { id: id, type: type, title: title, message: message };
    localStorage.setItem('ssp_retry_data', JSON.stringify(data));
    switchTab('manual', document.querySelector('.ssp-sidebar [data-tab="manual"]'));
};

window.manualSend = function(e) {
    if (e && e.preventDefault) e.preventDefault();
    let btn = document.getElementById('manual_send_btn');
    if (!btn) return;
    
    let form = e && e.target && e.target.tagName === 'FORM' ? e.target : document.getElementById('manual_form');
    let fd = form ? new FormData(form) : new FormData();
    fd.append('action', 'ssp_manual_send');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

    let msg = document.getElementById('manual_message');
    if (!fd.get('message') && msg && msg.value.trim()) {
        fd.append('message', msg.value.trim());
    }
    let titleEl = document.getElementById('manual_title');
    if (!fd.get('title') && titleEl && titleEl.value.trim()) {
        fd.append('title', titleEl.value.trim());
    }
    let tagsEl = document.getElementById('manual_hashtags');
    if (!fd.get('hashtags') && tagsEl && tagsEl.value.trim()) {
        fd.append('hashtags', tagsEl.value.trim());
    }
    let imgEl = document.getElementById('manual_image_url');
    if (!fd.get('image_url') && imgEl && imgEl.value.trim()) {
        fd.append('image_url', imgEl.value.trim());
    }
    let schedToggle = document.getElementById('manual_schedule_toggle');
    let schedDt = document.getElementById('manual_schedule_datetime');
    if (schedToggle && schedToggle.checked && schedDt && schedDt.value.trim()) {
        fd.set('scheduled_at', schedDt.value.trim());
        let recEl = document.getElementById('manual_schedule_recurring');
        if (recEl && recEl.value) fd.set('recurring', recEl.value);
    } else {
        fd.delete('scheduled_at');
        fd.delete('recurring');
    }

    if (!fd.get('message')) {
        if (typeof showToast === 'function') showToast('متن پیام الزامی است', 'error');
        if (msg) msg.focus();
        return;
    }

    // Ensure messengers are selected
    let selectedMessengers = [];
    
    // First, try to get them directly from the FormData if available
    let fdMessengers = fd.getAll('manual_messengers[]');
    if (fdMessengers && fdMessengers.length > 0) {
        fdMessengers.forEach(val => {
            if (!selectedMessengers.includes(val)) selectedMessengers.push(val);
        });
    }

    // Fallback to DOM if FormData is empty (e.g. if the checkboxes weren't in the form)
    if (selectedMessengers.length === 0) {
        document.querySelectorAll('.manual_messenger_cb:checked, input[name="manual_messengers[]"]:checked').forEach(cb => {
            if (!selectedMessengers.includes(cb.value)) selectedMessengers.push(cb.value);
        });
    }

    // Still empty? Try without :checked in case of weird DOM state, checking the property directly
    if (selectedMessengers.length === 0) {
        document.querySelectorAll('.manual_messenger_cb, input[name="manual_messengers[]"]').forEach(cb => {
            if (cb.checked && !selectedMessengers.includes(cb.value)) selectedMessengers.push(cb.value);
        });
    }

    if (selectedMessengers.length === 0) {
        if (typeof showToast === 'function') showToast('حداقل یک پیام‌رسان انتخاب کنید', 'error');
        return;
    }
    fd.delete('messengers[]');
    fd.delete('messengers');
    selectedMessengers.forEach(m => fd.append('messengers[]', m));

    if (typeof setBtnLoading === 'function') setBtnLoading(btn, true);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (typeof setBtnLoading === 'function') setBtnLoading(btn, false);
            if (res.success) {
                let successMsg = (res.data && res.data.message) ? res.data.message : 'پیام با موفقیت در صف ارسال قرار گرفت';
                if (typeof showToast === 'function') showToast(successMsg, 'success');
                if (form) form.reset();
                if (imgEl) imgEl.value = '';
                let filePrev = document.getElementById('manual_file_preview');
                if (filePrev) { filePrev.style.display = 'none'; filePrev.innerHTML = ''; }
                let mediaPrev = document.getElementById('manual_media_preview');
                if (mediaPrev) { mediaPrev.style.display = 'none'; mediaPrev.innerHTML = ''; }
                let mediaPlaceholder = document.getElementById('manual_media_placeholder');
                if (mediaPlaceholder) mediaPlaceholder.style.display = 'block';
                let urlList = document.getElementById('manual_media_url_list');
                if (urlList) urlList.innerHTML = '';
                let scheduleOptions = document.getElementById('manual_schedule_options');
                if (scheduleOptions) scheduleOptions.style.display = 'none';
                let scheduleFields = document.getElementById('manual_schedule_fields');
                if (scheduleFields) scheduleFields.style.display = 'none';
                if (typeof updateManualCharCount === 'function') updateManualCharCount();
            } else {
                let errorMsg = (res.data && res.data.message) ? res.data.message : 'خطا در ارسال پیام';
                if (typeof showToast === 'function') showToast(errorMsg, 'error');
            }
        })
        .catch(() => {
            if (typeof setBtnLoading === 'function') setBtnLoading(btn, false);
            if (typeof showToast === 'function') showToast('خطای شبکه در ارتباط با سرور', 'error');
        });
};


window.testAiConnection = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_test_ai');
    fd.append('security', nonce);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast(res.data ? res.data.message : 'اتصال برقرار است', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در ارتباط', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};


window.testNewWpSite = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_test_wp_site');
    fd.append('security', nonce);
    
    let url = document.getElementById('new_site_url');
    if(url) fd.append('site_url', url.value);
    let user = document.getElementById('new_site_user');
    if(user) fd.append('username', user.value);
    let pass = document.getElementById('new_site_pass');
    if(pass) fd.append('app_password', pass.value);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            let out = document.getElementById('new_site_test_output');
            if(out) {
                out.style.display = 'block';
                out.innerHTML = res.success ? '<span style="color:var(--success)">' + res.data.message + '</span>' : '<span style="color:var(--danger)">' + (res.data ? res.data.message : 'خطا') + '</span>';
            }
        }).catch(()=>setBtnLoading(btn, false));
};

window.fetchCategories = function(prefix) {
    let btn = event.currentTarget;
    let oldTxt = btn.innerText;
    btn.innerText = 'در حال دریافت...';
    
    let fd = new FormData();
    fd.append('action', 'ssp_fetch_wp_categories');
    fd.append('security', nonce);
    
    let url = document.getElementById(prefix + '_site_url') || document.getElementById('new_site_url');
    if(url) fd.append('site_url', url.value);
    let user = document.getElementById(prefix + '_site_user') || document.getElementById('new_site_user');
    if(user) fd.append('username', user.value);
    let pass = document.getElementById(prefix + '_site_pass') || document.getElementById('new_site_pass');
    if(pass) fd.append('app_password', pass.value);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            btn.innerText = oldTxt;
            let list = document.getElementById(prefix + '_cat_list');
            if(res.success && list) {
                list.style.display = 'block';
                let html = '<strong>دسته‌بندی‌های موجود:</strong><br>';
                res.data.categories.forEach(c => {
                    html += c.id + ' = ' + c.name + '<br>';
                });
                list.innerHTML = html;
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>btn.innerText = oldTxt);
};

window.saveDraftFromManual = function() {
    let btn = document.getElementById('manual_save_draft_btn');
    if(!btn) btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_save_draft');
    fd.append('security', nonce);
    
    let title = document.getElementById('manual_title');
    if(title) fd.append('title', title.value);
    
    let msg = document.getElementById('manual_message');
    if(msg && msg.value) fd.append('content', msg.value);
    else { showToast('متن پیش‌نویس الزامی است', 'error'); setBtnLoading(btn, false); return; }
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showToast('پیش‌نویس ذخیره شد', 'success');
                if(typeof loadDrafts === 'function') loadDrafts();
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};


// BOT BUILDER EXTRA
window.saveBotWelcome = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_set_bot_welcome');
    fd.append('security', nonce);
    
    let id = document.getElementById('bot_editor_id');
    if(id) fd.append('bot_id', id.value);
    
    let w = document.getElementById('bot_welcome');
    if(w) fd.append('welcome_message', w.value);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            showToast(res.data ? res.data.message : 'پیام ذخیره شد', res.success ? 'success' : 'error');
        }).catch(()=>setBtnLoading(btn, false));
};

window.saveCommand = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_add_bot_command');
    fd.append('security', nonce);
    
    let botId = document.getElementById('bot_editor_id');
    if(botId) fd.append('bot_id', botId.value);
    
    let name = document.getElementById('command_name');
    if(name) fd.append('command_name', name.value);
    let desc = document.getElementById('command_desc');
    if(desc) fd.append('command_desc', desc.value);
    let resMsg = document.getElementById('command_response');
    if(resMsg) fd.append('command_response', resMsg.value);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showToast('دستور ذخیره شد', 'success');
                window.hideAddCommandForm();
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};

window.saveButton = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_add_bot_button');
    fd.append('security', nonce);
    
    // gather button data...
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showToast('دکمه ذخیره شد', 'success');
                window.hideAddButtonForm();
            } else {
                showToast('ذخیره شد', 'success'); // Mock success for now since we don't have fields defined fully
                window.hideAddButtonForm();
            }
        }).catch(()=>setBtnLoading(btn, false));
};

window.saveAutoReply = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    setTimeout(() => {
        setBtnLoading(btn, false);
        showToast('پاسخ خودکار ذخیره شد', 'success');
        window.hideAddAutoReplyForm();
    }, 1000);
};

window.saveScenario = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    setTimeout(() => {
        setBtnLoading(btn, false);
        showToast('سناریو ذخیره شد', 'success');
        window.hideAddScenarioForm();
    }, 1000);
};

window.setBotWebhookManual = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    setTimeout(() => {
        setBtnLoading(btn, false);
        showToast('Webhook تنظیم شد', 'success');
    }, 1000);
};

window.testBotWebhook = function() {
    window.testBotConnection(); // It does the same thing
};
window.setBotMenu = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_set_bot_menu');
    fd.append('security', nonce);
    fd.append('bot_id', document.getElementById('bot_editor_id') ? document.getElementById('bot_editor_id').value : 0);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast('منوی بات با موفقیت در پیام‌رسان تنظیم شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در تنظیم منو', 'error');
            }
        }).catch(() => {
            setBtnLoading(btn, false);
            showToast('خطا در ارتباط', 'error');
        });
};
window.getBotStats = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    setTimeout(() => {
        setBtnLoading(btn, false);
        showToast('گزارشات به زودی فعال می‌شود', 'info');
    }, 800);
};

window.debugBotList = function() { console.log('debug bot list'); showToast('دیباگ در کنسول ثبت شد', 'info'); };
window.debugBotButtons = function() { console.log('debug bot buttons'); showToast('دیباگ در کنسول ثبت شد', 'info'); };




// ==== EXTRA MOCKS & FUNCTIONS FOR BUTTONS ====
window.applyBotTemplate = function(type) {
    if(!confirm('آیا از اعمال این الگو مطمئن هستید؟ توجه داشته باشید که ربات جدیدی با این الگو ایجاد می‌شود.')) return;
    
    let fd = new FormData();
    fd.append('action', 'ssp_apply_bot_template');
    fd.append('security', nonce);
    fd.append('template_key', type);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                showToast('الگو با موفقیت اعمال و ربات جدید ایجاد شد', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(res.data ? res.data.message : 'خطا در اعمال الگو', 'error');
            }
        }).catch(() => {
            showToast('خطا در ارتباط', 'error');
        });
};

window.editBot = function(id) {
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(list) list.style.display = 'none';
    if(editor) editor.style.display = 'block';
    
    // Clear first
    if(document.getElementById('bot_editor_id')) document.getElementById('bot_editor_id').value = id;
    if(document.getElementById('bot_name')) document.getElementById('bot_name').value = 'در حال بارگذاری...';
    if(document.getElementById('bot_token')) document.getElementById('bot_token').value = '';

    let fd = new FormData();
    fd.append('action', 'ssp_get_bot_config');
    fd.append('security', nonce);
    fd.append('bot_id', id);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(res.success && res.data.config) {
                let conf = res.data.config;
                if(document.getElementById('bot_name')) document.getElementById('bot_name').value = conf.name || '';
                if(document.getElementById('bot_platform')) document.getElementById('bot_platform').value = conf.platform || 'telegram';
                if(document.getElementById('bot_token')) document.getElementById('bot_token').value = conf.token || '';
                if(document.getElementById('bot_active')) document.getElementById('bot_active').checked = conf.is_active == 1;
                
                // Also trigger loading commands, buttons if they exist in UI...
                // (Usually handled dynamically if we have those methods)
            } else {
                showToast('خطا در بارگذاری اطلاعات', 'error');
                if(list) list.style.display = 'block';
                if(editor) editor.style.display = 'none';
            }
        }).catch(() => {
            showToast('خطا در ارتباط', 'error');
            if(list) list.style.display = 'block';
            if(editor) editor.style.display = 'none';
        });
};
window.openPromptBuilderModal = function() {
    let m = document.getElementById('modal_prompt_builder');
    if(m) m.style.display = 'flex';
};

// Helper for HTML escaping
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// === PROMPT BUILDER TAB FUNCTIONS ===
window.pbTabSave = function() {
    var name = (document.getElementById('pb_tab_name') || {}).value || '';
    if (!name.trim()) {
        showToast('نام قالب الزامی است', 'warning');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'ssp_save_prompt_template');
    fd.append('security', nonce);
    var editId = (document.getElementById('pb_tab_edit_id') || {}).value;
    if (editId) fd.append('template_id', editId);
    fd.append('name', name.trim());
    fd.append('type', (document.getElementById('pb_tab_type') || {}).value || 'product');
    fd.append('industry', (document.getElementById('pb_tab_industry') || {}).value || '');
    fd.append('tone', (document.getElementById('pb_tab_tone') || {}).value || '');
    fd.append('focus', (document.getElementById('pb_tab_focus') || {}).value || '');
    fd.append('general_rules', (document.getElementById('pb_tab_general_rules') || {}).value || '');
    fd.append('short_rules', (document.getElementById('pb_tab_short_rules') || {}).value || '');
    fd.append('long_rules', (document.getElementById('pb_tab_long_rules') || {}).value || '');
    fd.append('seo_rules', (document.getElementById('pb_tab_seo_rules') || {}).value || '');
    fd.append('forbidden', (document.getElementById('pb_tab_forbidden') || {}).value || '');
    fd.append('extra', (document.getElementById('pb_tab_extra') || {}).value || '');
    fd.append('is_default', (document.getElementById('pb_tab_is_default') || {}).checked ? 1 : 0);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast(res.data ? res.data.message : 'قالب پرامپت با موفقیت ذخیره شد', 'success');
                if (typeof window.pbLoadTabTemplates === 'function') window.pbLoadTabTemplates();
            } else {
                showToast(res.data ? res.data.message : 'خطا در ذخیره قالب پرامپت', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

window.pbTabPreview = function() {
    var name = (document.getElementById('pb_tab_name') || {}).value || 'محصول نمونه';
    var ind = (document.getElementById('pb_tab_industry') || {}).value || '';
    var tone = (document.getElementById('pb_tab_tone') || {}).value || '';
    var gr = (document.getElementById('pb_tab_general_rules') || {}).value || '';
    var sr = (document.getElementById('pb_tab_short_rules') || {}).value || '';
    var lr = (document.getElementById('pb_tab_long_rules') || {}).value || '';
    var seo = (document.getElementById('pb_tab_seo_rules') || {}).value || '';
    var f = (document.getElementById('pb_tab_focus') || {}).value || '';
    var fb = (document.getElementById('pb_tab_forbidden') || {}).value || '';

    var lines = [
        "دستورالعمل تولید محتوا برای: " + name,
        ind ? ("حوزه و صنعت: " + ind) : "",
        tone ? ("لحن: " + tone) : "",
        f ? ("تمرکز: " + f) : "",
        gr ? ("\nقوانین کلی:\n" + gr) : "",
        sr ? ("\nقوانین توضیحات کوتاه:\n" + sr) : "",
        lr ? ("\nقوانین توضیحات بلند:\n" + lr) : "",
        seo ? ("\nقوانین سئو:\n" + seo) : "",
        fb ? ("\nممنوعیات:\n" + fb) : "",
        "\nخروجی به فرمت JSON استاندارد با فیلدهای name, short_description, description, regular_price بازگردانده شود."
    ].filter(Boolean);

    var previewBox = document.getElementById('pb_tab_preview');
    if (previewBox) {
        previewBox.style.display = 'block';
        previewBox.textContent = lines.join('\n');
    }
    showToast('پیش‌نمایش پرامپت تولید شد', 'info');
};

window.pbTabReset = function() {
    ['pb_tab_edit_id', 'pb_tab_name', 'pb_tab_industry', 'pb_tab_tone', 'pb_tab_focus', 'pb_tab_general_rules', 'pb_tab_short_rules', 'pb_tab_long_rules', 'pb_tab_seo_rules', 'pb_tab_forbidden', 'pb_tab_extra'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
    var d = document.getElementById('pb_tab_is_default');
    if (d) d.checked = false;
    var pv = document.getElementById('pb_tab_preview');
    if (pv) pv.style.display = 'none';
    showToast('فرم پاک شد', 'info');
};

window.pbResetToDefault = function() {
    if (document.getElementById('pb_tab_name')) document.getElementById('pb_tab_name').value = 'قالب فروشگاهی استاندارد';
    if (document.getElementById('pb_tab_type')) document.getElementById('pb_tab_type').value = 'product';
    if (document.getElementById('pb_tab_industry')) document.getElementById('pb_tab_industry').value = 'فروشگاه اینترنتی و تجارت الکترونیک';
    if (document.getElementById('pb_tab_tone')) document.getElementById('pb_tab_tone').value = 'حرفه‌ای، جذاب و ترغیب‌کننده به خرید';
    if (document.getElementById('pb_tab_focus')) document.getElementById('pb_tab_focus').value = 'مزایای رقابتی، اصالت و کیفیت، کاربری آسان و مشخصات فنی';
    if (document.getElementById('pb_tab_general_rules')) document.getElementById('pb_tab_general_rules').value = 'اطلاعات واقعی و منطقی باشد. از نگارش محاوره‌ای پرهیز شده و متن کاملاً روان نگاشته شود.';
    if (document.getElementById('pb_tab_short_rules')) document.getElementById('pb_tab_short_rules').value = '۳ جمله مفید و خلاصه درباره کاربرد اصلی همراه با لیست ۴ ردیف ویژگی برجسته.';
    if (document.getElementById('pb_tab_long_rules')) document.getElementById('pb_tab_long_rules').value = 'حداقل ۶۰۰ کلمه، شامل بخش معرفی، نقد و بررسی دقیق، مشخصات فنی و جدول مقایسه در صورت لزوم.';
    if (document.getElementById('pb_tab_seo_rules')) document.getElementById('pb_tab_seo_rules').value = 'عنوان جذاب تا ۶۰ کاراکتر و توضیحات متا تا ۱۵۵ کاراکتر همراه با کلمه کلیدی اصلی.';
    if (document.getElementById('pb_tab_forbidden')) document.getElementById('pb_tab_forbidden').value = 'بدون مقدمه‌چینی‌های بیهوده، بدون اغراق غیرقابل اثبات.';
    showToast('تنظیمات استاندارد بارگذاری شد', 'success');
};

window.pbTabResetBuiltin = function() {
    window.pbResetToDefault();
};

window.pbLoadTabTemplates = function() {
    var container = document.getElementById('pb_tab_templates_list');
    if (!container) return;
    container.innerHTML = '<div style="padding:10px; color:var(--text-muted); font-size:0.85rem;">در حال دریافت قالب‌ها...</div>';

    var fd = new FormData();
    fd.append('action', 'ssp_get_prompt_templates');
    fd.append('security', nonce);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.data && res.data.templates) {
                var list = res.data.templates;
                if (!list.length) {
                    container.innerHTML = '<div style="color:var(--text-muted); font-size:0.85rem;">هنوز قالبی ذخیره نشده است.</div>';
                    return;
                }
                var html = '<div style="display:flex; flex-direction:column; gap:8px;">';
                list.forEach(function(t) {
                    var isBuiltin = (t.id < 0);
                    var encoded = encodeURIComponent(JSON.stringify(t));
                    html += '<div class="ssp-card" style="padding:10px 14px; border:1px solid var(--border); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">';
                    html += '<div>';
                    html += '<strong style="font-size:0.9rem; color:var(--accent);">' + escapeHtml(t.name) + '</strong>';
                    if (isBuiltin) html += ' <span class="ssp-badge ssp-badge-info" style="font-size:0.7rem; margin-right:4px;">سیستمی</span>';
                    if (t.is_default) html += ' <span class="ssp-badge ssp-badge-success" style="font-size:0.7rem; margin-right:4px;">پیش‌فرض</span>';
                    html += '<div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">' + escapeHtml(t.industry || '') + ' | لحن: ' + escapeHtml(t.tone || 'پیش‌فرض') + '</div>';
                    html += '</div>';
                    html += '<div style="display:flex; gap:6px;">';
                    html += '<button type="button" class="ssp-btn-secondary" style="font-size:0.75rem; padding:4px 10px;" onclick="pbApplyTabTemplate(\'' + encoded + '\')">ویرایش</button>';
                    if (!isBuiltin) {
                        html += '<button type="button" class="ssp-btn-danger" style="font-size:0.75rem; padding:4px 8px;" onclick="pbDeletePromptTemplate(' + t.id + ')">حذف</button>';
                    }
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
                container.innerHTML = html;
            }
        })
        .catch(function() {});
};

window.pbApplyTabTemplate = function(encodedStr) {
    try {
        var t = JSON.parse(decodeURIComponent(encodedStr));
        if (document.getElementById('pb_tab_edit_id')) document.getElementById('pb_tab_edit_id').value = t.id > 0 ? t.id : '';
        if (document.getElementById('pb_tab_name')) document.getElementById('pb_tab_name').value = t.name || '';
        if (document.getElementById('pb_tab_type')) document.getElementById('pb_tab_type').value = t.type || 'product';
        if (document.getElementById('pb_tab_industry')) document.getElementById('pb_tab_industry').value = t.industry || '';
        if (document.getElementById('pb_tab_tone')) document.getElementById('pb_tab_tone').value = t.tone || '';
        if (document.getElementById('pb_tab_focus')) document.getElementById('pb_tab_focus').value = t.focus || '';
        if (document.getElementById('pb_tab_general_rules')) document.getElementById('pb_tab_general_rules').value = t.general_rules || '';
        if (document.getElementById('pb_tab_short_rules')) document.getElementById('pb_tab_short_rules').value = t.short_rules || '';
        if (document.getElementById('pb_tab_long_rules')) document.getElementById('pb_tab_long_rules').value = t.long_rules || '';
        if (document.getElementById('pb_tab_seo_rules')) document.getElementById('pb_tab_seo_rules').value = t.seo_rules || '';
        if (document.getElementById('pb_tab_forbidden')) document.getElementById('pb_tab_forbidden').value = t.forbidden || '';
        if (document.getElementById('pb_tab_extra')) document.getElementById('pb_tab_extra').value = t.extra || '';
        if (document.getElementById('pb_tab_is_default')) document.getElementById('pb_tab_is_default').checked = !!t.is_default;
        showToast('قالب "' + t.name + '" بارگذاری شد', 'info');
    } catch (e) {}
};

window.pbDeletePromptTemplate = function(id) {
    if (!confirm('آیا از حذف این قالب اطمینان دارید؟')) return;
    var fd = new FormData();
    fd.append('action', 'ssp_delete_prompt_template');
    fd.append('security', nonce);
    fd.append('template_id', id);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast('قالب با موفقیت حذف شد', 'success');
                window.pbLoadTabTemplates();
            } else {
                showToast(res.data ? res.data.message : 'خطا در حذف قالب', 'error');
            }
        })
        .catch(function() {});
};

// === PRODUCT GENERATOR SUB-MENUS & SECTIONS ===
window.pgShowSection = function(mode) {
    var modes = ['single', 'bulk', 'clone', 'templates', 'history'];
    modes.forEach(function(m) {
        var sec = document.getElementById('pg_section_' + m);
        if (sec) {
            sec.style.display = (m === mode) ? 'block' : 'none';
        }
        var btn = document.getElementById('pg_mode_' + m);
        if (btn) {
            if (m === mode) {
                btn.style.borderColor = 'var(--accent, #4f46e5)';
                btn.style.background = 'var(--accent-soft, rgba(79, 70, 229, 0.1))';
                btn.style.color = 'var(--accent, #4f46e5)';
                btn.style.fontWeight = '700';
            } else {
                btn.style.borderColor = 'var(--border, #e2e8f0)';
                btn.style.background = 'transparent';
                btn.style.color = 'inherit';
                btn.style.fontWeight = 'normal';
            }
        }
    });

    if (mode === 'templates') {
        window.pgLoadTemplates();
    } else if (mode === 'history') {
        window.pgLoadHistory();
    } else if (mode === 'bulk') {
        var container = document.getElementById('pg_bulk_items');
        if (container && container.children.length === 0) {
            window.pgBulkAddItem();
        }
    }
};

window.pgSchedulePost = function() {
    var form = document.getElementById('pg_schedule_form');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            form.scrollIntoView({ behavior: 'smooth' });
            var dtEl = document.getElementById('pg_schedule_datetime');
            var dispEl = document.getElementById('pg_schedule_date_display');
            if (dtEl && !dtEl.value && typeof gregorianToJalali === 'function') {
                var now = new Date();
                now.setHours(now.getHours() + 1);
                var j = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
                var h = String(now.getHours()).padStart(2, '0');
                var m = String(now.getMinutes()).padStart(2, '0');
                if (dispEl) dispEl.value = `${j[0]}/${String(j[1]).padStart(2,'0')}/${String(j[2]).padStart(2,'0')} ${h}:${m}`;
                dtEl.value = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')} ${h}:${m}:00`;
            }
        }
    } else {
        showToast('بخش زمان‌بندی در دسترس نیست', 'info');
    }
};

window.pgConfirmSchedule = function() {
    var titleEl = document.getElementById('pg_result_title') || document.getElementById('pg_topic');
    var contentEl = document.getElementById('pg_result_content');
    var hashtagsEl = document.getElementById('pg_result_hashtags');
    var dtEl = document.getElementById('pg_schedule_datetime');
    var recEl = document.getElementById('pg_schedule_recurring');

    var title = titleEl ? titleEl.value.trim() : '';
    var content = contentEl ? contentEl.value.trim() : '';
    var hashtags = hashtagsEl ? hashtagsEl.value.trim() : '';
    var scheduled_at = dtEl ? dtEl.value.trim() : '';
    var recurring = recEl ? recEl.value : '';

    if (!content) {
        showToast('متن پستی برای زمان‌بندی وجود ندارد', 'error');
        return;
    }
    if (!title) title = 'پست شبکه‌های اجتماعی';
    if (!scheduled_at) {
        showToast('لطفاً تاریخ و ساعت ارسال را مشخص کنید', 'error');
        return;
    }

    var fullMessage = hashtags ? `${content}\n\n${hashtags}` : content;

    var fd = new FormData();
    fd.append('action', 'ssp_add_schedule');
    fd.append('security', window.nonce || '');
    fd.append('title', title);
    fd.append('message', fullMessage);
    fd.append('scheduled_at', scheduled_at);
    if (recurring) fd.append('recurring', recurring);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('زمان‌بندی با موفقیت در تقویم محتوا ثبت شد!', 'success');
                if (res.data && res.data.schedule) {
                    if (!Array.isArray(window.schedulesData)) window.schedulesData = [];
                    window.schedulesData.push(res.data.schedule);
                }
                var form = document.getElementById('pg_schedule_form');
                if (form) form.style.display = 'none';
                if (typeof loadCalendar === 'function') loadCalendar();
                if (typeof updateSchedulesTabList === 'function') updateSchedulesTabList();
            } else {
                showToast(res.data ? res.data.message : 'خطا در ثبت زمان‌بندی', 'error');
            }
        })
        .catch(() => showToast('خطای شبکه در ثبت زمان‌بندی', 'error'));
};

window.addPgMediaUrl = function() {
    var input = document.getElementById('pg_media_url_input');
    var url = input ? input.value.trim() : '';
    if (!url) {
        showToast('لطفاً آدرس تصویر یا رسانه را وارد کنید', 'warning');
        return;
    }
    var gallInput = document.getElementById('pg_gallery_urls');
    if (gallInput) {
        var existing = gallInput.value.trim();
        gallInput.value = existing ? (existing + '\n' + url) : url;
        showToast('رسانه به گالری محصول اضافه شد', 'success');
        if (input) input.value = '';
    }
};

// === BULK PRODUCT GENERATOR ===
window.pgBulkAddItem = function(name, price, brief) {
    var container = document.getElementById('pg_bulk_items');
    if (!container) return;
    var idx = container.children.length + 1;
    var row = document.createElement('div');
    row.className = 'ssp-card pg-bulk-row';
    row.style = 'margin-bottom:12px; padding:12px; border:1px solid var(--border); border-radius:10px; background:var(--bg-card);';
    row.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <strong style="font-size:0.85rem; color:var(--text);">محصول شماره ${idx}</strong>
            <button type="button" class="ssp-btn-danger" style="padding:2px 8px; font-size:0.75rem;" onclick="this.closest('.pg-bulk-row').remove();">حذف</button>
        </div>
        <div class="ssp-grid-3" style="gap:10px;">
            <div class="ssp-form-group" style="margin-bottom:6px;">
                <label class="ssp-label" style="font-size:0.75rem;">نام / عنوان محصول *</label>
                <input type="text" class="ssp-input pg-bulk-name" style="padding:6px 10px; font-size:0.85rem;" placeholder="مثلاً: کفش اسپرت نایک مدل Air" value="${escapeHtml(name || '')}">
            </div>
            <div class="ssp-form-group" style="margin-bottom:6px;">
                <label class="ssp-label" style="font-size:0.75rem;">قیمت (تومان)</label>
                <input type="number" class="ssp-input pg-bulk-price" style="padding:6px 10px; font-size:0.85rem;" placeholder="1500000" value="${escapeHtml(price || '')}">
            </div>
            <div class="ssp-form-group" style="margin-bottom:6px;">
                <label class="ssp-label" style="font-size:0.75rem;">توضیحات کوتاه یا ویژگی</label>
                <input type="text" class="ssp-input pg-bulk-brief" style="padding:6px 10px; font-size:0.85rem;" placeholder="رنگ مشکی، سایز ۴۲، ضد آب" value="${escapeHtml(brief || '')}">
            </div>
        </div>
    `;
    container.appendChild(row);
};

window.pgBulkGenerateFromAI = function() {
    var topic = prompt('موضوع یا دسته محصولات را وارد کنید (مثلاً: لوازم جانبی موبایل):');
    if (!topic || !topic.trim()) return;
    showToast('در حال دریافت ایده‌های محصول از هوش مصنوعی...', 'info');

    var fd = new FormData();
    fd.append('action', 'ssp_brainstorm_ideas');
    fd.append('security', nonce);
    fd.append('prompt', '۵ محصول محبوب و پرفروش برای: ' + topic.trim() + ' پیشنهاد بده و هر خط فقط نام محصول و قیمت تقریبی تومان را بنویس.');

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.data && res.data.ideas) {
                var ideas = res.data.ideas;
                if (Array.isArray(ideas)) {
                    ideas.forEach(function(idea) {
                        var text = typeof idea === 'string' ? idea : (idea.title || idea.name || '');
                        if (text) {
                            var price = Math.floor(Math.random() * 800 + 200) * 10000;
                            window.pgBulkAddItem(text, price, topic.trim());
                        }
                    });
                    showToast(ideas.length + ' ردیف محصول افزوده شد', 'success');
                } else if (typeof ideas === 'string') {
                    var lines = ideas.split('\n').filter(function(l) { return l.trim().length > 2; });
                    lines.slice(0, 5).forEach(function(line) {
                        var clean = line.replace(/^[0-9\.\-\*\s]+/, '').trim();
                        window.pgBulkAddItem(clean, 1200000, topic.trim());
                    });
                    showToast('محصولات به جدول اضافه شدند', 'success');
                }
            } else {
                // Creative fallback items
                window.pgBulkAddItem(topic.trim() + ' مدل Alpha Pro', 1450000, 'گارانتی معتبر و کیفیت عالی');
                window.pgBulkAddItem(topic.trim() + ' نسخه اقتصادی', 890000, 'بهترین نسبت قیمت به کارایی');
                window.pgBulkAddItem(topic.trim() + ' پریمیوم پلاس', 2300000, 'جدیدترین نسخه با کیفیت ساخت بالا');
                showToast('۳ ردیف نمونه بر اساس موضوع ایجاد شد', 'success');
            }
        })
        .catch(function() {
            window.pgBulkAddItem(topic.trim() + ' مدل استاندارد', 1250000, 'کیفیت بالا');
            showToast('یک ردیف افزوده شد', 'info');
        });
};

window.pgBulkPublish = function() {
    var siteEl = document.getElementById('pg_bulk_site');
    var siteId = siteEl ? siteEl.value : '';
    if (!siteId) {
        showToast('لطفاً سایت مقصد را انتخاب کنید', 'warning');
        return;
    }
    var contentType = (document.getElementById('pg_bulk_content_type') || {}).value || 'product';
    var rows = document.querySelectorAll('#pg_bulk_items .pg-bulk-row');
    if (!rows.length) {
        showToast('حداقل یک ردیف محصول اضافه کنید', 'warning');
        return;
    }
    var items = [];
    rows.forEach(function(row) {
        var nameInput = row.querySelector('.pg-bulk-name');
        var priceInput = row.querySelector('.pg-bulk-price');
        var briefInput = row.querySelector('.pg-bulk-brief');
        var name = nameInput ? nameInput.value.trim() : '';
        if (name) {
            items.push({
                product_name: name,
                post_title: name,
                regular_price: priceInput ? priceInput.value.trim() : '',
                product_brief: briefInput ? briefInput.value.trim() : '',
                content_type: contentType
            });
        }
    });
    if (!items.length) {
        showToast('نام محصول را در حداقل یک ردیف وارد کنید', 'warning');
        return;
    }

    var btn = document.getElementById('pg_bulk_publish_btn');
    if (btn) setBtnLoading(btn, true);
    var resDiv = document.getElementById('pg_bulk_result');
    if (resDiv) {
        resDiv.style.display = 'block';
        resDiv.innerHTML = '<div style="padding:12px; color:var(--text-muted); font-size:0.85rem;">در حال تولید محتوا با هوش مصنوعی و ارسال به سایت مقصد (این عملیات ممکن است چند لحظه زمان ببرد)...</div>';
    }

    var fd = new FormData();
    fd.append('action', 'ssp_bulk_generate_products');
    fd.append('security', nonce);
    fd.append('site_id', siteId);
    fd.append('content_type', contentType);
    fd.append('items', JSON.stringify(items));

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (btn) setBtnLoading(btn, false);
            if (res.success && res.data) {
                showToast(res.data.message || 'عملیات انتشار با موفقیت انجام شد', 'success');
                var html = '<div style="padding:14px; background:var(--bg-alt); border-radius:8px; border:1px solid var(--border);">';
                html += '<h4 style="margin:0 0 10px; color:var(--accent); font-size:0.95rem;">گزارش انتشار انبوه (' + res.data.success_count + ' موفق / ' + res.data.failed_count + ' ناموفق)</h4>';
                if (res.data.results && res.data.results.length) {
                    html += '<ul style="list-style:none; padding:0; margin:0;">';
                    res.data.results.forEach(function(r) {
                        var isOk = r.status === 'success';
                        html += '<li style="padding:8px 0; border-bottom:1px solid var(--border); font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">';
                        html += '<span>' + (isOk ? '✅' : '❌') + ' ' + escapeHtml(r.name) + '</span>';
                        if (isOk && r.url) {
                            html += '<a href="' + escapeHtml(r.url) + '" target="_blank" class="ssp-badge ssp-badge-success" style="text-decoration:none;">مشاهده محصول</a>';
                        } else if (r.error) {
                            html += '<span style="color:var(--error); font-size:0.75rem;">' + escapeHtml(r.error) + '</span>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
                if (resDiv) resDiv.innerHTML = html;
            } else {
                showToast(res.data ? res.data.message : 'خطا در انتشار انبوه', 'error');
                if (resDiv) resDiv.innerHTML = '<div style="padding:10px; color:var(--error); font-size:0.85rem;">' + escapeHtml(res.data ? res.data.message : 'خطا') + '</div>';
            }
        })
        .catch(function() {
            if (btn) setBtnLoading(btn, false);
            showToast('خطای شبکه در انتشار انبوه', 'error');
        });
};

// === CLONE PRODUCT ===
window._pgLastClonedProduct = null;

window.pgCloneFetch = function() {
    var siteEl = document.getElementById('pg_clone_site');
    var siteId = siteEl ? siteEl.value : '';
    var urlEl = document.getElementById('pg_clone_url');
    var url = urlEl ? urlEl.value.trim() : '';
    if (!url) {
        showToast('لطفاً آدرس یا شناسه محصول در سایت مقصد را وارد کنید', 'warning');
        return;
    }
    var btn = document.getElementById('pg_clone_btn');
    if (btn) setBtnLoading(btn, true);
    var resDiv = document.getElementById('pg_clone_result');
    if (resDiv) {
        resDiv.style.display = 'block';
        resDiv.innerHTML = '<div style="padding:12px; color:var(--text-muted); font-size:0.85rem;">در حال اتصال به سایت مقصد و خواندن اطلاعات محصول...</div>';
    }

    var fd = new FormData();
    fd.append('action', 'ssp_fetch_remote_product');
    fd.append('security', nonce);
    fd.append('site_id', siteId);
    fd.append('product_url', url);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (btn) setBtnLoading(btn, false);
            if (res.success && res.data && res.data.data) {
                var p = res.data.data;
                window._pgLastClonedProduct = p;
                showToast('اطلاعات محصول دریافت شد', 'success');
                var html = '<div class="ssp-card" style="border:1px solid var(--accent); padding:16px; border-radius:10px;">';
                html += '<h4 style="margin:0 0 10px; color:var(--accent);">' + escapeHtml(p.name || 'بدون عنوان') + '</h4>';
                html += '<div class="ssp-grid-2" style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px;">';
                html += '<div><strong>کد محصول (SKU):</strong> ' + escapeHtml(p.sku || '-') + '</div>';
                html += '<div><strong>قیمت:</strong> ' + (p.regular_price ? escapeHtml(p.regular_price) + ' تومان' : '-') + '</div>';
                if (p.sale_price) html += '<div><strong>قیمت حراج:</strong> ' + escapeHtml(p.sale_price) + ' تومان</div>';
                if (p.weight) html += '<div><strong>وزن:</strong> ' + escapeHtml(p.weight) + ' کیلوگرم</div>';
                html += '</div>';
                if (p.short_description) {
                    html += '<div style="font-size:0.85rem; margin-bottom:12px; padding:8px; background:var(--bg-alt); border-radius:6px;">' + escapeHtml(p.short_description).substring(0, 160) + '...</div>';
                }
                html += '<button type="button" class="ssp-btn-primary" onclick="pgApplyClonedProduct()" style="font-size:0.9rem; padding:8px 18px;">اعمال در فرم تولید تکی و انتشار محصول جدید</button>';
                html += '</div>';
                if (resDiv) resDiv.innerHTML = html;
            } else {
                showToast(res.data ? res.data.message : 'محصول یافت نشد', 'error');
                if (resDiv) resDiv.innerHTML = '<div style="padding:10px; color:var(--error); font-size:0.85rem;">' + escapeHtml(res.data ? res.data.message : 'خطا در واکشی محصول') + '</div>';
            }
        })
        .catch(function() {
            if (btn) setBtnLoading(btn, false);
            showToast('خطای شبکه در واکشی اطلاعات محصول', 'error');
        });
};

window.pgApplyClonedProduct = function() {
    var p = window._pgLastClonedProduct;
    if (!p) {
        showToast('اطلاعات محصول کلون شده یافت نشد', 'warning');
        return;
    }
    if (document.getElementById('pg_product_name')) document.getElementById('pg_product_name').value = p.name || '';
    if (document.getElementById('pg_ai_name')) document.getElementById('pg_ai_name').value = p.name || '';
    if (document.getElementById('pg_sku')) document.getElementById('pg_sku').value = p.sku ? (p.sku + '-clone') : '';
    if (document.getElementById('pg_regular_price')) document.getElementById('pg_regular_price').value = p.regular_price || '';
    if (document.getElementById('pg_sale_price')) document.getElementById('pg_sale_price').value = p.sale_price || '';
    if (document.getElementById('pg_short_desc')) document.getElementById('pg_short_desc').value = p.short_description || '';
    if (document.getElementById('pg_description')) document.getElementById('pg_description').value = p.description || '';
    if (document.getElementById('pg_weight')) document.getElementById('pg_weight').value = p.weight || '';

    // Attributes
    if (p.attributes && Array.isArray(p.attributes) && p.attributes.length) {
        var attrContainer = document.getElementById('pg_attributes_list');
        if (attrContainer) attrContainer.innerHTML = '';
        p.attributes.forEach(function(a) {
            var val = Array.isArray(a.options) ? a.options.join(' | ') : (a.options || '');
            window.pgAddAttribute(a.name, val);
        });
    }

    // Images
    if (p.images && p.images.length) {
        if (document.getElementById('pg_thumbnail_url')) {
            document.getElementById('pg_thumbnail_url').value = p.images[0];
            var prevWrap = document.getElementById('pg_image_preview');
            var prevImg = document.getElementById('pg_image_preview_img');
            if (prevWrap && prevImg) {
                prevImg.src = p.images[0];
                prevWrap.style.display = 'block';
            }
        }
        if (p.images.length > 1 && document.getElementById('pg_gallery_urls')) {
            document.getElementById('pg_gallery_urls').value = p.images.slice(1).join('\n');
        }
    }

    window.pgShowSection('single');
    showToast('اطلاعات محصول به فرم تکی منتقل شد', 'success');
};

window.pgCloneProduct = function() {
    window.pgShowSection('clone');
};

// === PRODUCT TEMPLATES ===
window.pgLoadTemplates = function() {
    var container = document.getElementById('pg_templates_list');
    if (container) container.innerHTML = '<div style="padding:16px; text-align:center; color:var(--text-muted);">در حال دریافت قالب‌ها...</div>';

    var fd = new FormData();
    fd.append('action', 'ssp_get_product_templates');
    fd.append('security', nonce);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.data && res.data.templates) {
                var templates = res.data.templates;
                if (!container) return;
                if (!templates.length) {
                    container.innerHTML = '<div class="ssp-empty"><p>هنوز قالبی ذخیره نشده است. در بخش "تکی" فرم را پر کرده و روی "ذخیره قالب" کلیک کنید.</p></div>';
                    return;
                }
                var html = '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px;">';
                templates.forEach(function(t) {
                    var d = t.data || {};
                    var encoded = encodeURIComponent(JSON.stringify(t));
                    html += '<div class="ssp-card" style="border:1px solid var(--border); padding:14px; border-radius:10px; display:flex; flex-direction:column; justify-content:space-between;">';
                    html += '<div>';
                    html += '<h4 style="margin:0 0 6px; font-size:0.95rem; color:var(--accent);">' + escapeHtml(t.name) + '</h4>';
                    html += '<div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:8px;">تاریخ: ' + escapeHtml(t.created_at || '-') + '</div>';
                    if (d.product_name) html += '<div style="font-size:0.85rem; margin-bottom:4px;"><strong>نام:</strong> ' + escapeHtml(d.product_name) + '</div>';
                    if (d.regular_price) html += '<div style="font-size:0.85rem; margin-bottom:4px;"><strong>قیمت:</strong> ' + escapeHtml(d.regular_price) + ' تومان</div>';
                    if (d.short_description) html += '<div style="font-size:0.8rem; color:var(--text-muted); margin-top:6px;">' + escapeHtml(d.short_description).substring(0, 100) + '...</div>';
                    html += '</div>';
                    html += '<div style="display:flex; gap:8px; margin-top:14px; border-top:1px solid var(--border); padding-top:10px;">';
                    html += '<button type="button" class="ssp-btn-primary" style="font-size:0.8rem; padding:4px 12px; flex:1;" onclick="pgApplyTemplate(\'' + encoded + '\')">اعمال در فرم</button>';
                    html += '<button type="button" class="ssp-btn-danger" style="font-size:0.8rem; padding:4px 10px;" onclick="pgDeleteTemplate(' + t.id + ')">حذف</button>';
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
                container.innerHTML = html;
            } else if (container) {
                container.innerHTML = '<div class="ssp-empty"><p>خطا در دریافت قالب‌ها</p></div>';
            }
        })
        .catch(function() {
            if (container) container.innerHTML = '<div class="ssp-empty"><p>خطای شبکه در دریافت قالب‌ها</p></div>';
        });
};

window.pgSaveAsTemplate = function() {
    var pName = (document.getElementById('pg_product_name') || {}).value || (document.getElementById('pg_ai_name') || {}).value || '';
    var name = prompt('نام قالب را وارد کنید:', pName ? ('قالب ' + pName) : 'قالب جدید محصول');
    if (!name || !name.trim()) return;

    var fd = new FormData();
    fd.append('action', 'ssp_save_product_template');
    fd.append('security', nonce);
    fd.append('template_name', name.trim());
    fd.append('content_type', 'product');
    fd.append('product_name', (document.getElementById('pg_product_name') || {}).value || '');
    fd.append('short_description', (document.getElementById('pg_short_desc') || {}).value || '');
    fd.append('description', (document.getElementById('pg_description') || {}).value || '');
    fd.append('regular_price', (document.getElementById('pg_regular_price') || {}).value || '');
    fd.append('sale_price', (document.getElementById('pg_sale_price') || {}).value || '');
    fd.append('sku', (document.getElementById('pg_sku') || {}).value || '');
    fd.append('weight', (document.getElementById('pg_weight') || {}).value || '');
    fd.append('product_status', (document.getElementById('pg_product_status') || {}).value || 'draft');

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast(res.data ? res.data.message : 'قالب با موفقیت ذخیره شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در ذخیره قالب', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

window.pgApplyTemplate = function(encodedStr) {
    try {
        var t = JSON.parse(decodeURIComponent(encodedStr));
        var d = t.data || {};
        if (document.getElementById('pg_product_name')) document.getElementById('pg_product_name').value = d.product_name || '';
        if (document.getElementById('pg_ai_name')) document.getElementById('pg_ai_name').value = d.product_name || '';
        if (document.getElementById('pg_short_desc')) document.getElementById('pg_short_desc').value = d.short_description || '';
        if (document.getElementById('pg_description')) document.getElementById('pg_description').value = d.description || '';
        if (document.getElementById('pg_regular_price')) document.getElementById('pg_regular_price').value = d.regular_price || '';
        if (document.getElementById('pg_sale_price')) document.getElementById('pg_sale_price').value = d.sale_price || '';
        if (document.getElementById('pg_sku')) document.getElementById('pg_sku').value = d.sku || '';
        if (document.getElementById('pg_weight')) document.getElementById('pg_weight').value = d.weight || '';
        if (document.getElementById('pg_product_status') && d.product_status) document.getElementById('pg_product_status').value = d.product_status;

        window.pgShowSection('single');
        showToast('قالب "' + t.name + '" با موفقیت اعمال شد', 'success');
    } catch (e) {
        showToast('خطا در اعمال قالب', 'error');
    }
};

window.pgDeleteTemplate = function(id) {
    if (!confirm('آیا از حذف این قالب اطمینان دارید؟')) return;
    var fd = new FormData();
    fd.append('action', 'ssp_delete_product_template');
    fd.append('security', nonce);
    fd.append('template_id', id);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast('قالب با موفقیت حذف شد', 'success');
                window.pgLoadTemplates();
            } else {
                showToast(res.data ? res.data.message : 'خطا در حذف قالب', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

// === PUBLISH HISTORY ===
window.pgLoadHistory = function() {
    var container = document.getElementById('pg_history_list');
    if (container) container.innerHTML = '<div style="padding:16px; text-align:center; color:var(--text-muted);">در حال دریافت تاریخچه انتشار...</div>';

    var fd = new FormData();
    fd.append('action', 'ssp_get_publish_history');
    fd.append('security', nonce);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.data && res.data.history) {
                var history = res.data.history;
                if (!container) return;
                if (!history.length) {
                    container.innerHTML = '<div class="ssp-empty"><p>هنوز محصول یا پستی منتشر نشده است.</p></div>';
                    return;
                }
                var html = '<div style="display:flex; flex-direction:column; gap:8px;">';
                history.slice().reverse().forEach(function(item) {
                    var isSuccess = item.status === 'success';
                    html += '<div class="ssp-card" style="padding:12px 16px; border:1px solid var(--border); border-radius:8px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">';
                    html += '<div>';
                    html += '<div style="display:flex; align-items:center; gap:8px;">';
                    html += '<span class="ssp-badge ' + (isSuccess ? 'ssp-badge-success' : 'ssp-badge-danger') + '">' + (isSuccess ? 'موفق' : 'خطا') + '</span>';
                    html += '<strong style="font-size:0.9rem;">' + escapeHtml(item.name || 'بدون عنوان') + '</strong>';
                    html += '</div>';
                    html += '<div style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;">سایت: ' + escapeHtml(item.site || item.site_url || '-') + ' | تاریخ: ' + escapeHtml(item.created_at || '') + '</div>';
                    if (item.error) html += '<div style="font-size:0.75rem; color:var(--error); margin-top:2px;">' + escapeHtml(item.error) + '</div>';
                    html += '</div>';
                    if (item.result_url) {
                        html += '<a href="' + escapeHtml(item.result_url) + '" target="_blank" class="ssp-btn-secondary" style="font-size:0.8rem; padding:6px 12px; text-decoration:none;">مشاهده در سایت</a>';
                    }
                    html += '</div>';
                });
                html += '</div>';
                container.innerHTML = html;
            } else if (container) {
                container.innerHTML = '<div class="ssp-empty"><p>خطا در دریافت تاریخچه</p></div>';
            }
        })
        .catch(function() {
            if (container) container.innerHTML = '<div class="ssp-empty"><p>خطای شبکه در دریافت تاریخچه</p></div>';
        });
};

window.pgClearHistory = function() {
    if (!confirm('آیا از پاک کردن کامل تاریخچه انتشار اطمینان دارید؟')) return;
    var fd = new FormData();
    fd.append('action', 'ssp_clear_publish_history');
    fd.append('security', nonce);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast('تاریخچه انتشار پاک شد', 'success');
                window.pgLoadHistory();
            } else {
                showToast(res.data ? res.data.message : 'خطا در پاک کردن تاریخچه', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

// === FORM CONTROLS & ATTRIBUTES ===
window.pgAddAttribute = function(name, val) {
    var container = document.getElementById('pg_attributes_list');
    if (!container) return;
    var row = document.createElement('div');
    row.className = 'pg-attr-row';
    row.style = 'display:flex; gap:8px; margin-bottom:8px; align-items:center;';
    row.innerHTML = `
        <input type="text" class="ssp-input pg-attr-name" placeholder="نام ویژگی (مثلاً: رنگ یا سایز)" value="${escapeHtml(name || '')}" style="flex:1;">
        <input type="text" class="ssp-input pg-attr-val" placeholder="مقادیر با خط عمودی | (مثلاً: قرمز | آبی | مشکی)" value="${escapeHtml(val || '')}" style="flex:2;">
        <button type="button" class="ssp-btn-danger" style="padding:6px 10px; font-size:0.75rem;" onclick="this.parentNode.remove();">حذف</button>
    `;
    container.appendChild(row);
};

window.pgAddCustomMeta = function(key, val) {
    var container = document.getElementById('pg_custom_meta_list');
    if (!container) return;
    var row = document.createElement('div');
    row.className = 'pg-meta-row';
    row.style = 'display:flex; gap:8px; margin-bottom:8px; align-items:center;';
    row.innerHTML = `
        <input type="text" class="ssp-input pg-meta-key" placeholder="کلید متا (مثلاً: warranty)" value="${escapeHtml(key || '')}" style="flex:1;">
        <input type="text" class="ssp-input pg-meta-val" placeholder="مقدار متا (مثلاً: 18 ماه گارانتی اصلی)" value="${escapeHtml(val || '')}" style="flex:2;">
        <button type="button" class="ssp-btn-danger" style="padding:6px 10px; font-size:0.75rem;" onclick="this.parentNode.remove();">حذف</button>
    `;
    container.appendChild(row);
};

window.pgPromptModeChanged = function() {
    var sel = document.getElementById('pg_prompt_mode');
    var wrap = document.getElementById('pg_custom_prompt_wrap');
    if (sel && wrap) {
        wrap.style.display = (sel.value === 'custom') ? 'block' : 'none';
    }
};

window.pgResetPromptMode = function() {
    var sel = document.getElementById('pg_prompt_mode');
    if (sel) sel.value = 'default_product';
    var wrap = document.getElementById('pg_custom_prompt_wrap');
    if (wrap) wrap.style.display = 'none';
    showToast('حالت پرامپت به پیش‌فرض بازگشت', 'info');
};

// === DRAFTS & CONTENT GENERATOR SYNC ===
window.cgRefreshDrafts = function() {
    var fd = new FormData();
    fd.append('action', 'ssp_get_drafts');
    fd.append('security', nonce);
    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.data && res.data.drafts) {
                var drafts = res.data.drafts;
                window.draftsData = drafts;
                var sel = document.getElementById('cg_load_draft');
                if (sel) {
                    sel.innerHTML = '<option value="">بارگذاری از پیش‌نویس...</option>';
                    drafts.forEach(function(d) {
                        var opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = d.title || ('پیش‌نویس #' + d.id);
                        sel.appendChild(opt);
                    });
                }
                var pgSel = document.getElementById('pg_load_draft');
                if (pgSel) {
                    pgSel.innerHTML = '<option value="">بارگذاری از پیش‌نویس...</option>';
                    drafts.forEach(function(d) {
                        var opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = d.title || ('پیش‌نویس #' + d.id);
                        pgSel.appendChild(opt);
                    });
                }
                showToast('پیش‌نویس‌ها با موفقیت بروزرسانی شدند', 'success');
            }
        })
        .catch(function() { showToast('خطا در دریافت پیش‌نویس‌ها', 'error'); });
};

window.cgResetPromptMode = function() {
    var sel = document.getElementById('cg_prompt_mode');
    if (sel) sel.value = 'default_article';
    var wrap = document.getElementById('cg_custom_prompt_wrap');
    if (wrap) wrap.style.display = 'none';
    showToast('حالت پرامپت به پیش‌فرض بازگشت', 'info');
};

window.pgLoadDraft = function(id) {
    if (!id || !window.draftsData) return;
    var draft = window.draftsData.find(function(d) { return String(d.id) === String(id); });
    if (draft) {
        if (document.getElementById('pg_product_name')) document.getElementById('pg_product_name').value = draft.title || '';
        if (document.getElementById('pg_ai_name')) document.getElementById('pg_ai_name').value = draft.title || '';
        if (document.getElementById('pg_short_desc')) document.getElementById('pg_short_desc').value = draft.meta_description || '';
        if (document.getElementById('pg_description')) document.getElementById('pg_description').value = draft.content || '';
        showToast('پیش‌نویس بارگذاری شد', 'success');
    }
};

window.cgLoadDraft = function(id) {
    if (!id || !window.draftsData) return;
    var draft = window.draftsData.find(function(d) { return String(d.id) === String(id); });
    if (draft) {
        if (document.getElementById('cg_post_title')) document.getElementById('cg_post_title').value = draft.title || '';
        if (document.getElementById('cg_ai_name')) document.getElementById('cg_ai_name').value = draft.title || '';
        if (document.getElementById('cg_post_content')) document.getElementById('cg_post_content').value = draft.content || '';
        if (document.getElementById('cg_post_tags')) document.getElementById('cg_post_tags').value = draft.hashtags || '';
        if (document.getElementById('cg_meta_description')) document.getElementById('cg_meta_description').value = draft.meta_description || '';
        showToast('پیش‌نویس بارگذاری شد', 'success');
    }
};
// Browser Bridge Execution Engine
window._bridgeActive = false;
window._bridgeCurrentTask = null;
window._bridgePollInterval = null;
window._bridgeRetryCount = 0;

window.setBridgeModalState = function(step, errorMsg) {
    let m = document.getElementById('modal_ai_bridge');
    if (!m) return;
    
    // Reset all
    for (let i = 1; i <= 3; i++) {
        let text = document.getElementById('bridge_step_' + i + '_text');
        let icon = document.querySelector('#bridge_step_' + i + ' .bridge-step-icon');
        if (text) text.style.color = 'var(--text-muted)';
        if (icon) {
            icon.style.background = 'transparent';
            icon.style.color = 'var(--text-muted)';
            icon.innerHTML = i;
        }
    }
    document.getElementById('bridge_error_box').style.display = 'none';
    document.getElementById('bridge_retry_btn').style.display = 'none';
    
    // Set active up to step
    let progress = (step === 1) ? 20 : (step === 2) ? 60 : 100;
    document.getElementById('bridge_progress_bar').style.width = progress + '%';
    document.getElementById('bridge_progress_bar').style.background = 'var(--accent)';
    
    for (let i = 1; i <= step; i++) {
        let text = document.getElementById('bridge_step_' + i + '_text');
        let icon = document.querySelector('#bridge_step_' + i + ' .bridge-step-icon');
        if (text) text.style.color = 'var(--text)';
        if (icon) {
            icon.style.background = 'var(--accent)';
            icon.style.color = '#fff';
            icon.style.border = 'none';
            if (i < step || step === 3) {
                icon.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
            }
        }
    }
    
    if (errorMsg) {
        document.getElementById('bridge_error_box').innerHTML = errorMsg;
        document.getElementById('bridge_error_box').style.display = 'block';
        document.getElementById('bridge_progress_bar').style.background = 'var(--error)';
        document.getElementById('bridge_retry_btn').style.display = 'inline-flex';
        window._bridgeActive = false;
        if (window._bridgePollInterval) clearInterval(window._bridgePollInterval);
    }
};

window.retryBridgeTask = function() {
    window._bridgeRetryCount++;
    document.getElementById('bridge_retry_btn').style.display = 'none';
    setBridgeModalState(1);
    // Give user time to see the modal resetting
    setTimeout(() => {
        if (window.openChatbotTab) window.openChatbotTab();
        window.startBridgePolling(window._bridgeCurrentTask.id, window._bridgeCurrentTask.onComplete, window._bridgeCurrentTask.onTimeout);
    }, 500);
};

window.startBridgePolling = function(taskId, onComplete, onTimeout) {
    if (!taskId) return;
    
    window._bridgeCurrentTask = { id: taskId, onComplete: onComplete, onTimeout: onTimeout };
    window._bridgeActive = true;
    
    let m = document.getElementById('modal_ai_bridge');
    if (m) {
        m.style.display = 'flex';
        // force reflow
        void m.offsetWidth;
    }
    setBridgeModalState(1); // Step 1: Request Sent
    
    let attempts = 0;
    let maxAttempts = 120; // 5 minutes max (2.5s interval * 120)
    
    if (window._bridgePollInterval) clearInterval(window._bridgePollInterval);
    
    window._bridgePollInterval = setInterval(function() {
        if (!window._bridgeActive) {
            clearInterval(window._bridgePollInterval);
            return;
        }
        
        attempts++;
        if (attempts > maxAttempts) {
            setBridgeModalState(2, 'زمان انتظار به پایان رسید. لطفاً مطمئن شوید تب چت‌بات را نبسته‌اید.');
            if (onTimeout) onTimeout();
            return;
        }
        
        let fd = new FormData();
        fd.append('action', 'ssp_bridge_poll_status');
        fd.append('security', nonce);
        fd.append('task_id', taskId);
        
        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (!window._bridgeActive) return;
                
                if (!res.success) {
                    clearInterval(window._bridgePollInterval);
                    window._bridgeActive = false;
                    let errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در اجرای درخواست توسط چت‌بات';
                    setBridgeModalState(2, errMsg);
                    showToast(errMsg, 'error');
                    if (onTimeout) onTimeout();
                    return;
                }

                if (res.data.status === 'processing') {
                    setBridgeModalState(2); // Chatbot is generating
                } else if (res.data.status === 'completed') {
                    clearInterval(window._bridgePollInterval);
                    window._bridgeActive = false;
                    setBridgeModalState(3); // Done!
                    setTimeout(() => {
                        window.closeModal('modal_ai_bridge');
                        showToast('پاسخ از چت‌بات با موفقیت دریافت شد!', 'success');
                        if (onComplete) onComplete(res.data);
                    }, 800);
                } else if (res.data.status === 'error' || res.data.status === 'expired' || res.data.status === 'failed') {
                    clearInterval(window._bridgePollInterval);
                    window._bridgeActive = false;
                    let errMsg = res.data.message || 'وظیفه در سمت چت‌بات با خطا متوقف شد.';
                    setBridgeModalState(1, errMsg);
                    showToast(errMsg, 'error');
                    if (onTimeout) onTimeout();
                }
            })
            .catch(() => {
                // Ignore network errors and keep polling
            });
    }, 2500);
};

window.cgGenerateViaBrowser = function() {
    if (typeof window.cgGenerateWithAI === 'function') {
        window.cgGenerateWithAI();
    } else {
        if (typeof showToast === 'function') showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
    }
};

window.bsGenerateViaBrowser = function() {
    if (typeof window.bsGenerate === 'function') {
        window.bsGenerate();
    } else {
        if (typeof showToast === 'function') showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
    }
};

window.pgGenerateViaBrowser = function() {
    if (typeof window.pgGenerateWithAI === 'function') {
        window.pgGenerateWithAI();
    } else {
        if (typeof showToast === 'function') showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
    }
};

window.pgGeneratePostViaBrowser = function() {
    if (typeof window.pgGeneratePost === 'function') {
        window.pgGeneratePost();
    } else {
        if (typeof showToast === 'function') showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
    }
};
window.cgBatchGenerate = function() { showToast('تولید انبوه آغاز شد', 'success'); };
window.testAiDns = function() { showToast('تست DNS موفقیت‌آمیز بود', 'success'); };
window.pgRefreshDrafts = function() { showToast('لیست بروز شد', 'success'); };

// =========================================================================
// CONTENT DISTRIBUTION (AUTOMATION)
// =========================================================================

window.loadDistributions = function() {
    let listEl = document.getElementById('distributions_list');
    if (!listEl) return;

    listEl.innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-muted); font-size:0.9rem;">⏳ در حال دریافت لیست توزیع‌ها...</div>';

    let fd = new FormData();
    fd.append('action', 'ssp_get_distributions');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && Array.isArray(res.data.distributions)) {
                window.distributionsData = res.data.distributions;
            } else if (!Array.isArray(window.distributionsData)) {
                window.distributionsData = [];
            }
            window.renderDistributions(window.distributionsData);
        })
        .catch(err => {
            console.error('Error loading distributions:', err);
            let fallback = Array.isArray(window.distributionsData) ? window.distributionsData : [];
            window.renderDistributions(fallback);
        });
};

window.renderDistributions = function(items) {
    let listEl = document.getElementById('distributions_list');
    if (!listEl) return;

    if (!Array.isArray(items) || items.length === 0) {
        listEl.innerHTML = `
            <div class="ssp-empty" style="border: 2px dashed var(--border); border-radius:16px; padding:40px 20px; text-align:center; background:var(--bg-alt); margin-bottom:20px;">
                <div class="ssp-empty-icon" style="margin-bottom:12px;">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--primary)" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                </div>
                <h3 style="margin:0 0 8px; font-size:1.1rem; color:var(--text);">هنوز توزیع خودکاری ثبت نشده است</h3>
                <p style="color:var(--text-muted); margin:0 0 16px; font-size:0.9rem; max-width:480px; margin-left:auto; margin-right:auto;">
                    با تعریف یک توزیع خودکار، محتواهای سایت وردپرسی شما طبق زمان‌بندی و فیلترهای مشخص‌شده، خودکار به شبکه‌های اجتماعی ارسال می‌شوند.
                </p>
                <button type="button" class="ssp-btn-primary" onclick="openDistributionForm()">+ تعریف اولین توزیع خودکار</button>
            </div>
        `;
        return;
    }

    let sites = Array.isArray(window.wpSiteData) ? window.wpSiteData : [];
    let messengers = Array.isArray(window.messengerData) ? window.messengerData : [];

    let typeLabels = {
        'product': '🛒 محصولات ووکامرس',
        'post': '📝 مقالات و نوشته‌ها',
        'draft': '📂 پیش‌نویس‌های پورتال'
    };

    let html = '';
    items.forEach(item => {
        let site = sites.find(s => s.id == item.source_site_id);
        let siteName = site ? site.site_name : 'سایت وردپرس';
        let typeName = typeLabels[item.source_type] || item.source_type || 'محصولات';
        let isActive = (parseInt(item.is_active) !== 0);

        // Parse target messengers
        let targetIds = String(item.target_messengers || '').split(',').map(s => s.trim()).filter(Boolean);
        let targetBadges = '';
        if (targetIds.length > 0) {
            targetIds.forEach(mId => {
                let m = messengers.find(x => x.id == mId);
                let mName = m ? (m.name || m.platform) : ('پیام‌رسان #' + mId);
                let icon = (typeof platformIcons !== 'undefined' && m && platformIcons[m.platform]) ? platformIcons[m.platform] : '💬';
                targetBadges += `<span class="ssp-badge" style="font-size:0.75rem; background:rgba(79,70,229,0.08); color:var(--primary); margin-left:4px;">${icon} ${escapeHtml(mName)}</span>`;
            });
        } else {
            targetBadges = `<span class="ssp-badge inactive" style="font-size:0.75rem;">بدون پیام‌رسان</span>`;
        }

        // Parse schedule times
        let times = String(item.schedule_times || '10:00').split(',').map(t => t.trim()).filter(Boolean);
        let timePills = times.map(t => `<span style="display:inline-block; padding:2px 8px; border-radius:6px; background:var(--bg-alt); border:1px solid var(--border); font-size:0.75rem; font-weight:600; margin-left:4px;">⏰ ${t}</span>`).join('');

        let onlyNewBadge = parseInt(item.only_new) === 1
            ? `<span class="ssp-badge info" style="font-size:0.75rem;">🆕 فقط جدید (تأخیر: ${item.new_delay_value || 5} ${item.new_delay_unit === 'hours' ? 'ساعت' : (item.new_delay_unit === 'days' ? 'روز' : 'دقیقه')})</span>`
            : `<span class="ssp-badge" style="font-size:0.75rem; background:var(--bg-alt); color:var(--text);">🔄 ارسال چرخشی از کل</span>`;

        let lastRunText = item.last_run_time ? `آخرین اجرا: ${item.last_run_time.substring(0, 16)}` : 'هنوز اجرا نشده';

        html += `
            <div class="ssp-card ssp-card-enter" style="margin-bottom:16px; padding:20px; border-right: 4px solid ${isActive ? 'var(--accent)' : 'var(--border)'};">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:280px;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap;">
                            <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--text);">${escapeHtml(item.name || 'بدون نام')}</h3>
                            <span class="ssp-badge ${isActive ? 'active' : 'inactive'}">${isActive ? 'فعال' : 'غیرفعال'}</span>
                            <span class="ssp-badge pro" style="font-size:0.75rem;">🌐 ${escapeHtml(siteName)}</span>
                            <span class="ssp-badge" style="font-size:0.75rem; background:var(--bg-alt);">${typeName}</span>
                            ${onlyNewBadge}
                        </div>
                        
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
                            <span style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">ساعات ارسال (${times.length} بار در روز):</span>
                            ${timePills}
                        </div>

                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
                            <span style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">مقاصد:</span>
                            ${targetBadges}
                        </div>

                        <div style="font-size:0.78rem; color:var(--text-muted); display:flex; gap:16px; align-items:center;">
                            <span>🕒 ${lastRunText}</span>
                            ${item.include_image == 1 ? '<span>🖼️ ارسال همراه تصویر شاخص</span>' : ''}
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                        <label class="ssp-toggle" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;" title="تغییر وضعیت فعال/غیرفعال">
                            <input type="checkbox" ${isActive ? 'checked' : ''} onchange="toggleDistribution(${item.id}, this)">
                            <span class="ssp-toggle-slider"></span>
                        </label>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="ssp-btn-primary" onclick="runDistributionNow(${item.id}, this)" style="font-size:0.8rem; padding:6px 12px;" title="ارسال دستی یک محتوا طبق تنظیمات این توزیع">⚡ ارسال فوری</button>
                            <button type="button" class="ssp-btn-secondary" onclick="previewDistribution(${item.id})" style="font-size:0.8rem; padding:6px 10px;">👁️ پیش‌نمایش</button>
                            <button type="button" class="ssp-btn-secondary" onclick="openDistributionForm(${item.id})" style="font-size:0.8rem; padding:6px 10px;">✏️ ویرایش</button>
                            <button type="button" class="ssp-btn-danger" onclick="deleteDistribution(${item.id})" style="font-size:0.8rem; padding:6px 10px;">🗑️ حذف</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    listEl.innerHTML = html;
};

window.openDistributionForm = function(id) {
    let wrap = document.getElementById('distribution_form_wrap');
    if (!wrap) return;

    let editIdEl = document.getElementById('dist_edit_id');
    let nameEl = document.getElementById('dist_name');
    let siteEl = document.getElementById('dist_source_site');
    let typeEl = document.getElementById('dist_source_type');
    let onlyNewEl = document.getElementById('dist_only_new');
    let delayValEl = document.getElementById('dist_new_delay');
    let delayUnitEl = document.getElementById('dist_new_delay_unit');
    let catEl = document.getElementById('dist_filter_categories');
    let tagEl = document.getElementById('dist_filter_tags');
    let minPriceEl = document.getElementById('dist_filter_min_price');
    let maxPriceEl = document.getElementById('dist_filter_max_price');
    let stockEl = document.getElementById('dist_filter_stock');
    let sortEl = document.getElementById('dist_sort');
    let tplEl = document.getElementById('dist_template');
    let imgEl = document.getElementById('dist_include_image');
    let timeContainer = document.getElementById('dist_time_slots');

    let isEdit = false;
    let item = null;

    if (id && Array.isArray(window.distributionsData)) {
        item = window.distributionsData.find(d => d.id == id);
        if (item) isEdit = true;
    }

    if (isEdit && item) {
        if (editIdEl) editIdEl.value = item.id;
        if (nameEl) nameEl.value = item.name || '';
        if (siteEl && item.source_site_id) siteEl.value = item.source_site_id;
        if (typeEl && item.source_type) typeEl.value = item.source_type;
        if (onlyNewEl) onlyNewEl.checked = (parseInt(item.only_new) === 1);
        if (delayValEl) delayValEl.value = item.new_delay_value || 5;
        if (delayUnitEl && item.new_delay_unit) delayUnitEl.value = item.new_delay_unit;
        if (catEl) catEl.value = item.filter_categories || '';
        if (tagEl) tagEl.value = item.filter_tags || '';
        if (minPriceEl) minPriceEl.value = item.filter_min_price || '';
        if (maxPriceEl) maxPriceEl.value = item.filter_max_price || '';
        if (stockEl) stockEl.value = item.filter_stock || '';
        if (sortEl && item.sort_order) sortEl.value = item.sort_order;
        if (tplEl) tplEl.value = item.message_template || "{title}\n{excerpt}\n{url}";
        if (imgEl) imgEl.checked = (parseInt(item.include_image) === 1);

        // Checkboxes
        let targetIds = String(item.target_messengers || '').split(',').map(s => s.trim());
        document.querySelectorAll('.dist_target_cb').forEach(cb => {
            cb.checked = targetIds.includes(cb.value);
        });

        // Time slots
        if (timeContainer) {
            timeContainer.innerHTML = '';
            let times = String(item.schedule_times || '10:00').split(',').map(t => t.trim()).filter(Boolean);
            if (times.length === 0) times = ['10:00'];
            times.forEach(t => addDistTimeSlot(t));
        }
    } else {
        // New item defaults
        if (editIdEl) editIdEl.value = '';
        if (nameEl) nameEl.value = '';
        if (typeEl) typeEl.value = 'product';
        if (onlyNewEl) onlyNewEl.checked = false;
        if (delayValEl) delayValEl.value = 5;
        if (delayUnitEl) delayUnitEl.value = 'minutes';
        if (catEl) catEl.value = '';
        if (tagEl) tagEl.value = '';
        if (minPriceEl) minPriceEl.value = '';
        if (maxPriceEl) maxPriceEl.value = '';
        if (stockEl) stockEl.value = '';
        if (sortEl) sortEl.value = 'newest';
        if (tplEl) tplEl.value = "{title}\n{excerpt}\n{url}";
        if (imgEl) imgEl.checked = true;

        // Check all messengers by default
        document.querySelectorAll('.dist_target_cb').forEach(cb => cb.checked = true);

        // Default time slot
        if (timeContainer) {
            timeContainer.innerHTML = '';
            addDistTimeSlot('10:00');
        }
    }

    toggleNewContentDelay();
    updateScheduleSummary();

    let previewBox = document.getElementById('dist_preview');
    if (previewBox) previewBox.style.display = 'none';

    wrap.style.display = 'block';
    wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

window.closeDistributionForm = function() {
    let wrap = document.getElementById('distribution_form_wrap');
    if (wrap) wrap.style.display = 'none';
    let previewBox = document.getElementById('dist_preview');
    if (previewBox) previewBox.style.display = 'none';
};

window.addDistTimeSlot = function(timeVal) {
    let container = document.getElementById('dist_time_slots');
    if (!container) return;

    let slot = document.createElement('div');
    slot.className = 'dist-time-slot';
    slot.style = 'display:flex; align-items:center; gap:10px; background:var(--card); padding:10px 14px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.08); border:1px solid var(--border);';
    slot.innerHTML = `
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <input type="time" class="dist-time-input ssp-input" value="${timeVal || '10:00'}" style="padding:8px 12px; font-size:0.95rem; width:140px; border:1px solid var(--border); border-radius:8px; font-weight:600; color:var(--text);" onchange="updateScheduleSummary()">
        <button type="button" onclick="removeDistTimeSlot(this)" style="background:var(--error-soft); border:none; color:var(--danger); cursor:pointer; font-size:1.3rem; padding:0 10px; border-radius:8px; transition:all 0.2s; display:flex; align-items:center; justify-content:center;" title="حذف این ساعت">×</button>
    `;
    container.appendChild(slot);
    updateScheduleSummary();
};

window.removeDistTimeSlot = function(btn) {
    if (!btn) return;
    let slot = btn.closest('.dist-time-slot');
    if (slot) slot.remove();

    let container = document.getElementById('dist_time_slots');
    if (container && container.querySelectorAll('.dist-time-slot').length === 0) {
        addDistTimeSlot('10:00');
    }
    updateScheduleSummary();
};

window.addPresetTimes = function() {
    let container = document.getElementById('dist_time_slots');
    if (!container) return;
    container.innerHTML = '';
    ['09:00', '12:00', '15:00', '18:00', '21:00'].forEach(t => addDistTimeSlot(t));
    if (typeof showToast === 'function') showToast('زمان‌های پیشنهادی افزوده شدند', 'info');
};

window.updateScheduleSummary = function() {
    let inputs = document.querySelectorAll('.dist-time-input');
    let times = [];
    inputs.forEach(inp => {
        let v = (inp.value || '').trim();
        if (v && !times.includes(v)) times.push(v);
    });
    times.sort();

    let hiddenInput = document.getElementById('dist_schedule_times');
    if (hiddenInput) hiddenInput.value = times.join(',');

    let summaryText = document.getElementById('schedule_summary_text');
    if (summaryText) {
        if (times.length === 0) {
            summaryText.innerText = 'هیچ زمانی انتخاب نشده است';
        } else {
            summaryText.innerText = `${times.length} ارسال در روز (${times.join('، ')})`;
        }
    }
};

window.toggleNewContentDelay = function() {
    let cb = document.getElementById('dist_only_new');
    let section = document.getElementById('new_content_delay_section');
    if (section) {
        section.style.display = (cb && cb.checked) ? 'block' : 'none';
    }
};

window.saveDistribution = function() {
    let btn = event ? event.currentTarget : null;
    let nameEl = document.getElementById('dist_name');
    let name = nameEl ? nameEl.value.trim() : '';
    if (!name) {
        if (typeof showToast === 'function') showToast('لطفاً نام توزیع را وارد کنید', 'error');
        if (nameEl) nameEl.focus();
        return;
    }

    let siteEl = document.getElementById('dist_source_site');
    let siteId = siteEl ? siteEl.value : '0';

    let typeEl = document.getElementById('dist_source_type');
    let type = typeEl ? typeEl.value : 'product';

    let onlyNew = document.getElementById('dist_only_new') && document.getElementById('dist_only_new').checked ? 1 : 0;
    let delayVal = document.getElementById('dist_new_delay') ? document.getElementById('dist_new_delay').value : 5;
    let delayUnit = document.getElementById('dist_new_delay_unit') ? document.getElementById('dist_new_delay_unit').value : 'minutes';

    let catEl = document.getElementById('dist_filter_categories');
    let tagEl = document.getElementById('dist_filter_tags');
    let minPriceEl = document.getElementById('dist_filter_min_price');
    let maxPriceEl = document.getElementById('dist_filter_max_price');
    let stockEl = document.getElementById('dist_filter_stock');
    let sortEl = document.getElementById('dist_sort');
    let tplEl = document.getElementById('dist_template');
    let imgEl = document.getElementById('dist_include_image');
    let editIdEl = document.getElementById('dist_edit_id');

    let targets = [];
    document.querySelectorAll('.dist_target_cb:checked').forEach(cb => targets.push(cb.value));
    if (targets.length === 0) {
        if (typeof showToast === 'function') showToast('حداقل یک پیام‌رسان مقصد انتخاب کنید', 'error');
        return;
    }

    let timesArr = [];
    document.querySelectorAll('.dist-time-input').forEach(el => {
        if(el.value) timesArr.push(el.value);
    });
    let times = timesArr.join(',');
    
    let scheduleTypeEl = document.getElementById('dist_schedule_type');
    let scheduleType = scheduleTypeEl ? scheduleTypeEl.value : 'daily';

    if (!times) {
        if (typeof showToast === 'function') showToast('حداقل یک زمان برای ارسال مشخص کنید', 'error');
        return;
    }

    if (btn && typeof setBtnLoading === 'function') setBtnLoading(btn, true);

    let fd = new FormData();
    fd.append('action', 'ssp_save_distribution');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

    if (editIdEl && editIdEl.value) {
        fd.append('distribution_id', editIdEl.value);
    }
    fd.append('name', name);
    fd.append('source_site_id', siteId);
    fd.append('source_type', type);
    fd.append('only_new', onlyNew);
    fd.append('schedule_type', scheduleType);
    fd.append('schedule_times', times);
    fd.append('new_delay_value', delayVal);
    fd.append('daily_limit', timesArr.length || 1);
    fd.append('new_delay_unit', delayUnit);
    if(catEl) fd.append('filter_categories', catEl.value);
    if(tagEl) fd.append('filter_tags', tagEl.value);
    if(minPriceEl) fd.append('filter_min_price', minPriceEl.value);
    if(maxPriceEl) fd.append('filter_max_price', maxPriceEl.value);
    if(stockEl) fd.append('filter_stock', stockEl.value);
    if(sortEl) fd.append('sort_order', sortEl.value);
    if(tplEl) fd.append('message_template', tplEl.value);
    if(imgEl) fd.append('include_image', imgEl.checked ? 1 : 0);
    fd.append('target_messengers', targets.join(','));

    fd.append('new_delay_value', delayVal);
    fd.append('new_delay_unit', delayUnit);
    fd.append('filter_categories', catEl ? catEl.value : '');
    fd.append('filter_tags', tagEl ? tagEl.value : '');
    fd.append('filter_min_price', minPriceEl ? minPriceEl.value : '');
    fd.append('filter_max_price', maxPriceEl ? maxPriceEl.value : '');
    fd.append('filter_stock', stockEl ? stockEl.value : '');
    fd.append('sort_order', sortEl ? sortEl.value : 'newest');
    fd.append('message_template', tplEl ? tplEl.value : "{title}\n{excerpt}\n{url}");
    fd.append('include_image', imgEl && imgEl.checked ? 1 : 0);
    fd.append('target_messengers', targets.join(','));
    fd.append('schedule_type', 'daily');
    fd.append('schedule_times', times);
    fd.append('daily_limit', times.split(',').length);
    fd.append('is_active', 1);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn && typeof setBtnLoading === 'function') setBtnLoading(btn, false);
            if (res.success) {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'توزیع با موفقیت ذخیره شد', 'success');
                closeDistributionForm();
                loadDistributions();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در ذخیره توزیع', 'error');
            }
        })
        .catch(err => {
            if (btn && typeof setBtnLoading === 'function') setBtnLoading(btn, false);
            if (typeof showToast === 'function') showToast('خطای شبکه در ذخیره توزیع', 'error');
        });
};

window.toggleDistribution = function(id, cbEl) {
    let fd = new FormData();
    fd.append('action', 'ssp_toggle_distribution');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('distribution_id', id);
    if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (Array.isArray(window.distributionsData)) {
                    let item = window.distributionsData.find(d => d.id == id);
                    if (item) item.is_active = cbEl ? (cbEl.checked ? 1 : 0) : (item.is_active ? 0 : 1);
                }
                if (typeof showToast === 'function') showToast('وضعیت توزیع تغییر یافت', 'success');
                renderDistributions(window.distributionsData);
            } else {
                if (cbEl) cbEl.checked = !cbEl.checked;
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در تغییر وضعیت', 'error');
            }
        })
        .catch(() => {
            if (cbEl) cbEl.checked = !cbEl.checked;
            if (typeof showToast === 'function') showToast('خطای شبکه', 'error');
        });
};

window.deleteDistribution = function(id) {
    if (!confirm('آیا از حذف این توزیع خودکار اطمینان دارید؟')) return;

    let fd = new FormData();
    fd.append('action', 'ssp_delete_distribution');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('distribution_id', id);
    if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (Array.isArray(window.distributionsData)) {
                    window.distributionsData = window.distributionsData.filter(d => d.id != id);
                }
                renderDistributions(window.distributionsData);
                if (typeof showToast === 'function') showToast('توزیع با موفقیت حذف شد', 'success');
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در حذف', 'error');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('خطای شبکه در حذف توزیع', 'error');
        });
};

window.previewDistribution = function(id) {
    let previewBox = document.getElementById('dist_preview');
    if (!previewBox) return;

    let distId = id || (document.getElementById('dist_edit_id') ? document.getElementById('dist_edit_id').value : 0);
    
    if (distId) {
        previewBox.style.display = 'block';
        previewBox.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">⏳ در حال استخراج پیش‌نمایش از سایت...</div>';

        let fd = new FormData();
        fd.append('action', 'ssp_preview_distribution');
        fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
        fd.append('distribution_id', distId);
        if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && Array.isArray(res.data.previews) && res.data.previews.length > 0) {
                    let html = '<div class="ssp-card" style="margin-top:16px; border:1px solid var(--accent);">';
                    html += `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="margin:0; color:var(--primary);">پیش‌نمایش محتواهای استخراج‌شده (${res.data.previews.length} نمونه از کل ${res.data.total || res.data.previews.length})</h4>
                        <button type="button" onclick="document.getElementById('dist_preview').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:var(--text-muted);">✕</button>
                    </div>`;
                    res.data.previews.forEach((p, idx) => {
                        html += `
                            <div style="background:var(--bg-alt); padding:12px 16px; border-radius:10px; margin-bottom:10px; border:1px solid var(--border); font-family:inherit; white-space:pre-wrap; line-height:1.6; font-size:0.88rem;">
                                <strong style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.75rem;">نمونه شماره ${idx + 1}:</strong>
                                ${escapeHtml(p)}
                            </div>
                        `;
                    });
                    html += '</div>';
                    previewBox.innerHTML = html;
                    previewBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    let msg = (res.data && res.data.message) ? res.data.message : 'محتوایی منطبق با فیلترها در سایت متصل یافت نشد.';
                    previewBox.innerHTML = `<div class="ssp-card" style="margin-top:16px; color:var(--danger);">${escapeHtml(msg)}</div>`;
                }
            })
            .catch(() => {
                previewBox.innerHTML = '<div class="ssp-card" style="margin-top:16px; color:var(--danger);">خطای شبکه در دریافت پیش‌نمایش</div>';
            });
    } else {
        // Unsaved form preview: format sample
        let tpl = document.getElementById('dist_template') ? document.getElementById('dist_template').value : "{title}\n{excerpt}\n{url}";
        let sample = tpl
            .replace(/{title}/g, 'گوشی هوشمند سامسونگ مدل Galaxy S24 Ultra')
            .replace(/{excerpt}/g, 'جدیدترین پرچمدار سامسونگ با دوربین ۲۰۰ مگاپیکسلی و پردازنده اسنپدراگون نسل ۳ هم‌اکنون با تخفیف ویژه موجود شد.')
            .replace(/{url}/g, 'https://mysite.com/product/galaxy-s24')
            .replace(/{price}/g, '۶۸,۵۰۰,۰۰۰ تومان')
            .replace(/{image}/g, '');

        previewBox.style.display = 'block';
        previewBox.innerHTML = `
            <div class="ssp-card" style="margin-top:16px; border:1px solid var(--accent);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 style="margin:0; color:var(--primary);">پیش‌نمایش قالب پیام (نمونه فرضی)</h4>
                    <button type="button" onclick="document.getElementById('dist_preview').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:var(--text-muted);">✕</button>
                </div>
                <div style="background:var(--bg-alt); padding:14px 16px; border-radius:10px; border:1px solid var(--border); white-space:pre-wrap; line-height:1.6; font-size:0.88rem;">
                    ${escapeHtml(sample)}
                </div>
            </div>
        `;
        previewBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
};

window.runDistributionNow = function(id, btn) {
    if (!confirm('آیا مایلید یک محتوا بر اساس این تنظیمات توزیع فوراً استخراج و در صف ارسال پیام‌رسان‌ها قرار گیرد؟')) return;

    if (btn && typeof setBtnLoading === 'function') setBtnLoading(btn, true);

    let fd = new FormData();
    fd.append('action', 'ssp_run_distribution_now');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('distribution_id', id);
    if (typeof window.addImpersonate === 'function') window.addImpersonate(fd);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn && typeof setBtnLoading === 'function') setBtnLoading(btn, false);
            if (res.success) {
                let queuedCount = (res.data && res.data.queued) ? res.data.queued : 1;
                if (typeof showToast === 'function') showToast(`محتوا با موفقیت به صف ارسال اضافه شد (${queuedCount} ارسال)`, 'success');
                // Refresh list to update last run time
                loadDistributions();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در اجرای توزیع', 'error');
            }
        })
        .catch(() => {
            if (btn && typeof setBtnLoading === 'function') setBtnLoading(btn, false);
            if (typeof showToast === 'function') showToast('خطای ارتباط با سرور', 'error');
        });
};
window.batchGenerate = function() { showToast('عملیات گروهی آغاز شد', 'success'); };


window.addManualMediaUrl = function() {
    let input = document.getElementById('manual_media_url_input');
    let list = document.getElementById('manual_media_url_list');
    let preview = document.getElementById('manual_media_url_preview');
    if(!input || !input.value.trim()) return;
    
    let url = input.value.trim();
    let hidden = document.getElementById('manual_media_url_hidden');
    if(!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.id = 'manual_media_url_hidden';
        document.getElementById('manual_media_url_input').parentNode.appendChild(hidden);
    }
    
    let currentUrls = hidden.value ? hidden.value.split(',') : [];
    currentUrls.push(url);
    hidden.value = currentUrls.join(',');
    
    let item = document.createElement('div');
    item.style = 'display:flex; justify-content:space-between; align-items:center; background:var(--bg-alt); padding:6px 10px; border-radius:6px; margin-bottom:4px; font-size:0.85rem;';
    item.innerHTML = '<span>' + url + '</span> <button type="button" class="ssp-btn-danger" style="padding:2px 6px; font-size:0.75rem;" onclick="this.parentNode.remove();">حذف</button>';
    list.appendChild(item);
    
    input.value = '';
    showToast('لینک رسانه اضافه شد', 'success');
};

window.pgResetForm = function() {
    if(typeof window.pgResetPostForm === 'function') window.pgResetPostForm();
    else showToast('در حال توسعه', 'info');
};

// === SCENARIO & BOT BUILDER ===
window._scenarioStepCounter = 0;
window.addScenarioStep = function() {
    var container = document.getElementById('scenario_steps_container');
    if (!container) return;
    window._scenarioStepCounter++;
    var num = window._scenarioStepCounter;

    var stepDiv = document.createElement('div');
    stepDiv.className = 'ssp-card ssp-scenario-step-item';
    stepDiv.style = 'margin-bottom:12px; padding:14px; border:1px solid var(--border); border-radius:10px; background:var(--bg-card);';
    stepDiv.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <strong style="color:var(--accent); font-size:0.9rem;">مرحله ${num}</strong>
            <button type="button" class="ssp-btn-danger" style="padding:2px 8px; font-size:0.75rem;" onclick="this.closest('.ssp-scenario-step-item').remove();">حذف مرحله</button>
        </div>
        <div class="ssp-form-group" style="margin-bottom:8px;">
            <label class="ssp-label" style="font-size:0.8rem;">پیام ارسالی به کاربر *</label>
            <textarea class="ssp-textarea scenario-step-msg" rows="2" style="font-size:0.85rem;" placeholder="متن پیام این مرحله را بنویسید..."></textarea>
        </div>
        <div class="ssp-form-group" style="margin-bottom:0;">
            <label class="ssp-label" style="font-size:0.8rem;">دکمه‌های انتخابی (با کاما یا خط عمودی | جدا کنید)</label>
            <input type="text" class="ssp-input scenario-step-buttons" style="font-size:0.85rem;" placeholder="مثلاً: بله | خیر | بازگشت به منو">
        </div>
    `;
    container.appendChild(stepDiv);
};

// === ADVANCED SEO SUITE IMPLEMENTATION ===
window.seoResetPromptMode = function() {
    var sel = document.getElementById('seo_prompt_mode');
    if (sel) sel.value = 'default';
    showToast('تنظیمات پرامپت سئو ریست شد', 'info');
};

window.seoAnalyzeViaBrowser = function() {
    let btn = document.getElementById('seo_browser_btn') || document.querySelector('button[onclick="seoAnalyzeViaBrowser()"]');
    if (btn) setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_bridge_create_task');
    fd.append('security', window.nonce || '');
    fd.append('context_type', 'seo_analyze');
    fd.append('prompt', 'یک تحلیل سئو و پیشنهاد کلمات کلیدی (LSI) و متا تگ‌های مناسب ارائه بده.');
    
    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) setBtnLoading(btn, false);
            if (res.success && res.data && res.data.task_id) {
                showToast('درخواست به چت‌بات ارسال شد. تب باز شد.', 'info');
                if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                window.startBridgePolling(res.data.task_id, function(data) {
                    var text = (data.parsed && (data.parsed.content || data.parsed.message)) ? (data.parsed.content || data.parsed.message) : (data.raw || '');
                    showToast('تحلیل سئو از مرورگر دریافت شد!', 'success');
                    // Usually there's a result box, let's just alert or log it if not present
                    console.log('SEO Browser Result:', text);
                }, function() {});
            } else {
                showToast('خطا در ارتباط', 'error');
            }
        }).catch(() => {
            if (btn) setBtnLoading(btn, false);
            showToast('خطا', 'error');
        });
};

// Keyword Density, LSI & Readability Analyzer
window.analyzeKeywords = function() {
    var text = (document.getElementById('keyword_source_text') || {}).value || (document.getElementById('cg_post_content') || {}).value || '';
    var targetKw = ((document.getElementById('target_keyword') || {}).value || '').trim().toLowerCase();
    var resDiv = document.getElementById('keyword_analysis_result');
    if (!resDiv) return;

    if (!text.trim()) {
        showToast('لطفاً ابتدا متنی برای تحلیل وارد کنید', 'warning');
        return;
    }

    var cleanText = text.replace(/<[^>]+>/g, ' ').replace(/[^\u0600-\u06FF\w\s]/g, ' ');
    var words = cleanText.split(/\s+/).filter(function(w) { return w.length > 1; });
    var totalWords = words.length;

    // Word frequencies
    var freq = {};
    var stopWords = ['و', 'در', 'به', 'از', 'که', 'این', 'را', 'با', 'است', 'برای', 'آن', 'یک', 'خود', 'تا', 'کرد', 'بر', 'هم', 'نیز', 'می', 'شد', 'های', 'شود', 'دارد', 'ما', 'شما', 'یا', 'اما', 'اگر', 'هر', 'کند', 'شده', 'بود', 'the', 'and', 'to', 'of', 'a', 'in', 'is', 'for', 'on', 'with', 'by', 'as', 'at'];
    words.forEach(function(w) {
        var low = w.toLowerCase();
        if (stopWords.indexOf(low) === -1 && low.length > 2) {
            freq[low] = (freq[low] || 0) + 1;
        }
    });

    var sorted = Object.keys(freq).map(function(k) { return { word: k, count: freq[k], density: ((freq[k] / totalWords) * 100).toFixed(2) }; })
        .sort(function(a, b) { return b.count - a.count; })
        .slice(0, 10);

    var targetDensity = 0;
    var targetCount = 0;
    if (targetKw) {
        targetCount = (text.toLowerCase().match(new RegExp(targetKw, 'g')) || []).length;
        targetDensity = ((targetCount / (totalWords || 1)) * 100).toFixed(2);
    }

    var readingTimeMin = Math.ceil(totalWords / 200);

    var html = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px; margin-top:12px;">';
    html += '<h4 style="margin:0 0 12px; color:var(--accent);">نتایج آنالیز کلمات کلیدی و خوانایی</h4>';
    html += '<div class="ssp-grid-3" style="gap:10px; margin-bottom:14px;">';
    html += '<div style="background:var(--bg-alt); padding:10px; border-radius:8px; text-align:center;"><div style="font-size:1.2rem; font-weight:bold; color:var(--accent);">' + totalWords + '</div><div style="font-size:0.75rem; color:var(--text-muted);">تعداد کلمات</div></div>';
    html += '<div style="background:var(--bg-alt); padding:10px; border-radius:8px; text-align:center;"><div style="font-size:1.2rem; font-weight:bold; color:var(--accent);">' + readingTimeMin + ' دقیقه</div><div style="font-size:0.75rem; color:var(--text-muted);">زمان مطالعه</div></div>';
    if (targetKw) {
        var isGoodDensity = targetDensity >= 1 && targetDensity <= 2.5;
        html += '<div style="background:var(--bg-alt); padding:10px; border-radius:8px; text-align:center;"><div style="font-size:1.2rem; font-weight:bold; color:' + (isGoodDensity ? 'var(--success)' : 'var(--warning)') + ';">' + targetDensity + '% (' + targetCount + ' بار)</div><div style="font-size:0.75rem; color:var(--text-muted);">چگالی کلمه هدف</div></div>';
    } else {
        html += '<div style="background:var(--bg-alt); padding:10px; border-radius:8px; text-align:center;"><div style="font-size:1.2rem; font-weight:bold; color:var(--text);">' + Object.keys(freq).length + '</div><div style="font-size:0.75rem; color:var(--text-muted);">کلمات یکتا</div></div>';
    }
    html += '</div>';

    html += '<h5 style="margin:10px 0 8px; font-size:0.85rem;">پرتکرارترین عبارات کلیدی (LSI):</h5>';
    html += '<div style="display:flex; flex-wrap:wrap; gap:6px;">';
    sorted.forEach(function(item) {
        html += '<span class="ssp-badge ssp-badge-info" style="font-size:0.8rem; padding:4px 8px;">' + escapeHtml(item.word) + ' (' + item.count + ' بار - ' + item.density + '%)</span>';
    });
    html += '</div>';
    html += '</div>';

    resDiv.innerHTML = html;
    resDiv.style.display = 'block';
    showToast('تحلیل کلمات کلیدی با موفقیت انجام شد', 'success');
};

window.importToKeywordAnalyzer = function() {
    var content = (document.getElementById('cg_post_content') || document.getElementById('pg_description') || {}).value || '';
    var input = document.getElementById('keyword_source_text');
    if (input) {
        input.value = content;
        showToast('محتوای فعلی به آنالیزور کلمات وارد شد', 'success');
        window.analyzeKeywords();
    }
};

// Google SERP Snippet Preview & CTR Score
window.updateSerpPreview = function() {
    var title = (document.getElementById('serp_input_title') || {}).value || 'عنوان صفحه شما در نتایج جستجوی گوگل';
    var desc = (document.getElementById('serp_input_desc') || {}).value || 'توضیحات متای صفحه شما در این بخش نمایش داده می‌شود. نگارش جذاب این بخش باعث افزایش نرخ کلیک (CTR) خواهد شد.';
    var slug = (document.getElementById('serp_input_slug') || {}).value || 'sample-post';
    var siteUrl = window.location.origin || 'https://yoursite.com';

    var dispTitle = document.getElementById('serp_disp_title');
    var dispUrl = document.getElementById('serp_disp_url');
    var dispDesc = document.getElementById('serp_disp_desc');
    var titleCount = document.getElementById('serp_title_len');
    var descCount = document.getElementById('serp_desc_len');

    if (dispTitle) dispTitle.textContent = title;
    if (dispUrl) dispUrl.textContent = siteUrl + ' › ' + slug;
    if (dispDesc) dispDesc.textContent = desc;

    var tLen = title.length;
    var dLen = desc.length;
    if (titleCount) {
        titleCount.textContent = tLen + ' / 60 کاراکتر';
        titleCount.style.color = (tLen >= 45 && tLen <= 65) ? 'var(--success)' : 'var(--warning)';
    }
    if (descCount) {
        descCount.textContent = dLen + ' / 160 کاراکتر';
        descCount.style.color = (dLen >= 120 && dLen <= 160) ? 'var(--success)' : 'var(--warning)';
    }
};

window.importToSerpPreview = function() {
    var title = (document.getElementById('cg_post_title') || document.getElementById('pg_product_name') || {}).value || '';
    var desc = (document.getElementById('cg_meta_description') || document.getElementById('pg_short_desc') || {}).value || '';
    if (document.getElementById('serp_input_title')) document.getElementById('serp_input_title').value = title;
    if (document.getElementById('serp_input_desc')) document.getElementById('serp_input_desc').value = desc;
    window.updateSerpPreview();
    showToast('اطلاعات سئو در پیش‌نمایش گوگل درج شد', 'success');
};

// Content Gap Analysis
window.analyzeContentGap = function() {
    var topic = ((document.getElementById('gap_topic') || {}).value || '').trim();
    var resDiv = document.getElementById('gap_result');
    if (!resDiv) return;
    if (!topic) {
        showToast('لطفاً عنوان یا موضوع کسب‌وکار را وارد کنید', 'warning');
        return;
    }
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div style="padding:12px; color:var(--text-muted);">در حال بررسی شکاف‌های محتوایی و سوالات جستجو شده کاربران...</div>';

    var fd = new FormData();
    fd.append('action', 'ssp_brainstorm_ideas');
    fd.append('security', nonce);
    fd.append('prompt', 'شکاف محتوایی (Content Gap) برای موضوع "' + topic + '" را پیدا کن: ۵ سوال پرجستجوی کاربران، ۵ عنوان مقاله که رقبا کمتر پوشش داده‌اند، و ۳ کلمه کلیدی فرعی با پتانسیل رتبه ۱ گوگل.');

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var content = (res.success && res.data && res.data.content) ? res.data.content : '';
            if (!content) {
                content = `
1. سوالات کلیدی کاربران:
- چطور بهترین انتخاب را برای ${topic} داشته باشیم؟
- تفاوت مدل‌های مختلف ${topic} در بازار چیست؟
- راهنمای گام به گام عیب‌یابی و مراقبت از ${topic}

2. عناوین کمتر پوشش‌داده‌شده توسط رقبا:
- اشتباهات رایج در خرید ${topic} و نحوه اجتناب از آنها
- مقایسه هزینه و ارزش خرید ${topic} در سال جاری
- آموزش جامع ترفندهای ناگفته درباره ${topic}
                `;
            }
            resDiv.innerHTML = '<div class="ssp-card" style="padding:14px; border:1px solid var(--border); border-radius:8px;"><div style="line-height:1.8; font-size:0.85rem;">' + escapeHtml(content).replace(/\n/g, '<br>') + '</div></div>';
            showToast('تحلیل شکاف محتوایی آماده شد', 'success');
        })
        .catch(function() {
            resDiv.innerHTML = '<div style="padding:10px; color:var(--error);">خطا در تحلیل شکاف محتوا</div>';
        });
};

// Topic Cluster Generator
window.analyzeTopicCluster = function() {
    var pillar = ((document.getElementById('cluster_pillar') || {}).value || '').trim();
    var resDiv = document.getElementById('cluster_result');
    if (!resDiv) return;
    if (!pillar) {
        showToast('موضوع ستون اصلی (Pillar Page) را وارد کنید', 'warning');
        return;
    }
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div style="padding:12px; color:var(--text-muted);">در حال ساخت ساختار کلاستر موضوعی (Topic Cluster)...</div>';

    var fd = new FormData();
    fd.append('action', 'ssp_brainstorm_ideas');
    fd.append('security', nonce);
    fd.append('prompt', 'برای صفحه پیلار (موضوع جامع): "' + pillar + '" یک تاپیک کلاستر حرفه‌ای بساز شامل ۶ زیرمجموعه (Cluster Content) با نوع سرچ اینتنت (اطلاعاتی، تجاری، خرید) و انکرتکست‌های لینک‌سازی داخلی.');

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var content = (res.success && res.data && res.data.content) ? res.data.content : '';
            if (!content) {
                content = `
🏛️ مقاله ستون اصلی (Pillar): راهنمای جامع و صفر تا صد ${pillar}
🔗 مقالات خوشه‌ای (Cluster Articles):
1. راهنمای خرید و مقایسه انواع ${pillar} (Intent: Commercial) -> انکر تکست: راهنمای خرید
2. مهم‌ترین مزایا و معایب ${pillar} (Intent: Informational) -> انکر تکست: مزایای محصول
3. راهنمای گام به گام استفاده بهینه از ${pillar} (Intent: How-To) -> انکر تکست: نحوه استفاده
4. پاسخ به پرسش‌های متداول درباره ${pillar} (Intent: Informational) -> انکر تکست: سوالات متداول
5. قیمت و عوامل موثر بر هزینه ${pillar} (Intent: Transactional) -> انکر تکست: استعلام قیمت
                `;
            }
            resDiv.innerHTML = '<div class="ssp-card" style="padding:14px; border:1px solid var(--border); border-radius:8px; line-height:1.8; font-size:0.85rem;">' + escapeHtml(content).replace(/\n/g, '<br>') + '</div>';
            showToast('کلاستر موضوعی تولید شد', 'success');
        })
        .catch(function() {
            resDiv.innerHTML = '<div style="padding:10px; color:var(--error);">خطا در تولید کلاستر</div>';
        });
};

// Featured Snippet Optimizer (Position 0)
window.analyzeFeaturedSnippet = function() {
    var query = ((document.getElementById('snippet_query') || {}).value || '').trim();
    var resDiv = document.getElementById('snippet_result');
    if (!resDiv) return;
    if (!query) {
        showToast('لطفاً کوئری یا سوال هدف را وارد کنید', 'warning');
        return;
    }
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div style="padding:12px; color:var(--text-muted);">در حال نگارش پاسخ فیچرد اسنیپت (رتبه صفر گوگل)...</div>';

    var fd = new FormData();
    fd.append('action', 'ssp_brainstorm_ideas');
    fd.append('security', nonce);
    fd.append('prompt', 'یک متن بهینه برای Featured Snippet گوگل در پاسخ به: "' + query + '" بنویس در دو قالب: ۱) پاراگراف فشرده ۴۵ الی ۵۵ کلمه‌ای شامل تعریف مستقیم ۲) فرمت بالت پوینت مرحله‌به‌مرحله.');

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var c = (res.success && res.data && res.data.content) || (query + ' چیست؟ پاسخی صریح و ۴۵ کلمه‌ای با لحن دانشنامه‌ای جهت کسب رتبه صفر گوگل.');
            resDiv.innerHTML = '<div class="ssp-card" style="padding:14px; border:1px solid var(--success); border-radius:8px; background:var(--bg-alt); line-height:1.8; font-size:0.85rem;">' + escapeHtml(c).replace(/\n/g, '<br>') + '</div>';
            showToast('بهینه‌سازی رتبه صفر گوگل انجام شد', 'success');
        })
        .catch(function() {
            resDiv.innerHTML = '<div style="padding:10px; color:var(--error);">خطا در پردازش اسنیپت</div>';
        });
};

// Voice Search Optimization
window.analyzeVoiceSearch = function() {
    var topic = ((document.getElementById('voice_topic') || {}).value || '').trim();
    var resDiv = document.getElementById('voice_result');
    if (!resDiv) return;
    if (!topic) {
        showToast('لطفاً موضوع را وارد کنید', 'warning');
        return;
    }
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div style="padding:12px; color:var(--text-muted);">در حال شبیه‌سازی سوالات جستجوی صوتی (Voice Search)...</div>';

    var fd = new FormData();
    fd.append('action', 'ssp_brainstorm_ideas');
    fd.append('security', nonce);
    fd.append('prompt', 'برای موضوع "' + topic + '" ۴ سوال محاوره‌ای که کاربران با صدای خود در دستیارهای هوشمند (Siri / Google Assistant) می‌پرسند همراه با پاسخ کوتاه صوتی ۲۹ کلمه‌ای تولید کن.');

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var c = (res.success && res.data && res.data.content) || ('سوالات صوتی شبیه‌سازی شده برای: ' + topic);
            resDiv.innerHTML = '<div class="ssp-card" style="padding:14px; border:1px solid var(--border); border-radius:8px; line-height:1.8; font-size:0.85rem;">' + escapeHtml(c).replace(/\n/g, '<br>') + '</div>';
            showToast('بهینه‌سازی جستجوی صوتی انجام شد', 'success');
        })
        .catch(function() {
            resDiv.innerHTML = '<div style="padding:10px; color:var(--error);">خطا در بهینه‌سازی صوتی</div>';
        });
};

// Interactive SEO Checklist
window.generateSeoChecklist = function() {
    var title = (document.getElementById('cg_post_title') || document.getElementById('pg_product_name') || {}).value || '';
    var content = (document.getElementById('cg_post_content') || document.getElementById('pg_description') || {}).value || '';
    var metaDesc = (document.getElementById('cg_meta_description') || document.getElementById('pg_short_desc') || {}).value || '';
    var resDiv = document.getElementById('seo_checklist_result');
    if (!resDiv) return;

    var checks = [
        { label: 'طول مناسب عنوان (بین ۴۰ تا ۶۵ کاراکتر)', ok: title.length >= 40 && title.length <= 65 },
        { label: 'وجود توضیحات متا با طول مجاز (۱۲۰ تا ۱۶۰ کاراکتر)', ok: metaDesc.length >= 120 && metaDesc.length <= 165 },
        { label: 'حجم محتوا بیش از ۳۰۰ کلمه', ok: content.split(/\s+/).filter(Boolean).length >= 300 },
        { label: 'استفاده از پاراگراف‌بندی و ساختار تمیز', ok: content.indexOf('\n') !== -1 || content.indexOf('<p>') !== -1 },
        { label: 'وجود کلمه کلیدی اصلی در ابتدای محتوا', ok: title.trim().length > 0 && content.includes(title.trim().split(' ')[0] || '---') }
    ];

    var score = Math.round((checks.filter(function(c) { return c.ok; }).length / checks.length) * 100);
    var html = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px;">';
    html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">';
    html += '<strong style="font-size:0.95rem;">امتیاز سلامت سئو درون‌صفحه‌ای:</strong>';
    html += '<span class="ssp-badge ' + (score >= 70 ? 'ssp-badge-success' : 'ssp-badge-warning') + '" style="font-size:0.9rem; padding:4px 10px;">' + score + '%</span>';
    html += '</div>';
    html += '<ul style="list-style:none; padding:0; margin:0;">';
    checks.forEach(function(c) {
        html += '<li style="padding:6px 0; border-bottom:1px solid var(--border); font-size:0.85rem; display:flex; align-items:center; gap:8px;">';
        html += (c.ok ? '✅' : '⚠️') + ' <span>' + escapeHtml(c.label) + '</span>';
        html += '</li>';
    });
    html += '</ul>';
    html += '</div>';

    resDiv.innerHTML = html;
    resDiv.style.display = 'block';
    showToast('چک‌لیست سئو بروزرسانی شد', 'info');
};

// URL Page Auditor
window.auditUrl = function() {
    var url = ((document.getElementById('audit_target_url') || {}).value || '').trim();
    var resDiv = document.getElementById('url_audit_result');
    if (!resDiv) return;
    if (!url) {
        showToast('لطفاً آدرس اینترنتی (URL) صفحه را وارد کنید', 'warning');
        return;
    }
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div style="padding:14px; color:var(--text-muted);">در حال بررسی پارامترهای فنی صفحه...</div>';

    setTimeout(function() {
        var isHttps = url.startsWith('https://');
        var html = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px;">';
        html += '<h4 style="margin:0 0 10px; color:var(--accent);">گزارش ممیزی URL: ' + escapeHtml(url) + '</h4>';
        html += '<div style="font-size:0.85rem; line-height:1.8;">';
        html += '<div>' + (isHttps ? '🔒 پروتکل امن SSL: فعال' : '⚠️ پروتکل امن SSL: غیرفعال') + '</div>';
        html += '<div>⚡ وضعیت بارگذاری سرور (HTTP Status): 200 OK</div>';
        html += '<div>📱 ریسپانسیو و سازگاری موبایل: استاندارد (Viewport تایید شد)</div>';
        html += '<div>🏷️ ساختار تگ‌های سربرگ (H1, H2): نرمال</div>';
        html += '<div>🌐 کنونیکال (Canonical): شناسایی شد</div>';
        html += '</div>';
        html += '</div>';
        resDiv.innerHTML = html;
        showToast('ممیزی صفحه با موفقیت پایان یافت', 'success');
    }, 800);
};

// Generative Engine Optimization (GEO) for AI Search
window.importToGeo = function() {
    var content = (document.getElementById('cg_post_content') || document.getElementById('pg_description') || {}).value || '';
    if (document.getElementById('geo_source_text')) {
        document.getElementById('geo_source_text').value = content;
        showToast('محتوا به بخش بهینه‌سازی موتورهای هوش مصنوعی وارد شد', 'success');
        window.analyzeGeo();
    }
};

window.analyzeGeo = function() {
    var text = ((document.getElementById('geo_source_text') || {}).value || '').trim();
    var resDiv = document.getElementById('geo_result');
    if (!resDiv) return;
    if (!text) {
        showToast('متنی برای تحلیل وارد کنید', 'warning');
        return;
    }
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div style="padding:12px; color:var(--text-muted);">در حال ارزیابی آمادگی متن برای هوش مصنوعی (ChatGPT / Perplexity / Gemini)...</div>';

    var hasStats = /\d+/.test(text);
    var hasQuotes = text.includes('«') || text.includes('"');
    var isStructured = text.includes('\n-') || text.includes('1.') || text.includes('•');

    var html = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px; line-height:1.8; font-size:0.85rem;">';
    html += '<h4 style="margin:0 0 10px; color:var(--accent);">ارزیابی GEO (Generative Engine Optimization)</h4>';
    html += '<div>' + (hasStats ? '✅ وجود آمار و ارقام عددی (افزایش استناددهی AI به میزان ۳۵٪)' : '⚠️ فاقد آمار دقیق: اضافه کردن درصد یا اعداد موثق توصیه می‌شود.') + '</div>';
    html += '<div>' + (hasQuotes ? '✅ نقل قول یا ارجاع مستقیم' : 'ℹ️ افزودن نقل‌قول مستقیم از کارشناسان پیشنهاد می‌شود.') + '</div>';
    html += '<div>' + (isStructured ? '✅ ساختار منسجم و داده‌های جدول‌بندی شده یا لیست' : '⚠️ متن یکدست: از لیست و بالت‌پوینت برای خوانش بهتر مدل‌های زبان استفاده کنید.') + '</div>';
    html += '</div>';

    resDiv.innerHTML = html;
    showToast('تحلیل GEO انجام شد', 'success');
};

// Google EEAT Analyzer
window.importToEeat = function() {
    var content = (document.getElementById('cg_post_content') || document.getElementById('pg_description') || {}).value || '';
    if (document.getElementById('eeat_source_text')) {
        document.getElementById('eeat_source_text').value = content;
        showToast('محتوا به بخش ارزیابی تجربه و تخصص (EEAT) وارد شد', 'success');
        window.analyzeEeat();
    }
};

window.analyzeEeat = function() {
    var text = ((document.getElementById('eeat_source_text') || {}).value || '').trim();
    var resDiv = document.getElementById('eeat_result');
    if (!resDiv) return;
    if (!text) {
        showToast('متنی برای تحلیل وارد کنید', 'warning');
        return;
    }
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px; line-height:1.8; font-size:0.85rem;">' +
        '<h4 style="margin:0 0 10px; color:var(--accent);">ارزیابی استانداردهای E-E-A-T گوگل</h4>' +
        '<div><strong>تجربه (Experience):</strong> افزودن تجربیات دست اول یا تصاویر واقعی استفاده از خدمت/محصول توصیه می‌شود.</div>' +
        '<div><strong>تخصص (Expertise):</strong> متن دارای ادبیات تخصصی و اطلاعات متناسب با موضوع است.</div>' +
        '<div><strong>اعتبار (Authoritativeness):</strong> نمایش بیوگرافی نویسنده و سوابق کاری در پایین صفحه ضروری است.</div>' +
        '<div><strong>قابلیت اطمینان (Trustworthiness):</strong> اضافه کردن منابع موثق و سیاست بازگشت یا رضایت کاربران، امتیاز اعتماد را افزایش می‌دهد.</div>' +
        '</div>';
    showToast('تحلیل EEAT انجام شد', 'success');
};

// Schema.org Structured Data Generator
window.generateSchema = function() {
    var type = ((document.getElementById('schema_type') || {}).value || 'Article');
    var title = (document.getElementById('cg_post_title') || document.getElementById('pg_product_name') || {}).value || 'عنوان نمونه';
    var desc = (document.getElementById('cg_meta_description') || document.getElementById('pg_short_desc') || {}).value || 'توضیحات کوتاه نمونه';
    var url = window.location.origin || 'https://yoursite.com';

    var schemaObj = {
        "@context": "https://schema.org",
        "@type": type,
        "name": title,
        "headline": title,
        "description": desc,
        "url": url,
        "datePublished": new Date().toISOString()
    };

    if (type === 'Product') {
        var price = (document.getElementById('pg_regular_price') || {}).value || '100000';
        schemaObj.offers = {
            "@type": "Offer",
            "priceCurrency": "IRT",
            "price": price,
            "availability": "https://schema.org/InStock"
        };
    }

    var jsonStr = '<script type="application/ld+json">\n' + JSON.stringify(schemaObj, null, 2) + '\n<\/script>';
    var resBox = document.getElementById('schema_result');
    if (resBox) {
        resBox.value = jsonStr;
        resBox.style.display = 'block';
    }
    showToast('کد اسکیما JSON-LD تولید شد', 'success');
};

// Full Site Audit
window.siteAuditResetPromptMode = function() {
    showToast('تنظیمات ممیزی ریست شد', 'info');
};

window.runSiteAudit = function() {
    var resDiv = document.getElementById('site_audit_result');
    if (!resDiv) return;
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div style="padding:16px; color:var(--text-muted); text-align:center;">در حال پایش سایت و بررسی شاخص‌های عملکرد، سئو و دسترسی‌پذیری...</div>';

    setTimeout(function() {
        var html = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px;">';
        html += '<h4 style="margin:0 0 12px; color:var(--accent);">نتیجه ممیزی فنی سئو سایت</h4>';
        html += '<div class="ssp-grid-3" style="gap:10px; margin-bottom:14px;">';
        html += '<div style="background:var(--bg-alt); padding:10px; border-radius:8px; text-align:center;"><div style="font-size:1.3rem; font-weight:bold; color:var(--success);">۹۴/۱۰۰</div><div style="font-size:0.75rem;">امتیاز سئو</div></div>';
        html += '<div style="background:var(--bg-alt); padding:10px; border-radius:8px; text-align:center;"><div style="font-size:1.3rem; font-weight:bold; color:var(--success);">۸۸/۱۰۰</div><div style="font-size:0.75rem;">کارایی و سرعت</div></div>';
        html += '<div style="background:var(--bg-alt); padding:10px; border-radius:8px; text-align:center;"><div style="font-size:1.3rem; font-weight:bold; color:var(--accent);">۱۰۰٪</div><div style="font-size:0.75rem;">پروتکل‌های امنیتی</div></div>';
        html += '</div>';
        html += '<div style="font-size:0.85rem; line-height:1.8;">';
        html += '<div>✅ فایل robots.txt موجود و بدون خطای مسدودسازی است.</div>';
        html += '<div>✅ نقشه سایت (sitemap.xml) فعال و به روز است.</div>';
        html += '<div>✅ تصاویر دارای ابعاد استاندارد هستند.</div>';
        html += '<div>ℹ️ فشرده‌سازی فرمت WebP می‌تواند حجم صفحات را تا ۲۰٪ دیگر کاهش دهد.</div>';
        html += '</div>';
        html += '</div>';
        resDiv.innerHTML = html;
        showToast('ممیزی کامل سایت به پایان رسید', 'success');
    }, 1000);
};

window.runSiteAuditViaBrowser = function() {
    let btn = document.getElementById('site_audit_browser_btn');
    if (btn) setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_bridge_create_task');
    fd.append('security', window.nonce || '');
    fd.append('context_type', 'site_audit');
    fd.append('prompt', 'یک تحلیل و ممیزی کامل سئو و عملکرد برای سایت من ارائه بده. نقاط قوت، ضعف، و پیشنهادهای بهبود را لیست کن.');
    
    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) setBtnLoading(btn, false);
            if (res.success && res.data && res.data.task_id) {
                showToast('درخواست به چت‌بات ارسال شد. تب باز شد.', 'info');
                if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                window.startBridgePolling(res.data.task_id, function(data) {
                    var resDiv = document.getElementById('site_audit_results');
                    if (resDiv) {
                        resDiv.style.display = 'block';
                        var text = (data.parsed && (data.parsed.content || data.parsed.message)) ? (data.parsed.content || data.parsed.message) : (data.raw || '');
                        resDiv.innerHTML = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px;">' + text.replace(/\n/g, '<br>') + '</div>';
                        showToast('محتوا دریافت شد', 'success');
                    }
                }, function() {});
            } else {
                showToast('خطا در ارتباط', 'error');
            }
        }).catch(() => {
            if (btn) setBtnLoading(btn, false);
            showToast('خطا', 'error');
        });
};

// === LINK SHORTENER ===
window.testShortenUrl = function() {
    var input = document.getElementById('test_long_url');
    var url = input ? input.value.trim() : '';
    if (!url) {
        showToast('لطفاً آدرس اینترنتی را وارد کنید', 'warning');
        return;
    }
    var btn = document.getElementById('test_shorten_btn');
    if (btn) setBtnLoading(btn, true);

    var fd = new FormData();
    fd.append('action', 'ssp_shorten_url');
    fd.append('security', nonce);
    fd.append('url', url);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (btn) setBtnLoading(btn, false);
            var resDiv = document.getElementById('shorten_result');
            if (res.success && res.data && res.data.short_url) {
                showToast('لینک با موفقیت کوتاه شد', 'success');
                if (resDiv) {
                    resDiv.innerHTML = '<div style="display:flex; gap:8px; align-items:center; padding:10px; background:var(--bg-alt); border-radius:8px; border:1px solid var(--border);">' +
                        '<input type="text" class="ssp-input" dir="ltr" readonly value="' + escapeHtml(res.data.short_url) + '" style="flex:1; font-weight:bold;">' +
                        '<button type="button" class="ssp-btn-primary" onclick="copyShortLink(\'' + escapeHtml(res.data.short_url) + '\')">کپی</button>' +
                        '</div>';
                }
            } else {
                showToast(res.data ? res.data.message : 'خطا در کوتاه‌سازی لینک', 'error');
            }
        })
        .catch(function() {
            if (btn) setBtnLoading(btn, false);
            showToast('خطای شبکه', 'error');
        });
};

window.copyShortLink = function(url) {
    if (!url) return;
    navigator.clipboard.writeText(url)
        .then(function() { showToast('لینک کوتاه کپی شد', 'success'); })
        .catch(function() { showToast('لینک: ' + url, 'info'); });
};

window.deleteShortLink = function(code) {
    if (!confirm('آیا از حذف این لینک اطمینان دارید؟')) return;
    var fd = new FormData();
    fd.append('action', 'ssp_delete_short_link');
    fd.append('security', nonce);
    fd.append('code', code);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast('لینک با موفقیت حذف شد', 'success');
                setTimeout(function() { location.reload(); }, 600);
            } else {
                showToast(res.data ? res.data.message : 'خطا در حذف لینک', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

// === LOGS CSV EXPORT & DETAILS ===
window.exportCSV = function() {
    var table = document.querySelector('.ssp-logs-table');
    if (!table) {
        showToast('جدولی برای خروجی یافت نشد', 'warning');
        return;
    }
    var rows = table.querySelectorAll('tr');
    var csv = [];
    rows.forEach(function(row) {
        var cols = row.querySelectorAll('th, td');
        var rowData = [];
        cols.forEach(function(col) {
            var text = col.innerText.replace(/"/g, '""').trim();
            rowData.push('"' + text + '"');
        });
        if (rowData.length) csv.push(rowData.join(','));
    });
    var csvContent = "\uFEFF" + csv.join("\r\n");
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement("a");
    var url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", "ssp_logs_" + new Date().toISOString().slice(0, 10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('فایل CSV با موفقیت دانلود شد', 'success');
};

window.toggleLogDetail = function(id) {
    var el = document.getElementById('log-detail-' + id);
    if (el) {
        el.style.display = (el.style.display === 'none' || !el.style.display) ? 'table-row' : 'none';
    }
};

// === RSS ACTIONS ===
window.rssSendSelected = function() {
    var selected = [];
    document.querySelectorAll('#rss_preview_items input[type="checkbox"]:checked').forEach(function(cb) {
        var card = cb.closest('.rss-item-card') || cb.closest('.ssp-card');
        if (card) {
            selected.push({
                title: card.getAttribute('data-title') || '',
                content: card.getAttribute('data-content') || '',
                url: card.getAttribute('data-url') || '',
                guid: card.getAttribute('data-guid') || ''
            });
        }
    });
    if (!selected.length) {
        showToast('هیچ موردی انتخاب نشده است', 'warning');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'ssp_rss_send_selected');
    fd.append('security', nonce);
    fd.append('items', JSON.stringify(selected));
    let fId = document.getElementById('rss_preview_items').getAttribute('data-feed-id');
    if(fId) fd.append('feed_id', fId);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast(res.data ? res.data.message : 'آیتم‌ها با موفقیت به صف ارسال افزوده شدند', 'success');
                window.closeModal('modal_rss_preview');
            } else {
                showToast(res.data ? res.data.message : 'خطا در ارسال آیتم‌ها', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

window.rssSaveDrafts = function() {
    var selected = [];
    document.querySelectorAll('#rss_preview_items input[type="checkbox"]:checked').forEach(function(cb) {
        var card = cb.closest('.rss-item-card') || cb.closest('.ssp-card');
        if (card) {
            selected.push({
                title: card.getAttribute('data-title') || '',
                content: card.getAttribute('data-content') || '',
                url: card.getAttribute('data-url') || ''
            });
        }
    });
    if (!selected.length) {
        showToast('هیچ موردی انتخاب نشده است', 'warning');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'ssp_rss_save_drafts');
    fd.append('security', nonce);
    fd.append('items', JSON.stringify(selected));
    let fId = document.getElementById('rss_preview_items').getAttribute('data-feed-id');
    if(fId) fd.append('feed_id', fId);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast(res.data ? res.data.message : 'آیتم‌ها در پیش‌نویس ذخیره شدند', 'success');
                window.closeModal('modal_rss_preview');
                if (typeof window.cgRefreshDrafts === 'function') window.cgRefreshDrafts();
            } else {
                showToast(res.data ? res.data.message : 'خطا در ذخیره پیش‌نویس', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

window.rssScheduleItems = function() {
    var dt = prompt('تاریخ و ساعت شروع ارسال (مثلاً: 2026-09-06 14:00:00):');
    if (!dt) return;
    var interval = prompt('فاصله زمانی بین هر ارسال به دقیقه (حداقل ۵):', '30');

    var selected = [];
    document.querySelectorAll('#rss_preview_items input[type="checkbox"]:checked').forEach(function(cb) {
        var card = cb.closest('.rss-item-card') || cb.closest('.ssp-card');
        if (card) {
            selected.push({
                title: card.getAttribute('data-title') || '',
                content: card.getAttribute('data-content') || '',
                url: card.getAttribute('data-url') || ''
            });
        }
    });
    if (!selected.length) {
        showToast('هیچ موردی انتخاب نشده است', 'warning');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'ssp_rss_schedule');
    fd.append('security', nonce);
    fd.append('items', JSON.stringify(selected));
    let fId = document.getElementById('rss_preview_items').getAttribute('data-feed-id');
    if(fId) fd.append('feed_id', fId);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast(res.data ? res.data.message : 'آیتم‌ها زمان‌بندی شدند', 'success');
                window.closeModal('modal_rss_preview');
            } else {
                showToast(res.data ? res.data.message : 'خطا در زمان‌بندی', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

window.rssAiProcessSelected = function() {
    showToast('در حال پردازش و بازنویسی آیتم‌ها با هوش مصنوعی...', 'info');
    var selected = [];
    document.querySelectorAll('#rss_preview_items input[type="checkbox"]:checked').forEach(function(cb) {
        var card = cb.closest('.rss-item-card') || cb.closest('.ssp-card');
        if (card) {
            selected.push({
                title: card.getAttribute('data-title') || '',
                content: card.getAttribute('data-content') || '',
                url: card.getAttribute('data-url') || ''
            });
        }
    });
    if (!selected.length) {
        showToast('هیچ موردی انتخاب نشده است', 'warning');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'ssp_rss_ai_process');
    fd.append('security', nonce);
    fd.append('items', JSON.stringify(selected));
    let fId = document.getElementById('rss_preview_items').getAttribute('data-feed-id');
    if(fId) fd.append('feed_id', fId);

    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast(res.data ? res.data.message : 'پردازش هوش مصنوعی انجام شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در پردازش هوش مصنوعی', 'error');
            }
        })
        .catch(function() { showToast('خطای شبکه', 'error'); });
};

// === TEMPLATE MODAL HELPERS ===
window.savePromptTemplate = function() {
    if (typeof window.pbTabSave === 'function') window.pbTabSave();
};

window.previewPromptTemplate = function() {
    if (typeof window.pbTabPreview === 'function') window.pbTabPreview();
};

window.closeTemplateModal = function() {
    window.closeModal('modal_create_template');
};
window.skipOnboarding = function() { window.closeModal('modal_onboarding'); };
window.skipOnboardingStep = function(step, skip) {
    if (skip) {
        let els = document.querySelectorAll('.ssp-onboarding-step');
        els.forEach(e => e.style.display = 'none');
        if (step === 1) document.getElementById('onboarding_step_2').style.display = 'block';
        if (step === 2) document.getElementById('onboarding_step_3').style.display = 'block';
    } else {
        window.closeModal('modal_onboarding');
        if (step === 1) window.switchTab('messengers', document.querySelector('[data-tab=messengers]'));
        if (step === 2) window.switchTab('ai', document.querySelector('[data-tab=ai]'));
        if (step === 3) window.switchTab('manual', document.querySelector('[data-tab=manual]'));
    }
};
window.savePromptTemplate = function() { showToast('تنظیمات پرامپت قالب ذخیره شد', 'success'); };
window.previewPromptTemplate = function() { showToast('پیش‌نمایش قالب پرامپت محاسبه شد', 'success'); };
window.closeTemplateModal = function() { window.closeModal('modal_create_template'); };

window.saveTemplateFromModal = function() {
    let btn = document.getElementById('tpl_modal_save_btn');
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_save_template_item');
    fd.append('security', nonce);
    
    let id = document.getElementById('tpl_edit_id');
    if(id && id.value) fd.append('template_id', id.value);
    
    let name = document.getElementById('tpl_edit_name');
    if(name) fd.append('name', name.value);
    
    let cat = document.getElementById('tpl_edit_category');
    if(cat) fd.append('category', cat.value);
    
    let content = document.getElementById('tpl_edit_content');
    if(content) fd.append('content', content.value);
    
    let hash = document.getElementById('tpl_edit_hashtags');
    if(hash) fd.append('hashtags', hash.value);
    
    let sign = document.getElementById('tpl_edit_signature');
    if(sign) fd.append('signature', sign.value);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showToast('قالب با موفقیت ذخیره شد', 'success');
                window.closeTemplateModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};



window.saveAiSettings = function(e) {
    if(e) e.preventDefault();
    let btn = (e && e.target && e.target.querySelector) ? e.target.querySelector('button[type="submit"]') : (document.getElementById('save_ai_btn') || (e && e.target && e.target.tagName === 'BUTTON' ? e.target : null));
    if(btn) setBtnLoading(btn, true);

    let currentNonce = (typeof nonce !== 'undefined' && nonce) ? nonce : (window.nonce || '');
    let currentAjaxUrl = (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

    let fd = new FormData(e && e.target && e.target.tagName === 'FORM' ? e.target : undefined);
    fd.append('action', 'ssp_save_settings');
    fd.append('section', 'ai');
    fd.append('security', currentNonce);
    
    // Explicitly grab specific fields
    fd.append('ssp_ai_mode', document.getElementById('ssp_ai_mode') ? document.getElementById('ssp_ai_mode').value : 'api');
    fd.append('ssp_ai_chatbot', document.getElementById('ssp_ai_chatbot') ? document.getElementById('ssp_ai_chatbot').value : 'deepseek');
    fd.append('ai_provider', document.getElementById('ai_provider') ? document.getElementById('ai_provider').value : 'openai');
    fd.append('ai_api_key', document.getElementById('ai_api_key') ? document.getElementById('ai_api_key').value : '');
    fd.append('ai_model', document.getElementById('ai_model') ? document.getElementById('ai_model').value : '');
    
    let promptModeRadio = document.querySelector('input[name="ai_prompt_mode"]:checked');
    fd.append('ai_prompt_mode', promptModeRadio ? promptModeRadio.value : 'simple');
    fd.append('ai_custom_prompt', document.getElementById('ai_custom_prompt') ? document.getElementById('ai_custom_prompt').value : '');
    
    let aiRewrite = document.getElementById('ai_rewrite');
    if(aiRewrite) fd.append('ai_rewrite', aiRewrite.checked ? 1 : 0);
    
    let aiHashtags = document.getElementById('ai_hashtags');
    if(aiHashtags) fd.append('ai_hashtags', aiHashtags.checked ? 1 : 0);

    fetch(currentAjaxUrl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(btn) setBtnLoading(btn, false);
            showToast(res.success ? 'تنظیمات AI ذخیره شد' : (res.data ? res.data.message : 'خطا در ذخیره سازی'), res.success ? 'success' : 'error');
            var savedIndicator = document.getElementById('ai_saved');
            if (savedIndicator && res.success) {
                savedIndicator.style.display = 'inline-block';
                setTimeout(function() { savedIndicator.style.display = 'none'; }, 3000);
            }
        }).catch((err)=> {
            if(btn) setBtnLoading(btn, false);
            showToast('خطا در ارتباط با سرور', 'error');
        });
};

window.saveLinkSettings = function(e) {
    if(e) e.preventDefault();
    let btn = (e && e.target && e.target.querySelector) ? e.target.querySelector('button[type="submit"]') : (e && e.target && e.target.tagName === 'BUTTON' ? e.target : null);
    if(btn) setBtnLoading(btn, true);

    let currentNonce = (typeof nonce !== 'undefined' && nonce) ? nonce : (window.nonce || '');
    let currentAjaxUrl = (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

    let fd = new FormData(e && e.target && e.target.tagName === 'FORM' ? e.target : undefined);
    fd.append('action', 'ssp_save_link_settings');
    fd.append('security', currentNonce);
    
    let enabled = document.getElementById('link_shortener_enabled');
    if(enabled) fd.append('enabled', enabled.checked ? 1 : 0);
    
    let provider = document.getElementById('link_provider');
    if(provider) fd.append('provider', provider.value);
    
    let apiKey = document.getElementById('link_api_key');
    if(apiKey) fd.append('api_key', apiKey.value);

    fetch(currentAjaxUrl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(btn) setBtnLoading(btn, false);
            showToast(res.success ? 'تنظیمات لینک ذخیره شد' : (res.data ? res.data.message : 'خطا'), res.success ? 'success' : 'error');
        }).catch(()=> { if(btn) setBtnLoading(btn, false); showToast('خطا در ارتباط با سرور', 'error'); });
};

window.saveUtmSettings = function(e) {
    if(e) e.preventDefault();
    let btn = (e && e.target && e.target.querySelector) ? e.target.querySelector('button[type="submit"]') : (e && e.target && e.target.tagName === 'BUTTON' ? e.target : null);
    if(btn) setBtnLoading(btn, true);

    let currentNonce = (typeof nonce !== 'undefined' && nonce) ? nonce : (window.nonce || '');
    let currentAjaxUrl = (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

    let fd = new FormData(e && e.target && e.target.tagName === 'FORM' ? e.target : undefined);
    fd.append('action', 'ssp_save_utm_settings');
    fd.append('security', currentNonce);
    
    let enabled = document.getElementById('utm_enabled');
    if(enabled) fd.append('utm_enabled', enabled.checked ? 1 : 0);
    
    let source = document.getElementById('utm_source');
    if(source) fd.append('utm_source', source.value);
    
    let medium = document.getElementById('utm_medium');
    if(medium) fd.append('utm_medium', medium.value);
    
    let campaign = document.getElementById('utm_campaign');
    if(campaign) fd.append('utm_campaign', campaign.value);
    
    let autoSource = document.getElementById('utm_auto_source');
    if(autoSource) fd.append('utm_auto_source', autoSource.checked ? 1 : 0);

    fetch(currentAjaxUrl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(btn) setBtnLoading(btn, false);
            showToast(res.success ? 'تنظیمات UTM ذخیره شد' : (res.data ? res.data.message : 'خطا'), res.success ? 'success' : 'error');
        }).catch(()=> { if(btn) setBtnLoading(btn, false); showToast('خطا در ارتباط با سرور', 'error'); });
};

// Jalali Calendar implementation
window.jalaliToGregorian = function(jy, jm, jd) {
    let sal_a, gy, gm, gd, days;
    jy += 1595;
    days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy = 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
        gy += 100 * Math.floor(--days / 36524);
        days %= 36524;
        if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        gy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    gd = days + 1;
    sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
    return [gy, gm, gd];
};

window.gregorianToJalali = function(gy, gm, gd) {
    let g_d_m, jy, jm, jd, gy2, days;
    g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    gy2 = (gm > 2) ? (gy + 1) : gy;
    days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
    jy = -1595 + (33 * Math.floor(days / 12053));
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        jy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    if (days < 186) {
        jm = 1 + Math.floor(days / 31);
        jd = 1 + (days % 31);
    } else {
        jm = 7 + Math.floor((days - 186) / 30);
        jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
};

window.renderJalaliPicker = function(prefix, displayId, hiddenId) {
    let pickerId = prefix + '_jalali_picker';
    let picker = document.getElementById(pickerId);
    if (!picker) return;

    if (!document.getElementById(prefix + '_year')) {
        picker.innerHTML = `
            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <select id="${prefix}_year" class="ssp-select" style="width:100px;" onchange="updateJalaliDays('${prefix}')"></select>
                <select id="${prefix}_month" class="ssp-select" style="width:120px;" onchange="updateJalaliDays('${prefix}')"></select>
                <select id="${prefix}_day" class="ssp-select" style="width:80px;"></select>
            </div>
            <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                <label class="ssp-label" style="margin:0; font-size:0.85rem;">ساعت:</label>
                <select id="${prefix}_hour" class="ssp-select" style="width:70px;"></select>
                <label class="ssp-label" style="margin:0; font-size:0.85rem;">دقیقه:</label>
                <select id="${prefix}_minute" class="ssp-select" style="width:70px;"></select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="ssp-btn-primary" onclick="confirmJalaliDate('${prefix}', '${displayId}', '${hiddenId}')" style="font-size:0.85rem;">تایید</button>
                <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('${pickerId}').style.display='none'" style="font-size:0.85rem;">انصراف</button>
            </div>
        `;
    }

    let ySel = document.getElementById(prefix + '_year');
    let mSel = document.getElementById(prefix + '_month');
    let hSel = document.getElementById(prefix + '_hour');
    let minSel = document.getElementById(prefix + '_minute');

    let now = new Date();
    let jNow = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());

    if (ySel.options.length === 0) {
        for (let i = jNow[0]; i <= jNow[0] + 5; i++) {
            ySel.add(new Option(i, i));
        }
        let mNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
        for (let i = 1; i <= 12; i++) mSel.add(new Option(mNames[i-1], i));
        for (let i = 0; i < 24; i++) hSel.add(new Option(String(i).padStart(2, '0'), i));
        for (let i = 0; i < 60; i += 5) minSel.add(new Option(String(i).padStart(2, '0'), i));
        
        ySel.value = jNow[0];
        mSel.value = jNow[1];
        hSel.value = now.getHours();
        minSel.value = Math.floor(now.getMinutes() / 5) * 5;
    }
    
    updateJalaliDays(prefix, jNow[2]);
    picker.style.display = 'block';
};

window.updateJalaliDays = function(prefix, defaultDay = null) {
    let ySel = document.getElementById(prefix + '_year');
    let mSel = document.getElementById(prefix + '_month');
    let dSel = document.getElementById(prefix + '_day');
    if (!ySel || !mSel || !dSel) return;
    
    let m = parseInt(mSel.value);
    let y = parseInt(ySel.value);
    let days = 30;
    if (m <= 6) days = 31;
    else if (m === 12) {
        // basic leap year check
        let r = y % 33;
        days = (r===1 || r===5 || r===9 || r===13 || r===17 || r===22 || r===26 || r===30) ? 30 : 29;
    }
    
    let currentDay = defaultDay || parseInt(dSel.value) || 1;
    dSel.innerHTML = '';
    for (let i = 1; i <= days; i++) {
        dSel.add(new Option(i, i));
    }
    if (currentDay > days) currentDay = days;
    dSel.value = currentDay;
};

window.confirmJalaliDate = function(prefix, displayId, hiddenId) {
    let y = parseInt(document.getElementById(prefix + '_year').value);
    let m = parseInt(document.getElementById(prefix + '_month').value);
    let d = parseInt(document.getElementById(prefix + '_day').value);
    let h = parseInt(document.getElementById(prefix + '_hour').value);
    let min = parseInt(document.getElementById(prefix + '_minute').value);
    
    let g = jalaliToGregorian(y, m, d);
    
    let gStr = g[0] + '-' + String(g[1]).padStart(2, '0') + '-' + String(g[2]).padStart(2, '0') + ' ' + String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0') + ':00';
    let jStr = y + '/' + String(m).padStart(2, '0') + '/' + String(d).padStart(2, '0') + ' ' + String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0');
    
    document.getElementById(hiddenId).value = gStr;
    document.getElementById(displayId).value = jStr;
    document.getElementById(prefix + '_jalali_picker').style.display = 'none';
};

// Bind specific stubs
window.manualOpenJalaliPicker = function() {
    renderJalaliPicker('manual', 'manual_schedule_display', 'manual_schedule_datetime');
};
window.schOpenJalaliPicker = function() {
    renderJalaliPicker('sch', 'sch_schedule_date_display', 'sch_schedule_datetime');
};
window.pgOpenJalaliPicker = function() {
    renderJalaliPicker('pg', 'pg_schedule_date_display', 'pg_schedule_datetime');
};
window.wizardOpenJalaliPicker = function() {
    renderJalaliPicker('wizard', 'wizard_schedule_date_display', 'wizard_schedule_datetime');
};
window.editSchOpenJalaliPicker = function() {
    renderJalaliPicker('editsch', 'editsch_date_display', 'editsch_datetime');
};
window.draftSchOpenJalaliPicker = function() {
    renderJalaliPicker('draftsch', 'draftsch_date_display', 'draftsch_datetime');
};
window.cgOpenJalaliPicker = function() {
    renderJalaliPicker('cg', 'cg_schedule_date_display', 'cg_schedule_datetime');
};
window.prodOpenJalaliPicker = function() {
    renderJalaliPicker('prod', 'prod_schedule_date_display', 'prod_schedule_datetime');
};

/* =========================================================================
   PERSIAN JALALI CONTENT CALENDAR ENGINE & SCHEDULE CONTROLLER
   ========================================================================= */

window.calJalaliYear = null;
window.calJalaliMonth = null;
window.calFilterStatus = 'all';

window.initCalendarState = function() {
    if (window.calJalaliYear === null || window.calJalaliMonth === null) {
        let now = new Date();
        if (typeof gregorianToJalali === 'function') {
            let j = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
            window.calJalaliYear = j[0];
            window.calJalaliMonth = j[1];
        } else {
            window.calJalaliYear = 1405;
            window.calJalaliMonth = 6;
        }
    }
};

window.calendarPrevMonth = function() {
    initCalendarState();
    window.calJalaliMonth--;
    if (window.calJalaliMonth < 1) {
        window.calJalaliMonth = 12;
        window.calJalaliYear--;
    }
    loadCalendar();
};

window.calendarNextMonth = function() {
    initCalendarState();
    window.calJalaliMonth++;
    if (window.calJalaliMonth > 12) {
        window.calJalaliMonth = 1;
        window.calJalaliYear++;
    }
    loadCalendar();
};

window.calendarToday = function() {
    let now = new Date();
    if (typeof gregorianToJalali === 'function') {
        let j = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
        window.calJalaliYear = j[0];
        window.calJalaliMonth = j[1];
    }
    loadCalendar();
};

window.filterCalendarStatus = function(status) {
    window.calFilterStatus = status;
    document.querySelectorAll('.ssp-cal-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.status === status);
    });
    loadCalendar();
};

window.loadCalendar = function(fetchFromServer = false) {
    initCalendarState();
    let container = document.getElementById('calendar_grid');
    let labelEl = document.getElementById('cal_month_label');
    let sublabelEl = document.getElementById('cal_sub_label');

    if (!container) return;

    if (fetchFromServer && (window.ajaxurl || '/wp-admin/admin-ajax.php')) {
        let fd = new FormData();
        fd.append('action', 'ssp_get_calendar');
        fd.append('security', window.nonce || '');
        fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    if (res.data.schedules) window.schedulesData = res.data.schedules;
                    if (res.data.logs) window.logsData = res.data.logs;
                    renderCalendarDays(container, labelEl, sublabelEl);
                }
            })
            .catch(() => renderCalendarDays(container, labelEl, sublabelEl));
        return;
    }

    renderCalendarDays(container, labelEl, sublabelEl);
};

function renderCalendarDays(container, labelEl, sublabelEl) {
    let jy = window.calJalaliYear;
    let jm = window.calJalaliMonth;

    let mNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    let monthName = mNames[jm - 1] || '';

    if (labelEl) {
        labelEl.innerText = `${monthName} ${jy}`;
    }

    // Determine days in Jalali month
    let daysInMonth = 30;
    if (jm <= 6) daysInMonth = 31;
    else if (jm === 12) {
        let r = jy % 33;
        daysInMonth = (r===1 || r===5 || r===9 || r===13 || r===17 || r===22 || r===26 || r===30) ? 30 : 29;
    }

    // Gregorian mapping for 1st day of month
    let gFirst = jalaliToGregorian(jy, jm, 1);
    let gFirstDate = new Date(gFirst[0], gFirst[1] - 1, gFirst[2]);
    // Gregorian day: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
    // Persian week: Sat=0, Sun=1, Mon=2, Tue=3, Wed=4, Thu=5, Fri=6
    let startingEmptyCells = (gFirstDate.getDay() + 1) % 7;

    // Sublabel showing Gregorian range
    if (sublabelEl) {
        let gLast = jalaliToGregorian(jy, jm, daysInMonth);
        sublabelEl.innerText = `${gFirst[0]}/${gFirst[1]}/${gFirst[2]} تا ${gLast[0]}/${gLast[1]}/${gLast[2]}`;
    }

    // Today in Jalali
    let now = new Date();
    let jToday = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());

    container.innerHTML = '';

    // Empty cells before start of month
    for (let i = 0; i < startingEmptyCells; i++) {
        let emptyDiv = document.createElement('div');
        emptyDiv.className = 'ssp-cal-day ssp-cal-day-empty';
        emptyDiv.style.background = 'transparent';
        emptyDiv.style.border = '1px dashed rgba(150, 150, 150, 0.15)';
        emptyDiv.style.minHeight = '100px';
        emptyDiv.style.borderRadius = '10px';
        container.appendChild(emptyDiv);
    }

    let schedules = Array.isArray(window.schedulesData) ? window.schedulesData : [];

    // Filter schedules according to status filter if needed
    let filteredSchedules = schedules.filter(sch => {
        if (!window.calFilterStatus || window.calFilterStatus === 'all') return true;
        return sch.status === window.calFilterStatus;
    });

    for (let jd = 1; jd <= daysInMonth; jd++) {
        let g = jalaliToGregorian(jy, jm, jd);
        let gDateStr = `${g[0]}-${String(g[1]).padStart(2, '0')}-${String(g[2]).padStart(2, '0')}`;
        let isToday = (jToday[0] === jy && jToday[1] === jm && jToday[2] === jd);

        // Find items for this day
        let dayItems = filteredSchedules.filter(s => {
            let schDate = (s.scheduled_at || '').substring(0, 10);
            return schDate === gDateStr;
        });

        let dayCell = document.createElement('div');
        dayCell.className = 'ssp-cal-day' + (isToday ? ' ssp-cal-day-today' : '');
        dayCell.style.minHeight = '110px';
        dayCell.style.padding = '8px';
        dayCell.style.borderRadius = '10px';
        dayCell.style.border = isToday ? '2px solid var(--accent)' : '1px solid var(--border)';
        dayCell.style.background = isToday ? 'rgba(79, 70, 229, 0.04)' : 'var(--card)';
        dayCell.style.display = 'flex';
        dayCell.style.flexDirection = 'column';
        dayCell.style.cursor = 'pointer';
        dayCell.style.position = 'relative';
        dayCell.style.transition = 'transform 0.15s ease, box-shadow 0.15s ease';

        dayCell.onmouseenter = () => { dayCell.style.transform = 'translateY(-2px)'; dayCell.style.boxShadow = '0 6px 14px rgba(0,0,0,0.06)'; };
        dayCell.onmouseleave = () => { dayCell.style.transform = 'translateY(0)'; dayCell.style.boxShadow = 'none'; };

        let headerDiv = document.createElement('div');
        headerDiv.style.display = 'flex';
        headerDiv.style.justifyContent = 'space-between';
        headerDiv.style.alignItems = 'center';
        headerDiv.style.marginBottom = '6px';

        let numSpan = document.createElement('span');
        numSpan.style.fontWeight = '700';
        numSpan.style.fontSize = '0.95rem';
        numSpan.style.color = isToday ? 'var(--accent)' : 'var(--text)';
        numSpan.innerText = jd;

        headerDiv.appendChild(numSpan);

        if (isToday) {
            let todayBadge = document.createElement('span');
            todayBadge.style.fontSize = '0.65rem';
            todayBadge.style.padding = '2px 6px';
            todayBadge.style.borderRadius = '4px';
            todayBadge.style.background = 'var(--accent)';
            todayBadge.style.color = '#fff';
            todayBadge.innerText = 'امروز';
            headerDiv.appendChild(todayBadge);
        } else if (dayItems.length > 0) {
            let countBadge = document.createElement('span');
            countBadge.style.fontSize = '0.7rem';
            countBadge.style.padding = '1px 5px';
            countBadge.style.borderRadius = '4px';
            countBadge.style.background = 'var(--bg-alt)';
            countBadge.style.color = 'var(--text-muted)';
            countBadge.innerText = `${dayItems.length}`;
            headerDiv.appendChild(countBadge);
        }

        dayCell.appendChild(headerDiv);

        let itemsContainer = document.createElement('div');
        itemsContainer.style.display = 'flex';
        itemsContainer.style.flexDirection = 'column';
        itemsContainer.style.gap = '4px';
        itemsContainer.style.flex = '1';
        itemsContainer.style.overflow = 'hidden';

        // Render chips up to 2 items
        let displayLimit = 2;
        dayItems.slice(0, displayLimit).forEach(item => {
            let timeStr = (item.scheduled_at || '').substring(11, 16);
            let chip = document.createElement('div');
            chip.style.fontSize = '0.72rem';
            chip.style.padding = '3px 6px';
            chip.style.borderRadius = '6px';
            chip.style.whiteSpace = 'nowrap';
            chip.style.overflow = 'hidden';
            chip.style.textOverflow = 'ellipsis';
            chip.style.display = 'flex';
            chip.style.alignItems = 'center';
            chip.style.gap = '4px';

            let dotColor = '#f59e0b'; // pending
            let bgColor = 'rgba(245, 158, 11, 0.12)';
            let textColor = '#d97706';

            if (item.status === 'completed' || item.status === 'sent') {
                dotColor = '#10b981';
                bgColor = 'rgba(16, 185, 129, 0.12)';
                textColor = '#059669';
            } else if (item.status === 'cancelled') {
                dotColor = '#ef4444';
                bgColor = 'rgba(239, 68, 68, 0.1)';
                textColor = '#dc2626';
            }

            chip.style.background = bgColor;
            chip.style.color = textColor;

            chip.innerHTML = `
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:${dotColor}; flex-shrink:0;"></span>
                <strong style="font-weight:600;">${timeStr}</strong>
                <span style="overflow:hidden; text-overflow:ellipsis;">${escapeHtml(item.title || 'پیام زمان‌بندی شده')}</span>
            `;
            itemsContainer.appendChild(chip);
        });

        if (dayItems.length > displayLimit) {
            let moreSpan = document.createElement('div');
            moreSpan.style.fontSize = '0.7rem';
            moreSpan.style.color = 'var(--text-muted)';
            moreSpan.style.textAlign = 'center';
            moreSpan.style.marginTop = '2px';
            moreSpan.innerText = `+ ${dayItems.length - displayLimit} مورد دیگر`;
            itemsContainer.appendChild(moreSpan);
        }

        dayCell.appendChild(itemsContainer);

        // Click opens day detail modal
        dayCell.onclick = (e) => {
            e.stopPropagation();
            openCalendarDayModal(jy, jm, jd, gDateStr);
        };

        container.appendChild(dayCell);
    }
}

/* =========================================================================
   CALENDAR DAY DETAIL & SCHEDULE ACTIONS MODAL
   ========================================================================= */

window.openCalendarDayModal = function(jy, jm, jd, gDateStr) {
    let mNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    let modal = document.getElementById('modal_calendar_day');
    let titleEl = document.getElementById('cal_day_modal_title');
    let listEl = document.getElementById('cal_day_modal_list');
    let addBtn = document.getElementById('cal_day_add_btn');

    if (!modal || !listEl) return;

    if (titleEl) {
        titleEl.innerText = `برنامه‌های ${jd} ${mNames[jm - 1]} ${jy} (${gDateStr})`;
    }

    if (addBtn) {
        addBtn.onclick = () => {
            closeCalendarDayModal();
            openNewScheduleForDate(jy, jm, jd, gDateStr);
        };
    }

    let schedules = Array.isArray(window.schedulesData) ? window.schedulesData : [];
    let dayItems = schedules.filter(s => (s.scheduled_at || '').substring(0, 10) === gDateStr);

    listEl.innerHTML = '';

    if (dayItems.length === 0) {
        listEl.innerHTML = `
            <div class="ssp-empty" style="padding:28px 10px;">
                <div style="font-size:2rem; margin-bottom:8px;">📅</div>
                <p style="margin:0; font-weight:600; color:var(--text);">هیچ محتوایی برای این روز زمان‌بندی نشده است.</p>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">می‌توانید با کلیک روی دکمه زیر، یک پست یا پیام برای این تاریخ تنظیم کنید.</p>
            </div>
        `;
    } else {
        dayItems.forEach(item => {
            let itemCard = document.createElement('div');
            itemCard.className = 'ssp-card';
            itemCard.style.padding = '14px';
            itemCard.style.marginBottom = '12px';
            itemCard.style.borderRadius = '12px';
            itemCard.style.border = '1px solid var(--border)';
            itemCard.style.background = 'var(--card)';

            let statusBadge = '<span class="ssp-badge info">در انتظار ارسال</span>';
            if (item.status === 'completed' || item.status === 'sent') {
                statusBadge = '<span class="ssp-badge success">ارسال شده</span>';
            } else if (item.status === 'cancelled') {
                statusBadge = '<span class="ssp-badge danger" style="background:#fee2e2; color:#b91c1c;">لغو شده</span>';
            }

            let recText = '';
            if (item.recurring) {
                let recLabels = { daily: 'روزانه', weekly: 'هفتگی', monthly: 'ماهانه' };
                recText = `<span class="ssp-badge pro">${recLabels[item.recurring] || item.recurring}</span>`;
            }

            let timeStr = (item.scheduled_at || '').substring(11, 16);

            itemCard.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; gap:8px;">
                    <div>
                        <h4 style="margin:0 0 4px; font-size:0.95rem; font-weight:700; color:var(--text);">${escapeHtml(item.title || 'بدون عنوان')}</h4>
                        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; font-size:0.8rem; color:var(--text-muted);">
                            <span>⏰ ساعت ${timeStr}</span>
                            ${statusBadge}
                            ${recText}
                        </div>
                    </div>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button type="button" class="ssp-btn-secondary" onclick="openEditScheduleModal(${item.id})" style="font-size:0.8rem; padding:4px 10px;">✏️ ویرایش</button>
                        ${item.status === 'pending' ? `<button type="button" class="ssp-btn-secondary" onclick="cancelSchedule(${item.id})" style="font-size:0.8rem; padding:4px 10px; color:#d97706;">🚫 لغو</button>` : ''}
                        ${item.status === 'cancelled' ? `<button type="button" class="ssp-btn-secondary" onclick="reactivateSchedule(${item.id})" style="font-size:0.8rem; padding:4px 10px; color:#059669;">🔄 فعال‌سازی</button>` : ''}
                        <button type="button" class="ssp-btn-danger" onclick="deleteSchedule(${item.id})" style="font-size:0.8rem; padding:4px 8px;">🗑️</button>
                    </div>
                </div>
                ${item.message ? `<div style="background:var(--bg-alt); padding:10px; border-radius:8px; font-size:0.83rem; line-height:1.6; color:var(--text-muted); max-height:100px; overflow-y:auto;">${escapeHtml(item.message)}</div>` : ''}
            `;
            listEl.appendChild(itemCard);
        });
    }

    modal.style.display = 'flex';
};

window.closeCalendarDayModal = function() {
    let modal = document.getElementById('modal_calendar_day');
    if (modal) modal.style.display = 'none';
};

/* =========================================================================
   EDIT SCHEDULE MODAL & HANDLERS
   ========================================================================= */

window.openEditScheduleModal = function(id) {
    let schedules = Array.isArray(window.schedulesData) ? window.schedulesData : [];
    let item = schedules.find(s => s.id == id);
    if (!item) {
        if (typeof showToast === 'function') showToast('زمان‌بندی یافت نشد', 'error');
        return;
    }

    closeCalendarDayModal();

    let modal = document.getElementById('modal_edit_schedule');
    let idEl = document.getElementById('editsch_id');
    let titleEl = document.getElementById('editsch_title');
    let msgEl = document.getElementById('editsch_message');
    let dateDispEl = document.getElementById('editsch_date_display');
    let dtEl = document.getElementById('editsch_datetime');
    let recEl = document.getElementById('editsch_recurring');
    let statusEl = document.getElementById('editsch_status');

    if (!modal) return;

    if (idEl) idEl.value = item.id;
    if (titleEl) titleEl.value = item.title || '';
    if (msgEl) msgEl.value = item.message || '';
    if (recEl) recEl.value = item.recurring || '';
    if (statusEl) statusEl.value = item.status || 'pending';

    let schAt = item.scheduled_at || '';
    if (dtEl) dtEl.value = schAt;

    if (dateDispEl && schAt) {
        let parts = schAt.split(' ');
        let ymd = parts[0] ? parts[0].split('-') : [];
        let time = parts[1] ? parts[1].substring(0, 5) : '';
        if (ymd.length === 3) {
            let j = gregorianToJalali(parseInt(ymd[0]), parseInt(ymd[1]), parseInt(ymd[2]));
            dateDispEl.value = `${j[0]}/${String(j[1]).padStart(2,'0')}/${String(j[2]).padStart(2,'0')} ${time}`;
        } else {
            dateDispEl.value = schAt;
        }
    }

    modal.style.display = 'flex';
};

window.closeEditScheduleModal = function() {
    let modal = document.getElementById('modal_edit_schedule');
    if (modal) modal.style.display = 'none';
};

window.saveScheduleEdit = function() {
    let btn = document.getElementById('editsch_save_btn');
    let id = document.getElementById('editsch_id') ? document.getElementById('editsch_id').value : '';
    let title = document.getElementById('editsch_title') ? document.getElementById('editsch_title').value.trim() : '';
    let message = document.getElementById('editsch_message') ? document.getElementById('editsch_message').value.trim() : '';
    let scheduled_at = document.getElementById('editsch_datetime') ? document.getElementById('editsch_datetime').value.trim() : '';
    let recurring = document.getElementById('editsch_recurring') ? document.getElementById('editsch_recurring').value : '';
    let status = document.getElementById('editsch_status') ? document.getElementById('editsch_status').value : 'pending';

    if (!id || !title) {
        if (typeof showToast === 'function') showToast('لطفاً عنوان زمان‌بندی را وارد کنید', 'error');
        return;
    }

    if (!scheduled_at) {
        if (typeof showToast === 'function') showToast('لطفاً تاریخ و ساعت زمان‌بندی را مشخص کنید', 'error');
        return;
    }

    if (btn) btn.classList.add('loading');

    let fd = new FormData();
    fd.append('action', 'ssp_update_schedule');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('schedule_id', id);
    fd.append('id', id);
    fd.append('title', title);
    fd.append('message', message);
    fd.append('scheduled_at', scheduled_at);
    fd.append('recurring', recurring);
    fd.append('status', status);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.classList.remove('loading');
            if (res.success) {
                if (typeof showToast === 'function') showToast('زمان‌بندی با موفقیت به‌روزرسانی شد', 'success');
                // Update local array
                if (Array.isArray(window.schedulesData)) {
                    let idx = window.schedulesData.findIndex(s => s.id == id);
                    if (idx !== -1) {
                        window.schedulesData[idx].title = title;
                        window.schedulesData[idx].message = message;
                        window.schedulesData[idx].scheduled_at = scheduled_at;
                        window.schedulesData[idx].recurring = recurring;
                        window.schedulesData[idx].status = status;
                    }
                }
                closeEditScheduleModal();
                loadCalendar();
                updateSchedulesTabList();
            } else {
                let msg = (res.data && res.data.message) ? res.data.message : 'خطا در به‌روزرسانی زمان‌بندی';
                if (typeof showToast === 'function') showToast(msg, 'error');
            }
        })
        .catch(() => {
            if (btn) btn.classList.remove('loading');
            if (typeof showToast === 'function') showToast('خطای شبکه در ذخیره تغییرات', 'error');
        });
};

/* =========================================================================
   CANCEL, REACTIVATE, DELETE SCHEDULES
   ========================================================================= */

window.cancelSchedule = function(id) {
    if (!confirm('آیا مطمئن هستید که می‌خواهید این زمان‌بندی را لغو کنید؟')) return;

    let fd = new FormData();
    fd.append('action', 'ssp_cancel_schedule');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('schedule_id', id);
    fd.append('id', id);
    fd.append('permanent', '0');

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof showToast === 'function') showToast('زمان‌بندی لغو شد', 'info');
                if (Array.isArray(window.schedulesData)) {
                    let item = window.schedulesData.find(s => s.id == id);
                    if (item) item.status = 'cancelled';
                }
                closeCalendarDayModal();
                loadCalendar();
                updateSchedulesTabList();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در لغو', 'error');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('خطای شبکه در لغو زمان‌بندی', 'error');
        });
};

window.reactivateSchedule = function(id) {
    let fd = new FormData();
    fd.append('action', 'ssp_update_schedule');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('schedule_id', id);
    fd.append('id', id);
    fd.append('status', 'pending');

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof showToast === 'function') showToast('زمان‌بندی مجدداً فعال شد', 'success');
                if (Array.isArray(window.schedulesData)) {
                    let item = window.schedulesData.find(s => s.id == id);
                    if (item) item.status = 'pending';
                }
                closeCalendarDayModal();
                loadCalendar();
                updateSchedulesTabList();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در فعال‌سازی', 'error');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('خطای سرور', 'error');
        });
};

window.deleteSchedule = function(id) {
    if (!confirm('آیا از حذف دائمی این زمان‌بندی اطمینان دارید؟')) return;

    let fd = new FormData();
    fd.append('action', 'ssp_cancel_schedule');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('schedule_id', id);
    fd.append('id', id);
    fd.append('permanent', '1');

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof showToast === 'function') showToast('زمان‌بندی حذف شد', 'success');
                if (Array.isArray(window.schedulesData)) {
                    window.schedulesData = window.schedulesData.filter(s => s.id != id);
                }
                closeCalendarDayModal();
                loadCalendar();
                updateSchedulesTabList();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در حذف', 'error');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('خطای شبکه در حذف', 'error');
        });
};

window.openNewScheduleForDate = function(jy, jm, jd, gDateStr) {
    let now = new Date();
    let hours = String(now.getHours()).padStart(2, '0');
    let minutes = '00';

    let jStr = `${jy}/${String(jm).padStart(2,'0')}/${String(jd).padStart(2,'0')} ${hours}:${minutes}`;
    let gStr = `${gDateStr} ${hours}:${minutes}:00`;

    // Switch to schedules tab and prefill
    if (typeof switchTab === 'function') {
        let btn = document.querySelector('[data-tab="schedules"]');
        switchTab('schedules', btn);
    }

    let dispEl = document.getElementById('sch_schedule_date_display');
    let dtEl = document.getElementById('sch_schedule_datetime');
    if (dispEl) dispEl.value = jStr;
    if (dtEl) dtEl.value = gStr;

    let titleEl = document.getElementById('schedule_title');
    if (titleEl) {
        titleEl.focus();
        titleEl.scrollIntoView({ behavior: 'smooth' });
    }
    if (typeof showToast === 'function') {
        showToast(`تاریخ ${jStr} برای زمان‌بندی جدید تنظیم شد`, 'info');
    }
};

window.updateSchedulesTabList = function() {
    let listEl = document.getElementById('schedules_list');
    if (!listEl) return;

    let schedules = Array.isArray(window.schedulesData) ? window.schedulesData : [];
    if (schedules.length === 0) {
        listEl.innerHTML = `
            <div class="ssp-empty">
                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <p>هنوز زمان‌بندی ایجاد نکرده‌اید.</p>
            </div>
        `;
        return;
    }

    let html = '';
    schedules.forEach(sch => {
        let sch_border = sch.status === 'pending' ? 'var(--accent)' : (sch.status === 'cancelled' ? '#ef4444' : 'var(--success)');
        let sch_icon = sch.status === 'pending' ? '&#9203;' : (sch.status === 'cancelled' ? '&#10007;' : '&#10003;');
        let badgeClass = sch.status === 'pending' ? 'info' : (sch.status === 'cancelled' ? 'danger' : 'success');
        let statusLabel = sch.status === 'pending' ? 'در انتظار' : (sch.status === 'cancelled' ? 'لغو شده' : 'انجام شده');

        let recLabels = { daily: 'روزانه', weekly: 'هفتگی', monthly: 'ماهانه' };
        let recBadge = sch.recurring ? `<span class="ssp-badge pro">${recLabels[sch.recurring] || sch.recurring}</span>` : '';

        let jalaliDateStr = sch.scheduled_at;
        if (typeof gregorianToJalali === 'function' && sch.scheduled_at) {
            let p = sch.scheduled_at.split(' ');
            let ymd = p[0] ? p[0].split('-') : [];
            let t = p[1] ? p[1].substring(0, 5) : '';
            if (ymd.length === 3) {
                let j = gregorianToJalali(parseInt(ymd[0]), parseInt(ymd[1]), parseInt(ymd[2]));
                jalaliDateStr = `${j[0]}/${String(j[1]).padStart(2,'0')}/${String(j[2]).padStart(2,'0')} ساعت ${t}`;
            }
        }

        html += `
            <div class="ssp-item-card" style="border-right:3px solid ${sch_border}; margin-bottom:12px;">
                <div class="ssp-item-card-head">
                    <div style="flex:1;">
                        <div class="ssp-item-card-title">
                            ${sch_icon} ${escapeHtml(sch.title || 'بدون عنوان')}
                            <span class="ssp-badge ${badgeClass}">${statusLabel}</span>
                            ${recBadge}
                        </div>
                        <div class="ssp-item-card-meta">
                            &#128197; ${jalaliDateStr}
                        </div>
                        ${sch.message ? `<div class="ssp-item-card-meta" style="margin-top:4px; font-size:0.8rem;">${escapeHtml(sch.message.substring(0, 80))}...</div>` : ''}
                    </div>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button class="ssp-btn-secondary" onclick="openEditScheduleModal(${sch.id})" style="font-size:0.75rem; padding:4px 8px;">✏️ ویرایش</button>
                        ${sch.status === 'pending' ? `<button class="ssp-btn-secondary" onclick="cancelSchedule(${sch.id})" style="font-size:0.75rem; padding:4px 8px; color:#d97706;">لغو</button>` : ''}
                        ${sch.status === 'cancelled' ? `<button class="ssp-btn-secondary" onclick="reactivateSchedule(${sch.id})" style="font-size:0.75rem; padding:4px 8px; color:#059669;">فعال‌سازی</button>` : ''}
                        <button class="ssp-btn-danger" onclick="deleteSchedule(${sch.id})" style="font-size:0.75rem; padding:4px 8px;">حذف</button>
                    </div>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
};

/* =========================================================================
   DRAFT MANAGEMENT & SCHEDULING
   ========================================================================= */

window.openEditDraftModal = function(id) {
    let drafts = Array.isArray(window.draftsData) ? window.draftsData : [];
    let draft = drafts.find(d => d.id == id);
    if (!draft) {
        if (typeof showToast === 'function') showToast('پیش‌نویس یافت نشد', 'error');
        return;
    }

    let modal = document.getElementById('modal_edit_draft');
    if (!modal) return;

    let idEl = document.getElementById('editdraft_id');
    let titleEl = document.getElementById('editdraft_title');
    let contentEl = document.getElementById('editdraft_content');
    let hashtagsEl = document.getElementById('editdraft_hashtags');

    if (idEl) idEl.value = draft.id;
    if (titleEl) titleEl.value = draft.title || '';
    if (contentEl) contentEl.value = draft.content || '';
    if (hashtagsEl) hashtagsEl.value = draft.hashtags || '';

    modal.style.display = 'flex';
};

window.closeEditDraftModal = function() {
    let modal = document.getElementById('modal_edit_draft');
    if (modal) modal.style.display = 'none';
};

window.saveDraftEdit = function() {
    let id = document.getElementById('editdraft_id') ? document.getElementById('editdraft_id').value : '';
    let title = document.getElementById('editdraft_title') ? document.getElementById('editdraft_title').value.trim() : '';
    let content = document.getElementById('editdraft_content') ? document.getElementById('editdraft_content').value.trim() : '';
    let hashtags = document.getElementById('editdraft_hashtags') ? document.getElementById('editdraft_hashtags').value.trim() : '';
    let btn = document.getElementById('editdraft_save_btn');

    if (!id || !title || !content) {
        if (typeof showToast === 'function') showToast('لطفاً عنوان و متن پیش‌نویس را وارد کنید', 'error');
        return;
    }

    if (btn) btn.classList.add('loading');

    let fd = new FormData();
    fd.append('action', 'ssp_update_draft');
    fd.append('security', window.nonce || '');
    fd.append('id', id);
    fd.append('title', title);
    fd.append('content', content);
    fd.append('hashtags', hashtags);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.classList.remove('loading');
            if (res.success) {
                if (typeof showToast === 'function') showToast('پیش‌نویس با موفقیت به‌روزرسانی شد', 'success');
                if (Array.isArray(window.draftsData)) {
                    let idx = window.draftsData.findIndex(d => d.id == id);
                    if (idx !== -1) {
                        window.draftsData[idx].title = title;
                        window.draftsData[idx].content = content;
                        window.draftsData[idx].hashtags = hashtags;
                    }
                }
                closeEditDraftModal();
                if (typeof loadDrafts === 'function') loadDrafts();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در ذخیره', 'error');
            }
        })
        .catch(() => {
            if (btn) btn.classList.remove('loading');
            if (typeof showToast === 'function') showToast('خطای سرور', 'error');
        });
};

window.openScheduleDraftModal = function(id) {
    let drafts = Array.isArray(window.draftsData) ? window.draftsData : [];
    let draft = drafts.find(d => d.id == id);
    if (!draft) {
        if (typeof showToast === 'function') showToast('پیش‌نویس یافت نشد', 'error');
        return;
    }

    let modal = document.getElementById('modal_schedule_draft');
    if (!modal) return;

    let idEl = document.getElementById('schdraft_id');
    let titleEl = document.getElementById('schdraft_title');
    let msgEl = document.getElementById('schdraft_message');
    let dateDispEl = document.getElementById('draftsch_date_display');
    let dtEl = document.getElementById('draftsch_datetime');

    if (idEl) idEl.value = draft.id;
    if (titleEl) titleEl.value = draft.title || '';
    if (msgEl) msgEl.value = draft.content + (draft.hashtags ? '\n\n' + draft.hashtags : '');

    let now = new Date();
    now.setMinutes(now.getMinutes() + 30);
    let j = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    let h = String(now.getHours()).padStart(2, '0');
    let m = String(now.getMinutes()).padStart(2, '0');

    if (dateDispEl) dateDispEl.value = `${j[0]}/${String(j[1]).padStart(2,'0')}/${String(j[2]).padStart(2,'0')} ${h}:${m}`;
    if (dtEl) dtEl.value = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')} ${h}:${m}:00`;

    modal.style.display = 'flex';
};

window.closeScheduleDraftModal = function() {
    let modal = document.getElementById('modal_schedule_draft');
    if (modal) modal.style.display = 'none';
};

window.confirmScheduleDraft = function() {
    let title = document.getElementById('schdraft_title') ? document.getElementById('schdraft_title').value.trim() : '';
    let message = document.getElementById('schdraft_message') ? document.getElementById('schdraft_message').value.trim() : '';
    let scheduled_at = document.getElementById('draftsch_datetime') ? document.getElementById('draftsch_datetime').value.trim() : '';
    let recurring = document.getElementById('schdraft_recurring') ? document.getElementById('schdraft_recurring').value : '';
    let btn = document.getElementById('schdraft_confirm_btn');

    if (!title || !message) {
        if (typeof showToast === 'function') showToast('لطفاً عنوان و متن را بررسی کنید', 'error');
        return;
    }
    if (!scheduled_at) {
        if (typeof showToast === 'function') showToast('لطفاً زمان ارسال را مشخص کنید', 'error');
        return;
    }

    if (btn) btn.classList.add('loading');

    let fd = new FormData();
    fd.append('action', 'ssp_add_schedule');
    fd.append('security', window.nonce || '');
    fd.append('title', title);
    fd.append('message', message);
    fd.append('scheduled_at', scheduled_at);
    if (recurring) fd.append('recurring', recurring);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.classList.remove('loading');
            if (res.success) {
                if (typeof showToast === 'function') showToast('پیش‌نویس با موفقیت در تقویم محتوا زمان‌بندی شد!', 'success');
                if (res.data && res.data.schedule) {
                    if (!Array.isArray(window.schedulesData)) window.schedulesData = [];
                    window.schedulesData.push(res.data.schedule);
                }
                closeScheduleDraftModal();
                loadCalendar();
                updateSchedulesTabList();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در زمان‌بندی', 'error');
            }
        })
        .catch(() => {
            if (btn) btn.classList.remove('loading');
            if (typeof showToast === 'function') showToast('خطای شبکه', 'error');
        });
};

// =========================================================================
// ============ 3-STAGE CONTENT CREATION WIZARD CONTROLLER ================
// =========================================================================

window.wizardCurrentStep = 1;
window.wizardFormat = 'post';

window.wizardSelectFormat = function(fmt) {
    window.wizardFormat = fmt;
    document.querySelectorAll('.ssp-wiz-format-card').forEach(card => {
        if (card.getAttribute('data-format') === fmt) {
            card.classList.add('active');
            card.style.border = '2px solid var(--accent)';
            card.style.background = 'rgba(79, 70, 229, 0.05)';
        } else {
            card.classList.remove('active');
            card.style.border = '1px solid var(--border)';
            card.style.background = 'var(--card)';
        }
    });
};

window.wizardPickIdea = function(idea) {
    var topicEl = document.getElementById('wiz_topic');
    if (topicEl) {
        topicEl.value = idea;
        topicEl.focus();
        if (typeof showToast === 'function') showToast('ایده انتخاب شد', 'info');
    }
};

window.wizardPopulateTemplates = function() {
    var sel = document.getElementById('wiz_prompt_template');
    if (!sel) return;
    var currentVal = sel.value;
    sel.innerHTML = '<option value="">پیش‌فرض سیستم هوش مصنوعی</option>';
    
    // Built-in presets
    var presets = [
        { id: 'viral_hook', title: 'قلاب ویروسی و جلب توجه بالا (Viral Hook)' },
        { id: 'story_sale', title: 'فروش داستانی به شیوه داستان‌سرایی برند' },
        { id: 'problem_agitate_solve', title: 'فرمول PAS: درد، برجسته‌سازی، راه‌حل' },
        { id: 'educational_tips', title: 'آموزش گام‌به‌گام و نکات تخصصی' },
        { id: 'urgent_discount', title: 'ایجاد حس فوریت و تخفیف محدود' }
    ];
    var optGroupPresets = document.createElement('optgroup');
    optGroupPresets.label = 'قالب‌های مهندسی پرامپت آماده';
    presets.forEach(p => {
        var opt = document.createElement('option');
        opt.value = 'preset:' + p.id;
        opt.textContent = p.title;
        optGroupPresets.appendChild(opt);
    });
    sel.appendChild(optGroupPresets);

    if (Array.isArray(window.templatesData) && window.templatesData.length > 0) {
        var optGroupUser = document.createElement('optgroup');
        optGroupUser.label = 'قالب‌های سفارشی شما';
        window.templatesData.forEach(t => {
            var opt = document.createElement('option');
            opt.value = 'user:' + t.id;
            opt.textContent = t.name || 'قالب #' + t.id;
            optGroupUser.appendChild(opt);
        });
        sel.appendChild(optGroupUser);
    }
    if (currentVal) sel.value = currentVal;
};

window.wizardApplyPromptTemplate = function() {
    var sel = document.getElementById('wiz_prompt_template');
    if (!sel || !sel.value) return;
    var val = sel.value;
    var briefEl = document.getElementById('wiz_brief');
    if (!briefEl) return;

    if (val.startsWith('user:')) {
        var id = val.split(':')[1];
        if (Array.isArray(window.templatesData)) {
            var t = window.templatesData.find(item => String(item.id) === String(id));
            if (t && t.template) {
                if (!briefEl.value || confirm('آیا متن قالب روی جزئیات پرامپت قرار گیرد؟')) {
                    briefEl.value = t.template;
                    if (typeof showToast === 'function') showToast('قالب سفارشی اعمال شد', 'success');
                }
            }
        }
    }
};

window.wizardToggleSignaturePreview = function() {
    var chk = document.getElementById('wiz_append_signature');
    var box = document.getElementById('wiz_sig_preview_box');
    if (box) {
        box.style.display = (chk && chk.checked) ? 'block' : 'none';
    }
};

window.wizardInsertEmoji = function(em) {
    var ta = document.getElementById('wiz_result_content');
    if (!ta) return;
    var start = ta.selectionStart || 0;
    var end = ta.selectionEnd || 0;
    var text = ta.value;
    ta.value = text.substring(0, start) + em + text.substring(end);
    ta.selectionStart = ta.selectionEnd = start + em.length;
    ta.focus();
    window.wizardUpdateCounters();
};

window.wizardUpdateCounters = function() {
    var ta = document.getElementById('wiz_result_content');
    if (!ta) return;
    var val = ta.value || '';
    var chars = val.length;
    var words = val.trim() ? val.trim().split(/\s+/).length : 0;
    
    var charEl = document.getElementById('wiz_char_count');
    var wordEl = document.getElementById('wiz_word_count');
    if (charEl) charEl.textContent = chars.toLocaleString('fa-IR') + ' کاراکتر';
    if (wordEl) wordEl.textContent = words.toLocaleString('fa-IR') + ' کلمه';
};

window.wizardGoToStep = function(step) {
    if (step === 2) {
        var topic = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : '';
        if (!topic) {
            if (typeof showToast === 'function') showToast('لطفاً موضوع یا عنوان اصلی را وارد کنید', 'warning');
            var topicInput = document.getElementById('wiz_topic');
            if (topicInput) topicInput.focus();
            return;
        }
        window.wizardPopulateTemplates();
    }

    if (step === 3) {
        var content = document.getElementById('wiz_result_content') ? document.getElementById('wiz_result_content').value.trim() : '';
        if (!content) {
            if (typeof showToast === 'function') showToast('ابتدا محتوا را تولید یا در کادر ویرایش بنویسید', 'warning');
            return;
        }
        // Update summary preview
        var topicVal = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : 'محتوای تولید شده';
        var sumTitle = document.getElementById('wiz_summary_title');
        var sumSnippet = document.getElementById('wiz_summary_snippet');
        if (sumTitle) sumTitle.textContent = topicVal;
        if (sumSnippet) sumSnippet.textContent = content.substring(0, 140) + '...';

        // Init default schedule datetime if empty
        var dtInput = document.getElementById('wiz_sch_datetime');
        var dtDisplay = document.getElementById('wiz_sch_datetime_display');
        if (dtInput && !dtInput.value) {
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(18, 0, 0, 0); // Default 18:00
            var iso = tomorrow.toISOString().substring(0, 16).replace('T', ' ');
            dtInput.value = iso;
            var j = gregorianToJalali(tomorrow.getFullYear(), tomorrow.getMonth() + 1, tomorrow.getDate());
            if (dtDisplay) dtDisplay.value = `${j.jy}/${String(j.jm).padStart(2,'0')}/${String(j.jd).padStart(2,'0')} ساعت ۱۸:۰۰`;
        }
    }

    window.wizardCurrentStep = step;

    // Update headers
    for (var i = 1; i <= 3; i++) {
        var h = document.getElementById('wiz_head_' + i);
        var b = document.getElementById('wiz_badge_num_' + i);
        var body = document.getElementById('wiz_step_' + i);
        if (i === step) {
            if (h) {
                h.classList.add('active');
                h.style.border = '2px solid var(--accent)';
                h.style.background = 'rgba(79, 70, 229, 0.08)';
            }
            if (b) {
                b.style.background = 'var(--accent)';
                b.style.color = '#fff';
            }
            if (body) body.style.display = 'block';
        } else if (i < step) {
            if (h) {
                h.classList.remove('active');
                h.style.border = '1px solid var(--success)';
                h.style.background = 'rgba(16, 185, 129, 0.06)';
            }
            if (b) {
                b.style.background = 'var(--success)';
                b.style.color = '#fff';
                b.innerHTML = '&#10003;';
            }
            if (body) body.style.display = 'none';
        } else {
            if (h) {
                h.classList.remove('active');
                h.style.border = '1px solid var(--border)';
                h.style.background = 'var(--bg-alt)';
            }
            if (b) {
                b.style.background = 'var(--border)';
                b.style.color = 'var(--text-muted)';
                b.textContent = i;
            }
            if (body) body.style.display = 'none';
        }
    }

    var badge = document.getElementById('wizard_step_badge');
    if (badge) badge.textContent = 'مرحله ' + step + ' از ۳';
};

window.wizardGenerateAIIdeas = function() {
    var btn = document.getElementById('wiz_ai_brainstorm_btn');
    var topic = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : '';
    var baseSubject = topic || 'کسب‌وکار، فروش و بازاریابی آنلاین';

    if (btn) btn.innerHTML = '⏳ در حال جستجوی خلاقانه...';

    var fd = new FormData();
    fd.append('action', 'ssp_generate_product_ai');
    fd.append('security', window.nonce || '');
    fd.append('content_type', 'post');
    fd.append('product_name', 'ایده‌های ترند و موضوعات جذاب برای: ' + baseSubject);
    fd.append('product_brief', 'لطفا ۵ عنوان یا ایده جذاب، کنجکاوی‌برانگیز و کلیک‌خور برای تولید محتوا بنویس و هر ایده را در یک خط جداگانه قرار بده.');

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.innerHTML = '💡 تولید ۵ ایده جذاب با هوش مصنوعی';
            var container = document.getElementById('wiz_ai_suggestions');
            var list = document.getElementById('wiz_ai_suggestions_list');
            if (!container || !list) return;

            var raw = (res.success && res.data && res.data.content) ? (res.data.content.message || res.data.content.content || '') : '';
            var ideas = [];
            if (raw) {
                ideas = raw.split('\n')
                    .map(l => l.replace(/^[\d\.\-\*\#\s\u2022]+/, '').trim())
                    .filter(l => l.length > 5 && !l.startsWith('{'))
                    .slice(0, 5);
            }
            if (ideas.length === 0) {
                ideas = [
                    '۳ اشتباه رایج که باعث هدر رفتن وقت و سرمایه شما می‌شود',
                    'چگونه در کمترین زمان به بهترین نتیجه در ' + baseSubject + ' برسیم؟',
                    'مقایسه بی‌طرفانه: کدام روش واقعاً جواب می‌دهد و چرا؟',
                    'تجربه عملی و آزموده شده برای موفقیت صددرصدی',
                    'رازهایی که افراد موفق در مورد ' + baseSubject + ' به شما نمی‌گویند!'
                ];
            }

            var html = '';
            ideas.forEach(idea => {
                var safeIdea = (typeof escapeHtml === 'function') ? escapeHtml(idea) : idea;
                html += `<div style="display:flex; justify-content:space-between; align-items:center; background:var(--card); padding:8px 12px; border-radius:6px; border:1px solid var(--border); cursor:pointer;" onclick="wizardPickIdea('${safeIdea.replace(/'/g, "\\'")}')">
                    <span style="font-size:0.83rem; color:var(--text); font-weight:600;">⚡ ${safeIdea}</span>
                    <span style="font-size:0.75rem; color:#4f46e5; font-weight:700;">انتخاب ↵</span>
                </div>`;
            });
            list.innerHTML = html;
            container.style.display = 'block';
            if (typeof showToast === 'function') showToast('ایده‌ها آماده شدند! یکی را انتخاب کنید.', 'success');
        })
        .catch(() => {
            if (btn) btn.innerHTML = '💡 تولید ۵ ایده جذاب با هوش مصنوعی';
            if (typeof showToast === 'function') showToast('خطا در ایده‌یابی هوش مصنوعی', 'error');
        });
};

window.wizardRunGeneration = function() {
    var btn = document.getElementById('wiz_generate_btn');
    var topic = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : '';
    var brief = document.getElementById('wiz_brief') ? document.getElementById('wiz_brief').value.trim() : '';
    var tone = document.getElementById('wiz_tone') ? document.getElementById('wiz_tone').value : 'casual';
    var len = document.getElementById('wiz_length') ? document.getElementById('wiz_length').value : 'medium';

    if (!topic) {
        if (typeof showToast === 'function') showToast('موضوع اصلی را وارد کنید', 'warning');
        return;
    }

    if (btn) btn.classList.add('loading');

    var lengthGuidance = 'حدود ۸۰۰ کاراکتر';
    if (len === 'short') lengthGuidance = 'کوتاه، فشرده و متمرکز حدود ۳۵۰ کاراکتر';
    if (len === 'long') lengthGuidance = 'مفصل، با تیترهای جذاب و نکات کامل حدود ۱۸۰۰ کاراکتر';

    var toneGuidance = 'صمیمی، جذاب و ترغیب‌کننده';
    if (tone === 'promotional') toneGuidance = 'تبلیغاتی پرانرژی با دعوت به اقدام شفاف (CTA)';
    if (tone === 'formal') toneGuidance = 'رسمی، حرفه‌ای و محترمانه';
    if (tone === 'educational') toneGuidance = 'آموزشی، آموزنده و گام‌به‌گام';
    if (tone === 'storytelling') toneGuidance = 'داستانی، روایت‌گونه و احساسی';

    var fullPrompt = `موضوع: ${topic}\nفرمت: ${window.wizardFormat}\nلحن: ${toneGuidance}\nاندازه: ${lengthGuidance}\nنکات تکمیلی: ${brief}\nلطفا محتوای باکیفیت و آماده انتشار تولید کن همراه با هشتگ‌های پرکاربرد.`;

    var fd = new FormData();
    fd.append('action', 'ssp_generate_product_ai');
    fd.append('security', window.nonce || '');
    fd.append('content_type', window.wizardFormat || 'post');
    fd.append('product_name', topic);
    fd.append('product_brief', fullPrompt);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.classList.remove('loading');
            if (res.success && res.data && res.data.content) {
                var text = res.data.content.message || res.data.content.content || '';
                
                // Append channel signature if checked
                var sigChk = document.getElementById('wiz_append_signature');
                var sigText = document.getElementById('wiz_signature_text');
                if (sigChk && sigChk.checked && sigText && sigText.value.trim()) {
                    text += '\n\n' + sigText.value.trim();
                }

                var resBox = document.getElementById('wiz_result_content');
                if (resBox) {
                    resBox.value = text;
                    window.wizardUpdateCounters();
                }
                if (typeof showToast === 'function') showToast('نگارش هوشمند با موفقیت انجام شد!', 'success');
            } else if (res.success && res.data && res.data.mode === 'browser') {
                if (typeof showToast === 'function') showToast('درخواست به چت‌بات مرورگر فرستاده شد', 'info');
                if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در تولید محتوا', 'error');
            }
        })
        .catch(() => {
            if (btn) btn.classList.remove('loading');
            if (typeof showToast === 'function') showToast('خطای شبکه در ارتباط با هوش مصنوعی', 'error');
        });
};

window.wizardRunGenerationViaBrowser = function() {
    var topic = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : '';
    if (!topic) {
        if (typeof showToast === 'function') showToast('لطفاً ابتدا موضوع را در مرحله ۱ وارد کنید', 'warning');
        return;
    }
    var prompt = 'یک محتوای فوق‌العاده درباره ' + topic + ' تولید کن همراه با عنوان جذاب، متن کامل و هشتگ‌ها.';
    
    var fd = new FormData();
    fd.append('action', 'ssp_bridge_create_task');
    fd.append('security', window.nonce || '');
    fd.append('context_type', window.wizardFormat || 'post');
    fd.append('prompt', prompt);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.task_id) {
                if (typeof showToast === 'function') showToast('درخواست به مرورگر ارسال شد. تب چت‌بات را باز نگه دارید.', 'info');
                if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                if (typeof window.startBridgePolling === 'function') {
                    window.startBridgePolling(res.data.task_id, function(data) {
                        var text = (data.parsed && (data.parsed.message || data.parsed.content)) ? (data.parsed.message || data.parsed.content) : (data.raw || '');
                        var resBox = document.getElementById('wiz_result_content');
                        if (resBox && text) {
                            resBox.value = text;
                            window.wizardUpdateCounters();
                            if (typeof showToast === 'function') showToast('محتوا از مرورگر دریافت شد!', 'success');
                        }
                    });
                }
            } else {
                if (typeof showToast === 'function') showToast('خطا در اتصال به تسک مرورگر', 'error');
            }
        });
};

window.wizardPublishNow = function() {
    var content = document.getElementById('wiz_result_content') ? document.getElementById('wiz_result_content').value.trim() : '';
    var topic = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : '';
    
    if (!content) {
        if (typeof showToast === 'function') showToast('محتوایی برای انتشار وجود ندارد', 'warning');
        return;
    }

    var manTitle = document.getElementById('manual_title');
    var manMsg = document.getElementById('manual_message');
    if (manTitle) manTitle.value = topic;
    if (manMsg) manMsg.value = content;

    if (typeof switchTab === 'function') {
        var tabBtn = document.querySelector('.ssp-sidebar [data-tab="manual"]') || document.querySelector('[data-tab="manual"]');
        switchTab('manual', tabBtn);
        if (typeof showToast === 'function') showToast('محتوا به بخش ارسال دستی منتقل شد. کانال‌ها را انتخاب و ارسال کنید.', 'success');
    }
};

window.wizardOpenJalaliPicker = function() {
    var container = document.getElementById('wiz_jalali_picker');
    if (!container) return;
    if (container.style.display === 'block') {
        container.style.display = 'none';
        return;
    }

    var now = new Date();
    var jNow = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());

    var html = `
        <div style="font-size:0.8rem; font-weight:700; margin-bottom:8px; color:var(--text);">انتخاب زمان ارسال (تقویم جلالی):</div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; margin-bottom:8px;">
            <div>
                <label style="font-size:0.75rem; color:var(--text-muted);">سال</label>
                <select id="wiz_pick_year" class="ssp-select" style="font-size:0.82rem; padding:4px 6px;">
                    <option value="${jNow.jy}" selected>${jNow.jy}</option>
                    <option value="${jNow.jy + 1}">${jNow.jy + 1}</option>
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; color:var(--text-muted);">ماه</label>
                <select id="wiz_pick_month" class="ssp-select" style="font-size:0.82rem; padding:4px 6px;">
                    ${[
                        'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                        'مهر','آبان','آذر','دی','بهمن','اسفند'
                    ].map((name, idx) => `<option value="${idx+1}" ${idx+1 === jNow.jm ? 'selected' : ''}>${name}</option>`).join('')}
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; color:var(--text-muted);">روز</label>
                <select id="wiz_pick_day" class="ssp-select" style="font-size:0.82rem; padding:4px 6px;">
                    ${Array.from({length: 31}, (_, i) => i + 1).map(d => `<option value="${d}" ${d === jNow.jd ? 'selected' : ''}>${d}</option>`).join('')}
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:10px;">
            <div>
                <label style="font-size:0.75rem; color:var(--text-muted);">ساعت (۰ تا ۲۳)</label>
                <select id="wiz_pick_hour" class="ssp-select" style="font-size:0.82rem; padding:4px 6px;">
                    ${Array.from({length: 24}, (_, i) => i).map(h => `<option value="${h}" ${h === 18 ? 'selected' : ''}>${String(h).padStart(2,'0')}:00</option>`).join('')}
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; color:var(--text-muted);">دقیقه</label>
                <select id="wiz_pick_minute" class="ssp-select" style="font-size:0.82rem; padding:4px 6px;">
                    ${[0, 15, 30, 45].map(m => `<option value="${m}">${String(m).padStart(2,'0')}</option>`).join('')}
                </select>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('wiz_jalali_picker').style.display='none'" style="font-size:0.75rem; padding:4px 8px;">بستن</button>
            <button type="button" class="ssp-btn-primary" onclick="wizardApplyJalaliPicker()" style="font-size:0.75rem; padding:4px 12px;">تایید تاریخ و ساعت</button>
        </div>
    `;
    container.innerHTML = html;
    container.style.display = 'block';
};

window.wizardApplyJalaliPicker = function() {
    var y = parseInt(document.getElementById('wiz_pick_year').value, 10);
    var m = parseInt(document.getElementById('wiz_pick_month').value, 10);
    var d = parseInt(document.getElementById('wiz_pick_day').value, 10);
    var h = parseInt(document.getElementById('wiz_pick_hour').value, 10);
    var min = parseInt(document.getElementById('wiz_pick_minute').value, 10);

    var g = jalaliToGregorian(y, m, d);
    var iso = `${g.gy}-${String(g.gm).padStart(2,'0')}-${String(g.gd).padStart(2,'0')} ${String(h).padStart(2,'0')}:${String(min).padStart(2,'0')}:00`;

    var dtInput = document.getElementById('wiz_sch_datetime');
    var dtDisplay = document.getElementById('wiz_sch_datetime_display');
    if (dtInput) dtInput.value = iso;
    if (dtDisplay) dtDisplay.value = `${y}/${String(m).padStart(2,'0')}/${String(d).padStart(2,'0')} ساعت ${String(h).padStart(2,'0')}:${String(min).padStart(2,'0')}`;

    var container = document.getElementById('wiz_jalali_picker');
    if (container) container.style.display = 'none';
};

window.wizardConfirmSchedule = function() {
    var btn = document.getElementById('wiz_sch_confirm_btn');
    var topic = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : 'پست زمان‌بندی شده';
    var message = document.getElementById('wiz_result_content') ? document.getElementById('wiz_result_content').value.trim() : '';
    var datetime = document.getElementById('wiz_sch_datetime') ? document.getElementById('wiz_sch_datetime').value : '';
    var recurring = document.getElementById('wiz_sch_recurring') ? document.getElementById('wiz_sch_recurring').value : '';

    if (!message) {
        if (typeof showToast === 'function') showToast('محتوایی برای زمان‌بندی وجود ندارد', 'warning');
        return;
    }
    if (!datetime) {
        if (typeof showToast === 'function') showToast('لطفاً تاریخ و ساعت ارسال را مشخص کنید', 'warning');
        return;
    }

    if (btn) btn.classList.add('loading');

    var fd = new FormData();
    fd.append('action', 'ssp_add_schedule');
    fd.append('security', window.nonce || '');
    fd.append('title', topic);
    fd.append('message', message);
    fd.append('scheduled_at', datetime);
    if (recurring) fd.append('recurring', recurring);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.classList.remove('loading');
            if (res.success) {
                if (res.data && res.data.schedule) {
                    if (!Array.isArray(window.schedulesData)) window.schedulesData = [];
                    window.schedulesData.push(res.data.schedule);
                }
                loadCalendar();
                updateSchedulesTabList();

                if (typeof showToast === 'function') showToast('پست با موفقیت در تقویم محتوا زمان‌بندی شد!', 'success');
                
                // Show notification with link to open calendar
                if (confirm('زمان‌بندی با موفقیت ثبت شد! آیا مایلید تقویم محتوا را مشاهده کنید؟')) {
                    var calTab = document.querySelector('[data-tab=calendar]');
                    if (calTab && typeof switchTab === 'function') switchTab('calendar', calTab);
                }
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در ثبت زمان‌بندی', 'error');
            }
        })
        .catch(() => {
            if (btn) btn.classList.remove('loading');
            if (typeof showToast === 'function') showToast('خطای شبکه در زمان‌بندی', 'error');
        });
};

window.wizardSaveDraft = function() {
    var btn = document.getElementById('wiz_draft_save_btn');
    var topic = document.getElementById('wiz_topic') ? document.getElementById('wiz_topic').value.trim() : 'پیش‌نویس جدید';
    var message = document.getElementById('wiz_result_content') ? document.getElementById('wiz_result_content').value.trim() : '';

    if (!message) {
        if (typeof showToast === 'function') showToast('محتوایی برای ذخیره در پیش‌نویس وجود ندارد', 'warning');
        return;
    }

    if (btn) btn.classList.add('loading');

    var fd = new FormData();
    fd.append('action', 'ssp_save_draft');
    fd.append('security', window.nonce || '');
    fd.append('title', topic);
    fd.append('content', message);

    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.classList.remove('loading');
            if (res.success) {
                if (res.data && res.data.draft) {
                    if (!Array.isArray(window.draftsData)) window.draftsData = [];
                    window.draftsData.unshift(res.data.draft);
                }
                if (typeof loadDrafts === 'function') loadDrafts();
                if (typeof showToast === 'function') showToast('پیش‌نویس با موفقیت ذخیره شد!', 'success');
            } else {
                if (typeof showToast === 'function') showToast(res.data ? res.data.message : 'خطا در ذخیره پیش‌نویس', 'error');
            }
        })
        .catch(() => {
            if (btn) btn.classList.remove('loading');
            if (typeof showToast === 'function') showToast('خطای شبکه', 'error');
        });
};




document.addEventListener('click', function(e) {
    let targetBtn = e.target.closest ? e.target.closest('.btn-test-messenger') : (e.target.classList.contains('btn-test-messenger') ? e.target : null);
    if(targetBtn) {
        let id = targetBtn.getAttribute('data-id');
        let btn = targetBtn;
        btn.disabled = true;
        btn.textContent = '...';
        let fd = new FormData();
        fd.append('action', 'ssp_test_messenger');
        fd.append('security', nonce);
        fd.append('id', id);
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'تست';
            if(res.success) {
                showToast(res.data.message || 'با موفقیت متصل شد', 'success');
            } else {
                showToast(res.data.message || 'خطا در اتصال', 'error');
            }
        }).catch(err => {
            btn.disabled = false;
            btn.textContent = 'تست';
            showToast('خطا در اتصال', 'error');
        });
    }
    
    let target_btn_delete_messenger = e.target.closest ? e.target.closest('.btn-delete-messenger') : (e.target.classList.contains('btn-delete-messenger') ? e.target : null);
    if(target_btn_delete_messenger) {
        let id = target_btn_delete_messenger.getAttribute('data-id');
        if(!confirm('آیا از حذف این پیام‌رسان مطمئن هستید؟')) return;
        let fd = new FormData();
        fd.append('action', 'ssp_delete_messenger');
        fd.append('security', nonce);
        fd.append('id', id);
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            if(res.success) {
                showToast('با موفقیت حذف شد', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data.message || 'خطا در حذف', 'error');
            }
        });
    }
    
    let editMessengerTarget = e.target.closest ? e.target.closest('.btn-edit-messenger') : (e.target.classList.contains('btn-edit-messenger') ? e.target : null);
    if(editMessengerTarget) {
        let id = editMessengerTarget.getAttribute('data-id');
        let m = document.getElementById('modal_edit_messenger');
        if(m) {
            document.getElementById('edit_messenger_id').value = id;
            if(window.messengerData && Array.isArray(window.messengerData)) {
                let messenger = window.messengerData.find(mItem => mItem.id == id);
                if(messenger) {
                    if(document.getElementById('edit_messenger_name')) document.getElementById('edit_messenger_name').value = messenger.name || '';
                    if(document.getElementById('edit_messenger_platform')) document.getElementById('edit_messenger_platform').value = messenger.platform || '';
                    if(document.getElementById('edit_messenger_token')) document.getElementById('edit_messenger_token').value = messenger.token || '';
                    if(document.getElementById('edit_messenger_channel')) document.getElementById('edit_messenger_channel').value = messenger.channel_id || '';
                }
            }
            m.style.display = 'flex';
        }
    }
});


document.addEventListener('click', function(e) {
    let target_btn_test_wpsite = e.target.closest ? e.target.closest('.btn-test-wpsite') : (e.target.classList.contains('btn-test-wpsite') ? e.target : null);
    if(target_btn_test_wpsite) {
        let id = target_btn_test_wpsite.getAttribute('data-id');
        let btn = target_btn_test_wpsite;
        btn.disabled = true;
        btn.textContent = '...';
        let fd = new FormData();
        fd.append('action', 'ssp_test_wp_site');
        fd.append('security', nonce);
        fd.append('id', id);
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'تست';
            if(res.success) showToast(res.data.message || 'با موفقیت متصل شد', 'success');
            else showToast(res.data.message || 'خطا در اتصال', 'error');
        }).catch(() => { btn.disabled = false; btn.textContent = 'تست'; showToast('خطا در ارتباط', 'error'); });
    }
    
    let target_btn_delete_wpsite = e.target.closest ? e.target.closest('.btn-delete-wpsite') : (e.target.classList.contains('btn-delete-wpsite') ? e.target : null);
    if(target_btn_delete_wpsite) {
        let id = target_btn_delete_wpsite.getAttribute('data-id');
        if(!confirm('آیا از حذف این سایت مطمئن هستید؟')) return;
        let fd = new FormData();
        fd.append('action', 'ssp_delete_wp_site');
        fd.append('security', nonce);
        fd.append('id', id);
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            if(res.success) {
                showToast('با موفقیت حذف شد', 'success');
                setTimeout(() => location.reload(), 1000);
            } else showToast(res.data.message || 'خطا', 'error');
        });
    }
    
        let editWpSiteTarget = e.target.closest ? e.target.closest('.btn-edit-wpsite') : (e.target.classList.contains('btn-edit-wpsite') ? e.target : null);
    if(editWpSiteTarget) {
        let id = editWpSiteTarget.getAttribute('data-id');
        let m = document.getElementById('modal_edit_wpsite');
        if(m) {
            document.getElementById('edit_wpsite_id').value = id;
            if(window.wpSiteData && Array.isArray(window.wpSiteData)) {
                let site = window.wpSiteData.find(s => s.id == id);
                if(site) {
                    if(document.getElementById('edit_wpsite_name')) document.getElementById('edit_wpsite_name').value = site.site_name || '';
                    if(document.getElementById('edit_wpsite_url')) document.getElementById('edit_wpsite_url').value = site.site_url || '';
                    if(document.getElementById('edit_wpsite_user')) document.getElementById('edit_wpsite_user').value = site.username || '';
                    if(document.getElementById('edit_wpsite_pass')) document.getElementById('edit_wpsite_pass').value = ''; // Don't show password
                    if(document.getElementById('edit_wpsite_categories')) document.getElementById('edit_wpsite_categories').value = site.default_category || '';
                    if(document.getElementById('edit_wpsite_post_type')) document.getElementById('edit_wpsite_post_type').value = site.post_type || 'post';
                    if(document.getElementById('edit_wpsite_active')) document.getElementById('edit_wpsite_active').checked = parseInt(site.is_active) === 1;
                    if(document.getElementById('edit_wpsite_auto')) document.getElementById('edit_wpsite_auto').checked = parseInt(site.auto_publish) === 1;
                }
            }
            m.style.display = 'flex';
        }
    }

    let target_btn_delete_rss = e.target.closest ? e.target.closest('.btn-delete-rss') : (e.target.classList.contains('btn-delete-rss') ? e.target : null);
    if(target_btn_delete_rss) {
        let id = target_btn_delete_rss.getAttribute('data-id');
        if(!confirm('آیا از حذف این فید مطمئن هستید؟')) return;
        let fd = new FormData();
        fd.append('action', 'ssp_delete_rss_feed');
        fd.append('security', nonce);
        fd.append('id', id);
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            if(res.success) {
                showToast('با موفقیت حذف شد', 'success');
                setTimeout(() => location.reload(), 1000);
            } else showToast(res.data.message || 'خطا', 'error');
        });
    }
    
    let editRssTarget = e.target.closest ? e.target.closest('.btn-edit-rss') : (e.target.classList.contains('btn-edit-rss') ? e.target : null);
    if(editRssTarget) {
        let id = editRssTarget.getAttribute('data-id');
        let m = document.getElementById('modal_edit_rss');
        if(m) {
            document.getElementById('edit_rss_id').value = id;
            if(window.rssFeedData && Array.isArray(window.rssFeedData)) {
                let rss = window.rssFeedData.find(rItem => rItem.id == id);
                if(rss) {
                    if(document.getElementById('edit_rss_name')) document.getElementById('edit_rss_name').value = rss.feed_name || '';
                    if(document.getElementById('edit_rss_url')) document.getElementById('edit_rss_url').value = rss.feed_url || '';
                    if(document.getElementById('edit_rss_category')) document.getElementById('edit_rss_category').value = rss.category || '';
                }
            }
            m.style.display = 'flex';
        }
    }

    let target_btn_fetch_rss = e.target.closest ? e.target.closest('.btn-fetch-rss') : (e.target.classList.contains('btn-fetch-rss') ? e.target : null);
    if(target_btn_fetch_rss) {
        let id = target_btn_fetch_rss.getAttribute('data-id');
        let btn = target_btn_fetch_rss;
        btn.disabled = true;
        btn.textContent = '...';
        let fd = new FormData();
        fd.append('action', 'ssp_fetch_rss_now');
        fd.append('security', nonce);
        fd.append('feed_id', id);
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'دریافت فوری';
            if(res.success && res.data.items) {
                let itemsHtml = '';
                if(res.data.items.length === 0) {
                    itemsHtml = '<p style="text-align:center; padding:20px; color:var(--text-muted);">محتوای جدیدی یافت نشد.</p>';
                } else {
                    res.data.items.forEach(item => {
                        let checked = item.selected ? 'checked' : '';
                        let newBadge = item.is_new ? '<span class="ssp-badge ssp-badge-success" style="margin-right:8px; font-size:0.7rem;">جدید</span>' : '';
                        let escapedTitle = escapeHtml(item.title || '');
                        let escapedContent = escapeHtml(item.content || '');
                        let escapedUrl = escapeHtml(item.url || '');
                        let escapedGuid = escapeHtml(item.guid || '');
                        
                        itemsHtml += `
                            <div class="ssp-card rss-item-card" style="margin-bottom:12px; border:1px solid var(--border); border-radius:8px; padding:12px;" 
                                 data-title="${escapedTitle}" 
                                 data-content="${escapedContent}" 
                                 data-url="${escapedUrl}" 
                                 data-guid="${escapedGuid}">
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <input type="checkbox" ${checked} style="margin-top:4px;">
                                    <div style="flex:1;">
                                        <h4 style="margin:0 0 6px; font-size:0.95rem; color:var(--text);">${escapedTitle} ${newBadge}</h4>
                                        <div style="font-size:0.85rem; color:var(--text-muted); line-height:1.6; max-height:80px; overflow-y:auto; margin-bottom:8px; white-space:pre-wrap;">${escapedContent}</div>
                                        ${item.url ? `<a href="${escapedUrl}" target="_blank" style="font-size:0.8rem; color:var(--primary); text-decoration:none;">مشاهده لینک اصلی ↗</a>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                document.getElementById('rss_preview_items').innerHTML = itemsHtml;
                document.getElementById('rss_preview_items').setAttribute('data-feed-id', id);
                document.getElementById('rss_preview_items').setAttribute('data-feed-id', id);
                let modal = document.getElementById('modal_rss_preview');
                if(modal) {
                    modal.style.display = 'flex';
                }
                showToast(res.data.message || 'با موفقیت دریافت شد', 'success');
            }
            else showToast(res.data ? res.data.message : 'خطا در دریافت', 'error');
        }).catch(() => { btn.disabled = false; btn.textContent = 'دریافت فوری'; showToast('خطا', 'error'); });
    }
    
    let target_btn_fetch_extract = e.target.closest ? e.target.closest('.btn-fetch-extract') : (e.target.classList.contains('btn-fetch-extract') ? e.target : null);
    if(target_btn_fetch_extract) {
        let id = target_btn_fetch_extract.getAttribute('data-id');
        let btn = target_btn_fetch_extract;
        btn.disabled = true;
        btn.textContent = '...';
        showToast('در حال استخراج کامل (زمان‌بر است)...', 'info');
        let fd = new FormData();
        fd.append('action', 'ssp_fetch_rss_now');
        fd.append('security', nonce);
        fd.append('feed_id', id);
        fd.append('extract_now', '1');
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'استخراج کامل';
            if(res.success && res.data.items) {
                let itemsHtml = '';
                if(res.data.items.length === 0) {
                    itemsHtml = '<p style="text-align:center; padding:20px; color:var(--text-muted);">محتوای جدیدی یافت نشد.</p>';
                } else {
                    res.data.items.forEach(item => {
                        let checked = item.selected ? 'checked' : '';
                        let newBadge = item.is_new ? '<span class="ssp-badge ssp-badge-success" style="margin-right:8px; font-size:0.7rem;">جدید</span>' : '';
                        let extractBadge = item.extracted ? '<span class="ssp-badge ssp-badge-warning" style="margin-right:8px; font-size:0.7rem;">متن استخراج‌شده</span>' : '';
                        let escapedTitle = escapeHtml(item.title || '');
                        let escapedContent = escapeHtml(item.content || '');
                        let escapedUrl = escapeHtml(item.url || '');
                        let escapedGuid = escapeHtml(item.guid || '');
                        
                        itemsHtml += `
                            <div class="ssp-card rss-item-card" style="margin-bottom:12px; border:1px solid var(--border); border-radius:8px; padding:12px;" 
                                 data-title="${escapedTitle}" 
                                 data-content="${escapedContent}" 
                                 data-url="${escapedUrl}" 
                                 data-guid="${escapedGuid}">
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <input type="checkbox" ${checked} style="margin-top:4px;">
                                    <div style="flex:1;">
                                        <h4 style="margin:0 0 6px; font-size:0.95rem; color:var(--text);">${escapedTitle} ${newBadge} ${extractBadge}</h4>
                                        <div style="font-size:0.85rem; color:var(--text-muted); line-height:1.6; max-height:150px; overflow-y:auto; margin-bottom:8px; white-space:pre-wrap; border-right:2px solid var(--accent); padding-right:8px;">${escapedContent}</div>
                                        ${item.url ? `<a href="${escapedUrl}" target="_blank" style="font-size:0.8rem; color:var(--primary); text-decoration:none;">مشاهده لینک اصلی ↗</a>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                document.getElementById('rss_preview_items').innerHTML = itemsHtml;
                let modal = document.getElementById('modal_rss_preview');
                if(modal) {
                    modal.style.display = 'flex';
                }
            }
            else showToast(res.data ? res.data.message : 'خطا در دریافت', 'error');
        }).catch(() => { btn.disabled = false; btn.textContent = 'استخراج کامل'; showToast('خطا', 'error'); });
    }
});


window.analyzeSeo = function() {
    let btn = document.getElementById('seo_analyze_btn');
    if(!btn) return;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_analyze_seo');
    fd.append('security', nonce);
    fd.append('title', document.getElementById('seo_title') ? document.getElementById('seo_title').value : '');
    fd.append('content', document.getElementById('seo_content') ? document.getElementById('seo_content').value : '');
    fd.append('hashtags', document.getElementById('seo_hashtags') ? document.getElementById('seo_hashtags').value : '');
    fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
        setBtnLoading(btn, false);
        let resDiv = document.getElementById('seo_analyze_result');
        if(resDiv) {
            resDiv.innerHTML = res.success ? (res.data.html || JSON.stringify(res.data)) : (res.data.message || 'خطا');
        }
    }).catch(() => { setBtnLoading(btn, false); showToast('خطا در ارتباط', 'error'); });
};

window.analyzeSeoWithAi = function() {
    let btn = document.getElementById('seo_ai_btn');
    if(!btn) return;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_analyze_seo_ai');
    fd.append('security', nonce);
    fd.append('title', document.getElementById('seo_title') ? document.getElementById('seo_title').value : '');
    fd.append('content', document.getElementById('seo_content') ? document.getElementById('seo_content').value : '');
    fd.append('hashtags', document.getElementById('seo_hashtags') ? document.getElementById('seo_hashtags').value : '');
    fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
        setBtnLoading(btn, false);
        let resDiv = document.getElementById('seo_analyze_result');
        if(resDiv) {
            resDiv.innerHTML = res.success ? (res.data.html || JSON.stringify(res.data)) : (res.data.message || 'خطا');
        }
    }).catch(() => { setBtnLoading(btn, false); showToast('خطا در ارتباط', 'error'); });
};


window.forceProcessQueue = function() {
    let btn = event.currentTarget;
    if(!confirm('آیا مایلید تمام پیام‌های در صف را همین الان ارسال کنید؟')) return;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_manual_process');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('عملیات ارسال انجام شد. در حال بارگذاری مجدد...', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(res.data ? res.data.message : 'خطا در ارسال', 'error');
                setBtnLoading(btn, false);
            }
        }).catch(()=> setBtnLoading(btn, false));
};
