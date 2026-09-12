const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const snippet = `
document.addEventListener('click', function(e) {
    if(e.target && e.target.classList.contains('btn-test-wpsite')) {
        let id = e.target.getAttribute('data-id');
        let btn = e.target;
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
    
    if(e.target && e.target.classList.contains('btn-delete-wpsite')) {
        let id = e.target.getAttribute('data-id');
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
    
    if(e.target && e.target.classList.contains('btn-edit-wpsite')) {
        let id = e.target.getAttribute('data-id');
        let m = document.getElementById('modal_edit_wpsite');
        if(m) {
            document.getElementById('edit_wpsite_id').value = id;
            m.style.display = 'flex';
        }
    }

    if(e.target && e.target.classList.contains('btn-delete-rss')) {
        let id = e.target.getAttribute('data-id');
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
    
    if(e.target && e.target.classList.contains('btn-edit-rss')) {
        let id = e.target.getAttribute('data-id');
        let m = document.getElementById('modal_edit_rss');
        if(m) {
            document.getElementById('edit_rss_id').value = id;
            m.style.display = 'flex';
        }
    }

    if(e.target && e.target.classList.contains('btn-fetch-rss')) {
        let id = e.target.getAttribute('data-id');
        let btn = e.target;
        btn.disabled = true;
        btn.textContent = '...';
        let fd = new FormData();
        fd.append('action', 'ssp_fetch_rss_now');
        fd.append('security', nonce);
        fd.append('feed_id', id);
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            btn.disabled = false;
            btn.textContent = 'دریافت فوری';
            if(res.success) showToast(res.data.message || 'با موفقیت دریافت شد', 'success');
            else showToast(res.data.message || 'خطا در دریافت', 'error');
        }).catch(() => { btn.disabled = false; btn.textContent = 'دریافت فوری'; showToast('خطا', 'error'); });
    }
    
    if(e.target && e.target.classList.contains('btn-fetch-extract')) {
        showToast('در حال استخراج کامل (تست)...', 'info');
    }
});
`;

if (!code.includes('btn-delete-wpsite')) {
    code += '\n' + snippet;
    fs.writeFileSync('assets/js/portal-core.js', code);
    console.log("Appended wpsite and rss events to portal-core.js");
}
