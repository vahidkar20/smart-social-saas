const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const replacement = `window.applyBotTemplate = function(type) {
    if(!confirm('آیا از اعمال این الگو مطمئن هستید؟ توجه داشته باشید که ربات جدیدی با این الگو ایجاد می‌شود.')) return;
    
    let fd = new FormData();
    fd.append('action', 'ssp_apply_bot_template');
    fd.append('security', nonce);
    fd.append('template_key', type);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                showToast('الگو با موفقیت اعمال و ربات جدید ایجاد شد', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(res.data ? res.data.message : 'خطا در اعمال الگو', 'error');
            }
        }).catch(() => {
            showToast('خطا در ارتباط', 'error');
        });
};`;

code = code.replace(/window\.applyBotTemplate = function\(type\) \{[\s\S]*?(?=window\.editBot = function)/g, replacement + "\n\n");

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed applyBotTemplate stub');
