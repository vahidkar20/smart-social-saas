const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const snippet = `
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
`;

if (!code.includes('window.analyzeSeo = function')) {
    code += '\n' + snippet;
    fs.writeFileSync('assets/js/portal-core.js', code);
    console.log("Appended SEO events to portal-core.js");
}
