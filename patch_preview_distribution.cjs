const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const oldCode1 = `    let distId = id || (document.getElementById('dist_edit_id') ? document.getElementById('dist_edit_id').value : 0);

    if (!distId) {
        if (typeof showToast === 'function') showToast('ابتدا تنظیمات توزیع را ذخیره کنید تا امکان پیش‌نمایش فراهم شود.', 'warning');
        return;
    }
    
    if (distId) {`;

const newCode1 = `    let distId = id || (document.getElementById('dist_edit_id') ? document.getElementById('dist_edit_id').value : 0);
    
    if (distId) {`;

if (js.includes(oldCode1)) {
    js = js.replace(oldCode1, newCode1);
    console.log('patched previewDistribution');
} else {
    console.log('not found previewDistribution');
}

fs.writeFileSync('assets/js/portal-core.js', js);
