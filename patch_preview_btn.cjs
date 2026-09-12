const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `if (distId) {
        previewBox.style.display = 'block';`;
let replacement = `if (distId) {
        previewBox.style.display = 'block';`;

let targetBlock = `    if (distId) {
        previewBox.style.display = 'block';`;

let replaceBlock = `    if (!distId) {
        if (typeof showToast === 'function') showToast('ابتدا تنظیمات توزیع را ذخیره کنید تا امکان پیش‌نمایش فراهم شود.', 'warning');
        return;
    }
    
    if (distId) {
        previewBox.style.display = 'block';`;

js = js.replace(targetBlock, replaceBlock);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched preview btn warning');
