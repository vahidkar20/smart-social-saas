const fs = require('fs');
let ai = fs.readFileSync('assets/js/portal-ai.js', 'utf8');

let fixedBsGen = `window.bsGenerate = function() {
    let btn = document.getElementById('bs_gen_btn');
    if(!btn) return;
    let topic = document.getElementById('bs_topic');
    if(topic && !topic.value.trim()) { showToast('لطفا موضوع را وارد کنید', 'error'); return; }
    setBtnLoading(btn, true);
    
    let tone = document.getElementById('bs_tone');
    let type = document.getElementById('bs_type');
    let promptText = "موضوع: " + (topic ? topic.value : "") + "\\n" + "لحن: " + (tone ? tone.value : "") + "\\n" + "نوع محتوا: " + (type ? type.value : "");

    let fd = new FormData();
    fd.append('action', 'ssp_brainstorm_ideas');
    fd.append('security', nonce);
    fd.append('prompt', promptText);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            let resWrap = document.getElementById('bs_results_wrap');
            let contentWrap = document.getElementById('bs_content');
            if (res.success && resWrap && contentWrap) {
                resWrap.style.display = 'block';
                contentWrap.innerHTML = (res.data.content || '').replace(/\\n/g, '<br>');
                showToast('ایده‌ها تولید شدند', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در تولید ایده', 'error');
            }
        }).catch(err => {
            setBtnLoading(btn, false);
            showToast('خطای شبکه', 'error');
        });
};`;

ai = ai.replace(/window\.bsGenerate = function\(\) \{[\s\S]*?\}\)\.catch\(err => \{[\s\S]*?\}\);\n\};/, fixedBsGen);

fs.writeFileSync('assets/js/portal-ai.js', ai);
