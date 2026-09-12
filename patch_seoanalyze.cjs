const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const oldCode1 = `window.seoAnalyzeViaBrowser = function() {
    window._bridgeCurrentTool = 'seo';
    if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
    showToast('در حال هدایت به مرورگر جهت تحلیل سئو...', 'info');
};`;

const newCode1 = `window.seoAnalyzeViaBrowser = function() {
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
};`;

if (js.includes(oldCode1)) {
    js = js.replace(oldCode1, newCode1);
    fs.writeFileSync('assets/js/portal-core.js', js);
    console.log('patched seoAnalyzeViaBrowser');
} else {
    console.log('not found seoAnalyzeViaBrowser');
}
