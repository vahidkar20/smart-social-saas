const fs = require('fs');
let view = fs.readFileSync('includes/views/portal.php', 'utf8');
view = view.replace(
    '<button class="ssp-btn-danger" onclick="cancelAllQueue()" style="font-size:0.8rem; padding:4px 12px;">لغو همه</button>',
    `<div style="display:flex; gap:8px;">
                                        <button class="ssp-btn-primary" onclick="forceProcessQueue()" style="font-size:0.8rem; padding:4px 12px;">ارسال سریع همه (حل مشکل)</button>
                                        <button class="ssp-btn-danger" onclick="cancelAllQueue()" style="font-size:0.8rem; padding:4px 12px;">لغو همه</button>
                                    </div>`
);

let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
if (!js.includes('forceProcessQueue')) {
    js += `\n\nwindow.forceProcessQueue = function() {
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
};\n`;
}

fs.writeFileSync('includes/views/portal.php', view);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('Force process queue added');
