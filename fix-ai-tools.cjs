const fs = require('fs');

let ai = fs.readFileSync('assets/js/portal-ai.js', 'utf8');

let newAi = `
window.cgResetForm = function() {
    if(!confirm('آیا فرم ریست شود؟')) return;
    document.getElementById('cg_brief').value = '';
    document.getElementById('cg_tone').value = 'informative';
    let content = document.getElementById('cg_content_editor');
    if(content) content.value = '';
};

window.pgResetPostForm = function() {
    if(!confirm('آیا فرم ریست شود؟')) return;
    let url = document.getElementById('pg_source_url');
    if(url) url.value = '';
    let text = document.getElementById('pg_source_text');
    if(text) text.value = '';
    let content = document.getElementById('pg_post_content');
    if(content) content.value = '';
};

window.pgCopyPost = function() {
    let content = document.getElementById('pg_post_content');
    if(content && content.value) {
        navigator.clipboard.writeText(content.value);
        showToast('متن کپی شد', 'success');
    } else {
        showToast('متنی برای کپی وجود ندارد', 'error');
    }
};

window.pgSendPost = function() {
    let content = document.getElementById('pg_post_content');
    if(!content || !content.value) {
        showToast('متنی برای ارسال وجود ندارد', 'error');
        return;
    }
    let manualMsg = document.getElementById('manual_message');
    if(manualMsg) manualMsg.value = content.value;
    switchTab('manual', document.querySelector('.ssp-sidebar [data-tab="manual"]'));
    showToast('متن به بخش ارسال دستی منتقل شد', 'success');
};

window.sendCgToSeo = function() {
    let content = document.getElementById('cg_content_editor');
    if(!content || !content.value) { showToast('محتوایی وجود ندارد', 'error'); return; }
    let seoContent = document.getElementById('seo_content');
    if(seoContent) seoContent.value = content.value;
    switchTab('seo', document.querySelector('.ssp-sidebar [data-tab="seo"]'));
    showToast('محتوا به ابزار سئو منتقل شد', 'success');
};

window.sendPgToSeo = function() {
    let content = document.getElementById('pg_post_content');
    if(!content || !content.value) { showToast('محتوایی وجود ندارد', 'error'); return; }
    let seoContent = document.getElementById('seo_content');
    if(seoContent) seoContent.value = content.value;
    switchTab('seo', document.querySelector('.ssp-sidebar [data-tab="seo"]'));
    showToast('محتوا به ابزار سئو منتقل شد', 'success');
};

window.cgSaveAsDraft = function() {
    let content = document.getElementById('cg_content_editor');
    if(!content || !content.value.trim()) {
        showToast('محتوایی برای ذخیره وجود ندارد', 'error');
        return;
    }
    let fd = new FormData();
    fd.append('action', 'ssp_save_draft');
    fd.append('security', nonce);
    fd.append('title', 'مقاله تولید شده با AI');
    fd.append('content', content.value);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('به عنوان پیش‌نویس ذخیره شد', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};
`;
ai = ai + "\n" + newAi;
fs.writeFileSync('assets/js/portal-ai.js', ai);

let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

windowSettings = `
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
`;

core = core + "\n" + windowSettings;

['cgResetForm', 'pgResetPostForm', 'pgCopyPost', 'pgSendPost', 'sendCgToSeo', 'sendPgToSeo', 'cgSaveAsDraft', 'testAiConnection'].forEach(fn => {
    core = core.replace("'" + fn + "', ", "");
});

fs.writeFileSync('assets/js/portal-core.js', core);
