const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const snippet = `
document.addEventListener('click', function(e) {
    if(e.target && e.target.classList.contains('btn-test-messenger')) {
        let id = e.target.getAttribute('data-id');
        let btn = e.target;
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
    
    if(e.target && e.target.classList.contains('btn-delete-messenger')) {
        let id = e.target.getAttribute('data-id');
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
    
    if(e.target && e.target.classList.contains('btn-edit-messenger')) {
        let id = e.target.getAttribute('data-id');
        let m = document.getElementById('modal_edit_messenger');
        if(m) {
            document.getElementById('edit_messenger_id').value = id;
            m.style.display = 'flex';
        }
    }
});
`;

if (!code.includes('btn-test-messenger')) {
    code += '\n' + snippet;
    fs.writeFileSync('assets/js/portal-core.js', code);
    console.log("Appended messenger events to portal-core.js");
}
