const fs = require('fs');

let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let newCore = `
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
                    resWrap.innerHTML = '<div class="ssp-card"><h3>عناوین پیشنهادی:</h3><p>' + (res.data.content||'').replace(/\\n/g, '<br>') + '</p></div>';
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
    e.preventDefault();
    let btn = document.getElementById('manual_send_btn');
    if(!btn) return;
    
    // gather data
    let fd = new FormData();
    fd.append('action', 'ssp_manual_send');
    fd.append('security', nonce);
    
    let title = document.getElementById('manual_title');
    if(title) fd.append('title', title.value);
    
    let msg = document.getElementById('manual_message');
    if(msg && msg.value) fd.append('message', msg.value);
    else { showToast('متن پیام الزامی است', 'error'); return; }
    
    // Messengers check
    let selected = [];
    document.querySelectorAll('.manual_messenger_cb:checked').forEach(cb => {
        selected.push(cb.value);
    });
    if(selected.length > 0) fd.append('messengers', selected.join(','));
    else { showToast('حداقل یک پیام‌رسان انتخاب کنید', 'error'); return; }
    
    setBtnLoading(btn, true);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showToast('پیام در صف ارسال قرار گرفت', 'success');
                if(msg) msg.value = '';
                if(title) title.value = '';
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(() => setBtnLoading(btn, false));
};
`;

core = core + "\n" + newCore;

['suggestSeoTitle', 'generateMetaDesc', 'clearLogs', 'retryMessageOld', 'retryMessage'].forEach(fn => {
    core = core.replace("'" + fn + "', ", "");
});

fs.writeFileSync('assets/js/portal-core.js', core);
