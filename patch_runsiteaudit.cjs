const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const oldCode1 = `window.runSiteAuditViaBrowser = function() {
    window._bridgeCurrentTool = 'site_audit';
    if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
    showToast('در حال شروع ممیزی از طریق مرورگر...', 'info');
};`;

const newCode1 = `window.runSiteAuditViaBrowser = function() {
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
                        resDiv.innerHTML = '<div class="ssp-card" style="padding:16px; border:1px solid var(--border); border-radius:10px;">' + text.replace(/\\n/g, '<br>') + '</div>';
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
};`;

if (js.includes(oldCode1)) {
    js = js.replace(oldCode1, newCode1);
    fs.writeFileSync('assets/js/portal-core.js', js);
    console.log('patched runSiteAuditViaBrowser');
} else {
    console.log('not found oldCode1');
}
