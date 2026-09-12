const fs = require('fs');
let ai = fs.readFileSync('assets/js/portal-ai.js', 'utf8');

let fixedPgGen = `window.pgGeneratePostWithAI = function() {
    let btn = document.getElementById('pg_ai_btn');
    if(!btn) return;
    
    let text = document.getElementById('pg_source_text');
    if(text && !text.value.trim()) { showToast('متن منبع خالی است', 'error'); return; }

    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_generate_ai_content');
    fd.append('security', nonce);
    
    let promptText = "بر اساس متن زیر یک پست شبکه‌های اجتماعی تولید کن:\\n" + (text ? text.value : "");
    fd.append('prompt', promptText);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            let contentWrap = document.getElementById('pg_post_content');
            let resWrap = document.getElementById('pg_post_editor');
            if (res.success && contentWrap) {
                if(resWrap) resWrap.style.display = 'block';
                contentWrap.value = res.data.content || '';
                showToast('پست با موفقیت تولید شد', 'success');
            } else if (res.success && res.data && res.data.mode === 'browser') {
                 showToast('لینک در پنجره جدید باز شد. لطفا اسکریپت را از افزونه مرورگر اجرا کنید', 'info');
                 window._bridgeCurrentTool = 'postgen';
                 window.openChatbotTab();
            } else {
                showToast(res.data ? res.data.message : 'خطا در تولید محتوا', 'error');
            }
        }).catch(err => {
            setBtnLoading(btn, false);
            showToast('خطای شبکه', 'error');
        });
};`;

ai = ai.replace(/window\.pgGeneratePostWithAI = function\(\) \{[\s\S]*?\}\)\.catch\(err => \{[\s\S]*?\}\);\n\};/, fixedPgGen);

fs.writeFileSync('assets/js/portal-ai.js', ai);
