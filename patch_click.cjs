const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = "if(e.target && e.target.classList.contains('btn-test-messenger')) {\n        let id = e.target.getAttribute('data-id');\n        let btn = e.target;";
let replacement = `let targetBtn = e.target.closest ? e.target.closest('.btn-test-messenger') : (e.target.classList.contains('btn-test-messenger') ? e.target : null);
    if(targetBtn) {
        let id = targetBtn.getAttribute('data-id');
        let btn = targetBtn;`;

js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched');
