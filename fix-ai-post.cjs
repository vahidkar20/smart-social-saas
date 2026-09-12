const fs = require('fs');
let ai = fs.readFileSync('assets/js/portal-ai.js', 'utf8');

let fixedCgGen = `window.cgGenerateWithAI = function() {
    let btn = document.getElementById('cg_ai_btn');
    if(!btn) return;
    
    let brief = document.getElementById('cg_ai_brief');
    let title = document.getElementById('cg_ai_title') || {value: ''};
    let tone = document.getElementById('cg_ai_tone');
    
    let promptText = "موضوع: " + title.value + "\\n" + "توضیحات: " + (brief ? brief.value : '') + "\\n" + "لحن: " + (tone ? tone.value : '');

    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_generate_ai_content');
    fd.append('security', nonce);
    fd.append('prompt', promptText);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            let resWrap = document.getElementById('cg_editor_wrap');
            let contentWrap = document.getElementById('cg_content_editor');
            if (res.success && resWrap && contentWrap) {
                resWrap.style.display = 'block';
                contentWrap.value = res.data.content || '';
                showToast('محتوا با موفقیت تولید شد', 'success');
            } else if (res.success && res.data && res.data.mode === 'browser') {
                 // Open bridge popup
                 showToast('لینک در پنجره جدید باز شد. لطفا اسکریپت را از افزونه مرورگر اجرا کنید', 'info');
                 window._bridgeCurrentTool = 'contentgen';
                 window.openChatbotTab();
            } else {
                showToast(res.data ? res.data.message : 'خطا در تولید محتوا', 'error');
            }
        }).catch(err => {
            setBtnLoading(btn, false);
            showToast('خطای شبکه', 'error');
        });
};`;

ai = ai.replace(/window\.cgGenerateWithAI = function\(\) \{[\s\S]*?\}\)\.catch\(err => \{[\s\S]*?\}\);\n\};/, fixedCgGen);

fs.writeFileSync('assets/js/portal-ai.js', ai);
